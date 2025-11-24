<?php
session_start();

// VALIDACIÓN SUPERADMIN 100% SEGURA (funciona aunque es_superadmin sea 1, "1" o true)
if (!isset($_SESSION['admin_user']) || empty($_SESSION['admin_user'])) {
    header('Location: /inicio-cm');
    exit;
}

if (!isset($_SESSION['es_superadmin']) || $_SESSION['es_superadmin'] != 1) {
    die('<h2 style="text-align:center;margin-top:10rem;color:#dc3545;">Acceso denegado. Solo superadministradores.</h2>');
}

// Si el archivo de logs no existe o está vacío, mostrar mensaje bonito
$logContent = '';
$logFile = 'logs/acciones.log';
if (file_exists($logFile) && filesize($logFile) > 0) {
    $logContent = htmlspecialchars(file_get_contents($logFile));
} else {
    $logContent = "— No hay acciones registradas aún —";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logs del Sistema - Colegio Mariano</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- BLOQUEO TOTAL DE INDEXACIÓN -->
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <meta name="googlebot" content="noindex, nofollow, noarchive">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <style>
    body { background: linear-gradient(135deg, #f0fdf4, #ecfdf5); min-height: 100vh; }
    .log-container { background: white; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); overflow: hidden; }
    .log-header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 1.5rem; }
    pre { background: #1a1a1a !important; color: #00ff9d !important; font-family: 'Consolas', monospace; }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="log-container">
      <div class="log-header text-center">
        <h1 class="mb-0"><i class="bi bi-journal-text me-3"></i>Logs del Sistema</h1>
        <small>Registro completo de acciones administrativas</small>
      </div>
      <div class="p-4">
        <pre class="rounded"><?= $logContent ?></pre>
      </div>
      <div class="p-4 text-center bg-light">
        <a href="/acceso-cm" class="btn btn-success btn-lg px-5">
          <i class="bi bi-arrow-left-circle me-2"></i>Volver al Panel
        </a>
      </div>
    </div>
  </div>
</body>
</html>