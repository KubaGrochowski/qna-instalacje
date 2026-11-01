<?php
/**
 * Plugin Name: QNA Zdjęcia
 * Description: Wszystkie zdjęcia na stronie w jednym miejscu: zdjęcie główne, „O nas”, zdjęcia usług, kolaże w Ofercie i Kontakcie oraz zdjęcia osób z zespołu. Zdjęcia osób są te same co w menu „Zespół” i zmieniają się w obu miejscach naraz. Wymaga motywu „QNA Instalacje”.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: qna-zdjecia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Znacznik dla motywu: zdjęcia są zarządzane tą wtyczką. */
function qna_zdjecia_active() {
	return true;
}

function qna_zdjecia_ready() {
	return function_exists( 'qna_fields' ) && function_exists( 'qna_img' ) && defined( 'QNA_OPTION' );
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Zdjęcia', 'Zdjęcia', 'edit_theme_options', 'qna-zdjecia', 'qna_zdjecia_page', 'dashicons-format-gallery', 3.5 );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_qna-zdjecia' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_style( 'common', '
		.qna-ph h2{margin:28px 0 10px;font-size:16px}
		.qna-ph .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;max-width:1200px}
		.qna-ph .slot{background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;display:flex;flex-direction:column}
		.qna-ph .slot .pic{aspect-ratio:4/3;background:#f0f0f1 center/cover no-repeat;display:grid;place-items:center;color:#8c8f94;font-size:13px}
		.qna-ph .slot .body{padding:10px 12px;display:grid;gap:8px}
		.qna-ph .slot .lbl{font-weight:600;font-size:13px}
		.qna-ph .slot .acts{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
		.qna-ph .slot.changed{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
	' );
	wp_add_inline_script( 'media-editor', "
		jQuery(function($){
			function show(slot,url){ slot.find('.pic').css('background-image',url?'url('+url+')':'none').text(url?'':'brak zdjęcia'); }
			$('.qna-ph').on('click','.pick',function(e){
				e.preventDefault();
				var slot=$(this).closest('.slot');
				var frame=wp.media({title:'Wybierz zdjęcie',button:{text:'Użyj tego zdjęcia'},library:{type:'image'},multiple:false});
				frame.on('select',function(){
					var a=frame.state().get('selection').first().toJSON();
					slot.find('input').val(a.id); slot.addClass('changed');
					show(slot,(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url));
				});
				frame.open();
			});
			$('.qna-ph').on('click','.reset',function(e){
				e.preventDefault();
				var slot=$(this).closest('.slot');
				slot.find('input').val(slot.data('default')); slot.addClass('changed');
				show(slot,slot.data('default-url'));
			});
		});
	" );
} );

add_action( 'admin_post_qna_save_photos', function () {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'qna_save_photos' );
	if ( ! qna_zdjecia_ready() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=qna-zdjecia' ) );
		exit;
	}
	$in    = isset( $_POST['ph'] ) && is_array( $_POST['ph'] ) ? wp_unslash( $_POST['ph'] ) : array(); // phpcs:ignore
	$saved = get_option( QNA_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	foreach ( qna_fields()['fields'] as $f ) {
		$id = str_replace( '.', '__', $f['key'] );
		if ( 'image' === $f['type'] && isset( $in[ $id ] ) ) {
			$saved[ $f['key'] ] = sanitize_file_name( $in[ $id ] );
		}
	}
	update_option( QNA_OPTION, $saved, true );

	// Zdjęcia osób = zdjęcie główne wpisu w „Zespół”.
	$people = isset( $_POST['person'] ) && is_array( $_POST['person'] ) ? wp_unslash( $_POST['person'] ) : array(); // phpcs:ignore
	foreach ( $people as $pid => $att ) {
		$pid = absint( $pid );
		if ( ! $pid || 'qna_osoba' !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
			continue;
		}
		$att = absint( $att );
		if ( $att ) {
			set_post_thumbnail( $pid, $att );
		} else {
			delete_post_thumbnail( $pid );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=qna-zdjecia&saved=1' ) );
	exit;
} );

function qna_zdjecia_slot( $name, $value, $label, $url, $default, $default_url, $reset_label ) {
	?>
	<div class="slot" data-default="<?php echo esc_attr( $default ); ?>" data-default-url="<?php echo esc_url( $default_url ); ?>">
		<div class="pic" style="<?php echo $url ? 'background-image:url(' . esc_url( $url ) . ')' : ''; ?>"><?php echo $url ? '' : 'brak zdjęcia'; ?></div>
		<div class="body">
			<span class="lbl"><?php echo esc_html( $label ); ?></span>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<div class="acts">
				<button type="button" class="button pick">Zmień zdjęcie</button>
				<button type="button" class="button-link reset"><?php echo esc_html( $reset_label ); ?></button>
			</div>
		</div>
	</div>
	<?php
}

function qna_zdjecia_page() {
	echo '<div class="wrap qna-ph"><h1>Zdjęcia</h1>';
	if ( ! qna_zdjecia_ready() ) {
		echo '<div class="notice notice-error"><p>Ta wtyczka działa z motywem „QNA Instalacje”. Włącz go w Wygląd → Motywy.</p></div></div>';
		return;
	}
	echo '<p>Wszystkie zdjęcia na stronie. Zmiany zapisujesz przyciskiem na dole.</p>';
	if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Zapisano zdjęcia.</p></div>';
	}
	$data   = qna_fields();
	$titles = array();
	foreach ( $data['sections'] as $s ) {
		$titles[ $s[0] ] = $s[1];
	}
	$groups = array();
	foreach ( $data['fields'] as $f ) {
		if ( 'image' === $f['type'] ) {
			$groups[ $f['section'] ][] = $f;
		}
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="qna_save_photos">';
	wp_nonce_field( 'qna_save_photos' );
	foreach ( $groups as $sec => $fields ) {
		echo '<h2>' . esc_html( $titles[ $sec ] ?? $sec ) . '</h2><div class="grid">';
		foreach ( $fields as $f ) {
			$def = $f['default'] ? QNA_IMG . $f['default'] : '';
			qna_zdjecia_slot( 'ph[' . str_replace( '.', '__', $f['key'] ) . ']', qna_get( $f['key'] ), $f['label'], qna_img( $f['key'] ), $f['default'], $def, $f['default'] ? 'Przywróć domyślne' : 'Usuń' );
		}
		echo '</div>';
	}
	if ( post_type_exists( 'qna_osoba' ) ) {
		$people = get_posts( array( 'post_type' => 'qna_osoba', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
		echo '<h2>Zespół</h2>';
		if ( $people ) {
			echo '<div class="grid">';
			foreach ( $people as $p ) {
				$thumb = get_post_thumbnail_id( $p );
				qna_zdjecia_slot( 'person[' . $p->ID . ']', $thumb ? (string) $thumb : '', get_the_title( $p ) . ' (zdjęcie główne)', (string) get_the_post_thumbnail_url( $p, 'medium' ), '', '', 'Usuń' );
			}
			echo '</div>';
		}
		echo '<p class="description">Nowe osoby dodasz w menu <a href="' . esc_url( admin_url( 'edit.php?post_type=qna_osoba' ) ) . '">Zespół</a>.</p>';
	}
	submit_button( 'Zapisz zdjęcia' );
	echo '</form></div>';
}
