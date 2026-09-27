<?php
/**
 * Hero: arch split or full-bleed photo, with live open/closed status.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class Lilo_Widget_Hero extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-hero';
	}

	public function get_title() {
		return __( 'Lilo Hero', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_layout', array( 'label' => __( 'Layout', 'lilo-cafe' ) ) );
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Hero style', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $this->d( 'layout' ),
				'options' => array(
					'split' => __( 'Arch split', 'lilo-cafe' ),
					'full'  => __( 'Full-bleed photo', 'lilo-cafe' ),
				),
			)
		);
		$this->add_control(
			'card_position',
			array(
				'label'     => __( 'Card position', 'lilo-cafe' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => $this->d( 'card_position' ),
				'options'   => array(
					'left'  => array( 'title' => __( 'Left', 'lilo-cafe' ), 'icon' => 'eicon-h-align-left' ),
					'right' => array( 'title' => __( 'Right', 'lilo-cafe' ), 'icon' => 'eicon-h-align-right' ),
				),
				'condition' => array( 'layout' => 'full' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'lilo-cafe' ) ) );
		$this->add_image_control( 'eyebrow_icon', __( 'Eyebrow icon', 'lilo-cafe' ) );
		$this->add_control( 'eyebrow_text', array( 'label' => __( 'Eyebrow text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'eyebrow_text' ), 'label_block' => true ) );
		$this->add_control( 'title_before', array( 'label' => __( 'Title: before', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'title_before' ), 'label_block' => true, 'separator' => 'before' ) );
		$this->add_control( 'title_highlight', array( 'label' => __( 'Title: script highlight', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'title_highlight' ), 'label_block' => true ) );
		$this->add_control( 'title_after', array( 'label' => __( 'Title: after', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'title_after' ), 'label_block' => true ) );
		$this->add_control(
			'title_tag',
			array(
				'label'   => __( 'Title HTML tag', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h1',
				'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'div' => 'div' ),
			)
		);
		$this->add_control( 'description', array( 'label' => __( 'Description', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'default' => $this->d( 'description' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_buttons', array( 'label' => __( 'Buttons', 'lilo-cafe' ) ) );
		$this->add_control( 'btn1_text', array( 'label' => __( 'Primary button', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'btn1_text' ) ) );
		$this->add_link_control( 'btn1_link', __( 'Primary link', 'lilo-cafe' ) );
		$this->add_control( 'btn2_text', array( 'label' => __( 'Outline button', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'btn2_text' ), 'separator' => 'before', 'description' => __( 'Shown in the Arch split style.', 'lilo-cafe' ) ) );
		$this->add_link_control( 'btn2_link', __( 'Outline link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_status', array( 'label' => __( 'Open / closed status', 'lilo-cafe' ) ) );
		$this->add_control( 'show_status', array( 'label' => __( 'Show live status', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'show_status' ) ) );
		$days = array(
			'sun' => __( 'Sunday', 'lilo-cafe' ),
			'mon' => __( 'Monday', 'lilo-cafe' ),
			'tue' => __( 'Tuesday', 'lilo-cafe' ),
			'wed' => __( 'Wednesday', 'lilo-cafe' ),
			'thu' => __( 'Thursday', 'lilo-cafe' ),
			'fri' => __( 'Friday', 'lilo-cafe' ),
			'sat' => __( 'Saturday', 'lilo-cafe' ),
		);
		$first = true;
		foreach ( $days as $key => $label ) {
			$args = array(
				'label'       => $label,
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'hours_' . $key ),
				'placeholder' => __( 'Closed', 'lilo-cafe' ),
				'condition'   => array( 'show_status' => 'yes' ),
			);
			if ( $first ) {
				$args['description'] = __( 'Opening hours like 8:30am-6:30pm. Leave empty when closed. Uses the timezone in Settings > General.', 'lilo-cafe' );
				$first               = false;
			}
			$this->add_control( 'hours_' . $key, $args );
		}
		$this->add_control( 'status_open_text', array( 'label' => __( 'Text while open', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'status_open_text' ), 'label_block' => true, 'separator' => 'before', 'condition' => array( 'show_status' => 'yes' ) ) );
		$this->add_control( 'status_later_text', array( 'label' => __( 'Text before/after hours', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'status_later_text' ), 'label_block' => true, 'condition' => array( 'show_status' => 'yes' ) ) );
		$this->add_control(
			'status_closed_text',
			array(
				'label'       => __( 'Text on closed days', 'lilo-cafe' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => $this->d( 'status_closed_text' ),
				'label_block' => true,
				'description' => __( 'Placeholders: {hours}, {day}, {next_day}, {next_open}.', 'lilo-cafe' ),
				'condition'   => array( 'show_status' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_images', array( 'label' => __( 'Images', 'lilo-cafe' ) ) );
		$this->add_image_control( 'image', __( 'Main photo', 'lilo-cafe' ) );
		$this->add_control( 'show_badge', array( 'label' => __( 'Round badge photo', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'show_badge' ), 'separator' => 'before', 'condition' => array( 'layout' => 'split' ) ) );
		$this->add_image_control( 'badge_image', __( 'Badge photo', 'lilo-cafe' ), array( 'layout' => 'split', 'show_badge' => 'yes' ) );
		$this->add_control( 'show_flower', array( 'label' => __( 'Decorative flower', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'show_flower' ), 'separator' => 'before', 'condition' => array( 'layout' => 'split' ) ) );
		$this->add_image_control( 'flower_image', __( 'Flower image', 'lilo-cafe' ), array( 'layout' => 'split', 'show_flower' => 'yes' ) );
		$this->end_controls_section();

		// Style.
		$this->add_box_style_section( '.lilo-hero', '.lilo-hero__inner' );
		$this->start_controls_section( 'section_style_media', array( 'label' => __( 'Photo', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'hero_height',
			array(
				'label'      => __( 'Height', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh', 'vw' ),
				'range'      => array( 'px' => array( 'min' => 300, 'max' => 1000 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .lilo-hero--split .lilo-hero__media' => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .lilo-hero--full' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'photo_radius',
			array(
				'label'      => __( 'Photo corner radius', 'lilo-cafe' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .lilo-hero--split .lilo-hero__photo' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'condition'  => array( 'layout' => 'split' ),
			)
		);
		$this->add_control(
			'overlay_color',
			array(
				'label'     => __( 'Photo overlay', 'lilo-cafe' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .lilo-hero__photo::after' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Card background', 'lilo-cafe' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .lilo-hero__card' => 'background-color: {{VALUE}};' ),
				'condition' => array( 'layout' => 'full' ),
			)
		);
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'eyebrow'   => array( __( 'Eyebrow', 'lilo-cafe' ), '.lilo-eyebrow__text' ),
				'title'     => array( __( 'Title', 'lilo-cafe' ), '.lilo-hero__title' ),
				'highlight' => array( __( 'Script highlight', 'lilo-cafe' ), '.lilo-hero__title .lilo-hl' ),
				'desc'      => array( __( 'Description', 'lilo-cafe' ), '.lilo-hero__desc' ),
				'status'    => array( __( 'Status', 'lilo-cafe' ), '.lilo-status' ),
			)
		);
		$this->add_button_style_section( 'btn1', __( 'Primary button', 'lilo-cafe' ), '.lilo-btn--primary' );
		$this->add_button_style_section( 'btn2', __( 'Outline button', 'lilo-cafe' ), '.lilo-btn--outline' );
	}

	protected function title_html( $s ) {
		$tag  = in_array( $s['title_tag'], array( 'h1', 'h2', 'div' ), true ) ? $s['title_tag'] : 'h1';
		$html = esc_html( $s['title_before'] );
		if ( '' !== $s['title_highlight'] ) {
			$html .= ' <span class="lilo-hl">' . esc_html( $s['title_highlight'] ) . '</span>';
		}
		if ( '' !== $s['title_after'] ) {
			$html .= ' ' . esc_html( $s['title_after'] );
		}
		return '<' . $tag . ' class="lilo-hero__title">' . trim( $html ) . '</' . $tag . '>';
	}

	protected function eyebrow_html( $s ) {
		if ( empty( $s['eyebrow_text'] ) && empty( $s['eyebrow_icon']['url'] ) ) {
			return '';
		}
		$icon = ! empty( $s['eyebrow_icon']['url'] ) ? lilo_img( $s['eyebrow_icon'], '', 'thumbnail', array( 'class' => 'lilo-eyebrow__icon' ) ) : '';
		return '<div class="lilo-eyebrow">' . $icon . '<span class="lilo-eyebrow__text">' . esc_html( $s['eyebrow_text'] ) . '</span></div>';
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$status = 'yes' === $s['show_status'] ? lilo_status_html( $s ) : '';
		$alt    = trim( $s['title_before'] . ' ' . $s['title_highlight'] . ' ' . $s['title_after'] );

		if ( 'full' === $s['layout'] ) {
			?>
			<section class="lilo-hero lilo-hero--full lilo-hero--card-<?php echo esc_attr( $s['card_position'] ? $s['card_position'] : 'right' ); ?>">
				<div class="lilo-hero__photo"><?php echo lilo_img( $s['image'], $alt, 'full', array( 'loading' => 'eager' ) ); // phpcs:ignore ?></div>
				<div class="lilo-hero__inner">
					<div class="lilo-hero__card">
						<?php echo $this->eyebrow_html( $s ); // phpcs:ignore ?>
						<?php echo $this->title_html( $s ); // phpcs:ignore ?>
						<div class="lilo-hero__actions">
							<?php echo $this->button_html( $s['btn1_text'], $s['btn1_link'], 'lilo-btn lilo-btn--primary' ); // phpcs:ignore ?>
							<?php echo $status; // phpcs:ignore ?>
						</div>
					</div>
				</div>
			</section>
			<?php
			return;
		}
		?>
		<section class="lilo-hero lilo-hero--split">
			<div class="lilo-hero__inner">
				<div class="lilo-hero__content">
					<?php echo $this->eyebrow_html( $s ); // phpcs:ignore ?>
					<?php echo $this->title_html( $s ); // phpcs:ignore ?>
					<?php if ( ! empty( $s['description'] ) ) : ?>
						<p class="lilo-hero__desc"><?php echo esc_html( $s['description'] ); ?></p>
					<?php endif; ?>
					<div class="lilo-hero__buttons">
						<?php echo $this->button_html( $s['btn1_text'], $s['btn1_link'], 'lilo-btn lilo-btn--primary' ); // phpcs:ignore ?>
						<?php echo $this->button_html( $s['btn2_text'], $s['btn2_link'], 'lilo-btn lilo-btn--outline' ); // phpcs:ignore ?>
					</div>
					<?php echo $status; // phpcs:ignore ?>
				</div>
				<div class="lilo-hero__media">
					<div class="lilo-hero__photo"><?php echo lilo_img( $s['image'], $alt, 'full', array( 'loading' => 'eager' ) ); // phpcs:ignore ?></div>
					<?php if ( 'yes' === $s['show_badge'] && ! empty( $s['badge_image']['url'] ) ) : ?>
						<div class="lilo-hero__badge"><?php echo lilo_img( $s['badge_image'], '', 'medium' ); // phpcs:ignore ?></div>
					<?php endif; ?>
					<?php if ( 'yes' === $s['show_flower'] && ! empty( $s['flower_image']['url'] ) ) : ?>
						<?php echo lilo_img( $s['flower_image'], '', 'thumbnail', array( 'class' => 'lilo-hero__flower' ) ); // phpcs:ignore ?>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
