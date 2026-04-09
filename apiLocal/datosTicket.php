<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json; charset=utf-8');

$cita_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cita_id <= 0) {
  http_response_code(400);
  echo json_encode(["error" => "ID de cita no valido."], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $conn = conectar();

  $sql = "SELECT
            ci.id,
            ci.IdNino,
            n.name AS paciente_nombre,
            us.id AS psicologo_id,
            us.name AS psicologo_nombre,
            ci.costo,
            ci.Programado,
            DATE_FORMAT(DATE(ci.Programado), '%d-%m-%Y') AS Fecha,
            TIME(ci.Programado) AS Hora,
            ci.Tipo,
            ci.FormaPago,
            es.id AS estatus_id,
            es.name AS estatus_nombre
          FROM Cita ci
          INNER JOIN nino n ON n.id = ci.IdNino
          INNER JOIN Usuarios us ON us.id = ci.IdUsuario
          INNER JOIN Estatus es ON es.id = ci.Estatus
          WHERE ci.id = ?";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $cita_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $cita = $result->fetch_assoc();

  if (!$cita) {
    http_response_code(404);
    echo json_encode(["error" => "Cita no encontrada."], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Pagos
  $sqlPagos = "SELECT
                 id,
                 cita_id,
                 metodo,
                 monto,
                 registrado_por,
                 creado_en
               FROM CitaPagos
               WHERE cita_id = ?
               ORDER BY creado_en ASC, id ASC";

  $stmt2 = $conn->prepare($sqlPagos);
  $stmt2->bind_param("i", $cita_id);
  $stmt2->execute();
  $result2 = $stmt2->get_result();

  $pagos = [];
  $pagadoTotal = 0.0;

  while ($p = $result2->fetch_assoc()) {
    $monto = (float)$p["monto"];
    $pagadoTotal += $monto;

    $pagos[] = [
      "id" => (int)$p["id"],
      "metodo" => $p["metodo"],
      "monto" => $monto,
      "registrado_por" => (int)$p["registrado_por"],
      "creado_en" => $p["creado_en"]
    ];
  }

  $costo = (float)$cita["costo"];
  $adeudo = max(0, $costo - $pagadoTotal);

  $json = [
    "ticket" => [
      "id" => (int)$cita["id"],
      "clinic" => [
        "name" => "CLINICA CERENE"
      ],
      "cita" => [
        "programado" => $cita["Programado"],
        "fecha" => $cita["Fecha"],
        "hora" => $cita["Hora"],
        "tipo" => $cita["Tipo"],
        "formaPago" => $cita["FormaPago"],
        "costo" => $costo,
        "estatus" => [
          "id" => (int)$cita["estatus_id"],
          "name" => $cita["estatus_nombre"]
        ]
      ],
      "paciente" => [
        "id" => (int)$cita["IdNino"],
        "name" => $cita["paciente_nombre"]
      ],
      "psicologo" => [
        "id" => (int)$cita["psicologo_id"],
        "name" => $cita["psicologo_nombre"]
      ],
      "pagos" => $pagos,
      "totals" => [
        "currency" => "MXN",
        "grandTotal" => $costo,
        "paidTotal" => $pagadoTotal,
        "dueTotal" => $adeudo
      ]
    ]
  ];

  echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
 catch (Exception $e) {
    echo "No se pudo imprimir el ticket: " . $e->getMessage();
}
