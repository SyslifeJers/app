<?php
include '../Modulos/head.php';
require_once __DIR__ . '/../Modulos/conflictos_agenda.php';
require_once __DIR__ . '/../Modulos/resumen_pagos.php';

$ROL_PRACTICANTE = 6;
$ROL_ADMIN = 3;
$rolesPermitidos = [1, 2, 3, 5];
$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$usuarioId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
$esAdministradorDemo = $rolUsuario === $ROL_ADMIN;

date_default_timezone_set('America/Mexico_City');

function demoPagosCrearTablas(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `Pagos` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `origen` VARCHAR(30) NOT NULL,
        `referencia_id` INT NULL,
        `cita_id` INT NULL,
        `paquete_id` INT NULL,
        `paciente_id` INT NOT NULL,
        `paciente_nombre` VARCHAR(150) NOT NULL,
        `psicologo_id` INT NULL,
        `psicologo_nombre` VARCHAR(150) NULL,
        `monto` DECIMAL(10,2) NOT NULL,
        `metodo_pago` VARCHAR(50) NOT NULL,
        `fecha_pago` DATETIME NOT NULL,
        `fecha_corte` DATE NOT NULL,
        `registrado_por` INT NULL,
        `observaciones` VARCHAR(255) NULL,
        `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pagos_origen_referencia` (`origen`, `referencia_id`),
        KEY `idx_pagos_cita` (`cita_id`),
        KEY `idx_pagos_paquete` (`paquete_id`),
        KEY `idx_pagos_paciente` (`paciente_id`),
        KEY `idx_pagos_fecha_corte` (`fecha_corte`),
        KEY `idx_pagos_metodo` (`metodo_pago`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `SaldoMovimientos` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `paciente_id` INT NOT NULL,
        `tipo` VARCHAR(30) NOT NULL,
        `monto` DECIMAL(10,2) NOT NULL,
        `saldo_anterior` DECIMAL(10,2) NOT NULL,
        `saldo_nuevo` DECIMAL(10,2) NOT NULL,
        `pago_id` INT NULL,
        `cita_id` INT NULL,
        `paquete_id` INT NULL,
        `registrado_por` INT NULL,
        `observaciones` VARCHAR(255) NULL,
        `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_saldo_movimientos_paciente` (`paciente_id`),
        KEY `idx_saldo_movimientos_tipo` (`tipo`),
        KEY `idx_saldo_movimientos_pago` (`pago_id`),
        KEY `idx_saldo_movimientos_cita` (`cita_id`),
        KEY `idx_saldo_movimientos_paquete` (`paquete_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function demoPagosSaldo(mysqli $conn, int $pacienteId): float
{
    $saldo = 0.0;
    $stmt = $conn->prepare('SELECT saldo_paquete FROM nino WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $pacienteId);
        $stmt->execute();
        $stmt->bind_result($saldoCalculado);
        if ($stmt->fetch()) {
            $saldo = (float) $saldoCalculado;
        }
        $stmt->close();
    }

    return $saldo;
}

function demoPagosInsertarPago(mysqli $conn, array $data): int
{
    $stmt = $conn->prepare('INSERT INTO Pagos (origen, referencia_id, cita_id, paquete_id, paciente_id, paciente_nombre, psicologo_id, psicologo_nombre, monto, metodo_pago, fecha_pago, fecha_corte, registrado_por, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('No fue posible preparar el pago.');
    }

    $stmt->bind_param(
        'siiiisisdsssis',
        $data['origen'],
        $data['referencia_id'],
        $data['cita_id'],
        $data['paquete_id'],
        $data['paciente_id'],
        $data['paciente_nombre'],
        $data['psicologo_id'],
        $data['psicologo_nombre'],
        $data['monto'],
        $data['metodo_pago'],
        $data['fecha_pago'],
        $data['fecha_corte'],
        $data['registrado_por'],
        $data['observaciones']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible guardar el pago.');
    }

    $id = (int) $conn->insert_id;
    $stmt->close();

    if (function_exists('registrarResumenPago')) {
        registrarResumenPago($conn, [
            'origen' => $data['origen'],
            'referencia_id' => $data['referencia_id'] ?? $id,
            'cita_id' => $data['cita_id'],
            'paquete_id' => $data['paquete_id'],
            'paciente_id' => $data['paciente_id'],
            'paciente_nombre' => $data['paciente_nombre'],
            'psicologo_id' => $data['psicologo_id'],
            'psicologo_nombre' => $data['psicologo_nombre'] ?? '',
            'monto' => $data['monto'],
            'metodo_pago' => $data['metodo_pago'],
            'fecha_pago' => $data['fecha_pago'],
            'fecha_corte' => $data['fecha_corte'],
            'registrado_por' => $data['registrado_por'],
            'observaciones' => $data['observaciones'],
        ]);
    }

    return $id;
}

function demoPagosInsertarMovimientoSaldo(mysqli $conn, int $pacienteId, string $tipo, float $monto, ?int $pagoId, ?int $citaId, ?int $paqueteId, ?int $usuarioId, ?string $observaciones): void
{
    if (abs($monto) < 0.01) {
        return;
    }

    $saldoAnterior = demoPagosSaldo($conn, $pacienteId);
    $saldoNuevo = $saldoAnterior + $monto;

    if ($saldoNuevo < -0.0001 && $monto < 0 && $tipo !== 'adeudo_cita') {
        throw new RuntimeException('El saldo del paciente es insuficiente.');
    }

    $stmt = $conn->prepare('INSERT INTO SaldoMovimientos (paciente_id, tipo, monto, saldo_anterior, saldo_nuevo, pago_id, cita_id, paquete_id, registrado_por, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('No fue posible preparar el movimiento de saldo.');
    }

    $stmt->bind_param('isdddiiiis', $pacienteId, $tipo, $monto, $saldoAnterior, $saldoNuevo, $pagoId, $citaId, $paqueteId, $usuarioId, $observaciones);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible guardar el movimiento de saldo.');
    }
    $stmt->close();

    $stmtActualizarSaldo = $conn->prepare('UPDATE nino SET saldo_paquete = ? WHERE id = ?');
    if (!$stmtActualizarSaldo) {
        throw new RuntimeException('No fue posible preparar la actualización del saldo del paciente.');
    }
    $stmtActualizarSaldo->bind_param('di', $saldoNuevo, $pacienteId);
    if (!$stmtActualizarSaldo->execute()) {
        $stmtActualizarSaldo->close();
        throw new RuntimeException('No fue posible actualizar el saldo del paciente.');
    }
    $stmtActualizarSaldo->close();
}

function demoPagosPaciente(mysqli $conn, int $pacienteId): array
{
    $stmt = $conn->prepare('SELECT id, name FROM nino WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No fue posible consultar el paciente.');
    }

    $stmt->bind_param('i', $pacienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$paciente) {
        throw new RuntimeException('Paciente no encontrado.');
    }

    return $paciente;
}

function demoPagosPacienteDelTutor(mysqli $conn, int $pacienteId, int $tutorId): array
{
    $stmt = $conn->prepare('SELECT id, name, COALESCE(saldo_paquete, 0) AS saldo_demo FROM nino WHERE id = ? AND idtutor = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No fue posible consultar el paciente del tutor.');
    }

    $stmt->bind_param('ii', $pacienteId, $tutorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$paciente) {
        throw new RuntimeException('Selecciona un paciente valido para el tutor.');
    }

    return $paciente;
}

function demoPagosObtenerIdEstatus(mysqli $conn, string $nombre, int $predeterminado): int
{
    $estatusId = null;
    $nombreNormalizado = strtolower(trim($nombre));
    $stmt = $conn->prepare('SELECT id FROM Estatus WHERE TRIM(LOWER(name)) = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $nombreNormalizado);
        $stmt->execute();
        $stmt->bind_result($idEncontrado);
        if ($stmt->fetch()) {
            $estatusId = (int) $idEncontrado;
        }
        $stmt->close();
    }

    return $estatusId ?? $predeterminado;
}

function demoPagosPsicologo(mysqli $conn, int $psicologoId): array
{
    $stmt = $conn->prepare("SELECT usu.id, usu.name FROM Usuarios usu INNER JOIN Rol ON Rol.id = usu.IdRol WHERE usu.id = ? AND usu.activo = 1 AND LOWER(Rol.name) LIKE '%psicolog%' LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('No fue posible consultar el psicologo.');
    }

    $stmt->bind_param('i', $psicologoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $psicologo = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$psicologo) {
        throw new RuntimeException('Psicologo no encontrado.');
    }

    return $psicologo;
}

$mensajeDemo = null;
$tipoMensajeDemo = 'success';
$tienePermisoDemo = in_array($rolUsuario, $rolesPermitidos, true) && $rolUsuario !== $ROL_PRACTICANTE;

if ($tienePermisoDemo) {
    demoPagosCrearTablas($conn);
}

if ($tienePermisoDemo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';
    $fechaActual = date('Y-m-d H:i:s');
    $fechaCorte = substr($fechaActual, 0, 10);

    try {
        $conn->begin_transaction();

        if ($accion === 'cancelar_cita') {
            $citaId = isset($_POST['cita_id']) ? (int) $_POST['cita_id'] : 0;
            if ($citaId <= 0) {
                throw new RuntimeException('Selecciona una cita valida para cancelar.');
            }

            $estatusCancelada = demoPagosObtenerIdEstatus($conn, 'Cancelada', 1);
            $stmtCancelar = $conn->prepare('UPDATE Cita SET Estatus = ? WHERE id = ?');
            if (!$stmtCancelar) {
                throw new RuntimeException('No fue posible preparar la cancelacion.');
            }
            $stmtCancelar->bind_param('ii', $estatusCancelada, $citaId);
            if (!$stmtCancelar->execute()) {
                $stmtCancelar->close();
                throw new RuntimeException('No fue posible cancelar la cita.');
            }
            $stmtCancelar->close();

            if ($usuarioId !== null) {
                $stmtHistorial = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (NULL, ?, ?, ?, ?)');
                if ($stmtHistorial) {
                    $stmtHistorial->bind_param('siii', $fechaActual, $estatusCancelada, $citaId, $usuarioId);
                    $stmtHistorial->execute();
                    $stmtHistorial->close();
                }
            }

            $mensajeDemo = 'Cita cancelada correctamente.';
        } elseif ($accion === 'registrar_cita') {
            $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
            $psicologoId = isset($_POST['psicologo_id']) ? (int) $_POST['psicologo_id'] : 0;
            $fechaProgramadaInput = isset($_POST['programado']) ? trim((string) $_POST['programado']) : '';
            $tipoCita = isset($_POST['tipo']) ? substr(trim((string) $_POST['tipo']), 0, 100) : '';
            $costo = isset($_POST['costo']) ? (float) $_POST['costo'] : 0.0;
            $tiempo = isset($_POST['tiempo']) ? (int) $_POST['tiempo'] : 60;

            if ($pacienteId <= 0 || $psicologoId <= 0 || $fechaProgramadaInput === '' || $tipoCita === '') {
                throw new RuntimeException('Completa paciente, psicologo, fecha y tipo de cita.');
            }
            if ($costo < 0 || $tiempo <= 0) {
                throw new RuntimeException('El costo no puede ser negativo y el tiempo debe ser mayor a cero.');
            }

            $fechaProgramada = str_replace('T', ' ', $fechaProgramadaInput);
            if (strlen($fechaProgramada) === 16) {
                $fechaProgramada .= ':00';
            }
            $fechaValida = DateTime::createFromFormat('Y-m-d H:i:s', $fechaProgramada);
            if (!$fechaValida) {
                throw new RuntimeException('La fecha de la cita no es valida.');
            }

            $paciente = demoPagosPaciente($conn, $pacienteId);
            $psicologo = demoPagosPsicologo($conn, $psicologoId);

            $conflicto = obtenerConflictoAgendaPsicologo($conn, $psicologoId, $fechaProgramada, $tiempo, null, $pacienteId);
            if ($conflicto !== null) {
                throw new RuntimeException('La psicologa ya tiene un conflicto de agenda en ese horario.');
            }

            $estatusCreada = demoPagosObtenerIdEstatus($conn, 'Creada', 2);
            $forzada = 0;
            $stmtCita = $conn->prepare('INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Tiempo, forzada, Estatus, Tipo, paquete_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)');
            if (!$stmtCita) {
                throw new RuntimeException('No fue posible preparar la cita.');
            }
            $stmtCita->bind_param('iiisdsiiis', $pacienteId, $psicologoId, $usuarioId, $fechaActual, $costo, $fechaProgramada, $tiempo, $forzada, $estatusCreada, $tipoCita);
            if (!$stmtCita->execute()) {
                $stmtCita->close();
                throw new RuntimeException('No fue posible registrar la cita.');
            }
            $nuevaCitaId = (int) $conn->insert_id;
            $stmtCita->close();

            if ($usuarioId !== null) {
                $stmtHistorial = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (NULL, ?, ?, ?, ?)');
                if ($stmtHistorial) {
                    $stmtHistorial->bind_param('siii', $fechaActual, $estatusCreada, $nuevaCitaId, $usuarioId);
                    $stmtHistorial->execute();
                    $stmtHistorial->close();
                }
            }

            $mensajeDemo = 'Cita registrada para ' . $paciente['name'] . ' con ' . $psicologo['name'] . '. Los pagos quedan separados del registro de cita.';
        } elseif ($accion === 'registrar_pago_sin_cita') {
            if (!$esAdministradorDemo) {
                throw new RuntimeException('Solo el administrador puede registrar pagos sin cita.');
            }

            $tutorId = isset($_POST['tutor_id']) ? (int) $_POST['tutor_id'] : 0;
            $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
            $metodoPago = isset($_POST['metodo_pago']) ? substr(trim((string) $_POST['metodo_pago']), 0, 50) : '';
            $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;
            $observaciones = isset($_POST['observaciones']) ? substr(trim((string) $_POST['observaciones']), 0, 255) : null;

            if ($tutorId <= 0 || $pacienteId <= 0 || $monto <= 0 || $metodoPago === '') {
                throw new RuntimeException('Selecciona tutor, paciente, metodo de pago y monto mayor a cero.');
            }

            $paciente = demoPagosPacienteDelTutor($conn, $pacienteId, $tutorId);
            $saldoAnterior = demoPagosSaldo($conn, $pacienteId);
            $tipoMovimiento = $saldoAnterior < -0.0001 ? 'abono_adeudo' : 'agregar_saldo';
            $pagoId = demoPagosInsertarPago($conn, [
                'origen' => 'sin_cita',
                'referencia_id' => $tutorId,
                'cita_id' => null,
                'paquete_id' => null,
                'paciente_id' => $pacienteId,
                'paciente_nombre' => substr((string) $paciente['name'], 0, 150),
                'psicologo_id' => null,
                'psicologo_nombre' => null,
                'monto' => $monto,
                'metodo_pago' => $metodoPago,
                'fecha_pago' => $fechaActual,
                'fecha_corte' => $fechaCorte,
                'registrado_por' => $usuarioId,
                'observaciones' => $observaciones !== '' ? $observaciones : 'Pago sin cita',
            ]);

            demoPagosInsertarMovimientoSaldo($conn, $pacienteId, $tipoMovimiento, $monto, $pagoId, null, null, $usuarioId, $observaciones !== '' ? $observaciones : 'Pago sin cita');

            $mensajeDemo = $saldoAnterior < -0.0001
                ? 'Pago sin cita registrado. Se abonó al adeudo y el excedente quedó como saldo disponible.'
                : 'Pago sin cita registrado como saldo disponible.';
        } elseif ($accion === 'agregar_saldo') {
            $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
            $metodoPago = isset($_POST['metodo_pago']) ? substr(trim((string) $_POST['metodo_pago']), 0, 50) : '';
            $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;
            $observaciones = isset($_POST['observaciones']) ? substr(trim((string) $_POST['observaciones']), 0, 255) : null;

            if ($pacienteId <= 0 || $monto <= 0 || $metodoPago === '') {
                throw new RuntimeException('Selecciona paciente, metodo de pago y monto mayor a cero.');
            }

            $paciente = demoPagosPaciente($conn, $pacienteId);
            $saldoAnterior = demoPagosSaldo($conn, $pacienteId);
            $tipoMovimiento = $saldoAnterior < -0.0001 ? 'abono_adeudo' : 'agregar_saldo';

            $pagoId = demoPagosInsertarPago($conn, [
                'origen' => 'saldo',
                'referencia_id' => $pacienteId,
                'cita_id' => null,
                'paquete_id' => null,
                'paciente_id' => $pacienteId,
                'paciente_nombre' => substr((string) $paciente['name'], 0, 150),
                'psicologo_id' => null,
                'psicologo_nombre' => null,
                'monto' => $monto,
                'metodo_pago' => $metodoPago,
                'fecha_pago' => $fechaActual,
                'fecha_corte' => $fechaCorte,
                'registrado_por' => $usuarioId,
                'observaciones' => $observaciones !== '' ? $observaciones : 'Abono/agregado de saldo',
            ]);

            demoPagosInsertarMovimientoSaldo($conn, $pacienteId, $tipoMovimiento, $monto, $pagoId, null, null, $usuarioId, $observaciones !== '' ? $observaciones : 'Pago para saldo/adeudo');

            $mensajeDemo = $saldoAnterior < -0.0001
                ? 'Abono registrado al saldo adeudor. Este pago queda en el corte de hoy.'
                : 'Saldo agregado. Este pago queda en el corte de hoy.';
        } elseif ($accion === 'vender_paquete') {
            $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
            $paqueteId = isset($_POST['paquete_id']) ? (int) $_POST['paquete_id'] : 0;
            $metodoPago = isset($_POST['metodo_pago']) ? substr(trim((string) $_POST['metodo_pago']), 0, 50) : '';
            $observaciones = isset($_POST['observaciones']) ? substr(trim((string) $_POST['observaciones']), 0, 255) : null;

            if ($pacienteId <= 0 || $paqueteId <= 0 || $metodoPago === '') {
                throw new RuntimeException('Selecciona paciente, paquete y metodo de pago.');
            }

            $paciente = demoPagosPaciente($conn, $pacienteId);
            $stmtPaquete = $conn->prepare('SELECT id, nombre, primer_pago_monto, saldo_adicional FROM Paquetes WHERE id = ? AND activo = 1 LIMIT 1');
            if (!$stmtPaquete) {
                throw new RuntimeException('No fue posible consultar el paquete.');
            }
            $stmtPaquete->bind_param('i', $paqueteId);
            $stmtPaquete->execute();
            $paquete = $stmtPaquete->get_result()->fetch_assoc();
            $stmtPaquete->close();

            if (!$paquete) {
                throw new RuntimeException('El paquete seleccionado no esta disponible.');
            }

            $montoPago = (float) $paquete['primer_pago_monto'];
            $saldoOtorgado = (float) $paquete['saldo_adicional'];

            $pagoId = demoPagosInsertarPago($conn, [
                'origen' => 'paquete',
                'referencia_id' => $paqueteId,
                'cita_id' => null,
                'paquete_id' => $paqueteId,
                'paciente_id' => $pacienteId,
                'paciente_nombre' => substr((string) $paciente['name'], 0, 150),
                'psicologo_id' => null,
                'psicologo_nombre' => null,
                'monto' => $montoPago,
                'metodo_pago' => $metodoPago,
                'fecha_pago' => $fechaActual,
                'fecha_corte' => $fechaCorte,
                'registrado_por' => $usuarioId,
                'observaciones' => $observaciones !== '' ? $observaciones : 'Venta de paquete ' . $paquete['nombre'],
            ]);

            demoPagosInsertarMovimientoSaldo($conn, $pacienteId, 'paquete', $saldoOtorgado, $pagoId, null, $paqueteId, $usuarioId, 'Saldo otorgado por paquete ' . $paquete['nombre']);

            $mensajeDemo = 'Venta de paquete registrada. El saldo del paciente fue actualizado.';
        } elseif ($accion === 'pagar_cita') {
            $citaId = isset($_POST['cita_id']) ? (int) $_POST['cita_id'] : 0;
            $metodoPago = isset($_POST['metodo_pago']) ? substr(trim((string) $_POST['metodo_pago']), 0, 50) : '';
            $montoExterno = isset($_POST['monto_externo']) ? (float) $_POST['monto_externo'] : 0.0;
            $montoSaldo = isset($_POST['monto_saldo']) ? (float) $_POST['monto_saldo'] : 0.0;
            $observaciones = isset($_POST['observaciones']) ? substr(trim((string) $_POST['observaciones']), 0, 255) : null;

            if ($citaId <= 0) {
                throw new RuntimeException('Selecciona una cita.');
            }
            if ($montoExterno < 0 || $montoSaldo < 0) {
                throw new RuntimeException('Los montos no pueden ser negativos.');
            }
            if ($montoExterno > 0 && $metodoPago === '') {
                throw new RuntimeException('Selecciona metodo de pago para el monto externo.');
            }

            $stmtCita = $conn->prepare('SELECT ci.id, ci.IdNino, ci.IdUsuario, ci.costo, ci.Programado, ci.Tipo, n.name AS paciente_nombre, u.name AS psicologo_nombre FROM Cita ci INNER JOIN nino n ON n.id = ci.IdNino INNER JOIN Usuarios u ON u.id = ci.IdUsuario WHERE ci.id = ? LIMIT 1');
            if (!$stmtCita) {
                throw new RuntimeException('No fue posible consultar la cita.');
            }
            $stmtCita->bind_param('i', $citaId);
            $stmtCita->execute();
            $cita = $stmtCita->get_result()->fetch_assoc();
            $stmtCita->close();

            if (!$cita) {
                throw new RuntimeException('Cita no encontrada.');
            }

            $pacienteId = (int) $cita['IdNino'];
            $costo = (float) $cita['costo'];
            $totalAplicado = $montoExterno + $montoSaldo;
            $pagoId = null;
            if ($montoExterno > 0) {
                $pagoId = demoPagosInsertarPago($conn, [
                    'origen' => 'cita',
                    'referencia_id' => $citaId,
                    'cita_id' => $citaId,
                    'paquete_id' => null,
                    'paciente_id' => $pacienteId,
                    'paciente_nombre' => substr((string) $cita['paciente_nombre'], 0, 150),
                    'psicologo_id' => (int) $cita['IdUsuario'],
                    'psicologo_nombre' => substr((string) $cita['psicologo_nombre'], 0, 150),
                    'monto' => $montoExterno,
                    'metodo_pago' => $metodoPago,
                    'fecha_pago' => $fechaActual,
                    'fecha_corte' => $fechaCorte,
                    'registrado_por' => $usuarioId,
                    'observaciones' => $observaciones !== '' ? $observaciones : 'Pago externo de cita #' . $citaId,
                ]);
            }

            if ($montoSaldo > 0) {
                demoPagosInsertarMovimientoSaldo($conn, $pacienteId, 'consumo_cita', -1 * $montoSaldo, $pagoId, $citaId, null, $usuarioId, 'Consumo de saldo para cita #' . $citaId);
            }

            $faltante = max(0, $costo - $totalAplicado);
            if ($faltante > 0) {
                demoPagosInsertarMovimientoSaldo($conn, $pacienteId, 'adeudo_cita', -1 * $faltante, $pagoId, $citaId, null, $usuarioId, 'Faltante pendiente de cita #' . $citaId);
            }

            $excedente = max(0, $totalAplicado - $costo);
            if ($excedente > 0) {
                demoPagosInsertarMovimientoSaldo($conn, $pacienteId, 'excedente_cita', $excedente, $pagoId, $citaId, null, $usuarioId, 'Excedente guardado como saldo de cita #' . $citaId);
            }

            $mensajeDemo = $faltante > 0
                ? 'Pago parcial registrado. El faltante quedó como saldo adeudor del paciente.'
                : 'Pago de cita registrado.';
        } else {
            throw new RuntimeException('Accion no valida.');
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $mensajeDemo = $e->getMessage();
        $tipoMensajeDemo = 'danger';
    }
}

$pacientes = [];
$tutoresPagoSinCita = [];
$psicologos = [];
$paquetes = [];
$citas = [];
$citasHoy = [];
$historialPacientesDemo = [];
$agendaPsicologosDemo = [];
$resumenMetodo = [];
$resumenOrigen = [];
$pagosRecientes = [];
$movimientosRecientes = [];
$fechaVistaParam = isset($_GET['fecha']) ? trim((string) $_GET['fecha']) : '';
$fechaVistaObj = DateTime::createFromFormat('Y-m-d', $fechaVistaParam) ?: new DateTime('now', new DateTimeZone('America/Mexico_City'));
$fechaVista = $fechaVistaObj->format('Y-m-d');
$fechaVistaAnterior = (clone $fechaVistaObj)->modify('-1 day')->format('Y-m-d');
$fechaVistaSiguiente = (clone $fechaVistaObj)->modify('+1 day')->format('Y-m-d');
$fechaVistaTitulo = $fechaVistaObj->format('d/m/Y');

if ($tienePermisoDemo) {
    if ($result = $conn->query('SELECT n.id, n.name, COALESCE(n.saldo_paquete, 0) AS saldo_demo FROM nino n WHERE n.activo = 1 ORDER BY n.name ASC LIMIT 500')) {
        while ($row = $result->fetch_assoc()) {
            $pacientes[] = $row;
        }
        $result->free();
    }

    if ($result = $conn->query("SELECT c.id AS tutor_id, c.name AS tutor_nombre, n.id AS paciente_id, n.name AS paciente_nombre, COALESCE(n.saldo_paquete, 0) AS saldo_demo FROM Clientes c INNER JOIN nino n ON n.idtutor = c.id WHERE c.activo = 1 AND n.activo = 1 ORDER BY c.name ASC, n.name ASC LIMIT 1000")) {
        while ($row = $result->fetch_assoc()) {
            $tutoresPagoSinCita[] = $row;
        }
        $result->free();
    }

    if ($result = $conn->query("SELECT usu.id, usu.name FROM Usuarios usu INNER JOIN Rol ON Rol.id = usu.IdRol WHERE usu.activo = 1 AND LOWER(Rol.name) LIKE '%psicolog%' ORDER BY usu.name ASC")) {
        while ($row = $result->fetch_assoc()) {
            $psicologos[] = $row;
        }
        $result->free();
    }

    if ($result = $conn->query('SELECT id, nombre, primer_pago_monto, saldo_adicional FROM Paquetes WHERE activo = 1 ORDER BY nombre ASC')) {
        while ($row = $result->fetch_assoc()) {
            $paquetes[] = $row;
        }
        $result->free();
    }

    $sqlCitas = "SELECT ci.id, ci.IdNino, ci.IdUsuario, ci.costo, ci.Programado, ci.Tipo, n.name AS paciente_nombre, u.name AS psicologo_nombre,
                    COALESCE(n.saldo_paquete, 0) AS saldo_demo
                 FROM Cita ci
                 INNER JOIN nino n ON n.id = ci.IdNino
                 INNER JOIN Usuarios u ON u.id = ci.IdUsuario
                 LEFT JOIN Estatus es ON es.id = ci.Estatus
                 WHERE (es.name IS NULL OR es.name NOT IN ('Cancelada'))
                 ORDER BY ci.Programado DESC
                 LIMIT 300";
    if ($result = $conn->query($sqlCitas)) {
        while ($row = $result->fetch_assoc()) {
            $citas[] = $row;
        }
        $result->free();
    }

    $sqlCitasHoy = "SELECT ci.id, ci.IdNino, ci.IdUsuario, ci.costo, ci.Programado, TIME(ci.Programado) AS Hora, ci.Tipo, ci.FormaPago, es.name AS estatus_nombre, n.name AS paciente_nombre, u.name AS psicologo_nombre,
                       COALESCE(n.saldo_paquete, 0) AS saldo_demo,
                       COALESCE((SELECT SUM(dp.monto) FROM Pagos dp WHERE dp.cita_id = ci.id), 0) AS pagado_externo_demo,
                       COALESCE((SELECT ABS(SUM(dsm2.monto)) FROM SaldoMovimientos dsm2 WHERE dsm2.cita_id = ci.id AND dsm2.tipo = 'consumo_cita'), 0) AS pagado_saldo_demo
                    FROM Cita ci
                    INNER JOIN nino n ON n.id = ci.IdNino
                    INNER JOIN Usuarios u ON u.id = ci.IdUsuario
                    LEFT JOIN Estatus es ON es.id = ci.Estatus
                    WHERE DATE(ci.Programado) = ?
                    ORDER BY CASE WHEN ci.Programado >= NOW() THEN 0 ELSE 1 END, ci.Programado ASC";
    if ($stmtCitasHoy = $conn->prepare($sqlCitasHoy)) {
        $stmtCitasHoy->bind_param('s', $fechaVista);
        $stmtCitasHoy->execute();
        $result = $stmtCitasHoy->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $citasHoy[] = $row;
            }
        }
        $stmtCitasHoy->close();
    }

    $sqlHistorialPacientes = "SELECT ci.id, ci.IdNino, ci.costo, ci.Programado, ci.Tipo, es.name AS estatus_nombre, u.name AS psicologo_nombre
                              FROM Cita ci
                              INNER JOIN Usuarios u ON u.id = ci.IdUsuario
                              LEFT JOIN Estatus es ON es.id = ci.Estatus
                              ORDER BY ci.Programado DESC
                              LIMIT 500";
    if ($result = $conn->query($sqlHistorialPacientes)) {
        while ($row = $result->fetch_assoc()) {
            $pacienteKey = (string) (int) $row['IdNino'];
            if (!isset($historialPacientesDemo[$pacienteKey])) {
                $historialPacientesDemo[$pacienteKey] = [];
            }
            if (count($historialPacientesDemo[$pacienteKey]) < 12) {
                $historialPacientesDemo[$pacienteKey][] = [
                    'id' => (int) $row['id'],
                    'programado' => (string) $row['Programado'],
                    'tipo' => (string) $row['Tipo'],
                    'psicologo' => (string) $row['psicologo_nombre'],
                    'estatus' => (string) ($row['estatus_nombre'] ?? 'Sin estatus'),
                    'costo' => (float) $row['costo'],
                ];
            }
        }
        $result->free();
    }

    $sqlAgendaPsicologos = "SELECT ci.id, ci.IdUsuario, ci.costo, ci.Programado, TIME(ci.Programado) AS hora, ci.Tipo, es.name AS estatus_nombre, n.name AS paciente_nombre
                            FROM Cita ci
                            INNER JOIN nino n ON n.id = ci.IdNino
                            LEFT JOIN Estatus es ON es.id = ci.Estatus
                            WHERE DATE(ci.Programado) BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                            ORDER BY ci.Programado ASC
                            LIMIT 500";
    if ($result = $conn->query($sqlAgendaPsicologos)) {
        while ($row = $result->fetch_assoc()) {
            $psicologoKey = (string) (int) $row['IdUsuario'];
            if (!isset($agendaPsicologosDemo[$psicologoKey])) {
                $agendaPsicologosDemo[$psicologoKey] = [];
            }
            if (count($agendaPsicologosDemo[$psicologoKey]) < 20) {
                $agendaPsicologosDemo[$psicologoKey][] = [
                    'id' => (int) $row['id'],
                    'programado' => (string) $row['Programado'],
                    'hora' => (string) $row['hora'],
                    'tipo' => (string) $row['Tipo'],
                    'paciente' => (string) $row['paciente_nombre'],
                    'estatus' => (string) ($row['estatus_nombre'] ?? 'Sin estatus'),
                    'costo' => (float) $row['costo'],
                ];
            }
        }
        $result->free();
    }

    if ($result = $conn->query('SELECT metodo_pago, SUM(monto) AS total FROM Pagos GROUP BY metodo_pago ORDER BY total DESC')) {
        while ($row = $result->fetch_assoc()) {
            $resumenMetodo[] = $row;
        }
        $result->free();
    }

    if ($result = $conn->query('SELECT origen, SUM(monto) AS total FROM Pagos GROUP BY origen ORDER BY total DESC')) {
        while ($row = $result->fetch_assoc()) {
            $resumenOrigen[] = $row;
        }
        $result->free();
    }

    if ($stmtPagosRecientes = $conn->prepare('SELECT id, origen, referencia_id, paciente_nombre, psicologo_nombre, monto, metodo_pago, fecha_pago, observaciones FROM Pagos WHERE fecha_corte = ? ORDER BY id DESC LIMIT 50')) {
        $stmtPagosRecientes->bind_param('s', $fechaVista);
        $stmtPagosRecientes->execute();
        $result = $stmtPagosRecientes->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $pagosRecientes[] = $row;
            }
        }
        $stmtPagosRecientes->close();
    }

    if ($stmtMovimientosRecientes = $conn->prepare('SELECT dsm.id, dsm.paciente_id, n.name AS paciente_nombre, dsm.tipo, dsm.monto, dsm.saldo_anterior, dsm.saldo_nuevo, dsm.cita_id, dsm.paquete_id, dsm.observaciones, dsm.creado_en FROM SaldoMovimientos dsm INNER JOIN nino n ON n.id = dsm.paciente_id WHERE DATE(dsm.creado_en) = ? ORDER BY dsm.id DESC LIMIT 50')) {
        $stmtMovimientosRecientes->bind_param('s', $fechaVista);
        $stmtMovimientosRecientes->execute();
        $result = $stmtMovimientosRecientes->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $movimientosRecientes[] = $row;
            }
        }
        $stmtMovimientosRecientes->close();
    }
}
?>

<div class="page-header">
    <h3 class="fw-bold mb-2">Pagos separados</h3>
    <div class="text-muted">Fase de pruebas con venta de paquetes, pagos de cita y saldo del paciente.</div>
</div>

<?php if (!$tienePermisoDemo): ?>
    <div class="alert alert-danger">No tienes permisos para usar este módulo.</div>
<?php else: ?>
    <?php if ($mensajeDemo !== null): ?>
        <div class="alert alert-<?php echo htmlspecialchars($tipoMensajeDemo, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeDemo, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h4 class="card-title mb-0">Citas de <?php echo htmlspecialchars($fechaVistaTitulo, ENT_QUOTES, 'UTF-8'); ?></h4>
            <div class="btn-group btn-group-sm" role="group" aria-label="Navegacion de dias">
                <a class="btn btn-outline-secondary" href="?fecha=<?php echo htmlspecialchars($fechaVistaAnterior, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-chevron-left me-1"></i>Regresar</a>
                <a class="btn btn-outline-primary" href="?fecha=<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">Hoy</a>
                <a class="btn btn-outline-secondary" href="?fecha=<?php echo htmlspecialchars($fechaVistaSiguiente, ENT_QUOTES, 'UTF-8'); ?>">Avanzar<i class="fas fa-chevron-right ms-1"></i></a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tablaDemoCitasHoy">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Horario</th>
                            <th>Paciente</th>
                            <th>Servicio</th>
                            <th>Estatus</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end" style="width: 150px;">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($citasHoy === []): ?>
                            <tr><td colspan="6" class="text-muted">No hay citas para hoy.</td></tr>
                        <?php else: ?>
                            <?php foreach ($citasHoy as $citaHoy): ?>
                                <?php
                                    $pagadoDemo = (float) $citaHoy['pagado_externo_demo'] + (float) $citaHoy['pagado_saldo_demo'];
                                    $pendienteDemo = max(0, (float) $citaHoy['costo'] - $pagadoDemo);
                                    $estatusNombre = (string) ($citaHoy['estatus_nombre'] ?? 'Sin estatus');
                                    $estatusNormalizado = strtolower($estatusNombre);
                                    $estatusBadgeClass = 'bg-secondary';
                                    $estatusIcono = 'fas fa-info-circle';
                                    if ($estatusNormalizado === 'creada') {
                                        $estatusBadgeClass = 'bg-primary';
                                        $estatusIcono = 'far fa-calendar-plus';
                                    } elseif ($estatusNormalizado === 'reprogramado') {
                                        $estatusBadgeClass = 'bg-warning text-dark';
                                        $estatusIcono = 'fas fa-clock';
                                    } elseif ($estatusNormalizado === 'finalizada') {
                                        $estatusBadgeClass = 'bg-success';
                                        $estatusIcono = 'fas fa-check-circle';
                                    } elseif ($estatusNormalizado === 'cancelada') {
                                        $estatusBadgeClass = 'bg-danger';
                                        $estatusIcono = 'fas fa-ban';
                                    }
                                    $demoBadgeClass = $pendienteDemo > 0.009 ? 'bg-danger' : 'bg-success';
                                    $demoIcono = $pendienteDemo > 0.009 ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?php echo htmlspecialchars(substr((string) $citaHoy['Hora'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="demo-compact-muted ms-2">#<?php echo (int) $citaHoy['id']; ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold demo-compact-line">
                                            <span><?php echo htmlspecialchars((string) $citaHoy['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <button type="button" class="btn btn-sm btn-demo-mini btn-outline-primary demo-open-patient-history" data-paciente-id="<?php echo (int) $citaHoy['IdNino']; ?>" data-paciente-nombre="<?php echo htmlspecialchars((string) $citaHoy['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Ver historial de citas">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </span>
                                        <span class="demo-compact-muted demo-compact-line">
                                            <span><?php echo htmlspecialchars((string) $citaHoy['psicologo_nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <button type="button" class="btn btn-sm btn-demo-mini btn-outline-info demo-open-psych-calendar" data-psicologo-id="<?php echo (int) $citaHoy['IdUsuario']; ?>" data-psicologo-nombre="<?php echo htmlspecialchars((string) $citaHoy['psicologo_nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Ver calendario de psicologa">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="demo-compact-line"><?php echo htmlspecialchars((string) $citaHoy['Tipo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="badge <?php echo $demoBadgeClass; ?> ms-2"><i class="<?php echo $demoIcono; ?> me-1"></i>Pagado $<?php echo number_format($pagadoDemo, 2); ?></span>
                                    </td>
                                    <td><span class="badge <?php echo $estatusBadgeClass; ?>"><i class="<?php echo $estatusIcono; ?> me-1"></i><?php echo htmlspecialchars($estatusNombre, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td class="text-end fw-semibold">$<?php echo number_format((float) $citaHoy['costo'], 2); ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if (in_array($estatusNormalizado, ['creada', 'reprogramado'], true)): ?>
                                                <button type="button" class="btn btn-outline-success py-1 px-2 demo-pay-today" data-cita="<?php echo (int) $citaHoy['id']; ?>" data-costo="<?php echo htmlspecialchars(number_format((float) $citaHoy['costo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) $citaHoy['saldo_demo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">Pagar</button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('¿Cancelar esta cita?');">
                                                    <input type="hidden" name="accion" value="cancelar_cita">
                                                    <input type="hidden" name="cita_id" value="<?php echo (int) $citaHoy['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger py-1 px-2">Cancelar</button>
                                                </form>
                                            <?php elseif ($estatusNormalizado === 'finalizada'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Completada</span>
                                            <?php elseif ($estatusNormalizado === 'cancelada'): ?>
                                                <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Cancelada</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success py-1 px-2 demo-pay-today" data-cita="<?php echo (int) $citaHoy['id']; ?>" data-costo="<?php echo htmlspecialchars(number_format((float) $citaHoy['costo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) $citaHoy['saldo_demo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">Pagar</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .demo-floating-package {
            position: fixed;
            right: 28px;
            bottom: 28px;
            z-index: 1050;
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .demo-floating-payment {
            position: fixed;
            right: 28px;
            bottom: 96px;
            z-index: 1050;
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .demo-floating-appointment {
            position: fixed;
            right: 28px;
            bottom: 164px;
            z-index: 1050;
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .demo-floating-no-appointment-payment {
            position: fixed;
            right: 28px;
            bottom: 232px;
            z-index: 1050;
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .demo-availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
        }
        .demo-availability-day {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .demo-availability-day-header {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 12px;
            font-weight: 700;
        }
        .demo-availability-slot {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
        }
        .demo-availability-slot:last-child {
            border-bottom: 0;
        }
        .demo-availability-slot.available {
            background: #f0fdf4;
            color: #166534;
        }
        .demo-availability-slot.busy {
            background: #fff7ed;
            color: #9a3412;
        }
        #tablaDemoCitasHoy td,
        #tablaDemoCitasHoy th {
            white-space: nowrap;
            vertical-align: middle;
        }
        .demo-compact-line {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-right: 10px;
        }
        .demo-compact-muted {
            color: #6c757d;
            font-size: 12px;
        }
        .btn-demo-mini {
            --bs-btn-padding-y: 0.05rem;
            --bs-btn-padding-x: 0.3rem;
            --bs-btn-font-size: 0.72rem;
            line-height: 1.1;
        }
    </style>

    <button type="button" class="btn btn-primary rounded-circle shadow-lg demo-floating-package" data-bs-toggle="modal" data-bs-target="#modalVentaPaqueteDemo" aria-label="Abrir venta de paquete" title="Venta de paquete">
        <i class="fas fa-box-open"></i>
    </button>

    <button type="button" class="btn btn-success rounded-circle shadow-lg demo-floating-payment" data-bs-toggle="modal" data-bs-target="#modalSaldoPagoDemo" aria-label="Abrir pago de saldo" title="Pagar adeudo o agregar saldo">
        <i class="fas fa-wallet"></i>
    </button>

    <button type="button" class="btn btn-info rounded-circle shadow-lg demo-floating-appointment" data-bs-toggle="modal" data-bs-target="#modalAgendarCitaDemo" aria-label="Abrir agenda de cita" title="Agendar cita">
        <i class="far fa-calendar-plus"></i>
    </button>

    <?php if ($esAdministradorDemo): ?>
        <button type="button" class="btn btn-warning rounded-circle shadow-lg demo-floating-no-appointment-payment" data-bs-toggle="modal" data-bs-target="#modalPagoSinCitaDemo" aria-label="Abrir pago sin cita" title="Registrar pago sin cita">
            <i class="fas fa-receipt"></i>
        </button>
    <?php endif; ?>

    <div class="modal fade" id="modalAgendarCitaDemo" tabindex="-1" aria-labelledby="modalAgendarCitaDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="registrar_cita">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalAgendarCitaDemoLabel"><i class="far fa-calendar-plus me-2"></i>Agendar cita</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="registroPacienteBuscar">Buscar paciente</label>
                                <input type="search" class="form-control patient-search" id="registroPacienteBuscar" data-target="registroPaciente" placeholder="Escribe el nombre">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="registroPaciente">Paciente</label>
                                <select class="form-select" id="registroPaciente" name="paciente_id" required>
                                    <option value="">Selecciona paciente</option>
                                    <?php foreach ($pacientes as $paciente): ?>
                                        <option value="<?php echo (int) $paciente['id']; ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) ($paciente['saldo_demo'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $paciente['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="registroPsicologo">Psicologa</label>
                                <select class="form-select" id="registroPsicologo" name="psicologo_id" required>
                                    <option value="">Selecciona psicologa</option>
                                    <?php foreach ($psicologos as $psicologo): ?>
                                        <option value="<?php echo (int) $psicologo['id']; ?>"><?php echo htmlspecialchars((string) $psicologo['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="registroProgramado">Fecha y hora</label>
                                <input type="datetime-local" class="form-control" id="registroProgramado" name="programado" step="any" value="<?php echo htmlspecialchars(date('Y-m-d\\TH:i'), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="registroTipo">Tipo de cita</label>
                                <input type="text" class="form-control" id="registroTipo" name="tipo" value="Consulta" maxlength="100" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="registroCosto">Costo</label>
                                <input type="number" class="form-control" id="registroCosto" name="costo" min="0" step="0.01" value="500" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="registroTiempo">Duracion minutos</label>
                                <input type="number" class="form-control" id="registroTiempo" name="tiempo" min="15" step="15" value="60" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info">Registrar cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalSaldoPagoDemo" tabindex="-1" aria-labelledby="modalSaldoPagoDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="agregar_saldo">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalSaldoPagoDemoLabel"><i class="fas fa-wallet me-2"></i>Pagar adeudo o agregar saldo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            Si el paciente tiene saldo negativo, este pago abona al adeudo. Si no tiene adeudo, se guarda como saldo disponible. El pago queda en el corte del dia en que se registra.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="saldoPacienteBuscar">Buscar paciente</label>
                                <input type="search" class="form-control patient-search" id="saldoPacienteBuscar" data-target="saldoPaciente" placeholder="Escribe el nombre">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="saldoPaciente">Paciente</label>
                                <select class="form-select" id="saldoPaciente" name="paciente_id" required>
                                    <option value="">Selecciona paciente</option>
                                    <?php foreach ($pacientes as $paciente): ?>
                                        <option value="<?php echo (int) $paciente['id']; ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) ($paciente['saldo_demo'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $paciente['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="saldoMetodo">Metodo de pago</label>
                                <select class="form-select" id="saldoMetodo" name="metodo_pago" required>
                                    <option value="">Selecciona metodo</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="saldoMonto">Monto pagado</label>
                                <input type="number" min="0.01" step="0.01" class="form-control" id="saldoMonto" name="monto" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="saldoObservaciones">Observaciones</label>
                                <input type="text" class="form-control" id="saldoObservaciones" name="observaciones" maxlength="255" placeholder="Opcional">
                            </div>
                            <div class="col-12">
                                <div class="row g-2" id="saldoDemoResumen">
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 bg-light h-100">
                                            <div class="text-muted small">Saldo actual</div>
                                            <div class="fw-bold" id="saldoActualDemoTexto">$0.00</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 bg-light h-100">
                                            <div class="text-muted small">Movimiento</div>
                                            <div class="fw-bold text-success" id="saldoMovimientoDemoTexto">+$0.00</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 bg-light h-100">
                                            <div class="text-muted small">Saldo estimado</div>
                                            <div class="fw-bold" id="saldoEstimadoDemoTexto">$0.00</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text" id="saldoAccionDemoTexto">Selecciona paciente y monto para ver el ajuste.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Registrar pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($esAdministradorDemo): ?>
        <div class="modal fade" id="modalPagoSinCitaDemo" tabindex="-1" aria-labelledby="modalPagoSinCitaDemoLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post">
                        <input type="hidden" name="accion" value="registrar_pago_sin_cita">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalPagoSinCitaDemoLabel"><i class="fas fa-receipt me-2"></i>Pago sin cita</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning py-2">
                                Este registro entra al corte del dia sin ligarse a una cita. El monto abona al adeudo del paciente y cualquier excedente queda como saldo disponible.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="pagoSinCitaTutorBuscar">Buscar tutor</label>
                                    <input type="search" class="form-control" id="pagoSinCitaTutorBuscar" placeholder="Escribe el nombre del tutor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="pagoSinCitaTutor">Tutor</label>
                                    <select class="form-select" id="pagoSinCitaTutor" name="tutor_id" required>
                                        <option value="">Selecciona tutor</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="pagoSinCitaPaciente">Paciente a acreditar</label>
                                    <select class="form-select" id="pagoSinCitaPaciente" name="paciente_id" required>
                                        <option value="">Selecciona primero un tutor</option>
                                    </select>
                                    <div class="form-text" id="pagoSinCitaSaldoTexto">El saldo estimado se mostrara al elegir paciente y monto.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="pagoSinCitaMetodo">Metodo de pago</label>
                                    <select class="form-select" id="pagoSinCitaMetodo" name="metodo_pago" required>
                                        <option value="">Selecciona metodo</option>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Tarjeta">Tarjeta</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="pagoSinCitaMonto">Monto pagado</label>
                                    <input type="number" min="0.01" step="0.01" class="form-control" id="pagoSinCitaMonto" name="monto" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="pagoSinCitaObservaciones">Observaciones</label>
                                    <input type="text" class="form-control" id="pagoSinCitaObservaciones" name="observaciones" maxlength="255" placeholder="Opcional">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning">Registrar pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="modalVentaPaqueteDemo" tabindex="-1" aria-labelledby="modalVentaPaqueteDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="vender_paquete">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalVentaPaqueteDemoLabel"><i class="fas fa-box-open me-2"></i>Venta de paquete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="paquetePacienteBuscar">Buscar paciente</label>
                                <input type="search" class="form-control patient-search" id="paquetePacienteBuscar" data-target="paquetePaciente" placeholder="Escribe el nombre">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="paquetePaciente">Paciente</label>
                                <select class="form-select" id="paquetePaciente" name="paciente_id" required>
                                    <option value="">Selecciona paciente</option>
                                    <?php foreach ($pacientes as $paciente): ?>
                                        <option value="<?php echo (int) $paciente['id']; ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) ($paciente['saldo_demo'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $paciente['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="paqueteId">Paquete</label>
                                <select class="form-select" id="paqueteId" name="paquete_id" required>
                                    <option value="">Selecciona paquete</option>
                                    <?php foreach ($paquetes as $paquete): ?>
                                        <option value="<?php echo (int) $paquete['id']; ?>" data-pago="<?php echo htmlspecialchars(number_format((float) $paquete['primer_pago_monto'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) $paquete['saldo_adicional'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) $paquete['nombre'], ENT_QUOTES, 'UTF-8'); ?> - pago $<?php echo number_format((float) $paquete['primer_pago_monto'], 2); ?> / saldo $<?php echo number_format((float) $paquete['saldo_adicional'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="paqueteMetodo">Metodo de pago</label>
                                <select class="form-select" id="paqueteMetodo" name="metodo_pago" required>
                                    <option value="">Selecciona metodo</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resumen</label>
                                <div class="border rounded p-2 bg-light small" id="paqueteResumen">Selecciona un paquete.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="paqueteObservaciones">Observaciones</label>
                                <input type="text" class="form-control" id="paqueteObservaciones" name="observaciones" maxlength="255" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar venta demo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPagoCitaDemo" tabindex="-1" aria-labelledby="modalPagoCitaDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="pagar_cita">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalPagoCitaDemoLabel"><i class="fas fa-cash-register me-2"></i>Pago de cita</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="citaId">Cita</label>
                                <select class="form-select" id="citaId" name="cita_id" required>
                                    <option value="">Selecciona cita</option>
                                    <?php foreach ($citas as $cita): ?>
                                        <option value="<?php echo (int) $cita['id']; ?>" data-costo="<?php echo htmlspecialchars(number_format((float) $cita['costo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" data-saldo="<?php echo htmlspecialchars(number_format((float) $cita['saldo_demo'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            #<?php echo (int) $cita['id']; ?> - <?php echo htmlspecialchars((string) $cita['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) $cita['Programado'], ENT_QUOTES, 'UTF-8'); ?> - costo $<?php echo number_format((float) $cita['costo'], 2); ?> - saldo $<?php echo number_format((float) $cita['saldo_demo'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-2 bg-light small" id="citaResumen">Selecciona una cita para ver costo y saldo disponible.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="citaMontoSaldo">Usar saldo</label>
                                <input type="number" min="0" step="0.01" class="form-control" id="citaMontoSaldo" name="monto_saldo" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="citaMetodo">Metodo externo</label>
                                <select class="form-select" id="citaMetodo" name="metodo_pago">
                                    <option value="">Sin pago externo</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="citaMontoExterno">Monto externo</label>
                                <input type="number" min="0" step="0.01" class="form-control" id="citaMontoExterno" name="monto_externo" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="citaObservaciones">Observaciones</label>
                                <input type="text" class="form-control" id="citaObservaciones" name="observaciones" maxlength="255" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Registrar pago demo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalHistorialPacienteDemo" tabindex="-1" aria-labelledby="modalHistorialPacienteDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHistorialPacienteDemoLabel"><i class="fas fa-user-clock me-2"></i>Historial de citas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="historialPacienteDemoBody">
                    <div class="text-muted">Selecciona un paciente.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAgendaPsicologoDemo" tabindex="-1" aria-labelledby="modalAgendaPsicologoDemoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgendaPsicologoDemoLabel"><i class="far fa-calendar-alt me-2"></i>Calendario de psicologa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="agendaPsicologoDemoBody">
                    <div class="text-muted">Selecciona una psicologa.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h4 class="card-title mb-0">Totales por metodo</h4></div>
                <div class="card-body">
                    <?php if ($resumenMetodo === []): ?>
                        <div class="text-muted">Sin pagos registrados.</div>
                    <?php else: ?>
                        <?php foreach ($resumenMetodo as $fila): ?>
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span><?php echo htmlspecialchars((string) $fila['metodo_pago'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong>$<?php echo number_format((float) $fila['total'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h4 class="card-title mb-0">Totales por origen</h4></div>
                <div class="card-body">
                    <?php if ($resumenOrigen === []): ?>
                        <div class="text-muted">Sin pagos registrados.</div>
                    <?php else: ?>
                        <?php foreach ($resumenOrigen as $fila): ?>
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span><?php echo htmlspecialchars((string) $fila['origen'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong>$<?php echo number_format((float) $fila['total'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Pagos del día</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tablaDemoPagos">
                    <thead><tr><th>ID</th><th>Fecha</th><th>Origen</th><th>Paciente</th><th>Psicologo</th><th>Metodo</th><th>Monto</th><th>Nota</th></tr></thead>
                    <tbody>
                        <?php foreach ($pagosRecientes as $pago): ?>
                            <tr>
                                <td>#<?php echo (int) $pago['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $pago['fecha_pago'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $pago['origen'], ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) $pago['referencia_id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $pago['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pago['psicologo_nombre'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $pago['metodo_pago'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="fw-semibold">$<?php echo number_format((float) $pago['monto'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pago['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">Movimientos de saldo</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tablaDemoSaldo">
                    <thead><tr><th>ID</th><th>Fecha</th><th>Paciente</th><th>Tipo</th><th>Monto</th><th>Saldo anterior</th><th>Saldo nuevo</th><th>Referencia</th><th>Nota</th></tr></thead>
                    <tbody>
                        <?php foreach ($movimientosRecientes as $mov): ?>
                            <tr>
                                <td>#<?php echo (int) $mov['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $mov['creado_en'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $mov['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $mov['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="fw-semibold <?php echo (float) $mov['monto'] < 0 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format((float) $mov['monto'], 2); ?></td>
                                <td>$<?php echo number_format((float) $mov['saldo_anterior'], 2); ?></td>
                                <td>$<?php echo number_format((float) $mov['saldo_nuevo'], 2); ?></td>
                                <td><?php echo $mov['cita_id'] ? 'Cita #' . (int) $mov['cita_id'] : ($mov['paquete_id'] ? 'Paquete #' . (int) $mov['paquete_id'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mov['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php $conn->close(); ?>
<?php include '../Modulos/footer.php'; ?>
<script>
    (function () {
        const currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const paqueteSelect = document.getElementById('paqueteId');
        const paqueteResumen = document.getElementById('paqueteResumen');
        const citaSelect = document.getElementById('citaId');
        const citaResumen = document.getElementById('citaResumen');
        const citaMontoSaldo = document.getElementById('citaMontoSaldo');
        const citaMontoExterno = document.getElementById('citaMontoExterno');
        const registroProgramado = document.getElementById('registroProgramado');
        const saldoPacienteSelect = document.getElementById('saldoPaciente');
        const saldoMontoInput = document.getElementById('saldoMonto');
        const saldoActualDemoTexto = document.getElementById('saldoActualDemoTexto');
        const saldoMovimientoDemoTexto = document.getElementById('saldoMovimientoDemoTexto');
        const saldoEstimadoDemoTexto = document.getElementById('saldoEstimadoDemoTexto');
        const saldoAccionDemoTexto = document.getElementById('saldoAccionDemoTexto');
        const pagoSinCitaTutorBuscar = document.getElementById('pagoSinCitaTutorBuscar');
        const pagoSinCitaTutorSelect = document.getElementById('pagoSinCitaTutor');
        const pagoSinCitaPacienteSelect = document.getElementById('pagoSinCitaPaciente');
        const pagoSinCitaMontoInput = document.getElementById('pagoSinCitaMonto');
        const pagoSinCitaSaldoTexto = document.getElementById('pagoSinCitaSaldoTexto');
        const tutoresPagoSinCita = <?php echo json_encode($tutoresPagoSinCita, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const historialPacientes = <?php echo json_encode($historialPacientesDemo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const agendaPsicologos = <?php echo json_encode($agendaPsicologosDemo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function toNumber(value) {
            const parsed = Number.parseFloat(value || '0');
            return Number.isNaN(parsed) ? 0 : parsed;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatDateTime(value) {
            if (!value) {
                return '-';
            }
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return String(value);
            }
            return date.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
        }

        function pad2(value) {
            return String(value).padStart(2, '0');
        }

        function toDateInputKey(date) {
            return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
        }

        function parseLocalDateTime(value) {
            if (!value) {
                return null;
            }
            const date = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function renderPsychAvailability(items) {
            const appointmentsBySlot = {};
            (Array.isArray(items) ? items : []).forEach(function (item) {
                const date = parseLocalDateTime(item.programado);
                if (!date) {
                    return;
                }
                const key = toDateInputKey(date) + ' ' + pad2(date.getHours()) + ':00';
                appointmentsBySlot[key] = item;
            });

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const dayCards = [];
            for (let dayOffset = 0; dayOffset < 7; dayOffset += 1) {
                const day = new Date(today.getTime());
                day.setDate(today.getDate() + dayOffset);
                const dayKey = toDateInputKey(day);
                const dayLabel = day.toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short' });
                const slots = [];
                for (let hour = 8; hour <= 19; hour += 1) {
                    const slotKey = dayKey + ' ' + pad2(hour) + ':00';
                    const appointment = appointmentsBySlot[slotKey];
                    if (appointment) {
                        slots.push('<div class="demo-availability-slot busy">'
                            + '<span><i class="fas fa-user-clock me-1"></i>' + pad2(hour) + ':00</span>'
                            + '<span class="text-end">' + escapeHtml(appointment.paciente || 'Ocupado') + '</span>'
                            + '</div>');
                    } else {
                        slots.push('<div class="demo-availability-slot available">'
                            + '<span><i class="fas fa-check-circle me-1"></i>' + pad2(hour) + ':00</span>'
                            + '<span>Disponible</span>'
                            + '</div>');
                    }
                }
                dayCards.push('<div class="demo-availability-day">'
                    + '<div class="demo-availability-day-header">' + escapeHtml(dayLabel) + '</div>'
                    + slots.join('')
                    + '</div>');
            }

            return '<div class="d-flex flex-wrap gap-2 mb-3">'
                + '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disponible</span>'
                + '<span class="badge bg-warning text-dark"><i class="fas fa-user-clock me-1"></i>Ocupado</span>'
                + '</div>'
                + '<div class="demo-availability-grid">' + dayCards.join('') + '</div>';
        }

        function updateSaldoDemoResumen() {
            if (!saldoPacienteSelect || !saldoActualDemoTexto || !saldoMovimientoDemoTexto || !saldoEstimadoDemoTexto || !saldoAccionDemoTexto) {
                return;
            }

            const option = saldoPacienteSelect.options[saldoPacienteSelect.selectedIndex];
            const saldoActual = option && option.value ? toNumber(option.dataset.saldo) : 0;
            const monto = saldoMontoInput ? Math.max(0, toNumber(saldoMontoInput.value)) : 0;
            const saldoEstimado = saldoActual + monto;

            saldoActualDemoTexto.textContent = currency.format(saldoActual);
            saldoMovimientoDemoTexto.textContent = '+' + currency.format(monto);
            saldoEstimadoDemoTexto.textContent = currency.format(saldoEstimado);
            saldoActualDemoTexto.className = 'fw-bold ' + (saldoActual < -0.0001 ? 'text-danger' : (saldoActual > 0.0001 ? 'text-success' : ''));
            saldoEstimadoDemoTexto.className = 'fw-bold ' + (saldoEstimado < -0.0001 ? 'text-danger' : (saldoEstimado > 0.0001 ? 'text-success' : ''));

            if (!option || !option.value) {
                saldoAccionDemoTexto.textContent = 'Selecciona paciente y monto para ver el ajuste.';
            } else if (saldoActual < -0.0001) {
                saldoAccionDemoTexto.textContent = 'Este pago abonara al adeudo del paciente.';
            } else {
                saldoAccionDemoTexto.textContent = 'Este pago se agregara como saldo disponible.';
            }
        }

        function renderCitasList(items, emptyText, mode) {
            if (!Array.isArray(items) || items.length === 0) {
                return '<div class="text-muted">' + escapeHtml(emptyText) + '</div>';
            }

            return '<div class="list-group list-group-flush">' + items.map(function (item) {
                const title = mode === 'paciente' ? item.psicologo : item.paciente;
                const icon = mode === 'paciente' ? 'fas fa-user-md' : 'fas fa-user';
                return '<div class="list-group-item px-0">'
                    + '<div class="d-flex justify-content-between gap-3">'
                    + '<div>'
                    + '<div class="fw-semibold"><i class="' + icon + ' me-2 text-primary"></i>' + escapeHtml(title || 'Sin registro') + '</div>'
                    + '<div class="small text-muted">#' + escapeHtml(item.id) + ' · ' + escapeHtml(item.tipo || '-') + ' · ' + formatDateTime(item.programado) + '</div>'
                    + '</div>'
                    + '<div class="text-end">'
                    + '<span class="badge bg-light text-dark border">' + escapeHtml(item.estatus || 'Sin estatus') + '</span>'
                    + '<div class="small fw-semibold mt-1">' + currency.format(toNumber(item.costo)) + '</div>'
                    + '</div>'
                    + '</div>'
                    + '</div>';
            }).join('') + '</div>';
        }

        function updatePaqueteResumen() {
            if (!paqueteSelect || !paqueteResumen) {
                return;
            }
            const option = paqueteSelect.options[paqueteSelect.selectedIndex];
            if (!option || !option.value) {
                paqueteResumen.textContent = 'Selecciona un paquete.';
                return;
            }
            const pago = toNumber(option.dataset.pago);
            const saldo = toNumber(option.dataset.saldo);
            paqueteResumen.textContent = 'Pago a caja: ' + currency.format(pago) + ' | Saldo otorgado: ' + currency.format(saldo);
        }

        function updateCitaResumen() {
            if (!citaSelect || !citaResumen) {
                return;
            }
            const option = citaSelect.options[citaSelect.selectedIndex];
            if (!option || !option.value) {
                citaResumen.textContent = 'Selecciona una cita para ver costo y saldo disponible.';
                return;
            }
            const costo = toNumber(option.dataset.costo);
            const saldo = toNumber(option.dataset.saldo);
            citaResumen.textContent = 'Costo de cita: ' + currency.format(costo) + ' | Saldo disponible: ' + currency.format(saldo);
            if (citaMontoSaldo && toNumber(citaMontoSaldo.value) === 0 && saldo > 0) {
                citaMontoSaldo.value = Math.min(costo, saldo).toFixed(2);
            }
            if (citaMontoExterno) {
                const saldoUsado = citaMontoSaldo ? toNumber(citaMontoSaldo.value) : 0;
                citaMontoExterno.value = Math.max(0, costo - saldoUsado).toFixed(2);
            }
        }

        function renderPagoSinCitaTutores(query) {
            if (!pagoSinCitaTutorSelect) {
                return;
            }

            const normalizedQuery = normalizeSearch(query);
            const tutorMap = new Map();
            tutoresPagoSinCita.forEach(function (item) {
                const tutorId = String(item.tutor_id || '');
                if (!tutorId || tutorMap.has(tutorId)) {
                    return;
                }
                const tutorNombre = String(item.tutor_nombre || 'Tutor sin nombre');
                if (normalizedQuery !== '' && normalizeSearch(tutorNombre).indexOf(normalizedQuery) === -1) {
                    return;
                }
                tutorMap.set(tutorId, tutorNombre);
            });

            const currentValue = pagoSinCitaTutorSelect.value;
            pagoSinCitaTutorSelect.innerHTML = '<option value="">Selecciona tutor</option>';
            tutorMap.forEach(function (nombre, id) {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = nombre + ' #' + id;
                pagoSinCitaTutorSelect.appendChild(option);
            });
            if (currentValue && tutorMap.has(currentValue)) {
                pagoSinCitaTutorSelect.value = currentValue;
            }
        }

        function updatePagoSinCitaPacientes() {
            if (!pagoSinCitaTutorSelect || !pagoSinCitaPacienteSelect) {
                return;
            }

            const tutorId = pagoSinCitaTutorSelect.value;
            pagoSinCitaPacienteSelect.innerHTML = tutorId ? '<option value="">Selecciona paciente</option>' : '<option value="">Selecciona primero un tutor</option>';
            tutoresPagoSinCita.forEach(function (item) {
                if (String(item.tutor_id || '') !== tutorId) {
                    return;
                }
                const option = document.createElement('option');
                option.value = item.paciente_id;
                option.dataset.saldo = String(item.saldo_demo || 0);
                option.textContent = String(item.paciente_nombre || 'Paciente sin nombre') + ' - saldo ' + currency.format(toNumber(item.saldo_demo));
                pagoSinCitaPacienteSelect.appendChild(option);
            });
            updatePagoSinCitaSaldo();
        }

        function updatePagoSinCitaSaldo() {
            if (!pagoSinCitaPacienteSelect || !pagoSinCitaSaldoTexto) {
                return;
            }

            const option = pagoSinCitaPacienteSelect.options[pagoSinCitaPacienteSelect.selectedIndex];
            const saldoActual = option && option.value ? toNumber(option.dataset.saldo) : 0;
            const monto = pagoSinCitaMontoInput ? Math.max(0, toNumber(pagoSinCitaMontoInput.value)) : 0;
            const saldoNuevo = saldoActual + monto;
            if (!option || !option.value) {
                pagoSinCitaSaldoTexto.textContent = 'El saldo estimado se mostrara al elegir paciente y monto.';
                return;
            }
            pagoSinCitaSaldoTexto.textContent = 'Saldo actual: ' + currency.format(saldoActual) + ' | Pago: +' + currency.format(monto) + ' | Saldo final: ' + currency.format(saldoNuevo);
        }

        function normalizeSearch(value) {
            return String(value || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        document.querySelectorAll('.patient-search').forEach(function (input) {
            const select = document.getElementById(input.dataset.target || '');
            if (!select) {
                return;
            }

            let searchTimer = null;

            input.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    const term = input.value.trim();
                    select.innerHTML = '<option value="">Buscando...</option>';

                    fetch('buscar_pacientes.php?q=' + encodeURIComponent(term), { credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (payload) {
                            select.innerHTML = '<option value="">Selecciona paciente</option>';
                            if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                                return;
                            }

                            payload.data.forEach(function (paciente) {
                                const option = document.createElement('option');
                                option.value = paciente.id;
                                option.textContent = paciente.name;
                                option.dataset.saldo = String(paciente.saldo_demo || 0);
                                select.appendChild(option);
                            });

                            if (select.id === 'saldoPaciente') {
                                updateSaldoDemoResumen();
                            }
                        })
                        .catch(function () {
                            select.innerHTML = '<option value="">No se pudo buscar</option>';
                            if (select.id === 'saldoPaciente') {
                                updateSaldoDemoResumen();
                            }
                        });
                }, 250);
            });
        });

        if (saldoPacienteSelect) {
            saldoPacienteSelect.addEventListener('change', updateSaldoDemoResumen);
            updateSaldoDemoResumen();
        }
        if (saldoMontoInput) {
            saldoMontoInput.addEventListener('input', updateSaldoDemoResumen);
            saldoMontoInput.addEventListener('change', updateSaldoDemoResumen);
        }

        if (pagoSinCitaTutorSelect) {
            renderPagoSinCitaTutores('');
            pagoSinCitaTutorSelect.addEventListener('change', updatePagoSinCitaPacientes);
        }
        if (pagoSinCitaTutorBuscar) {
            pagoSinCitaTutorBuscar.addEventListener('input', function () {
                renderPagoSinCitaTutores(pagoSinCitaTutorBuscar.value);
                updatePagoSinCitaPacientes();
            });
        }
        if (pagoSinCitaPacienteSelect) {
            pagoSinCitaPacienteSelect.addEventListener('change', updatePagoSinCitaSaldo);
        }
        if (pagoSinCitaMontoInput) {
            pagoSinCitaMontoInput.addEventListener('input', updatePagoSinCitaSaldo);
            pagoSinCitaMontoInput.addEventListener('change', updatePagoSinCitaSaldo);
        }

        if (paqueteSelect) {
            paqueteSelect.addEventListener('change', updatePaqueteResumen);
            updatePaqueteResumen();
        }
        if (citaSelect) {
            citaSelect.addEventListener('change', updateCitaResumen);
            updateCitaResumen();
        }
        if (citaMontoSaldo && citaMontoExterno && citaSelect) {
            citaMontoSaldo.addEventListener('input', function () {
                const option = citaSelect.options[citaSelect.selectedIndex];
                const costo = option ? toNumber(option.dataset.costo) : 0;
                citaMontoExterno.value = Math.max(0, costo - toNumber(citaMontoSaldo.value)).toFixed(2);
            });
        }

        if (registroProgramado && !registroProgramado.value) {
            const now = new Date();
            now.setMinutes(Math.ceil(now.getMinutes() / 15) * 15, 0, 0);
            const offsetMs = now.getTimezoneOffset() * 60000;
            registroProgramado.value = new Date(now.getTime() - offsetMs).toISOString().slice(0, 16);
        }

        document.querySelectorAll('.demo-pay-today').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!citaSelect) {
                    return;
                }
                citaSelect.value = button.dataset.cita || '';
                if (citaSelect.value === '' && button.dataset.cita) {
                    const option = document.createElement('option');
                    option.value = button.dataset.cita;
                    option.dataset.costo = button.dataset.costo || '0';
                    option.dataset.saldo = button.dataset.saldo || '0';
                    option.textContent = '#' + button.dataset.cita + ' - cita de hoy';
                    citaSelect.appendChild(option);
                    citaSelect.value = button.dataset.cita;
                }
                updateCitaResumen();
                const pagoModalElement = document.getElementById('modalPagoCitaDemo');
                if (pagoModalElement && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(pagoModalElement).show();
                }
            });
        });

        document.querySelectorAll('.demo-open-patient-history').forEach(function (button) {
            button.addEventListener('click', function () {
                const pacienteId = button.dataset.pacienteId || '';
                const pacienteNombre = button.dataset.pacienteNombre || 'Paciente';
                const title = document.getElementById('modalHistorialPacienteDemoLabel');
                const body = document.getElementById('historialPacienteDemoBody');
                const modalElement = document.getElementById('modalHistorialPacienteDemo');
                if (!body || !modalElement) {
                    return;
                }
                if (title) {
                    title.innerHTML = '<i class="fas fa-user-clock me-2"></i>Historial de ' + escapeHtml(pacienteNombre);
                }
                body.innerHTML = renderCitasList(historialPacientes[pacienteId] || [], 'Sin historial reciente para este paciente.', 'paciente');
                if (window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        });

        document.querySelectorAll('.demo-open-psych-calendar').forEach(function (button) {
            button.addEventListener('click', function () {
                const psicologoId = button.dataset.psicologoId || '';
                const psicologoNombre = button.dataset.psicologoNombre || 'Psicologa';
                const title = document.getElementById('modalAgendaPsicologoDemoLabel');
                const body = document.getElementById('agendaPsicologoDemoBody');
                const modalElement = document.getElementById('modalAgendaPsicologoDemo');
                if (!body || !modalElement) {
                    return;
                }
                if (title) {
                    title.innerHTML = '<i class="far fa-calendar-alt me-2"></i>Disponibilidad de ' + escapeHtml(psicologoNombre);
                }
                body.innerHTML = renderPsychAvailability(agendaPsicologos[psicologoId] || []);
                if (window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        });

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.DataTable === 'function') {
            ['#tablaDemoCitasHoy', '#tablaDemoPagos', '#tablaDemoSaldo'].forEach(function (selector) {
                if (window.jQuery(selector).length) {
                    window.jQuery(selector).DataTable({
                        order: selector === '#tablaDemoCitasHoy' ? [] : [[0, 'desc']],
                        pageLength: 10,
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_',
                            zeroRecords: 'Sin resultados',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_',
                            infoEmpty: 'Sin registros',
                            paginate: { first: 'Primero', last: 'Ultimo', next: 'Siguiente', previous: 'Anterior' }
                        }
                    });
                }
            });
        }
    })();
</script>
