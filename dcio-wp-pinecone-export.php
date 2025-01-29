<?php
/*
Plugin Name: heise I/O Export
Description: Uploads/Delete articles to a vector database
Version: 1.0
Author: Sebastian Seypt
*/

/**
 * Main plugin class for heise I/O Export functionality.
 */
class DCIO_Pinecone_Export
{
    private static $instance = null;
    private $pinecone_handler;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->pinecone_handler = new DCIO_Pinecone_Handler();
        $this->init_hooks();
    }

    /**
     * Get the Pinecone handler instance
     * @return DCIO_Pinecone_Handler
     */
    public function get_pinecone_handler()
    {
        return $this->pinecone_handler;
    }

    private function load_dependencies()
    {
        require plugin_dir_path(__FILE__) . 'includes/class/dcio-settings.php';
        require plugin_dir_path(__FILE__) . 'includes/class/dcio-pinecone-handler.php';
    }

    private function init_hooks()
    {
        add_action('init', array('DCIO_Pinecone_Export_Settings', 'init'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('admin_notices', array($this, 'display_admin_notice_error'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('wp_ajax_dcio', array($this, 'ajax_upload_delete_blob'));
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'DC/IO Pinecone Export',
            'heise I/O Export',
            'manage_options',
            'dcio_pinecone_article_view',
            array($this, 'render_article_view_page'),
            'dashicons-upload',
            4
        );

        add_submenu_page(
            'dcio_pinecone_article_view',
            'Settings',
            'Settings',
            'manage_options',
            'dcio_pinecone_settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_assets($hook)
    {
        wp_enqueue_style('dcio-pinecone-export', plugins_url('assets/css/dcio-pinecone-export.css', __FILE__));

        if ('toplevel_page_dcio_pinecone_article_view' == $hook) {
            $this->enqueue_article_view_assets();
        }

        if ('pinecone-export_page_dcio_pinecone_export_page' == $hook) {
            $this->enqueue_export_page_assets();
        }
    }

    private function enqueue_article_view_assets()
    {
        wp_enqueue_style('dcio-pinecone-export-page', plugins_url('assets/css/dcio-pinecone-article-view.css', __FILE__));
        wp_enqueue_style('dcio_datepicker_style', plugins_url('assets/css/jquery-ui.min.css', __FILE__));
        wp_enqueue_script('dcio_datepicker_script', plugins_url('assets/js/jquery-ui.min.js', __FILE__), array('jquery'), '1.0', true);
        wp_enqueue_script('dcio-pinecone-article-view_script', plugins_url('assets/js/dcio-pinecone-article-view.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script("dcio-pinecone-article-view_script", "dciopineconeExport", array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    private function enqueue_export_page_assets()
    {
        wp_enqueue_script('dcio_pinecone_export_page_script', plugins_url('js/dcio-pinecone-export-page.js', __FILE__), array('jquery'), '1.0', true);
        wp_localize_script("dcio_pinecone_export_page_script", "dciopineconeExport", array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'postsIdsToExport' => $this->pinecone_handler->get_posts_ids_to_export(),
        ));
    }

    public function ajax_upload_delete_blob()
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
        $result = $this->upload_delete_blob($post, $pinecone_action);
        wp_send_json(['result' => $result]);
        wp_die();
    }

    public function upload_delete_blob($post, $action)
    {
        if ($action === "delete") {
            $result = $this->pinecone_handler->deleteVectorFromPinecone($post->ID);
            if ($result["status"] === "success") {
                delete_post_meta($post->ID, 'dcio_pinecone_exported');
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

        $chunks = $this->pinecone_handler->chunkText(strip_tags($post->post_content));
        $embeddings = $this->pinecone_handler->getEmbeddings($chunks);
        $vectors = $this->pinecone_handler->createVectors($chunks, $embeddings, $post);

        $result = $this->pinecone_handler->deleteVectorFromPinecone($post->ID);
        if ($result["status"] === "error") {
            return $result;
        }

        $result = $this->pinecone_handler->uploadVectorsToPinecone($vectors);
        if ($result["status"] === "success") {
            update_post_meta($post->ID, 'dcio_pinecone_exported', '1');
        }

        return $result;
    }

    public function render_article_view_page()
    {
        include plugin_dir_path(__FILE__) . 'includes/templates/dcio-article-view.php';
    }

    public function render_export_page()
    {
        include plugin_dir_path(__FILE__) . 'includes/templates/dcio-export-page.php';
    }

    public function render_settings_page()
    {
        include plugin_dir_path(__FILE__) . 'includes/templates/dcio-settings.php';
    }

    public function handle_post_status_change($new_status, $old_status, $post)
    {
        if ($new_status == 'publish') {
            $this->upload_delete_blob($post, "add");
        } else {
            if (!get_post_meta($post->ID, 'dcio_pinecone_exported', true)) return;
            $this->upload_delete_blob($post, "delete");
        }
    }

    public function display_admin_notice_error()
    {
        if ($error = get_transient('dcio_admin_notice_error')) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__($error, 'sample-text-domain')
            );
            delete_transient('dcio_admin_notice_error');
        }
    }

    public function add_meta_box()
    {
        add_meta_box(
            'pinecone_export_meta_box_id',
            'Pinecone Export',
            array($this, 'render_meta_box'),
            'post'
        );
    }

    public function render_meta_box($post)
    {
        include plugin_dir_path(__FILE__) . 'includes/templates/dcio-post-meta-box.php';
    }

    public function save_meta_box_data($post_id)
    {
        if (array_key_exists('exclude_pinecone_export', $_POST)) {
            if ($_POST['exclude_pinecone_export'] == '1') {
                update_post_meta($post_id, 'exclude_pinecone_export', $_POST['exclude_pinecone_export']);

                $exported = get_post_meta($post_id, 'dcio_pinecone_exported', true);
                if ($exported) {
                    $post = get_post($post_id);
                    $this->upload_delete_blob($post, "delete");
                }
            } else {
                delete_post_meta($post_id, 'exclude_pinecone_export');
            }
            return;
        }
        delete_post_meta($post_id, 'exclude_pinecone_export');
    }
}

// Initialize the plugin
function DCIO_Pinecone_Export_Init()
{
    return DCIO_Pinecone_Export::get_instance();
}

DCIO_Pinecone_Export_Init();
