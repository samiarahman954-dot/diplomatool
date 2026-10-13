<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * La Maison — "L'hôtellerie est alors devenue une évidence" (two images, sand background).
 */
class Hospitality extends Base {

	public function get_name() {
		return 'dpk-hospitality';
	}

	public function get_title() {
		return __( 'DP Hospitality', 'douce-paresse-kit' );
	}

	public function get_icon() {
		return 'eicon-gallery-masonry';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'douce-paresse-kit' ) ) );
		$this->add_control( 'lead', array( 'label' => __( 'Lead', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => 'L’hôtellerie est alors devenue une évidence.' ) );
		$this->add_paragraphs_control(
			'paragraphs',
			__( 'Paragraphs', 'douce-paresse-kit' ),
			array(
				array( 'text' => 'Un lieu où l’accueil se construit aussi à travers les détails laissés à l’hôte et les instants qu’il peut s’accorder au cours de son séjour.', 'style' => 'body' ),
				array( 'text' => 'Douce Paresse imagine des objets d’hospitalité qui donnent forme à cette vision et permettent aux établissements de la partager avec leurs voyageurs.', 'style' => 'body' ),
			)
		);
		$this->add_control( 'image_small', array( 'label' => __( 'Small image (under the text)', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'maison-add.webp' ) ) );
		$this->add_control( 'image_large', array( 'label' => __( 'Large image', 'douce-paresse-kit' ), 'type' => Controls_Manager::MEDIA, 'default' => $this->media_default( 'maison-ambiance.webp' ) ) );
		$this->end_controls_section();

		$this->style_box( '.dpk-hosp' );
		$this->style_text( 'lead', __( 'Lead', 'douce-paresse-kit' ), '.dpk-hosp__lead' );
		$this->style_text( 'body', __( 'Body text', 'douce-paresse-kit' ), '.dpk-hosp .dpk-p' );
		$this->style_accents();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		?>
		<section class="dpk dpk-hosp">
			<div class="dpk-hosp__inner">
				<div class="dpk-hosp__text">
					<span class="dpk-line" aria-hidden="true"></span>
					<?php if ( $s['lead'] ) : ?>
						<p class="dpk-hosp__lead"><?php echo $this->text( $s['lead'] ); // phpcs:ignore ?></p>
					<?php endif; ?>
					<?php $this->render_paragraphs( $s['paragraphs'] ); ?>
					<?php echo $this->img( $s['image_small'], 'dpk-hosp__small' ); // phpcs:ignore ?>
				</div>
				<div class="dpk-hosp__media">
					<?php echo $this->img( $s['image_large'], 'dpk-hosp__large' ); // phpcs:ignore ?>
				</div>
			</div>
		</section>
		<?php
	}
}
