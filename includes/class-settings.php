<?php
/**
 * Página de configurações (opção única "mvf_settings").
 */

defined( 'ABSPATH' ) || exit;

class MVF_Settings {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_notices', array( __CLASS__, 'turnstile_warning' ) );
		add_filter(
			'option_page_capability_mvf_settings_group',
			static function () {
				return 'mvf_manage';
			}
		);
	}

	public static function register(): void {
		register_setting(
			'mvf_settings_group',
			'mvf_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => mvf_settings_defaults(),
			)
		);
	}

	public static function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$current = mvf_settings();
		$out     = mvf_settings_defaults();

		$out['active_edition']     = absint( $input['active_edition'] ?? 0 );
		$out['turnstile_site_key'] = sanitize_text_field( $input['turnstile_site_key'] ?? '' );

		// Campo de senha vazio mantém o secret salvo; "-" apaga.
		$secret = sanitize_text_field( $input['turnstile_secret_key'] ?? '' );
		if ( '' === $secret ) {
			$secret = $current['turnstile_secret_key'];
		} elseif ( '-' === $secret ) {
			$secret = '';
		}
		$out['turnstile_secret_key'] = $secret;

		$out['behind_cloudflare']  = empty( $input['behind_cloudflare'] ) ? 0 : 1;
		$out['rate_limit_seconds'] = min( 3600, absint( $input['rate_limit_seconds'] ?? 5 ) );
		$out['delete_data']        = empty( $input['delete_data'] ) ? 0 : 1;
		$out['privacy_text']       = wp_kses_post( $input['privacy_text'] ?? '' );

		$points = array_filter(
			array_map( 'trim', explode( ',', (string) ( $input['points'] ?? '' ) ) ),
			'strlen'
		);
		$points = array_slice( array_map( 'absint', $points ), 0, MVF_MAX_CLIPS );
		if ( $points ) {
			$out['points'] = array_values( $points );
		} else {
			add_settings_error( 'mvf_settings', 'mvf_points', __( 'Tabela de pontos inválida. Mantida a padrão (F1).', 'mvf-voting' ) );
		}

		return $out;
	}

	public static function render(): void {
		if ( ! current_user_can( 'mvf_manage' ) ) {
			return;
		}
		$s        = mvf_settings();
		$editions = mvf_get_editions();
		?>
		<div class="wrap mvf-admin">
			<h1><?php esc_html_e( 'Configurações da votação', 'mvf-voting' ); ?></h1>
			<?php settings_errors( 'mvf_settings' ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'mvf_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mvf-active-edition"><?php esc_html_e( 'Edição ativa', 'mvf-voting' ); ?></label></th>
						<td>
							<select id="mvf-active-edition" name="mvf_settings[active_edition]">
								<option value="0"><?php esc_html_e( '— mais recente —', 'mvf-voting' ); ?></option>
								<?php foreach ( $editions as $ed ) : ?>
									<option value="<?php echo esc_attr( $ed->term_id ); ?>" <?php selected( (int) $s['active_edition'], $ed->term_id ); ?>><?php echo esc_html( $ed->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Edição exibida pelo shortcode [mvf_votacao] quando nenhuma for informada.', 'mvf-voting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mvf-ts-site"><?php esc_html_e( 'Turnstile: Site key', 'mvf-voting' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="mvf-ts-site" name="mvf_settings[turnstile_site_key]" value="<?php echo esc_attr( $s['turnstile_site_key'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="mvf-ts-secret"><?php esc_html_e( 'Turnstile: Secret key', 'mvf-voting' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code" id="mvf-ts-secret" name="mvf_settings[turnstile_secret_key]" value="" autocomplete="new-password"
								placeholder="<?php echo $s['turnstile_secret_key'] ? esc_attr__( '•••••• salvo (deixe vazio para manter)', 'mvf-voting' ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Crie as chaves em dash.cloudflare.com → Turnstile. Digite "-" para apagar o secret salvo.', 'mvf-voting' ); ?><br>
								<?php esc_html_e( 'Chaves de teste: site 1x00000000000000000000AA com secret 1x0000000000000000000000000000000AA (sempre passa) ou 2x0000000000000000000000000000000AA (sempre falha).', 'mvf-voting' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloudflare', 'mvf-voting' ); ?></th>
						<td>
							<label><input type="checkbox" name="mvf_settings[behind_cloudflare]" value="1" <?php checked( $s['behind_cloudflare'] ); ?>>
							<?php esc_html_e( 'O site está atrás do proxy do Cloudflare (usar o cabeçalho CF-Connecting-IP como IP do votante)', 'mvf-voting' ); ?></label>
							<p class="description"><?php esc_html_e( 'Só marque se todo o tráfego passa pelo Cloudflare; caso contrário o cabeçalho pode ser falsificado.', 'mvf-voting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mvf-rate"><?php esc_html_e( 'Intervalo mínimo entre votos (segundos)', 'mvf-voting' ); ?></label></th>
						<td>
							<input type="number" min="0" max="3600" id="mvf-rate" name="mvf_settings[rate_limit_seconds]" value="<?php echo esc_attr( $s['rate_limit_seconds'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Por IP. O público pode votar quantas vezes quiser; isto só segura robôs. 0 desativa.', 'mvf-voting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mvf-points"><?php esc_html_e( 'Pontos do júri por posição', 'mvf-voting' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="mvf-points" name="mvf_settings[points]" value="<?php echo esc_attr( implode( ',', $s['points'] ) ); ?>">
							<p class="description"><?php esc_html_e( 'Separados por vírgula, do 1º ao 10º lugar. Padrão (F1): 25,18,15,12,10,8,6,4,2,1. Alterar recalcula todos os resultados.', 'mvf-voting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mvf-privacy"><?php esc_html_e( 'Aviso de privacidade', 'mvf-voting' ); ?></label></th>
						<td>
							<textarea id="mvf-privacy" name="mvf_settings[privacy_text]" rows="3" class="large-text"><?php echo esc_textarea( $s['privacy_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Exibido abaixo da votação (LGPD). Ex.: "Para evitar fraudes registramos um identificador anônimo do seu acesso. Veja nossa Política de Privacidade."', 'mvf-voting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Desinstalação', 'mvf-voting' ); ?></th>
						<td>
							<label><input type="checkbox" name="mvf_settings[delete_data]" value="1" <?php checked( $s['delete_data'] ); ?>>
							<?php esc_html_e( 'Apagar clipes, categorias, edições e todos os votos ao excluir o plugin', 'mvf-voting' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function turnstile_warning(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! current_user_can( 'mvf_manage' ) || false === strpos( (string) $screen->id, 'mvf' ) ) {
			return;
		}
		if ( MVF_Turnstile::enabled() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'O Cloudflare Turnstile não está configurado: os votos do público estão sem proteção contra robôs.', 'mvf-voting' ),
			esc_url( admin_url( 'admin.php?page=mvf-settings' ) ),
			esc_html__( 'Configurar', 'mvf-voting' )
		);
	}
}
