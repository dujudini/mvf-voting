<?php
/**
 * Faixa com o status da votação (aberta até…, abre em…, encerrada).
 *
 * @var array $period Resultado de MVF_Periods::status().
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="mvf-status mvf-status--<?php echo esc_attr( $period['state'] ); ?>">
	<?php echo esc_html( MVF_Periods::message( $period ) ); ?>
</p>
