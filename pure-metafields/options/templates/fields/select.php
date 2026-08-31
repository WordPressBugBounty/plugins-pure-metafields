<?php
/**
 * Field: select
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$options  = isset( $field['options'] )  ? (array) $field['options'] : array();
$multiple = ! empty( $field['multiple'] );
$current  = $multiple ? (array) $value : (string) $value;
$name     = $multiple ? $id . '[]' : $id;
?>
<select
	id="<?php echo esc_attr( $id ); ?>-select"
	class="tm-select-field select2 <?php echo esc_attr( $id ); ?>"
	name="<?php echo esc_attr( $name ); ?>"
	<?php echo $multiple ? 'multiple' : ''; ?>>
	<?php if ( ! $multiple ): ?>
		<option value=""><?php echo esc_html__( '— Select —', 'pure-metafields' ); ?></option>
	<?php endif; ?>
	<?php foreach ( $options as $opt_value => $opt_label ):
		$selected = $multiple
			? in_array( (string) $opt_value, array_map( 'strval', $current ), true )
			: ( (string) $current === (string) $opt_value );
	?>
		<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $selected ); ?>>
			<?php echo esc_html( $opt_label ); ?>
		</option>
	<?php endforeach; ?>
</select>
