<?php
/**
 * Funciones auxiliares para administrar el saldo de los pacientes.
 */

if (!function_exists('obtenerSaldoPaciente')) {
    /**
     * Obtiene el saldo actual registrado para un paciente.
     */
    function obtenerSaldoPaciente(mysqli $conn, int $pacienteId): float
    {
        $saldo = 0.0;
        $stmt = $conn->prepare('SELECT saldo_paquete FROM nino WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $pacienteId);
            if ($stmt->execute()) {
                $stmt->bind_result($saldoObtenido);
                if ($stmt->fetch()) {
                    $saldo = (float) $saldoObtenido;
                }
            }
            $stmt->close();
        }

        return $saldo;
    }
}

if (!function_exists('ajustarSaldoPaciente')) {
    /**
     * Ajusta el saldo del paciente sumando o restando el valor indicado.
     */
    function ajustarSaldoPaciente(mysqli $conn, int $pacienteId, float $ajuste): bool
    {
        $stmt = $conn->prepare('UPDATE nino SET saldo_paquete = saldo_paquete + ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('di', $ajuste, $pacienteId);
        $resultado = $stmt->execute();
        $stmt->close();

        return $resultado;
    }
}
