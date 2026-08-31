<?php
/**
 * Field: tabs (radio button group)
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$choices = isset( $field['choices'] ) ? (array) $field['choices']
	: ( isset( $field['options'] ) ? (array) $field['options'] : array() );
$current = (string) $value;
?>
<div class="tm-button-groups">
	<?php foreach ( $choices as $key => $label ): ?>
		<label class="tm-button-radio">
			<input
				type="radio"
				name="<?php echo esc_attr( $id ); ?>"
				value="<?php echo esc_attr( $key ); ?>"
				class="<?php echo esc_attr( $id ); ?>-tab"
				<?php checked( $current, (string) $key ); ?>
			/>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
	<?php endforeach; ?>
</div>
