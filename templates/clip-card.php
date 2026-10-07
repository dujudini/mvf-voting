<?php
/**
 * Card de um clipe: player leve do YouTube, dados, botão de voto e compartilhamento.
 *
 * @var int     $clip_id
 * @var WP_Term $category
 * @var bool    $open
 * @var string  $permalink
 */

defined( 'ABSPATH' ) || exit;

$title      = get_the_title( $clip_id );
$youtube_id = (string) get_post_meta( $clip_id, 'youtube_id', true );
$artista    = (string) get_post_meta( $clip_id, 'artista', true );
$diretor    = (string) get_post_meta( $clip_id, 'diretor', true );
$produtora  = (string) get_post_meta( $clip_id, 'produtora', true );
$anchor     = 'mvf-clip-' . $category->term_id . '-' . $clip_id;
$share_url  = $permalink ? $permalink . '#' . $anchor : '';
/* translators: 1: clipe, 2: categoria */
$share_text = sprintf( __( 'Votei em "%1$s" (%2$s). Vote também:', 'mvf-voting' ), $title, $category->name );
?>
<article class="mvf-card" id="<?php echo esc_attr( $anchor ); ?>">
	<?php if ( $youtube_id ) : ?>
		<button type="button" class="mvf-player" data-youtube="<?php echo esc_attr( $youtube_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: clipe */ __( 'Assistir %s', 'mvf-voting' ), $title ) ); ?>">
			<img src="<?php echo esc_url( mvf_clip_thumb_url( $clip_id ) ); ?>" alt="" loading="lazy" decoding="async">
			<span class="mvf-player__icon" aria-hidden="true"></span>
		</button>
	<?php endif; ?>

	<div class="mvf-card__body">
		<h3 class="mvf-card__title"><?php echo esc_html( $title ); ?></h3>
		<?php if ( $artista ) : ?>
			<p class="mvf-card__artist"><?php echo esc_html( $artista ); ?></p>
		<?php endif; ?>
		<?php if ( $diretor || $produtora ) : ?>
			<p class="mvf-card__credits">
				<?php
				$credits = array();
				if ( $diretor ) {
					/* translators: %s: nome */
					$credits[] = sprintf( __( 'Direção: %s', 'mvf-voting' ), $diretor );
				}
				if ( $produtora ) {
					/* translators: %s: nome */
					$credits[] = sprintf( __( 'Produtora: %s', 'mvf-voting' ), $produtora );
				}
				echo esc_html( implode( ' · ', $credits ) );
				?>
			</p>
		<?php endif; ?>

		<?php if ( $open ) : ?>
			<button type="button" class="mvf-vote" data-clip="<?php echo esc_attr( $clip_id ); ?>" data-category="<?php echo esc_attr( $category->term_id ); ?>">
				<?php esc_html_e( 'Votar', 'mvf-voting' ); ?>
			</button>
			<p class="mvf-feedback" aria-live="polite"></p>

			<?php if ( $share_url ) : ?>
				<div class="mvf-share" hidden>
					<span><?php esc_html_e( 'Chame mais gente:', 'mvf-voting' ); ?></span>
					<a href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $share_text . ' ' . $share_url ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
					<a href="<?php echo esc_url( 'https://twitter.com/intent/tweet?text=' . rawurlencode( $share_text ) . '&url=' . rawurlencode( $share_url ) ); ?>" target="_blank" rel="noopener">X</a>
					<a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $share_url ) ); ?>" target="_blank" rel="noopener">Facebook</a>
					<button type="button" class="mvf-copy" data-url="<?php echo esc_attr( $share_url ); ?>" data-text="<?php echo esc_attr( $share_text ); ?>"><?php esc_html_e( 'Copiar link', 'mvf-voting' ); ?></button>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</article>
