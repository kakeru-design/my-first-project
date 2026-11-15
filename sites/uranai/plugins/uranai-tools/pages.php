<?php
/**
 * プラグインが用意するページ(WordPressの固定ページは作らない)
 *   /seiza/{星座}/       星座ごとの今日の運勢(12)
 *   /aisho/               12星座の相性一覧
 *   /aisho/{a}-{b}/       2星座の相性(78)
 *   /tanjobi/             誕生日一覧
 *   /tanjobi/{月}-{日}/   誕生日別の性格(366)
 *   /shukan/              今週の12星座ランキング
 *   /tsuki/               新月・満月カレンダー
 *   /kaiun/               開運グッズ
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/content.php';

const UT_REWRITE_VERSION = '3';

add_action(
	'init',
	function () {
		add_rewrite_rule( '^seiza/([a-z]+)/?$', 'index.php?ut_view=seiza&ut_a=$matches[1]', 'top' );
		add_rewrite_rule( '^aisho/?$', 'index.php?ut_view=aisho', 'top' );
		add_rewrite_rule( '^aisho/([a-z]+)-([a-z]+)/?$', 'index.php?ut_view=aisho&ut_a=$matches[1]&ut_b=$matches[2]', 'top' );
		add_rewrite_rule( '^tanjobi/?$', 'index.php?ut_view=tanjobi', 'top' );
		add_rewrite_rule( '^tanjobi/([0-9]{1,2})-([0-9]{1,2})/?$', 'index.php?ut_view=tanjobi&ut_a=$matches[1]&ut_b=$matches[2]', 'top' );
		add_rewrite_rule( '^shukan/?$', 'index.php?ut_view=shukan', 'top' );
		add_rewrite_rule( '^tsuki/?$', 'index.php?ut_view=tsuki', 'top' );
		add_rewrite_rule( '^kaiun/?$', 'index.php?ut_view=kaiun', 'top' );
		// プラグインを入れ替えたとき(有効化フックが走らない)にも一度だけ反映する
		if ( get_option( 'ut_rewrite_version' ) !== UT_REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'ut_rewrite_version', UT_REWRITE_VERSION );
		}
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		return array_merge( $vars, array( 'ut_view', 'ut_a', 'ut_b' ) );
	}
);

/**
 * 表示中のページ。対象外なら null、URLが不正なら array('view' => '404')
 * @return array{view: string, a?: string, b?: string}|null
 */
function ut_view() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$view  = get_query_var( 'ut_view' );
	$a     = (string) get_query_var( 'ut_a' );
	$b     = (string) get_query_var( 'ut_b' );
	$signs = ut_zodiac_signs();
	if ( ! $view ) {
		$cache = null;
	} elseif ( 'seiza' === $view ) {
		$cache = isset( $signs[ $a ] ) ? array( 'view' => 'seiza', 'a' => $a ) : array( 'view' => '404' );
	} elseif ( 'aisho' === $view && '' === $a ) {
		$cache = array( 'view' => 'aisho-index' );
	} elseif ( 'aisho' === $view ) {
		$cache = ( isset( $signs[ $a ] ) && isset( $signs[ $b ] ) ) ? array( 'view' => 'aisho', 'a' => $a, 'b' => $b ) : array( 'view' => '404' );
	} elseif ( 'tanjobi' === $view && '' === $a ) {
		$cache = array( 'view' => 'tanjobi-index' );
	} elseif ( 'tanjobi' === $view ) {
		$cache = ut_birthday_valid( $a, $b ) && ltrim( $a, '0' ) === $a && ltrim( $b, '0' ) === $b
			? array( 'view' => 'tanjobi', 'a' => (int) $a, 'b' => (int) $b )
			: array( 'view' => '404' );
	} elseif ( in_array( $view, array( 'shukan', 'tsuki', 'kaiun' ), true ) ) {
		$cache = array( 'view' => $view );
	} else {
		$cache = array( 'view' => '404' );
	}
	return $cache;
}

/** 星座ページで表示中の星座キー(後方互換) */
function ut_current_sign() {
	$v = ut_view();
	return ( $v && 'seiza' === $v['view'] ) ? $v['a'] : '';
}

/** 表示中ページの正規URL */
function ut_view_url( array $v ) {
	switch ( $v['view'] ) {
		case 'seiza':
			return ut_sign_url( $v['a'] );
		case 'aisho':
			return ut_compat_url( $v['a'], $v['b'] );
		case 'aisho-index':
			return home_url( '/aisho/' );
		case 'tanjobi':
			return ut_birthday_url( $v['a'], $v['b'] );
		case 'tanjobi-index':
			return home_url( '/tanjobi/' );
		default:
			return home_url( '/' . $v['view'] . '/' );
	}
}

add_action(
	'template_redirect',
	function () {
		$v = ut_view();
		if ( ! $v ) {
			return;
		}
		if ( '404' === $v['view'] ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		// 相性ページは星座の並び順のURLにそろえる(逆順は301で転送)
		if ( 'aisho' === $v['view'] && ut_pair( $v['a'], $v['b'] ) !== array( $v['a'], $v['b'] ) ) {
			wp_safe_redirect( ut_compat_url( $v['a'], $v['b'] ), 301 );
			exit;
		}
	},
	5
);

add_filter( 'redirect_canonical', fn( $url ) => ( ut_view() && '404' !== ut_view()['view'] ) ? false : $url );

add_filter(
	'template_include',
	function ( $template ) {
		$v = ut_view();
		if ( $v && '404' !== $v['view'] ) {
			global $wp_query;
			$wp_query->is_404  = false;
			$wp_query->is_home = false;
			status_header( 200 );
			return __DIR__ . '/templates/view.php';
		}
		return $template;
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		$v = ut_view();
		if ( $v && '404' !== $v['view'] ) {
			$classes[] = 'ut-page';
			$classes[] = 'ut-page-' . $v['view'];
		}
		return $classes;
	}
);

/** 表示中ページのタイトル・説明文・パンくず */
function ut_view_meta( array $v ) {
	$s     = ut_zodiac_signs();
	$today = ut_today();
	switch ( $v['view'] ) {
		case 'seiza':
			$r = ut_ranking_entry( $v['a'] );
			return array(
				'title' => $s[ $v['a'] ]['name'] . 'の今日の運勢(' . $today->format( 'n月j日' ) . ')',
				'desc'  => $r['name'] . '(' . $r['period'] . ')の今日の運勢は12星座中' . $r['rank'] . '位。' . $r['text'] . ' 恋愛運・仕事運・金運、ラッキーカラーも毎日更新。',
				'crumb' => array( array( home_url( '/' ) . '#zodiac-ranking', '12星座ランキング' ), array( '', $s[ $v['a'] ]['name'] ) ),
			);
		case 'aisho':
			$c    = ut_compat( $v['a'], $v['b'] );
			$name = $v['a'] === $v['b'] ? $s[ $v['a'] ]['name'] . 'どうし' : $s[ $c['a'] ]['name'] . 'と' . $s[ $c['b'] ]['name'];
			return array(
				'title' => $name . 'の相性|恋愛・友情・仕事',
				'desc'  => $name . 'の相性は' . $c['score'] . '%。「' . $c['label'] . '」の関係です。恋愛・友情・仕事の相性と、うまくいくコツを紹介します。',
				'crumb' => array( array( home_url( '/aisho/' ), '12星座の相性' ), array( '', $name ) ),
			);
		case 'aisho-index':
			return array(
				'title' => '12星座の相性一覧(78通り)',
				'desc'  => '12星座どうしの相性を78通りすべて紹介。恋愛・友情・仕事の相性と、二人がうまくいくコツがわかります。',
				'crumb' => array( array( '', '12星座の相性' ) ),
			);
		case 'tanjobi':
			$b = ut_birthday( $v['a'], $v['b'] );
			return array(
				'title' => $v['a'] . '月' . $v['b'] . '日生まれの性格と運勢',
				'desc'  => $v['a'] . '月' . $v['b'] . '日生まれの人は' . $s[ $b['key'] ]['name'] . '。性格・恋愛傾向・向いている仕事・ラッキーカラー・誕生石を紹介します。',
				'crumb' => array( array( home_url( '/tanjobi/' ), '誕生日別の性格' ), array( '', $v['a'] . '月' . $v['b'] . '日' ) ),
			);
		case 'tanjobi-index':
			return array(
				'title' => '誕生日別の性格(366日)',
				'desc'  => '1月1日から12月31日まで、366日それぞれの誕生日の性格・恋愛傾向・向いている仕事を紹介します。',
				'crumb' => array( array( '', '誕生日別の性格' ) ),
			);
		case 'shukan':
			$w = ut_weekly_ranking();
			return array(
				'title' => '今週の12星座ランキング(' . $w['monday']->format( 'n月j日' ) . '〜)',
				'desc'  => '今週(' . $w['monday']->format( 'n月j日' ) . '〜)の12星座ランキング。1位は' . $w['list'][0]['name'] . '。星座ごとの今週のキーワードとラッキーデーも。',
				'crumb' => array( array( '', '今週の12星座ランキング' ) ),
			);
		case 'kaiun':
			return array(
				'title' => '開運グッズ・お守りアイテム',
				'desc'  => '天然石・お守り・新月の願い事ノート・金運小物など、毎日の開運習慣のおともになるアイテムを紹介。12星座別・九星(本命星)別のラッキーアイテムも。',
				'crumb' => array( array( '', '開運グッズ' ) ),
			);
		case 'tsuki':
			return array(
				'title' => '新月・満月カレンダー' . $today->format( 'Y' ) . '|日時と星座・願い事のテーマ',
				'desc'  => '次の新月・満月はいつ? 新月・満月の日時(日本時間)と、月が入る星座ごとの願い事・手放しのテーマを1年分まとめました。',
				'crumb' => array( array( '', '新月・満月カレンダー' ) ),
			);
	}
	return array( 'title' => '', 'desc' => '', 'crumb' => array() );
}

/** 表示中ページの本文 */
function ut_view_html( array $v ) {
	switch ( $v['view'] ) {
		case 'seiza':
			return ut_sign_page_html( $v['a'] );
		case 'aisho':
			return ut_compat_page_html( $v['a'], $v['b'] );
		case 'aisho-index':
			return ut_compat_index_html();
		case 'tanjobi':
			return ut_birthday_page_html( $v['a'], $v['b'] );
		case 'tanjobi-index':
			return ut_birthday_index_html();
		case 'shukan':
			return ut_weekly_html();
		case 'tsuki':
			return ut_moon_calendar_html();
		case 'kaiun':
			return ut_kaiun_page_html();
	}
	return '';
}

add_filter(
	'document_title_parts',
	function ( $parts ) {
		$v = ut_view();
		if ( $v && '404' !== $v['view'] ) {
			$parts['title'] = ut_view_meta( $v )['title'];
		}
		return $parts;
	}
);

add_filter(
	'affiliate_base_meta_description',
	function ( $desc ) {
		$v = ut_view();
		return ( $v && '404' !== $v['view'] ) ? ut_view_meta( $v )['desc'] : $desc;
	}
);

add_action(
	'wp_head',
	function () {
		$v = ut_view();
		if ( $v && '404' !== $v['view'] ) {
			echo '<link rel="canonical" href="' . esc_url( ut_view_url( $v ) ) . '">' . "\n";
		}
	}
);

/** 星座ページの本文 */
function ut_sign_page_html( $key ) {
	$today   = ut_today();
	$signs   = ut_zodiac_signs();
	$profile = ut_sign_profiles()[ $key ];
	$d       = ut_sign_detail( ut_ranking_entry( $key, $today ), $today );

	$out  = '<article class="ut-sign-article">';
	$out .= '<header class="ut-sign-hero">';
	$out .= '<p class="ut-sign-symbol" aria-hidden="true">' . esc_html( $d['symbol'] ) . '</p>';
	$out .= '<h1 class="ut-sign-name">' . esc_html( $d['name'] ) . 'の今日の運勢</h1>';
	$out .= '<p class="ut-sign-period">' . esc_html( $d['period'] ) . ' / ' . esc_html( $today->format( 'Y年n月j日' ) . '(' . ut_weekday( $today ) . ')' ) . '</p>';
	$out .= '<p class="ut-sign-rank"><span>12星座中</span><b>' . (int) $d['rank'] . '</b><span>位</span></p>';
	$out .= '</header>';

	$out .= ut_sign_detail_html( $d );

	$out .= '<section class="ut-profile"><h2 class="ut-title">' . esc_html( $d['name'] ) . 'の性格と特徴</h2>';
	$out .= '<p>' . esc_html( $profile['nature'] ) . '</p>';
	$out .= '<dl class="ut-today-list">';
	$out .= '<div><dt>期間</dt><dd>' . esc_html( $d['period'] ) . '</dd></div>';
	$out .= '<div><dt>エレメント</dt><dd>' . esc_html( $profile['element'] ) . '</dd></div>';
	$out .= '<div><dt>守護星</dt><dd>' . esc_html( $profile['ruler'] ) . '</dd></div>';
	$out .= '<div><dt>キーワード</dt><dd>' . esc_html( implode( '・', $profile['keywords'] ) ) . '</dd></div>';
	$out .= '</dl></section>';

	$out .= '<section class="ut-other-signs"><h2 class="ut-title">' . esc_html( $d['name'] ) . 'とほかの星座の相性</h2>' . ut_compat_links_html( $key ) . '</section>';

	$out .= '<section class="ut-other-signs"><h2 class="ut-title">ほかの星座の今日の運勢</h2><nav class="ut-sign-nav">';
	foreach ( $signs as $k => $s ) {
		if ( $k !== $key ) {
			$out .= '<a href="' . esc_url( ut_sign_url( $k ) ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $s['symbol'] ) . '</span>' . esc_html( $s['name'] ) . '</a>';
		}
	}
	$out .= '</nav><p class="ut-more"><a href="' . esc_url( home_url( '/' ) ) . '#zodiac-ranking">今日の12星座ランキングを見る ›</a></p></section>';

	$out .= '<p class="ut-more"><a href="' . esc_url( home_url( '/kaiun/' ) ) . '#kaiun-signs">' . esc_html( $d['name'] ) . 'のラッキーアイテム・開運グッズを見る ›</a></p>';

	$out .= '<section class="ut-sign-cta"><h2 class="ut-title">生年月日で、もっとくわしく</h2><p>九星気学の診断では、生年月日からあなたの本命星・今日の運勢・今月の吉方位がわかります。</p>';
	$out .= '<p class="ut-more"><a class="atk-button" href="' . esc_url( home_url( '/' ) ) . '#kyusei">無料で診断する</a></p></section>';

	$out .= '<p class="ut-note">※運勢は毎日0時に更新されます。結果は娯楽としてお楽しみください。</p>';
	return $out . '</article>';
}

/* ==================================================================
 * サイトマップ(wp-sitemap.xml)に追加
 * ================================================================== */

/** プラグインが用意する全ページのURL */
function ut_all_view_urls() {
	$urls = array( home_url( '/shukan/' ), home_url( '/tsuki/' ), home_url( '/aisho/' ), home_url( '/tanjobi/' ), home_url( '/kaiun/' ) );
	$keys = ut_sign_keys();
	foreach ( $keys as $k ) {
		$urls[] = ut_sign_url( $k );
	}
	foreach ( $keys as $i => $a ) {
		foreach ( array_slice( $keys, $i ) as $b ) {
			$urls[] = ut_compat_url( $a, $b );
		}
	}
	for ( $m = 1; $m <= 12; $m++ ) {
		for ( $d = 1; $d <= 31; $d++ ) {
			if ( ut_birthday_valid( $m, $d ) ) {
				$urls[] = ut_birthday_url( $m, $d );
			}
		}
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
				$this->name        = 'uranai';
				$this->object_type = 'uranai';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				return array_map( fn( $u ) => array( 'loc' => $u ), ut_all_view_urls() );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		};
		wp_register_sitemap_provider( 'uranai', $provider );
	},
	20
);
