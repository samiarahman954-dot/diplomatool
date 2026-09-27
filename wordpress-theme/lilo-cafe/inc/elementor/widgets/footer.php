<?php
/**
 * Site footer: logo, info columns and copyright.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Footer extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-footer';
	}

	public function get_title() {
		return __( 'Lilo Site Footer', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-footer';
	}

	public function get_keywords() {
		return array( 'lilo', 'footer', 'hours', 'address', 'social' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_logo', array( 'label' => __( 'Logo', 'lilo-cafe' ) ) );
		$this->add_image_control( 'logo', __( 'Logo', 'lilo-cafe' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_columns', array( 'label' => __( 'Columns', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'title', array( 'label' => __( 'Title', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 4, 'description' => __( 'Line breaks, <br> and <a href> links are allowed.', 'lilo-cafe' ) ) );
		$r->add_control( 'links', array( 'label' => __( 'Show as link list', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER ) );
		$this->add_control(
			'columns',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'columns' ),
				'title_field' => '{{{ title }}}',
			)
		);
		$this->add_control( 'copyright', array( 'label' => __( 'Copyright', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => $this->d( 'copyright' ), 'description' => __( '{year} shows the current year.', 'lilo-cafe' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_box', array( 'label' => __( 'Footer', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bg', array( 'label' => __( 'Background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-footer' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'line', array( 'label' => __( 'Line color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-footer, {{WRAPPER}} .lilo-footer__bottom' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'logo_width',
			array(
				'label'      => __( 'Logo width', 'lilo-cafe' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 40, 'max' => 400 ) ),
				'selectors'  => array( '{{WRAPPER}} .lilo-footer__logo-img' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'label_s' => array( __( 'Column titles', 'lilo-cafe' ), '.lilo-label' ),
				'text_s'  => array( __( 'Column text', 'lilo-cafe' ), '.lilo-footer__col' ),
				'link_s'  => array( __( 'Links', 'lilo-cafe' ), '.lilo-footer__col a' ),
				'copy_s'  => array( __( 'Copyright', 'lilo-cafe' ), '.lilo-footer__bottom' ),
			)
		);
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$cols = array();
		foreach ( (array) $s['columns'] as $c ) {
			$cols[] = array(
				'title' => $c['title'],
				'text'  => 'yes' === $c['links'] ? $c['text'] : nl2br( str_replace( array( "<br>\n", "<br />\n" ), "\n", $c['text'] ) ),
				'links' => 'yes' === $c['links'],
			);
		}
		echo lilo_render_footer( // phpcs:ignore
			array(
				'logo'      => $s['logo'],
				'columns'   => $cols,
				'copyright' => $s['copyright'],
			)
		);
	}
}
