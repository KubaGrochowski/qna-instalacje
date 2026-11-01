<?php
/**
 * Plugin Name: QNA Zespół
 * Description: Osoby w sekcji „Zespół” na stronie: imię i nazwisko, rola, krótki opis i zdjęcie główne. Pozwala dodawać kolejne osoby i ustalać ich kolejność. Zdjęcie główne osoby jest to samo co w menu „Zdjęcia”.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-zespol
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QNA_ZESPOL_META = array(
	'rola'          => array( 'Rola', 'text', 'np. Właściciel' ),
	'opis'          => array( 'Krótki opis', 'textarea', 'Pokazuje się w okienku po kliknięciu zdjęcia.' ),
	'doswiadczenie' => array( 'Doświadczenie', 'textarea', 'Opcjonalnie. Puste pole się nie pokazuje.' ),
	'umiejetnosci'  => array( 'Umiejętności', 'textarea', 'Opcjonalnie.' ),
	'zadania'       => array( 'W firmie', 'textarea', 'Opcjonalnie: czym ta osoba zajmuje się w firmie.' ),
);

function qna_zespol_register() {
	register_post_type( 'qna_osoba', array(
		'labels'       => array(
			'name'                  => 'Zespół',
			'singular_name'         => 'Osoba',
			'menu_name'             => 'Zespół',
			'all_items'             => 'Wszystkie osoby',
			'add_new'               => 'Dodaj osobę',
			'add_new_item'          => 'Dodaj osobę',
			'edit_item'             => 'Edytuj osobę',
			'new_item'              => 'Nowa osoba',
			'search_items'          => 'Szukaj osób',
			'not_found'             => 'Brak osób',
			'not_found_in_trash'    => 'Brak osób w koszu',
			'featured_image'        => 'Zdjęcie główne',
			'set_featured_image'    => 'Ustaw zdjęcie główne',
			'remove_featured_image' => 'Usuń zdjęcie główne',
			'use_featured_image'    => 'Użyj jako zdjęcia głównego',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_position' => 4,
		'menu_icon'    => 'dashicons-groups',
		'supports'     => array( 'title', 'thumbnail', 'page-attributes' ),
		'show_in_rest' => false,
	) );
}
add_action( 'init', 'qna_zespol_register' );

// Okienko „Zdjęcie główne” przy osobach (niezależnie od motywu).
add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails', array( 'qna_osoba' ) );
}, 20 );

add_filter( 'enter_title_here', function ( $t, $post ) {
	return 'qna_osoba' === $post->post_type ? 'Imię i nazwisko' : $t;
}, 10, 2 );

add_action( 'add_meta_boxes_qna_osoba', function () {
	add_meta_box( 'qna_osoba_dane', 'Dane osoby', 'qna_zespol_box', 'qna_osoba', 'normal', 'high' );
	remove_meta_box( 'pageparentdiv', 'qna_osoba', 'side' );
	add_meta_box( 'qna_osoba_kolejnosc', 'Kolejność', function ( $post ) {
		printf( '<input type="number" name="menu_order" value="%d" style="width:80px"> <p class="description">Mniejsza liczba = wcześniej na stronie.</p>', (int) $post->menu_order );
	}, 'qna_osoba', 'side' );
} );

function qna_zespol_box( $post ) {
	wp_nonce_field( 'qna_osoba_save', 'qna_osoba_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( QNA_ZESPOL_META as $key => $f ) {
		list( $label, $type, $help ) = $f;
		$v = get_post_meta( $post->ID, '_qna_' . $key, true );
		echo '<tr><th><label for="qna-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="qna-' . esc_attr( $key ) . '" name="qna_osoba[' . esc_attr( $key ) . ']" rows="3" class="large-text">' . esc_textarea( $v ) . '</textarea>';
		} else {
			echo '<input type="text" id="qna-' . esc_attr( $key ) . '" name="qna_osoba[' . esc_attr( $key ) . ']" value="' . esc_attr( $v ) . '" class="regular-text">';
		}
		echo '<p class="description">' . esc_html( $help ) . '</p></td></tr>';
	}
	echo '</table>';
}

add_action( 'save_post_qna_osoba', function ( $post_id ) {
	if ( ! isset( $_POST['qna_osoba_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qna_osoba_nonce'] ) ), 'qna_osoba_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['qna_osoba'] ) && is_array( $_POST['qna_osoba'] ) ? wp_unslash( $_POST['qna_osoba'] ) : array(); // phpcs:ignore
	foreach ( QNA_ZESPOL_META as $key => $f ) {
		if ( isset( $in[ $key ] ) ) {
			$v = 'textarea' === $f[1] ? sanitize_textarea_field( $in[ $key ] ) : sanitize_text_field( $in[ $key ] );
			update_post_meta( $post_id, '_qna_' . $key, $v );
		}
	}
} );

/* Lista osób: zdjęcie, rola, kolejność */
add_filter( 'manage_qna_osoba_posts_columns', function ( $cols ) {
	return array(
		'cb'         => $cols['cb'],
		'qna_foto'   => 'Zdjęcie',
		'title'      => 'Imię i nazwisko',
		'qna_rola'   => 'Rola',
		'qna_order'  => 'Kolejność',
	);
} );
add_action( 'manage_qna_osoba_posts_custom_column', function ( $col, $id ) {
	if ( 'qna_foto' === $col ) {
		echo has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, array( 56, 56 ), array( 'style' => 'width:56px;height:56px;object-fit:cover;border-radius:6px' ) ) : '<span style="color:#8c8f94">brak</span>';
	} elseif ( 'qna_rola' === $col ) {
		echo esc_html( get_post_meta( $id, '_qna_rola', true ) );
	} elseif ( 'qna_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $id );
	}
}, 10, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'qna_osoba' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'ASC' ) );
	}
} );

/* Pierwsze uruchomienie: osoby domyślne z motywu (jeśli lista jest pusta). */
function qna_zespol_seed() {
	qna_zespol_register();
	if ( get_posts( array( 'post_type' => 'qna_osoba', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
		return;
	}
	if ( ! function_exists( 'qna_fields' ) ) {
		return;
	}
	$fields = qna_fields();
	foreach ( (array) ( $fields['team'] ?? array() ) as $i => $m ) {
		$id = wp_insert_post( array( 'post_type' => 'qna_osoba', 'post_status' => 'publish', 'post_title' => $m['imie'], 'menu_order' => $i + 1 ) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_qna_rola', $m['rola'] );
		update_post_meta( $id, '_qna_opis', $m['opis'] );
		$map = array( 'Doświadczenie' => 'doswiadczenie', 'Umiejętności' => 'umiejetnosci', 'W firmie' => 'zadania' );
		foreach ( $m['info'] as $row ) {
			if ( isset( $map[ $row[0] ] ) ) {
				update_post_meta( $id, '_qna_' . $map[ $row[0] ], $row[1] );
			}
		}
		if ( ! empty( $m['zdjecie'] ) ) {
			$src = get_template_directory() . '/img/' . $m['zdjecie'];
			if ( file_exists( $src ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$tmp = wp_tempnam( $m['zdjecie'] );
				copy( $src, $tmp );
				$att = media_handle_sideload( array( 'name' => $m['zdjecie'], 'tmp_name' => $tmp ), $id );
				if ( ! is_wp_error( $att ) ) {
					set_post_thumbnail( $id, $att );
				}
			}
		}
	}
}
register_activation_hook( __FILE__, 'qna_zespol_seed' );
