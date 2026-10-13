<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Numbered legal articles (Mentions légales, Politique de confidentialité).
 */
class Legal extends Base {

	public function get_name() {
		return 'dpk-legal';
	}

	public function get_title() {
		return __( 'DP Legal Content', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Articles', 'douce-paresse-kit' ) ) );
		$rep = new Repeater();
		$rep->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$rep->add_control( 'content', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::WYSIWYG ) );
		$rep->add_control(
			'rows',
			array(
				'label'       => __( 'Information rows (Label | Value, one per line)', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
			)
		);
		$rep->add_control( 'content_after', array( 'label' => __( 'Text after the rows', 'douce-paresse-kit' ), 'type' => Controls_Manager::WYSIWYG ) );
		$this->add_control(
			'articles',
			array(
				'label'       => __( 'Articles', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ title }}}',
				'default'     => array(
					array(
						'title'   => 'Éditeur du site',
						'content' => '<p>Texte de l’article.</p>',
						'rows'    => "Adresse | 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.\nE-mail | <a href=\"mailto:contact@douceparesse.com\">contact@douceparesse.com</a>.",
					),
				),
			)
		);
		$this->add_control(
			'numbering',
			array(
				'label'        => __( 'Number the articles', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'see_also_section', array( 'label' => __( 'See also', 'douce-paresse-kit' ) ) );
		$this->add_control( 'see_label', array( 'label' => __( 'Label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Voir aussi' ) );
		$this->add_control( 'see_text', array( 'label' => __( 'Button text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Politique de confidentialité' ) );
		$this->add_control( 'see_link', array( 'label' => __( 'Button link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#' ) ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-legal' );
		$this->style_text( 'title', __( 'Article titles', 'douce-paresse-kit' ), '.dpk-legal__title' );
		$this->style_text( 'body', __( 'Text', 'douce-paresse-kit' ), '.dpk-legal__content' );
		$this->style_text( 'row_label', __( 'Row labels', 'douce-paresse-kit' ), '.dpk-legal__row-label' );
		$this->style_text( 'row_value', __( 'Row values', 'douce-paresse-kit' ), '.dpk-legal__row-value' );
		$this->style_button( 'button', __( 'Button', 'douce-paresse-kit' ), '.dpk-legal .dpk-btn' );
	}

	private function rows( $value ) {
		$rows = $this->lines( $value, 2 );
		if ( ! $rows ) {
			return;
		}
		echo '<div class="dpk-legal__rows">';
		foreach ( $rows as $r ) {
			echo '<div class="dpk-legal__row"><span class="dpk-legal__row-label">' . esc_html( $r[0] ) . '</span><span class="dpk-legal__row-value">' . $this->inline( $r[1] ) . '</span></div>'; // phpcs:ignore
		}
		echo '</div>';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-legal">
			<?php foreach ( (array) $s['articles'] as $i => $a ) : ?>
				<article class="dpk-legal__article">
					<div class="dpk-legal__head">
						<?php if ( 'yes' === $s['numbering'] ) : ?>
							<span class="dpk-legal__num"><?php echo esc_html( $i + 1 ); ?></span>
						<?php endif; ?>
						<h2 class="dpk-legal__title"><?php echo esc_html( $a['title'] ); ?></h2>
					</div>
					<?php if ( trim( wp_strip_all_tags( $a['content'] ) ) ) : ?>
						<div class="dpk-legal__content"><?php echo \DPK\Links::html( wp_kses_post( wpautop( $a['content'] ) ) ); // phpcs:ignore ?></div>
					<?php endif; ?>
					<?php $this->rows( $a['rows'] ); ?>
					<?php if ( trim( wp_strip_all_tags( $a['content_after'] ) ) ) : ?>
						<div class="dpk-legal__content"><?php echo \DPK\Links::html( wp_kses_post( wpautop( $a['content_after'] ) ) ); // phpcs:ignore ?></div>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
			<?php if ( $s['see_text'] ) : ?>
				<div class="dpk-legal__see">
					<span class="dpk-legal__see-label"><?php echo esc_html( $s['see_label'] ); ?></span>
					<a class="dpk-btn dpk-btn--outline"<?php echo $this->link_attrs( $s['see_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['see_text'] ); ?> <span aria-hidden="true">→</span></a>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
