<?php
/**
 * Edytowalna treść strony: odczyt pól i strona „Treść strony” w panelu.
 * Lista pól i wartości domyślne: fields.json (generowany przez wordpress/build-theme.py).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const QNA_OPTION = 'qna_content';

function qna_fields() {
	static $data = null;
	if ( null === $data ) {
		$data = json_decode( file_get_contents( get_template_directory() . '/fields.json' ), true );
	}
	return $data;
}

function qna_field( $key ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( qna_fields()['fields'] as $f ) {
			$map[ $f['key'] ] = $f;
		}
	}
	return isset( $map[ $key ] ) ? $map[ $key ] : null;
}

/** Wartość pola: zapisana w panelu albo domyślna. */
function qna_get( $key ) {
	static $saved = null;
	if ( null === $saved ) {
		$saved = get_option( QNA_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
	}
	if ( array_key_exists( $key, $saved ) ) {
		return (string) $saved[ $key ];
	}
	$f = qna_field( $key );
	return $f ? (string) $f['default'] : '';
}

function qna_allowed_html() {
	return array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'br'     => array(),
		'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
	);
}

function qna_kses( $html ) {
	return wp_kses( $html, qna_allowed_html() );
}

function qna_e( $key ) {
	echo esc_html( qna_get( $key ) );
}

function qna_a( $key ) {
	echo esc_attr( qna_get( $key ) );
}

function qna_h( $key ) {
	echo qna_kses( qna_get( $key ) ); // phpcs:ignore
}

function qna_p( $key ) {
	echo nl2br( esc_html( qna_get( $key ) ) ); // phpcs:ignore
}

/** Wartości pól prefix1..prefixN bez pustych. */
function qna_list( $prefix, $n ) {
	$out = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$v = trim( qna_get( $prefix . $i ) );
		if ( '' !== $v ) {
			$out[] = $v;
		}
	}
	return $out;
}

function qna_lines( $key ) {
	$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', qna_get( $key ) ) ) );
	echo implode( '<br>', array_map( 'esc_html', $lines ) ); // phpcs:ignore
}

function qna_tel() {
	$digits = preg_replace( '/[^\d+]/', '', qna_get( 'kontakt.telefon' ) );
	if ( '' !== $digits && '+' !== $digits[0] ) {
		$digits = '+48' . ltrim( $digits, '0' );
	}
	return 'tel:' . $digits;
}

/** Telefon z pola: link do dzwonienia albo (gdy numer jest do uzupełnienia, np. z „X”) sam tekst. */
function qna_tel_html( $key ) {
	$v = trim( qna_get( $key ) );
	if ( '' === $v ) {
		return;
	}
	$digits = preg_replace( '/[^\d+]/', '', $v );
	if ( preg_match( '/x/i', $v ) || strlen( preg_replace( '/\D/', '', $digits ) ) < 9 ) {
		printf( '<span class="tel-todo">%s</span>', esc_html( $v ) );
		return;
	}
	if ( '+' !== $digits[0] ) {
		$digits = '+48' . ltrim( $digits, '0' );
	}
	printf( '<a class="tel-link" href="tel:%s">%s</a>', esc_attr( $digits ), esc_html( $v ) );
}

function qna_maps_url() {
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( qna_get( 'kontakt.adres1' ) . ', ' . qna_get( 'kontakt.adres2' ) );
}

/** Adres zdjęcia: z biblioteki mediów (ID) albo plik z motywu. */
function qna_img( $key ) {
	$v = qna_get( $key );
	if ( ctype_digit( $v ) && (int) $v > 0 ) {
		$url = wp_get_attachment_image_url( (int) $v, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	if ( '' === $v || ctype_digit( $v ) ) {
		return '';
	}
	return QNA_IMG . $v;
}

function qna_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$ini   = '';
	foreach ( array_slice( $parts, 0, 2 ) as $p ) {
		$ini .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return $ini;
}

/**
 * Osoby z zespołu: z wtyczki „QNA Zespół” (typ wpisu qna_osoba) albo domyślne z motywu.
 * Każda osoba: id, imie, rola, opis, info [[etykieta, tekst]], zdjecie (URL lub '').
 */
function qna_team_people() {
	$people = array();
	if ( post_type_exists( 'qna_osoba' ) ) {
		$posts = get_posts( array( 'post_type' => 'qna_osoba', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
		foreach ( $posts as $p ) {
			$info = array();
			foreach ( array( 'doswiadczenie' => 'Doświadczenie', 'umiejetnosci' => 'Umiejętności', 'zadania' => 'W firmie' ) as $meta => $label ) {
				$v = trim( (string) get_post_meta( $p->ID, '_qna_' . $meta, true ) );
				if ( '' !== $v ) {
					$info[] = array( $label, $v );
				}
			}
			$people[] = array(
				'id'      => 'p-' . $p->ID,
				'imie'    => get_the_title( $p ),
				'rola'    => (string) get_post_meta( $p->ID, '_qna_rola', true ),
				'opis'    => (string) get_post_meta( $p->ID, '_qna_opis', true ),
				'info'    => $info,
				'zdjecie' => (string) get_the_post_thumbnail_url( $p, 'large' ),
			);
		}
		return $people;
	}
	foreach ( (array) qna_fields()['team'] as $i => $m ) {
		$people[] = array(
			'id'      => 'p-domyslna-' . $i,
			'imie'    => $m['imie'],
			'rola'    => $m['rola'],
			'opis'    => $m['opis'],
			'info'    => $m['info'],
			'zdjecie' => $m['zdjecie'] ? QNA_IMG . $m['zdjecie'] : '',
		);
	}
	return $people;
}

function qna_team() {
	$tpls = '';
	foreach ( qna_team_people() as $m ) {
		$photo = $m['zdjecie']
			? sprintf( '<img loading="lazy" decoding="async" src="%s" alt="%s">', esc_url( $m['zdjecie'] ), esc_attr( $m['imie'] ) )
			: sprintf( '<span class="initials">%s</span><small>zdjęcie wkrótce</small>', esc_html( qna_initials( $m['imie'] ) ) );
		printf(
			"\n        <button class=\"member\" type=\"button\" data-person=\"%s\" aria-haspopup=\"dialog\">\n          <span class=\"member-photo%s\">%s<span class=\"plus\" aria-hidden=\"true\"></span></span>\n          <span class=\"member-name\">%s</span>\n          <span class=\"member-role\">%s</span>\n          <span class=\"member-hint\">Kliknij zdjęcie, aby dowiedzieć się więcej <svg class=\"arr\" aria-hidden=\"true\"><use href=\"#i-arrow\"/></svg></span>\n        </button>",
			esc_attr( $m['id'] ),
			$m['zdjecie'] ? '' : ' empty',
			$photo, // phpcs:ignore
			esc_html( $m['imie'] ),
			esc_html( $m['rola'] )
		);
		$info = '';
		foreach ( $m['info'] as $row ) {
			$info .= sprintf( '<div data-spec="%s">%s</div>', esc_attr( $row[0] ), esc_html( $row[1] ) );
		}
		$tpls .= sprintf(
			"\n        <template id=\"%s\"><div data-role>%s</div><div data-name>%s</div><div data-photo>%s</div><div data-desc>%s</div>%s</template>",
			esc_attr( $m['id'] ),
			esc_html( $m['rola'] ),
			esc_html( $m['imie'] ),
			$m['zdjecie'] ? sprintf( '<img src="%s" alt="%s">', esc_url( $m['zdjecie'] ), esc_attr( $m['imie'] ) ) : sprintf( '<span class="initials">%s</span><small>zdjęcie wkrótce</small>', esc_html( qna_initials( $m['imie'] ) ) ),
			esc_html( $m['opis'] ),
			$info // phpcs:ignore
		);
	}
	echo $tpls; // phpcs:ignore
}

/** Rodzaje realizacji: [klucz => nazwa] (nazwy usług z „Treść strony”). */
function qna_real_types() {
	$out = array();
	foreach ( (array) ( qna_fields()['typy'] ?? array() ) as $t ) {
		$out[ $t[0] ] = qna_get( $t[1] );
	}
	return $out;
}

/** Realizacje: z wtyczki „QNA Realizacje” albo domyślne z motywu. */
function qna_real_items() {
	$items = array();
	if ( post_type_exists( 'qna_realizacja' ) ) {
		$posts = get_posts( array( 'post_type' => 'qna_realizacja', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
		foreach ( $posts as $p ) {
			$url = get_the_post_thumbnail_url( $p, 'large' );
			if ( ! $url ) {
				continue;
			}
			$items[] = array(
				'typ'  => (string) get_post_meta( $p->ID, '_qna_typ', true ),
				'url'  => $url,
				'alt'  => get_the_title( $p ),
				'opis' => (string) get_post_meta( $p->ID, '_qna_opis', true ),
				'miejsce'   => (string) get_post_meta( $p->ID, '_qna_miejsce', true ),
				'parametry' => (string) get_post_meta( $p->ID, '_qna_parametry', true ),
				'rozmiar'   => (string) get_post_meta( $p->ID, '_qna_rozmiar', true ),
			);
		}
		return $items;
	}
	foreach ( (array) ( qna_fields()['realizacje'] ?? array() ) as $r ) {
		$items[] = array( 'typ' => $r['typ'], 'url' => QNA_IMG . $r['zdjecie'], 'alt' => $r['alt'], 'opis' => $r['opis'], 'miejsce' => $r['miejsce'] ?? '', 'parametry' => $r['parametry'] ?? '', 'rozmiar' => $r['rozmiar'] ?? 'auto' );
	}
	return $items;
}

function qna_realizacje() {
	$types = qna_real_types();
	$sizes = array( 'auto', 'normal', 'big', 'wide', 'tall' );
	foreach ( qna_real_items() as $it ) {
		$label  = isset( $types[ $it['typ'] ] ) ? $types[ $it['typ'] ] : '';
		$size   = in_array( $it['rozmiar'] ?? '', $sizes, true ) ? $it['rozmiar'] : 'auto';
		$over   = '<span class="real-type">' . esc_html( $label ) . '</span>';
		if ( '' !== trim( $it['miejsce'] ?? '' ) ) {
			$over .= '<span class="real-place">' . esc_html( $it['miejsce'] ) . '</span>';
		}
		$specs = array_filter( array_map( 'trim', explode( ',', $it['parametry'] ?? '' ) ) );
		if ( $specs ) {
			$over .= '<span class="real-specs"><i>' . implode( '</i><i>', array_map( 'esc_html', $specs ) ) . '</i></span>';
		}
		if ( '' !== trim( $it['opis'] ) ) {
			$over .= '<span class="real-desc">' . esc_html( $it['opis'] ) . '</span>';
		}
		printf(
			"\n          <figure class=\"real-item\" data-type=\"%s\" data-size=\"%s\"><button class=\"real-open\" type=\"button\" aria-label=\"Powiększ: %s\"><img loading=\"lazy\" decoding=\"async\" src=\"%s\" alt=\"%s\"><span class=\"real-over\">%s</span></button></figure>",
			esc_attr( $it['typ'] ),
			esc_attr( $size ),
			esc_attr( $it['alt'] ),
			esc_url( $it['url'] ),
			esc_attr( $it['alt'] ),
			$over // phpcs:ignore
		);
	}
}

function qna_ticker() {
	$items = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', qna_get( 'start.pasek' ) ) ) );
	$row   = '';
	foreach ( $items as $t ) {
		$row .= '<span>' . esc_html( $t ) . '</span>';
	}
	echo $row . "\n      " . $row; // phpcs:ignore
}

function qna_brands( $n ) {
	for ( $i = 1; $i <= $n; $i++ ) {
		$name = trim( qna_get( 'marki.marka' . $i ) );
		if ( '' === $name ) {
			continue;
		}
		printf( "\n          <li class=\"brand-cell\"><b>%s</b><small>%s</small></li>", esc_html( $name ), esc_html( qna_get( 'marki.marka' . $i . '_opis' ) ) );
	}
}

/* ---------------- Panel: Treść strony ---------------- */

add_action( 'admin_menu', function () {
	add_menu_page( 'Treść strony', 'Treść strony', 'edit_theme_options', 'qna-tresc', 'qna_render_admin', 'dashicons-edit-page', 3 );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_qna-tresc' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_style( 'common', '
		.qna-admin details{background:#fff;border:1px solid #dcdcde;border-radius:6px;margin:0 0 12px;max-width:980px}
		.qna-admin summary{cursor:pointer;padding:14px 18px;font-size:15px;font-weight:600}
		.qna-admin .qna-sec{padding:4px 18px 18px;border-top:1px solid #f0f0f1}
		.qna-admin .qna-row{display:grid;grid-template-columns:260px 1fr;gap:16px;padding:12px 0;border-bottom:1px solid #f6f7f7}
		.qna-admin .qna-row label{font-weight:600}
		.qna-admin .qna-row input[type=text],.qna-admin .qna-row input[type=email],.qna-admin .qna-row input[type=url],.qna-admin .qna-row textarea{width:100%}
		.qna-admin .qna-row textarea{min-height:76px}
		.qna-admin .qna-img{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
		.qna-admin .qna-img img{width:120px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #dcdcde;background:#f6f7f7}
		.qna-admin .qna-reset{margin-top:28px;padding:16px 18px;border:1px solid #f0c0c0;border-radius:6px;max-width:980px;background:#fff}
		@media (max-width:782px){.qna-admin .qna-row{grid-template-columns:1fr}}
	' );
	wp_add_inline_script( 'media-editor', "
		jQuery(function($){
			$('.qna-admin').on('click','.qna-pick',function(e){
				e.preventDefault();
				var box=$(this).closest('.qna-img');
				var frame=wp.media({title:'Wybierz zdjęcie',button:{text:'Użyj tego zdjęcia'},library:{type:'image'},multiple:false});
				frame.on('select',function(){
					var a=frame.state().get('selection').first().toJSON();
					box.find('input').val(a.id);
					box.find('img').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show();
				});
				frame.open();
			});
			$('.qna-admin').on('click','.qna-clear',function(e){
				e.preventDefault();
				var box=$(this).closest('.qna-img');
				box.find('input').val(box.data('default'));
				box.find('img').attr('src',box.data('default-url')||'').toggle(!!box.data('default-url'));
			});
		});
	" );
} );

add_action( 'admin_post_qna_save_content', function () {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'qna_save_content' );

	if ( ! empty( $_POST['qna_reset'] ) ) {
		if ( ! empty( $_POST['qna_reset_confirm'] ) ) {
			delete_option( QNA_OPTION );
			wp_safe_redirect( admin_url( 'admin.php?page=qna-tresc&qna=reset' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=qna-tresc&qna=noconfirm' ) );
		}
		exit;
	}

	$in    = isset( $_POST['qna'] ) && is_array( $_POST['qna'] ) ? wp_unslash( $_POST['qna'] ) : array(); // phpcs:ignore
	$saved = get_option( QNA_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	foreach ( qna_fields()['fields'] as $f ) {
		$id = str_replace( '.', '__', $f['key'] );
		if ( ! array_key_exists( $id, $in ) ) {
			continue;
		}
		$v = $in[ $id ];
		switch ( $f['type'] ) {
			case 'textarea':
				$v = sanitize_textarea_field( $v );
				break;
			case 'html':
				$v = qna_kses( $v );
				break;
			case 'email':
				$v = sanitize_email( $v );
				break;
			case 'url':
				$v = esc_url_raw( $v );
				break;
			case 'number':
				$v = preg_replace( '/[^\d]/', '', $v );
				break;
			case 'image':
				$v = sanitize_file_name( $v ); // ID z biblioteki albo nazwa pliku z motywu
				break;
			default:
				$v = sanitize_text_field( $v );
		}
		$saved[ $f['key'] ] = $v;
	}
	update_option( QNA_OPTION, $saved, true );
	wp_safe_redirect( admin_url( 'admin.php?page=qna-tresc&qna=saved' ) );
	exit;
} );

function qna_render_admin() {
	$data   = qna_fields();
	$by_sec = array();
	$photos_plugin = function_exists( 'qna_zdjecia_active' );
	foreach ( $data['fields'] as $f ) {
		if ( $photos_plugin && 'image' === $f['type'] ) {
			continue;
		}
		$by_sec[ $f['section'] ][] = $f;
	}
	$msg = isset( $_GET['qna'] ) ? sanitize_key( $_GET['qna'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap qna-admin">
		<h1>Treść strony</h1>
		<p>Teksty i dane kontaktowe na stronie.<?php echo $photos_plugin ? ' Zdjęcia: menu <strong>Zdjęcia</strong>.' : ''; ?><?php echo post_type_exists( 'qna_osoba' ) ? ' Osoby: menu <strong>Zespół</strong>.' : ''; ?></p>
		<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Zapisano zmiany.</p></div><?php endif; ?>
		<?php if ( 'reset' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Przywrócono domyślną treść strony.</p></div><?php endif; ?>
		<?php if ( 'noconfirm' === $msg ) : ?><div class="notice notice-warning is-dismissible"><p>Aby przywrócić domyślną treść, zaznacz pole potwierdzenia.</p></div><?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">Zobacz stronę</a></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="qna_save_content">
			<?php wp_nonce_field( 'qna_save_content' ); ?>
			<?php foreach ( $data['sections'] as $i => $sec ) :
				list( $sid, $stitle ) = $sec;
				if ( empty( $by_sec[ $sid ] ) ) {
					continue;
				} ?>
				<details <?php echo 0 === $i ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $stitle ); ?></summary>
					<div class="qna-sec">
						<?php foreach ( $by_sec[ $sid ] as $f ) :
							$id    = str_replace( '.', '__', $f['key'] );
							$name  = 'qna[' . $id . ']';
							$value = qna_get( $f['key'] ); ?>
							<div class="qna-row">
								<label for="qna-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
								<div>
									<?php if ( 'textarea' === $f['type'] || 'html' === $f['type'] ) : ?>
										<textarea id="qna-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
									<?php elseif ( 'image' === $f['type'] ) :
										$url = qna_img( $f['key'] );
										$def = $f['default'] ? QNA_IMG . $f['default'] : ''; ?>
										<div class="qna-img" data-default="<?php echo esc_attr( $f['default'] ); ?>" data-default-url="<?php echo esc_url( $def ); ?>">
											<img src="<?php echo esc_url( $url ); ?>" alt="" <?php echo $url ? '' : 'style="display:none"'; ?>>
											<input type="hidden" id="qna-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
											<button type="button" class="button qna-pick">Wybierz zdjęcie</button>
											<button type="button" class="button-link qna-clear"><?php echo $f['default'] ? 'Przywróć domyślne' : 'Usuń zdjęcie'; ?></button>
										</div>
									<?php else :
										$t = in_array( $f['type'], array( 'email', 'url' ), true ) ? $f['type'] : 'text'; ?>
										<input type="<?php echo esc_attr( $t ); ?>" id="qna-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo 'number' === $f['type'] ? 'inputmode="numeric" style="max-width:140px"' : ''; ?>>
									<?php endif; ?>
									<?php if ( ! empty( $f['help'] ) ) : ?><p class="description"><?php echo esc_html( $f['help'] ); ?></p><?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endforeach; ?>
			<?php submit_button( 'Zapisz zmiany' ); ?>

			<div class="qna-reset">
				<strong>Przywróć domyślną treść</strong>
				<p class="description">Usuwa wszystkie zmiany z tej strony i przywraca teksty oraz zdjęcia z motywu.</p>
				<label><input type="checkbox" name="qna_reset_confirm" value="1"> Potwierdzam, chcę przywrócić domyślną treść</label>
				<p><button type="submit" name="qna_reset" value="1" class="button">Przywróć domyślne</button></p>
			</div>
		</form>
	</div>
	<?php
}

/* ---------------- Kokpit: opis narzędzi ---------------- */
add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'qna_opis', 'Strona QNA Instalacje: narzędzia', function () {
		$items = array(
			array( 'Treść strony', 'Teksty na stronie: dane kontaktowe i firmy, opisy usług, sekcja „O nas”, marki, obszar działania, nagłówki podstron i stopka. Tu ustawia się też e-mail, na który trafiają zapytania z formularza.', admin_url( 'admin.php?page=qna-tresc' ) ),
		);
		if ( function_exists( 'qna_zdjecia_active' ) ) {
			$items[] = array( 'Zdjęcia (wtyczka QNA Zdjęcia)', 'Wszystkie zdjęcia na stronie w jednym miejscu: zdjęcie główne, „O nas”, zdjęcia usług, kolaże w Ofercie i Kontakcie oraz zdjęcia osób z zespołu. Zdjęcia osób są te same co w menu Zespół.', admin_url( 'admin.php?page=qna-zdjecia' ) );
		}
		if ( post_type_exists( 'qna_osoba' ) ) {
			$items[] = array( 'Zespół (wtyczka QNA Zespół)', 'Osoby w sekcji „Zespół”: imię i nazwisko, rola, krótki opis i zdjęcie główne. Można dodawać kolejne osoby i ustalać ich kolejność.', admin_url( 'edit.php?post_type=qna_osoba' ) );
		}
		if ( post_type_exists( 'qna_realizacja' ) ) {
			$items[] = array( 'Realizacje (wtyczka QNA Realizacje)', 'Zdjęcia w sekcji „Realizacje”: każde zdjęcie ma rodzaj usługi (do filtrowania na stronie) i opcjonalny krótki opis. Liczby realizacji przy rodzajach ustawia się w „Treść strony” → Usługi.', admin_url( 'edit.php?post_type=qna_realizacja' ) );
		}
		if ( post_type_exists( 'qna_zapytanie' ) ) {
			$items[] = array( 'Zapytania (wtyczka QNA Formularz)', 'Wszystkie zapytania z formularza na stronie, zapisane w panelu, także gdy e-mail nie dojdzie. W „Poczta (SMTP)” ustawia się skrzynkę, z której strona wysyła zapytania na e-mail firmy.', admin_url( 'edit.php?post_type=qna_zapytanie' ) );
		} else {
			$items[] = array( 'Formularz kontaktowy', 'Zapytania ze strony przychodzą na e-mail ustawiony w „Treść strony”.', '' );
		}
		if ( function_exists( 'qna_opinie_section' ) ) {
			$items[] = array( 'Opinie Google (wtyczka QNA Opinie Google)', 'Sekcja z oceną i opiniami z wizytówki firmy w Mapach Google. Odświeża się automatycznie; wymaga klucza API Google.', admin_url( 'admin.php?page=qna-opinie' ) );
		}
		echo '<ul style="margin:0">';
		foreach ( $items as $it ) {
			echo '<li style="margin:0 0 12px">';
			echo $it[2] ? '<a href="' . esc_url( $it[2] ) . '"><strong>' . esc_html( $it[0] ) . '</strong></a>' : '<strong>' . esc_html( $it[0] ) . '</strong>';
			echo '<br><span style="color:#50575e">' . esc_html( $it[1] ) . '</span></li>';
		}
		echo '</ul>';
	} );
	// widżet na górze kokpitu
	global $wp_meta_boxes;
	$w = $wp_meta_boxes['dashboard']['normal']['core']['qna_opis'] ?? null;
	if ( $w ) {
		unset( $wp_meta_boxes['dashboard']['normal']['core']['qna_opis'] );
		$wp_meta_boxes['dashboard']['normal']['high']['qna_opis'] = $w;
	}
} );
