<?php
/**
 * Plugin Name: QNA Formularz
 * Description: Zapytania z formularza kontaktowego. Każde zapytanie zapisuje się w panelu (menu „Zapytania”), więc nie zginie, nawet gdy e-mail nie dojdzie. Zawiera ustawienia poczty (SMTP), przez którą zapytania są wysyłane na e-mail firmy. Wymaga motywu „QNA Instalacje”.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-formularz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QNA_SMTP_OPTION = 'qna_smtp';

/* ---------------- Zapytania ---------------- */

add_action( 'init', function () {
	register_post_type( 'qna_zapytanie', array(
		'labels'        => array(
			'name'          => 'Zapytania',
			'singular_name' => 'Zapytanie',
			'menu_name'     => 'Zapytania',
			'all_items'     => 'Wszystkie zapytania',
			'edit_item'     => 'Zapytanie',
			'search_items'  => 'Szukaj zapytań',
			'not_found'     => 'Brak zapytań',
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'menu_position' => 3,
		'menu_icon'     => 'dashicons-email-alt',
		'supports'      => array( 'title' ),
		'capabilities'  => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'  => true,
		'show_in_rest'  => false,
	) );
} );

/** Zapis zapytania (wywoływane przez motyw). Zwraca ID wpisu. */
add_filter( 'qna_contact_save', function ( $id, $d ) {
	$post_id = wp_insert_post( array(
		'post_type'    => 'qna_zapytanie',
		'post_status'  => 'private',
		'post_title'   => $d['name'] . ( $d['service'] ? ' – ' . $d['service'] : '' ),
		'post_content' => $d['body'],
	) );
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return $id;
	}
	foreach ( array( 'name', 'phone', 'email', 'city', 'service' ) as $k ) {
		update_post_meta( $post_id, '_qna_' . $k, $d[ $k ] );
	}
	update_post_meta( $post_id, '_qna_nowe', 1 );
	return $post_id;
}, 10, 2 );

add_action( 'qna_contact_mail_result', function ( $post_id, $sent, $note ) {
	if ( $post_id ) {
		update_post_meta( $post_id, '_qna_mail', $sent ? 'wysłano' : ( $note ? $note : 'nie wysłano' ) );
	}
}, 10, 3 );

add_filter( 'manage_qna_zapytanie_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox">',
		'title'     => 'Od kogo / usługa',
		'qna_tel'   => 'Telefon',
		'qna_email' => 'E-mail',
		'qna_mail'  => 'Wysyłka e-mail',
		'date'      => 'Data',
	);
} );
add_action( 'manage_qna_zapytanie_posts_custom_column', function ( $col, $id ) {
	if ( 'qna_tel' === $col ) {
		echo esc_html( get_post_meta( $id, '_qna_phone', true ) );
	} elseif ( 'qna_email' === $col ) {
		echo esc_html( get_post_meta( $id, '_qna_email', true ) );
	} elseif ( 'qna_mail' === $col ) {
		$m = get_post_meta( $id, '_qna_mail', true );
		$c = 'wysłano' === $m ? '#008a20' : '#b32d2e';
		echo '<span style="color:' . esc_attr( $c ) . '">' . esc_html( $m ? $m : '—' ) . '</span>';
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'qna_zapytanie' === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'] );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = str_replace( '>Edytuj<', '>Otwórz<', $actions['edit'] );
		}
	}
	return $actions;
}, 10, 2 );

/* Licznik nowych zapytań przy menu */
add_action( 'admin_menu', function () {
	global $menu;
	$n = count( get_posts( array( 'post_type' => 'qna_zapytanie', 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_qna_nowe', 'meta_value' => 1 ) ) );
	if ( ! $n ) {
		return;
	}
	foreach ( $menu as $k => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=qna_zapytanie' === $item[2] ) {
			$menu[ $k ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>';
		}
	}
}, 99 );

/* Podgląd zapytania (tylko do odczytu) */
add_action( 'add_meta_boxes_qna_zapytanie', function ( $post ) {
	remove_meta_box( 'submitdiv', 'qna_zapytanie', 'side' );
	add_meta_box( 'qna_zap', 'Treść zapytania', function ( $post ) {
		delete_post_meta( $post->ID, '_qna_nowe' );
		$tel   = get_post_meta( $post->ID, '_qna_phone', true );
		$email = get_post_meta( $post->ID, '_qna_email', true );
		echo '<p><strong>Data:</strong> ' . esc_html( get_the_date( 'j.m.Y, H:i', $post ) ) . '</p>';
		if ( $tel ) {
			echo '<p><strong>Telefon:</strong> <a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a></p>';
		}
		if ( $email ) {
			echo '<p><strong>E-mail:</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
		}
		echo '<pre style="white-space:pre-wrap;font:14px/1.6 inherit;background:#f6f7f7;padding:14px;border-radius:6px">' . esc_html( $post->post_content ) . '</pre>';
		echo '<p class="description">Wysyłka e-mail: ' . esc_html( get_post_meta( $post->ID, '_qna_mail', true ) ) . '</p>';
	}, 'qna_zapytanie', 'normal', 'high' );
} );
add_action( 'admin_head-post.php', function () {
	if ( 'qna_zapytanie' === get_post_type() ) {
		echo '<style>#titlediv{pointer-events:none}#titlediv input{background:transparent;border:0;box-shadow:none}</style>';
	}
} );

/* ---------------- Poczta (SMTP) ---------------- */

function qna_smtp() {
	$o = get_option( QNA_SMTP_OPTION, array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), array( 'host' => '', 'port' => '587', 'secure' => 'tls', 'user' => '', 'pass' => '', 'from' => '', 'from_name' => 'Strona QNA Instalacje' ) );
}

add_filter( 'qna_smtp_configured', function () {
	return '' !== qna_smtp()['host'];
} );

/** Nadawca = skrzynka z ustawień (domyślny wordpress@domena bywa odrzucany lub trafia do SPAM-u). */
function qna_smtp_from() {
	$o    = qna_smtp();
	$from = $o['from'] ? $o['from'] : $o['user'];
	return ( '' !== $o['host'] && is_email( $from ) ) ? $from : '';
}
add_filter( 'wp_mail_from', function ( $from ) {
	$f = qna_smtp_from();
	return $f ? $f : $from;
}, 20 );
add_filter( 'wp_mail_from_name', function ( $name ) {
	return qna_smtp_from() ? qna_smtp()['from_name'] : $name;
}, 20 );

add_action( 'phpmailer_init', function ( $m ) {
	$o = qna_smtp();
	if ( '' === $o['host'] ) {
		return;
	}
	$m->isSMTP();
	$m->Host       = $o['host'];
	$m->Port       = (int) $o['port'];
	$m->SMTPSecure = 'none' === $o['secure'] ? '' : $o['secure'];
	$m->SMTPAutoTLS = 'none' !== $o['secure'];
	$m->SMTPAuth   = '' !== $o['user'];
	$m->Username   = $o['user'];
	$m->Password   = $o['pass'];
	$from          = $o['from'] ? $o['from'] : $o['user'];
	if ( is_email( $from ) ) {
		$m->setFrom( $from, $o['from_name'], false );
	}
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=qna_zapytanie', 'Poczta (SMTP)', 'Poczta (SMTP)', 'manage_options', 'qna-smtp', 'qna_smtp_page' );
} );

add_action( 'admin_post_qna_smtp_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'qna_smtp_save' );
	$in  = isset( $_POST['smtp'] ) && is_array( $_POST['smtp'] ) ? wp_unslash( $_POST['smtp'] ) : array(); // phpcs:ignore
	$old = qna_smtp();
	$new = array(
		'host'      => sanitize_text_field( $in['host'] ?? '' ),
		'port'      => (string) absint( $in['port'] ?? 587 ),
		'secure'    => in_array( $in['secure'] ?? '', array( 'tls', 'ssl', 'none' ), true ) ? $in['secure'] : 'tls',
		'user'      => sanitize_text_field( $in['user'] ?? '' ),
		'pass'      => '' !== ( $in['pass'] ?? '' ) ? (string) $in['pass'] : $old['pass'], // puste pole = bez zmiany hasła
		'from'      => sanitize_email( $in['from'] ?? '' ),
		'from_name' => sanitize_text_field( $in['from_name'] ?? '' ),
	);
	update_option( QNA_SMTP_OPTION, $new, false );
	$msg = 'saved';
	if ( ! empty( $_POST['qna_test'] ) ) {
		$to    = function_exists( 'qna_contact_email' ) ? qna_contact_email() : get_option( 'admin_email' );
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			$error = $e->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );
		$ok = wp_mail( $to, 'Test poczty ze strony QNA Instalacje', "To jest wiadomość testowa. Jeśli ją widzisz, zapytania z formularza będą docierać na ten adres.\n\nWysłano: " . current_time( 'mysql' ) );
		remove_action( 'wp_mail_failed', $catch );
		set_transient( 'qna_smtp_test', array( $ok, $to, $error ), 120 );
		$msg = 'test';
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=qna_zapytanie&page=qna-smtp&m=' . $msg ) );
	exit;
} );

function qna_smtp_page() {
	$o   = qna_smtp();
	$msg = isset( $_GET['m'] ) ? sanitize_key( $_GET['m'] ) : ''; // phpcs:ignore
	echo '<div class="wrap"><h1>Poczta (SMTP)</h1>';
	echo '<p>Dane skrzynki, z której strona wysyła zapytania z formularza. Zapytania przychodzą na adres ustawiony w „Treść strony” → Kontakt i dane firmy. Dane dostępowe da dostawca poczty (hosting).</p>';
	if ( 'saved' === $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>Zapisano ustawienia poczty.</p></div>';
	}
	if ( 'test' === $msg && ( $t = get_transient( 'qna_smtp_test' ) ) ) {
		list( $ok, $to, $error ) = $t;
		echo $ok
			? '<div class="notice notice-success"><p>Wysłano wiadomość testową na <strong>' . esc_html( $to ) . '</strong>. Sprawdź skrzynkę (także SPAM).</p></div>'
			: '<div class="notice notice-error"><p>Nie udało się wysłać wiadomości testowej na ' . esc_html( $to ) . '. Błąd: ' . esc_html( $error ? $error : 'brak szczegółów' ) . '</p></div>';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="qna_smtp_save">
		<?php wp_nonce_field( 'qna_smtp_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="smtp-host">Serwer SMTP</label></th><td><input id="smtp-host" name="smtp[host]" class="regular-text" value="<?php echo esc_attr( $o['host'] ); ?>" placeholder="np. mail.qna24.pl"><p class="description">Puste = wysyłka domyślną metodą serwera (często nie dochodzi).</p></td></tr>
			<tr><th><label for="smtp-port">Port</label></th><td><input id="smtp-port" name="smtp[port]" type="number" style="width:100px" value="<?php echo esc_attr( $o['port'] ); ?>"></td></tr>
			<tr><th><label for="smtp-secure">Szyfrowanie</label></th><td><select id="smtp-secure" name="smtp[secure]">
				<?php foreach ( array( 'tls' => 'TLS (zwykle port 587)', 'ssl' => 'SSL (zwykle port 465)', 'none' => 'Brak' ) as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['secure'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
			<tr><th><label for="smtp-user">Login</label></th><td><input id="smtp-user" name="smtp[user]" class="regular-text" value="<?php echo esc_attr( $o['user'] ); ?>" autocomplete="off" placeholder="np. biuro@qna24.pl"></td></tr>
			<tr><th><label for="smtp-pass">Hasło</label></th><td><input id="smtp-pass" name="smtp[pass]" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $o['pass'] ? 'zapisane, wpisz nowe, aby zmienić' : ''; ?>"></td></tr>
			<tr><th><label for="smtp-from">Adres nadawcy</label></th><td><input id="smtp-from" name="smtp[from]" type="email" class="regular-text" value="<?php echo esc_attr( $o['from'] ); ?>" placeholder="zwykle taki sam jak login"></td></tr>
			<tr><th><label for="smtp-fromname">Nazwa nadawcy</label></th><td><input id="smtp-fromname" name="smtp[from_name]" class="regular-text" value="<?php echo esc_attr( $o['from_name'] ); ?>"></td></tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary">Zapisz</button>
			<button type="submit" name="qna_test" value="1" class="button">Zapisz i wyślij e-mail testowy</button>
		</p>
	</form></div>
	<?php
}
