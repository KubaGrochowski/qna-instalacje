<?php
/**
 * Plugin Name: QNA Opinie Google
 * Description: Opinie z wizytówki firmy w Mapach Google na stronie: średnia ocena, liczba opinii i najnowsze opinie. Odświeżają się automatycznie (raz na 12 godzin) przez oficjalne Google Places API. Wymaga klucza API Google i motywu „QNA Instalacje”.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-opinie
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QNA_OP_OPTION = 'qna_opinie';      // ustawienia
const QNA_OP_DATA   = 'qna_opinie_data'; // ostatnie poprawnie pobrane dane
const QNA_OP_CACHE  = 'qna_opinie_fresh';

function qna_op_settings() {
	$o = get_option( QNA_OP_OPTION, array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), array(
		'key'      => '',
		'place_id' => '',
		'query'    => 'Qna Instalacje, Na Skarpę 3, 86-260 Unisław',
		'link'     => '',
		'min'      => '4',
		'show'     => '1',
		'title'    => 'Co mówią klienci',
	) );
}

/** Szuka Place ID po nazwie i adresie (Places API, Text Search). */
function qna_op_find_place( $key, $query ) {
	$r = wp_remote_post( 'https://places.googleapis.com/v1/places:searchText', array(
		'timeout' => 15,
		'headers' => array( 'Content-Type' => 'application/json', 'X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress' ),
		'body'    => wp_json_encode( array( 'textQuery' => $query, 'languageCode' => 'pl' ) ),
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $r ) || empty( $d['places'][0]['id'] ) ) {
		return new WP_Error( 'qna_op', $d['error']['message'] ?? 'Nie znaleziono firmy w Google. Wpisz Place ID ręcznie.' );
	}
	return $d['places'][0]['id'];
}

/** Pobiera dane miejsca (ocena, liczba opinii, opinie). */
function qna_op_fetch() {
	$o = qna_op_settings();
	if ( '' === $o['key'] ) {
		return new WP_Error( 'qna_op', 'Brak klucza API.' );
	}
	$place = $o['place_id'];
	if ( '' === $place ) {
		$place = qna_op_find_place( $o['key'], $o['query'] );
		if ( is_wp_error( $place ) ) {
			return $place;
		}
		$o['place_id'] = $place;
		update_option( QNA_OP_OPTION, $o, false );
	}
	$r = wp_remote_get( 'https://places.googleapis.com/v1/places/' . rawurlencode( $place ) . '?languageCode=pl', array(
		'timeout' => 15,
		'headers' => array( 'X-Goog-Api-Key' => $o['key'], 'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri,reviews' ),
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'qna_op', $d['error']['message'] ?? 'Błąd Google API (' . wp_remote_retrieve_response_code( $r ) . ').' );
	}
	$reviews = array();
	foreach ( (array) ( $d['reviews'] ?? array() ) as $rv ) {
		$reviews[] = array(
			'autor'  => $rv['authorAttribution']['displayName'] ?? '',
			'url'    => $rv['authorAttribution']['uri'] ?? '',
			'foto'   => $rv['authorAttribution']['photoUri'] ?? '',
			'ocena'  => (int) ( $rv['rating'] ?? 0 ),
			'tekst'  => $rv['text']['text'] ?? ( $rv['originalText']['text'] ?? '' ),
			'kiedy'  => $rv['relativePublishTimeDescription'] ?? '',
		);
	}
	return array(
		'ocena'   => (float) ( $d['rating'] ?? 0 ),
		'liczba'  => (int) ( $d['userRatingCount'] ?? 0 ),
		'link'    => $d['googleMapsUri'] ?? '',
		'opinie'  => $reviews,
		'czas'    => time(),
	);
}

/** Dane do wyświetlenia: świeże z pamięci podręcznej albo pobrane ponownie (raz na 12 h). */
function qna_op_data( $force = false ) {
	if ( ! $force && get_transient( QNA_OP_CACHE ) ) {
		return get_option( QNA_OP_DATA );
	}
	$res = qna_op_fetch();
	if ( is_wp_error( $res ) ) {
		update_option( 'qna_opinie_error', $res->get_error_message() . ' (' . current_time( 'mysql' ) . ')', false );
		set_transient( QNA_OP_CACHE, 1, HOUR_IN_SECONDS ); // kolejna próba za godzinę
		return get_option( QNA_OP_DATA );                 // ostatnie dobre dane (jeśli są)
	}
	delete_option( 'qna_opinie_error' );
	update_option( QNA_OP_DATA, $res, false );
	set_transient( QNA_OP_CACHE, 1, 12 * HOUR_IN_SECONDS );
	return $res;
}

function qna_op_stars( $n ) {
	$n = max( 0, min( 5, (float) $n ) );
	$out = '<span class="op-stars" aria-label="' . esc_attr( number_format_i18n( $n, 1 ) ) . ' na 5">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= '<span class="' . ( $i <= round( $n ) ? 'on' : '' ) . '">★</span>';
	}
	return $out . '</span>';
}

/** Sekcja na stronie (wywoływana przez motyw). Bez danych nic nie wyświetla. */
function qna_opinie_section() {
	$o = qna_op_settings();
	if ( '1' !== $o['show'] ) {
		return;
	}
	$d = qna_op_data();
	if ( ! is_array( $d ) || empty( $d['liczba'] ) ) {
		return;
	}
	$link    = $o['link'] ? $o['link'] : $d['link'];
	$reviews = array_values( array_filter( $d['opinie'], function ( $r ) use ( $o ) {
		return $r['ocena'] >= (int) $o['min'] && '' !== trim( $r['tekst'] );
	} ) );
	?>
  <section class="section opinie" id="opinie">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Opinie</span>
        <h2><?php echo esc_html( $o['title'] ); ?></h2>
      </div>
      <div class="op-summary">
        <b class="op-score"><?php echo esc_html( number_format_i18n( $d['ocena'], 1 ) ); ?></b>
        <div><?php echo qna_op_stars( $d['ocena'] ); // phpcs:ignore ?><span class="op-count">na podstawie <?php echo (int) $d['liczba']; ?> opinii w Google</span></div>
        <?php if ( $link ) : ?><a class="btn btn-dark" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">Zobacz wszystkie opinie</a><?php endif; ?>
      </div>
      <?php if ( $reviews ) : ?>
      <div class="op-grid">
        <?php foreach ( array_slice( $reviews, 0, 5 ) as $r ) :
			$txt = wp_trim_words( $r['tekst'], 55, '…' ); ?>
        <figure class="op-card">
          <?php echo qna_op_stars( $r['ocena'] ); // phpcs:ignore ?>
          <blockquote><?php echo esc_html( $txt ); ?></blockquote>
          <figcaption>
            <?php if ( $r['foto'] ) : ?><img src="<?php echo esc_url( $r['foto'] ); ?>" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
            <span><?php echo $r['url'] ? '<a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['autor'] ) . '</a>' : esc_html( $r['autor'] ); ?><small><?php echo esc_html( $r['kiedy'] ); ?> · Google</small></span>
          </figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p class="op-source">Opinie pochodzą z Map Google i odświeżają się automatycznie.</p>
    </div>
  </section>
	<?php
}

/* ---------------- Panel ---------------- */

add_action( 'admin_menu', function () {
	add_menu_page( 'Opinie Google', 'Opinie Google', 'manage_options', 'qna-opinie', 'qna_op_page', 'dashicons-star-filled', 3.7 );
} );

add_action( 'admin_post_qna_op_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'qna_op_save' );
	$in  = isset( $_POST['op'] ) && is_array( $_POST['op'] ) ? wp_unslash( $_POST['op'] ) : array(); // phpcs:ignore
	$old = qna_op_settings();
	$new = array(
		'key'      => '' !== trim( $in['key'] ?? '' ) ? sanitize_text_field( $in['key'] ) : $old['key'],
		'place_id' => sanitize_text_field( $in['place_id'] ?? '' ),
		'query'    => sanitize_text_field( $in['query'] ?? '' ),
		'link'     => esc_url_raw( $in['link'] ?? '' ),
		'min'      => (string) min( 5, max( 1, absint( $in['min'] ?? 4 ) ) ),
		'show'     => empty( $in['show'] ) ? '0' : '1',
		'title'    => sanitize_text_field( $in['title'] ?? 'Co mówią klienci' ),
	);
	if ( ! empty( $_POST['op_clear_key'] ) ) {
		$new['key'] = '';
	}
	update_option( QNA_OP_OPTION, $new, false );
	delete_transient( QNA_OP_CACHE );
	if ( '' !== $new['key'] ) {
		qna_op_data( true );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=qna-opinie&saved=1' ) );
	exit;
} );

function qna_op_page() {
	$o   = qna_op_settings();
	$d   = get_option( QNA_OP_DATA );
	$err = get_option( 'qna_opinie_error' );
	echo '<div class="wrap"><h1>Opinie Google</h1>';
	echo '<p>Opinie z wizytówki firmy w Mapach Google. Strona pobiera je sama i odświeża raz na 12 godzin. Google udostępnia przez API średnią ocenę, liczbę opinii i do 5 opinii.</p>';
	if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Zapisano.</p></div>';
	}
	if ( $err ) {
		echo '<div class="notice notice-error"><p>Ostatnie pobieranie nie powiodło się: ' . esc_html( $err ) . '</p></div>';
	}
	if ( is_array( $d ) ) {
		printf( '<div class="notice notice-info"><p>Ostatnio pobrane: <strong>%s ★</strong>, %d opinii, %d opinii z tekstem do pokazania. (%s)</p></div>',
			esc_html( number_format_i18n( $d['ocena'], 1 ) ), (int) $d['liczba'], count( $d['opinie'] ), esc_html( wp_date( 'j.m.Y H:i', $d['czas'] ) ) );
	} elseif ( '' === $o['key'] ) {
		echo '<div class="notice notice-warning"><p>Sekcja opinii nie pokazuje się, dopóki nie wpiszesz klucza API Google.</p></div>';
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="qna_op_save">
		<?php wp_nonce_field( 'qna_op_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="op-key">Klucz API Google</label></th><td><input id="op-key" name="op[key]" type="password" class="regular-text" autocomplete="off" placeholder="<?php echo $o['key'] ? 'zapisany, wpisz nowy, aby zmienić' : 'AIza…'; ?>">
				<?php if ( $o['key'] ) : ?><label style="margin-left:8px"><input type="checkbox" name="op_clear_key" value="1"> usuń klucz</label><?php endif; ?>
				<p class="description">Klucz z Google Cloud z włączonym „Places API (New)”. Najlepiej ograniczyć go do tego API.</p></td></tr>
			<tr><th><label for="op-query">Firma w Google</label></th><td><input id="op-query" name="op[query]" class="regular-text" value="<?php echo esc_attr( $o['query'] ); ?>"><p class="description">Nazwa i adres, po których strona sama znajdzie wizytówkę.</p></td></tr>
			<tr><th><label for="op-place">Place ID</label></th><td><input id="op-place" name="op[place_id]" class="regular-text" value="<?php echo esc_attr( $o['place_id'] ); ?>" placeholder="uzupełni się samo"><p class="description">Zostaw puste: zostanie znalezione po nazwie i adresie.</p></td></tr>
			<tr><th><label for="op-link">Link „Zobacz wszystkie opinie”</label></th><td><input id="op-link" name="op[link]" type="url" class="regular-text" value="<?php echo esc_attr( $o['link'] ); ?>" placeholder="puste = link z Google"></td></tr>
			<tr><th><label for="op-min">Pokazuj opinie od</label></th><td><select id="op-min" name="op[min]"><?php for ( $i = 1; $i <= 5; $i++ ) : ?><option value="<?php echo (int) $i; ?>" <?php selected( $o['min'], (string) $i ); ?>><?php echo (int) $i; ?> ★</option><?php endfor; ?></select></td></tr>
			<tr><th><label for="op-title">Tytuł sekcji</label></th><td><input id="op-title" name="op[title]" class="regular-text" value="<?php echo esc_attr( $o['title'] ); ?>"></td></tr>
			<tr><th>Sekcja na stronie</th><td><label><input type="checkbox" name="op[show]" value="1" <?php checked( $o['show'], '1' ); ?>> Pokazuj sekcję opinii na stronie głównej</label></td></tr>
		</table>
		<?php submit_button( 'Zapisz i pobierz opinie' ); ?>
	</form></div>
	<?php
}
