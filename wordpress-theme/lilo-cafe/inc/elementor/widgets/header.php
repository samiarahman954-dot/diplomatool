<?php
/**
 * Site header: logo, navigation and order button.
 *
 * Use it inside Appearance > Header & Footer templates (or Elementor Pro's Theme Builder).
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Header extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-header';
	}

	public function get_title() {
		return __( 'Lilo Site Header', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	public function get_keywords() {
		return array( 'lilo', 'header', 'menu', 'nav', 'logo' );
	}

	protected function menu_options() {
		$options = array( '' => __( '— Primary menu location —', 'lilo-cafe' ) );
		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ $menu->term_id ] = $menu->name;
		}
		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_logo', array( 'label' => __( 'Logo', 'lilo-cafe' ) ) );
		$this->add_image_control( 'logo', __( 'Logo', 'lilo-cafe' ) );
		$this->add_link_control( 'logo_link', __( 'Logo link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_nav', array( 'label' => __( 'Navigation', 'lilo-cafe' ) ) );
		$this->add_control(
			'source',
			array(
				'label'   => __( 'Links from', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $this->d( 'source' ),
				'options' => array(
					'links' => __( 'Links set here', 'lilo-cafe' ),
					'menu'  => __( 'A WordPress menu', 'lilo-cafe' ),
				),
			)
		);
		$this->add_control(
			'menu',
			array(
				'label'       => __( 'Menu', 'lilo-cafe' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->menu_options(),
				'condition'   => array( 'source' => 'menu' ),
				'description' => sprintf( '<a href="%s" target="_blank">%s</a>', admin_url( 'nav-menus.php' ), __( 'Edit menus', 'lilo-cafe' ) ),
			)
		);
		$r = new Repeater();
		$r->add_control( 'text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'link', array( 'label' => __( 'Link', 'lilo-cafe' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ), 'description' => __( '{home} = site address, {menu} = Menu page.', 'lilo-cafe' ) ) );
		$this->add_control(
			'links',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'links' ),
				'title_field' => '{{{ text }}}',
				'condition'   => array( 'source' => 'links' ),
			)
		);
		$this->add_control( 'hamburger', array( 'label' => __( 'Hamburger menu on phones', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'hamburger' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_cta', array( 'label' => __( 'Button', 'lilo-cafe' ) ) );
		$this->add_control( 'cta_text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'default' => $this->d( 'cta_text' ) ) );
		$this->add_link_control( 'cta_link', __( 'Link', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_bar', array( 'label' => __( 'Bar', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bar_bg', array( 'label' => __( 'Background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-header, {{WRAPPER}} .lilo-nav' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'bar_border', array( 'label' => __( 'Bottom border color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-header' => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'logo_height',
			array(
				'label'      => __( 'Logo height', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 200 ) ),
				'selectors'  => array( '{{WRAPPER}} .lilo-header__logo-img' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'bar_padding',
			array(
				'label'      => __( 'Padding', 'lilo-cafe' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'vw' ),
				'selectors'  => array( '{{WRAPPER}} .lilo-header__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_links', array( 'label' => __( 'Links', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'link_typo', 'selector' => '{{WRAPPER}} .lilo-nav__list a' ) );
		$this->add_control( 'link_color', array( 'label' => __( 'Color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-nav__list a' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_hover', array( 'label' => __( 'Hover color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-nav__list a:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_active', array( 'label' => __( 'Current page underline', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-nav__list .current-menu-item > a' => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'link_gap',
			array(
				'label'      => __( 'Space between links', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .lilo-nav, {{WRAPPER}} .lilo-nav__list' => 'column-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_button_style_section( 'cta', __( 'Button', 'lilo-cafe' ), '.lilo-btn--nav' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo lilo_render_header( // phpcs:ignore
			array(
				'logo'      => $s['logo'],
				'logo_link' => ! empty( $s['logo_link']['url'] ) ? $s['logo_link']['url'] : home_url( '/' ),
				'source'    => $s['source'],
				'menu'      => $s['menu'] ? (int) $s['menu'] : 'primary',
				'links'     => $s['links'],
				'cta_text'  => $s['cta_text'],
				'cta_link'  => $s['cta_link'],
				'hamburger' => 'yes' === $s['hamburger'],
			)
		);
	}
}
