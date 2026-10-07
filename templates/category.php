<?php
/**
 * Uma categoria com a grade de clipes.
 *
 * @var WP_Term $category
 * @var int[]   $clips
 * @var bool    $open
 * @var string  $permalink
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="mvf-category" id="mvf-cat-<?php echo esc_attr( $category->slug ); ?>">
	<h2 class="mvf-category__title"><?php echo esc_html( $category->name ); ?></h2>
	<?php if ( $category->description ) : ?>
		<div class="mvf-category__desc"><?php echo wp_kses_post( wpautop( $category->description ) ); ?></div>
	<?php endif; ?>

	<div class="mvf-grid">
		<?php
		foreach ( $clips as $clip_id ) {
			mvf_template(
				'clip-card',
				array(
					'clip_id'   => $clip_id,
					'category'  => $category,
					'open'      => $open,
					'permalink' => $permalink,
				)
			);
		}
		?>
	</div>
</section>
