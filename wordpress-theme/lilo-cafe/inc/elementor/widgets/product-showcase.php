<?php
/**
 * Product showcase: white photo cards on a tinted band ("Raspados & coladas").
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Product_Showcase extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-product-showcase';
	}

	public function get_title() {
		return __( 'Lilo Product Showcase', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-products';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->add_control( 'description', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'default' => $this->d( 'description' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_items', array( 'label' => __( 'Products', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'image', array( 'label' => __( 'Image', 'lilo-cafe' ), 'type' => Controls_Manager::MEDIA ) );
		$r->add_control( 'name', array( 'label' => __( 'Name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'desc', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ) );
		$r->add_control( 'link', array( 'label' => __( 'Link (optional)', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->clean_rows( $this->d_rows( 'items' ) ),
				'title_field' => '{{{ name }}}',
			)
		);
		$this->add_grid_controls( '.lilo-cards', 190 );
		$this->end_controls_section();

		$this->start_controls_section( 'section_button', array( 'label' => __( 'Button', 'lilo-cafe' ) ) );
		$this->add_control( 'btn_text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'btn_text' ) ) );
		$this->add_link_control( 'btn_link', __( 'Link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_cards', array( 'label' => __( 'Cards', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'card_bg', array( 'label' => __( 'Card background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-card-p' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control(
			'card_radius',
			array(
				'label'      => __( 'Card radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}} .lilo-card-p' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_text_style( 'card_name', __( 'Name', 'lilo-cafe' ), '.lilo-card-p__name' );
		$this->add_text_style( 'card_desc', __( 'Description', 'lilo-cafe' ), '.lilo-card-p__desc' );
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'intro'  => array( __( 'Description', 'lilo-cafe' ), '.lilo-sec__intro' ),
			),
			__( 'Heading', 'lilo-cafe' )
		);
		$this->add_button_style_section( 'btn', __( 'Button', 'lilo-cafe' ), '.lilo-btn' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-showcase"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-sec__head">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<?php if ( ! empty( $s['description'] ) ) : ?>
						<p class="lilo-sec__intro"><?php echo esc_html( $s['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<div class="lilo-cards">
					<?php
					foreach ( (array) $s['items'] as $it ) :
						$tag   = ! empty( $it['link']['url'] ) ? 'a' : 'div';
						$attrs = 'a' === $tag ? lilo_link_attrs( $it['link'] ) : '';
						?>
						<<?php echo $tag . $attrs; // phpcs:ignore ?> class="lilo-card-p elementor-repeater-item-<?php echo esc_attr( $it['_id'] ); ?>">
							<div class="lilo-card-p__media"><?php echo lilo_img( $it['image'], $it['name'], 'medium' ); // phpcs:ignore ?></div>
							<div class="lilo-card-p__text">
								<span class="lilo-card-p__name"><?php echo esc_html( $it['name'] ); ?></span>
								<?php if ( ! empty( $it['desc'] ) ) : ?>
									<span class="lilo-card-p__desc"><?php echo esc_html( $it['desc'] ); ?></span>
								<?php endif; ?>
							</div>
						</<?php echo $tag; // phpcs:ignore ?>>
					<?php endforeach; ?>
				</div>
				<?php if ( ! empty( $s['btn_text'] ) ) : ?>
					<div class="lilo-sec__foot"><?php echo $this->button_html( $s['btn_text'], $s['btn_link'], 'lilo-btn lilo-btn--primary' ); // phpcs:ignore ?></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
