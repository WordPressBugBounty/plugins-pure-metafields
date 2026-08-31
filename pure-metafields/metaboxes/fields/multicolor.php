<?php
/**
 * Multicolor field – metabox context
 *
 * Data model:
 *   $field['options'] = { slot_key => css_color }
 *     - slot_key  : PHP identifier (e.g. 'primary')
 *     - css_color : CSS color that IS the swatch background AND the stored value
 *
 * Standalone: POST name="id[slot_key]" = css_color  →  saved as assoc array.
 * Repeater:   JSON-carrier hidden input updated by .tm-mc-rpt-picker change handler.
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

// ── Slot definitions { slot_key => css_color } ────────────────────────────────
$_mc_slots = array();
if ( isset( $options ) && is_array( $options ) && count( $options ) ) {
    $_mc_slots = $options;
} elseif ( isset( $choices ) && is_array( $choices ) && count( $choices ) ) {
    $_mc_slots = $choices;
}

// ── Default selection { slot_key => css_color } ───────────────────────────────
// Used to determine which swatches are pre-checked on first load (no saved value).
$_mc_def = array();
if ( isset( $default ) ) {
    if ( is_array( $default ) ) {
        $_mc_def = $default;
    } elseif ( is_string( $default ) && '' !== $default ) {
        $_dec = json_decode( $default, true );
        if ( is_array( $_dec ) ) $_mc_def = $_dec;
    }
}

if ( isset( $row_db_value ) ) :
    // ── Repeater row: JSON carrier ────────────────────────────────────────────
    $_mc_saved = is_array( $row_db_value ) ? $row_db_value : $_mc_def;
    $_mc_json  = wp_json_encode( $_mc_saved );
    $_mc_cid   = esc_attr( $id );
    ?>
    <input type="hidden"
           name="<?php echo esc_attr( $id ); ?>[]"
           class="tm-multicolor-carrier tm-multicolor-carrier-<?php echo $_mc_cid; ?>"
           value="<?php echo esc_attr( $_mc_json ); ?>"
    />
    <div class="tm-multicolor-group">
        <?php foreach ( $_mc_slots as $_mc_key => $_mc_slot_color ) :
            // The slot color comes directly from options (label IS the color).
            $_mc_color   = ( '' !== $_mc_slot_color ) ? $_mc_slot_color : '#cccccc';
            $_mc_checked = isset( $_mc_saved[ $_mc_key ] );
        ?>
        <label class="tm-mcswatch-item">
            <input
                type="checkbox"
                class="tm-mcswatch-input tm-mc-rpt-picker"
                data-mc-carrier="<?php echo $_mc_cid; ?>"
                data-mc-key="<?php echo esc_attr( $_mc_key ); ?>"
                data-mc-color="<?php echo esc_attr( $_mc_color ); ?>"
                value="<?php echo esc_attr( $_mc_color ); ?>"
                <?php checked( $_mc_checked ); ?>
            />
            <span class="tm-mcswatch-color" style="background-color:<?php echo esc_attr( $_mc_color ); ?>"></span>
            <span class="tm-mcswatch-label"><?php echo esc_html( $_mc_key ); ?></span>
        </label>
        <?php endforeach; ?>
    </div>

<?php else :
    // ── Standalone metabox ────────────────────────────────────────────────────
    global $post;
    $_mc_has_meta = ! empty( $post ) && metadata_exists( 'post', $post->ID, $id );
    $_mc_saved    = $_mc_has_meta ? get_post_meta( $post->ID, $id, true ) : null;
    // On first load (no saved meta): use defaults as the selected set.
    $_mc_current  = is_array( $_mc_saved ) ? $_mc_saved : ( is_null( $_mc_saved ) ? $_mc_def : array() );
    ?>
    <div class="tm-multicolor-group">
        <?php foreach ( $_mc_slots as $_mc_key => $_mc_slot_color ) :
            $_mc_color   = ( '' !== $_mc_slot_color ) ? $_mc_slot_color : '#cccccc';
            $_mc_checked = isset( $_mc_current[ $_mc_key ] );
        ?>
        <label class="tm-mcswatch-item">
            <input
                type="checkbox"
                class="tm-mcswatch-input"
                name="<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $_mc_key ); ?>]"
                id="<?php echo esc_attr( $id . '_' . $_mc_key ); ?>"
                value="<?php echo esc_attr( $_mc_color ); ?>"
                <?php checked( $_mc_checked ); ?>
            />
            <span class="tm-mcswatch-color" style="background-color:<?php echo esc_attr( $_mc_color ); ?>"></span>
            <span class="tm-mcswatch-label"><?php echo esc_html( $_mc_key ); ?></span>
        </label>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
