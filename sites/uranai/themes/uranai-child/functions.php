<?php
/**
 * Uranai Child テーマ関数
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'uranai-fonts', 'https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@600;700&display=swap', array(), null );
	}
);

/* 比較表の行(電話占い向け) */
add_filter(
	'atk_table_columns',
	function () {
		return array(
			'price'    => '1分あたり料金',
			'bonus'    => '初回特典',
			'methods'  => '相談方法',
			'payments' => '支払い方法',
		);
	}
);

/* トップページ: 12星座ランキング → 九星気学の診断 → 電話占いランキング */
add_action(
	'affiliate_base_front_top',
	function () {
		if ( shortcode_exists( 'zodiac_ranking' ) ) {
			echo do_shortcode( '[zodiac_ranking]' );
			echo do_shortcode( '[kyusei_form]' );
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			echo '<p class="atk-warning">Uranai Tools プラグインを有効化してください。</p>';
		}
		// 提携リンクが未設定(仮置き)の案件は一般の閲覧者に見せない。管理者には案内だけ出す。
		$slugs = array( 'uranai-a', 'uranai-b', 'uranai-c' );
		$ready = function_exists( 'atk_link' )
			? array_values( array_filter( $slugs, fn( $s ) => atk_link( $s ) && ! atk_is_placeholder( atk_link( $s ) ) ) )
			: array();
		if ( $ready ) {
			echo '<h2 class="section-title">おすすめ電話占いランキング</h2>';
			echo do_shortcode( '[aff_ranking slugs="' . esc_attr( implode( ',', $ready ) ) . '"]' );
		} elseif ( current_user_can( 'edit_posts' ) ) {
			echo '<p class="atk-warning">[管理者のみ表示] 電話占いランキングは、affiliate-links.json に提携URLを設定すると表示されます。</p>';
		}
	}
);

/* 占いは娯楽である旨の注記(YMYL対策・誇大表現の予防) */
add_action(
	'affiliate_base_footer_notes',
	function () {
		echo '<p>占いの結果は娯楽・参考情報としてお楽しみください。健康・法律・金銭などの重要な判断は専門家にご相談ください。</p>';
	}
);

/* トップは主役コンテンツを最上部に出すため、サイト名の大きな帯は出さない(サイト名はヘッダーの h1) */
add_filter( 'affiliate_base_show_hero', '__return_false' );
