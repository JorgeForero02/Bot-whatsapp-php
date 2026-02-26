<?php

return [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'name' => getenv('DB_NAME') ?: 'whatsapp_rag_bot',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4'
    ],
    
    'whatsapp' => [
        'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
        'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
        'verify_token' => getenv('WHATSAPP_VERIFY_TOKEN') ?: 'your_verify_token_here',
        'api_version' => 'v18.0',
        'base_url' => 'https://graph.facebook.com'
    ],
    
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-3.5-turbo',
        'embedding_model' => getenv('OPENAI_EMBEDDING_MODEL') ?: 'text-embedding-ada-002',
        'temperature' => 0.7,
        'max_tokens' => 500
    ],
    
    'rag' => [
        'chunk_size' => 500,
        'chunk_overlap' => 50,
        'top_k_results' => 3,
        'similarity_threshold' => 0.7,
        'similarity_method' => 'cosine'
    ],
    
    'uploads' => [
        'path' => __DIR__ . '/../uploads',
        'max_size' => 10485760,
        'allowed_types' => ['pdf', 'txt', 'docx']
    ],
    
    'google_calendar' => require __DIR__ . '/calendar.php',
    
    'app' => [
        'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost',
        'timezone' => 'America/Bogota',
        'debug' => getenv('APP_DEBUG') === 'true'
    ]
];
