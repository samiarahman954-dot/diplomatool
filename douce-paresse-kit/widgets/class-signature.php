<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Le Coffret Luxury — "La signature d'un accueil" (centered title, wide image, two text columns, second image).
 */
class Signature extends Base {

	public function get_name() {
		return 'dpk-signature';
	}

	public function get_title() {
		return __( 'DP Signature', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-featured-image';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control( 'numeral', array( 'label' => __( 'Numeral', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'II' ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'La signature d’un accueil' ) );
		$this->add_control( 'image', array( 'label' => __( 'Main image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'composition-inside.webp' ) ) );
		$this->add_control( 'lead', array( 'label' => __( 'Lead (left column)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 4, 'default' => 'Douce Paresse est née d’une conviction : la courtoisie française reste un art vivant, qui s’exprime dans les détails de l’accueil. Le Coffret Luxury en est la première expression.' ) );
		$this->add_control( 'body', array( 'label' => __( 'Text (right column)', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 5, 'default' => 'Pensé comme un ensemble, chaque élément participe à une même intention : des tasses en porcelaine aux cuillères délicatement gravées de la signature « l’art de ralentir », en passant par la sélection de thés, le sucre et la carte poétique.' ) );
		$this->add_control( 'closing', array( 'label' => __( 'Italic closing line', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'Autant d’attentions pensées pour sublimer les gestes les plus simples.' ) );
		$this->add_control( 'image_2', array( 'label' => __( 'Second image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'coffret-add.webp' ) ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-sign' );
		$this->style_text( 'title', __( 'Title', 'douce-paresse-kit' ), '.dpk-sign__title' );
		$this->style_text( 'lead', __( 'Lead', 'douce-paresse-kit' ), '.dpk-sign__lead' );
		$this->style_text( 'body', __( 'Body text', 'douce-paresse-kit' ), '.dpk-sign .dpk-p--body' );
		$this->style_text( 'closing', __( 'Closing line', 'douce-paresse-kit' ), '.dpk-sign .dpk-p--closing' );
		$this->style_accents( '.dpk-sign .dpk-marker' );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-sign">
			<div class="dpk-sign__inner">
				<div class="dpk-sign__head">
					<?php if ( $s['numeral'] ) : ?>
						<div class="dpk-marker"><span class="dpk-rule" aria-hidden="true"></span><span><?php echo esc_html( $s['numeral'] ); ?></span><span class="dpk-rule" aria-hidden="true"></span></div>
					<?php endif; ?>
					<h2 class="dpk-sign__title"><?php echo $this->text( $s['title'] ); // phpcs:ignore ?></h2>
				</div>
				<?php echo $this->img( $s['image'], 'dpk-sign__img' ); // phpcs:ignore ?>
				<div class="dpk-sign__cols">
					<p class="dpk-sign__lead"><?php echo $this->text( $s['lead'] ); // phpcs:ignore ?></p>
					<div class="dpk-sign__col">
						<?php if ( $s['body'] ) : ?>
							<p class="dpk-p dpk-p--body"><?php echo $this->text( $s['body'] ); // phpcs:ignore ?></p>
						<?php endif; ?>
						<?php if ( $s['closing'] ) : ?>
							<p class="dpk-p dpk-p--closing"><?php echo $this->text( $s['closing'] ); // phpcs:ignore ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php echo $this->img( $s['image_2'], 'dpk-sign__img2' ); // phpcs:ignore ?>
			</div>
		</section>
		<?php
	}
}
