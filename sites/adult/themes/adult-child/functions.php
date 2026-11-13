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

/* ------------------------------------------------------------------
 * トップページ: イチオシ女優(常に最上部) → 人気作品 → 新着作品
 * イチオシ女優は 設定 → FANZA連携 から差し替える。
 * ------------------------------------------------------------------ */
add_action(
	'affiliate_base_front_top',
	function () {
		if ( ! function_exists( 'fz_featured_html' ) ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<p class="atk-warning">FANZA Connector プラグインを有効化してください。</p>';
			}
			return;
		}
		echo fz_featured_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo do_shortcode( '[fanza_items title="人気の作品" sub="いま売れている作品" sort="rank" hits="8"]' );
		echo do_shortcode( '[fanza_items title="新着作品" sub="最近配信された作品" sort="date" hits="8"]' );
	}
);

/* ヘッダーの検索フォーム(品番・女優名・キーワード) */
add_action(
	'affiliate_base_header_after',
	function () {
		?>
		<form class="header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="品番・女優名・キーワードで探す" aria-label="検索">
			<button type="submit">検索</button>
		</form>
		<?php
	}
);

/* 検索結果ページで FANZA の作品も表示 */
add_action(
	'affiliate_base_before_loop',
	function () {
		if ( is_search() && function_exists( 'fz_items' ) && '' !== get_search_query( false ) ) {
			echo '<section class="fz-section">' . fz_items_html( fz_items( array( 'keyword' => get_search_query( false ), 'hits' => 20, 'sort' => 'match' ) ) ) . '</section>'; // phpcs:ignore
			echo '<h2 class="section-title">サイト内の記事</h2>';
		}
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
