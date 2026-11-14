<?php
/**
 * 星座ごとの今日の運勢ページ(/seiza/{星座}/)
 * WordPress の固定ページを作らずに、プラグインがURLを用意して表示する。
 */

defined( 'ABSPATH' ) || exit;

const UT_REWRITE_VERSION = '1';

add_action(
	'init',
	function () {
		add_rewrite_rule( '^seiza/([a-z]+)/?$', 'index.php?ut_sign=$matches[1]', 'top' );
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
		$vars[] = 'ut_sign';
		return $vars;
	}
);

/** 表示中の星座キー(星座ページ以外は空) */
function ut_current_sign() {
	$key = get_query_var( 'ut_sign' );
	return ( $key && isset( ut_zodiac_signs()[ $key ] ) ) ? $key : '';
}

/* 不正な星座名は404、正しければ専用テンプレートで表示 */
add_action(
	'template_redirect',
	function () {
		if ( get_query_var( 'ut_sign' ) && ! ut_current_sign() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}
);

add_filter(
	'template_include',
	function ( $template ) {
		if ( ut_current_sign() ) {
			global $wp_query;
			$wp_query->is_404  = false;
			$wp_query->is_home = false;
			status_header( 200 );
			return __DIR__ . '/templates/sign.php';
		}
		return $template;
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( ut_current_sign() ) {
			$classes[] = 'ut-sign-page';
		}
		return $classes;
	}
);

/* タイトル・説明文・正規URL */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		$key = ut_current_sign();
		if ( $key ) {
			$parts['title'] = ut_zodiac_signs()[ $key ]['name'] . 'の今日の運勢(' . ut_today()->format( 'n月j日' ) . ')';
		}
		return $parts;
	}
);

add_filter(
	'affiliate_base_meta_description',
	function ( $desc ) {
		$key = ut_current_sign();
		if ( ! $key ) {
			return $desc;
		}
		$r = ut_ranking_entry( $key );
		return $r['name'] . '(' . $r['period'] . ')の今日の運勢は12星座中' . $r['rank'] . '位。' . $r['text'] . ' 恋愛運・仕事運・金運、ラッキーカラーも毎日更新。';
	}
);

add_action(
	'wp_head',
	function () {
		$key = ut_current_sign();
		if ( $key ) {
			echo '<link rel="canonical" href="' . esc_url( ut_sign_url( $key ) ) . '">' . "\n";
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

	$out .= '<section class="ut-other-signs"><h2 class="ut-title">ほかの星座の今日の運勢</h2><nav class="ut-sign-nav">';
	foreach ( $signs as $k => $s ) {
		if ( $k !== $key ) {
			$out .= '<a href="' . esc_url( ut_sign_url( $k ) ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $s['symbol'] ) . '</span>' . esc_html( $s['name'] ) . '</a>';
		}
	}
	$out .= '</nav><p class="ut-more"><a href="' . esc_url( home_url( '/' ) ) . '#zodiac-ranking">今日の12星座ランキングを見る ›</a></p></section>';

	$out .= '<section class="ut-sign-cta"><h2 class="ut-title">生年月日で、もっとくわしく</h2><p>九星気学の診断では、生年月日からあなたの本命星・今日の運勢・今月の吉方位がわかります。</p>';
	$out .= '<p class="ut-more"><a class="atk-button" href="' . esc_url( home_url( '/' ) ) . '#kyusei">無料で診断する</a></p></section>';

	$out .= '<p class="ut-note">※運勢は毎日0時に更新されます。結果は娯楽としてお楽しみください。</p>';
	return $out . '</article>';
}

/* ==================================================================
 * サイトマップ(wp-sitemap.xml)に12星座ページを追加
 * ================================================================== */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		$provider = new class() extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'seiza';
				$this->object_type = 'seiza';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				$list = array();
				foreach ( array_keys( ut_zodiac_signs() ) as $key ) {
					$list[] = array(
						'loc'     => ut_sign_url( $key ),
						'lastmod' => ut_today()->format( 'Y-m-d' ),
					);
				}
				return $list;
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return 1;
			}
		};
		wp_register_sitemap_provider( 'seiza', $provider );
	},
	20
);
