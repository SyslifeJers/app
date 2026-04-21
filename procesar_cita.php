<?php
session_start();
header('Content-Type: application/json');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Mexico_City');

$ROL_PRACTICANTE = 6;
if (isset($_SESSION['rol']) && (int) $_SESSION['rol'] === $ROL_PRACTICANTE) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear o modificar citas.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $required = ['sendIdCliente', 'sendIdPsicologo', 'resumenTipo', 'resumenFecha', 'resumenCosto'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
            exit;
        }

        $valor = is_string($_POST[$field]) ? trim($_POST[$field]) : $_POST[$field];
        if ($valor === '') {
            echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
            exit;
        }
    }

    require_once 'conexion.php';
    require_once __DIR__ . '/Modulos/conflictos_agenda.php';
    require_once __DIR__ . '/Modulos/logger.php';
    require_once __DIR__ . '/Modulos/resumen_pagos.php';
    require_once __DIR__ . '/Modulos/saldo_pacientes.php';

    $conn = conectar();
    $conn->set_charset('utf8');

    $idCliente = isset($_POST['sendIdCliente']) ? (int) $_POST['sendIdCliente'] : 0;
    $idPsicologo = isset($_POST['sendIdPsicologo']) ? (int) $_POST['sendIdPsicologo'] : 0;
    $pacienteNombre = isset($_POST['sendClienteNombre']) ? trim((string) $_POST['sendClienteNombre']) : '';
    $psicologoNombre = isset($_POST['sendPsicologoNombre']) ? trim((string) $_POST['sendPsicologoNombre']) : '';
    $tipo = trim($_POST['resumenTipo']);
    $fechaCita = $_POST['resumenFecha'];
    $idGenerado = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
    $estatus = 2;
    $fechaActual = date('Y-m-d H:i:s');
    $costo = (float) $_POST['resumenCosto'];
    if ($costo < 0) {
        echo json_encode(['success' => false, 'message' => 'El costo no puede ser negativo.']);
        $conn->close();
        exit;
    }

    $tiempoRaw = $_POST['resumenTiempo'] ?? 60;
    $tiempo = (int) $tiempoRaw;
    $forzar = isset($_POST['forzar']) && (int) $_POST['forzar'] === 1;
    if ($tiempo <= 0) {
        echo json_encode(['success' => false, 'message' => 'El tiempo de la cita debe ser mayor a 0 minutos.']);
        $conn->close();
        exit;
    }

    $paqueteId = isset($_POST['sendIdPaquete']) && $_POST['sendIdPaquete'] !== '' ? (int) $_POST['sendIdPaquete'] : null;
    $paqueteMetodo = isset($_POST['paqueteMetodo']) ? trim($_POST['paqueteMetodo']) : '';
    $paqueteInfo = null;

    if ($paqueteId !== null) {
        $metodosPermitidos = ['Efectivo', 'Transferencia'];
        if (!in_array($paqueteMetodo, $metodosPermitidos, true)) {
            echo json_encode(['success' => false, 'message' => 'Selecciona un método de pago válido para el paquete.']);
            $conn->close();
            exit;
        }

        $stmtPaquete = $conn->prepare('SELECT id, nombre, primer_pago_monto, saldo_adicional FROM Paquetes WHERE id = ? AND activo = 1');
        if (!$stmtPaquete) {
            echo json_encode(['success' => false, 'message' => 'No fue posible consultar el paquete seleccionado.']);
            $conn->close();
            exit;
        }

        $stmtPaquete->bind_param('i', $paqueteId);
        $stmtPaquete->execute();
        $stmtPaquete->bind_result($paqueteDbId, $paqueteNombre, $paquetePrimerPago, $paqueteSaldoAdicional);
        if ($stmtPaquete->fetch()) {
            $paqueteInfo = [
                'id' => (int) $paqueteDbId,
                'nombre' => $paqueteNombre,
                'primer_pago_monto' => (float) $paquetePrimerPago,
                'saldo_adicional' => (float) $paqueteSaldoAdicional,
            ];
        }
        $stmtPaquete->close();

        if ($paqueteInfo === null) {
            echo json_encode(['success' => false, 'message' => 'El paquete seleccionado no está disponible.']);
            $conn->close();
            exit;
        }
    } else {
        $paqueteMetodo = '';
    }

    // Revisión rápida: evitar duplicados con misma fecha y usuario
    $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
    if (!$check) {
        echo json_encode(['success' => false, 'message' => 'No fue posible validar la disponibilidad de la cita.']);
        $conn->close();
        exit;
    }
    $check->bind_param('is', $idCliente, $fechaCita);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una cita registrada para este paciente en esa fecha y hora.']);
        $conn->close();
        exit;
    }

    try {
        $conflictoAgenda = obtenerConflictoAgendaPsicologo($conn, $idPsicologo, $fechaCita, $tiempo, null, $idCliente);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        $conn->close();
        exit;
    }

    if ($conflictoAgenda !== null && !$forzar) {
        http_response_code(409);
        echo json_encode(array_merge([
            'success' => false,
        ], construirPayloadConflictoAgenda($conflictoAgenda)));
        $conn->close();
        exit;
    }

    $conn->begin_transaction();

    try {
        $forzada = ($conflictoAgenda !== null && $forzar) ? 1 : 0;
        $sql = 'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Tiempo, forzada, Estatus, Tipo, paquete_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('No fue posible preparar la creación de la cita.');
        }

        $stmt->bind_param('iiisdsiiisi', $idCliente, $idPsicologo, $idGenerado, $fechaActual, $costo, $fechaCita, $tiempo, $forzada, $estatus, $tipo, $paqueteId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('No fue posible guardar la cita.');
        }

        $nuevaCitaId = $conn->insert_id;
        $stmt->close();

        $descripcionPaquete = '';

        if ($paqueteInfo !== null) {
            $montoInicial = (float) $paqueteInfo['primer_pago_monto'];
            $saldoOtorgado = (float) $paqueteInfo['saldo_adicional'];

            $stmtPago = $conn->prepare('INSERT INTO CitaPagos (cita_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)');
            if (!$stmtPago) {
                throw new Exception('No fue posible registrar el pago inicial del paquete.');
            }
            $stmtPago->bind_param('isdi', $nuevaCitaId, $paqueteMetodo, $montoInicial, $idGenerado);
            if (!$stmtPago->execute()) {
                $stmtPago->close();
                throw new Exception('Ocurrió un error al guardar el pago inicial del paquete.');
            }
            $stmtPago->close();

            registrarResumenPago($conn, [
                'origen' => 'cita',
                'referencia_id' => $nuevaCitaId,
                'cita_id' => $nuevaCitaId,
                'paciente_id' => $idCliente,
                'paciente_nombre' => $pacienteNombre,
                'psicologo_id' => $idPsicologo,
                'psicologo_nombre' => $psicologoNombre,
                'monto' => $montoInicial,
                'metodo_pago' => $paqueteMetodo,
                'fecha_pago' => $fechaActual,
                'fecha_corte' => substr($fechaActual, 0, 10),
                'registrado_por' => $idGenerado,
                'observaciones' => sprintf('Pago inicial de paquete %s.', $paqueteInfo['nombre']),
            ]);

            $formaPagoPaquete = sprintf('Paquete (%s)', $paqueteMetodo);
            $stmtActualizar = $conn->prepare('UPDATE Cita SET FormaPago = ? WHERE id = ?');
            if ($stmtActualizar) {
                $stmtActualizar->bind_param('si', $formaPagoPaquete, $nuevaCitaId);
                $stmtActualizar->execute();
                $stmtActualizar->close();
            }

            if ($saldoOtorgado > 0) {
                if (!ajustarSaldoPaciente($conn, $idCliente, $saldoOtorgado)) {
                    throw new Exception('No fue posible actualizar el saldo disponible del paciente.');
                }
            }

            $descripcionPaquete = sprintf(
                ' Se aplicó el paquete "%s" con pago inicial de %.2f (%s) y saldo adicional de %.2f.',
                $paqueteInfo['nombre'],
                $montoInicial,
                $paqueteMetodo,
                $saldoOtorgado
            );
        }

        registrarLog(
            $conn,
            $idGenerado,
            'citas',
            'crear',
            sprintf(
                'Se creó la cita #%d para el paciente %d con el psicólogo %d programada el %s%s.',
                $nuevaCitaId,
                $idCliente,
                $idPsicologo,
                $fechaCita,
                $forzada === 1 ? ' con conflicto forzado' : ''
            ) . $descripcionPaquete,
            'Cita',
            (string) $nuevaCitaId
        );

        $conn->commit();
        echo json_encode(['success' => true, 'forzada' => $forzada === 1]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
