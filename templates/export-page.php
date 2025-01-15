<?php
$number_exported_posts = get_number_exported_posts();
$number_exportable_posts = get_number_of_exportable_posts();
$number_exluded_post = get_number_exluded_posts();
?>


<div class="dcio-export-pinecone wrap">
    <h1 class="wp-heading-inline">DC/IO Pinecone Export</h1>
    <?php if ($number_exported_posts != $number_exportable_posts) : ?>
        <button class="dcio-export-pinecone__button page-title-action">Start Export</button>
    <?php endif; ?>
    <p><b><span id="progress-count"><?= $number_exported_posts  ?></span> of <?= $number_exportable_posts  ?></b> articles have been exported. <b><?= $number_exluded_post ?></b> articles are excluded from export.</p>
    <div id="progress-text"></div>
</div>