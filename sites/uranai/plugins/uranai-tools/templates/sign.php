<?php
/**
 * 星座ページのテンプレート(テーマのヘッダー・フッターを使う)
 */
defined( 'ABSPATH' ) || exit;
get_header();
$ut_key = ut_current_sign();
?>
<div class="container-narrow">
	<nav class="breadcrumb" aria-label="パンくずリスト"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a> &rsaquo; <a href="<?php echo esc_url( home_url( '/' ) ); ?>#zodiac-ranking">12星座ランキング</a> &rsaquo; <?php echo esc_html( ut_zodiac_signs()[ $ut_key ]['name'] ); ?></nav>
	<?php echo ut_sign_page_html( $ut_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
