<article <?php post_class( 'card' ); ?>>
	<a href="<?php the_permalink(); ?>">
		<div class="card-thumb">
			<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); } ?>
		</div>
		<div class="card-body">
			<?php $cats = get_the_category(); ?>
			<?php if ( $cats ) : ?><span class="card-cat"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
			<h3 class="card-title"><?php the_title(); ?></h3>
			<time class="card-date" datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date() ); ?></time>
		</div>
	</a>
</article>
