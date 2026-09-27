<?php
/**
 * Menu section: photo cards.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Menu_Photos extends Lilo_Menu_Section_Base {

	public function get_name() {
		return 'lilo-menu-photos';
	}

	public function get_title() {
		return __( 'Lilo Menu: Photo Cards', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_items_controls() {
		$this->start_controls_section( 'section_items', array( 'label' => __( 'Items', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'image', array( 'label' => __( 'Image', 'lilo-cafe' ), 'type' => Controls_Manager::MEDIA ) );
		$r->add_control( 'name', array( 'label' => __( 'Name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'desc', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ) );
		$r->add_control( 'price', array( 'label' => __( 'Price (optional)', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->clean_rows( $this->d_rows( 'items' ) ),
				'title_field' => '{{{ name }}}',
			)
		);
		$this->add_grid_controls( '.lilo-cards', 210 );
		$this->end_controls_section();
	}

	protected function register_items_style() {
		$this->add_control( 'card_bg', array( 'label' => __( 'Card background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-card-p' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'card_border', array( 'label' => __( 'Card border color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-card-p' => 'border-color: {{VALUE}};' ) ) );
		$this->add_text_style( 'item_name', __( 'Name', 'lilo-cafe' ), '.lilo-card-p__name' );
		$this->add_text_style( 'item_desc', __( 'Description', 'lilo-cafe' ), '.lilo-card-p__desc' );
		$this->add_text_style( 'item_price', __( 'Price', 'lilo-cafe' ), '.lilo-card-p__price' );
	}

	protected function render_items( $s ) {
		echo '<div class="lilo-cards lilo-cards--bordered">';
		foreach ( (array) $s['items'] as $it ) {
			?>
			<div class="lilo-card-p elementor-repeater-item-<?php echo esc_attr( $it['_id'] ); ?>">
				<div class="lilo-card-p__media"><?php echo lilo_img( $it['image'], $it['name'], 'medium' ); // phpcs:ignore ?></div>
				<div class="lilo-card-p__text">
					<span class="lilo-card-p__name"><?php echo esc_html( $it['name'] ); ?></span>
					<?php if ( ! empty( $it['desc'] ) ) : ?>
						<span class="lilo-card-p__desc"><?php echo esc_html( $it['desc'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $it['price'] ) ) : ?>
						<span class="lilo-card-p__price"><?php echo esc_html( $it['price'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
		echo '</div>';
	}
}
