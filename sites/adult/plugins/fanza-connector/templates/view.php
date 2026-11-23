<?php
/**
 * プラグインが用意するページ共通のテンプレート(テーマのヘッダー・フッターを使う)
 */
defined( 'ABSPATH' ) || exit;
get_header();
$fz_v    = fz_view();
$fz_meta = fz_view_meta( $fz_v );
?>
<div class="container">
	<nav class="breadcrumb" aria-label="パンくずリスト">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">ホーム</a>
		<?php foreach ( $fz_meta['crumb'] as $fz_c ) : ?>
			&rsaquo; <?php echo $fz_c[0] ? '<a href="' . esc_url( $fz_c[0] ) . '">' . esc_html( $fz_c[1] ) . '</a>' : esc_html( $fz_c[1] ); ?>
		<?php endforeach; ?>
	</nav>
	<?php echo fz_view_html( $fz_v ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
