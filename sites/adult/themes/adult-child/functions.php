<?php
/**
 * Adult Child テーマ関数
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Google SafeSearch 向けにアダルトサイトであることを明示 */
add_action(
	'wp_head',
	function () {
		echo '<meta name="rating" content="adult">' . "\n";
	},
	1
);

/* 比較表の行(動画配信・アダルトサービス向け) */
add_filter(
	'atk_table_columns',
	function () {
		return array(
			'price'    => '月額料金',
			'bonus'    => '無料期間・特典',
			'devices'  => '対応端末',
			'payments' => '支払い方法',
		);
	}
);

add_action(
	'affiliate_base_front_top',
	function () {
		echo '<h2 class="section-title">おすすめサービス比較</h2>';
		echo do_shortcode( '[aff_ranking slugs="adult-a,adult-b,adult-c"]' );
	}
);

add_action(
	'affiliate_base_footer_notes',
	function () {
		echo '<p><span class="age-badge">18</span> 当サイトは18歳未満の方の閲覧を固く禁止しています。</p>';
	}
);

/* ------------------------------------------------------------------
 * 年齢確認ゲート
 * Cookie で30日間記憶。ログイン中のユーザーと検索エンジンのクローラーには表示しない(JSのみで制御)。
 * ------------------------------------------------------------------ */
add_action(
	'wp_body_open',
	function () {
		if ( is_user_logged_in() ) {
			return;
		}
		$leave = apply_filters( 'adult_child_age_gate_leave_url', 'https://www.google.com/' );
		?>
		<div class="age-gate" id="age-gate" role="dialog" aria-modal="true" aria-labelledby="age-gate-title" hidden>
			<div class="age-gate-box">
				<span class="age-badge">18</span>
				<h2 id="age-gate-title">年齢確認</h2>
				<p>このサイトには成人向けの内容が含まれます。<br>18歳未満の方はご利用いただけません。</p>
				<div class="age-gate-actions">
					<button type="button" class="age-gate-yes" id="age-gate-yes">18歳以上です(入場する)</button>
					<a class="age-gate-no" href="<?php echo esc_url( $leave ); ?>" rel="nofollow">18歳未満です(退出する)</a>
				</div>
			</div>
		</div>
		<script>
		(function () {
			var key = 'age_verified=1';
			if (document.cookie.indexOf(key) !== -1) return;
			var gate = document.getElementById('age-gate');
			gate.hidden = false;
			document.documentElement.classList.add('age-gate-open');
			document.getElementById('age-gate-yes').focus();
			document.getElementById('age-gate-yes').addEventListener('click', function () {
				document.cookie = key + '; max-age=' + (60 * 60 * 24 * 30) + '; path=/; SameSite=Lax';
				gate.hidden = true;
				document.documentElement.classList.remove('age-gate-open');
			});
		})();
		</script>
		<?php
	},
	1
);
