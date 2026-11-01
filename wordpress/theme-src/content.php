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

function qna_member_photo_inner( $k ) {
	$url = qna_img( $k . '.zdjecie' );
	if ( $url ) {
		printf( '<img src="%s" alt="%s">', esc_url( $url ), esc_attr( qna_get( $k . '.imie' ) ) );
	} else {
		printf( '<span class="initials">%s</span><small>zdjęcie wkrótce</small>', esc_html( qna_initials( qna_get( $k . '.imie' ) ) ) );
	}
}

function qna_member_photo( $k ) {
	$url = qna_img( $k . '.zdjecie' );
	echo '<span class="member-photo' . ( $url ? '' : ' empty' ) . '">';
	if ( $url ) {
		printf( '<img loading="lazy" decoding="async" src="%s" alt="%s">', esc_url( $url ), esc_attr( qna_get( $k . '.imie' ) ) );
	} else {
		qna_member_photo_inner( $k );
	}
	echo '<span class="plus" aria-hidden="true"></span></span>';
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
	foreach ( $data['fields'] as $f ) {
		$by_sec[ $f['section'] ][] = $f;
	}
	$msg = isset( $_GET['qna'] ) ? sanitize_key( $_GET['qna'] ) : ''; // phpcs:ignore
	?>
	<div class="wrap qna-admin">
		<h1>Treść strony</h1>
		<p>Tu zmienisz teksty, dane kontaktowe i zdjęcia na stronie. Rozwiń sekcję, popraw pola i kliknij <strong>Zapisz zmiany</strong>. Zmiany od razu są widoczne na stronie.</p>
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
