<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;

class JMK_Widget_Trust_Strip extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-trust-strip';
	}

	public function get_title() {
		return __( 'JM Trust Strip', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-check-circle';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Trust items', 'jmk' ) ) );
		$rep = new Repeater();
		foreach ( $this->icon_fields( '✅' ) as $field ) {
			$name = $field['name'];
			unset( $field['name'] );
			$rep->add_control( $name, $field );
		}
		$rep->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Trust point', 'jmk' ),
			)
		);
		$rep->add_control(
			'sub',
			array(
				'label'   => __( 'Sub text', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
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
					array( 'emoji' => '✅', 'title' => 'Licensed & Insured', 'sub' => 'Texas moving company' ),
					array( 'emoji' => '⭐', 'title' => '4.7★ · 218 reviews', 'sub' => 'Real Google reviews' ),
					array( 'emoji' => '📍', 'title' => 'Grand Prairie based', 'sub' => 'Central to all of DFW' ),
					array( 'emoji' => '💵', 'title' => 'No hidden fees', 'sub' => 'Upfront, all-in quotes' ),
				),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<div class="jmk jmk-trust"><section class="jmk-sec"><div class="jmk-wrap">
			<?php foreach ( $s['items'] as $item ) : ?>
				<div class="jmk-tw"><?php $this->render_icon( $item ); ?><div><?php echo esc_html( $item['title'] ); ?>
					<?php if ( '' !== $item['sub'] ) : ?>
						<small><?php echo esc_html( $item['sub'] ); ?></small>
					<?php endif; ?></div></div>
			<?php endforeach; ?>
		</div></section></div>
		<?php
	}
}
