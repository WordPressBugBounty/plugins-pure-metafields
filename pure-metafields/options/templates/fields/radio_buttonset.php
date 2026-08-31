<?php
/**
 * Field: radio_buttonset
 * Segmented button-group control — same visual as 'tabs' but with
 * Kirki-compatible 'choices' key and optional Dashicon support.
 *
 * Data structure (Kirki-compatible):
 *   'choices' => [ 'red' => 'Red', 'green' => 'Green', 'blue' => 'Blue' ]
 *
 * Icon+label variant:
 *   'choices' => [ 'left' => [ 'icon' => 'dashicons-align-left', 'text' => 'Left' ], ... ]
 *
 * Also accepts 'options' key for backwards compatibility.
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Accept both 'choices' (Kirki) and 'options' (framework) keys.
$choices = ! empty( $field['choices'] ) ? (array) $field['choices']
	: ( ! empty( $field['options'] ) ? (array) $field['options'] : array() );

$current = '' !== (string) $value ? (string) $value
	: ( isset( $field['default'] ) ? (string) $field['default'] : '' );
?>
<div class="tm-button-groups tm-rbs-group" id="tmrbs-<?php echo esc_attr( $id ); ?>">
	<?php foreach ( $choices as $key => $label ) :
		$is_icon  = is_array( $label );
		$text     = $is_icon ? ( $label['text'] ?? (string) $key ) : (string) $label;
		$icon     = $is_icon && ! empty( $label['icon'] ) ? $label['icon'] : '';
		$checked  = $current === (string) $key;
	?>
		<label class="tm-button-radio">
			<input
				type="radio"
				name="<?php echo esc_attr( $id ); ?>"
				value="<?php echo esc_attr( $key ); ?>"
				class="<?php echo esc_attr( $id ); ?>-rbs"
				<?php checked( $checked ); ?>
			/>
			<?php if ( $icon ) : ?>
				<span class="dashicons <?php echo esc_attr( $icon ); ?> tm-rbs-icon"></span>
			<?php endif; ?>
			<span><?php echo esc_html( $text ); ?></span>
		</label>
	<?php endforeach; ?>
</div>
