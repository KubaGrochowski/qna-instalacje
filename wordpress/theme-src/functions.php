<?php
/**
 * QNA Instalacje: motyw strony-wizytówki.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QNA_VERSION', '1.0.0' );
define( 'QNA_IMG', get_template_directory_uri() . '/img/' );

require_once get_template_directory() . '/content.php';

/** Adres, na który trafiają zapytania z formularza (Treść strony → Kontakt i dane firmy). */
function qna_contact_email() {
	$email = qna_get( 'kontakt.formularz_email' );
	return is_email( $email ) ? $email : get_option( 'admin_email' );
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'script', 'style' ) );
} );

add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_front_page() ) {
		$parts = array( 'title' => 'QNA Instalacje' );
	}
	return $parts;
} );

add_action( 'wp_enqueue_scripts', function () {
	$dir = get_template_directory_uri();
	wp_enqueue_style( 'qna-fonts', 'https://fonts.googleapis.com/css2?family=Anton&family=Onest:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap', array(), null );
	wp_enqueue_style( 'qna-site', $dir . '/assets/site.css', array(), QNA_VERSION );
	wp_enqueue_script( 'qna-site', $dir . '/assets/site.js', array(), QNA_VERSION, true );
	wp_add_inline_script( 'qna-site', 'window.QNA_FORM = ' . wp_json_encode( array(
		'url'   => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'qna_contact' ),
	) ) . ';', 'before' );

	// Strona nie korzysta z bloków WordPressa: nie ładujemy ich stylów.
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}, 20 );

add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="preload" as="image" href="' . esc_url( qna_img( 'start.zdjecie' ) ) . '" fetchpriority="high">' . "\n";
	if ( ! has_site_icon() ) {
		echo '<link rel="icon" type="image/png" href="' . esc_url( QNA_IMG . 'favicon.png' ) . '">' . "\n";
	}
}, 1 );

// Bez paska admina na podglądzie strony (strona ma własny, przyklejony header).
add_filter( 'show_admin_bar', '__return_false' );


/* ---------- Formularz kontaktowy ---------- */
add_action( 'wp_ajax_qna_contact', 'qna_handle_contact' );
add_action( 'wp_ajax_nopriv_qna_contact', 'qna_handle_contact' );

function qna_handle_contact() {
	if ( ! check_ajax_referer( 'qna_contact', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.' ), 400 );
	}
	// Pole-pułapka: wypełniają je tylko boty.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success();
	}

	$f = function ( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	};
	$name    = $f( 'name' );
	$phone   = $f( 'phone' );
	$email   = sanitize_email( $f( 'email' ) );
	$city    = $f( 'city' );
	$service = $f( 'service' );
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$consent = ! empty( $_POST['consent'] );

	$services = array(
		'elektryka'    => 'Instalacje elektryczne i niskoprądowe',
		'klimatyzacja' => 'Klimatyzacja, wentylacja',
		'pompy'        => 'Instalacje hydrauliczne, pompy ciepła',
		'fotowoltaika' => 'Fotowoltaika, magazyny energii',
		'serwis'       => 'Serwis pomp ciepła',
		'mycie'        => 'Mycie paneli fotowoltaicznych',
		'inne'         => 'Inne',
	);

	$errors = array();
	if ( mb_strlen( $name ) < 2 ) {
		$errors[] = 'imię i nazwisko';
	}
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 9 ) {
		$errors[] = 'telefon';
	}
	if ( mb_strlen( $message ) < 5 ) {
		$errors[] = 'wiadomość';
	}
	if ( ! $consent ) {
		$errors[] = 'zgoda na przetwarzanie danych';
	}

	$lines = array(
		'Imię i nazwisko: ' . $name,
		'Telefon: ' . $phone,
		'E-mail: ' . ( $email ? $email : '—' ),
		'Miejscowość inwestycji: ' . ( $city ? $city : '—' ),
		'Usługa: ' . ( isset( $services[ $service ] ) ? $services[ $service ] : '—' ),
	);

	if ( 'mycie' === $service ) {
		$panels  = absint( $f( 'panels' ) );
		$mount   = $f( 'mount' );
		$address = $f( 'address' );
		$roof    = str_replace( ',', '.', $f( 'roof_height' ) );
		if ( $panels < 1 ) {
			$errors[] = 'liczba paneli';
		}
		if ( ! in_array( $mount, array( 'dach', 'grunt' ), true ) ) {
			$errors[] = 'położenie paneli';
		}
		if ( mb_strlen( $address ) < 5 ) {
			$errors[] = 'adres lokalizacji';
		}
		if ( 'dach' === $mount && ! ( (float) $roof > 0 ) ) {
			$errors[] = 'wysokość dachu';
		}
		$lines[] = '';
		$lines[] = 'Mycie paneli:';
		$lines[] = '- liczba paneli: ' . $panels;
		$lines[] = '- położenie: ' . ( 'dach' === $mount ? 'na dachu' : 'na gruncie' );
		if ( 'dach' === $mount ) {
			$lines[] = '- wysokość dachu: ' . $roof . ' m';
		}
		$lines[] = '- adres: ' . $address;
	}

	if ( $errors ) {
		wp_send_json_error( array( 'message' => 'Uzupełnij: ' . implode( ', ', $errors ) . '.' ), 422 );
	}

	$lines[] = '';
	$lines[] = 'Wiadomość:';
	$lines[] = $message;
	$body    = implode( "\n", $lines );
	$subject = 'Zapytanie ze strony: ' . $name;
	$headers = array();
	if ( $email ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}

	// Wtyczka „QNA Formularz” zapisuje zapytanie w panelu (Zapytania), zanim spróbujemy wysłać e-mail.
	$saved_id = (int) apply_filters( 'qna_contact_save', 0, array(
		'name'    => $name,
		'phone'   => $phone,
		'email'   => $email,
		'city'    => $city,
		'service' => isset( $services[ $service ] ) ? $services[ $service ] : '',
		'subject' => $subject,
		'body'    => $body,
	) );

	// Lokalnie bez skonfigurowanej poczty: zapytanie trafia też do pliku wp-content/uploads/qna-zapytania.log.
	if ( 'local' === wp_get_environment_type() && ! apply_filters( 'qna_smtp_configured', false ) ) {
		$up = wp_upload_dir();
		file_put_contents( trailingslashit( $up['basedir'] ) . 'qna-zapytania.log', '[' . current_time( 'mysql' ) . "] {$subject}
{$body}

", FILE_APPEND );
		do_action( 'qna_contact_mail_result', $saved_id, false, 'lokalnie: zapisano do pliku, bez wysyłki' );
		wp_send_json_success();
	}

	$sent = wp_mail( qna_contact_email(), $subject, $body, $headers );
	do_action( 'qna_contact_mail_result', $saved_id, $sent, $sent ? '' : 'wysyłka e-mail nie powiodła się' );
	// Zapytanie jest bezpieczne, jeśli zapisała je wtyczka, nawet gdy e-mail nie wyszedł.
	if ( $sent || $saved_id ) {
		wp_send_json_success();
	}
	wp_send_json_error( array( 'message' => 'Nie udało się wysłać wiadomości. Zadzwoń do nas: ' . qna_get( 'kontakt.telefon' ) . '.' ), 500 );
}
