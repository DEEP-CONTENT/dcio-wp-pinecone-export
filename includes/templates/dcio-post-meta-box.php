<?php
$value = get_post_meta($post->ID, 'exclude_pinecone_export', true);
?>

<div class="pinecone-export">
    <label class="pinecone-export__label" for="exclude_pinecone_export">Exclude from Pinecone Export</label>
    <input class="pinecone-export__input" type="checkbox" id="exclude_pinecone_export" name="exclude_pinecone_export" value="1" <?php checked($value, true); ?> />
</div>