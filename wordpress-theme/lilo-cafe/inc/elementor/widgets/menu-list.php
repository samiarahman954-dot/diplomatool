<?php
/**
 * Menu section: dotted-leader price list with an optional arch photo.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Menu_List extends Lilo_Menu_Section_Base {

	public function get_name() {
		return 'lilo-menu-list';
	}

	public function get_title() {
		return __( 'Lilo Menu: Price List', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	protected function register_items_controls() {
		$this->start_controls_section( 'section_items', array( 'label' => __( 'Items', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'name', array( 'label' => __( 'Name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'desc', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ) );
		$r->add_control( 'price', array( 'label' => __( 'Price', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'description' => __( 'Leave empty to hide the dotted leader.', 'lilo-cafe' ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'items' ),
				'title_field' => '{{{ name }}}',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_image', array( 'label' => __( 'Photo', 'lilo-cafe' ) ) );
		$this->add_control( 'show_image', array( 'label' => __( 'Show photo', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'show_image' ) ) );
		$this->add_image_control( 'image', __( 'Photo', 'lilo-cafe' ), array( 'show_image' => 'yes' ) );
		$this->end_controls_section();
	}

	protected function register_items_style() {
		$this->add_control( 'leader_color', array( 'label' => __( 'Dotted leader color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mlist__dots' => 'border-color: {{VALUE}};' ) ) );
		$this->add_text_style( 'item_name', __( 'Name', 'lilo-cafe' ), '.lilo-mlist__name' );
		$this->add_text_style( 'item_price', __( 'Price', 'lilo-cafe' ), '.lilo-mlist__price' );
		$this->add_text_style( 'item_desc', __( 'Description', 'lilo-cafe' ), '.lilo-mlist__desc' );
		$this->add_frame_controls( '.lilo-mlist__media', __( 'Photo frame', 'lilo-cafe' ), false );
	}

	protected function render_items( $s ) {
		$has_img = 'yes' === $s['show_image'] && ! empty( $s['image']['url'] );
		?>
		<div class="lilo-mlist<?php echo $has_img ? ' lilo-mlist--has-img' : ''; ?>">
			<?php if ( $has_img ) : ?>
				<div class="lilo-mlist__media"><?php echo lilo_img( $s['image'], $s['title'], 'medium_large' ); // phpcs:ignore ?></div>
			<?php endif; ?>
			<div class="lilo-mlist__grid">
				<?php foreach ( (array) $s['items'] as $it ) : ?>
					<div class="lilo-mlist__item elementor-repeater-item-<?php echo esc_attr( $it['_id'] ); ?>">
						<div class="lilo-mlist__row">
							<span class="lilo-mlist__name"><?php echo esc_html( $it['name'] ); ?></span>
							<?php if ( '' !== trim( (string) $it['price'] ) ) : ?>
								<span class="lilo-mlist__dots" aria-hidden="true"></span>
								<span class="lilo-mlist__price"><?php echo esc_html( $it['price'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $it['desc'] ) ) : ?>
							<span class="lilo-mlist__desc"><?php echo esc_html( $it['desc'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
