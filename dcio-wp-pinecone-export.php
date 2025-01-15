<?php
/*
Plugin Name: DC/IO Pinecone Export
Description: Uploads/Delete articles to a Pinecone vector database
Version: 1.0
Author: Sebastian Seypt
*/

require 'vendor/autoload.php';
require 'dcio-pinecone-ajax-requests.php';
require 'dcio-pinecone-functions.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


/**
 * Enqueues styles and scripts for the dcio_pinecone_export plugin.
 *
 * This function is hooked into the 'admin_enqueue_scripts' action and will
 * enqueue styles and scripts for the dcio_pinecone_export plugin. It also localizes
 * the 'dcio_pinecone_export_script' script with data for AJAX requests.
 *
 * @param string $hook The current admin page's hook suffix.
 */
function enqueue_dcio_pinecone_export_styles_and_scripts($hook)
{
    wp_enqueue_style('dcio-pinecone-export', plugins_url('css/dcio-pinecone-export.css', __FILE__));

    if ('toplevel_page_dcio_pinecone_article_view' == $hook) {
        wp_enqueue_style('dcio-pinecone-export-page', plugins_url('css/dcio-pinecone-article-view.css', __FILE__));
        wp_enqueue_style('dcio_datepicker_style', plugins_url('css/jquery-ui.min.css', __FILE__));

        wp_enqueue_script('dcio_datepicker_script', plugins_url('js/jquery-ui.min.js', __FILE__), array('jquery'), '1.0', true);
        wp_enqueue_script('dcio-pinecone-article-view_script', plugins_url('js/dcio-pinecone-article-view.js', __FILE__), array('jquery'), '1.0', true);

        $array = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        );
        wp_localize_script("dcio-pinecone-article-view_script", "dciopineconeExport", $array);
    }

    if ('pinecone-export_page_dcio_pinecone_export_page' == $hook) {
        wp_enqueue_script('dcio_pinecone_export_page_script', plugins_url('js/dcio-pinecone-export-page.js', __FILE__), array('jquery'), '1.0', true);

        $array = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'postsIdsToExport' => get_posts_ids_to_export(),
        );

        wp_localize_script("dcio_pinecone_export_page_script", "dciopineconeExport", $array);
    }
}
add_action('admin_enqueue_scripts', 'enqueue_dcio_pinecone_export_styles_and_scripts');


/**
 * Renders the 'article-view' page in the WordPress admin menu.
 *
 * @return void
 */
function dcio_pinecone_article_view_render()
{
    include 'templates/article-view.php';
}

/**
 * Renders the 'export-page' in the WordPress admin menu.
 *
 * @return void
 */
function dcio_pinecone_export_page_render()
{
    include 'templates/export-page.php';
}


/**
 * Adds a new page to the WordPress admin menu.
 *
 * This function uses the WordPress function add_menu_page() to add a new page to the admin menu.
 * The new page is titled 'DC/IO pinecone Export' and its content is rendered by the function 'dcio_pinecone_export_menu_page_render'.
 * The page is added to the 'admin_menu' action hook, so it will be added when the admin menu is built.
 *
 * @see https://developer.wordpress.org/reference/functions/add_menu_page/
 *
 * @return void
 */
function dcio_pinecone_export_menu_page()
{
    add_menu_page(
        'DC/IO Pinecone Export', // page title
        'Pinecone Export', // menu title
        'manage_options', // capability
        'dcio_pinecone_article_view', // menu slug
        'dcio_pinecone_article_view_render', // function 
        'dashicons-upload', // icon url
        4 // position
    );

    // add_submenu_page(
    //     'dcio_pinecone_article_view', // parent slug
    //     'Export All', // page title
    //     'Export All', // menu title
    //     'manage_options', // capability
    //     'dcio_pinecone_export_page', // menu slug
    //     'dcio_pinecone_export_page_render' // function
    // );
}
add_action('admin_menu', 'dcio_pinecone_export_menu_page');



/**
 * Uploads or deletes a blob in pinecone storage.
 *
 * @param WP_Post $post   The WordPress post object.
 * @param string  $action The action to perform, either "delete" or "upload".
 *
 * @return bool True if the blob was uploaded or deleted successfully, false otherwise.
 */
function upload_delete_blob($post, $action)
{

    if ($action === "delete") {
        $result = deleteVectorFromPinecone($post->ID);

        if ($result["status"] === "success") {
            delete_post_meta(
                $post->ID,
                'dcio_pinecone_exported'
            );
        }

        return $result;
    }

    if (array_key_exists('exclude_pinecone_export', $_POST) && $_POST['exclude_pinecone_export'] == '1') {
        return [
            "code" => "200",
            "status" => 'success',
            "message" => "Post is excluded from Pinecone export"
        ];
    }

    $chunks = chunkText(strip_tags($post->post_content));
    $embeddings = getEmmbeddings($chunks);
    $vectors = createVectors($chunks, $embeddings, $post);

    $result = deleteVectorFromPinecone($post->ID);
    if ($result["status"] === "error") {
        return $result;
    }

    $result = uploadVectorsToPinecone($vectors);
    if ($result["status"] === "success") {
        update_post_meta(
            $post->ID,
            'dcio_pinecone_exported',
            '1'
        );
    }

    return $result;
}


/**
 * Handles the transition of post status, uploading or deleting a blob in pinecone storage.
 *
 * @param string  $new_status The new status of the post.
 * @param string  $old_status The old status of the post.
 * @param WP_Post $post       The WordPress post object.
 *
 * @return void
 */
function dcio_pinecone_export_on_post_status_change($new_status, $old_status, $post)
{

    if ($new_status == 'publish') {
        upload_delete_blob($post, "add");
    } else {
        if (!get_post_meta($post->ID, 'dcio_pinecone_exported', true)) return;

        upload_delete_blob($post, "delete");
    }
}
add_action('transition_post_status', 'dcio_pinecone_export_on_post_status_change', 10, 3);


/**
 * Displays an admin notice error message.
 *
 * This function checks if a transient 'dcio_admin_notice_error' is set.
 * If it is, it displays the error message and then deletes the transient.
 *
 * @return void
 */
function dcio_admin_notice__error()
{
    // Check if our transient is set
    if (get_transient('dcio_admin_notice_error')) {
        // Display the error message and delete the transient
        $class = 'notice notice-error';
        $message = __(get_transient('dcio_admin_notice_error'), 'sample-text-domain');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        delete_transient('dcio_admin_notice_error');
    }
}
add_action('admin_notices', 'dcio_admin_notice__error');


/**
 * Adds a meta box to the post editing screen in the WordPress admin area.
 *
 * The meta box has the title 'pinecone Export' and displays the content returned by the 'pinecone_export_meta_box_html' callback function.
 *
 * @return void
 */
function pinecone_export_meta_box()
{
    add_meta_box(
        'pinecone_export_meta_box_id',          // Unique ID
        'Pinecone Export',                      // Box title
        'pinecone_export_meta_box_html',        // Content callback, must be of type callable
        'post'                               // Post type
    );
}
add_action('add_meta_boxes', 'pinecone_export_meta_box');


/**
 * Displays the HTML for the pinecone Export meta box.
 *
 * This function retrieves the post meta 'exclude_pinecone_export' for the given post and displays a checkbox input.
 * If the post meta value is true, the checkbox is checked.
 *
 * @param WP_Post $post The WordPress post object.
 *
 * @return void
 */
function pinecone_export_meta_box_html($post)
{
    include 'templates/post-meta-box.php';
}


/**
 * Saves the 'exclude_pinecone_export' post meta value when a post is saved.
 *
 * This function checks if 'exclude_pinecone_export' is in the $_POST array. If it is and its value is '1', 
 * it updates the post meta 'exclude_pinecone_export' with the value from $_POST. If the value is not '1', 
 * it deletes the post meta. If 'exclude_pinecone_export' is not in the $_POST array, it also deletes the post meta.
 *
 * @param int $post_id The ID of the post being saved.
 *
 * @return void
 */
function save_pinecone_export_meta_box($post_id)
{
    if (array_key_exists('exclude_pinecone_export', $_POST)) {
        if ($_POST['exclude_pinecone_export'] == '1') {
            update_post_meta(
                $post_id,
                'exclude_pinecone_export',
                $_POST['exclude_pinecone_export']
            );


            //check if post is already in pinecone
            $exported = get_post_meta($post_id, 'dcio_pinecone_exported', true);
            if ($exported) {
                $post = get_post($post_id);
                upload_delete_blob($post, "delete");
            }
        } else {
            delete_post_meta(
                $post_id,
                'exclude_pinecone_export'
            );
        }

        return;
    }

    delete_post_meta(
        $post_id,
        'exclude_pinecone_export'
    );
}
add_action('save_post', 'save_pinecone_export_meta_box');
