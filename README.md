Wordpress plugin that stores newly created or updated articles in a pinecone vector databases.
Articles whose status changes to not published are automatically deleted from pinecone storage by the plugin.

Please store the data required for access to the Pinecone Database in an .env file in the same directory.

Example for the .env file:

```
OPENAI_API_KEY=''
PINECONE_API_KEY=''
PINECONE_HOST=''
```
