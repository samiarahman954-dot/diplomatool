<?php
/**
 * Menu section: option groups shown as chips ("Make it yours", add-ons).
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Menu_Options extends Lilo_Menu_Section_Base {

	public function get_name() {
		return 'lilo-menu-options';
	}

	public function get_title() {
		return __( 'Lilo Menu: Options & Add-ons', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-form-vertical';
	}

	protected function register_items_controls() {
		$this->start_controls_section( 'section_items', array( 'label' => __( 'Option groups', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'name', array( 'label' => __( 'Group name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'price', array( 'label' => __( 'Price', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'options', array( 'label' => __( 'Options (one per line)', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 5 ) );
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'items' ),
				'title_field' => '{{{ name }}} {{{ price }}}',
			)
		);
		$this->add_grid_controls( '.lilo-mopts', 280 );
		$this->end_controls_section();
	}

	protected function register_items_style() {
		$this->add_control( 'group_line', array( 'label' => __( 'Group line color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mopts__group' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'chip_bg', array( 'label' => __( 'Chip background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-chip' => 'background-color: {{VALUE}};' ) ) );
		$this->add_text_style( 'group_name', __( 'Group name', 'lilo-cafe' ), '.lilo-mopts__name' );
		$this->add_text_style( 'group_price', __( 'Price', 'lilo-cafe' ), '.lilo-mopts__price' );
		$this->add_text_style( 'chip', __( 'Chips', 'lilo-cafe' ), '.lilo-chip' );
	}

	protected function render_items( $s ) {
		echo '<div class="lilo-mopts">';
		foreach ( (array) $s['items'] as $g ) {
			$options = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) $g['options'] ) ) );
			?>
			<div class="lilo-mopts__group elementor-repeater-item-<?php echo esc_attr( $g['_id'] ); ?>">
				<div class="lilo-mopts__top">
					<span class="lilo-mopts__name"><?php echo esc_html( $g['name'] ); ?></span>
					<span class="lilo-mopts__price"><?php echo esc_html( $g['price'] ); ?></span>
				</div>
				<?php if ( $options ) : ?>
					<div class="lilo-mopts__chips">
						<?php foreach ( $options as $o ) : ?>
							<span class="lilo-chip"><?php echo esc_html( $o ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
		echo '</div>';
	}
}
