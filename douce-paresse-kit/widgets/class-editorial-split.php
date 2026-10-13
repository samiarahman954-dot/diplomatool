<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Image + editorial text side by side (story, numbered chapters, "L'expérience en chambre"...).
 */
class Editorial_Split extends Base {

	public function get_name() {
		return 'dpk-editorial-split';
	}

	public function get_title() {
		return __( 'DP Image & Story', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-image-before-after';
	}

	protected function spacing_options() {
		return array(
			'none' => __( 'None', 'douce-paresse-kit' ),
			's'    => __( 'Small', 'douce-paresse-kit' ),
			'm'    => __( 'Medium', 'douce-paresse-kit' ),
			'mp'   => __( 'Medium +', 'douce-paresse-kit' ),
			'l'    => __( 'Large', 'douce-paresse-kit' ),
		);
	}

	/** Defaults are overridable by child widgets. */
	protected function split_defaults() {
		return array(
			'image'      => 'maison-principal.webp',
			'image_pos'  => '',
			'image_side' => 'left',
			'image_size' => 'wide',
			'ratio'      => '5/4',
			'image_max'  => '',
			'align'      => 'center',
			'pt'         => 'none',
			'pb'         => 'm',
			'marker'     => 'none',
			'marker_txt' => '',
			'title'      => '',
			'text_max'   => 520,
			'paragraphs' => array(
				array( 'text' => 'Douce Paresse est née d’une envie que l’on peut tous ressentir à un moment : ralentir, prendre du temps pour soi et s’accorder simplement une pause.', 'style' => 'body' ),
				array( 'text' => 'Et si, parfois, il suffisait de s’autoriser ce moment ?', 'style' => 'quote' ),
				array( 'text' => 'J’ai voulu donner une forme à cette envie et, surtout, pouvoir la partager. J’ai toujours aimé ces petits gestes qui témoignent du soin que l’on porte aux autres.', 'style' => 'body' ),
				array( 'text' => 'Je voulais une création simple, mais raffinée. De belles matières, des finitions soignées et ce souci du détail qui peut rendre un instant ordinaire un peu plus précieux.', 'style' => 'body' ),
				array( 'text' => 'C’est ainsi qu’est née Douce Paresse.', 'style' => 'closing-plain' ),
			),
		);
	}

	protected function register_split_controls() {
		$d = $this->split_defaults();

		$this->start_controls_section( 'image_section', array( 'label' => __( 'Image', 'douce-paresse-kit' ) ) );
		$this->add_control( 'image', array( 'label' => __( 'Image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( $d['image'] ) ) );
		$this->add_control( 'image_pos', array( 'label' => __( 'Image focus (object-position)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => $d['image_pos'], 'placeholder' => '50% 50%' ) );
		$this->add_control(
			'image_side',
			array(
				'label'   => __( 'Image side', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left'  => array( 'title' => __( 'Left', 'douce-paresse-kit' ), 'icon' => 'eicon-h-align-left' ),
					'right' => array( 'title' => __( 'Right', 'douce-paresse-kit' ), 'icon' => 'eicon-h-align-right' ),
				),
				'default' => $d['image_side'],
			)
		);
		$this->add_control(
			'image_size',
			array(
				'label'   => __( 'Image column', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'wide'  => __( 'Wider than text', 'douce-paresse-kit' ),
					'equal' => __( 'Same as text', 'douce-paresse-kit' ),
				),
				'default' => $d['image_size'],
			)
		);
		$this->add_control(
			'ratio',
			array(
				'label'   => __( 'Aspect ratio', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'5/4' => '5:4',
					'4/3' => '4:3',
					'1/1' => '1:1',
					'4/5' => '4:5',
					'3/4' => '3:4',
				),
				'default' => $d['ratio'],
			)
		);
		$this->add_control( 'image_max', array( 'label' => __( 'Image max width (px)', 'douce-paresse-kit' ), 'type' => Controls_Manager::NUMBER, 'default' => $d['image_max'] ) );
		$this->add_control(
			'align',
			array(
				'label'   => __( 'Image alignment in its column', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'flex-start' => __( 'Start', 'douce-paresse-kit' ),
					'center'     => __( 'Center', 'douce-paresse-kit' ),
					'flex-end'   => __( 'End', 'douce-paresse-kit' ),
				),
				'default' => $d['align'],
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'text_section', array( 'label' => __( 'Text', 'douce-paresse-kit' ) ) );
		$this->add_control(
			'marker',
			array(
				'label'   => __( 'Top marker', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'none'    => __( 'None', 'douce-paresse-kit' ),
					'line'    => __( 'Gold line', 'douce-paresse-kit' ),
					'numeral' => __( 'Numeral + line', 'douce-paresse-kit' ),
					'eyebrow' => __( 'Small label', 'douce-paresse-kit' ),
				),
				'default' => $d['marker'],
			)
		);
		$this->add_control(
			'marker_text',
			array(
				'label'     => __( 'Marker text', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => $d['marker_txt'],
				'condition' => array( 'marker' => array( 'numeral', 'eyebrow' ) ),
			)
		);
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => $d['title'] ) );
		$this->add_paragraphs_control( 'paragraphs', __( 'Paragraphs', 'douce-paresse-kit' ), $d['paragraphs'] );
		$this->add_control( 'text_max', array( 'label' => __( 'Text max width (px)', 'douce-paresse-kit' ), 'type' => Controls_Manager::NUMBER, 'default' => $d['text_max'] ) );
		$this->end_controls_section();

		$this->start_controls_section( 'spacing_section', array( 'label' => __( 'Spacing', 'douce-paresse-kit' ) ) );
		$this->add_control( 'pt', array( 'label' => __( 'Space above', 'douce-paresse-kit' ), 'type' => Controls_Manager::SELECT, 'options' => $this->spacing_options(), 'default' => $d['pt'] ) );
		$this->add_control( 'pb', array( 'label' => __( 'Space below', 'douce-paresse-kit' ), 'type' => Controls_Manager::SELECT, 'options' => $this->spacing_options(), 'default' => $d['pb'] ) );
		$this->end_controls_section();
	}

	protected function register_split_styles() {
		$this->style_box( '.dpk-split' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-split__title' );
		$this->style_text( 'body', __( 'Body text', 'douce-paresse-kit' ), '.dpk-split .dpk-p--body, .dpk-split .dpk-p--body-bordered' );
		$this->style_text( 'quote', __( 'Quotes & closing lines', 'douce-paresse-kit' ), '.dpk-split .dpk-p--quote, .dpk-split .dpk-p--closing, .dpk-split .dpk-p--closing-plain' );
		$this->style_accents( '.dpk-split .dpk-marker' );
	}

	protected function register_controls() {
		$this->register_split_controls();
		$this->register_split_styles();
	}

	protected function section_classes( $s, $base ) {
		$sp = array_keys( $this->spacing_options() );
		$pt = in_array( $s['pt'], $sp, true ) ? $s['pt'] : 'none';
		$pb = in_array( $s['pb'], $sp, true ) ? $s['pb'] : 'none';
		return 'dpk ' . $base . ' dpk-pt-' . $pt . ' dpk-pb-' . $pb;
	}

	protected function render_marker( $s ) {
		switch ( $s['marker'] ) {
			case 'line':
				echo '<span class="dpk-line" aria-hidden="true"></span>';
				break;
			case 'numeral':
				echo '<div class="dpk-marker"><span>' . esc_html( $s['marker_text'] ) . '</span><span class="dpk-rule" aria-hidden="true"></span></div>';
				break;
			case 'eyebrow':
				echo '<span class="dpk-marker">' . esc_html( $s['marker_text'] ) . '</span>';
				break;
		}
	}

	protected function render_split_row( $s ) {
		$ratios = array( '5/4', '4/3', '1/1', '4/5', '3/4' );
		$ratio  = in_array( $s['ratio'], $ratios, true ) ? $s['ratio'] : '5/4';
		$align  = in_array( $s['align'], array( 'flex-start', 'center', 'flex-end' ), true ) ? $s['align'] : 'center';
		$vars   = '--dpk-ratio:' . $ratio . ';--dpk-align:' . $align . ';';
		if ( ! empty( $s['image_max'] ) ) {
			$vars .= '--dpk-img-max:' . absint( $s['image_max'] ) . 'px;';
		}
		if ( ! empty( $s['text_max'] ) ) {
			$vars .= '--dpk-text-max:' . absint( $s['text_max'] ) . 'px;';
		}
		$row_cls = 'dpk-split__row dpk-split__row--' . ( 'equal' === $s['image_size'] ? 'equal' : 'wide' );

		ob_start();
		?>
		<div class="dpk-split__media">
			<?php echo $this->img( $s['image'], 'dpk-split__img', '', false, $this->pos( $s['image_pos'] ) ); // phpcs:ignore ?>
		</div>
		<?php
		$media = ob_get_clean();
		ob_start();
		?>
		<div class="dpk-split__text">
			<?php $this->render_marker( $s ); ?>
			<?php if ( $s['title'] ) : ?>
				<h2 class="dpk-split__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
			<?php endif; ?>
			<?php $this->render_paragraphs( $s['paragraphs'] ); ?>
		</div>
		<?php
		$text = ob_get_clean();

		echo '<div class="' . esc_attr( $row_cls ) . '" style="' . esc_attr( $vars ) . '">';
		echo 'right' === $s['image_side'] ? $text . $media : $media . $text; // phpcs:ignore
		echo '</div>';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<section class="' . esc_attr( $this->section_classes( $s, 'dpk-split' ) ) . '">';
		$this->render_split_row( $s );
		echo '</section>';
	}
}
