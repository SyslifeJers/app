<?php

function obtenerPrecioServicioPorId(mysqli $conn, int $precioId): ?array
{
    $stmt = $conn->prepare('SELECT id, name, costo, COALESCE(sesiones_total, 1) AS sesiones_total, COALESCE(requiere_cita, 1) AS requiere_cita, COALESCE(permite_sin_fecha, 0) AS permite_sin_fecha FROM Precios WHERE id = ? AND activo = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $precioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $precio = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$precio) {
        return null;
    }

    $precio['id'] = (int) $precio['id'];
    $precio['costo'] = (float) $precio['costo'];
    $precio['sesiones_total'] = max(0, (int) $precio['sesiones_total']);
    $precio['requiere_cita'] = (int) $precio['requiere_cita'];
    $precio['permite_sin_fecha'] = (int) $precio['permite_sin_fecha'];

    return $precio;
}

function obtenerPrecioServicioPorNombre(mysqli $conn, string $nombre): ?array
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, name, costo, COALESCE(sesiones_total, 1) AS sesiones_total, COALESCE(requiere_cita, 1) AS requiere_cita, COALESCE(permite_sin_fecha, 0) AS permite_sin_fecha FROM Precios WHERE activo = 1 AND LOWER(name) = LOWER(?) ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    $precio = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$precio) {
        return null;
    }

    $precio['id'] = (int) $precio['id'];
    $precio['costo'] = (float) $precio['costo'];
    $precio['sesiones_total'] = max(0, (int) $precio['sesiones_total']);
    $precio['requiere_cita'] = (int) $precio['requiere_cita'];
    $precio['permite_sin_fecha'] = (int) $precio['permite_sin_fecha'];

    return $precio;
}

function prepararServicioPaciente(mysqli $conn, int $pacienteId, ?int $psicologoId, array $precio, ?int $usuarioId): array
{
    $sesionesTotal = max(0, (int) ($precio['sesiones_total'] ?? 1));
    $precioId = (int) $precio['id'];

    $servicio = null;
    $stmt = $conn->prepare('SELECT id, sesiones_total, sesiones_completadas, saldo_restante FROM ServiciosPaciente WHERE nino_id = ? AND precio_id = ? AND estatus_id IN (2, 5) ORDER BY creado_en DESC, id DESC LIMIT 1 FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('No fue posible validar el servicio activo del paciente.');
    }
    $stmt->bind_param('ii', $pacienteId, $precioId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $servicio = $result->fetch_assoc();
    }
    $stmt->close();

    if ($servicio) {
        return [
            'id' => (int) $servicio['id'],
            'sesion' => null,
            'sesiones_total' => (int) $servicio['sesiones_total'],
            'saldo_restante' => (float) $servicio['saldo_restante'],
            'nuevo' => false,
        ];
    }

    $total = (float) $precio['costo'];
    $pagoInicial = 0.0;
    $saldoRestante = $total;
    $estatusActivo = 2;

    $stmtCrear = $conn->prepare('INSERT INTO ServiciosPaciente (precio_id, nino_id, psicologo_id, total, pago_inicial, saldo_restante, sesiones_total, sesiones_completadas, estatus_id, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
    if (!$stmtCrear) {
        throw new RuntimeException('No fue posible crear el servicio del paciente.');
    }
    $stmtCrear->bind_param('iiidddiii', $precioId, $pacienteId, $psicologoId, $total, $pagoInicial, $saldoRestante, $sesionesTotal, $estatusActivo, $usuarioId);
    if (!$stmtCrear->execute()) {
        $stmtCrear->close();
        throw new RuntimeException('No fue posible guardar el servicio del paciente.');
    }
    $servicioId = (int) $conn->insert_id;
    $stmtCrear->close();

    return [
        'id' => $servicioId,
        'sesion' => null,
        'sesiones_total' => $sesionesTotal,
        'saldo_restante' => $saldoRestante,
        'nuevo' => true,
    ];
}

function prepararServicioParaCita(mysqli $conn, int $pacienteId, int $psicologoId, array $precio, ?int $usuarioId): ?array
{
    $sesionesTotal = max(0, (int) ($precio['sesiones_total'] ?? 1));
    if ($sesionesTotal <= 1) {
        return null;
    }

    $precioId = (int) $precio['id'];
    $servicio = null;
    $stmt = $conn->prepare('SELECT id, sesiones_total, sesiones_completadas, saldo_restante FROM ServiciosPaciente WHERE nino_id = ? AND precio_id = ? AND estatus_id IN (2, 5) ORDER BY creado_en DESC, id DESC LIMIT 1 FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('No fue posible validar el servicio activo del paciente.');
    }

    $stmt->bind_param('ii', $pacienteId, $precioId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $servicio = $result->fetch_assoc();
    }
    $stmt->close();

    if (!$servicio) {
        $total = (float) $precio['costo'];
        $saldoRestante = $total;
        $pagoInicial = 0.0;
        $estatusActivo = 2;

        $stmtCrear = $conn->prepare('INSERT INTO ServiciosPaciente (precio_id, nino_id, psicologo_id, total, pago_inicial, saldo_restante, sesiones_total, sesiones_completadas, estatus_id, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
        if (!$stmtCrear) {
            throw new RuntimeException('No fue posible crear el servicio del paciente.');
        }
        $stmtCrear->bind_param('iiidddiii', $precioId, $pacienteId, $psicologoId, $total, $pagoInicial, $saldoRestante, $sesionesTotal, $estatusActivo, $usuarioId);
        if (!$stmtCrear->execute()) {
            $stmtCrear->close();
            throw new RuntimeException('No fue posible guardar el servicio del paciente.');
        }
        $servicioId = (int) $conn->insert_id;
        $stmtCrear->close();

        return [
            'id' => $servicioId,
            'sesion' => 1,
            'sesiones_total' => $sesionesTotal,
            'saldo_restante' => $saldoRestante,
            'nuevo' => true,
        ];
    }

    $servicioId = (int) $servicio['id'];
    $sesionesServicio = max(1, (int) $servicio['sesiones_total']);
    $stmtSesion = $conn->prepare('SELECT COALESCE(MAX(servicio_sesion), 0) FROM Cita WHERE servicio_paciente_id = ?');
    if (!$stmtSesion) {
        throw new RuntimeException('No fue posible calcular la siguiente sesion del servicio.');
    }
    $stmtSesion->bind_param('i', $servicioId);
    $stmtSesion->execute();
    $stmtSesion->bind_result($ultimaSesion);
    $stmtSesion->fetch();
    $stmtSesion->close();

    $siguienteSesion = ((int) $ultimaSesion) + 1;
    if ($siguienteSesion > $sesionesServicio) {
        throw new RuntimeException('Este servicio ya tiene todas sus sesiones agendadas.');
    }

    return [
        'id' => $servicioId,
        'sesion' => $siguienteSesion,
        'sesiones_total' => $sesionesServicio,
        'saldo_restante' => (float) $servicio['saldo_restante'],
        'nuevo' => false,
    ];
}

function obtenerServicioPorCita(mysqli $conn, int $citaId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT sp.id, sp.precio_id, sp.nino_id, sp.psicologo_id, sp.total, sp.saldo_restante, sp.sesiones_total, sp.sesiones_completadas, sp.estatus_id, ci.servicio_sesion, pr.name AS servicio_nombre, n.name AS paciente_nombre, COALESCE(u.name, "") AS psicologo_nombre FROM Cita ci INNER JOIN ServiciosPaciente sp ON sp.id = ci.servicio_paciente_id INNER JOIN Precios pr ON pr.id = sp.precio_id INNER JOIN nino n ON n.id = sp.nino_id LEFT JOIN Usuarios u ON u.id = sp.psicologo_id WHERE ci.id = ?';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $citaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $servicio = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$servicio) {
        return null;
    }

    $servicio['id'] = (int) $servicio['id'];
    $servicio['nino_id'] = (int) $servicio['nino_id'];
    $servicio['psicologo_id'] = $servicio['psicologo_id'] !== null ? (int) $servicio['psicologo_id'] : null;
    $servicio['saldo_restante'] = (float) $servicio['saldo_restante'];
    $servicio['sesiones_total'] = (int) $servicio['sesiones_total'];
    $servicio['sesiones_completadas'] = (int) $servicio['sesiones_completadas'];
    $servicio['servicio_sesion'] = $servicio['servicio_sesion'] !== null ? (int) $servicio['servicio_sesion'] : null;

    return $servicio;
}

function registrarPagoServicioPaciente(mysqli $conn, array $servicio, ?int $citaId, string $metodo, float $monto, ?int $usuarioId, ?string $fechaPago = null): float
{
    $montoAplicado = min($monto, max(0.0, (float) $servicio['saldo_restante']));
    if ($montoAplicado <= 0.009) {
        throw new RuntimeException('Este servicio ya no tiene adeudo.');
    }

    $servicioId = (int) $servicio['id'];
    $stmtPago = $conn->prepare('INSERT INTO ServicioPagos (servicio_paciente_id, cita_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?, ?)');
    if (!$stmtPago) {
        throw new RuntimeException('No fue posible registrar el pago del servicio.');
    }
    $stmtPago->bind_param('iisdi', $servicioId, $citaId, $metodo, $montoAplicado, $usuarioId);
    if (!$stmtPago->execute()) {
        $stmtPago->close();
        throw new RuntimeException('No fue posible guardar el pago del servicio.');
    }
    $stmtPago->close();

    $nuevoRestante = max(0.0, (float) $servicio['saldo_restante'] - $montoAplicado);
    $nuevoEstatus = $nuevoRestante > 0.009 ? 5 : 2;
    $stmtUpdate = $conn->prepare('UPDATE ServiciosPaciente SET saldo_restante = ?, estatus_id = ? WHERE id = ?');
    if (!$stmtUpdate) {
        throw new RuntimeException('No fue posible actualizar el saldo del servicio.');
    }
    $stmtUpdate->bind_param('dii', $nuevoRestante, $nuevoEstatus, $servicioId);
    if (!$stmtUpdate->execute()) {
        $stmtUpdate->close();
        throw new RuntimeException('No fue posible actualizar el saldo del servicio.');
    }
    $stmtUpdate->close();

    if (function_exists('registrarResumenPago')) {
        $fechaPago = $fechaPago ?: date('Y-m-d H:i:s');
        registrarResumenPago($conn, [
            'origen' => 'servicio',
            'referencia_id' => $servicioId,
            'cita_id' => $citaId,
            'paciente_id' => (int) $servicio['nino_id'],
            'paciente_nombre' => (string) ($servicio['paciente_nombre'] ?? ''),
            'psicologo_id' => $servicio['psicologo_id'] ?? null,
            'psicologo_nombre' => (string) ($servicio['psicologo_nombre'] ?? ''),
            'monto' => $montoAplicado,
            'metodo_pago' => $metodo,
            'fecha_pago' => $fechaPago,
            'fecha_corte' => substr($fechaPago, 0, 10),
            'registrado_por' => $usuarioId,
            'observaciones' => 'Abono a servicio ' . (string) ($servicio['servicio_nombre'] ?? ''),
        ]);
    }

    return $montoAplicado;
}

function marcarSesionServicioCompletada(mysqli $conn, int $servicioId): void
{
    $stmt = $conn->prepare('UPDATE ServiciosPaciente SET sesiones_completadas = LEAST(sesiones_total, sesiones_completadas + 1), estatus_id = CASE WHEN LEAST(sesiones_total, sesiones_completadas + 1) >= sesiones_total AND saldo_restante <= 0.009 THEN 6 WHEN saldo_restante > 0.009 THEN 5 ELSE 2 END WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('No fue posible actualizar las sesiones del servicio.');
    }
    $stmt->bind_param('i', $servicioId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible actualizar las sesiones del servicio.');
    }
    $stmt->close();
}
