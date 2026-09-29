<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;

class JMK_Widget_Hero extends JMK_Widget_Base {

	public function get_name() {
		return 'jmk-hero';
	}

	public function get_title() {
		return __( 'JM Hero + Quote Form', 'jmk' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Hero content', 'jmk' ) ) );
		$this->add_control(
			'eyebrow',
			array(
				'label'       => __( 'Eyebrow', 'jmk' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Grand Prairie, TX · Movers Near You',
				'label_block' => true,
			)
		);
		$this->add_control(
			'title_before',
			array(
				'label'   => __( 'Title – before highlight', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'default' => "Grand Prairie's Trusted",
			)
		);
		$this->add_control(
			'title_hl',
			array(
				'label'   => __( 'Title – highlighted (yellow)', 'jmk' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'Moving Company',
			)
		);
		$this->add_control(
			'title_after',
			array(
				'label'   => __( 'Title – after highlight', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'rows'    => 2,
				'default' => '— Movers Near You, Ready Today',
			)
		);
		$this->add_control(
			'lede',
			array(
				'label'   => __( 'Intro text', 'jmk' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => 'Licensed & insured movers serving Grand Prairie, Dallas, Fort Worth, and all of DFW within a 50-mile radius. Free quotes in minutes.',
			)
		);
		$this->add_button_controls( 'btn1', __( 'Primary button', 'jmk' ), 'Get your free quote ➤', '/quote/', 'y' );
		$this->add_button_controls( 'btn2', __( 'Secondary button', 'jmk' ), '☎ Call (972) 638-7479', 'tel:+19726387479', 'ghost' );
		$this->end_controls_section();

		$this->start_controls_section( 'rating', array( 'label' => __( 'Rating line', 'jmk' ) ) );
		$this->add_control(
			'rating_show',
			array(
				'label'   => __( 'Show rating', 'jmk' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->add_control(
			'rating_value',
			array(
				'label'     => __( 'Rating', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '4.7 / 5',
				'condition' => array( 'rating_show' => 'yes' ),
			)
		);
		$this->add_control(
			'rating_note',
			array(
				'label'     => __( 'Rating note', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '· 218 Google reviews · Licensed & Insured',
				'condition' => array( 'rating_show' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'form', array( 'label' => __( 'Quote form', 'jmk' ) ) );
		$this->add_control(
			'form_show',
			array(
				'label'   => __( 'Show quote form', 'jmk' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$fields = array(
			'form_title'     => array( __( 'Form title', 'jmk' ), 'Get a free moving quote' ),
			'form_sub'       => array( __( 'Form subtitle', 'jmk' ), "Under a minute. No obligation. We'll text you a real price." ),
			'lbl_name'       => array( __( 'Name label', 'jmk' ), 'Full name' ),
			'ph_name'        => array( __( 'Name placeholder', 'jmk' ), 'Your name' ),
			'lbl_phone'      => array( __( 'Phone label', 'jmk' ), 'Phone' ),
			'ph_phone'       => array( __( 'Phone placeholder', 'jmk' ), '(000) 000-0000' ),
			'lbl_from'       => array( __( 'From label', 'jmk' ), 'From (ZIP)' ),
			'ph_from'        => array( __( 'From placeholder', 'jmk' ), '75052' ),
			'lbl_to'         => array( __( 'To label', 'jmk' ), 'To (ZIP)' ),
			'ph_to'          => array( __( 'To placeholder', 'jmk' ), '76010' ),
			'lbl_size'       => array( __( 'Size label', 'jmk' ), 'Move size' ),
			'ph_size'        => array( __( 'Size placeholder', 'jmk' ), 'Select home size…' ),
			'form_btn'       => array( __( 'Submit text', 'jmk' ), 'Get my free quote ➤' ),
		);
		foreach ( $fields as $key => $f ) {
			$this->add_control(
				$key,
				array(
					'label'       => $f[0],
					'type'        => Controls_Manager::TEXT,
					'default'     => $f[1],
					'label_block' => true,
					'condition'   => array( 'form_show' => 'yes' ),
				)
			);
		}
		$this->add_control(
			'sizes',
			array(
				'label'       => __( 'Move size options (one per line)', 'jmk' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'default'     => "Studio / 1 bedroom\n2 bedrooms\n3 bedrooms\n4+ bedrooms\nOffice / Commercial\nJust a few items / Labor only",
				'condition'   => array( 'form_show' => 'yes' ),
			)
		);
		$this->add_control(
			'form_fine',
			array(
				'label'       => __( 'Fine print (HTML allowed: b, a, br)', 'jmk' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => '&#128274; We never share your info. Prefer the full tool? <a href="/quote/">Detailed quote builder</a>.',
				'condition'   => array( 'form_show' => 'yes' ),
			)
		);
		$this->add_control(
			'ok_title',
			array(
				'label'     => __( 'Success title', 'jmk' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => "You're all set!",
				'separator' => 'before',
				'condition' => array( 'form_show' => 'yes' ),
			)
		);
		$this->add_control(
			'ok_text',
			array(
				'label'     => __( 'Success text (HTML allowed: b, a, br)', 'jmk' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => 'Thanks — a Just Move DFW coordinator will text your free quote shortly.<br><br>Need to move fast? Call <a href="tel:+19726387479"><b>(972) 638-7479</b></a>.',
				'condition' => array( 'form_show' => 'yes' ),
			)
		);
		$this->add_control(
			'redirect',
			array(
				'label'       => __( 'Redirect after submit (optional)', 'jmk' ),
				'type'        => Controls_Manager::URL,
				'description' => __( 'Leave empty to show the success message.', 'jmk' ),
				'condition'   => array( 'form_show' => 'yes' ),
			)
		);
		$this->add_control(
			'form_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Submissions are saved under <b>Just Move Kit → Leads</b> and emailed to the address set on that screen.', 'jmk' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'form_show' => 'yes' ),
			)
		);
		$this->end_controls_section();

		$this->add_brand_style_controls();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$uid  = 'jmk' . $this->get_id();
		$form = 'yes' === $s['form_show'];
		?>
		<div class="jmk jmk-hero"><section class="jmk-sec"><div class="jmk-wrap">
			<div class="jmk-hero-split<?php echo $form ? '' : ' no-form'; ?>">
				<div>
					<?php if ( '' !== $s['eyebrow'] ) : ?>
						<span class="jmk-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
					<?php endif; ?>
					<h1><?php echo esc_html( $s['title_before'] ); ?>
						<?php if ( '' !== $s['title_hl'] ) : ?>
							<span class="hl"><?php echo esc_html( $s['title_hl'] ); ?></span>
						<?php endif; ?>
						<?php echo esc_html( $s['title_after'] ); ?></h1>
					<?php if ( '' !== $s['lede'] ) : ?>
						<p class="jmk-lede"><?php echo esc_html( $s['lede'] ); ?></p>
					<?php endif; ?>
					<div class="jmk-cta-row">
						<?php
						$this->render_button( $s, 'btn1' );
						$this->render_button( $s, 'btn2' );
						?>
					</div>
					<?php if ( 'yes' === $s['rating_show'] ) : ?>
						<div class="jmk-rating"><span class="jmk-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
							<?php echo esc_html( $s['rating_value'] ); ?>
							<small><?php echo esc_html( $s['rating_note'] ); ?></small></div>
					<?php endif; ?>
				</div>

				<?php if ( $form ) : ?>
				<form class="jmk-qform" data-jmk-form data-source="home-hero-form"
					<?php if ( ! empty( $s['redirect']['url'] ) ) : ?>
						data-redirect="<?php echo esc_url( $s['redirect']['url'] ); ?>"
					<?php endif; ?>
					novalidate>
					<div class="fields">
						<h3><?php echo esc_html( $s['form_title'] ); ?></h3>
						<p class="sub"><?php echo esc_html( $s['form_sub'] ); ?></p>
						<label for="<?php echo esc_attr( $uid ); ?>-n"><?php echo esc_html( $s['lbl_name'] ); ?></label>
						<input id="<?php echo esc_attr( $uid ); ?>-n" name="name" type="text" required placeholder="<?php echo esc_attr( $s['ph_name'] ); ?>" autocomplete="name">
						<label for="<?php echo esc_attr( $uid ); ?>-p"><?php echo esc_html( $s['lbl_phone'] ); ?></label>
						<input id="<?php echo esc_attr( $uid ); ?>-p" name="phone" type="tel" required placeholder="<?php echo esc_attr( $s['ph_phone'] ); ?>" autocomplete="tel">
						<div class="row2">
							<div><label for="<?php echo esc_attr( $uid ); ?>-f"><?php echo esc_html( $s['lbl_from'] ); ?></label>
								<input id="<?php echo esc_attr( $uid ); ?>-f" name="from_zip" type="text" inputmode="numeric" placeholder="<?php echo esc_attr( $s['ph_from'] ); ?>"></div>
							<div><label for="<?php echo esc_attr( $uid ); ?>-t"><?php echo esc_html( $s['lbl_to'] ); ?></label>
								<input id="<?php echo esc_attr( $uid ); ?>-t" name="to_zip" type="text" inputmode="numeric" placeholder="<?php echo esc_attr( $s['ph_to'] ); ?>"></div>
						</div>
						<label for="<?php echo esc_attr( $uid ); ?>-s"><?php echo esc_html( $s['lbl_size'] ); ?></label>
						<select id="<?php echo esc_attr( $uid ); ?>-s" name="size">
							<option value=""><?php echo esc_html( $s['ph_size'] ); ?></option>
							<?php
							foreach ( preg_split( '/\r\n|\r|\n/', (string) $s['sizes'] ) as $opt ) {
								$opt = trim( $opt );
								if ( '' !== $opt ) {
									echo '<option>' . esc_html( $opt ) . '</option>';
								}
							}
							?>
						</select>
						<div class="jmk-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
						<button class="jmk-btn jmk-btn-y" type="submit"><?php echo esc_html( $s['form_btn'] ); ?></button>
						<p class="err" role="alert"></p>
						<p class="fine"><?php echo self::inline_kses( $s['form_fine'] ); // phpcs:ignore ?></p>
					</div>
					<div class="ok" role="status"><h3><?php echo esc_html( $s['ok_title'] ); ?></h3>
						<p><?php echo self::inline_kses( $s['ok_text'] ); // phpcs:ignore ?></p></div>
				</form>
				<?php endif; ?>
			</div>
		</div></section></div>
		<?php
	}
}
