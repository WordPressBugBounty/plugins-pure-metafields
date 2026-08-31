<?php
/**
 * Section Heading — pseudo-field injected by the Metafield Builder loader.
 * Renders a full-width section title with a separator line.
 * The outer tm-field-row wrapper is handled by group.php; CSS makes it span all columns.
 *
 * Variables below are populated at runtime via extract() by the field
 * renderer — declared here for static analysis (Intelephense).
 *
 * @var string $id
 * @var string $post_type
 * @var string $placeholder
 * @var string $default
 * @var string $bind
 * @var mixed  $row_db_value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// $label is extracted from the field array by tpmeta_load_template.
$section_title = isset( $label ) ? $label : '';
?>
<div class="tpmf-section-heading">
	<?php if ( $section_title !== '' ) : ?>
		<span class="tpmf-section-heading-label"><?php echo esc_html( $section_title ); ?></span>
	<?php endif; ?>
	<span class="tpmf-section-heading-line" aria-hidden="true"></span>
</div>
