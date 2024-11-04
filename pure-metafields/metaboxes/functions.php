<?php
/**
 * 
 * Functions
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
* Get the meta field value
*/
function tpmeta_field($meta_key, $post_id = NULL){
  if($post_id != NULL){
    $tpmeta_field_value = get_post_meta($post_id, $meta_key, true);
    if(empty($tpmeta_field_value) || $tpmeta_field_value == ""){
      $tpmeta_get_field = tpmeta_get_field_value_by_id($meta_key);
      if(is_null($tpmeta_get_field) || empty($tpmeta_get_field)){
        return false;
      }else{
        if(isset($tpmeta_get_field['default'])){
          return $tpmeta_get_field['default'];
        }else{
          return false;
        }
      }
    }else{
      return $tpmeta_field_value;
    }
  }else{
    global $post;
    if( !empty($post) ){
      $tpmeta_field_value = get_post_meta($post->ID, $meta_key, true); 
      if(!metadata_exists('post', $post->ID, $meta_key)){
        if(is_null(tpmeta_get_field_value_by_id($meta_key))){
          return false;
        }
        if(isset(tpmeta_get_field_value_by_id($meta_key)['default'])){
          return isset(tpmeta_get_field_value_by_id($meta_key)['default'])? tpmeta_get_field_value_by_id($meta_key)['default'] : false;
        }
        return false;
      }else{
        return $tpmeta_field_value;
      }
    }else{
      $tpmeta_get_field = tpmeta_get_field_value_by_id($meta_key);
      if(is_null($tpmeta_get_field) || empty($tpmeta_get_field)){
        return false;
      }else{
        return isset($tpmeta_get_field['default'])? $tpmeta_get_field['default'] : false;
      }
    }
  }
}

/**
* Get Gallery
*/
function tpmeta_gallery_field($key, $post_id = null ){
  global $post;
  $post_id = $post_id? $post_id : $post->ID;
  $get_field_value = get_post_meta($post_id, $key, true);
  if($get_field_value){
    return tpmeta_gallery_images($get_field_value);
  }else{
    return false;
  }
}

/**
* Get image
*/
function tpmeta_image_field($key, $post_id = null ){
  global $post;
  if( is_null($post) && is_null($post_id) ){
    return false;
  }
  $post_id = $post_id? $post_id : $post->ID;
  $get_field_value = get_post_meta($post_id, $key, true);
  if($get_field_value){
    return tpmeta_image($get_field_value);
  }else{
    return false;
  }
}

/**
* Compare the default condition
*/
function tpmeta_compare($value, $operator, $compare){
  $d = 'return ($value '.$operator.' $compare) ? true : false;';
  return eval($d);
}

/**
* Check wheather the condition match with value
*/
function tpmeta_is_condition_matched( array $conditional, array $fields){
  $get_conditional_field = array_column($fields, 'default', 'id');
  $get_conditional_field_value = isset($get_conditional_field[$conditional[0]])? $get_conditional_field[$conditional[0]] : '';

  if(tpmeta_field($conditional[0]) == ''){
    $compare_results = tpmeta_compare($get_conditional_field_value, $conditional[1], $conditional[2]);
  }else{
    $compare_results = tpmeta_compare(tpmeta_field($conditional[0]), $conditional[1], $conditional[2]);
  }
  return $compare_results;
}

/**
* Check wheather the condition match with row value of repeater
*/

function tpmeta_is_row_matched( array $conditional, array $row ){
  $get_row_value = isset($conditional[0])? $row[$conditional[0]] : '';
  $compare_results = tpmeta_compare($get_row_value, $conditional[1], $conditional[2]);
  return $compare_results;
}


/**
*  Match the field post format with admin post format
*/
function tpmeta_field_matched_with_post_format($field_with_post_format, $current_post_format){
  if(isset($field_with_post_format)){
    if($field_with_post_format != $current_post_format){
      return false;
    }else{
      return true;
    }
  }else{
    return true;
  }
}

/**
* Get images ids from meta field and return URL and ALT
*/
function tpmeta_gallery_images(string $gallery_value, string $size = "full"){
  if(gettype($gallery_value) == "string" && $gallery_value != ""){
    $get_images_ids = explode(",", $gallery_value);
    $gallery_items = array();
    foreach($get_images_ids as $image_id){
      $get_attachment = wp_get_attachment_image_src($image_id, $size);
      if(false != $get_attachment){
        array_push($gallery_items, array(
          'url' => $get_attachment[0],
          'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
        ));
      }
    }

    return $gallery_items;
  }else{
    return false;
  }
}

/**
* Return single image with URL and ALT
*/
function tpmeta_image($image_id, string $size = "full"){
  if($image_id != ""){
    $get_attachment = wp_get_attachment_image_src($image_id, $size);
    if(false != $get_attachment){
      $image_src = array(
        'url' => $get_attachment[0],
        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
      );
      return $image_src;
    }else{
      return false;
    }
  }else{
    return false;
  }
}


/**
* Get the field by IT's ID
*/
function tpmeta_get_field_value_by_id($field_id){
  $_metaboxes = apply_filters('tp_meta_boxes', array());
  $_fields = array_column($_metaboxes, 'fields');
  foreach($_fields as $fields){
    foreach($fields as $field){
      if($field['id'] == $field_id){
        return $field;
      }
    }
  }
}


/**
 * Load Template
 */
function tpmeta_load_template($path, $args = array()){
  extract($args);
  if( file_exists(TPMETA_PATH . $path) ){
    include(TPMETA_PATH . $path);
  }
}


/**
 * Allowed svg tags
 */
function tpmeta_allowed_svg_tags(){
  $allowed_tags = array(
    'svg' => array(
      'xmlns' => true,
      'viewBox' => true,
      'width' => true,
      'height' => true,
      'preserveAspectRatio' => true,
      'fill' => true,
      'stroke' => true,
      'style' => true,
      // Add any other necessary attributes
    ),
    'circle' => array(
      'cx' => true,
      'cy' => true,
      'r' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      // Add other attributes as needed
    ),
    'rect' => array(
      'x' => true,
      'y' => true,
      'width' => true,
      'height' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'ellipse' => array(
      'cx' => true,
      'cy' => true,
      'rx' => true,
      'ry' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'line' => array(
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'polyline' => array(
      'points' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'polygon' => array(
      'points' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'path' => array(
      'd' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true,
    ),
    'g' => array(
      'fill' => true,
      'stroke' => true,
      // Add other attributes as needed
    ),
    'defs' => array(), // No attributes needed
    'clipPath' => array(), // No attributes needed
    'linearGradient' => array(
      'id' => true,
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
    ),
    'radialGradient' => array(
      'id' => true,
      'cx' => true,
      'cy' => true,
      'r' => true,
      'fx' => true,
      'fy' => true,
    ),
    'stop' => array(
      'offset' => true,
      'stop-color' => true,
      'stop-opacity' => true,
    ),
    // Add more SVG tags and attributes as needed
  );

  return apply_filters('tpmeta_allowed_svg_tags', $allowed_tags);
}