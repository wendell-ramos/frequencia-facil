<?php
// Configuração lida das variáveis de ambiente (definidas no .env / docker-compose).
// Nunca coloque senhas direto aqui: este arquivo vai para o GitHub.

return [
    'app' => [
        'env'   => getenv('APP_ENV') ?: 'local',
        'debug' => (getenv('APP_DEBUG') ?: 'false') === 'true',
    ],
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'db',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'frequencia',
        'username' => getenv('DB_USERNAME') ?: 'frequencia',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'log' => [
        'file' => __DIR__ . '/../logs/app.log',
    ],
];
