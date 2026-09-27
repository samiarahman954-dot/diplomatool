<?php
/**
 * Sticky pill tabs that jump to menu sections.
 *
 * Put this widget and the menu section widgets in the same container so it
 * stays pinned while the menu scrolls.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Menu_Tabs extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-menu-tabs';
	}

	public function get_title() {
		return __( 'Lilo Menu Tabs (sticky)', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-tabs';
	}

	public function get_keywords() {
		return array( 'lilo', 'menu', 'tabs', 'anchor', 'sticky', 'nav' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_tabs', array( 'label' => __( 'Tabs', 'lilo-cafe' ) ) );
		$this->add_control(
			'source',
			array(
				'label'   => __( 'Tabs', 'lilo-cafe' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $this->d( 'source' ),
				'options' => array(
					'manual' => __( 'Set manually', 'lilo-cafe' ),
					'auto'   => __( 'Build automatically from menu sections', 'lilo-cafe' ),
				),
			)
		);
		$r = new Repeater();
		$r->add_control( 'text', array( 'label' => __( 'Label', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT ) );
		$r->add_control( 'anchor', array( 'label' => __( 'Section ID', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'description' => __( 'Without the #, e.g. lattes', 'lilo-cafe' ) ) );
		$this->add_control(
			'tabs',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'tabs' ),
				'title_field' => '{{{ text }}}',
				'condition'   => array( 'source' => 'manual' ),
			)
		);
		$this->add_control(
			'sticky',
			array(
				'label'        => __( 'Stick below header', 'lilo-cafe' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'separator'    => 'before',
				'prefix_class' => 'lilo-mtabs-sticky-',
				'description'  => __( 'Keep the tabs and the menu section widgets in the same container so the tabs stay pinned while that container scrolls.', 'lilo-cafe' ),
			)
		);
		$this->add_control( 'highlight', array( 'label' => __( 'Highlight current section', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->add_box_style_section( '.lilo-mtabs', '.lilo-mtabs__inner' );
		$this->add_button_style_section( 'tab', __( 'Tabs', 'lilo-cafe' ), '.lilo-mtabs__tab' );
		$this->start_controls_section( 'section_style_active', array( 'label' => __( 'Active tab', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'active_color', array( 'label' => __( 'Text color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mtabs__tab.is-active' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'active_bg', array( 'label' => __( 'Background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mtabs__tab.is-active' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'active_border', array( 'label' => __( 'Border color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-mtabs__tab.is-active' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$classes = 'lilo-mtabs' . ( 'yes' === $s['sticky'] ? ' is-sticky' : '' ) . ( 'yes' === $s['highlight'] ? ' is-spy' : '' );
		$auto    = 'auto' === $s['source'];
		?>
		<nav class="<?php echo esc_attr( $classes ); ?>" aria-label="<?php esc_attr_e( 'Menu sections', 'lilo-cafe' ); ?>"<?php echo $auto ? ' data-lilo-auto-tabs' : ''; ?>>
			<div class="lilo-mtabs__inner">
				<?php
				if ( ! $auto ) {
					foreach ( (array) $s['tabs'] as $t ) {
						$anchor = sanitize_html_class( ltrim( (string) $t['anchor'], '#' ) );
						if ( ! $anchor || '' === $t['text'] ) {
							continue;
						}
						printf( '<a class="lilo-mtabs__tab elementor-repeater-item-%s" href="#%s">%s</a>', esc_attr( $t['_id'] ), esc_attr( $anchor ), esc_html( $t['text'] ) );
					}
				}
				?>
			</div>
		</nav>
		<?php
	}
}
