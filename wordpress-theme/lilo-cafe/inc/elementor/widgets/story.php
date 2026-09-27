<?php
/**
 * Our story: arch photo, rich text, team members and a tinted note.
 *
 * @package LiloCafe
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

class Lilo_Widget_Story extends Lilo_Widget_Base {

	public function get_name() {
		return 'lilo-story';
	}

	public function get_title() {
		return __( 'Lilo Our Story', 'lilo-cafe' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_heading', array( 'label' => __( 'Heading & text', 'lilo-cafe' ) ) );
		$this->add_anchor_control();
		$this->add_heading_controls();
		$this->add_control( 'content', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::WYSIWYG, 'default' => $this->d( 'content' ), 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_image', array( 'label' => __( 'Photo', 'lilo-cafe' ) ) );
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

		$this->start_controls_section( 'section_people', array( 'label' => __( 'People', 'lilo-cafe' ) ) );
		$r = new Repeater();
		$r->add_control( 'name', array( 'label' => __( 'Name', 'lilo-cafe' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$r->add_control( 'role', array( 'label' => __( 'Role', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2 ) );
		$this->add_control(
			'people',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'default'     => $this->d_rows( 'people' ),
				'title_field' => '{{{ name }}}',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_note', array( 'label' => __( 'Note box', 'lilo-cafe' ) ) );
		$this->add_control( 'show_note', array( 'label' => __( 'Show note', 'lilo-cafe' ), 'type' => Controls_Manager::SWITCHER, 'default' => $this->d( 'show_note' ) ) );
		$this->add_image_control( 'note_icon', __( 'Icon', 'lilo-cafe' ), array( 'show_note' => 'yes' ) );
		$this->add_control( 'note_text', array( 'label' => __( 'Text', 'lilo-cafe' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => $this->d( 'note_text' ), 'condition' => array( 'show_note' => 'yes' ) ) );
		$this->end_controls_section();

		$this->add_box_style_section();
		$this->start_controls_section( 'section_style_media', array( 'label' => __( 'Photo & note', 'lilo-cafe' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_frame_controls( '.lilo-story__media', __( 'Photo frame', 'lilo-cafe' ) );
		$this->add_control( 'note_bg', array( 'label' => __( 'Note background', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .lilo-story__note' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'people_line', array( 'label' => __( 'People divider color', 'lilo-cafe' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .lilo-story__person' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->add_texts_style_section(
			array(
				'script'   => array( __( 'Script text', 'lilo-cafe' ), '.lilo-script' ),
				'title'    => array( __( 'Title', 'lilo-cafe' ), '.lilo-title' ),
				'body'     => array( __( 'Text', 'lilo-cafe' ), '.lilo-story__body' ),
				'pname'    => array( __( 'Person name', 'lilo-cafe' ), '.lilo-story__name' ),
				'prole'    => array( __( 'Person role', 'lilo-cafe' ), '.lilo-story__role' ),
				'notetext' => array( __( 'Note text', 'lilo-cafe' ), '.lilo-story__note-text' ),
			)
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="lilo-sec lilo-story lilo-story--img-<?php echo esc_attr( 'right' === $s['image_position'] ? 'right' : 'left' ); ?>"<?php echo $this->id_attr( $s ); // phpcs:ignore ?>>
			<div class="lilo-sec__inner">
				<?php if ( ! empty( $s['image']['url'] ) ) : ?>
					<div class="lilo-story__media"><?php echo lilo_img( $s['image'], $s['title'], 'large' ); // phpcs:ignore ?></div>
				<?php endif; ?>
				<div class="lilo-story__content">
					<?php echo $this->heading_html( $s ); // phpcs:ignore ?>
					<?php if ( ! empty( $s['content'] ) ) : ?>
						<div class="lilo-story__body lilo-rich"><?php echo wp_kses_post( $this->parse_text_editor( $s['content'] ) ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $s['people'] ) ) : ?>
						<div class="lilo-story__people">
							<?php foreach ( $s['people'] as $p ) : ?>
								<div class="lilo-story__person elementor-repeater-item-<?php echo esc_attr( $p['_id'] ); ?>">
									<span class="lilo-story__name"><?php echo esc_html( $p['name'] ); ?></span>
									<span class="lilo-story__role"><?php echo esc_html( $p['role'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( 'yes' === $s['show_note'] && ! empty( $s['note_text'] ) ) : ?>
						<div class="lilo-story__note">
							<?php echo ! empty( $s['note_icon']['url'] ) ? lilo_img( $s['note_icon'], '', 'thumbnail', array( 'class' => 'lilo-story__note-icon' ) ) : ''; // phpcs:ignore ?>
							<span class="lilo-story__note-text"><?php echo esc_html( $s['note_text'] ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
