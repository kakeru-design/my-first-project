<?php
/**
 * Plugin Name: FANZA Connector
 * Description: FANZA(DMM Webサービス API v3)から女優・作品情報を取得して表示する。トップの「イチオシ女優」は 設定 → FANZA連携 から差し替え可能。
 * Version: 0.1.0
 * Requires PHP: 8.0
 *
 * ショートコード:
 *   [fanza_featured]                                  イチオシ女優(管理画面で選んだ人)
 *   [fanza_items sort="rank" hits="12"]              作品一覧(人気順)
 *   [fanza_items actress_id="12345" sort="date"]     特定女優の新着作品
 *   [fanza_items keyword="キーワード" hits="8"]       キーワード検索
 */

defined( 'ABSPATH' ) || exit;

const FZ_OPTION       = 'fanza_connector';
const FZ_MAX_FEATURED = 8;
const FZ_API_BASE     = 'https://api.dmm.com/affiliate/v3/';
const FZ_PLUGIN_FILE  = __FILE__;

require_once __DIR__ . '/includes/price-tracker.php';

/* ==================================================================
 * 設定
 * ================================================================== */

function fz_settings() {
	$defaults = array(
		'api_id'         => '',
		'affiliate_id'   => '',
		'cache_hours'    => 6,
		'featured_title' => '今月のイチオシ女優',
		'featured'       => array(),
		'track_pages'    => 5,
	);
	return wp_parse_args( (array) get_option( FZ_OPTION, array() ), $defaults );
}

function fz_is_configured() {
	$s = fz_settings();
	return '' !== $s['api_id'] && '' !== $s['affiliate_id'];
}

/** 表示対象のイチオシ女優(空行を除く) */
function fz_featured_rows() {
	$rows = array_filter(
		(array) fz_settings()['featured'],
		function ( $r ) {
			return ! empty( $r['actress_id'] ) || ! empty( $r['name'] );
		}
	);
	return array_slice( array_values( $rows ), 0, FZ_MAX_FEATURED );
}

/* ==================================================================
 * API クライアント(キャッシュ・障害時は前回の結果で代替)
 * ================================================================== */

/**
 * @param bool $fresh true ならキャッシュを使わずに取得(価格記録用)
 * @return array|WP_Error API の result 部分
 */
function fz_api( $endpoint, array $params, $fresh = false ) {
	$pre = apply_filters( 'pre_fz_api', null, $endpoint, $params );
	if ( null !== $pre ) {
		return $pre;
	}
	if ( ! fz_is_configured() ) {
		return new WP_Error( 'fz_not_configured', 'FANZA の API ID / アフィリエイトID が未設定です(設定 → FANZA連携)。' );
	}

	$s      = fz_settings();
	$params = array_merge(
		array(
			'api_id'       => $s['api_id'],
			'affiliate_id' => $s['affiliate_id'],
			'output'       => 'json',
		),
		array_filter( $params, fn( $v ) => '' !== $v && null !== $v )
	);
	ksort( $params );
	$url = FZ_API_BASE . $endpoint . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	$key = 'fz_' . md5( $url );

	$cached = $fresh ? false : get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}

	$backup = get_transient( $key . '_bak' );
	if ( ! $fresh && get_transient( $key . '_err' ) ) {
		// 直近で失敗している間はAPIを叩かない
		return false !== $backup ? $backup : new WP_Error( 'fz_api', 'FANZA API に一時的に接続できません。' );
	}

	$res    = wp_remote_get( $url, array( 'timeout' => 10 ) );
	$code   = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$body   = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
	$result = is_array( $body ) && isset( $body['result'] ) ? $body['result'] : null;

	if ( 200 !== $code || ! is_array( $result ) || ( isset( $result['status'] ) && 200 !== (int) $result['status'] ) ) {
		set_transient( $key . '_err', 1, 5 * MINUTE_IN_SECONDS );
		if ( false !== $backup ) {
			return $backup;
		}
		$msg = is_wp_error( $res ) ? $res->get_error_message() : ( $result['message'] ?? ( 'HTTP ' . $code ) );
		return new WP_Error( 'fz_api', 'FANZA API の取得に失敗しました: ' . ( is_array( $msg ) ? wp_json_encode( $msg ) : $msg ) );
	}

	set_transient( $key, $result, HOUR_IN_SECONDS * max( 1, (int) $s['cache_hours'] ) );
	set_transient( $key . '_bak', $result, WEEK_IN_SECONDS );
	return $result;
}

function fz_flush_cache() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_fz\\_%' OR option_name LIKE '\\_transient\\_timeout\\_fz\\_%'" ); // phpcs:ignore WordPress.DB
}

function fz_actress( $actress_id ) {
	$r = fz_api( 'ActressSearch', array( 'actress_id' => (string) $actress_id ) );
	return is_wp_error( $r ) ? null : ( $r['actress'][0] ?? null );
}

/** @return array|WP_Error */
function fz_items( array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'site'    => 'FANZA',
			'service' => 'digital',
			'floor'   => 'videoa',
			'hits'    => 12,
			'sort'    => 'rank',
		)
	);
	$r = fz_api( 'ItemList', $args );
	return is_wp_error( $r ) ? $r : ( $r['items'] ?? array() );
}

/** 女優の代表作(content_id 指定があればそれ、なければ人気1位) */
function fz_pick_item( $actress_id, $content_id = '' ) {
	$args  = $content_id
		? array( 'cid' => $content_id, 'hits' => 1 )
		: array( 'article' => 'actress', 'article_id' => (string) $actress_id, 'hits' => 1, 'sort' => 'rank' );
	$items = $actress_id || $content_id ? fz_items( $args ) : array();
	return is_wp_error( $items ) ? null : ( $items[0] ?? null );
}

/* ==================================================================
 * 表示
 * ================================================================== */

function fz_admin_notice_html( $message ) {
	return current_user_can( 'manage_options' )
		? '<p class="fz-notice">[FANZA連携] ' . esc_html( $message ) . '</p>'
		: '';
}

function fz_link_attrs() {
	return 'rel="sponsored nofollow noopener" target="_blank"';
}

function fz_featured_html() {
	$rows = fz_featured_rows();
	if ( ! $rows ) {
		return fz_admin_notice_html( 'イチオシ女優が未設定です。設定 → FANZA連携 から登録してください。' );
	}

	$out = '<section class="fz-featured">'
		. fz_section_head_html( fz_settings()['featured_title'], '編集部おすすめの女優と代表作' )
		. '<div class="fz-featured-list">';

	foreach ( $rows as $i => $row ) {
		$actress = ! empty( $row['actress_id'] ) ? fz_actress( $row['actress_id'] ) : null;
		$item    = fz_pick_item( $row['actress_id'] ?? '', $row['content_id'] ?? '' );
		$name    = $row['name'] ?: ( $actress['name'] ?? '' );
		$photo   = $row['image'] ?: ( $actress['imageURL']['large'] ?? ( $actress['imageURL']['small'] ?? '' ) );
		$more    = $actress['listURL']['digital'] ?? ( $item['affiliateURL'] ?? '' );

		$out .= '<article class="fz-card">';
		$out .= '<div class="fz-card-photo">';
		$out .= '<span class="fz-card-badge">PICK UP ' . ( $i + 1 ) . '</span>';
		if ( $photo ) {
			$out .= '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" loading="' . ( $i < 2 ? 'eager' : 'lazy' ) . '">';
		}
		$out .= '</div><div class="fz-card-body">';
		$out .= '<h3 class="fz-card-name">' . esc_html( $name ) . '</h3>';
		if ( ! empty( $actress['ruby'] ) ) {
			$out .= '<p class="fz-card-ruby">' . esc_html( $actress['ruby'] ) . '</p>';
		}
		if ( ! empty( $row['comment'] ) ) {
			$out .= '<p class="fz-card-comment">' . nl2br( esc_html( $row['comment'] ) ) . '</p>';
		}
		if ( $item ) {
			$out .= '<a class="fz-card-item" href="' . esc_url( $item['affiliateURL'] ) . '" ' . fz_link_attrs() . '>'
				. '<img src="' . esc_url( $item['imageURL']['list'] ?? ( $item['imageURL']['small'] ?? '' ) ) . '" alt="" loading="lazy">'
				. '<span><span class="fz-card-item-label">おすすめ作品</span>' . esc_html( wp_trim_words( $item['title'], 40, '…' ) ) . '</span></a>';
		}
		if ( $more ) {
			$out .= '<a class="atk-button fz-card-cta" href="' . esc_url( $more ) . '" ' . fz_link_attrs() . '>出演作品をもっと見る</a>';
		}
		$out .= '</div></article>';
	}

	return $out . '</div></section>';
}

function fz_items_html( $items ) {
	if ( is_wp_error( $items ) ) {
		return fz_admin_notice_html( $items->get_error_message() );
	}
	if ( ! $items ) {
		return '<p>作品が見つかりませんでした。</p>';
	}
	$out = '<div class="fz-items">';
	foreach ( $items as $item ) {
		$out .= fz_item_card_html( $item );
	}
	return $out . '</div>';
}

/** "1,980~" のような文字列から数値を取り出す */
function fz_price_int( $v ) {
	return (int) preg_replace( '/\D/', '', strtok( (string) $v, '~' ) );
}

/** @return array{price:int,list:int,off:int} */
function fz_item_prices( $item ) {
	$p     = $item['prices'] ?? array();
	$price = fz_price_int( $p['price'] ?? '' );
	$list  = fz_price_int( $p['list_price'] ?? '' );
	// 購入(ダウンロード)価格があればそちらを優先
	foreach ( (array) ( $p['deliveries']['delivery'] ?? array() ) as $d ) {
		if ( in_array( $d['type'] ?? '', array( 'download', 'hd', '4k' ), true ) && ! empty( $d['price'] ) ) {
			$price = fz_price_int( $d['price'] );
			$list  = fz_price_int( $d['list_price'] ?? $list );
			break;
		}
	}
	$off = ( $list > $price && $price > 0 ) ? (int) round( ( 1 - $price / $list ) * 100 ) : 0;
	return array( 'price' => $price, 'list' => $list, 'off' => $off );
}

function fz_stars_html( $avg, $count = 0 ) {
	$avg = (float) $avg;
	if ( $avg <= 0 ) {
		return '';
	}
	$full = (int) round( $avg );
	return '<p class="fz-rating"><span class="fz-stars">' . str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full ) . '</span> '
		. esc_html( number_format( $avg, 1 ) ) . ( $count ? '<span class="fz-rating-count">(' . (int) $count . '件)</span>' : '' ) . '</p>';
}

/**
 * @param string $badge カード上部に出す追加バッジのHTML(呼び出し側でエスケープ済み)
 */
function fz_item_card_html( $item, $badge = '' ) {
	$url       = $item['affiliateURL'] ?? '';
	$actresses = array_column( $item['iteminfo']['actress'] ?? array(), 'name' );
	$genres    = array_slice( array_column( $item['iteminfo']['genre'] ?? array(), 'name' ), 0, 3 );
	$pr        = fz_item_prices( $item );
	$img       = $item['imageURL']['large'] ?? ( $item['imageURL']['list'] ?? '' );

	$out  = '<article class="fz-item">';
	$out .= '<a class="fz-item-link" href="' . esc_url( $url ) . '" ' . fz_link_attrs() . '>';
	$out .= '<div class="fz-item-thumb">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '' ) . '</div>';
	$out .= '<div class="fz-item-body">';
	if ( $badge ) {
		$out .= '<div class="fz-badges">' . $badge . '</div>';
	}
	if ( $pr['off'] ) {
		$out .= '<span class="fz-off">' . (int) $pr['off'] . '%OFF</span>';
	}
	$out .= '<h3 class="fz-item-title">' . esc_html( $item['title'] ?? '' ) . '</h3>';
	if ( $actresses ) {
		$names = array_slice( $actresses, 0, 3 );
		$out  .= '<p class="fz-item-actress">出演:' . esc_html( implode( '・', $names ) . ( count( $actresses ) > 3 ? '…' : '' ) ) . '</p>';
	}
	if ( $pr['price'] ) {
		$out .= '<p class="fz-price">' . ( $pr['off'] ? '<del>' . number_format( $pr['list'] ) . '円</del> ' : '' )
			. '<strong>' . number_format( $pr['price'] ) . '円</strong>' . ( false !== strpos( (string) ( $item['prices']['price'] ?? '' ), '~' ) ? '〜' : '' ) . '</p>';
	}
	$out .= fz_stars_html( $item['review']['average'] ?? 0, $item['review']['count'] ?? 0 );
	if ( ! empty( $item['maker_product'] ) || ! empty( $item['content_id'] ) ) {
		$out .= '<p class="fz-code">' . esc_html( strtoupper( $item['maker_product'] ?? $item['content_id'] ) ) . '</p>';
	}
	if ( $genres ) {
		$out .= '<ul class="fz-tags">';
		foreach ( $genres as $g ) {
			$out .= '<li>#' . esc_html( $g ) . '</li>';
		}
		$out .= '</ul>';
	}
	$out .= '</div></a>';
	$out .= '<a class="fz-item-cta" href="' . esc_url( $url ) . '" ' . fz_link_attrs() . '>FANZAで見る<small>PR</small></a>';
	return $out . '</article>';
}

/** セクション見出し(タイトル+サブタイトル+もっと見る) */
function fz_section_head_html( $title, $sub = '', $more_url = '' ) {
	return '<div class="fz-section-head"><div><h2 class="fz-section-title">' . esc_html( $title ) . '</h2>'
		. ( $sub ? '<p class="fz-section-sub">' . esc_html( $sub ) . '</p>' : '' ) . '</div>'
		. ( $more_url ? '<a class="fz-more" href="' . esc_url( $more_url ) . '">もっと見る ›</a>' : '' ) . '</div>';
}

add_shortcode( 'fanza_featured', 'fz_featured_html' );

add_shortcode(
	'fanza_items',
	function ( $atts ) {
		$a    = shortcode_atts(
			array(
				'keyword'    => '',
				'actress_id' => '',
				'genre_id'   => '',
				'sort'       => 'rank',
				'hits'       => 12,
				'floor'      => 'videoa',
				'title'      => '',
				'sub'        => '',
				'more'       => '',
			),
			$atts
		);
		$args = array(
			'keyword' => $a['keyword'],
			'sort'    => in_array( $a['sort'], array( 'rank', 'date', 'review', 'price', '-price', 'match' ), true ) ? $a['sort'] : 'rank',
			'hits'    => max( 1, min( 100, (int) $a['hits'] ) ),
			'floor'   => preg_replace( '/[^a-z_]/', '', $a['floor'] ),
		);
		if ( $a['actress_id'] ) {
			$args['article']    = 'actress';
			$args['article_id'] = preg_replace( '/\D/', '', $a['actress_id'] );
		} elseif ( $a['genre_id'] ) {
			$args['article']    = 'genre';
			$args['article_id'] = preg_replace( '/\D/', '', $a['genre_id'] );
		}
		$head = $a['title'] ? fz_section_head_html( $a['title'], $a['sub'], $a['more'] ) : '';
		return '<section class="fz-section">' . $head . fz_items_html( fz_items( $args ) ) . '</section>';
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'fanza-connector', plugins_url( 'assets/fanza.css', __FILE__ ), array(), '0.1.0' );
	}
);

/* DMM Webサービスの利用規約で必要なクレジット表記 */
add_action(
	'wp_footer',
	function () {
		echo '<p class="fz-credit"><a href="https://affiliate.dmm.com/api/" target="_blank" rel="noopener">Powered by FANZA Webサービス</a></p>';
	},
	5
);

/* ==================================================================
 * 管理画面(設定 → FANZA連携)
 * ================================================================== */

add_action(
	'admin_init',
	function () {
		register_setting( 'fanza_connector', FZ_OPTION, array( 'sanitize_callback' => 'fz_sanitize' ) );
	}
);

function fz_sanitize( $input ) {
	$input = (array) $input;
	$out   = array(
		'api_id'         => sanitize_text_field( $input['api_id'] ?? '' ),
		'affiliate_id'   => preg_replace( '/[^A-Za-z0-9_-]/', '', $input['affiliate_id'] ?? '' ),
		'cache_hours'    => max( 1, min( 48, (int) ( $input['cache_hours'] ?? 6 ) ) ),
		'track_pages'    => max( 1, min( 20, (int) ( $input['track_pages'] ?? 5 ) ) ),
		'featured_title' => sanitize_text_field( $input['featured_title'] ?? '' ),
		'featured'       => array(),
	);
	foreach ( array_slice( (array) ( $input['featured'] ?? array() ), 0, FZ_MAX_FEATURED ) as $row ) {
		$out['featured'][] = array(
			'actress_id' => preg_replace( '/\D/', '', $row['actress_id'] ?? '' ),
			'name'       => sanitize_text_field( $row['name'] ?? '' ),
			'comment'    => sanitize_textarea_field( $row['comment'] ?? '' ),
			'image'      => esc_url_raw( $row['image'] ?? '' ),
			'content_id' => preg_replace( '/[^a-z0-9_]/', '', strtolower( $row['content_id'] ?? '' ) ),
		);
	}
	fz_flush_cache();
	return $out;
}

add_action(
	'admin_menu',
	function () {
		$hook = add_options_page( 'FANZA連携', 'FANZA連携', 'manage_options', 'fanza-connector', 'fz_render_settings_page' );
		add_action(
			'admin_enqueue_scripts',
			function ( $current ) use ( $hook ) {
				if ( $current === $hook ) {
					wp_enqueue_media();
				}
			}
		);
	}
);

add_action(
	'admin_post_fz_flush',
	function () {
		check_admin_referer( 'fz_flush' );
		if ( current_user_can( 'manage_options' ) ) {
			fz_flush_cache();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=fanza-connector&flushed=1' ) );
		exit;
	}
);

function fz_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s      = fz_settings();
	$search = isset( $_GET['fz_search'] ) ? sanitize_text_field( wp_unslash( $_GET['fz_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$rows   = array_pad( array_values( (array) $s['featured'] ), 5, array() );
	if ( count( $rows ) < FZ_MAX_FEATURED ) {
		$rows[] = array(); // 予備の空行
	}
	$name = FZ_OPTION;
	?>
	<div class="wrap">
		<h1>FANZA連携</h1>
		<?php if ( isset( $_GET['flushed'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p>キャッシュを削除しました。</p></div>
		<?php endif; ?>

		<h2>女優IDを調べる</h2>
		<form method="get">
			<input type="hidden" name="page" value="fanza-connector">
			<input type="search" name="fz_search" value="<?php echo esc_attr( $search ); ?>" placeholder="女優名(ひらがな可)" class="regular-text">
			<?php submit_button( '検索', 'secondary', '', false ); ?>
		</form>
		<?php
		if ( '' !== $search ) {
			$r = fz_api( 'ActressSearch', array( 'keyword' => $search, 'hits' => 20 ) );
			if ( is_wp_error( $r ) ) {
				echo '<p style="color:#b32d2e">' . esc_html( $r->get_error_message() ) . '</p>';
			} elseif ( empty( $r['actress'] ) ) {
				echo '<p>見つかりませんでした。</p>';
			} else {
				echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>写真</th><th>女優ID</th><th>名前</th></tr></thead><tbody>';
				foreach ( $r['actress'] as $a ) {
					printf(
						'<tr><td>%s</td><td><code>%s</code></td><td>%s<br><small>%s</small></td></tr>',
						empty( $a['imageURL']['small'] ) ? '' : '<img src="' . esc_url( $a['imageURL']['small'] ) . '" width="50" alt="">',
						esc_html( $a['id'] ),
						esc_html( $a['name'] ),
						esc_html( $a['ruby'] ?? '' )
					);
				}
				echo '</tbody></table>';
			}
		}
		?>

		<form method="post" action="options.php">
			<?php settings_fields( 'fanza_connector' ); ?>

			<h2>イチオシ女優(トップページに上から順に表示)</h2>
			<p class="description">
				女優IDを入れると、宣材写真・名前・人気1位の作品を FANZA から自動取得します。
				「写真」「名前」「おすすめ作品ID」を入れた場合はそちらを優先します。行を空にすると非表示になります。
			</p>
			<p><label>見出し <input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[featured_title]" value="<?php echo esc_attr( $s['featured_title'] ); ?>"></label></p>
			<table class="widefat striped fz-rows">
				<thead><tr><th>#</th><th>女優ID</th><th>表示名(任意)</th><th>おすすめコメント</th><th>写真URL(任意)</th><th>おすすめ作品ID(任意)</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
					<?php $p = $name . '[featured][' . $i . ']'; ?>
					<tr>
						<td><?php echo (int) $i + 1; ?></td>
						<td><input type="text" inputmode="numeric" size="8" name="<?php echo esc_attr( $p ); ?>[actress_id]" value="<?php echo esc_attr( $row['actress_id'] ?? '' ); ?>"></td>
						<td><input type="text" size="14" name="<?php echo esc_attr( $p ); ?>[name]" value="<?php echo esc_attr( $row['name'] ?? '' ); ?>"></td>
						<td><textarea rows="2" cols="34" name="<?php echo esc_attr( $p ); ?>[comment]"><?php echo esc_textarea( $row['comment'] ?? '' ); ?></textarea></td>
						<td>
							<input type="url" size="22" class="fz-image" name="<?php echo esc_attr( $p ); ?>[image]" value="<?php echo esc_attr( $row['image'] ?? '' ); ?>">
							<button type="button" class="button fz-media">選択</button>
						</td>
						<td><input type="text" size="12" placeholder="例: abc00123" name="<?php echo esc_attr( $p ); ?>[content_id]" value="<?php echo esc_attr( $row['content_id'] ?? '' ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>API設定</h2>
			<table class="form-table">
				<tr><th>API ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[api_id]" value="<?php echo esc_attr( $s['api_id'] ); ?>"></td></tr>
				<tr><th>アフィリエイトID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[affiliate_id]" value="<?php echo esc_attr( $s['affiliate_id'] ); ?>"><p class="description">API用のID(末尾が -990〜-999 のもの)</p></td></tr>
				<tr><th>価格記録の取得ページ数</th><td><input type="number" min="1" max="20" name="<?php echo esc_attr( $name ); ?>[track_pages]" value="<?php echo esc_attr( $s['track_pages'] ); ?>"> ページ(1ページ100作品。人気順で取得)</td></tr>
				<tr><th>キャッシュ時間</th><td><input type="number" min="1" max="48" name="<?php echo esc_attr( $name ); ?>[cache_hours]" value="<?php echo esc_attr( $s['cache_hours'] ); ?>"> 時間</td></tr>
			</table>
			<?php submit_button( '保存する' ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fz_flush">
			<?php wp_nonce_field( 'fz_flush' ); ?>
			<?php submit_button( 'キャッシュを削除して最新データを取得', 'secondary' ); ?>
		</form>

		<?php do_action( 'fz_settings_page_after' ); ?>
	</div>
	<script>
	document.querySelectorAll('.fz-media').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = btn.previousElementSibling;
			var frame = wp.media({ title: '宣材写真を選択', multiple: false, library: { type: 'image' } });
			frame.on('select', function () { input.value = frame.state().get('selection').first().toJSON().url; });
			frame.open();
		});
	});
	</script>
	<?php
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=fanza-connector' ) ) . '">設定</a>' );
		return $links;
	}
);
