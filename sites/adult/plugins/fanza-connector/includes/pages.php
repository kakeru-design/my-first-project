<?php
/**
 * プラグインが用意するページ(WordPressの固定ページは作らない)
 *   /actress/        女優から探す(一覧)
 *   /actress/{id}/   女優別のおすすめ作品・セール情報
 *   /genre/          ジャンルから探す(一覧)
 *   /genre/{id}/     ジャンル別のおすすめ作品・セール情報
 *
 * ページを作る対象(サイトマップに載せる対象)は「登録簿」で管理する。
 *   - わたしのベスト5(設定 → FANZA連携)
 *   - 人気作品の上位100本に出演している女優・付いているジャンル(毎週たまっていく)
 * 登録簿にない女優ID・ジャンルIDのURLも表示はできるが、検索エンジンには登録しない(noindex)。
 */

defined( 'ABSPATH' ) || exit;

const FZ_REWRITE_VERSION = '1';
const FZ_REG_OPTION      = 'fz_registry';
const FZ_REG_MAX         = 600;

/* ==================================================================
 * 登録簿
 * ================================================================== */

/** @return array{actress: array<string, array{name: string, ruby: string, seen: string}>, genre: array<string, array{name: string, seen: string}>} */
function fz_registry() {
	$reg = (array) get_option( FZ_REG_OPTION, array() );
	return array(
		'actress' => (array) ( $reg['actress'] ?? array() ),
		'genre'   => (array) ( $reg['genre'] ?? array() ),
	);
}

/**
 * 週に1回、人気作品の上位100本から女優とジャンルを登録簿に追加する。
 * (APIの結果はキャッシュされるので、注目女優の集計と同じ取得結果を使う)
 */
function fz_registry_refresh( $force = false ) {
	$week = fz_trend_period_key( 'week' );
	$reg  = (array) get_option( FZ_REG_OPTION, array() );
	if ( ! $force && ( $reg['week'] ?? '' ) === $week ) {
		return;
	}
	if ( ! fz_is_configured() ) {
		return;
	}
	$items = fz_items( array( 'sort' => 'rank', 'hits' => 100 ) );
	if ( is_wp_error( $items ) || ! $items ) {
		return;
	}
	$today = wp_date( 'Y-m-d' );
	$act   = (array) ( $reg['actress'] ?? array() );
	$gen   = (array) ( $reg['genre'] ?? array() );
	foreach ( $items as $item ) {
		foreach ( fz_item_actresses( $item ) as $id => $a ) {
			$act[ $id ] = array( 'name' => $a['name'], 'ruby' => $a['ruby'] ?: ( $act[ $id ]['ruby'] ?? '' ), 'seen' => $today );
		}
		foreach ( (array) ( $item['iteminfo']['genre'] ?? array() ) as $g ) {
			$id = (string) ( $g['id'] ?? '' );
			if ( ctype_digit( $id ) && ! empty( $g['name'] ) ) {
				$gen[ $id ] = array( 'name' => $g['name'], 'seen' => $today );
			}
		}
	}
	foreach ( fz_featured_rows() as $row ) {
		$id = (string) ( $row['actress_id'] ?? '' );
		if ( ctype_digit( $id ) ) {
			$act[ $id ] = array( 'name' => $row['name'] ?: ( $act[ $id ]['name'] ?? '' ), 'ruby' => $act[ $id ]['ruby'] ?? '', 'seen' => $today );
		}
	}
	// 上限を超えたら、最近見かけていない順に外す
	foreach ( array( 'act', 'gen' ) as $k ) {
		if ( count( $$k ) > FZ_REG_MAX ) {
			uasort( $$k, fn( $x, $y ) => strcmp( $y['seen'], $x['seen'] ) );
			$$k = array_slice( $$k, 0, FZ_REG_MAX, true );
		}
	}
	update_option(
		FZ_REG_OPTION,
		array(
			'week'    => $week,
			'actress' => $act,
			'genre'   => $gen,
		),
		false
	);
}

/**
 * 作品の出演女優(IDごとに名前と読み)。APIは名前と読みを別の行で返すことがあるのでまとめる。
 *
 * @return array<string, array{name: string, ruby: string}>
 */
function fz_item_actresses( $item ) {
	$out = array();
	foreach ( (array) ( $item['iteminfo']['actress'] ?? array() ) as $a ) {
		$id = (string) ( $a['id'] ?? '' );
		if ( ! ctype_digit( $id ) ) {
			continue; // 「12345_ruby」のような読み用の行
		}
		$out[ $id ] = array( 'name' => (string) ( $a['name'] ?? '' ), 'ruby' => (string) ( $a['ruby'] ?? '' ) );
	}
	foreach ( (array) ( $item['iteminfo']['actress'] ?? array() ) as $a ) {
		$id = (string) ( $a['id'] ?? '' );
		if ( preg_match( '/^(\d+)_ruby$/', $id, $m ) && isset( $out[ $m[1] ] ) && '' === $out[ $m[1] ]['ruby'] ) {
			$out[ $m[1] ]['ruby'] = (string) ( $a['name'] ?? '' );
		}
	}
	return $out;
}

function fz_actress_url( $id ) {
	return home_url( '/actress/' . rawurlencode( (string) $id ) . '/' );
}

function fz_genre_url( $id ) {
	return home_url( '/genre/' . rawurlencode( (string) $id ) . '/' );
}

/** 登録簿にある女優ならページへのリンク、なければ名前だけ */
function fz_actress_link_html( $id, $name ) {
	$id = (string) $id;
	return isset( fz_registry()['actress'][ $id ] )
		? '<a href="' . esc_url( fz_actress_url( $id ) ) . '">' . esc_html( $name ) . '</a>'
		: esc_html( $name );
}

/* ==================================================================
 * ルーティング
 * ================================================================== */

add_action(
	'init',
	function () {
		add_rewrite_rule( '^actress/?$', 'index.php?fz_view=actress', 'top' );
		add_rewrite_rule( '^actress/([0-9]+)/?$', 'index.php?fz_view=actress&fz_id=$matches[1]', 'top' );
		add_rewrite_rule( '^genre/?$', 'index.php?fz_view=genre', 'top' );
		add_rewrite_rule( '^genre/([0-9]+)/?$', 'index.php?fz_view=genre&fz_id=$matches[1]', 'top' );
		// プラグインを入れ替えたとき(有効化フックが走らない)にも一度だけ反映する
		if ( get_option( 'fz_rewrite_version' ) !== FZ_REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'fz_rewrite_version', FZ_REWRITE_VERSION );
		}
	}
);

add_filter( 'query_vars', fn( $vars ) => array_merge( $vars, array( 'fz_view', 'fz_id' ) ) );

/**
 * 表示中のページ。対象外なら null、表示できなければ array('view' => '404')
 * @return array{view: string, id?: string, data?: array, indexed?: bool}|null
 */
function fz_view() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$view = (string) get_query_var( 'fz_view' );
	$id   = (string) get_query_var( 'fz_id' );
	if ( '' === $view ) {
		return $cache = null;
	}
	if ( ! fz_is_configured() && null === apply_filters( 'pre_fz_api', null, 'ActressSearch', array() ) ) {
		return $cache = array( 'view' => '404' );
	}
	fz_registry_refresh();
	$reg = fz_registry();

	if ( 'actress' === $view && '' === $id ) {
		$cache = array( 'view' => 'actress-index' );
	} elseif ( 'genre' === $view && '' === $id ) {
		$cache = array( 'view' => 'genre-index' );
	} elseif ( 'actress' === $view && ctype_digit( $id ) ) {
		$a     = fz_actress( $id );
		$cache = $a
			? array( 'view' => 'actress', 'id' => $id, 'data' => $a, 'indexed' => isset( $reg['actress'][ $id ] ) )
			: array( 'view' => '404' );
	} elseif ( 'genre' === $view && ctype_digit( $id ) && isset( $reg['genre'][ $id ] ) ) {
		$cache = array( 'view' => 'genre', 'id' => $id, 'data' => $reg['genre'][ $id ], 'indexed' => true );
	} else {
		$cache = array( 'view' => '404' );
	}
	return $cache;
}

function fz_view_ok() {
	$v = fz_view();
	return $v && '404' !== $v['view'];
}

function fz_view_url( array $v ) {
	switch ( $v['view'] ) {
		case 'actress':
			return fz_actress_url( $v['id'] );
		case 'genre':
			return fz_genre_url( $v['id'] );
		case 'genre-index':
			return home_url( '/genre/' );
		default:
			return home_url( '/actress/' );
	}
}

add_action(
	'template_redirect',
	function () {
		$v = fz_view();
		if ( $v && '404' === $v['view'] ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	},
	5
);

add_filter( 'redirect_canonical', fn( $url ) => fz_view_ok() ? false : $url );

add_filter(
	'template_include',
	function ( $template ) {
		if ( ! fz_view_ok() ) {
			return $template;
		}
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
		return dirname( __DIR__ ) . '/templates/view.php';
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( fz_view_ok() ) {
			$classes[] = 'fz-page';
			$classes[] = 'fz-page-' . fz_view()['view'];
		}
		return $classes;
	}
);

/** 表示中ページのタイトル・説明文・パンくず */
function fz_view_meta( array $v ) {
	switch ( $v['view'] ) {
		case 'actress':
			$name = $v['data']['name'] ?? '';
			$ruby = $v['data']['ruby'] ?? '';
			return array(
				'title' => $name . 'のおすすめ作品|人気ランキング・新作・セール情報',
				'desc'  => $name . ( $ruby ? '(' . $ruby . ')' : '' ) . 'のFANZA出演作品を、人気順・新着順で紹介。いまセール中の作品や最新作もまとめてチェックできます。',
				'crumb' => array( array( home_url( '/actress/' ), '女優から探す' ), array( '', $name ) ),
			);
		case 'genre':
			$name = $v['data']['name'] ?? '';
			return array(
				'title' => '「' . $name . '」のおすすめ作品|人気ランキング・新作・セール情報',
				'desc'  => 'FANZAの「' . $name . '」ジャンルの作品を、人気順・新着順で紹介。いまセール中の作品や、このジャンルでよく見る女優もまとめてチェックできます。',
				'crumb' => array( array( home_url( '/genre/' ), 'ジャンルから探す' ), array( '', $name ) ),
			);
		case 'genre-index':
			return array(
				'title' => 'ジャンルから探す|FANZA作品のジャンル一覧',
				'desc'  => 'いま人気のFANZA作品に多いジャンルの一覧。ジャンルごとに人気作品・新作・セール中の作品をまとめています。',
				'crumb' => array( array( '', 'ジャンルから探す' ) ),
			);
		default:
			return array(
				'title' => '女優から探す|人気・注目のAV女優一覧',
				'desc'  => 'いま人気のFANZA作品に出演している女優の一覧。女優ごとに人気作品・新作・セール中の作品をまとめています。',
				'crumb' => array( array( '', '女優から探す' ) ),
			);
	}
}

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( fz_view_ok() ) {
			$parts['title'] = fz_view_meta( fz_view() )['title'];
		}
		return $parts;
	}
);

add_filter( 'affiliate_base_meta_description', fn( $desc ) => fz_view_ok() ? fz_view_meta( fz_view() )['desc'] : $desc );

add_action(
	'wp_head',
	function () {
		if ( ! fz_view_ok() ) {
			return;
		}
		$v = fz_view();
		if ( isset( $v['indexed'] ) && ! $v['indexed'] ) {
			echo '<meta name="robots" content="noindex,follow">' . "\n";
		}
		echo '<link rel="canonical" href="' . esc_url( fz_view_url( $v ) ) . '">' . "\n";
	}
);

/* ==================================================================
 * 本文
 * ================================================================== */

function fz_view_html( array $v ) {
	switch ( $v['view'] ) {
		case 'actress':
			return fz_actress_page_html( $v['id'], $v['data'] );
		case 'genre':
			return fz_genre_page_html( $v['id'], $v['data'] );
		case 'genre-index':
			return fz_genre_index_html();
		default:
			return fz_actress_index_html();
	}
}

/** 価格の記録から、条件に合うセール中の作品(割引率の高い順) */
function fz_sale_items_for( $type, $id, $limit = 8 ) {
	if ( ! function_exists( 'fz_pt_snapshot' ) ) {
		return array();
	}
	$rows = array_filter(
		fz_pt_discount_ranking( fz_pt_snapshot()['rows'] ),
		function ( $r ) use ( $type, $id ) {
			$list = 'actress' === $type ? array_keys( fz_item_actresses( $r['item'] ) ) : array_column( $r['item']['iteminfo']['genre'] ?? array(), 'id' );
			return in_array( (string) $id, array_map( 'strval', $list ), true );
		}
	);
	return array_slice( array_values( $rows ), 0, $limit );
}

/** 作品一覧のセクション(0件なら出さない) */
function fz_list_section_html( $title, $sub, $items, $badge_fn = null ) {
	if ( is_wp_error( $items ) || ! $items ) {
		return '';
	}
	$out = '<section class="fz-section">' . fz_section_head_html( $title, $sub ) . '<div class="fz-items">';
	foreach ( $items as $i => $item ) {
		$out .= fz_item_card_html( $item, $badge_fn ? $badge_fn( $item, $i ) : '' );
	}
	return $out . '</div></section>';
}

function fz_rank_badge( $item, $i ) {
	return '<span class="fz-badge fz-badge-rank">' . (int) ( $i + 1 ) . '位</span>';
}

/** 名前のリンク一覧(チップ) */
function fz_chips_html( array $links ) {
	if ( ! $links ) {
		return '';
	}
	$out = '<ul class="fz-chips">';
	foreach ( $links as $l ) {
		$out .= '<li><a href="' . esc_url( $l[0] ) . '">' . esc_html( $l[1] ) . ( isset( $l[2] ) ? '<small>' . esc_html( $l[2] ) . '</small>' : '' ) . '</a></li>';
	}
	return $out . '</ul>';
}

function fz_actress_page_html( $id, array $a ) {
	$name    = $a['name'] ?? '';
	$photo   = $a['imageURL']['large'] ?? ( $a['imageURL']['small'] ?? '' );
	$popular = fz_items( array( 'article' => 'actress', 'article_id' => $id, 'sort' => 'rank', 'hits' => 12 ) );
	$newest  = fz_items( array( 'article' => 'actress', 'article_id' => $id, 'sort' => 'date', 'hits' => 8 ) );
	$sale    = array_column( fz_sale_items_for( 'actress', $id ), 'item' );
	$comment = '';
	foreach ( fz_featured_rows() as $row ) {
		if ( (string) ( $row['actress_id'] ?? '' ) === (string) $id ) {
			$comment = (string) ( $row['comment'] ?? '' );
		}
	}

	$out  = '<article class="fz-profile">';
	$out .= '<header class="fz-profile-head">';
	if ( $photo ) {
		$out .= '<img class="fz-profile-photo" src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" width="96" height="96">';
	}
	$out .= '<div><h1 class="fz-profile-name">' . esc_html( $name ) . 'のおすすめ作品</h1>';
	if ( ! empty( $a['ruby'] ) ) {
		$out .= '<p class="fz-profile-ruby">' . esc_html( $a['ruby'] ) . '</p>';
	}
	$out .= '</div></header>';
	$out .= '<p class="fz-profile-lead">FANZAで配信中の' . esc_html( $name ) . 'さんの出演作品を、人気順と新着順で紹介します。セール中の作品があれば、いちばん上にまとめています。</p>';
	if ( $comment ) {
		$out .= '<div class="fz-profile-comment"><p class="fz-profile-comment-label">色欲坊主のひとこと</p><p>' . nl2br( esc_html( $comment ) ) . '</p></div>';
	}

	$out .= fz_list_section_html( 'セール中の出演作品', '定価から安くなっている作品(割引率の高い順)', $sale );
	$out .= fz_list_section_html( '人気の出演作品', 'FANZAの売れている順', $popular, 'fz_rank_badge' );
	$out .= fz_list_section_html( '新着の出演作品', '最近配信された順', $newest );

	if ( ! empty( $a['listURL']['digital'] ) ) {
		$out .= '<p class="fz-profile-all"><a class="atk-button" href="' . esc_url( $a['listURL']['digital'] ) . '" ' . fz_link_attrs() . '>FANZAで' . esc_html( $name ) . 'さんの全作品を見る<small>PR</small></a></p>';
	}

	// よく出てくるジャンル
	$genres = array();
	$reg    = fz_registry();
	foreach ( array_merge( is_array( $popular ) ? $popular : array(), is_array( $newest ) ? $newest : array() ) as $item ) {
		foreach ( (array) ( $item['iteminfo']['genre'] ?? array() ) as $g ) {
			$gid = (string) ( $g['id'] ?? '' );
			if ( isset( $reg['genre'][ $gid ] ) ) {
				$genres[ $gid ] = ( $genres[ $gid ] ?? 0 ) + 1;
			}
		}
	}
	arsort( $genres );
	$links = array();
	foreach ( array_slice( array_keys( $genres ), 0, 10, true ) as $gid ) {
		$links[] = array( fz_genre_url( $gid ), $reg['genre'][ $gid ]['name'] );
	}
	if ( $links ) {
		$out .= '<section class="fz-section"><h2 class="fz-section-title">' . esc_html( $name ) . 'さんの作品に多いジャンル</h2>' . fz_chips_html( $links ) . '</section>';
	}

	$out .= fz_other_actresses_html( $id );
	return $out . '</article>';
}

/** ほかの注目女優へのリンク(ベスト5+今週の注目女優) */
function fz_other_actresses_html( $exclude_id = '' ) {
	$links = array();
	$seen  = array( (string) $exclude_id => true );
	foreach ( fz_featured_rows() as $row ) {
		$aid = (string) ( $row['actress_id'] ?? '' );
		if ( ctype_digit( $aid ) && ! isset( $seen[ $aid ] ) && ! empty( $row['name'] ) ) {
			$links[]      = array( fz_actress_url( $aid ), $row['name'] );
			$seen[ $aid ] = true;
		}
	}
	foreach ( fz_trending_actresses( 12 ) as $t ) {
		if ( ! isset( $seen[ $t['id'] ] ) ) {
			$links[]           = array( fz_actress_url( $t['id'] ), $t['name'] );
			$seen[ $t['id'] ] = true;
		}
	}
	if ( ! $links ) {
		return '';
	}
	return '<section class="fz-section"><h2 class="fz-section-title">ほかの人気・注目女優</h2>' . fz_chips_html( $links )
		. '<p class="fz-more-line"><a href="' . esc_url( home_url( '/actress/' ) ) . '">女優の一覧を見る ›</a></p></section>';
}

function fz_genre_page_html( $id, array $g ) {
	$name    = $g['name'] ?? '';
	$popular = fz_items( array( 'article' => 'genre', 'article_id' => $id, 'sort' => 'rank', 'hits' => 12 ) );
	$newest  = fz_items( array( 'article' => 'genre', 'article_id' => $id, 'sort' => 'date', 'hits' => 8 ) );
	$sale    = array_column( fz_sale_items_for( 'genre', $id ), 'item' );

	$out  = '<article class="fz-profile">';
	$out .= '<header class="fz-profile-head"><div><h1 class="fz-profile-name">「' . esc_html( $name ) . '」のおすすめ作品</h1></div></header>';
	$out .= '<p class="fz-profile-lead">FANZAの「' . esc_html( $name ) . '」ジャンルの作品を、人気順と新着順で紹介します。セール中の作品があれば、いちばん上にまとめています。</p>';
	$out .= fz_list_section_html( 'セール中の作品', '定価から安くなっている作品(割引率の高い順)', $sale );
	$out .= fz_list_section_html( '人気の作品', 'FANZAの売れている順', $popular, 'fz_rank_badge' );
	$out .= fz_list_section_html( '新着の作品', '最近配信された順', $newest );

	// このジャンルでよく見る女優
	$count = array();
	$names = array();
	$reg   = fz_registry();
	foreach ( is_array( $popular ) ? $popular : array() as $item ) {
		foreach ( fz_item_actresses( $item ) as $aid => $a ) {
			if ( isset( $reg['actress'][ $aid ] ) ) {
				$count[ $aid ] = ( $count[ $aid ] ?? 0 ) + 1;
				$names[ $aid ] = $a['name'];
			}
		}
	}
	arsort( $count );
	$links = array();
	foreach ( array_slice( array_keys( $count ), 0, 12, true ) as $aid ) {
		$links[] = array( fz_actress_url( $aid ), $names[ $aid ] );
	}
	if ( $links ) {
		$out .= '<section class="fz-section"><h2 class="fz-section-title">「' . esc_html( $name ) . '」の人気作品に出ている女優</h2>' . fz_chips_html( $links ) . '</section>';
	}
	$out .= '<section class="fz-section"><h2 class="fz-section-title">ほかのジャンル</h2>' . fz_genre_chips_html( 24, $id )
		. '<p class="fz-more-line"><a href="' . esc_url( home_url( '/genre/' ) ) . '">ジャンルの一覧を見る ›</a></p></section>';
	return $out . '</article>';
}

/** ジャンルのチップ(最近人気の作品で見かけた順) */
function fz_genre_chips_html( $limit = 0, $exclude = '' ) {
	$gen = fz_registry()['genre'];
	unset( $gen[ (string) $exclude ] );
	uasort( $gen, fn( $x, $y ) => array( $y['seen'], $x['name'] ) <=> array( $x['seen'], $y['name'] ) );
	if ( $limit ) {
		$gen = array_slice( $gen, 0, $limit, true );
	}
	$links = array();
	foreach ( $gen as $gid => $g ) {
		$links[] = array( fz_genre_url( $gid ), $g['name'] );
	}
	return fz_chips_html( $links );
}

function fz_actress_index_html() {
	$out  = '<article class="fz-profile"><header class="fz-profile-head"><div><h1 class="fz-profile-name">女優から探す</h1></div></header>';
	$out .= '<p class="fz-profile-lead">いま人気のFANZA作品に出演している女優の一覧です。名前を押すと、その女優の人気作品・新作・セール中の作品がまとめて見られます。</p>';

	$best = array();
	foreach ( fz_featured_rows() as $row ) {
		if ( ctype_digit( (string) ( $row['actress_id'] ?? '' ) ) && ! empty( $row['name'] ) ) {
			$best[] = array( fz_actress_url( $row['actress_id'] ), $row['name'] );
		}
	}
	if ( $best ) {
		$out .= '<section class="fz-section"><h2 class="fz-section-title">' . esc_html( fz_settings()['featured_title'] ) . '</h2>' . fz_chips_html( $best ) . '</section>';
	}
	$trend = array();
	foreach ( fz_trending_actresses( 10 ) as $t ) {
		$trend[] = array( fz_actress_url( $t['id'] ), $t['name'] );
	}
	if ( $trend ) {
		$out .= '<section class="fz-section"><h2 class="fz-section-title">今週の注目女優</h2>' . fz_chips_html( $trend ) . '</section>';
	}

	// 50音順(読みの1文字目の行ごと)
	$groups = array();
	foreach ( fz_registry()['actress'] as $aid => $a ) {
		if ( '' === $a['name'] ) {
			continue;
		}
		$row                      = fz_kana_row( $a['ruby'] );
		$groups[ $row ][ $aid ] = $a;
	}
	if ( $groups ) {
		$order = array( 'あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ', 'その他' );
		$out  .= '<section class="fz-section"><h2 class="fz-section-title">50音順</h2>';
		foreach ( $order as $row ) {
			if ( empty( $groups[ $row ] ) ) {
				continue;
			}
			uasort( $groups[ $row ], fn( $x, $y ) => strcmp( $x['ruby'] ?: $x['name'], $y['ruby'] ?: $y['name'] ) );
			$links = array();
			foreach ( $groups[ $row ] as $aid => $a ) {
				$links[] = array( fz_actress_url( $aid ), $a['name'] );
			}
			$out .= '<h3 class="fz-kana-head">' . esc_html( 'その他' === $row ? 'その他' : $row . '行' ) . '</h3>' . fz_chips_html( $links );
		}
		$out .= '</section>';
	}
	return $out . '</article>';
}

/** ひらがなの読みから「あ/か/さ…行」を返す */
function fz_kana_row( $ruby ) {
	$c = mb_substr( (string) $ruby, 0, 1 );
	if ( '' === $c ) {
		return 'その他';
	}
	$c    = mb_convert_kana( $c, 'c' ); // カタカナ → ひらがな
	$rows = array(
		'あ' => 'ぁあぃいぅうゔぇえぉお',
		'か' => 'かがきぎくぐけげこご',
		'さ' => 'さざしじすずせぜそぞ',
		'た' => 'ただちぢっつづてでとど',
		'な' => 'なにぬねの',
		'は' => 'はばぱひびぴふぶぷへべぺほぼぽ',
		'ま' => 'まみむめも',
		'や' => 'ゃやゅゆょよ',
		'ら' => 'らりるれろ',
		'わ' => 'ゎわゐゑをん',
	);
	foreach ( $rows as $row => $chars ) {
		if ( false !== mb_strpos( $chars, $c ) ) {
			return $row;
		}
	}
	return 'その他';
}

function fz_genre_index_html() {
	$out  = '<article class="fz-profile"><header class="fz-profile-head"><div><h1 class="fz-profile-name">ジャンルから探す</h1></div></header>';
	$out .= '<p class="fz-profile-lead">いま人気のFANZA作品に多いジャンルの一覧です。ジャンルを押すと、人気作品・新作・セール中の作品がまとめて見られます。</p>';
	$out .= '<section class="fz-section">' . fz_genre_chips_html() . '</section>';
	return $out . '</article>';
}

/** トップページ用「女優から探す・ジャンルから探す」 */
function fz_browse_html() {
	fz_registry_refresh();
	$reg = fz_registry();
	if ( ! $reg['actress'] && ! $reg['genre'] ) {
		return '';
	}
	$trend = array();
	foreach ( fz_trending_actresses( 12 ) as $t ) {
		$trend[] = array( fz_actress_url( $t['id'] ), $t['name'] );
	}
	$out = '<section class="fz-section fz-browse">';
	if ( $trend ) {
		$out .= fz_section_head_html( '女優から探す', '人気作品に出ている女優', home_url( '/actress/' ) ) . fz_chips_html( $trend );
	}
	if ( $reg['genre'] ) {
		$out .= '<div class="fz-browse-genre">' . fz_section_head_html( 'ジャンルから探す', 'いま人気のジャンル', home_url( '/genre/' ) ) . fz_genre_chips_html( 20 ) . '</div>';
	}
	return $out . '</section>';
}
add_shortcode( 'fanza_browse', 'fz_browse_html' );

/* ==================================================================
 * サイトマップ(wp-sitemap.xml)に追加
 * ================================================================== */

function fz_all_view_urls() {
	$reg  = fz_registry();
	$urls = array( home_url( '/actress/' ), home_url( '/genre/' ) );
	foreach ( array_keys( $reg['actress'] ) as $id ) {
		$urls[] = fz_actress_url( $id );
	}
	foreach ( array_keys( $reg['genre'] ) as $id ) {
		$urls[] = fz_genre_url( $id );
	}
	return $urls;
}

add_action(
	'init',
	function () {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		$provider = new class() extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'fanza';
				$this->object_type = 'fanza';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				return array_map( fn( $u ) => array( 'loc' => $u ), fz_all_view_urls() );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		};
		wp_register_sitemap_provider( 'fanza', $provider );
	},
	20
);
