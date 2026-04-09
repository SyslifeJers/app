<?php

declare(strict_types=1);

return [
    'db' => [
        'dsn' => getenv('CERENE_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=u529445062_cenere;charset=utf8mb4',
        'user' => getenv('CERENE_DB_USER') ?: 'root',
        'password' => getenv('CERENE_DB_PASSWORD') ?: '',
    ],
];
