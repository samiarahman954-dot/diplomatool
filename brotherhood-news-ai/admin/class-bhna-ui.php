<?php
/**
 * Shared admin UI components: page frame with navigation, cards, fields, toggles, badges.
 * Styles live in admin/assets/admin.css and are only loaded on the plugin's own screens.
 */

defined( 'ABSPATH' ) || exit;

class BHNA_UI {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return false !== strpos( $screen->id, 'bhna' ) || ( 'post' === $screen->base && 'post' === $screen->post_type );
	}

	public static function enqueue() {
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		wp_enqueue_style( 'bhna-admin', BHNA_URL . 'admin/assets/admin.css', array(), BHNA_VERSION );
		wp_enqueue_script( 'bhna-admin', BHNA_URL . 'admin/assets/admin.js', array(), BHNA_VERSION, true );
	}

	public static function nav_items() {
		return array(
			'bhna'          => array( 'Panel', 'dashicons-chart-area', admin_url( 'admin.php?page=bhna' ) ),
			'bhna-feeds'    => array( 'Fuentes', 'dashicons-rss', admin_url( 'admin.php?page=bhna-feeds' ) ),
			'bhna-settings' => array( 'Ajustes IA', 'dashicons-admin-generic', admin_url( 'admin.php?page=bhna-settings' ) ),
			'bhna-products' => array( 'Productos', 'dashicons-cart', admin_url( 'edit.php?post_type=' . BHNA_Amazon::CPT ) ),
			'bhna-amazon'   => array( 'Amazon', 'dashicons-amazon', admin_url( 'admin.php?page=bhna-amazon' ) ),
			'bhna-social'   => array( 'Redes', 'dashicons-share', admin_url( 'admin.php?page=bhna-social' ) ),
			'bhna-log'      => array( 'Registro', 'dashicons-list-view', admin_url( 'admin.php?page=bhna-log' ) ),
		);
	}

	public static function page_open( $active, $title, $description = '', $actions = '' ) {
		echo '<div class="wrap bhna-wrap">';
		echo '<div class="bhna-header"><div class="bhna-brand"><div class="bhna-logo">BH</div><div><h1>Brotherhood News AI</h1><small>v' . esc_html( BHNA_VERSION ) . '</small></div></div><nav class="bhna-nav">';
		foreach ( self::nav_items() as $slug => $item ) {
			printf(
				'<a href="%s" class="%s"><span class="dashicons %s"></span>%s</a>',
				esc_url( $item[2] ),
				$slug === $active ? 'is-active' : '',
				esc_attr( $item[1] ),
				esc_html( bhna__( $item[0] ) )
			);
		}
		echo '</nav>' . BHNA_I18N::switcher() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<hr class="wp-header-end" style="display:none;">';
		echo '<div class="bhna-page-title"><div><h2>' . esc_html( bhna__( $title ) ) . '</h2>';
		if ( $description ) {
			echo '<p>' . wp_kses_post( bhna__( $description ) ) . '</p>';
		}
		echo '</div>';
		if ( $actions ) {
			echo '<div class="bhna-actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped helpers.
		}
		echo '</div>';
	}

	public static function page_close() {
		echo '</div>';
	}

	public static function card_open( $title, $icon = '', $description = '', $head_extra = '', $class = '' ) {
		echo '<div class="bhna-card ' . esc_attr( $class ) . '"><div class="bhna-card-head"><div><h3>';
		if ( $icon ) {
			echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
		}
		echo esc_html( bhna__( $title ) ) . '</h3>';
		if ( $description ) {
			echo '<p>' . wp_kses_post( bhna__( $description ) ) . '</p>';
		}
		echo '</div>' . $head_extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function card_close() {
		echo '</div>';
	}

	/** One labelled form row. $control is trusted HTML built with the helpers below. */
	public static function field( $label, $control, $description = '', $for = '' ) {
		echo '<div class="bhna-field">';
		if ( $for ) {
			echo '<label for="' . esc_attr( $for ) . '">' . esc_html( bhna__( $label ) ) . '</label>';
		} else {
			echo '<div class="bhna-field-label">' . esc_html( bhna__( $label ) ) . '</div>';
		}
		echo '<div>' . $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $description ) {
			echo '<p class="description">' . wp_kses_post( bhna__( $description ) ) . '</p>';
		}
		echo '</div></div>';
	}

	public static function toggle( $name, $checked, $label = '', $class = '' ) {
		return sprintf(
			'<label class="bhna-switch"><input type="checkbox" name="%s" value="1" class="%s" %s><span class="bhna-slider"></span>%s</label>',
			esc_attr( $name ),
			esc_attr( $class ),
			checked( (bool) $checked, true, false ),
			$label ? '<span>' . esc_html( bhna__( $label ) ) . '</span>' : ''
		);
	}

	public static function text( $name, $value, $placeholder = '', $type = 'text', $id = '' ) {
		return sprintf(
			'<input type="%s" name="%s" id="%s" value="%s" placeholder="%s" class="regular-text">',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $id ? $id : 'bhna_' . $name ),
			esc_attr( $value ),
			esc_attr( bhna__( $placeholder ) )
		);
	}

	public static function number( $name, $value, $min, $max ) {
		return sprintf( '<input type="number" name="%s" id="bhna_%s" value="%s" min="%d" max="%d">', esc_attr( $name ), esc_attr( $name ), esc_attr( $value ), (int) $min, (int) $max );
	}

	public static function textarea( $name, $value, $rows = 4, $class = 'large-text', $placeholder = '' ) {
		return sprintf(
			'<textarea name="%s" id="bhna_%s" rows="%d" class="%s" placeholder="%s">%s</textarea>',
			esc_attr( $name ),
			esc_attr( $name ),
			(int) $rows,
			esc_attr( $class ),
			esc_attr( bhna__( $placeholder ) ),
			esc_textarea( $value )
		);
	}

	public static function select( $name, array $options, $selected, $id = '' ) {
		$html = sprintf( '<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $id ? $id : 'bhna_' . $name ) );
		foreach ( $options as $value => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( (string) $selected, (string) $value, false ), esc_html( bhna__( $label ) ) );
		}
		return $html . '</select>';
	}

	/** Password field that keeps the saved value unless a new one is typed. */
	public static function secret( $name, $saved, $placeholder ) {
		return sprintf(
			'<div class="bhna-secret"><input type="password" name="%s" id="bhna_%s" value="" autocomplete="new-password" placeholder="%s"><button type="button" class="button" data-bhna-reveal data-show="%s" data-hide="%s">%s</button></div>%s',
			esc_attr( $name ),
			esc_attr( $name ),
			esc_attr( $saved ? sprintf( bhna__( 'Guardado (••••%s) — escribe para cambiarlo' ), substr( (string) $saved, -4 ) ) : bhna__( $placeholder ) ),
			esc_attr( bhna__( 'Mostrar' ) ),
			esc_attr( bhna__( 'Ocultar' ) ),
			esc_html( bhna__( 'Mostrar' ) ),
			$saved ? ' ' . self::badge( 'Configurado', 'success' ) : ''
		);
	}

	public static function badge( $text, $type = '' ) {
		return '<span class="bhna-badge ' . ( $type ? 'is-' . esc_attr( $type ) : '' ) . '">' . esc_html( bhna__( $text ) ) . '</span>';
	}

	public static function kpi( $label, $value, $icon, $type = '' ) {
		printf(
			'<div class="bhna-kpi"><div class="bhna-kpi-icon %s"><span class="dashicons %s"></span></div><div><div class="bhna-kpi-label">%s</div><div class="bhna-kpi-value">%s</div></div></div>',
			$type ? 'is-' . esc_attr( $type ) : '',
			esc_attr( $icon ),
			esc_html( bhna__( $label ) ),
			esc_html( bhna__( (string) $value ) )
		);
	}

	/** Opens a POST form to admin-post.php with nonce and action. */
	public static function form_open( $action, $attrs = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_nonce_field( 'bhna_' . $action );
		echo '<input type="hidden" name="action" value="bhna_' . esc_attr( $action ) . '">';
		// Pressing Enter in a field must "save", not trigger the first secondary button (e.g. "Guardar y probar").
		echo '<button type="submit" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></button>';
	}

	/** Small standalone form with a single button. */
	public static function action_button( $action, $label, $class = 'button', $confirm = '', $hidden = array() ) {
		ob_start();
		self::form_open( $action );
		foreach ( $hidden as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		printf(
			'<button type="submit" class="%s"%s>%s</button></form>',
			esc_attr( $class ),
			$confirm ? ' data-bhna-confirm="' . esc_attr( bhna__( $confirm ) ) . '"' : '',
			esc_html( bhna__( $label ) )
		);
		return ob_get_clean();
	}

	public static function tabs( array $tabs ) {
		echo '<div class="bhna-tabs" role="tablist">';
		foreach ( $tabs as $key => $label ) {
			printf( '<button type="button" role="tab" data-tab="%s">%s</button>', esc_attr( $key ), esc_html( bhna__( $label ) ) );
		}
		echo '</div>';
	}

	public static function panel_open( $key ) {
		echo '<div class="bhna-tab-panel" data-panel="' . esc_attr( $key ) . '">';
	}

	public static function panel_close() {
		echo '</div>';
	}

	public static function savebar( $label ) {
		echo '<div class="bhna-savebar"><button type="submit" class="button button-primary button-large">' . esc_html( bhna__( $label ) ) . '</button></div>';
	}
}
