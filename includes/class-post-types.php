<?php
/**
 * CPT de clipes e taxonomias de edição e categoria.
 */

defined( 'ABSPATH' ) || exit;

class MVF_Post_Types {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		$manage = array(
			'edit_posts'             => 'mvf_manage',
			'edit_others_posts'      => 'mvf_manage',
			'edit_private_posts'     => 'mvf_manage',
			'edit_published_posts'   => 'mvf_manage',
			'publish_posts'          => 'mvf_manage',
			'read_private_posts'     => 'mvf_manage',
			'delete_posts'           => 'mvf_manage',
			'delete_private_posts'   => 'mvf_manage',
			'delete_published_posts' => 'mvf_manage',
			'delete_others_posts'    => 'mvf_manage',
			'create_posts'           => 'mvf_manage',
		);

		register_post_type(
			MVF_CLIP_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Clipes', 'mvf-voting' ),
					'singular_name'      => __( 'Clipe', 'mvf-voting' ),
					'add_new'            => __( 'Adicionar clipe', 'mvf-voting' ),
					'add_new_item'       => __( 'Adicionar novo clipe', 'mvf-voting' ),
					'edit_item'          => __( 'Editar clipe', 'mvf-voting' ),
					'new_item'           => __( 'Novo clipe', 'mvf-voting' ),
					'search_items'       => __( 'Buscar clipes', 'mvf-voting' ),
					'not_found'          => __( 'Nenhum clipe encontrado.', 'mvf-voting' ),
					'not_found_in_trash' => __( 'Nenhum clipe na lixeira.', 'mvf-voting' ),
					'all_items'          => __( 'Clipes', 'mvf-voting' ),
					'featured_image'     => __( 'Capa (opcional, substitui a thumbnail do YouTube)', 'mvf-voting' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'mvf',
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type' => array( 'mvf_clip', 'mvf_clips' ),
				'capabilities'    => $manage,
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);

		$term_caps = array(
			'manage_terms' => 'mvf_manage',
			'edit_terms'   => 'mvf_manage',
			'delete_terms' => 'mvf_manage',
			'assign_terms' => 'mvf_manage',
		);

		register_taxonomy(
			MVF_TAX_EDITION,
			MVF_CLIP_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Edições', 'mvf-voting' ),
					'singular_name' => __( 'Edição', 'mvf-voting' ),
					'add_new_item'  => __( 'Adicionar edição', 'mvf-voting' ),
					'edit_item'     => __( 'Editar edição', 'mvf-voting' ),
					'search_items'  => __( 'Buscar edições', 'mvf-voting' ),
					'not_found'     => __( 'Nenhuma edição encontrada.', 'mvf-voting' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_rest'      => false,
				'show_tagcloud'     => false,
				'show_admin_column' => false,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
				'query_var'         => MVF_TAX_EDITION,
				'capabilities'      => $term_caps,
			)
		);

		register_taxonomy(
			MVF_TAX_CAT,
			MVF_CLIP_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Categorias', 'mvf-voting' ),
					'singular_name' => __( 'Categoria', 'mvf-voting' ),
					'add_new_item'  => __( 'Adicionar categoria', 'mvf-voting' ),
					'edit_item'     => __( 'Editar categoria', 'mvf-voting' ),
					'search_items'  => __( 'Buscar categorias', 'mvf-voting' ),
					'not_found'     => __( 'Nenhuma categoria encontrada.', 'mvf-voting' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_rest'      => false,
				'show_tagcloud'     => false,
				'show_admin_column' => false,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
				'query_var'         => MVF_TAX_CAT,
				'capabilities'      => $term_caps,
			)
		);
	}
}
