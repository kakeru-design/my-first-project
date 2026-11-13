</main>
<footer class="site-footer">
	<div class="container">
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => 'nav', 'depth' => 1 ) ); ?>
		<?php endif; ?>
		<?php do_action( 'affiliate_base_footer_notes' ); ?>
		<p>当サイトはアフィリエイトプログラムを利用して商品・サービスを紹介しています。</p>
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
