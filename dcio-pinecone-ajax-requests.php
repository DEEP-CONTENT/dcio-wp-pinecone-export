<?php


/**
 * Handles AJAX requests for uploading and deleting posts in the pinecone vector database.
 *
 * @global array $_POST The array of HTTP POST variables.
 */
function ajax_upload_delete_blob()
{

    $post_id = $_POST['post_id'] ?? 0;
    $pinecone_action = $_POST['pinecone_action'] ?? "";

    if (!$post_id) {
        wp_send_json(['success' => false]);
        wp_die();
    }

    $allowed_actions = ['add', 'delete', 'exclude-on', 'exclude-off'];

    if (!($pinecone_action) || !in_array($pinecone_action, $allowed_actions)) {
        wp_send_json(['success' => false, 'message' => 'Invalid pinecone_action value']);
        wp_die();
    }

    if ($pinecone_action === "exclude-on") {
        update_post_meta($post_id, 'exclude_pinecone_export', '1');
        //check if post is already exported
        $pinecone_exported = get_post_meta($post_id, 'dcio_pinecone_exported', true);
        if ($pinecone_exported) {
            $pinecone_action = "delete";
        } else {
            wp_send_json(['code' => 200, 'message' => 'Message Placeholder', 'status' => "success"]);
            wp_die();
        }
    } else if ($pinecone_action === "exclude-off") {
        delete_post_meta($post_id, 'exclude_pinecone_export');
        $pinecone_action = "add";
    }

    $post = get_post($post_id);

    $result = upload_delete_blob($post, $pinecone_action);
    wp_send_json(['result' => $result]);

    wp_die();
}
add_action('wp_ajax_dcio', 'ajax_upload_delete_blob');
