<?php
/**
 * Plugin Name: Dropzone Uploader for Image Posts with Assignment Slug
 * Description: A Dropzone.js uploader where files are only uploaded after clicking an "Upload" button.
 * Version: 1.5
 * Author: Seth Gitner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Enqueue scripts and styles
function dropzone_enqueue_scripts() {
    wp_enqueue_script( 'dropzone', 'https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js', [], null, true );
    wp_enqueue_style( 'dropzone-css', 'https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css', [], null );
    wp_enqueue_style( 'emg-style', plugin_dir_url( __FILE__ ) . 'css/emg-style.css' );
    wp_enqueue_script( 'dropzone-custom-js', plugin_dir_url( __FILE__ ) . 'js/dropzone-custom.js', [ 'jquery', 'dropzone' ], null, true );

    // Localize the script with AJAX URL and nonce
    wp_localize_script( 'dropzone-custom-js', 'dropzoneUploader', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'dropzone_uploader_nonce' ),
    ]);
}
add_action( 'wp_enqueue_scripts', 'dropzone_enqueue_scripts' );

// Register Dropzone shortcode
function dropzone_uploader_shortcode() {
    ob_start(); ?>
    <form id="dropzoneUploader" class="dropzone" method="post" enctype="multipart/form-data">
        <label for="assignment-slug">Assignment Slug:</label>
        <input type="text" id="assignment-slug" name="assignment_slug" placeholder="Enter Assignment Slug" required />
        <select id="image-category" name="category" required>
            <option value="">Select Category</option>
            <?php
            $categories = get_categories( [ 'hide_empty' => false ] );
            foreach ( $categories as $category ) {
                echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
            }
            ?>
        </select>
        <button type="button" id="uploadButton" class="button button-primary">Upload</button>
    </form>
    <div id="uploadFeedback"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'dropzone_uploader', 'dropzone_uploader_shortcode' );

// Handle image uploads via AJAX
function dropzone_handle_upload() {
    check_ajax_referer( 'dropzone_uploader_nonce', 'security' );

    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( empty( $_FILES['file']['name'] ) || ! isset( $_POST['category'] ) || ! isset( $_POST['assignment_slug'] ) ) {
        wp_send_json_error( [ 'message' => 'Invalid request.' ] );
    }

    $category_id = intval( $_POST['category'] );
    $assignment_slug = sanitize_text_field( $_POST['assignment_slug'] );
    $uploaded_files = [];

    // Check if a post with the same slug already exists
    $existing_post = get_page_by_title( $assignment_slug, OBJECT, 'post' );

    // If no post exists, create a new one
    if ( ! $existing_post ) {
        $post_id = wp_insert_post( [
            'post_title'   => $assignment_slug,
            'post_content' => '',
            'post_status'  => 'publish',
        ]);

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Failed to create post: ' . $post_id->get_error_message() ] );
        }

        wp_set_post_categories( $post_id, [ $category_id ] );
    } else {
        $post_id = $existing_post->ID;
    }

    // Process all uploaded files
    foreach ( $_FILES['file']['name'] as $key => $value ) {
        if ( $_FILES['file']['error'][$key] === 0 ) {
            $file = [
                'name'     => $_FILES['file']['name'][$key],
                'type'     => $_FILES['file']['type'][$key],
                'tmp_name' => $_FILES['file']['tmp_name'][$key],
                'error'    => $_FILES['file']['error'][$key],
                'size'     => $_FILES['file']['size'][$key],
            ];

            $upload = wp_handle_upload( $file, [ 'test_form' => false ] );

            if ( isset( $upload['error'] ) ) {
                continue; // Skip this file if upload fails
            }

            $attachment_id = wp_insert_attachment( [
                'guid'           => $upload['url'],
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name( $file['name'] ),
                'post_status'    => 'inherit',
            ], $upload['file'] );

            if ( is_wp_error( $attachment_id ) ) {
                continue; // Skip this file if attachment creation fails
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';
            $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
            wp_update_attachment_metadata( $attachment_id, $metadata );

            $uploaded_files[] = $attachment_id;
        }
    }

    if ( empty( $uploaded_files ) ) {
        wp_send_json_error( [ 'message' => 'No files were uploaded.' ] );
    }

    // Append uploaded images to the existing post content
    $gallery_content = '';
    foreach ( $uploaded_files as $attachment_id ) {
        $image_url = wp_get_attachment_image_src( $attachment_id, 'full' )[0];
        $thumbnail_url = wp_get_attachment_image_src( $attachment_id, 'medium' )[0];
        $title = get_the_title( $attachment_id );

        $gallery_content .= '<div class="emg-item">';
        $gallery_content .= '<a href="' . esc_url( $image_url ) . '" data-lightbox="gallery" data-title="' . esc_attr( $title ) . '">';
        $gallery_content .= '<img src="' . esc_url( $thumbnail_url ) . '" alt="' . esc_attr( $title ) . '">';
        $gallery_content .= '</a>';
        $gallery_content .= '<p class="emg-filename">' . esc_html( $title ) . '</p>';
        $gallery_content .= '<a href="' . esc_url( $image_url ) . '" download class="emg-download-link">';
        $gallery_content .= '<i class="fa-solid fa-download"></i> Download Image';
        $gallery_content .= '</a>';
        $gallery_content .= '</div>';
    }

    $current_content = get_post_field( 'post_content', $post_id );
    $updated_content = $current_content . $gallery_content;

    $result = wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $updated_content,
    ]);

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => 'Failed to update post: ' . $result->get_error_message() ] );
    }

    wp_send_json_success( [ 'message' => 'Images uploaded successfully!', 'post_id' => $post_id ] );
}
add_action( 'wp_ajax_dropzone_upload', 'dropzone_handle_upload' );
add_action( 'wp_ajax_nopriv_dropzone_upload', 'dropzone_handle_upload' );