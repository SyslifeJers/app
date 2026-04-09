<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function handleCreatePrintTicket(mysqli $conn): void
{
    $data = getJsonInput();

    if (!array_key_exists('ticket_id', $data)) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El campo ticket_id es obligatorio.',
        ]);
    }

    $ticketId = (int) $data['ticket_id'];
    if ($ticketId <= 0) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El ticket_id debe ser un número entero positivo.',
        ]);
    }

    $estado = isset($data['estado']) ? trim((string) $data['estado']) : 'pendiente';
    if ($estado === '') {
        $estado = 'pendiente';
    }

    $stmt = $conn->prepare('INSERT INTO impresion_tickets (ticket_id, estado) VALUES (?, ?)');
    $stmt->bind_param('is', $ticketId, $estado);
    $stmt->execute();

    $id = (int) $conn->insert_id;
    $createdStmt = $conn->prepare('SELECT created_at, estado FROM impresion_tickets WHERE id = ?');
    $createdStmt->bind_param('i', $id);
    $createdStmt->execute();
    $createdResult = $createdStmt->get_result();
    $createdRow = $createdResult->fetch_assoc();

    $createdAt = $createdRow['created_at'] ?? date('Y-m-d H:i:s');
    $estado = $createdRow['estado'] ?? $estado;

    jsonResponse(201, [
        'status' => 'success',
        'ticket' => [
            'id' => $id,
            'ticket_id' => $ticketId,
            'estado' => $estado,
            'created_at' => $createdAt,
            'ticket_url' => buildTicketUrl($ticketId),
        ],
    ]);
}

function handleListPrintTickets(mysqli $conn): void
{
    $result = $conn->query("SELECT id, ticket_id, estado, created_at FROM impresion_tickets WHERE estado = 'pendiente' ORDER BY id ASC");

    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $ticketId = (int) $row['ticket_id'];
        $tickets[] = [
            'id' => (int) $row['id'],
            'ticket_id' => $ticketId,
            'estado' => $row['estado'],
            'created_at' => $row['created_at'],
            'ticket_url' => buildTicketUrl($ticketId),
        ];
    }

    jsonResponse(200, [
        'status' => 'success',
        'tickets' => $tickets,
    ]);
}

function buildTicketUrl(int $ticketId): string
{
    return 'https://app.clinicacerene.com/Modulos/ticket.php?id=' . $ticketId;
}
