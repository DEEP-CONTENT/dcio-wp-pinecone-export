<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implements WP-CLI commands for Pinecone vector database export
 */
class DCIO_WP_CLI_Command extends WP_CLI_Command
{

    private $pinecone_export;

    public function __construct()
    {
        $this->pinecone_export = DCIO_Pinecone_Export::get_instance();
    }

    private function has_required_credentials()
    {
        $pinecone_api_key = get_option('dcio_pinecone_api_key');
        $openai_api_key = get_option('dcio_openai_api_key');
        $pinecone_host = get_option('dcio_pinecone_host');

        return ($pinecone_api_key && $openai_api_key && $pinecone_host);
    }

    /**
     * Exports all published posts to the Pinecone vector database that are not already exported or marked as excluded.
     * 
     * ## OPTIONS
     * 
     * [--batch-size=<number>]
     * : Number of posts to process per batch (default: 10)
     * 
     * [--post-type=<type>]
     * : Post type to export (default: post)
     * 
     * ## EXAMPLES
     * 
     *     wp heise-io export --batch-size=20
     *     wp heise-io export --post-type=page
     */
    public function export($args, $assoc_args)
    {
        if (!$this->has_required_credentials()) {
            WP_CLI::error('API credentials not configured. Please check plugin settings.');
            return;
        }

        $batch_size = $assoc_args['batch-size'] ?? 10;
        $post_type = $assoc_args['post-type'] ?? 'post';

        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'exclude_pinecone_export',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'dcio_pinecone_exported',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);


        $total_posts = count($posts);
        WP_CLI::line(sprintf('Found %d posts to process', $total_posts));

        if ($total_posts === 0) {
            WP_CLI::success('No new posts to export');
            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Exporting posts to Pinecone', $total_posts);

        foreach (array_chunk($posts, $batch_size) as $batch) {
            foreach ($batch as $post_id) {
                $post = get_post($post_id);

                $result = $this->pinecone_export->upload_delete_blob($post, 'add');

                if ($result['status'] === 'success') {
                    update_post_meta($post_id, 'pinecone_exported', true);
                    $progress->tick();
                } else {
                    WP_CLI::warning(sprintf('Failed to export post %d: %s', $post_id, $result['message']));
                    $progress->tick();
                }

                // Add rate limiting delay
                usleep(100000); // 100ms delay between requests
            }

            // Add larger delay between batches
            sleep(1); // 1 second delay between batches
        }

        $progress->finish();
        WP_CLI::success('Export completed');
    }
}
