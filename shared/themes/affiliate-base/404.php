<?php get_header(); ?>
<div class="container-narrow">
	<h1 class="entry-title">ページが見つかりません</h1>
	<p>お探しのページは移動または削除された可能性があります。</p>
	<?php get_search_form(); ?>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a></p>
</div>
<?php get_footer(); ?>
