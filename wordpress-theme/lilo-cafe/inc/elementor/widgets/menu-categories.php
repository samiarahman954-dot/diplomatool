<?php
/**
 * "On the menu": arch category cards.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Menu_Categories extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-menu-categories';
	}

	public function get_title() {
		return __( 'Lilo Menu Categories', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->add_control( 'link_text', array( 'label' => __( 'Link text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'link_text' ), 'separator' => 'before' ) );
		$this->add_link_control( 'link', __( 'Link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_items', array( 'label' => __( 'Categories', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'image', array( 'label' => __( 'Image', 'lilo-cafe' ), 'type' => Controls_Manager::MEDIA ) );
		$r->add_control( 'title', array( 'label' => __( 'Title', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'desc', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA ) );
		$r->add_control( 'link', array( 'label' => __( 'Link', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control(
			'categories',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->clean_rows( $this->d_rows( 'categories' ) ),
				'title_field' => '{{{ title }}}',
			)
		);
		$this->add_grid_controls( '.lilo-cats__grid', 260 );
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_cards', array( 'label' => __( 'Cards', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'card_ratio',
			array(
				'label'   => __( 'Image aspect ratio', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''    => __( 'Default (4:5)', 'lilo-cafe' ),
					'1/1' => '1:1',
					'3/4' => '3:4',
					'2/3' => '2:3',
					'4/3' => '4:3',
				),
				'selectors' => array( '{{WRAPPER}} .lilo-cats__media' => 'aspect-ratio: {{VALUE}};' ),
			)
		);
		$this->add_frame_controls( '.lilo-cats__media', __( 'Image frame', 'lilo-cafe' ), false );
		$this->add_text_style( 'card_title', __( 'Card title', 'lilo-cafe' ), '.lilo-cats__title' );
		$this->add_text_style( 'card_desc', __( 'Card description', 'lilo-cafe' ), '.lilo-cats__desc' );
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'link'   => array( __( 'Link', 'lilo-cafe' ), '.lilo-link' ),
			),
			__( 'Heading', 'lilo-cafe' )
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-cats"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-sec__head">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<?php echo $this->button_html( $s['link_text'], $s['link'], 'lilo-link' ); // phpcs:ignore ?>
				</div>
				<div class="lilo-cats__grid">
					<?php foreach ( (array) $s['categories'] as $c ) : ?>
						<a class="lilo-cats__card elementor-repeater-item-<?php echo esc_attr( $c['_id'] ); ?>"<?php echo lilo_link_attrs( $c['link'] ); // phpcs:ignore ?>>
							<div class="lilo-cats__media"><?php echo lilo_img( $c['image'], $c['title'], 'medium_large' ); // phpcs:ignore ?></div>
							<div class="lilo-cats__text">
								<h3 class="lilo-cats__title"><?php echo esc_html( $c['title'] ); ?></h3>
								<?php if ( ! empty( $c['desc'] ) ) : ?>
									<p class="lilo-cats__desc"><?php echo esc_html( $c['desc'] ); ?></p>
								<?php endif; ?>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
