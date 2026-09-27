<?php
/**
 * Visit: address, hours table and a map.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Visit extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-visit';
	}

	public function get_title() {
		return __( 'Lilo Visit / Hours & Map', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-google-maps';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->end_controls_section();

		$this->start_controls_section( 'section_address', array( 'label' => __( 'Address', 'lilo-cafe' ) ) );
		$this->add_control( 'address_label', array( 'label' => __( 'Label', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'address_label' ) ) );
		$this->add_control( 'address', array( 'label' => __( 'Address', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'default' => $this->d( 'address' ) ) );
		$this->add_control( 'map_link_text', array( 'label' => __( 'Link text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'map_link_text' ) ) );
		$this->add_link_control( 'map_link', __( 'Link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_hours', array( 'label' => __( 'Hours', 'lilo-cafe' ) ) );
		$this->add_control( 'hours_label', array( 'label' => __( 'Label', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'hours_label' ) ) );
		$r = new Repeater();
		$r->add_control( 'days', array( 'label' => __( 'Days', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'time', array( 'label' => __( 'Hours', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$this->add_control(
			'hours',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'hours' ),
				'title_field' => '{{{ days }}}',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_map', array( 'label' => __( 'Map', 'lilo-cafe' ) ) );
		$this->add_control(
			'map_type',
			array(
				'label'   => __( 'Show', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $this->d( 'map_type' ),
				'options' => array(
					'embed' => __( 'Google map', 'lilo-cafe' ),
					'image' => __( 'Image', 'lilo-cafe' ),
					'none'  => __( 'Nothing', 'lilo-cafe' ),
				),
			)
		);
		$this->add_control( 'map_query', array( 'label' => __( 'Map address', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'default' => $this->d( 'map_query' ), 'condition' => array( 'map_type' => 'embed' ) ) );
		$this->add_control(
			'map_zoom',
			array(
				'label'     => __( 'Zoom', 'lilo-cafe' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => $this->d( 'map_zoom' ),
				'range'     => array( 'px' => array( 'min' => 1, 'max' => 20 ) ),
				'condition' => array( 'map_type' => 'embed' ),
			)
		);
		$this->add_image_control( 'map_image', __( 'Image', 'lilo-cafe' ), array( 'map_type' => 'image' ) );
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_map', array( 'label' => __( 'Map & hours', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'map_height',
			array(
				'label'      => __( 'Map min height', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array( 'px' => array( 'min' => 200, 'max' => 900 ) ),
				'selectors'  => array( '{{WRAPPER}} .lilo-visit__map, {{WRAPPER}} .lilo-visit__map iframe' => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'map_radius',
			array(
				'label'      => __( 'Map radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}} .lilo-visit__map' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'row_line', array( 'label' => __( 'Hours divider color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-visit__row' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script' => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'  => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'labels' => array( __( 'Labels', 'lilo-cafe' ), '.lilo-label' ),
				'addr'   => array( __( 'Address', 'lilo-cafe' ), '.lilo-visit__address' ),
				'mlink'  => array( __( 'Map link', 'lilo-cafe' ), '.lilo-visit__link' ),
				'rows'   => array( __( 'Hours rows', 'lilo-cafe' ), '.lilo-visit__row' ),
			)
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-visit"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<div class="lilo-visit__info">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<div class="lilo-visit__block lilo-visit__block--address">
						<?php if ( ! empty( $s['address_label'] ) ) : ?>
							<span class="lilo-label"><?php echo esc_html( $s['address_label'] ); ?></span>
						<?php endif; ?>
						<span class="lilo-visit__address"><?php echo nl2br( esc_html( $s['address'] ) ); ?></span>
						<?php echo $this->button_html( $s['map_link_text'], $s['map_link'], 'lilo-visit__link' ); // phpcs:ignore ?>
					</div>
					<?php if ( ! empty( $s['hours'] ) ) : ?>
						<div class="lilo-visit__block lilo-visit__block--hours">
							<?php if ( ! empty( $s['hours_label'] ) ) : ?>
								<span class="lilo-label"><?php echo esc_html( $s['hours_label'] ); ?></span>
							<?php endif; ?>
							<div class="lilo-visit__hours">
								<?php foreach ( $s['hours'] as $h ) : ?>
									<div class="lilo-visit__row elementor-repeater-item-<?php echo esc_attr( $h['_id'] ); ?>"><span><?php echo esc_html( $h['days'] ); ?></span><span><?php echo esc_html( $h['time'] ); ?></span></div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( 'embed' === $s['map_type'] && ! empty( $s['map_query'] ) ) : ?>
					<?php
					$zoom = ! empty( $s['map_zoom']['size'] ) ? (int) $s['map_zoom']['size'] : 16;
					$src  = 'https://maps.google.com/maps?q=' . rawurlencode( $s['map_query'] ) . '&z=' . $zoom . '&output=embed';
					?>
					<div class="lilo-visit__map"><iframe title="<?php echo esc_attr( $s['map_query'] ); ?>" src="<?php echo esc_url( $src ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
				<?php elseif ( 'image' === $s['map_type'] && ! empty( $s['map_image']['url'] ) ) : ?>
					<div class="lilo-visit__map lilo-visit__map--image"><?php echo lilo_img( $s['map_image'], $s['title'], 'large' ); // phpcs:ignore ?></div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
