<?php $t =& peTheme(); ?>
<?php $content =& $t->content; ?>
<?php $meta =& $content->meta(); ?>
<?php get_header(); ?>

<?php while ( $content->looping() ) : ?>

	<div class="row">
		<div class="column width-12">

			<div class="header-title">

				<h1><?php $content->title(); ?></h1>

			</div>

		</div>
	</div>

	<?php get_template_part( 'pagecontent' ); ?>

<?php endwhile; ?>

<?php get_footer(); ?>