<?php
/**
 * Plugin Name: Instant WebP Converter
 * Plugin URI:  https://wordpress.org/plugins/instant-webp-converter
 * Description: Instant WebP Converter automatically converts JPEG and PNG images to the optimized WebP format, enhancing your website’s speed and performance.
 * Version: 1.1 
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Mokhlesur Rahman
 * Author URI:  https://facebook.com/developermokhlesur
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Hook into WordPress upload process
add_filter('wp_handle_upload', 'iwpc_convert_and_replace_with_webp');

function iwpc_convert_and_replace_with_webp($image_data) {
    $file_type = $image_data['type'];
    $supported_types = array('image/jpeg', 'image/png');

    if (in_array($file_type, $supported_types)) {
        $image_path = $image_data['file'];
        $webp_image_path = iwpc_convert_to_webp($image_path, $file_type);

        if ($webp_image_path) {
            wp_delete_file($image_path); // Delete the original image
            $image_data['file'] = $webp_image_path;
            $image_data['type'] = 'image/webp';
            $image_data['url'] = str_replace(basename($image_path), basename($webp_image_path), $image_data['url']);
        }
    }
    return $image_data;
}

function iwpc_convert_to_webp($image_path, $mime_type) {
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($image_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($image_path);
            // Check for transparency and create a new true color image
            $new_image = imagecreatetruecolor(imagesx($image), imagesy($image));
            // Preserve transparency
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, imagesx($new_image), imagesy($new_image), $transparent);
            imagecopy($new_image, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $new_image; // Update the image variable to the new image
            break;
        default:
            return false;
    }

    $webp_image_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);
    if (imagewebp($image, $webp_image_path, 80)) {
        imagedestroy($image);
        return $webp_image_path;
    }

    return false;
}

// Add WebP support to allowed mime types in WordPress
add_filter('mime_types', 'iwpc_add_webp_mime_type');
function iwpc_add_webp_mime_type($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

// Ensure WebP images can be viewed in the Media Library
add_filter('upload_mimes', 'iwpc_allow_webp_uploads');
function iwpc_allow_webp_uploads($existing_mimes) {
    $existing_mimes['webp'] = 'image/webp';
    return $existing_mimes;
}

// Hook into plugin activation
register_activation_hook(__FILE__, 'iwpc_convert_existing_images_to_webp');

function iwpc_convert_existing_images_to_webp() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png'),
        'post_status' => 'inherit',
        'posts_per_page' => -1,
    );
    $images = get_posts($args);

    foreach ($images as $image) {
        $image_path = get_attached_file($image->ID);
        $mime_type = get_post_mime_type($image->ID);
        $webp_image_path = iwpc_convert_to_webp($image_path, $mime_type);

        if ($webp_image_path) {
            wp_delete_file($image_path);
            update_attached_file($image->ID, $webp_image_path);
            $metadata = wp_generate_attachment_metadata($image->ID, $webp_image_path);
            wp_update_attachment_metadata($image->ID, $metadata);
            wp_update_post(array(
                'ID' => $image->ID,
                'post_mime_type' => 'image/webp',
            ));
        }
    }
}
