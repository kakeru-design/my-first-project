<?php
/**
 * Affiliate Base テーマ関数
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		register_nav_menus(
			array(
				'global' => 'グローバルナビ',
				'footer' => 'フッターナビ',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$parent = wp_get_theme( get_template() );
		wp_enqueue_style( 'affiliate-base', get_template_directory_uri() . '/style.css', array(), $parent->get( 'Version' ) );
		if ( is_child_theme() ) {
			wp_enqueue_style( 'affiliate-child', get_stylesheet_uri(), array( 'affiliate-base' ), wp_get_theme()->get( 'Version' ) );
		}
	}
);

/**
 * 子テーマの affiliate-links.json を Affiliate Toolkit に渡す。
 */
add_filter(
	'atk_links',
	function ( $links ) {
		$file = get_stylesheet_directory() . '/affiliate-links.json';
		if ( is_readable( $file ) ) {
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( is_array( $data ) ) {
				$links = array_merge( $links, $data );
			}
		}
		return $links;
	}
);

/** メタディスクリプション(SEOプラグイン未導入時のみ) */
add_action(
	'wp_head',
	function () {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}
		if ( is_singular() ) {
			$desc = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_queried_object_id() ) ), 60, '…' );
		} else {
			$desc = get_bloginfo( 'description' );
		}
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . "\">\n";
		}
	},
	1
);

/** パンくず */
function affiliate_base_breadcrumb() {
	if ( is_front_page() ) {
		return;
	}
	$items = array( '<a href="' . esc_url( home_url( '/' ) ) . '">ホーム</a>' );
	if ( is_single() ) {
		$cats = get_the_category();
		if ( $cats ) {
			$items[] = '<a href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
		}
		$items[] = esc_html( get_the_title() );
	} elseif ( is_page() ) {
		$items[] = esc_html( get_the_title() );
	} elseif ( is_archive() ) {
		$items[] = esc_html( wp_strip_all_tags( get_the_archive_title() ) );
	} elseif ( is_search() ) {
		$items[] = '検索結果';
	}
	echo '<nav class="breadcrumb" aria-label="パンくずリスト">' . implode( ' &rsaquo; ', $items ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** アーカイブタイトルの接頭辞を外す */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
