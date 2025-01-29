<?php

class DCIO_Pinecone_Handler {
    private $openai_api_key;
    private $pinecone_api_key;
    private $pinecone_host;

    public function __construct() {
        $this->openai_api_key = get_option('dcio_openai_api_key');
        $this->pinecone_api_key = get_option('dcio_pinecone_api_key');
        $this->pinecone_host = get_option('dcio_pinecone_host');
    }

    public function get_categories_array(int $post_id): array {
        $categories = get_the_category($post_id);
        $categories_array = [];

        foreach ($categories as $category) {
            $categories_array[] = $category->name;
        }

        return $categories_array;
    }

    public function get_tags_array(int $post_id): array {
        $tags = get_the_tags($post_id);

        if (!$tags) {
            return [];
        }

        $tags_array = [];
        foreach ($tags as $tag) {
            $tags_array[] = $tag->name;
        }

        return $tags_array;
    }

    public function get_day_month_year(string $string): array {
        $date = [];
        $date_string = explode('.', $string);

        if (count($date_string) === 3) {
            $day = intval($date_string[0]);
            $month = intval($date_string[1]);
            $year = intval($date_string[2]);

            if (checkdate($month, $day, $year)) {
                $date = ["day" => $day, "month" => $month, "year" => $year];
            }
        }

        return $date;
    }

    public function get_posts_ids_to_export(): array {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'exclude_pinecone_export',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'dcio_pinecone_exported',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        return get_posts($args);
    }

    public function get_number_of_exportable_posts(): int {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'exclude_pinecone_export',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $post_ids = get_posts($args);
        return count($post_ids);
    }

    public function get_number_exported_posts(): int {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'dcio_pinecone_exported',
                    'value' => 1,
                    'compare' => '=',
                ),
            ),
        );

        $post_ids = get_posts($args);
        return count($post_ids);
    }

    public function get_number_excluded_posts(): int {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'exclude_pinecone_export',
                    'value' => 1,
                    'compare' => '=',
                ),
            ),
        );

        $post_ids = get_posts($args);
        return count($post_ids);
    }

    public function chunkText(string $text, int $maxLength = 2000, string $separator = ' ', int $overlapWords = 10): array {
        $text = trim($text);
        
        if (empty($text) || $maxLength <= 0 || $overlapWords < 0 || $separator === '') {
            return [];
        }

        if (strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $words = explode($separator, $text);
        $currentChunk = [];

        foreach ($words as $word) {
            if (strlen(implode($separator, $currentChunk) . $separator . $word) <= $maxLength || empty($currentChunk)) {
                $currentChunk[] = $word;
            } else {
                $chunks[] = implode($separator, $currentChunk);
                $currentChunk = array_slice($currentChunk, -$overlapWords);
                $currentChunk[] = $word;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode($separator, $currentChunk);
        }

        return $chunks;
    }

    public function getEmbeddings(array $chunks): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/embeddings');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "input" => $chunks,
            "model" => "text-embedding-3-large",
            "encoding_format" => "float"
        ]));

        $headers = [
            'Authorization: Bearer ' . $this->openai_api_key,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('OpenAI API Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($result, true);
    }

    public function createVectors(array $chunks, array $embeddings, WP_Post $post): array {
        $vectors = [];
        $hasChunks = count($chunks) > 1;
        
        $date_published = explode(" ", $post->post_modified ?? $post->post_date)[0];
        $timestamp = strtotime($date_published);
        $language = explode("_", get_locale())[0];

        foreach ($chunks as $index => $chunk) {
            $vectors[] = [
                $addition = $hasChunks ? "#chunk" . strval($index + 1) : "",
                "id" => strval($post->ID) . $addition,
                "values" => $embeddings['data'][$index]['embedding'],
                "metadata" => [
                    "author" => $post->post_author,
                    "categories" => $this->get_categories_array($post->ID),
                    "date" => $date_published,
                    "id" => $post->ID,
                    "language" => $language,
                    "tags" => $this->get_tags_array($post->ID),
                    "text" => $chunk,
                    'timestamp' => $timestamp,
                    "title" => $post->post_title,
                    "url" => get_permalink($post->ID),
                ]
            ];
        }
        return $vectors;
    }

    private function is_request_error($ch): bool {
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            set_transient('dcio_admin_notice_error', curl_error($ch), 45);
            return true;
        }

        if ($httpcode < 200 || $httpcode > 299) {
            set_transient('dcio_admin_notice_error', 'The post could not be added to Pinecone. Actual response code: ' . $httpcode, 45);
            return true;
        }

        return false;
    }

    public function deleteVectorFromPinecone(string $id): array {
        $id = strval($id);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->pinecone_host}/vectors/list?namespace=posts&prefix=$id#");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Api-Key: {$this->pinecone_api_key}"]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if(empty($responseData['vectors'])) {
            return [
                'code' => $code,
                'status' => 'success',
                'message' => "No post with id $id found in Pinecone"
            ];
        }

        $vectorIds = array_map(function ($vector) {
            return $vector['id'];
        }, $responseData['vectors']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->pinecone_host}/vectors/delete");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: {$this->pinecone_api_key}",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'ids' => $vectorIds,
            'namespace' => 'posts',
        ]));

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $code < 200 || $code > 299) {
            $result = [
                'code' => $code,
                'status' => 'error',
                'message' => curl_error($ch) ?? 'The post could not be deleted from Pinecone'
            ];
        } else {
            $result = [
                'code' => $code,
                'status' => 'success',
                'message' => 'The post was deleted from Pinecone'
            ];
        }

        curl_close($ch);
        return $result;
    }

    public function uploadVectorsToPinecone(array $vectors): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->pinecone_host}/vectors/upsert");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "vectors" => $vectors,
            "namespace" => "posts"
        ]));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: {$this->pinecone_api_key}",
            'Content-Type: application/json'
        ]);

        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->is_request_error($ch)) {
            $result = [
                'code' => $code,
                'status' => 'error',
                'message' => curl_error($ch)
            ];
        } else {
            $result = [
                'code' => $code,
                'status' => 'success',
                'message' => 'The post was added to Pinecone'
            ];
        }

        curl_close($ch);
        return $result;
    }
}
