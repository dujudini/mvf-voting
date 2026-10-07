<?php
/**
 * Tela de edição dos clipes: dados do vídeo, edição/categorias com limite e colunas da listagem.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Clip_Admin {

	const META_FIELDS = array( 'artista', 'diretor', 'produtora', 'ano' );

	public static function init(): void {
		add_action( 'add_meta_boxes_' . MVF_CLIP_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . MVF_CLIP_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . MVF_CLIP_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . MVF_CLIP_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function title_placeholder( $text, $post ) {
		return MVF_CLIP_TYPE === $post->post_type ? __( 'Nome do clipe / música', 'mvf-voting' ) : $text;
	}

	public static function meta_boxes(): void {
		add_meta_box( 'mvf-clip-data', __( 'Dados do clipe', 'mvf-voting' ), array( __CLASS__, 'render_data_box' ), MVF_CLIP_TYPE, 'normal', 'high' );
		add_meta_box( 'mvf-clip-cats', __( 'Edição e categorias', 'mvf-voting' ), array( __CLASS__, 'render_cats_box' ), MVF_CLIP_TYPE, 'side', 'high' );
	}

	public static function render_data_box( WP_Post $post ): void {
		wp_nonce_field( 'mvf_save_clip', 'mvf_clip_nonce' );
		$url   = (string) get_post_meta( $post->ID, 'youtube_url', true );
		$yt_id = (string) get_post_meta( $post->ID, 'youtube_id', true );
		$labels = array(
			'artista'   => __( 'Artista', 'mvf-voting' ),
			'diretor'   => __( 'Direção', 'mvf-voting' ),
			'produtora' => __( 'Produtora', 'mvf-voting' ),
			'ano'       => __( 'Ano', 'mvf-voting' ),
		);
		?>
		<div class="mvf-clip-data">
			<p>
				<label for="mvf-youtube-url"><strong><?php esc_html_e( 'URL do YouTube', 'mvf-voting' ); ?></strong></label><br>
				<input type="url" id="mvf-youtube-url" name="mvf_youtube_url" class="widefat" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
			</p>
			<p class="mvf-yt-preview" <?php echo $yt_id ? '' : 'hidden'; ?>>
				<img src="<?php echo esc_url( mvf_youtube_thumb( $yt_id ) ); ?>" alt="" width="320">
			</p>
			<div class="mvf-fields-grid">
				<?php foreach ( $labels as $key => $label ) : ?>
					<p>
						<label for="mvf-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label><br>
						<input type="<?php echo 'ano' === $key ? 'number' : 'text'; ?>" id="mvf-<?php echo esc_attr( $key ); ?>" name="mvf_meta[<?php echo esc_attr( $key ); ?>]" class="widefat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, $key, true ) ); ?>">
					</p>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function render_cats_box( WP_Post $post ): void {
		$editions = mvf_get_editions();
		if ( ! $editions ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Nenhuma edição cadastrada.', 'mvf-voting' ),
				esc_url( admin_url( 'edit-tags.php?taxonomy=' . MVF_TAX_EDITION . '&post_type=' . MVF_CLIP_TYPE ) ),
				esc_html__( 'Criar edição', 'mvf-voting' )
			);
			return;
		}

		$current_ed = wp_get_object_terms( $post->ID, MVF_TAX_EDITION, array( 'fields' => 'ids' ) );
		$current_ed = ( ! is_wp_error( $current_ed ) && $current_ed ) ? (int) $current_ed[0] : mvf_active_edition_id();
		$current_cats = wp_get_object_terms( $post->ID, MVF_TAX_CAT, array( 'fields' => 'ids' ) );
		$current_cats = is_wp_error( $current_cats ) ? array() : array_map( 'intval', $current_cats );
		?>
		<p>
			<label for="mvf-edition"><strong><?php esc_html_e( 'Edição', 'mvf-voting' ); ?></strong></label><br>
			<select id="mvf-edition" name="mvf_edition" class="widefat">
				<?php foreach ( $editions as $ed ) : ?>
					<option value="<?php echo esc_attr( $ed->term_id ); ?>" <?php selected( $current_ed, $ed->term_id ); ?>><?php echo esc_html( $ed->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><strong><?php esc_html_e( 'Categorias', 'mvf-voting' ); ?></strong></p>
		<?php foreach ( $editions as $ed ) : ?>
			<div class="mvf-cat-group" data-edition="<?php echo esc_attr( $ed->term_id ); ?>" <?php echo $ed->term_id === $current_ed ? '' : 'hidden'; ?>>
				<?php
				$cats = mvf_get_edition_categories( $ed->term_id );
				if ( ! $cats ) {
					echo '<p class="description">' . esc_html__( 'Esta edição ainda não tem categorias.', 'mvf-voting' ) . '</p>';
				}
				foreach ( $cats as $cat ) :
					$checked = in_array( $cat->term_id, $current_cats, true );
					$others  = mvf_category_clip_count( $cat->term_id, $post->ID );
					$max     = mvf_category_max_clips( $cat->term_id );
					$full    = ! $checked && $others >= $max;
					?>
					<label class="mvf-cat-option<?php echo $full ? ' is-full' : ''; ?>">
						<input type="checkbox" name="mvf_categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( $checked ); ?> <?php disabled( $full ); ?>>
						<?php echo esc_html( $cat->name ); ?>
						<span class="mvf-count">(<?php echo esc_html( ( $others + ( $checked ? 1 : 0 ) ) . '/' . $max ); ?>)</span>
						<?php if ( $full ) : ?>
							<em><?php esc_html_e( 'cheia', 'mvf-voting' ); ?></em>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['mvf_clip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mvf_clip_nonce'] ) ), 'mvf_save_clip' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Vídeo.
		$url   = isset( $_POST['mvf_youtube_url'] ) ? esc_url_raw( wp_unslash( $_POST['mvf_youtube_url'] ) ) : '';
		$yt_id = mvf_parse_youtube_id( $url );
		if ( '' !== $url && '' === $yt_id ) {
			mvf_add_notice( __( 'A URL informada não é um vídeo do YouTube válido. O vídeo não foi salvo.', 'mvf-voting' ) );
		} else {
			update_post_meta( $post_id, 'youtube_url', $url );
			update_post_meta( $post_id, 'youtube_id', $yt_id );
		}

		// Metadados simples.
		$meta = isset( $_POST['mvf_meta'] ) && is_array( $_POST['mvf_meta'] ) ? wp_unslash( $_POST['mvf_meta'] ) : array();
		foreach ( self::META_FIELDS as $key ) {
			$value = sanitize_text_field( $meta[ $key ] ?? '' );
			if ( 'ano' === $key && '' !== $value ) {
				$value = (string) absint( $value );
			}
			update_post_meta( $post_id, $key, $value );
		}

		// Edição.
		$edition_id = isset( $_POST['mvf_edition'] ) ? absint( $_POST['mvf_edition'] ) : 0;
		if ( ! $edition_id || ! term_exists( $edition_id, MVF_TAX_EDITION ) ) {
			return;
		}
		wp_set_object_terms( $post_id, array( $edition_id ), MVF_TAX_EDITION );

		// Categorias: só as da edição escolhida e respeitando o limite de cada uma.
		$requested = isset( $_POST['mvf_categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['mvf_categories'] ) ) : array();
		$current   = wp_get_object_terms( $post_id, MVF_TAX_CAT, array( 'fields' => 'ids' ) );
		$current   = is_wp_error( $current ) ? array() : array_map( 'intval', $current );
		$allowed   = array();
		$rejected  = array();

		foreach ( array_unique( $requested ) as $cat_id ) {
			if ( mvf_category_edition_id( $cat_id ) !== $edition_id ) {
				continue;
			}
			if ( ! in_array( $cat_id, $current, true ) && mvf_category_clip_count( $cat_id, $post_id ) >= mvf_category_max_clips( $cat_id ) ) {
				$term       = get_term( $cat_id, MVF_TAX_CAT );
				$rejected[] = $term && ! is_wp_error( $term ) ? $term->name : (string) $cat_id;
				continue;
			}
			$allowed[] = $cat_id;
		}

		wp_set_object_terms( $post_id, $allowed, MVF_TAX_CAT );

		if ( $rejected ) {
			mvf_add_notice(
				sprintf(
					/* translators: %s: lista de categorias */
					__( 'O clipe não foi incluído nestas categorias porque elas já atingiram o limite de clipes: %s.', 'mvf-voting' ),
					esc_html( implode( ', ', $rejected ) )
				)
			);
		}
	}

	public static function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['mvf_thumb'] = '';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['mvf_artista']    = __( 'Artista', 'mvf-voting' );
				$new['mvf_edicao']     = __( 'Edição', 'mvf-voting' );
				$new['mvf_categorias'] = __( 'Categorias', 'mvf-voting' );
			}
		}
		return $new;
	}

	public static function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mvf_thumb':
				$thumb = mvf_clip_thumb_url( $post_id, 'thumbnail' );
				if ( $thumb ) {
					printf( '<img src="%s" alt="" class="mvf-list-thumb">', esc_url( $thumb ) );
				}
				break;
			case 'mvf_artista':
				echo esc_html( (string) get_post_meta( $post_id, 'artista', true ) );
				break;
			case 'mvf_edicao':
			case 'mvf_categorias':
				$tax   = 'mvf_edicao' === $column ? MVF_TAX_EDITION : MVF_TAX_CAT;
				$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'names' ) );
				echo esc_html( is_wp_error( $terms ) ? '' : implode( ', ', $terms ) );
				break;
		}
	}

	public static function filters( string $post_type ): void {
		if ( MVF_CLIP_TYPE !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$sel_ed  = isset( $_GET[ MVF_TAX_EDITION ] ) ? sanitize_title( wp_unslash( $_GET[ MVF_TAX_EDITION ] ) ) : '';
		$sel_cat = isset( $_GET[ MVF_TAX_CAT ] ) ? sanitize_title( wp_unslash( $_GET[ MVF_TAX_CAT ] ) ) : '';
		// phpcs:enable
		$editions = mvf_get_editions();
		echo '<select name="' . esc_attr( MVF_TAX_EDITION ) . '"><option value="">' . esc_html__( 'Todas as edições', 'mvf-voting' ) . '</option>';
		foreach ( $editions as $ed ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $ed->slug ), selected( $sel_ed, $ed->slug, false ), esc_html( $ed->name ) );
		}
		echo '</select>';
		echo '<select name="' . esc_attr( MVF_TAX_CAT ) . '"><option value="">' . esc_html__( 'Todas as categorias', 'mvf-voting' ) . '</option>';
		foreach ( $editions as $ed ) {
			echo '<optgroup label="' . esc_attr( $ed->name ) . '">';
			foreach ( mvf_get_edition_categories( $ed->term_id ) as $cat ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $cat->slug ), selected( $sel_cat, $cat->slug, false ), esc_html( $cat->name ) );
			}
			echo '</optgroup>';
		}
		echo '</select>';
	}
}
