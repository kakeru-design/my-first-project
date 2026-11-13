<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="container">
		<div class="site-branding">
			<?php $tag = is_front_page() ? 'h1' : 'p'; ?>
			<<?php echo $tag; // phpcs:ignore ?> class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></<?php echo $tag; // phpcs:ignore ?>>
			<p class="site-description"><?php bloginfo( 'description' ); ?></p>
		</div>
		<?php if ( has_nav_menu( 'global' ) ) : ?>
			<nav class="global-nav" aria-label="グローバルナビ">
				<?php wp_nav_menu( array( 'theme_location' => 'global', 'container' => false, 'depth' => 1 ) ); ?>
			</nav>
		<?php endif; ?>
	</div>
</header>
<main class="site-main">
