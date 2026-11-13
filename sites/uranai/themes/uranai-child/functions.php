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

/* トップページにおすすめランキングを表示 */
add_action(
	'affiliate_base_front_top',
	function () {
		echo '<h2 class="section-title">おすすめ電話占いランキング</h2>';
		echo do_shortcode( '[aff_ranking slugs="uranai-a,uranai-b,uranai-c"]' );
	}
);

/* 占いは娯楽である旨の注記(YMYL対策・誇大表現の予防) */
add_action(
	'affiliate_base_footer_notes',
	function () {
		echo '<p>占いの結果は娯楽・参考情報としてお楽しみください。健康・法律・金銭などの重要な判断は専門家にご相談ください。</p>';
	}
);
