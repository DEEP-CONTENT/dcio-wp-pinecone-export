<?php


/**
 * Get categories from post_id
 * 
 * @param int $post_id
 * 
 * @return array<string>
 */
function get_categories_array($post_id)
{
    $categorys = get_the_category($post_id);
    $categorys_array = [];

    foreach ($categorys as $category) {
        array_push($categorys_array, $category->name);
    }

    return $categorys_array;
}


/**
 * Get tags from post_id
 * 
 * @param int $post_id
 * 
 * @return array<string>
 */
function get_tags_array($post_id)
{
    $tags = get_the_tags($post_id);

    if (!$tags) {
        return [];
    }

    $tags_array = [];

    foreach ($tags as $tag) {
        array_push($tags_array, $tag->name);
    }

    return $tags_array;
}


/**
 * This function takes a string in the format 'dd.mm.yyyy', splits it into day, month, and year, 
 * and checks if the date is valid. If valid, it returns an associative array with keys 'day', 'month', and 'year'.
 * If the input string is not in the correct format or the date is not valid, it returns an empty array.
 *
 * @param string $string The input string in the format 'dd.mm.yyyy'.
 * @return array An associative array with keys 'day', 'month', and 'year' if the date is valid, otherwise an empty array.
 */
function dcio_pinecone_get_day_month_year($string)
{
    $date = [];
    $date_string = explode('.', $string);

    if (count($date_string) === 3) {
        $day = intval($date_string[0]);
        $month = intval($date_string[1]);
        $year = intval($date_string[2]);

        if (checkdate($month, $day, $year)) {
            $date = ["day" => $day, "month" => $month, "year" => $year];
        }
    };

    return $date;
}


/**
 * Retrieves the IDs of posts that are to be exported.
 *
 * This function queries for posts of type 'post' that are published, and that do not have the meta keys 'exclude_pinecone_export'
 * and 'dcio_pinecone_exported'. It returns an array of the IDs of these posts.
 *
 * @return int[] An array of post IDs.
 */
function get_posts_ids_to_export()
{
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids', // This will return an array of post IDs
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'exclude_pinecone_export',
                'compare' => 'NOT EXISTS', // This will include posts that do not have the key
            ),
            array(
                'key' => 'dcio_pinecone_exported',
                'compare' => 'NOT EXISTS', // This will include posts that do not have the key
            ),
        ),
    );

    $post_ids  = get_posts($args);

    return $post_ids;
}


/**
 * Retrieves the number of posts that are exportable.
 *
 * This function queries for posts of type 'post' that are published, and that do not have the meta key 'exclude_pinecone_export'.
 * It returns the count of these posts.
 *
 * @return int The count of exportable posts.
 */
function get_number_of_exportable_posts()
{
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids', // This will return an array of post IDs
        'meta_query' => array(
            array(
                'key' => 'exclude_pinecone_export',
                'compare' => 'NOT EXISTS', // This will include posts that do not have the key
            ),
        ),
    );

    $post_ids  = get_posts($args);

    return count($post_ids);
}


/**
 * This function retrieves the number of published posts that have been exported to pinecone.
 * It uses a meta query to filter posts that have the 'dcio_pinecone_exported' meta key set to 1.
 *
 * @return int The number of exported posts.
 */
function get_number_exported_posts()
{
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids', // This will return an array of post IDs
        'meta_query' => array(
            array(
                'key' => 'dcio_pinecone_exported',
                'value' => 1,
                'compare' => '=',
            ),
        ),
    );

    $post_ids  = get_posts($args);

    return count($post_ids);
}


/**
 * Retrieves the count of posts that are marked to be excluded from pinecone export.
 *
 * This function queries for posts of type 'post' that are published and have the meta key 'exclude_pinecone_export' set to 1.
 * It returns the count of these posts.
 *
 * @return int The count of post IDs that are marked to be excluded from pinecone export.
 */
function get_number_exluded_posts()
{
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

    $post_ids  = get_posts($args);

    return count($post_ids);
}


/**
 * Chunk text into smaller pieces with overlap
 * 
 * @return string[]
 */
function chunkText(string $text, int $maxLength = 2000, string $separator = ' ', int $overlapWords = 10): array
{
    $text = trim($text);
    
    if (empty($text)) {
        return [];
    }
    if ($maxLength <= 0 || $overlapWords < 0) {
        return [];
    }

    if ($separator === '') {
        return [];
    }

    if (strlen($text) <= $maxLength) {
        return [$text];
    }

    $chunks = [];
    $words = explode($separator, $text);
    $currentChunk = [];

    // Loop through each word and add it to the current chunk
    foreach ($words as $word) {
        // If the current chunk plus the new word is less than the max length, add the word to the current chunk
        if (strlen(implode($separator, $currentChunk) . $separator . $word) <= $maxLength || empty($currentChunk)) {
            $currentChunk[] = $word;
        } else {
            // If the current chunk plus the new word is greater than the max length, add the current chunk to the chunks array and start a new chunk
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


/**
 * Get embeddings from OpenAI
 * 
 * @param string[] $chunks
 * @return float[]
 */
function getEmmbeddings($chunks)
{
    $OPENAI_API_KEY = $_ENV['OPENAI_API_KEY'];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/embeddings');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "input" => $chunks,
        "model" => "text-embedding-3-large",
        "encoding_format" => "float"
    ]));

    $headers = array();
    $headers[] = 'Authorization: Bearer ' . $OPENAI_API_KEY;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($result, true);
}


/**
 * Create vectors for Pinecone
 * 
 * @param string[] $chunks
 * @param float[] $embeddings
 * @param WP_Post $post
 * @return array
 */
function createVectors($chunks, $embeddings, $post)
{
    $vectors = [];
    $hasChunks = count($chunks) > 1;
    
    $date_pubblished = explode(" ", $post->post_modified ?? $post->post_date)[0];
    $timestamp = strtotime($date_pubblished);

    $language = explode("_", get_locale())[0];

    foreach ($chunks as $index => $chunk) {
        $vectors[] = [
            $addition = $hasChunks ? "#chunk" . strval($index + 1) : "",
            "id" => strval($post->ID) . $addition,
            "values" => $embeddings['data'][$index]['embedding'],
            "metadata" => [
                "author" => $post->post_author,
                "categories" => get_categories_array($post->ID),
                "date" => $date_pubblished,
                "id" => $post->ID,
                "language" => $language,
                "tags" => get_tags_array($post->ID) ?? [],
                "text" => $chunk,
                'timestamp' => $timestamp,
                "title" => $post->post_title,
                "url" => get_permalink($post->ID),
            ]
        ];
    }
    return $vectors;
}


/**
 * Checks if a given cURL request was successful.
 *
 * @param CurlHandle|resource $ch The cURL resource.
 *
 * @return bool True if the request was successful, false otherwise.
 */
function is_request_error($ch)
{

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


/**
 * Delete parent and child Vectors from Pinecone by id
 * 
 * @param string $id
 */
function deleteVectorFromPinecone($id)
{
    $id = strval($id);


    $api_key = $_ENV['PINECONE_API_KEY'];
    $index_host = $_ENV['PINECONE_HOST'];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "$index_host/vectors/list?namespace=posts&prefix=$id#");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Api-Key: $api_key",
    ));

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

    // Get the vector IDs
    $vectorIds = array_map(function ($vector) {
        return $vector['id'];
    }, $responseData['vectors']);


    // Now, delete the records by ID
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "$index_host/vectors/delete");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Api-Key: $api_key",
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        'ids' => $vectorIds,
        'namespace' => 'posts',
    )));

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


/**
 * Upload vectors to Pinecone
 * 
 * @param array $vectors
 * @return array
 */
function uploadVectorsToPinecone($vectors)
{
    $api_key = $_ENV['PINECONE_API_KEY'];
    $index_host = $_ENV['PINECONE_HOST'];
    
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "$index_host/vectors/upsert");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "vectors" => $vectors,
        "namespace" => "posts"
    ]));

    $headers = array();
    $headers[] = "Api-Key: $api_key";
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_exec($ch);

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (is_request_error($ch)) {
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
