<?php
/**
 * Plugin Name: QNA Realizacje
 * Description: Zdjęcia w sekcji „Realizacje” na stronie. Każda realizacja to zdjęcie, rodzaj usługi (według niego działa filtr na stronie) i opcjonalny krótki opis. Pozwala dodawać, zmieniać i ustawiać kolejność realizacji. Wymaga motywu „QNA Instalacje”.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-realizacje
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qna_realizacje_register() {
	register_post_type( 'qna_realizacja', array(
		'labels'        => array(
			'name'                  => 'Realizacje',
			'singular_name'         => 'Realizacja',
			'menu_name'             => 'Realizacje',
			'all_items'             => 'Wszystkie realizacje',
			'add_new'               => 'Dodaj realizację',
			'add_new_item'          => 'Dodaj realizację',
			'edit_item'             => 'Edytuj realizację',
			'new_item'              => 'Nowa realizacja',
			'search_items'          => 'Szukaj realizacji',
			'not_found'             => 'Brak realizacji',
			'not_found_in_trash'    => 'Brak realizacji w koszu',
			'featured_image'        => 'Zdjęcie',
			'set_featured_image'    => 'Ustaw zdjęcie',
			'remove_featured_image' => 'Usuń zdjęcie',
			'use_featured_image'    => 'Użyj jako zdjęcia',
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'menu_position' => 4,
		'menu_icon'     => 'dashicons-images-alt2',
		'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
		'show_in_rest'  => false,
	) );
}
add_action( 'init', 'qna_realizacje_register' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails', array( 'qna_realizacja' ) );
}, 21 );

function qna_realizacje_types() {
	if ( function_exists( 'qna_real_types' ) ) {
		return qna_real_types();
	}
	return array( 'elektryka' => 'Elektryka', 'klimatyzacja' => 'Klimatyzacja', 'pompy' => 'Pompy ciepła', 'fotowoltaika' => 'Fotowoltaika', 'serwis' => 'Serwis', 'mycie' => 'Mycie paneli' );
}

add_filter( 'enter_title_here', function ( $t, $post ) {
	return 'qna_realizacja' === $post->post_type ? 'Nazwa realizacji (np. Pompa ciepła, Chełmno)' : $t;
}, 10, 2 );

add_action( 'add_meta_boxes_qna_realizacja', function () {
	add_meta_box( 'qna_real_dane', 'Realizacja', 'qna_realizacje_box', 'qna_realizacja', 'normal', 'high' );
	remove_meta_box( 'pageparentdiv', 'qna_realizacja', 'side' );
	add_meta_box( 'qna_real_kolejnosc', 'Kolejność', function ( $post ) {
		printf( '<input type="number" name="menu_order" value="%d" style="width:80px"> <p class="description">Mniejsza liczba = wcześniej na stronie.</p>', (int) $post->menu_order );
	}, 'qna_realizacja', 'side' );
} );

function qna_realizacje_box( $post ) {
	wp_nonce_field( 'qna_real_save', 'qna_real_nonce' );
	$typ  = get_post_meta( $post->ID, '_qna_typ', true );
	$opis = get_post_meta( $post->ID, '_qna_opis', true );
	echo '<table class="form-table" role="presentation"><tr><th><label for="qna-typ">Rodzaj usługi</label></th><td><select id="qna-typ" name="qna_real[typ]">';
	foreach ( qna_realizacje_types() as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $typ, $k, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">Według rodzaju działa filtr w sekcji „Realizacje”.</p></td></tr>';
	echo '<tr><th><label for="qna-opis">Krótki opis</label></th><td><textarea id="qna-opis" name="qna_real[opis]" rows="2" class="large-text">' . esc_textarea( $opis ) . '</textarea><p class="description">Opcjonalnie. Pokazuje się pod zdjęciem.</p></td></tr></table>';
	echo '<p class="description">Zdjęcie ustawiasz w polu „Zdjęcie” po prawej. Realizacja bez zdjęcia nie pokazuje się na stronie.</p>';
}

add_action( 'save_post_qna_realizacja', function ( $post_id ) {
	if ( ! isset( $_POST['qna_real_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qna_real_nonce'] ) ), 'qna_real_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['qna_real'] ) && is_array( $_POST['qna_real'] ) ? wp_unslash( $_POST['qna_real'] ) : array(); // phpcs:ignore
	if ( isset( $in['typ'] ) && array_key_exists( $in['typ'], qna_realizacje_types() ) ) {
		update_post_meta( $post_id, '_qna_typ', sanitize_key( $in['typ'] ) );
	}
	if ( isset( $in['opis'] ) ) {
		update_post_meta( $post_id, '_qna_opis', sanitize_textarea_field( $in['opis'] ) );
	}
} );

add_filter( 'manage_qna_realizacja_posts_columns', function ( $cols ) {
	return array(
		'cb'        => $cols['cb'],
		'qna_foto'  => 'Zdjęcie',
		'title'     => 'Nazwa',
		'qna_typ'   => 'Rodzaj',
		'qna_opis'  => 'Krótki opis',
		'qna_order' => 'Kolejność',
	);
} );
add_action( 'manage_qna_realizacja_posts_custom_column', function ( $col, $id ) {
	if ( 'qna_foto' === $col ) {
		echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 64, 64 ), array( 'style' => 'width:64px;height:64px;object-fit:cover;border-radius:6px' ) ) : '<span style="color:#b32d2e">brak zdjęcia</span>';
	} elseif ( 'qna_typ' === $col ) {
		$t = qna_realizacje_types();
		$k = get_post_meta( $id, '_qna_typ', true );
		echo esc_html( $t[ $k ] ?? $k );
	} elseif ( 'qna_opis' === $col ) {
		echo esc_html( get_post_meta( $id, '_qna_opis', true ) );
	} elseif ( 'qna_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $id );
	}
}, 10, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'qna_realizacja' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
	}
} );

/* Pierwsze uruchomienie: realizacje domyślne z motywu (jeśli lista jest pusta). */
function qna_realizacje_seed() {
	qna_realizacje_register();
	if ( get_posts( array( 'post_type' => 'qna_realizacja', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
		return;
	}
	if ( ! function_exists( 'qna_fields' ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	foreach ( (array) ( qna_fields()['realizacje'] ?? array() ) as $i => $r ) {
		$id = wp_insert_post( array( 'post_type' => 'qna_realizacja', 'post_status' => 'publish', 'post_title' => $r['alt'], 'menu_order' => $i + 1 ) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_qna_typ', $r['typ'] );
		update_post_meta( $id, '_qna_opis', $r['opis'] );
		$src = get_template_directory() . '/img/' . $r['zdjecie'];
		if ( file_exists( $src ) ) {
			$tmp = wp_tempnam( $r['zdjecie'] );
			copy( $src, $tmp );
			$att = media_handle_sideload( array( 'name' => $r['zdjecie'], 'tmp_name' => $tmp ), $id, $r['alt'] );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $id, $att );
			}
		}
	}
}
register_activation_hook( __FILE__, 'qna_realizacje_seed' );
