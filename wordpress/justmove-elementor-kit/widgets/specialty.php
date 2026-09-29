<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Specialty extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-specialty';
	}

	public function get_title() {
		return __( 'JM Specialty Moves', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-apps';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Specialty moves', 'jmk' ) ) );
		$this->add_section_head_controls( 'Specialty moves', 'Moves that need more than the standard playbook' );
		$this->add_columns_control( 4 );
		$rep = new Repeater();
		foreach ( $this->icon_fields( '📦' ) as $field ) {
			$name = $field['name'];
			unset( $field['name'] );
			$rep->add_control( $name, $field );
		}
		$rep->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Specialty move', 'jmk' ),
				'label_block' => true,
			)
		);
		$rep->add_control(
			'text',
			array(
				'label' => __( 'Text', 'jmk' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Items', 'jmk' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array( 'emoji' => '🎖', 'title' => 'Military & PCS moves', 'text' => 'On-base and PCS relocations handled on your timeline and paperwork.' ),
					array( 'emoji' => '🎱', 'title' => 'Pool table movers', 'text' => 'Disassembly, transport and setup for slate pool tables across DFW.' ),
					array( 'emoji' => '🔒', 'title' => 'Gun safe movers', 'text' => 'Heavy safes moved safely with the right equipment and trained crews.' ),
					array( 'emoji' => '📦', 'title' => 'Storage & moving combo', 'text' => 'Need a gap between move-out and move-in? We combine storage with your move.' ),
				),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="jmk jmk-specialty"><section class="jmk-sec jmk-pt0"><div class="jmk-wrap">';
		$this->render_section_head( $s );
		echo '<div class="jmk-spec" style="--cols:' . (int) $s['columns'] . '">';
		foreach ( $s['items'] as $item ) {
			echo '<div class="s">';
			$this->render_icon( $item );
			echo '<b>' . esc_html( $item['title'] ) . '</b>';
			if ( '' !== $item['text'] ) {
				echo '<p>' . esc_html( $item['text'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div></div></section></div>';
	}
}
