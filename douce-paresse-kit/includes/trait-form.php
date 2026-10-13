<?php
namespace DPK\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Form controls + markup shared by the "Professional request" and "Contact" widgets.
 * Submissions are posted to admin-ajax (see DPK\Forms), e-mailed and stored in WordPress.
 */
trait Form_Trait {

	protected function form_field_types() {
		return array(
			'text'     => __( 'Text', 'douce-paresse-kit' ),
			'email'    => __( 'E-mail', 'douce-paresse-kit' ),
			'tel'      => __( 'Phone', 'douce-paresse-kit' ),
			'number'   => __( 'Number', 'douce-paresse-kit' ),
			'select'   => __( 'Select', 'douce-paresse-kit' ),
			'textarea' => __( 'Textarea', 'douce-paresse-kit' ),
		);
	}

	/**
	 * @param array $default_fields Default repeater rows.
	 * @param array $defaults       Default texts (privacy, note, submit, success...).
	 */
	protected function register_form_controls( $default_fields, $defaults ) {
		$this->start_controls_section( 'form_section', array( 'label' => __( 'Form', 'douce-paresse-kit' ) ) );

		$rep = new Repeater();
		$rep->add_control( 'label', array( 'label' => __( 'Label', 'douce-paresse-kit' ), 'type' => Controls_Manager::TEXT ) );
		$rep->add_control(
			'name',
			array(
				'label'       => __( 'Field key', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Lowercase, no spaces (e.g. email).', 'douce-paresse-kit' ),
			)
		);
		$rep->add_control(
			'type',
			array(
				'label'   => __( 'Type', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->form_field_types(),
				'default' => 'text',
			)
		);
		$rep->add_control(
			'required',
			array(
				'label'        => __( 'Required', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$rep->add_control(
			'full',
			array(
				'label'        => __( 'Full width', 'douce-paresse-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$rep->add_control(
			'options',
			array(
				'label'       => __( 'Options (one per line)', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXTAREA,
				'condition'   => array( 'type' => 'select' ),
			)
		);
		$rep->add_control(
			'placeholder',
			array(
				'label' => __( 'Placeholder', 'douce-paresse-kit' ),
				'type'  => Controls_Manager::TEXT,
			)
		);
		$rep->add_control(
			'auto',
			array(
				'label'   => __( 'Autocomplete', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'off',
			)
		);
		$this->add_control(
			'fields',
			array(
				'label'       => __( 'Fields', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'default'     => $default_fields,
				'title_field' => '{{{ label }}}',
			)
		);

		$this->add_control(
			'optional_text',
			array(
				'label'   => __( 'Optional hint', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['optional_text'],
			)
		);
		$this->add_control(
			'privacy_text',
			array(
				'label'   => __( 'Privacy notice (HTML allowed)', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => $defaults['privacy_text'],
			)
		);
		$this->add_control(
			'required_note',
			array(
				'label'   => __( 'Required fields note', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['required_note'],
			)
		);
		$this->add_control(
			'submit_text',
			array(
				'label'   => __( 'Button text', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['submit_text'],
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'form_success_section', array( 'label' => __( 'After sending', 'douce-paresse-kit' ) ) );
		if ( ! empty( $defaults['success_icon'] ) ) {
			$this->add_control(
				'success_icon',
				array(
					'label'   => __( 'Icon', 'douce-paresse-kit' ),
					'type'    => Controls_Manager::MEDIA,
					'default' => $this->media_default( $defaults['success_icon'] ),
				)
			);
		}
		$this->add_control(
			'success_title',
			array(
				'label'   => __( 'Title', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => $defaults['success_title'],
			)
		);
		$this->add_control(
			'success_text',
			array(
				'label'   => __( 'Text', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => $defaults['success_text'],
			)
		);
		$this->add_control(
			'reset_text',
			array(
				'label'   => __( 'New message button', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['reset_text'],
			)
		);
		$this->add_control(
			'recipient',
			array(
				'label'       => __( 'Send to (e-mail)', 'douce-paresse-kit' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => get_option( 'admin_email' ),
				'separator'   => 'before',
				'description' => __( 'Empty = site admin e-mail. Every submission is also saved under Douce Paresse → Messages.', 'douce-paresse-kit' ),
			)
		);
		$this->add_control(
			'email_subject',
			array(
				'label'   => __( 'E-mail subject', 'douce-paresse-kit' ),
				'type'    => Controls_Manager::TEXT,
				'default' => $defaults['email_subject'],
			)
		);
		$this->end_controls_section();
	}

	protected function form_script_depends() {
		return array( 'dpk-frontend' ); // Turnstile's api.js is injected by frontend.js only when a form is on the page.
	}

	protected function form_post_id() {
		$doc = \Elementor\Plugin::$instance->documents->get_current();
		return $doc ? (int) $doc->get_main_id() : (int) get_the_ID();
	}

	protected function render_form( $s, $success_scheme = 'light', $rows = 5, $field_min = 260 ) {
		$fields = is_array( $s['fields'] ) ? $s['fields'] : array();
		?>
		<div class="dpk-formwrap" data-dpk-formwrap>
			<form class="dpk-form" style="--dpk-field-min:<?php echo (int) $field_min; ?>px" novalidate data-dpk-form data-post="<?php echo esc_attr( $this->form_post_id() ); ?>" data-widget="<?php echo esc_attr( $this->get_id() ); ?>">
				<div class="dpk-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Laissez ce champ vide', 'douce-paresse-kit' ); ?>
						<input type="text" name="dpk_hp" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>
				<input type="hidden" name="dpk_token" value="">
				<?php
				foreach ( $fields as $f ) :
					$f    = wp_parse_args(
						(array) $f,
						array( 'label' => '', 'name' => '', 'type' => 'text', 'required' => '', 'full' => '', 'options' => '', 'placeholder' => '', 'auto' => 'off', '_id' => '' )
					);
					$name = sanitize_key( $f['name'] ? $f['name'] : $f['_id'] );
					$type = isset( $f['type'] ) ? $f['type'] : 'text';
					$req  = 'yes' === $f['required'];
					$full = 'yes' === $f['full'] || 'textarea' === $type;
					$cls  = 'dpk-field' . ( $full ? ' dpk-field--full' : '' );
					$attr = ' name="' . esc_attr( $name ) . '"' . ( $req ? ' data-required="1"' : '' ) . ( 'select' === $type ? '' : ' maxlength="' . ( 'textarea' === $type ? \DPK\Forms::MAX_AREA : \DPK\Forms::MAX_TEXT ) . '"' );
					?>
					<label class="<?php echo esc_attr( $cls ); ?>">
						<span class="dpk-field__label"><?php echo esc_html( $f['label'] ); ?>
							<?php if ( ! $req && ! empty( $s['optional_text'] ) ) : ?>
								<span class="dpk-field__hint"><?php echo esc_html( $s['optional_text'] ); ?></span>
							<?php endif; ?>
						</span>
						<?php if ( 'textarea' === $type ) : ?>
							<textarea class="dpk-field__input dpk-field__input--area" rows="<?php echo (int) $rows; ?>"<?php echo $attr; // phpcs:ignore ?> placeholder="<?php echo esc_attr( $f['placeholder'] ); ?>"></textarea>
						<?php elseif ( 'select' === $type ) : ?>
							<span class="dpk-field__select">
								<select class="dpk-field__input"<?php echo $attr; // phpcs:ignore ?>>
									<option value="" disabled selected><?php echo esc_html( $f['placeholder'] ); ?></option>
									<?php foreach ( $this->lines( $f['options'] ) as $opt ) : ?>
										<option value="<?php echo esc_attr( $opt[0] ); ?>"><?php echo esc_html( $opt[0] ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="dpk-field__caret" aria-hidden="true">▾</span>
							</span>
						<?php else : ?>
							<input class="dpk-field__input" type="<?php echo esc_attr( $type ); ?>"<?php echo $attr; // phpcs:ignore ?> autocomplete="<?php echo esc_attr( $f['auto'] ? $f['auto'] : 'off' ); ?>" placeholder="<?php echo esc_attr( $f['placeholder'] ); ?>">
						<?php endif; ?>
						<span class="dpk-field__error" aria-live="polite"></span>
					</label>
				<?php endforeach; ?>

				<?php if ( \DPK\Spam::turnstile_enabled() ) : ?>
					<div class="dpk-turnstile" data-dpk-turnstile></div>
				<?php endif; ?>
				<?php if ( ! empty( $s['privacy_text'] ) ) : ?>
					<p class="dpk-form__privacy"><?php echo wp_kses_post( $s['privacy_text'] ); ?></p>
				<?php endif; ?>
				<div class="dpk-form__footer<?php echo empty( $s['required_note'] ) ? ' dpk-form__footer--end' : ''; ?>">
					<?php if ( ! empty( $s['required_note'] ) ) : ?>
						<span class="dpk-form__note"><?php echo esc_html( $s['required_note'] ); ?></span>
					<?php endif; ?>
					<button type="submit" class="dpk-btn dpk-btn--solid dpk-form__submit"><?php echo esc_html( $s['submit_text'] ); ?> <span aria-hidden="true">→</span></button>
				</div>
				<p class="dpk-form__status" role="alert"></p>
			</form>

			<div class="dpk-success dpk-success--<?php echo esc_attr( $success_scheme ); ?>" role="status" data-dpk-success hidden>
				<?php if ( ! empty( $s['success_icon']['url'] ) ) : ?>
					<span class="dpk-success__icon"><?php echo $this->img( $s['success_icon'] ); // phpcs:ignore ?></span>
				<?php endif; ?>
				<p class="dpk-success__title"><?php echo $this->text( $s['success_title'] ); // phpcs:ignore ?></p>
				<p class="dpk-success__text"><?php echo $this->text( $s['success_text'] ); // phpcs:ignore ?></p>
				<button type="button" class="dpk-success__reset" data-dpk-form-reset><?php echo esc_html( $s['reset_text'] ); ?></button>
			</div>
		</div>
		<?php
	}

	protected function style_form() {
		$this->style_text( 'form_label', __( 'Form labels', 'douce-paresse-kit' ), '.dpk-field__label' );
		$this->start_controls_section(
			'form_inputs_style',
			array(
				'label' => __( 'Form inputs', 'douce-paresse-kit' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'form_input_color',
			array(
				'label'     => __( 'Text color', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dpk-field__input' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'form_input_line',
			array(
				'label'     => __( 'Underline color', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dpk-field__input' => 'border-bottom-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'form_input_focus',
			array(
				'label'     => __( 'Focus underline color', 'douce-paresse-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .dpk-field__input:focus' => 'border-bottom-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
		$this->style_button( 'form_button', __( 'Form button', 'douce-paresse-kit' ), '.dpk-form__submit' );
	}
}
