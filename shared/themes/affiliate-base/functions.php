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

/** SEOプラグインが入っているか(入っていれば説明文・OGPはそちらに任せる) */
function affiliate_base_has_seo_plugin() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' );
}

/** 表示中ページの説明文 */
function affiliate_base_description() {
	if ( is_singular() ) {
		$desc = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_queried_object_id() ) ), 60, '…' );
	} else {
		$desc = get_bloginfo( 'description' );
	}
	return trim( wp_strip_all_tags( (string) apply_filters( 'affiliate_base_meta_description', $desc ) ) );
}

/** 共有用の画像(アイキャッチ → カスタマイザーの共有用画像 → サイトアイコン の順) */
function affiliate_base_og_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		return get_the_post_thumbnail_url( null, 'large' );
	}
	$img = get_theme_mod( 'affiliate_base_og_image', '' );
	if ( $img ) {
		return $img;
	}
	return get_site_icon_url( 512 );
}

/* メタディスクリプションとOGP(SEOプラグイン未導入時のみ) */
add_action(
	'wp_head',
	function () {
		if ( affiliate_base_has_seo_plugin() ) {
			return;
		}
		$desc  = affiliate_base_description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array(), wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$img   = affiliate_base_og_image();
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . "\">\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\">\n";
		echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . "\">\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . "\">\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . "\">\n";
		echo '<meta property="og:locale" content="ja_JP">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\">\n";
		}
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . "\">\n";
		}
		$card = get_theme_mod( 'affiliate_base_og_image', '' ) || ( is_singular() && has_post_thumbnail() ) ? 'summary_large_image' : 'summary';
		echo '<meta name="twitter:card" content="' . esc_attr( $card ) . "\">\n";
	},
	1
);

/* カスタマイザー: 共有用画像(SNSでリンクを貼ったときの画像。横1200×630px推奨) */
add_action(
	'customize_register',
	function ( $wp_customize ) {
		$wp_customize->add_setting( 'affiliate_base_og_image', array( 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'affiliate_base_og_image',
				array(
					'label'       => '共有用画像(OGP)',
					'description' => 'SNSやLINEでリンクを貼ったときに表示される画像。横1200×630px推奨。未設定ならサイトアイコンを使います。',
					'section'     => 'title_tagline',
				)
			)
		);
	}
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
