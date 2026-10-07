<?php
/**
 * Página de votação do público.
 *
 * Pode ser sobrescrito pelo tema em /mvf-voting/voting.php.
 *
 * @var int    $edition_id
 * @var array  $sections   Lista de ['category' => WP_Term, 'clips' => int[]].
 * @var array  $period     Resultado de MVF_Periods::status().
 * @var string $permalink
 */

defined( 'ABSPATH' ) || exit;

$privacy = (string) mvf_setting( 'privacy_text' );
?>
<div class="mvf-voting" data-open="<?php echo $period['open'] ? '1' : '0'; ?>">

	<?php mvf_template( 'status', array( 'period' => $period ) ); ?>

	<?php if ( ! $sections ) : ?>
		<p class="mvf-empty"><?php esc_html_e( 'Nenhum clipe disponível para votação.', 'mvf-voting' ); ?></p>
	<?php else : ?>

		<?php if ( count( $sections ) > 1 ) : ?>
			<nav class="mvf-nav" aria-label="<?php esc_attr_e( 'Categorias', 'mvf-voting' ); ?>">
				<?php foreach ( $sections as $section ) : ?>
					<a href="#mvf-cat-<?php echo esc_attr( $section['category']->slug ); ?>"><?php echo esc_html( $section['category']->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php
		foreach ( $sections as $section ) {
			mvf_template(
				'category',
				array(
					'category'  => $section['category'],
					'clips'     => $section['clips'],
					'open'      => $period['open'],
					'permalink' => $permalink,
				)
			);
		}
		?>
	<?php endif; ?>

	<?php if ( $privacy ) : ?>
		<div class="mvf-privacy"><?php echo wp_kses_post( wpautop( $privacy ) ); ?></div>
	<?php endif; ?>

	<div class="mvf-captcha" aria-live="polite">
		<p class="mvf-captcha__label" hidden></p>
		<div id="mvf-turnstile"></div>
	</div>
	<div class="mvf-toast" role="status" aria-live="polite" hidden></div>
</div>
