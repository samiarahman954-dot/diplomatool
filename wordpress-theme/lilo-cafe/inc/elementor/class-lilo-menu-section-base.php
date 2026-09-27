<?php
/**
 * Shared base for the full-menu section widgets (list, photos, options).
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

abstract class Lilo_Menu_Section_Base extends Lilo_Widget_Base {

	public function get_keywords() {
		return array( 'lilo', 'cafe', 'menu', 'food', 'price' );
	}

	/**
	 * Items repeater and any layout-specific controls.
	 */
	abstract protected function register_items_controls();

	/**
	 * Style controls for the items.
	 */
	abstract protected function register_items_style();

	/**
	 * Items markup.
	 */
	abstract protected function render_items( $s );

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Section', 'lilo-cafe' ) ) );
		$this->add_control(
			'anchor_id',
			array(
				'label'       => __( 'Section ID (anchor)', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'anchor_id' ),
				'description' => __( 'Used by the Menu Tabs widget and links like /menu/#lattes.', 'lilo-cafe' ),
			)
		);
		$this->add_control(
			'tab_label',
			array(
				'label'       => __( 'Tab label', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'tab_label' ),
				'description' => __( 'Shown by Menu Tabs set to "Build automatically".', 'lilo-cafe' ),
			)
		);
		$this->add_heading_controls( true );
		$this->add_control( 'footnote', array( 'label' => __( 'Footnote', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => $this->d( 'footnote' ) ) );
		$this->end_controls_section();

		$this->register_items_controls();

		$this->add_box_style_section( '.lilo-msec', '.lilo-sec__inner' );
		$this->start_controls_section( 'section_style_items', array( 'label' => __( 'Items', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'divider_color', array( 'label' => __( 'Section divider color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-msec__box' => 'border-color: {{VALUE}};' ) ) );
		$this->register_items_style();
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'noteh'  => array( __( 'Note', 'lilo-cafe' ), '.lilo-note-caps' ),
				'foot'   => array( __( 'Footnote', 'lilo-cafe' ), '.lilo-msec__foot' ),
			),
			__( 'Heading & footnote', 'lilo-cafe' )
		);
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$id    = sanitize_html_class( $s['anchor_id'] );
		$label = ! empty( $s['tab_label'] ) ? $s['tab_label'] : $s['title'];
		$head  = $s;
		unset( $head['note'] );
		?>
		<div class="lilo-sec lilo-msec lilo-msec--<?php echo esc_attr( str_replace( 'lilo-menu-', '', $this->get_name() ) ); ?>">
			<div class="lilo-sec__inner">
				<section class="lilo-msec__box"<?php echo $id ? ' id="' . esc_attr( $id ) . '" data-lilo-tab="' . esc_attr( $label ) . '"' : ''; ?>>
					<div class="lilo-msec__head">
						<?php echo $this->heading_html( $head ); // phpcs:ignore ?>
						<?php if ( ! empty( $s['note'] ) ) : ?>
							<span class="lilo-note-caps"><?php echo esc_html( $s['note'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php $this->render_items( $s ); ?>
					<?php if ( ! empty( $s['footnote'] ) ) : ?>
						<p class="lilo-msec__foot"><?php echo esc_html( $s['footnote'] ); ?></p>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}
}
