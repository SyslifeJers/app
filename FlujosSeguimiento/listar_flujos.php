<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

flujos_require_session();
$conn = conectar();
$conn->set_charset('utf8mb4');

$sql = "SELECT f.id_flujo, f.nombre, f.descripcion, f.activo, f.fecha_creacion, f.usuario_creacion,
               COUNT(t.id_tarea) AS total_tareas
        FROM exp_flujos f
        LEFT JOIN exp_flujo_tareas t ON t.id_flujo = f.id_flujo AND t.activo = 1
        GROUP BY f.id_flujo, f.nombre, f.descripcion, f.activo, f.fecha_creacion, f.usuario_creacion
        ORDER BY f.fecha_creacion DESC";
$result = $conn->query($sql);
$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['id_flujo'] = (int)$row['id_flujo'];
        $row['activo'] = (int)$row['activo'];
        $row['total_tareas'] = (int)$row['total_tareas'];
        $rows[] = $row;
    }
}
$conn->close();

flujos_json_response(200, ['success' => true, 'data' => $rows]);
