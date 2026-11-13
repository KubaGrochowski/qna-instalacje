<?php
/**
 * Plugin Name: QNA Konto klienta
 * Description: Rola „Klient QNA” dla właściciela strony. Widzi tylko to, czego potrzebuje do edycji strony: Treść strony, Zdjęcia, Zespół, Realizacje i Zapytania. Bez wpisów, komentarzy, wyglądu, wtyczek, ustawień i narzędzi WordPressa, więc nic nie zepsuje. Konto zakładasz w Użytkownicy → Dodaj nowego → Rola: Klient QNA.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-konto-klienta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QNA_KLIENT_ROLE = 'qna_klient';

/** Uprawnienia roli: tylko to, czego wymagają nasze menu. */
function qna_klient_caps() {
	return array(
		'read'                   => true,
		'upload_files'           => true,  // zdjęcia w Treści strony, Zespole i Realizacjach
		'edit_theme_options'     => true,  // Treść strony, Zdjęcia
		'edit_posts'             => true,  // Zespół, Realizacje, Zapytania
		'edit_others_posts'      => true,
		'edit_published_posts'   => true,
		'publish_posts'          => true,
		'delete_posts'           => true,
		'delete_others_posts'    => true,
		'delete_published_posts' => true,
		'read_private_posts'     => true,  // zapytania z formularza są prywatne
		'edit_private_posts'     => true,
		'delete_private_posts'   => true,
	);
}

function qna_klient_add_role() {
	remove_role( QNA_KLIENT_ROLE );
	add_role( QNA_KLIENT_ROLE, 'Klient QNA', qna_klient_caps() );
}
register_activation_hook( __FILE__, 'qna_klient_add_role' );
register_deactivation_hook( __FILE__, function () {
	remove_role( QNA_KLIENT_ROLE );
} );

function qna_is_klient() {
	$u = wp_get_current_user();
	return $u && in_array( QNA_KLIENT_ROLE, (array) $u->roles, true ) && ! current_user_can( 'manage_options' );
}

/* ---------- Menu: tylko nasze narzędzia ---------- */
add_action( 'admin_menu', function () {
	if ( ! qna_is_klient() ) {
		return;
	}
	foreach ( array( 'edit.php', 'edit-comments.php', 'tools.php', 'themes.php', 'upload.php' ) as $slug ) {
		remove_menu_page( $slug );
	}
}, 999 );

/* Bezpośrednie wejście na ukryte ekrany przekierowuje do Treści strony. */
add_action( 'current_screen', function ( $screen ) {
	if ( ! qna_is_klient() ) {
		return;
	}
	$blocked_bases = array( 'edit-comments', 'tools', 'themes', 'customize', 'nav-menus', 'widgets', 'upload', 'import', 'export', 'site-health', 'theme-editor', 'comment' );
	$is_post       = in_array( $screen->base, array( 'edit', 'post' ), true ) && 'post' === $screen->post_type;
	if ( $is_post || in_array( $screen->base, $blocked_bases, true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=qna-tresc' ) );
		exit;
	}
} );

/* Pasek u góry: bez „Dodaj wpis”, komentarzy i logo WordPressa. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! qna_is_klient() ) {
		return;
	}
	foreach ( array( 'wp-logo', 'comments', 'new-content', 'customize', 'updates', 'search' ) as $id ) {
		$bar->remove_node( $id );
	}
}, 999 );

/* Kokpit: tylko nasz opis narzędzi. */
add_action( 'wp_dashboard_setup', function () {
	if ( ! qna_is_klient() ) {
		return;
	}
	global $wp_meta_boxes;
	foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? array() ) as $context => $prios ) {
		foreach ( (array) $prios as $prio => $boxes ) {
			foreach ( array_keys( (array) $boxes ) as $id ) {
				if ( 'qna_opis' !== $id ) {
					unset( $wp_meta_boxes['dashboard'][ $context ][ $prio ][ $id ] );
				}
			}
		}
	}
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}, 999 );

/* Biblioteka zdjęć: klient widzi wszystkie pliki (np. zdjęcia z motywu), ale może usuwać tylko swoje. */
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	if ( 'delete_post' === $cap && ! empty( $args[0] ) && 'attachment' === get_post_type( $args[0] ) ) {
		$u = get_userdata( $user_id );
		if ( $u && in_array( QNA_KLIENT_ROLE, (array) $u->roles, true ) && (int) get_post_field( 'post_author', $args[0] ) !== (int) $user_id ) {
			return array( 'do_not_allow' );
		}
	}
	return $caps;
}, 10, 4 );
