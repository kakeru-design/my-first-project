<?php
/**
 * 価格トラッカー: 「今日の値下げ」「割引率ランキング」「終了間近」
 *
 * - WP-Cron で1日1回(サイトのタイムゾーンで朝5時)、FANZA の人気作品を数ページ取得し、作品ごとの価格を日付付きで記録する。
 * - 記録はカスタムテーブル {prefix}fz_prices(価格の履歴)と {prefix}fz_items(表示用の作品データ)。
 * - 計算ロジック(値下げ検出・並び替え・残り時間)は DB に依存しない純粋関数に分けてある。
 *
 * ショートコード:
 *   [fanza_price_drops title="今日の値下げ" hits="8"]
 *   [fanza_discount_ranking hits="8"]
 *   [fanza_ending_soon hits="8"]
 */

defined( 'ABSPATH' ) || exit;

const FZ_PT_DB_VERSION = '1';
const FZ_PT_CRON_HOOK  = 'fz_pt_daily';

/* ==================================================================
 * 純粋関数(テスト可能な計算ロジック)
 * ================================================================== */

/** API の campaign(配列 or 単体)から最も早い終了日時を返す */
function fz_pt_campaign_end( $item ) {
	$camp = $item['campaign'] ?? null;
	if ( ! is_array( $camp ) ) {
		return null;
	}
	if ( isset( $camp['date_end'] ) ) {
		$camp = array( $camp );
	}
	$ends = array();
	foreach ( $camp as $c ) {
		if ( is_array( $c ) && ! empty( $c['date_end'] ) && false !== strtotime( $c['date_end'] ) ) {
			$ends[] = $c['date_end'];
		}
	}
	if ( ! $ends ) {
		return null;
	}
	usort( $ends, fn( $a, $b ) => strtotime( $a ) <=> strtotime( $b ) );
	return $ends[0];
}

/** 作品データ → 記録用の行 */
function fz_pt_row_from_item( $item ) {
	$pr = fz_item_prices( $item );
	return array(
		'content_id'   => (string) ( $item['content_id'] ?? '' ),
		'price'        => $pr['price'],
		'list_price'   => $pr['list'] ?: $pr['price'],
		'campaign_end' => fz_pt_campaign_end( $item ),
		'item'         => $item,
	);
}

function fz_pt_off_rate( $price, $list ) {
	return ( $list > $price && $price > 0 ) ? (int) round( ( 1 - $price / $list ) * 100 ) : 0;
}

/**
 * 前回記録より値下がりした行。値下げ率 → 値下げ額の順に並べる。
 *
 * @param array $rows 最新日の行
 * @param array $prev content_id => 前回の価格
 */
function fz_pt_drops( array $rows, array $prev ) {
	$out = array();
	foreach ( $rows as $r ) {
		$before = (int) ( $prev[ $r['content_id'] ] ?? 0 );
		if ( $before > 0 && $r['price'] > 0 && $r['price'] < $before ) {
			$r['prev_price'] = $before;
			$r['drop']       = $before - $r['price'];
			$r['drop_rate']  = (int) round( $r['drop'] / $before * 100 );
			$out[]           = $r;
		}
	}
	usort( $out, fn( $a, $b ) => array( $b['drop_rate'], $b['drop'] ) <=> array( $a['drop_rate'], $a['drop'] ) );
	return $out;
}

/** 定価に対する割引率の高い順(割引なしは除外)。同率なら安い順。 */
function fz_pt_discount_ranking( array $rows ) {
	$out = array();
	foreach ( $rows as $r ) {
		$r['off'] = fz_pt_off_rate( $r['price'], $r['list_price'] );
		if ( $r['off'] > 0 ) {
			$out[] = $r;
		}
	}
	usort( $out, fn( $a, $b ) => array( $b['off'], $a['price'] ) <=> array( $a['off'], $b['price'] ) );
	return $out;
}

/** セール終了が近い順(終了済み・キャンペーン情報なしは除外) */
function fz_pt_ending_soon( array $rows, $now_ts ) {
	$out = array();
	foreach ( $rows as $r ) {
		$end = $r['campaign_end'] ? fz_pt_to_ts( $r['campaign_end'] ) : 0;
		if ( $end > $now_ts ) {
			$r['end_ts'] = $end;
			$out[]       = $r;
		}
	}
	usort( $out, fn( $a, $b ) => $a['end_ts'] <=> $b['end_ts'] );
	return $out;
}

/** API の日時(日本時間)をタイムスタンプに */
function fz_pt_to_ts( $datetime ) {
	try {
		return ( new DateTimeImmutable( $datetime, new DateTimeZone( 'Asia/Tokyo' ) ) )->getTimestamp();
	} catch ( Exception $e ) {
		return 0;
	}
}

function fz_pt_remaining_label( $end_ts, $now_ts ) {
	$sec = $end_ts - $now_ts;
	if ( $sec <= 0 ) {
		return '終了';
	}
	if ( $sec < DAY_IN_SECONDS ) {
		return 'あと' . max( 1, (int) floor( $sec / HOUR_IN_SECONDS ) ) . '時間';
	}
	return 'あと' . (int) floor( $sec / DAY_IN_SECONDS ) . '日';
}

/* ==================================================================
 * DB
 * ================================================================== */

function fz_pt_tables() {
	global $wpdb;
	return array( $wpdb->prefix . 'fz_prices', $wpdb->prefix . 'fz_items' );
}

function fz_pt_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	[ $prices, $items ] = fz_pt_tables();
	$charset            = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$prices} (
  content_id varchar(64) NOT NULL,
  recorded_on date NOT NULL,
  price int(10) unsigned NOT NULL DEFAULT 0,
  list_price int(10) unsigned NOT NULL DEFAULT 0,
  campaign_end datetime NULL DEFAULT NULL,
  PRIMARY KEY  (content_id,recorded_on),
  KEY recorded_on (recorded_on)
) {$charset};
CREATE TABLE {$items} (
  content_id varchar(64) NOT NULL,
  data longtext NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (content_id),
  KEY updated_at (updated_at)
) {$charset};"
	);
	update_option( 'fz_pt_db_version', FZ_PT_DB_VERSION, false );
}

/** 毎日 5:00(サイトのタイムゾーン)に実行するよう登録 */
function fz_pt_schedule() {
	if ( wp_next_scheduled( FZ_PT_CRON_HOOK ) ) {
		return;
	}
	$tz   = wp_timezone();
	$next = new DateTimeImmutable( 'today 05:00', $tz );
	if ( $next <= new DateTimeImmutable( 'now', $tz ) ) {
		$next = $next->modify( '+1 day' );
	}
	wp_schedule_event( $next->getTimestamp(), 'daily', FZ_PT_CRON_HOOK );
}

register_activation_hook(
	FZ_PLUGIN_FILE,
	function () {
		fz_pt_install();
		fz_pt_schedule();
	}
);
register_deactivation_hook(
	FZ_PLUGIN_FILE,
	function () {
		wp_clear_scheduled_hook( FZ_PT_CRON_HOOK );
	}
);

add_action(
	'init',
	function () {
		if ( get_option( 'fz_pt_db_version' ) !== FZ_PT_DB_VERSION ) {
			fz_pt_install();
		}
		fz_pt_schedule();
	}
);

add_action( FZ_PT_CRON_HOOK, 'fz_pt_run' );

/**
 * 価格を取得して記録する。
 *
 * @return int|WP_Error 記録した作品数
 */
function fz_pt_run() {
	global $wpdb;
	if ( ! fz_is_configured() ) {
		return fz_pt_set_status( new WP_Error( 'fz_not_configured', 'API ID / アフィリエイトID が未設定です。' ) );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$pages   = (int) fz_settings()['track_pages'];
	$queries = array();
	for ( $p = 0; $p < $pages; $p++ ) {
		$queries[] = array( 'sort' => 'rank', 'offset' => 1 + $p * 100 );
	}
	$queries[] = array( 'sort' => 'date', 'offset' => 1 ); // 新作も1ページ分

	$collected = array();
	$rank_done = false;
	foreach ( $queries as $i => $q ) {
		if ( $rank_done && 'rank' === $q['sort'] ) {
			continue;
		}
		$r = fz_api(
			'ItemList',
			array_merge(
				array(
					'site'    => 'FANZA',
					'service' => 'digital',
					'floor'   => 'videoa',
					'hits'    => 100,
				),
				$q
			),
			true
		);
		if ( is_wp_error( $r ) ) {
			if ( 0 === $i ) {
				return fz_pt_set_status( $r );
			}
			continue;
		}
		$items = $r['items'] ?? array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['content_id'] ) ) {
				$collected[ $item['content_id'] ] = $item;
			}
		}
		if ( 'rank' === $q['sort'] && count( $items ) < 100 ) {
			$rank_done = true; // 人気順の最終ページに達した
		}
	}

	[ $prices, $items_table ] = fz_pt_tables();
	$today                    = wp_date( 'Y-m-d' );
	$now                      = current_time( 'mysql' );
	foreach ( $collected as $item ) {
		$row = fz_pt_row_from_item( $item );
		if ( $row['price'] <= 0 ) {
			continue;
		}
		$wpdb->replace(
			$prices,
			array(
				'content_id'   => $row['content_id'],
				'recorded_on'  => $today,
				'price'        => $row['price'],
				'list_price'   => $row['list_price'],
				'campaign_end' => $row['campaign_end'],
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);
		$wpdb->replace(
			$items_table,
			array(
				'content_id' => $row['content_id'],
				'data'       => wp_json_encode( $item ),
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s' )
		);
	}

	// 古い記録の削除
	$keep   = (int) apply_filters( 'fz_pt_retention_days', 60 );
	$cutoff = wp_date( 'Y-m-d', time() - $keep * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$prices} WHERE recorded_on < %s", $cutoff ) ); // phpcs:ignore WordPress.DB
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$items_table} WHERE updated_at < %s", $cutoff . ' 00:00:00' ) ); // phpcs:ignore WordPress.DB

	return fz_pt_set_status( count( $collected ) );
}

/** @param int|WP_Error $result */
function fz_pt_set_status( $result ) {
	$status = (array) get_option( 'fz_pt_status', array() );
	if ( is_wp_error( $result ) ) {
		$status['error']    = $result->get_error_message();
		$status['error_at'] = time();
	} else {
		$status = array(
			'last_run' => time(),
			'count'    => (int) $result,
			'error'    => '',
		);
	}
	update_option( 'fz_pt_status', $status, false );
	return $result;
}

/**
 * 最新の記録日の行と、各作品の前回価格。
 *
 * @return array{date: ?string, rows: array, prev: array}
 */
function fz_pt_snapshot() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$pre = apply_filters( 'pre_fz_pt_snapshot', null );
	if ( null !== $pre ) {
		return $cache = $pre;
	}

	global $wpdb;
	[ $prices, $items ] = fz_pt_tables();
	$empty              = array( 'date' => null, 'rows' => array(), 'prev' => array() );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prices ) ) !== $prices ) {
		return $cache = $empty;
	}
	$date = $wpdb->get_var( "SELECT MAX(recorded_on) FROM {$prices}" ); // phpcs:ignore WordPress.DB
	if ( ! $date ) {
		return $cache = $empty;
	}

	$rows = array();
	// phpcs:ignore WordPress.DB
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT p.content_id, p.price, p.list_price, p.campaign_end, i.data FROM {$prices} p INNER JOIN {$items} i ON i.content_id = p.content_id WHERE p.recorded_on = %s", $date ), ARRAY_A ) as $r ) {
		$item = json_decode( $r['data'], true );
		if ( ! is_array( $item ) ) {
			continue;
		}
		$rows[] = array(
			'content_id'   => $r['content_id'],
			'price'        => (int) $r['price'],
			'list_price'   => (int) $r['list_price'],
			'campaign_end' => $r['campaign_end'],
			'item'         => $item,
		);
	}

	$prev = array();
	// phpcs:ignore WordPress.DB
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT p.content_id, p.price FROM {$prices} p INNER JOIN (SELECT content_id, MAX(recorded_on) AS d FROM {$prices} WHERE recorded_on < %s GROUP BY content_id) m ON m.content_id = p.content_id AND m.d = p.recorded_on", $date ), ARRAY_A ) as $r ) {
		$prev[ $r['content_id'] ] = (int) $r['price'];
	}

	return $cache = array( 'date' => $date, 'rows' => $rows, 'prev' => $prev );
}

/* ==================================================================
 * 表示
 * ================================================================== */

/**
 * 共通の前処理。表示できない場合は管理者向けの案内(一般閲覧者には空文字)を返す。
 *
 * @return array|string
 */
function fz_pt_prepare() {
	if ( ! fz_is_configured() && null === apply_filters( 'pre_fz_pt_snapshot', null ) ) {
		return fz_admin_notice_html( 'API ID / アフィリエイトID が未設定のため、値下げ情報を表示できません(設定 → FANZA連携)。' );
	}
	$snap = fz_pt_snapshot();
	if ( ! $snap['date'] ) {
		return fz_admin_notice_html( '価格の記録がまだありません。設定 → FANZA連携 の「価格を今すぐ取得」を押すか、翌朝の自動取得をお待ちください。' );
	}
	return $snap;
}

function fz_pt_section_html( $atts, array $cards ) {
	$head = $atts['title'] ? fz_section_head_html( $atts['title'], $atts['sub'], $atts['more'] ?? '' ) : '';
	return '<section class="fz-section fz-pt-section">' . $head . '<div class="fz-items">' . implode( '', $cards ) . '</div></section>';
}

function fz_pt_atts( $atts, $title, $sub ) {
	$a         = shortcode_atts(
		array(
			'title' => $title,
			'sub'   => $sub,
			'hits'  => 8,
			'more'  => '',
		),
		$atts
	);
	$a['hits'] = max( 1, min( 100, (int) $a['hits'] ) );
	return $a;
}

function fz_pt_date_label( $date ) {
	$t = strtotime( $date );
	return $t ? wp_date( 'n/j', $t, wp_timezone() ) . '更新' : '';
}

add_shortcode(
	'fanza_price_drops',
	function ( $atts ) {
		$snap = fz_pt_prepare();
		if ( ! is_array( $snap ) ) {
			return $snap;
		}
		$a     = fz_pt_atts( $atts, '今日の値下げ', '前回より安くなった作品(' . fz_pt_date_label( $snap['date'] ) . ')' );
		$drops = array_slice( fz_pt_drops( $snap['rows'], $snap['prev'] ), 0, $a['hits'] );
		if ( ! $drops ) {
			return fz_admin_notice_html( '前回の記録より値下げされた作品はありません(記録が1日分だけの場合は、翌日以降に表示されます)。' );
		}
		$cards = array();
		foreach ( $drops as $r ) {
			$badge   = '<span class="fz-badge fz-badge-drop">' . esc_html( number_format( $r['drop'] ) ) . '円値下げ</span>'
				. '<span class="fz-badge fz-badge-prev">前回 ' . esc_html( number_format( $r['prev_price'] ) ) . '円</span>';
			$cards[] = fz_item_card_html( $r['item'], $badge );
		}
		return fz_pt_section_html( $a, $cards );
	}
);

add_shortcode(
	'fanza_discount_ranking',
	function ( $atts ) {
		$snap = fz_pt_prepare();
		if ( ! is_array( $snap ) ) {
			return $snap;
		}
		$a    = fz_pt_atts( $atts, '割引率ランキング', '定価から安くなっている順(' . fz_pt_date_label( $snap['date'] ) . ')' );
		$list = array_slice( fz_pt_discount_ranking( $snap['rows'] ), 0, $a['hits'] );
		if ( ! $list ) {
			return fz_admin_notice_html( '割引中の作品が見つかりませんでした。' );
		}
		$cards = array();
		foreach ( $list as $i => $r ) {
			$cards[] = fz_item_card_html( $r['item'], '<span class="fz-badge fz-badge-rank">' . (int) ( $i + 1 ) . '位</span>' );
		}
		return fz_pt_section_html( $a, $cards );
	}
);

add_shortcode(
	'fanza_ending_soon',
	function ( $atts ) {
		$snap = fz_pt_prepare();
		if ( ! is_array( $snap ) ) {
			return $snap;
		}
		$a    = fz_pt_atts( $atts, 'もうすぐセール終了', '終わる前にチェック' );
		$now  = time();
		$list = array_slice( fz_pt_ending_soon( $snap['rows'], $now ), 0, $a['hits'] );
		if ( ! $list ) {
			return fz_admin_notice_html( '終了日時が分かるセール作品はありません。' );
		}
		$cards = array();
		foreach ( $list as $r ) {
			$cards[] = fz_item_card_html( $r['item'], '<span class="fz-badge fz-badge-time">' . esc_html( fz_pt_remaining_label( $r['end_ts'], $now ) ) . '</span>' );
		}
		return fz_pt_section_html( $a, $cards );
	}
);

/* ==================================================================
 * 管理画面
 * ================================================================== */

add_action(
	'fz_settings_page_after',
	function () {
		global $wpdb;
		$status = (array) get_option( 'fz_pt_status', array() );
		[ $prices ] = fz_pt_tables();
		$rows       = 0;
		$days       = 0;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prices ) ) === $prices ) {
			$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prices}" ); // phpcs:ignore WordPress.DB
			$days = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT recorded_on) FROM {$prices}" ); // phpcs:ignore WordPress.DB
		}
		$next = wp_next_scheduled( FZ_PT_CRON_HOOK );
		$flag = isset( $_GET['fz_pt'] ) ? sanitize_key( $_GET['fz_pt'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<h2>価格の記録(今日の値下げ・終了間近)</h2>
		<?php if ( 'ok' === $flag ) : ?>
			<div class="notice notice-success"><p>価格を取得しました。</p></div>
		<?php elseif ( 'error' === $flag ) : ?>
			<div class="notice notice-error"><p>価格の取得に失敗しました: <?php echo esc_html( $status['error'] ?? '' ); ?></p></div>
		<?php endif; ?>
		<table class="form-table">
			<tr><th>最終取得</th><td><?php echo ! empty( $status['last_run'] ) ? esc_html( wp_date( 'Y年n月j日 H:i', $status['last_run'] ) . '(' . number_format( (int) $status['count'] ) . '作品)' ) : 'まだ取得していません'; ?></td></tr>
			<tr><th>記録件数</th><td><?php echo esc_html( number_format( $rows ) . '件(' . $days . '日分)' ); ?></td></tr>
			<tr><th>次回の自動取得</th><td><?php echo $next ? esc_html( wp_date( 'Y年n月j日 H:i', $next ) ) : '未登録'; ?></td></tr>
			<?php if ( ! empty( $status['error'] ) ) : ?>
				<tr><th>直近のエラー</th><td style="color:#b32d2e"><?php echo esc_html( $status['error'] ); ?></td></tr>
			<?php endif; ?>
		</table>
		<p class="description">値下げは「前回の記録」との比較で判定します。最初の取得の翌日以降に表示されます。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fz_pt_run">
			<?php wp_nonce_field( 'fz_pt_run' ); ?>
			<?php submit_button( '価格を今すぐ取得', 'primary' ); ?>
		</form>
		<?php
	}
);

add_action(
	'admin_post_fz_pt_run',
	function () {
		check_admin_referer( 'fz_pt_run' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		if ( get_option( 'fz_pt_db_version' ) !== FZ_PT_DB_VERSION ) {
			fz_pt_install();
		}
		$r = fz_pt_run();
		wp_safe_redirect( admin_url( 'options-general.php?page=fanza-connector&fz_pt=' . ( is_wp_error( $r ) ? 'error' : 'ok' ) ) );
		exit;
	}
);
