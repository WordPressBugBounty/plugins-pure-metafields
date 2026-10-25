<?php
/**
 * Register a meta box using a class.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class tpmeta_meta_box {

	private static $instance = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		
		if ( is_admin() ) {
			add_action( 'load-post.php',     array( $this, 'tpmeta_init_metabox' ) );
			add_action( 'load-post-new.php', array( $this, 'tpmeta_init_metabox' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'tpmeta_load_metabox_scripts' ));

			/**
			 * Option For user meta
			 */
			add_action('show_user_profile', array( $this, 'tpmeta_add_user_metafields' ));
			add_action('edit_user_profile', array( $this, 'tpmeta_add_user_metafields' ));
			add_action('personal_options_update', array( $this, 'tpmeta_save_user_metafields' ));
			add_action('edit_user_profile_update', array( $this, 'tpmeta_save_user_metafields' ));
			add_filter('manage_users_columns', array( $this, 'tpmeta_add_field_in_admin_table'));
			add_filter('manage_users_custom_column', array( $this, 'tpmeta_user_field_admin_table_values'), 10, 3);
		}
		
	}

	/**
	 * Load css and js
	 *
	 * @param string $hook Current admin screen hook (provided by
	 *                     `admin_enqueue_scripts`). Used to scope the
	 *                     user-fields stylesheet to profile.php /
	 *                     user-edit.php so its 50%-width overrides
	 *                     don't leak into post-edit metabox screens.
	 */
	public function tpmeta_load_metabox_scripts( $hook = '' ){
		TPMeta_Assets::enqueue_field_runtime();
		TPMeta_Assets::register_metabox_repeater();
		wp_enqueue_media();

		if ( 'profile.php' === $hook || 'user-edit.php' === $hook ) {
			TPMeta_Assets::enqueue_user_fields_css();
		}
	}

	/**
	 * Meta box initialization.
	 */
	public function tpmeta_init_metabox() {
		add_action( 'add_meta_boxes', array( $this, 'tpmeta_add_metabox'  ) );
		add_action( 'save_post', array( $this, 'tpmeta_save_metabox' ), 10, 2 );
	}

	/**
	 * Adds the meta box.
	 */
	public function tpmeta_add_metabox() {
		$metaboxs =  apply_filters('tp_meta_boxes', array());
		if(!empty($metaboxs)){
			foreach($metaboxs as $metabox){
				$_post_format = get_post_format();
				if( isset($metabox['post_format']) ){
					if($_post_format == $metabox['post_format']){
						$this->tpmeta_metabox_action($metabox, "remove");
					}else{
						$this->tpmeta_metabox_action($metabox);
					}
				}
				add_meta_box(
					$metabox['metabox_id'],
					$metabox['title'],
					array($this, 'tpmeta_metabox_render'),
					$metabox['post_type'],
					$metabox['context'],
					$metabox['priority'],
					array('meta' => $metabox)
				);
			}
		}
	}

	/**
	 * Hide Metabox
	 */
	public function tpmeta_metabox_action( $metabox, $action = NULL ){
		$screen_id 	= get_current_screen()->id;
		$user_id 	= get_current_user_id();
		$defaults 	= array(
			'postexcerpt',
			'trackbacksdiv',
			'postcustom',
			'commentstatusdiv',
			'commentsdiv',
			'slugdiv',
			'authordiv',
		);
		$closed_meta_boxes = get_user_meta($user_id, 'metaboxhidden_' . $screen_id, true);

		if($action == "remove"){
			if(empty($closed_meta_boxes)){
				return;
			}
			$search = array_search($metabox['metabox_id'], $closed_meta_boxes);
			
			if( !is_bool($search) && $search >= 0){
				unset($closed_meta_boxes[$search]);
			}
			update_user_meta($user_id, 'metaboxhidden_' . $screen_id, $closed_meta_boxes);
		}else{
			if( empty($closed_meta_boxes) ){
				$defaults[] = $metabox['metabox_id'];
				update_user_meta($user_id, 'metaboxhidden_' . $screen_id, $defaults);
			}else{
				$search = array_search($metabox['metabox_id'], $closed_meta_boxes);
				if(is_bool($search) && $search == false ){
					$closed_meta_boxes[] = $metabox['metabox_id'];
					update_user_meta($user_id, 'metaboxhidden_' . $screen_id, $closed_meta_boxes);
				}else{
					update_user_meta($user_id, 'metaboxhidden_' . $screen_id, $closed_meta_boxes);
				}
			}
		}
	}

	/**
	 * Metabox HTML Render Funtion
	 */
	public function tpmeta_metabox_render($post, $metabox){
		// Enqueue wp-api and wp-editor first
		wp_enqueue_script( 'wp-api' );
		wp_enqueue_script( 'wp-editor' );

		$meta = $metabox['args']['meta'];
		$columns = isset($meta['columns'])? $meta['columns'] : 3; 
		$_post_format = isset($meta['post_format'])? sanitize_text_field( wp_unslash($meta['post_format']) ) : '';
		$_metabox_id = isset($meta['metabox_id'])? sanitize_text_field( wp_unslash($meta['metabox_id']) ) : '';
		?>
		<?php
			// Detect if any section_heading pseudo-fields exist (injected by the
			// Metafield Builder loader). When present, the column grid classes
			// (`tm-meta-wrapper tm-meta-column-N`) move from the outer wrapper
			// onto each per-section div so every section renders its own
			// independent column layout. Legacy hand-coded metaboxes without
			// section_heading fields keep the original outer-grid markup.
			$_has_sections = false;
			foreach ( $meta['fields'] as $_f ) {
				if ( isset( $_f['type'] ) && 'section_heading' === $_f['type'] ) {
					$_has_sections = true;
					break;
				}
			}
			$_outer_class = $_has_sections
				? 'tm-meta-wrapper-outer'
				: 'tm-meta-wrapper tm-meta-column-' . esc_attr( $columns );
			// Section is now a column-1 flex container so the heading and
			// each .tm-meta-row sibling stack full-width inside it; each
			// row owns its own column count via tm-meta-column-N below.
			$_section_class = 'tm-metabox-section tm-meta-wrapper tm-meta-column-1';
		?>
		<div
		data-metabox-id="<?php echo esc_attr($_metabox_id); ?>"
    	data-post-format="<?php echo esc_attr($_post_format); ?>"
		class="<?php echo esc_attr( $_outer_class ); ?>">
			<?php wp_nonce_field( "_nonce_action_tp_metabox", "_nonce_tp_metabox" ); ?>
			<input type="hidden" name="current_metabox_id[]" value="<?php echo esc_attr($meta['metabox_id']); ?>">
			<?php
				if ( $_has_sections ) {
					$_section_open = false;
					$_row_open     = false;
					foreach ( $meta['fields'] as $field ) {
						$_ftype = isset( $field['type'] ) ? $field['type'] : '';

						// Row marker: open a per-row flex container with the
						// row's own column count. Skip the template render.
						if ( '_tpmf_row' === $_ftype ) {
							if ( $_row_open ) {
								echo '</div>'; // close prior row
								$_row_open = false;
							}
							$_row_cols = isset( $field['columns'] ) ? absint( $field['columns'] ) : 1;
							if ( $_row_cols < 1 ) { $_row_cols = 1; }
							echo '<div class="tm-meta-row tm-meta-wrapper tm-meta-column-' . esc_attr( $_row_cols ) . '">';
							$_row_open = true;
							continue;
						}

						$_is_heading = ( 'section_heading' === $_ftype );
						if ( $_is_heading ) {
							if ( $_row_open ) {
								echo '</div>';
								$_row_open = false;
							}
							if ( $_section_open ) {
								echo '</div>';
							}
							echo '<div class="' . esc_attr( $_section_class ) . '">';
							$_section_open = true;
						}
						tpmeta_load_template( 'metaboxes/fields/group.php', array(
							'field'  => $field,
							'fields' => $meta['fields'],
							'post'   => $post,
						) );
					}
					if ( $_row_open ) {
						echo '</div>';
					}
					if ( $_section_open ) {
						echo '</div>';
					}
				} else {
					foreach ( $meta['fields'] as $field ) {
						tpmeta_load_template( 'metaboxes/fields/group.php', array(
							'field'  => $field,
							'fields' => $meta['fields'],
							'post'   => $post,
						) );
					}
				}
			?>
		</div>
		<?php
	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return null
	 */
	public function tpmeta_save_metabox( $post_id, $post ) {
		
		// Add nonce for security and authentication.
		$metaboxes = apply_filters('tp_meta_boxes', array());
		// echo '<pre>';
		// var_dump($_POST);
		// echo '</pre>';
		// exit();

		// check if empty
		if(!isset($_POST['_nonce_tp_metabox'])){
			return;
		}

		// Check if nonce is valid.
		if ( !wp_verify_nonce( $_POST['_nonce_tp_metabox'], '_nonce_action_tp_metabox' ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$current_meta = array_filter($metaboxes, function($item){
			if( is_array($_POST['current_metabox_id']) ){
				if(in_array($item['metabox_id'], $_POST['current_metabox_id'])){
					return $item;
				}
			}else{
				return $item['metabox_id'] == $_POST['current_metabox_id'];
			}
		});

		$current_metas = array_values($current_meta);
		$types = array('text', 'image', 'gallery', 'colorpicker', 'tabs', 'datepicker', 'select_posts');

		foreach($current_metas as $meta){
			$fields = $meta['fields'];

			foreach($fields as $field){

				if ( 'image' === $field['type'] && isset( $field['return_format'] ) && isset( $_POST[ $field['id'] ] ) ) {
					/*
					 * Metabox image field with a return_format set on the field def.
					 * Storage shape matches the chosen return_format directly so a
					 * straight `get_post_meta()` / `tpmeta_field()` call returns the
					 * developer's choice — no wrapper helper required.
					 *
					 *   'array' → [ id, url, alt ]
					 *   'url'   → string
					 *   'id'    → integer
					 *
					 * Inbound POST may be the legacy scalar (attachment ID) or the
					 * array shape { url, alt, id } when the template emits it.
					 * Tokens ("{{theme_url}}/...") are kept verbatim so the value
					 * stays portable across domains.
					 */
					$rf = (string) $field['return_format'];
					if ( ! in_array( $rf, array( 'array', 'id', 'url' ), true ) ) $rf = 'array';

					$in_url = ''; $in_alt = ''; $in_id = 0;
					if ( is_array( $_POST[ $field['id'] ] ) ) {
						$arr = $_POST[ $field['id'] ];
						$in_url = isset( $arr['url'] ) ? wp_unslash( (string) $arr['url'] ) : '';
						$in_alt = isset( $arr['alt'] ) ? wp_unslash( (string) $arr['alt'] ) : '';
						$in_id  = isset( $arr['id'] )  ? absint( $arr['id'] ) : 0;
					} else {
						$raw = wp_unslash( (string) $_POST[ $field['id'] ] );
						if ( '' !== $raw ) {
							if ( false !== strpos( $raw, '{{' ) ) {
								$in_url = $raw;
							} elseif ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
								$in_url = $raw;
							} elseif ( absint( $raw ) ) {
								$in_id = absint( $raw );
							}
						}
					}

					$is_token  = ( '' !== $in_url && false !== strpos( $in_url, '{{' ) );
					$clean_url = $is_token ? sanitize_text_field( $in_url ) : esc_url_raw( $in_url );
					$clean_alt = sanitize_text_field( $in_alt );
					$clean_id  = (int) $in_id;

					// Cross-fill missing parts from the attachment when an ID is known.
					if ( $clean_id ) {
						if ( '' === $clean_url ) {
							$clean_url = (string) wp_get_attachment_url( $clean_id );
						}
						if ( '' === $clean_alt ) {
							$clean_alt = (string) get_post_meta( $clean_id, '_wp_attachment_image_alt', true );
						}
					} elseif ( '' !== $clean_url && ! $is_token && 'id' === $rf ) {
						$resolved_id = (int) attachment_url_to_postid( $clean_url );
						if ( $resolved_id ) $clean_id = $resolved_id;
					}

					if ( 'url' === $rf ) {
						update_post_meta( $post_id, $field['id'], $clean_url );
					} elseif ( 'id' === $rf ) {
						update_post_meta( $post_id, $field['id'], $clean_id ? $clean_id : '' );
					} else { // array
						update_post_meta( $post_id, $field['id'], array(
							'id'  => $clean_id,
							'url' => $clean_url,
							'alt' => $clean_alt,
						) );
					}
				}elseif(in_array($field['type'], $types) && !empty($_POST[$field['id']])){
					$_raw_val = $_POST[$field['id']];
					if ( is_array( $_raw_val ) ) {
						$_clean_val = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_raw_val ) );
					} else {
						$_clean_val = sanitize_text_field( wp_unslash( (string) $_raw_val ) );
					}
					update_post_meta($post_id, $field['id'], $_clean_val);
				}elseif($field['type'] == 'checkbox'){
					$_opts = !empty($field['options']) ? $field['options'] : ( !empty($field['choices']) ? $field['choices'] : array() );
					if(!empty($_opts)){
						$options = array();
						foreach($_opts as $key => $val){
							// Use isset(): unchecked boxes are absent from POST entirely;
							// a checked box is always present, even when its value is '0'.
							if(isset($_POST[$field['id'].'_'.$key])){
								$options[$key] = sanitize_text_field($val);
							}
						}
						update_post_meta($post_id, $field['id'], $options);
					}
				}elseif($field['type'] == 'multicolor'){
					if(isset($_POST[$field['id']]) && is_array($_POST[$field['id']])){
						$clean = array();
						foreach($_POST[$field['id']] as $k => $v){
							$k     = sanitize_key($k);
							$color = sanitize_text_field(wp_unslash((string)$v));
							if('' === $k) continue;
							if(self::is_valid_color($color)){
								$clean[$k] = $color;
							}
						}
						update_post_meta($post_id, $field['id'], $clean);
					}
				}elseif($field['type'] == 'textarea' && !empty($_POST[$field['id']])){
					// Define allowed SVG tags and attributes
					$allowed_tags = tpmeta_allowed_svg_tags();
					if(is_array($_POST[$field['id']])){
						return;
					}
					update_post_meta($post_id, $field['id'], wp_kses($_POST[$field['id']], $allowed_tags));
				}elseif($field['type'] == 'editor'){
					// Rich-text editor: preserve safe HTML (p, strong, a, lists,
					// images, etc.) — sanitize_text_field would strip all tags.
					if ( isset( $_POST[ $field['id'] ] ) && ! is_array( $_POST[ $field['id'] ] ) ) {
						$_html = wp_kses_post( wp_unslash( (string) $_POST[ $field['id'] ] ) );
						update_post_meta( $post_id, $field['id'], $_html );
					}
				}elseif($field['type'] == 'select'){
					if(isset($_POST[$field['id']]) && isset($field['multiple']) && $field['multiple'] == true){
						$array_object = array();
						
						foreach($field['options'] as $key => $val){
							if(in_array($key, $_POST[$field['id']])){
								$array_object[$key] = $val;
							}
						}
						update_post_meta($post_id, $field['id'], self::sanitize_array(!isset($_POST[$field['id']])? array() : $array_object));
					}else{
						$array_object = array();
						if(isset($field['value']) && $field['value'] == 'both'){
							foreach($field['options'] as $key => $val){
								if($key == $_POST[$field['id']]){
									$array_object[$key] = $val;
								}
							}
							update_post_meta($post_id, $field['id'], self::sanitize_array(!isset($_POST[$field['id']])? array() : $array_object));
						}else{
							update_post_meta($post_id, $field['id'], sanitize_text_field(!isset($_POST[$field['id']])? '' : $_POST[$field['id']]));
						}
					}
				}elseif($field['type'] == 'repeater' && isset($_POST[$field['id']]) ){
					$_meta_key = $field['id'];
					$_row_counter = isset($_POST[$field['id'].'_counter'])? intval($_POST[$field['id'].'_counter']) : 0;
					$_repeater_rows = self::sanitize_array($_POST[$field['id']]);
					$_item_num_values = isset($_POST[$field['id'].'_item_num']) ? array_map('intval', (array) $_POST[$field['id'].'_item_num']) : array();
					$_repeater_rows_value = array();
					if($_row_counter > 0){
						for($i=0; $i<count($_repeater_rows); $i++){
							$_row = array();
							foreach( $field['fields'] as $repeater_field ){
								$_get_field_value = self::sanitize_array($_POST[$repeater_field['id']]);

								if(in_array($repeater_field['type'], $types) && !empty($repeater_field)){
									$_row[$repeater_field['id']] = sanitize_text_field($_get_field_value[$i]);
								}elseif($repeater_field['type'] == 'textarea' && !empty($repeater_field)){
									$_row[$repeater_field['id']] = sanitize_textarea_field($_get_field_value[$i]);
								}elseif($repeater_field['type'] == 'multicolor' && !empty($repeater_field)){
									// Multicolor repeater: stored as JSON in a carrier hidden input.
									$_mc_raw = isset($_get_field_value[$i]) ? stripslashes($_get_field_value[$i]) : '';
									$_mc_map = json_decode($_mc_raw, true);
									$_mc_clean = array();
									if(is_array($_mc_map)){
										foreach($_mc_map as $_mk => $_mv){
											$_mk = sanitize_key($_mk);
											$_mv = sanitize_text_field(wp_unslash((string)$_mv));
											if('' !== $_mk && self::is_valid_color($_mv)){
												$_mc_clean[$_mk] = $_mv;
											}
										}
									}
									$_row[$repeater_field['id']] = $_mc_clean;
								}elseif($repeater_field['type'] == 'checkbox' && !empty($repeater_field)){
									$_row[$repeater_field['id']] = self::sanitize_array(json_decode(stripslashes($_get_field_value[$i]), true));
								}elseif($repeater_field['type'] == 'select' && !empty($repeater_field)){
									$array_object = [];
									$array_object[$_get_field_value[$i]] = $repeater_field['options'][$_get_field_value[$i]];
									$_row[$repeater_field['id']] = self::sanitize_array($array_object);
								}else{
									$_row[$repeater_field['id']] = sanitize_text_field($_get_field_value[$i]);
								}
							}
							// Persist the label number so it survives sort + reload.
							$_row['_item_num'] = isset($_item_num_values[$i]) && $_item_num_values[$i] > 0
								? $_item_num_values[$i]
								: ($i + 1);
							$_repeater_rows_value[] = $_row;
						}
					}
					update_post_meta($post_id, $_meta_key, $_repeater_rows_value);
				}else{
					if(isset($_POST[$field['id']])){
						$_raw_val = $_POST[$field['id']];
						if ( is_array( $_raw_val ) ) {
							$_clean_val = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_raw_val ) );
						} else {
							$_clean_val = sanitize_text_field( wp_unslash( (string) $_raw_val ) );
						}
						update_post_meta($post_id, $field['id'], $_clean_val);
					}else{
						update_post_meta($post_id, $field['id'], sanitize_text_field('off'));
					}
				}
			}
		}
	}

	/**
	 * Normalise the `tp_user_meta` filter return into a sequential list of
	 * user-meta blocks. Accepts both shapes:
	 *
	 *   Legacy:  array( 'label' => '...', 'fields' => array( ... ) )
	 *   New:     array( array( 'label' => '...', 'fields' => ... ), ... )
	 *
	 * Legacy single-block returns are detected by the presence of a top-level
	 * 'fields' key. Empty / non-array returns produce an empty list.
	 *
	 * @return array
	 */
	private function tpmeta_get_user_meta_blocks() {
		$raw = apply_filters( 'tp_user_meta', array() );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}
		// Legacy single-block: assoc with a 'fields' key at the top level.
		if ( isset( $raw['fields'] ) ) {
			return array( $raw );
		}
		return array_values( $raw );
	}

	/**
	 * Add User Extra Fields
	 */
	public function tpmeta_add_user_metafields($user){
		$blocks = $this->tpmeta_get_user_meta_blocks();
		if ( empty( $blocks ) ) {
			return;
		}
		wp_nonce_field( "_nonce_action_tp_user_meta", "_nonce_tp_user_meta" );

		foreach ( $blocks as $block ) {
			// Optional per-block capability gate (new shape only — legacy
			// blocks have no 'capability' key so the check is skipped).
			if ( ! empty( $block['capability'] ) && ! current_user_can( $block['capability'] ) ) {
				continue;
			}
			$_label  = isset( $block['label'] )  ? $block['label']  : '';
			$_fields = isset( $block['fields'] ) ? (array) $block['fields'] : array();
			?>
			<h2><?php echo esc_html( $_label ); ?></h2>
			<hr/>
			<table class="form-table">
				<?php
				foreach ( $_fields as $field ) {
					if ( empty( $field['type'] ) ) continue;
					$new_field = wp_parse_args( array( 'user_id' => $user->ID ), $field );
					tpmeta_load_template( 'metaboxes/user-fields/' . $field['type'] . '.php', $new_field );
				}
				?>
			</table>
			<?php
		}
	}

	public function tpmeta_save_user_metafields($user){
		// check if empty
		if(!isset($_POST['_nonce_tp_user_meta'])){
			return;
		}

		// Check if nonce is valid.
		if ( !wp_verify_nonce( $_POST['_nonce_tp_user_meta'], '_nonce_action_tp_user_meta' ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_user', $user ) ) {
			return;
		}

		foreach ( $this->tpmeta_get_user_meta_blocks() as $block ) {
			// Per-block capability gate also applies to save.
			if ( ! empty( $block['capability'] ) && ! current_user_can( $block['capability'] ) ) {
				continue;
			}
			$_fields = isset( $block['fields'] ) ? (array) $block['fields'] : array();
			foreach ( $_fields as $field ) {
				if ( empty( $field['type'] ) || empty( $field['id'] ) ) continue;
				$this->save_user_field( $user, $field );
			}
		}
	}

	/**
	 * Per-type user-meta save dispatcher. Mirrors the post-metabox save
	 * switch — same is_array() guards, same checkbox `isset()` rule, same
	 * legacy 'off' fallback on the catch-all branch so existing behaviour
	 * for the original 5 user-field templates stays byte-identical.
	 *
	 * @param int   $user_id
	 * @param array $field
	 */
	private function save_user_field( $user_id, $field ) {
		// Visual-only pseudo-field — never written.
		if ( 'section_heading' === $field['type'] ) {
			return;
		}

		$types = array( 'text', 'url', 'number', 'image', 'colorpicker', 'datepicker', 'select_posts' );

		if ( in_array( $field['type'], $types, true ) && isset( $_POST[ $field['id'] ] ) ) {
			$_raw_val = $_POST[ $field['id'] ];
			if ( is_array( $_raw_val ) ) {
				$_clean_val = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_raw_val ) );
			} else {
				$_clean_val = sanitize_text_field( wp_unslash( (string) $_raw_val ) );
			}
			update_user_meta( $user_id, $field['id'], $_clean_val );
			return;
		}

		if ( 'switch' === $field['type'] ) {
			// Present in POST = checked → 'on'. Absent → 'off'.
			$_val = isset( $_POST[ $field['id'] ] ) ? 'on' : 'off';
			update_user_meta( $user_id, $field['id'], $_val );
			return;
		}

		if ( 'multicolor' === $field['type'] ) {
			// Standalone POST shape: `name="id[slot_key]"` → assoc array.
			// When every swatch is unchecked the key is absent entirely, in
			// which case we explicitly store an empty array so the saved
			// shape stays stable (mirrors the post-metabox branch).
			$_raw = isset( $_POST[ $field['id'] ] ) ? $_POST[ $field['id'] ] : array();
			$_clean = array();
			if ( is_array( $_raw ) ) {
				foreach ( $_raw as $_k => $_v ) {
					$_k = sanitize_key( $_k );
					if ( '' === $_k ) continue;
					$_v = sanitize_text_field( wp_unslash( (string) $_v ) );
					if ( self::is_valid_color( $_v ) ) {
						$_clean[ $_k ] = $_v;
					}
				}
			}
			update_user_meta( $user_id, $field['id'], $_clean );
			return;
		}

		if ( 'editor' === $field['type'] ) {
			// Rich-text HTML — preserve safe tags via wp_kses_post.
			if ( isset( $_POST[ $field['id'] ] ) && ! is_array( $_POST[ $field['id'] ] ) ) {
				$_html = wp_kses_post( wp_unslash( (string) $_POST[ $field['id'] ] ) );
				update_user_meta( $user_id, $field['id'], $_html );
			}
			return;
		}

		if ( 'textarea' === $field['type'] && ! empty( $_POST[ $field['id'] ] ) ) {
			if ( is_array( $_POST[ $field['id'] ] ) ) {
				return;
			}
			$allowed_tags = tpmeta_allowed_svg_tags();
			update_user_meta( $user_id, $field['id'], wp_kses( wp_unslash( (string) $_POST[ $field['id'] ] ), $allowed_tags ) );
			return;
		}

		if ( 'select' === $field['type'] ) {
			if ( isset( $_POST[ $field['id'] ] ) && ! empty( $field['multiple'] ) ) {
				$_opts = isset( $field['options'] ) ? (array) $field['options'] : array();
				$_picked = (array) $_POST[ $field['id'] ];
				$_assoc = array();
				foreach ( $_opts as $_k => $_v ) {
					if ( in_array( $_k, $_picked, true ) ) {
						$_assoc[ $_k ] = $_v;
					}
				}
				update_user_meta( $user_id, $field['id'], self::sanitize_array( $_assoc ) );
			} else {
				$_v = isset( $_POST[ $field['id'] ] ) ? (string) $_POST[ $field['id'] ] : '';
				update_user_meta( $user_id, $field['id'], sanitize_text_field( wp_unslash( $_v ) ) );
			}
			return;
		}

		// Catch-all preserves legacy behaviour, including the 'off' fallback
		// on absent POST (used by the original checkbox user-field template).
		if ( isset( $_POST[ $field['id'] ] ) ) {
			$_raw_val = $_POST[ $field['id'] ];
			if ( is_array( $_raw_val ) ) {
				$_clean_val = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_raw_val ) );
			} else {
				$_clean_val = sanitize_text_field( wp_unslash( (string) $_raw_val ) );
			}
			update_user_meta( $user_id, $field['id'], $_clean_val );
		} else {
			update_user_meta( $user_id, $field['id'], 'off' );
		}
	}

	public function tpmeta_add_field_in_admin_table($column){
		foreach ( $this->tpmeta_get_user_meta_blocks() as $block ) {
			$_fields = isset( $block['fields'] ) ? (array) $block['fields'] : array();
			foreach ( $_fields as $field ) {
				if ( ! empty( $field['show_in_admin_table'] ) && 1 == $field['show_in_admin_table'] && ! empty( $field['id'] ) ) {
					$column[ $field['id'] ] = isset( $field['label'] ) ? $field['label'] : $field['id'];
				}
			}
		}
		return $column;
	}

	public function tpmeta_user_field_admin_table_values($value, $column, $user_id){
		foreach ( $this->tpmeta_get_user_meta_blocks() as $block ) {
			$_fields = isset( $block['fields'] ) ? (array) $block['fields'] : array();
			foreach ( $_fields as $field ) {
				if ( empty( $field['show_in_admin_table'] ) || 1 != $field['show_in_admin_table'] ) continue;
				if ( empty( $field['id'] ) || $field['id'] != $column )                            continue;

				if ( isset( $field['type'] ) && 'image' === $field['type'] ) {
					$user_image_url = get_user_meta( $user_id, $field['id'], true );
					if ( ! empty( $user_image_url ) ) {
						return '<div class="tp-user-image"><img src="' . esc_url( $user_image_url ) . '" alt=""/></div>';
					}
					return $value;
				}
				return get_user_meta( $user_id, $field['id'], true );
			}
		}
		return $value;
	}

	/**
	 * Returns true if $color is an acceptable CSS color value:
	 * hex (#rgb / #rrggbb / #rrggbbaa), rgb/rgba(), hsl/hsla(), or a named color word.
	 */
	public static function is_valid_color( $color ) {
		if ( '' === $color ) return true; // allow empty (clears value)
		if ( preg_match( '/^#([a-f0-9]{3,8})$/i', $color ) ) return true;
		if ( preg_match( '/^rgba?\([\s\d.,%-]+\)$/i', $color ) ) return true;
		if ( preg_match( '/^hsla?\([\s\d.,%-]+\)$/i', $color ) ) return true;
		if ( preg_match( '/^[a-zA-Z]+$/', $color ) ) return true; // named: red, blue, …
		return false;
	}

	public static function sanitize_array($arr){
		$sanitized_arr = array();
		if(is_array($arr)){
			if(!empty($arr)){
				foreach($arr as $key => $val){
					$sanitized_arr[$key] = is_array($val) ? self::sanitize_array($val) : sanitize_text_field($val);
				}
				return $sanitized_arr;
			}else{
				return $arr;
			}
		}else{
			return sanitize_text_field($arr);
		}
	}

	public static function instance(){
		if(!self::$instance){
			self::$instance = new self();
		}

		return self::$instance;
	}
}

new tpmeta_meta_box();