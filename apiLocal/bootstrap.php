<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Returns a singleton PDO connection to the primary database.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];

    try {
        $pdo = new PDO(
            $db['dsn'],
            $db['user'],
            $db['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        jsonResponse(500, [
            'status' => 'error',
            'message' => 'No se pudo conectar a la base de datos',
            'details' => $exception->getMessage(),
        ]);
    }

    ensureSchema($pdo);

    return $pdo;
}

/**
 * Ensures the tables required by the offline API exist.
 */
function ensureSchema(PDO $pdo): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS offline_citas (
            id_servidor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            paciente_id BIGINT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            hora TIME NOT NULL,
            estado VARCHAR(50) NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS offline_pagos (
            id_servidor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cita_id BIGINT UNSIGNED NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            metodo VARCHAR(100) NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS impresion_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}
