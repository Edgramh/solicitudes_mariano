<?php
// exportar.php - EXPORTAR A CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=solicitudes_' . date('Y-m-d') . '.csv');

$host = 'localhost'; $db = 'coleg115_solicitudes'; $user = 'coleg115_solicitudes'; $pass = 'm;uJW)n#=r[@';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM para UTF-8

// Headers
fputcsv($output, ['Código', 'Solicitante', 'Email', 'Área', 'Tipo', 'Monto', 'Estado', 'Fecha Creación']);

// Datos
$stmt = $pdo->query("SELECT codigo, nombre_solicitante, correo_solicitante, unidad, tipo_solicitud, monto_total, estado, fecha_creacion FROM solicitudes ORDER BY fecha_creacion DESC");
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['codigo'],
        $row['nombre_solicitante'],
        $row['correo_solicitante'],
        $row['unidad'],
        $row['tipo_solicitud'],
        '$' . number_format($row['monto_total']),
        $row['estado'],
        date('d/m/Y', strtotime($row['fecha_creacion']))
    ]);
}

fclose($output);
exit;
?>