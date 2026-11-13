<?php get_header(); ?>
<div class="container">
	<?php if ( is_home() && ! is_paged() ) : ?>
		<section class="hero">
			<h1><?php bloginfo( 'name' ); ?></h1>
			<p><?php bloginfo( 'description' ); ?></p>
		</section>
		<?php do_action( 'affiliate_base_front_top' ); ?>
	<?php else : ?>
		<?php affiliate_base_breadcrumb(); ?>
		<h1 class="section-title">
			<?php
			if ( is_search() ) {
				printf( '「%s」の検索結果', esc_html( get_search_query() ) );
			} elseif ( is_archive() ) {
				the_archive_title();
			} else {
				echo '新着記事';
			}
			?>
		</h1>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php if ( is_home() && ! is_paged() ) : ?><h2 class="section-title">新着記事</h2><?php endif; ?>
		<div class="post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card' );
			endwhile;
			?>
		</div>
		<div class="pagination"><?php echo paginate_links(); // phpcs:ignore ?></div>
	<?php else : ?>
		<p>記事が見つかりませんでした。</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
