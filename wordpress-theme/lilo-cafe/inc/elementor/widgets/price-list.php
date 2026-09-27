<?php
/**
 * Signature list: dark band with a priced list and an arch photo.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Price_List extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-price-list';
	}

	public function get_title() {
		return __( 'Lilo Signature Price List', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls( true );
		$this->end_controls_section();

		$this->start_controls_section( 'section_items', array( 'label' => __( 'Items', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'name', array( 'label' => __( 'Name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'desc', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ) );
		$r->add_control( 'price', array( 'label' => __( 'Price', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'items' ),
				'title_field' => '{{{ name }}}',
			)
		);
		$this->add_control( 'footnote', array( 'label' => __( 'Footnote', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => $this->d( 'footnote' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_image', array( 'label' => __( 'Image', 'lilo-cafe' ) ) );
		$this->add_image_control( 'image', __( 'Photo', 'lilo-cafe' ) );
		$this->add_control(
			'image_position',
			array(
				'label'   => __( 'Photo position', 'lilo-cafe' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => $this->d( 'image_position' ),
				'options' => array(
					'left'  => array( 'title' => __( 'Left', 'lilo-cafe' ), 'icon' => 'eicon-h-align-left' ),
					'right' => array( 'title' => __( 'Right', 'lilo-cafe' ), 'icon' => 'eicon-h-align-right' ),
				),
			)
		);
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_list', array( 'label' => __( 'List & photo', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'divider_color', array( 'label' => __( 'Divider color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-plist__row' => 'border-color: {{VALUE}};' ) ) );
		$this->add_text_style( 'item_name', __( 'Item name', 'lilo-cafe' ), '.lilo-plist__name' );
		$this->add_text_style( 'item_desc', __( 'Item description', 'lilo-cafe' ), '.lilo-plist__desc' );
		$this->add_text_style( 'item_price', __( 'Item price', 'lilo-cafe' ), '.lilo-plist__price' );
		$this->add_text_style( 'foot', __( 'Footnote', 'lilo-cafe' ), '.lilo-plist__foot' );
		$this->add_frame_controls( '.lilo-plist__media', __( 'Photo frame', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'noteh'  => array( __( 'Note', 'lilo-cafe' ), '.lilo-note-caps' ),
			),
			__( 'Heading', 'lilo-cafe' )
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-plist lilo-plist--img-<?php echo esc_attr( 'left' === $s['image_position'] ? 'left' : 'right' ); ?>"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-plist__content">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<div class="lilo-plist__list">
						<?php foreach ( (array) $s['items'] as $it ) : ?>
							<div class="lilo-plist__row elementor-repeater-item-<?php echo esc_attr( $it['_id'] ); ?>">
								<div class="lilo-plist__main">
									<span class="lilo-plist__name"><?php echo esc_html( $it['name'] ); ?></span>
									<?php if ( ! empty( $it['desc'] ) ) : ?>
										<span class="lilo-plist__desc"><?php echo esc_html( $it['desc'] ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( ! empty( $it['price'] ) ) : ?>
									<span class="lilo-plist__price"><?php echo esc_html( $it['price'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $s['footnote'] ) ) : ?>
						<p class="lilo-plist__foot"><?php echo esc_html( $s['footnote'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $s['image']['url'] ) ) : ?>
					<div class="lilo-plist__media"><?php echo lilo_img( $s['image'], $s['title'], 'large' ); // phpcs:ignore ?></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
