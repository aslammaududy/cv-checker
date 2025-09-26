<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Milvus Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Milvus vector database connection details.
    | These settings will be used by the HelgeSverre/milvus package to
    | connect to your Milvus instance running in Docker.
    |
    */

    'host' => env('MILVUS_HOST', 'localhost'),
    'port' => env('MILVUS_PORT', '19530'),
    'token' => env('MILVUS_TOKEN', ''),
    'database' => env('MILVUS_DATABASE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default Collection Settings
    |--------------------------------------------------------------------------
    |
    | You can define default settings for collections here.
    |
    */

    'default_collection' => env('MILVUS_DEFAULT_COLLECTION', 'documents'),
    'vector_dimension' => env('MILVUS_VECTOR_DIMENSION', 768),

];