<?php
declare(strict_types=1);

return [
    'environment' => getenv('QUIZHELL_ENV') ?: 'development',
    'database' => [
        'host' => getenv('QUIZHELL_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('QUIZHELL_DB_PORT') ?: 3306),
        'name' => getenv('QUIZHELL_DB_NAME') ?: 'quizhell',
        'user' => getenv('QUIZHELL_DB_USER') ?: 'root',
        'password' => getenv('QUIZHELL_DB_PASSWORD') ?: '',
    ],
    'session_name' => 'quizhell_session',
];
