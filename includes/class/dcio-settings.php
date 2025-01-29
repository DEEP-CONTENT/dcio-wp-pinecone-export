<?php
class DCIO_Pinecone_Export_Settings {
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function register_settings() {
        register_setting('dcio_pinecone_options', 'dcio_openai_api_key');
        register_setting('dcio_pinecone_options', 'dcio_pinecone_api_key');
        register_setting('dcio_pinecone_options', 'dcio_pinecone_host');
    }

    public static function get_openai_api_key() {
        return get_option('dcio_openai_api_key');
    }

    public static function get_pinecone_api_key() {
        return get_option('dcio_pinecone_api_key');
    }

    public static function get_pinecone_host() {
        return get_option('dcio_pinecone_host');
    }
}