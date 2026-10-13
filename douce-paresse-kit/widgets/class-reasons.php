<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Le Coffret Luxury — "Pourquoi choisir le Coffret Luxury ?" (three reasons + button).
 */
class Reasons extends Base {

	public function get_name() {
		return 'dpk-reasons';
	}

	public function get_title() {
		return __( 'DP Reasons', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-columns';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Pourquoi choisir le Coffret Luxury ?' ) );
		$rep = new Repeater();
		$rep->add_control( 'num', array( 'label' => __( 'Number', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'label_block' => true ) );
		$rep->add_control( 'desc', array( 'label' => __( 'Text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA ) );
		$this->add_control(
			'items',
			array(
				'label'       => __( 'Reasons', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ num }}} — {{{ title }}}',
				'default'     => array(
					array( 'num' => 'I', 'title' => 'Valoriser l’expérience en chambre', 'desc' => 'Offrir à vos hôtes une attention singulière qui prolonge le geste d’accueil.' ),
					array( 'num' => 'II', 'title' => 'Différencier votre accueil', 'desc' => 'Remplacer le plateau de courtoisie standardisé par un ensemble cohérent, élégant et identifiable.' ),
					array( 'num' => 'III', 'title' => 'Créer un souvenir', 'desc' => 'Transformer un moment quotidien en une parenthèse plus personnelle, pensée pour rester en mémoire.' ),
				),
			)
		);
		$this->add_control( 'button_text', array( 'label' => __( 'Button text', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Demander l’offre professionnelle' ) );
		$this->add_control( 'button_link', array( 'label' => __( 'Button link', 'douce-paresse-kit' ), 'type' => Controls_Manager::URL, 'default' => $this->link_default( '#' ) ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-reasons' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-reasons__title' );
		$this->style_text( 'item_title', __( 'Reason titles', 'douce-paresse-kit' ), '.dpk-reasons__item-title' );
		$this->style_text( 'item_text', __( 'Reason text', 'douce-paresse-kit' ), '.dpk-reasons__item .dpk-p' );
		$this->style_button( 'button', __( 'Button', 'douce-paresse-kit' ), '.dpk-reasons .dpk-btn' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-reasons">
			<h2 class="dpk-reasons__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
			<div class="dpk-reasons__grid">
				<?php foreach ( (array) $s['items'] as $item ) : ?>
					<div class="dpk-reasons__item">
						<span class="dpk-reasons__num"><?php echo esc_html( $item['num'] ); ?></span>
						<h3 class="dpk-reasons__item-title"><?php echo esc_html( $item['title'] ); ?></h3>
						<p class="dpk-p dpk-p--body"><?php echo $this->text( $item['desc'] ); // phpcs:ignore ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $s['button_text'] ) : ?>
				<div class="dpk-reasons__cta"><a class="dpk-btn dpk-btn--solid"<?php echo $this->link_attrs( $s['button_link'] ); // phpcs:ignore ?>><?php echo esc_html( $s['button_text'] ); ?> <span aria-hidden="true">→</span></a></div>
			<?php endif; ?>
		</section>
		<?php
	}
}
