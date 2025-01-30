<div class="wrap">
    <h1>Heise I/O Export Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('dcio_pinecone_options');
        do_settings_sections('dcio_pinecone_options');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">OpenAI API Key</th>
                <td>
                    <input type="password" name="dcio_openai_api_key"
                        value="<?php echo esc_attr(get_option('dcio_openai_api_key')); ?>"
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Pinecone API Key</th>
                <td>
                    <input type="password" name="dcio_pinecone_api_key"
                        value="<?php echo esc_attr(get_option('dcio_pinecone_api_key')); ?>"
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Pinecone Host</th>
                <td>
                    <input type="text" name="dcio_pinecone_host"
                        value="<?php echo esc_attr(get_option('dcio_pinecone_host')); ?>"
                        class="regular-text">
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>