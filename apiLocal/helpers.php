<?php

declare(strict_types=1);

/**
 * Sends a JSON response and terminates the request.
 */
function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    exit;
}

/**
 * Reads the JSON payload from the current request.
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'JSON inválido: ' . json_last_error_msg(),
        ]);
    }

    if (!is_array($data)) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El cuerpo de la petición debe ser un objeto JSON.',
        ]);
    }

    return $data;
}

/**
 * Extracts an identifier from the URI path.
 */
function extractIdFromPath(string $pattern, string $path): ?int
{
    if (preg_match($pattern, $path, $matches) !== 1) {
        return null;
    }

    return isset($matches['id']) ? (int) $matches['id'] : null;
}

/**
 * Parses an ISO-8601 timestamp and returns a DateTimeImmutable instance.
 */
function parseIsoDate(string $value, string $field): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
    if ($date === false) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => "El campo {$field} debe tener formato ISO-8601.",
        ]);
    }

    return $date;
}

/**
 * Binds parameters to a mysqli statement using a dynamic set of values.
 */
function bindStatementParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    array_unshift($refs, $types);
    $stmt->bind_param(...$refs);
}
