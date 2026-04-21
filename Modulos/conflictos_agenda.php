<?php

declare(strict_types=1);

function obtenerConflictoAgendaPsicologo(mysqli $conn, int $psicologoId, string $inicioProgramado, int $tiempoMinutos = 60, ?int $excluirCitaId = null, ?int $pacienteId = null): ?array
{
    if ($psicologoId <= 0) {
        return null;
    }

    $tiempoMinutos = $tiempoMinutos > 0 ? $tiempoMinutos : 60;

    $sql = 'SELECT ci.id,
                   ci.Programado,
                   COALESCE(ci.Tiempo, 60) AS Tiempo,
                   DATE_ADD(ci.Programado, INTERVAL COALESCE(ci.Tiempo, 60) MINUTE) AS Termina,
                   n.name AS paciente,
                   u.name AS psicologo
            FROM Cita ci
            INNER JOIN nino n ON n.id = ci.IdNino
            INNER JOIN Usuarios u ON u.id = ci.IdUsuario
            WHERE ci.IdUsuario = ?
              AND ci.Estatus IN (2, 3)';

    $tipos = 'isis';
    $parametros = [$psicologoId, $inicioProgramado, $tiempoMinutos, $inicioProgramado];

    if ($excluirCitaId !== null && $excluirCitaId > 0) {
        $sql .= ' AND ci.id <> ?';
        $tipos .= 'i';
        $parametros[] = $excluirCitaId;
    }

    $sql .= ' AND ci.Programado < DATE_ADD(?, INTERVAL ? MINUTE)
              AND DATE_ADD(ci.Programado, INTERVAL COALESCE(ci.Tiempo, 60) MINUTE) > ?
            ORDER BY ci.Programado ASC
            LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('No fue posible validar la disponibilidad de la psicóloga.');
    }

    $stmt->bind_param($tipos, ...$parametros);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $conflicto = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    $conflictoCita = null;
    if (is_array($conflicto)) {
        $conflictoCita = [
            'cita_id' => (int) ($conflicto['id'] ?? 0),
            'programado' => (string) ($conflicto['Programado'] ?? ''),
            'termina' => (string) ($conflicto['Termina'] ?? ''),
            'tiempo' => (int) ($conflicto['Tiempo'] ?? 60),
            'paciente' => (string) ($conflicto['paciente'] ?? ''),
            'psicologo' => (string) ($conflicto['psicologo'] ?? ''),
            'psicologo_id' => $psicologoId,
            'source_type' => 'cita',
        ];
    }

    $sqlReservacion = 'SELECT rc.id,
                              rc.paciente_id,
                              rc.psicologo_id,
                              rc.tipo,
                              rc.hora_inicio,
                              rc.tiempo,
                              rc.fecha_inicio,
                              rc.fecha_fin,
                              n.name AS paciente,
                              u.name AS psicologo
                       FROM ReservacionContinua rc
                       INNER JOIN ReservacionContinuaDia rcd ON rcd.reservacion_id = rc.id
                       INNER JOIN nino n ON n.id = rc.paciente_id
                       INNER JOIN Usuarios u ON u.id = rc.psicologo_id
                       WHERE rc.activo = 1
                         AND rc.psicologo_id = ?
                         AND rc.fecha_inicio <= DATE(?)
                         AND (rc.fecha_fin IS NULL OR rc.fecha_fin >= DATE(?))
                         AND rcd.dia_semana = (WEEKDAY(DATE(?)) + 1)
                         AND rc.hora_inicio < TIME(DATE_ADD(?, INTERVAL ? MINUTE))
                         AND ADDTIME(rc.hora_inicio, SEC_TO_TIME(rc.tiempo * 60)) > TIME(?)';

    $tiposReservacion = 'issssis';
    $parametrosReservacion = [$psicologoId, $inicioProgramado, $inicioProgramado, $inicioProgramado, $inicioProgramado, $tiempoMinutos, $inicioProgramado];

    if ($pacienteId !== null && $pacienteId > 0) {
        $sqlReservacion .= ' AND rc.paciente_id <> ?';
        $tiposReservacion .= 'i';
        $parametrosReservacion[] = $pacienteId;
    }

    $sqlReservacion .= ' ORDER BY rc.hora_inicio ASC LIMIT 1';

    $stmtReservacion = $conn->prepare($sqlReservacion);
    if ($stmtReservacion === false) {
        throw new RuntimeException('No fue posible validar las reservaciones continuas de la psicóloga.');
    }

    $stmtReservacion->bind_param($tiposReservacion, ...$parametrosReservacion);
    $stmtReservacion->execute();
    $resultadoReservacion = $stmtReservacion->get_result();
    $reservacion = $resultadoReservacion ? $resultadoReservacion->fetch_assoc() : null;
    $stmtReservacion->close();

    if (!is_array($reservacion)) {
        return $conflictoCita;
    }

    $fechaBase = new DateTime($inicioProgramado);
    $horaInicio = DateTime::createFromFormat('H:i:s', (string) $reservacion['hora_inicio']);
    if ($horaInicio instanceof DateTime) {
        $fechaBase->setTime((int) $horaInicio->format('H'), (int) $horaInicio->format('i'), (int) $horaInicio->format('s'));
    }
    $fechaFin = clone $fechaBase;
    $fechaFin->modify('+' . max(1, (int) $reservacion['tiempo']) . ' minutes');

    $conflictoReservacion = [
        'cita_id' => 0,
        'reservacion_id' => (int) ($reservacion['id'] ?? 0),
        'programado' => $fechaBase->format('Y-m-d H:i:s'),
        'termina' => $fechaFin->format('Y-m-d H:i:s'),
        'tiempo' => (int) ($reservacion['tiempo'] ?? 60),
        'paciente' => (string) ($reservacion['paciente'] ?? ''),
        'psicologo' => (string) ($reservacion['psicologo'] ?? ''),
        'psicologo_id' => $psicologoId,
        'tipo' => (string) ($reservacion['tipo'] ?? ''),
        'source_type' => 'reservacion_continua',
    ];

    if ($conflictoCita === null) {
        return $conflictoReservacion;
    }

    $inicioCita = strtotime((string) $conflictoCita['programado']);
    $inicioReservacion = strtotime((string) $conflictoReservacion['programado']);
    if ($inicioReservacion !== false && ($inicioCita === false || $inicioReservacion <= $inicioCita)) {
        return $conflictoReservacion;
    }

    return $conflictoCita;
}

function construirPayloadConflictoAgenda(array $conflicto, string $mensaje = 'La psicóloga seleccionada ya tiene una cita en ese horario.'): array
{
    return [
        'conflict' => true,
        'conflict_type' => 'psicologo_ocupado',
        'message' => $mensaje,
        'conflict_data' => [
            'cita_id' => (int) ($conflicto['cita_id'] ?? 0),
            'reservacion_id' => (int) ($conflicto['reservacion_id'] ?? 0),
            'programado' => (string) ($conflicto['programado'] ?? ''),
            'termina' => (string) ($conflicto['termina'] ?? ''),
            'tiempo' => (int) ($conflicto['tiempo'] ?? 60),
            'paciente' => (string) ($conflicto['paciente'] ?? ''),
            'psicologo' => (string) ($conflicto['psicologo'] ?? ''),
            'psicologo_id' => (int) ($conflicto['psicologo_id'] ?? 0),
            'tipo' => (string) ($conflicto['tipo'] ?? ''),
            'source_type' => (string) ($conflicto['source_type'] ?? 'cita'),
        ],
    ];
}
