<?php
/**
 * プラグインが用意するページ共通のテンプレート(テーマのヘッダー・フッターを使う)
 */
defined( 'ABSPATH' ) || exit;
get_header();
$ut_v    = ut_view();
$ut_meta = ut_view_meta( $ut_v );
?>
<div class="container-narrow">
	<nav class="breadcrumb" aria-label="パンくずリスト">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a>
		<?php foreach ( $ut_meta['crumb'] as $ut_c ) : ?>
			&rsaquo; <?php echo $ut_c[0] ? '<a href="' . esc_url( $ut_c[0] ) . '">' . esc_html( $ut_c[1] ) . '</a>' : esc_html( $ut_c[1] ); ?>
		<?php endforeach; ?>
	</nav>
	<?php echo ut_view_html( $ut_v ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
