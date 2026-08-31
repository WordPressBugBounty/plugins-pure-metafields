<?php
/**
 * Section Heading (user meta) — pseudo-field, visual divider only.
 * Renders a full-width row spanning both th + td columns so the heading
 * sits flush with the form-table layout the user-profile screen uses.
 * No value is read or written.
 *
 * @var string $id
 * @var string $label
 * @var string $description
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_title = isset( $label ) ? $label : '';
$_desc  = isset( $description ) ? $description : '';
?>
<tr class="tpmf-user-section-heading">
    <th colspan="2" scope="row" style="padding-top:24px;">
        <h3 style="margin:0;"><?php echo esc_html( $_title ); ?></h3>
        <?php if ( '' !== $_desc ) : ?>
            <p class="description" style="font-weight:400;"><?php echo esc_html( $_desc ); ?></p>
        <?php endif; ?>
    </th>
</tr>
