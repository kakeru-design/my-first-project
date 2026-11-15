<?php
/**
 * 開運グッズページ(/kaiun/)
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/kaiun-data.php';

/** アイテムのリンク(Amazonアソシエイト未設定・関数なしでも壊れない) */
function ut_kaiun_link( $keyword, $label ) {
	return function_exists( 'atk_amazon_link_html' ) ? atk_amazon_link_html( $keyword, $label ) : esc_html( $label );
}

function ut_kaiun_page_html() {
	$signs = ut_zodiac_signs();
	$stars = ut_kyusei_stars();

	$out  = '<article class="ut-article ut-kaiun">';
	$out .= '<h1 class="ut-page-title">開運グッズ・お守りアイテム</h1>';
	$out .= '<p class="ut-kaiun-pr"><span>PR</span>本ページにはアフィリエイト広告(Amazonアソシエイト)が含まれます。</p>';
	$out .= '<div class="ut-letter ut-kaiun-intro"><p class="ut-letter-from">月詠灯より</p>';
	$out .= '<p>お気に入りの小物を手に取ると、ふっと気持ちが明るくなることがありますよね。ここでは、毎日の開運習慣のおともになりそうなアイテムを集めました。石や色の意味は、昔からの言い伝えとして楽しんでくださいね。あなたが「いいな」と感じるものを選ぶのが、いちばんです。</p></div>';

	// 目次
	$out .= '<nav class="ut-kaiun-toc" aria-label="目次">';
	foreach ( ut_kaiun_categories() as $i => $c ) {
		$out .= '<a href="#kaiun-' . (int) $i . '">' . esc_html( $c['title'] ) . '</a>';
	}
	$out .= '<a href="#kaiun-signs">12星座別</a><a href="#kaiun-kyusei">九星別</a></nav>';

	foreach ( ut_kaiun_categories() as $i => $c ) {
		$out .= '<section class="ut-kaiun-sec" id="kaiun-' . (int) $i . '"><h2 class="ut-title">' . esc_html( $c['title'] ) . '</h2>';
		$out .= '<p class="ut-lead">' . esc_html( $c['lead'] ) . '</p><ul class="ut-kaiun-items">';
		foreach ( $c['items'] as [ $kw, $label, $note ] ) {
			$out .= '<li><p class="ut-kaiun-name">' . ut_kaiun_link( $kw, $label ) . '</p><p class="ut-kaiun-note">' . esc_html( $note ) . '</p></li>';
		}
		$out .= '</ul></section>';
	}

	$out .= '<section class="ut-kaiun-sec" id="kaiun-signs"><h2 class="ut-title">12星座別のラッキーアイテム</h2><ul class="ut-kaiun-items ut-kaiun-signs">';
	foreach ( ut_kaiun_signs() as $key => [ $item, $note ] ) {
		$s    = $signs[ $key ];
		$out .= '<li><p class="ut-kaiun-sign"><a href="' . esc_url( ut_sign_url( $key ) ) . '"><span class="ut-symbol" aria-hidden="true">' . esc_html( $s['symbol'] ) . '</span>' . esc_html( $s['name'] ) . '</a></p>';
		$out .= '<p class="ut-kaiun-name">' . ut_kaiun_link( $item[0], $item[1] ) . '</p><p class="ut-kaiun-note">' . esc_html( $note ) . '</p></li>';
	}
	$out .= '</ul></section>';

	$out .= '<section class="ut-kaiun-sec" id="kaiun-kyusei"><h2 class="ut-title">九星(本命星)別の開運カラーと小物</h2>';
	$out .= '<p class="ut-lead">本命星がわからない方は、<a href="' . esc_url( home_url( '/' ) ) . '#kyusei">九星気学の無料診断</a>で調べられます。</p><ul class="ut-kaiun-items">';
	foreach ( ut_kaiun_kyusei() as $n => [ $color, $hex, $item, $note ] ) {
		$out .= '<li><p class="ut-kaiun-sign">' . esc_html( $stars[ $n ]['name'] ) . '</p>';
		$out .= '<p class="ut-kaiun-color"><i class="ut-swatch" style="background:' . esc_attr( $hex ) . '"></i>' . esc_html( $color ) . '</p>';
		$out .= '<p class="ut-kaiun-name">' . ut_kaiun_link( $item[0], $item[1] ) . '</p><p class="ut-kaiun-note">' . esc_html( $note ) . '</p></li>';
	}
	$out .= '</ul></section>';

	$out .= '<p class="ut-note">※石や色の意味は、古くからの言い伝えや一般的にいわれていることの紹介です。持つだけで何かが起こることをお約束するものではありません。価格や在庫は販売ページでご確認ください。</p>';
	return $out . '</article>';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'ut_view' ) && ut_view() && 'kaiun' === ut_view()['view'] ) {
			wp_enqueue_style( 'uranai-kaiun', plugins_url( 'assets/kaiun.css', __FILE__ ), array( 'uranai-tools' ), '1.0.0' );
		}
	}
);
