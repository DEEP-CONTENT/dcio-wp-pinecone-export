Wordpress plugin that stores newly created or updated articles in a pinecone vector databases.
Articles whose status changes to not published are automatically deleted from pinecone storage by the plugin.

Settings:
![thumbnail_image](https://github.com/user-attachments/assets/308832bd-7173-4936-b251-16fc0dcc377a)

Export of all articles to the vector database is possible as a batch process via the WP-CLI with the command:
```bash
wp heise-io export
```

Only articles that are not excluded and have not yet been exported are exported.
