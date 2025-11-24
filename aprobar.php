<?php
// aprobar.php - VERSIÓN FINAL: SPINNER ANIMADO CENTRADO + FADE IN/OUT
session_start();
require_once 'vendor/autoload.php';
require_once 'functions.php';
use PHPMailer\PHPMailer\PHPMailer;

// === FUNCIÓN: ENVIAR NOTIFICACIÓN (SMTP OFICIAL) ===
function enviar_notificacion_aprobacion($pdo, $solicitud, $estado, $comentario, $aprobado_por, $cotizaciones, $codigo) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.colegiomariano.cl';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@colegiomariano.cl';
        $mail->Password = '=pXF$Wt#%U8d';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->setFrom('no-reply@colegiomariano.cl', 'Sistema de Solicitudes - Colegio Mariano');
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $es_aprobada = ($estado === 'Aprobada');
        $color = $es_aprobada ? '#10b981' : '#ef4444';
        $monto = '$' . number_format($solicitud['monto_total'], 0, ',', '.');

        // CORREO AL SOLICITANTE
        $mail->clearAllRecipients();
        $mail->addAddress($solicitud['correo_solicitante']);
        $mail->Subject = "Solicitud #$codigo: " . ucfirst($estado);
        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:Arial,sans-serif;background:#f9fafb;color:#1f2937}
            .container{max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1)}
            .header{background:$color;color:white;padding:30px;text-align:center}
            .content{padding:30px;line-height:1.6}
            .code{font-size:32px;font-weight:bold;color:$color;text-align:center;margin:20px 0}
            .footer{background:#f3f4f6;padding:20px;text-align:center;color:#6b7280;font-size:13px}
        </style></head><body>
        <div class='container'>
            <div class='header'><h1>Solicitud " . strtoupper($estado) . "</h1></div>
            <div class='content'>
                <p>¡Hola <strong>" . htmlspecialchars($solicitud['nombre_solicitante']) . "</strong>!</p>
                <div class='code'>#$codigo</div>
                <p>Tu solicitud ha sido <strong>" . strtolower($estado) . "</strong>.</p>
                <p><strong>Monto:</strong> $monto</p>
                " . ($comentario ? "<p><strong>Comentario:</strong> " . nl2br(htmlspecialchars($comentario)) . "</p>" : "") . "
            </div>
            <div class='footer'>Sistema de Solicitudes - Colegio Mariano</div>
        </div></body></html>";
        $mail->send();

        // CORREO A COMPRAS
        $mail->clearAllRecipients(); $mail->clearAttachments();
        $mail->addAddress('compras@colegiomariano.cl');
        $mail->Subject = ($es_aprobada ? 'APROBADA' : 'RECHAZADA') . ": Solicitud #$codigo";
        if ($es_aprobada) {
            $pdf_path = __DIR__ . "/pdfs/solicitud_$codigo.pdf";
            if (file_exists($pdf_path)) $mail->addAttachment($pdf_path, "solicitud_$codigo.pdf");
            foreach ($cotizaciones as $cot) {
                $ruta = __DIR__ . "/cotizaciones/$cot";
                if (file_exists($ruta)) $mail->addAttachment($ruta, $cot);
            }
        }
        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:Arial,sans-serif;background:#f4f4f4;padding:20px}
            .container{max-width:600px;margin:auto;background:white;border-radius:12px;overflow:hidden}
            .header{background:$color;color:white;padding:20px;text-align:center}
            .content{padding:25px;color:#333}
            .footer{text-align:center;padding:20px;color:#888;font-size:12px;background:#f0f0f0}
        </style></head><body>
        <div class='container'>
            <div class='header'><h1>Solicitud " . strtoupper($estado) . "</h1></div>
            <div class='content'>
                <p><strong>Código:</strong> #$codigo</p>
                <p><strong>Solicitante:</strong> " . htmlspecialchars($solicitud['nombre_solicitante']) . "</p>
                <p><strong>Área:</strong> " . htmlspecialchars($solicitud['unidad']) . "</p>
                <p><strong>Monto:</strong> $monto</p>
                <p><strong>Procesado por:</strong> " . htmlspecialchars($aprobado_por) . "</p>
                " . ($comentario ? "<p><strong>Comentario:</strong> " . nl2br(htmlspecialchars($comentario)) . "</p>" : "") . "
            </div>
            <div class='footer'>Sistema de Solicitudes - Colegio Mariano</div>
        </div></body></html>";
        $mail->send();
    } catch (Exception $e) {
        error_log("Error envío notificación ($estado): " . $e->getMessage());
    }
}

// === PROTEGER ACCESO ===
if (!isset($_SESSION['admin_user'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /inicio-cm');
    exit;
}

// === CONEXIÓN BD ===
$host = 'localhost';
$db = 'coleg115_solicitudes';
$user = 'coleg115_solicitudes';
$pass = 'm;uJW)n#=r[@';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$_SESSION['admin_user']]);
    $admin_actual = $stmt->fetch();
    if (!$admin_actual) { session_destroy(); header('Location: /inicio-cm'); exit; }
    $es_superadmin = (bool)$admin_actual['es_superadmin'];
    $area_admin = $admin_actual['area'];
    $nombre_admin = $admin_actual['nombre'] ?? explode('@', $_SESSION['admin_user'])[0];
} catch (Exception $e) {
    die('<div class="alert alert-danger text-center p-4">Error de conexión.</div>');
}

// === OBTENER CÓDIGO ===
$codigo = trim($_GET['codigo'] ?? '');
if (!$codigo || strlen($codigo) !== 8) {
    die('<div class="alert alert-danger text-center p-4">Código inválido.</div>');
}
$stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE codigo = ?");
$stmt->execute([$codigo]);
$solicitud = $stmt->fetch();
if (!$solicitud) {
    die('<div class="alert alert-danger text-center p-4">Solicitud no encontrada.</div>');
}
if (!$es_superadmin && $solicitud['unidad'] !== $area_admin) {
    die('<div class="alert alert-warning text-center p-4">No tienes permiso para esta solicitud.</div>');
}

// === ARTÍCULOS Y COTIZACIONES ===
$stmt_art = $pdo->prepare("SELECT * FROM articulos WHERE solicitud_id = ? ORDER BY id");
$stmt_art->execute([$solicitud['id']]);
$articulos = $stmt_art->fetchAll();
$cotizaciones = [];
$cot_dir = __DIR__ . "/cotizaciones";
if (is_dir($cot_dir)) {
    foreach (glob($cot_dir . "/cotizacion_{$codigo}_*.{pdf,jpg,jpeg,png}", GLOB_BRACE) as $file) {
        $cotizaciones[] = basename($file);
    }
}

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$success = $error = '';

// === PROCESAR DESDE EL PANEL ===
if ($_POST['accion'] ?? '' === 'actualizar') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error = "Token inválido.";
    } elseif ($solicitud['estado'] !== 'Pendiente') {
        $error = "Ya fue procesada.";
    } else {
        $estado = $_POST['estado'] ?? '';
        $comentario = trim($_POST['comentario'] ?? '');
        if (!in_array($estado, ['Aprobada', 'Rechazada'])) {
            $error = "Estado inválido.";
        } else {
            $aprobado_por = $nombre_admin . " (" . ($es_superadmin ? "Superadmin" : $area_admin) . ")";
            $comentario_final = $comentario ?: "Acción desde panel - " . date('d-m-Y H:i');
            $stmt_upd = $pdo->prepare("UPDATE solicitudes SET estado = ?, comentario_aprobacion = ?, aprobado_por = ?, fecha_aprobacion = NOW() WHERE id = ?");
            $stmt_upd->execute([$estado, $comentario_final, $aprobado_por, $solicitud['id']]);
            log_action($pdo, $_SESSION['admin_user'], $estado === 'Aprobada' ? 'APROBÓ' : 'RECHAZÓ', "Código: #$codigo");
            enviar_notificacion_aprobacion($pdo, $solicitud, $estado, $comentario_final, $aprobado_por, $cotizaciones, $codigo);
            header("Location: /gestion-cm/$codigo?success=1");
            exit;
        }
    }
}

if (isset($_GET['success'])) $success = "Decisión registrada y notificada correctamente.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Aprobar #<?= htmlspecialchars($codigo) ?></title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --p:#10b981; --pd:#059669; }
    body{background:linear-gradient(135deg,#f0fdf4,#ecfdf5);font-family:system-ui,sans-serif;min-height:100vh}
    .card{border-radius:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);border:1px solid #d1fae5;position:relative;overflow:hidden}
    .card::before{content:'';position:absolute;top:0;left:0;right:0;height:6px;background:linear-gradient(90deg,var(--p),var(--pd));border-radius:24px 24px 0 0}
    .badge-pendiente{background:#fbbf24;color:#78350f}
    .badge-aprobada{background:var(--p);color:white}
    .badge-rechazada{background:#ef4444;color:white}

    /* OVERLAY ANIMADO CENTRADO */
    #loadingOverlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(255, 255, 255, 0.97);
      display: none; align-items: center; justify-content: center;
      z-index: 9999; opacity: 0; transition: opacity 0.4s ease;
    }
    #loadingOverlay.show { display: flex; opacity: 1; }

    .spinner {
      width: 70px; height: 70px;
      border: 7px solid #f3f3f3;
      border-top: 7px solid var(--p);
      border-radius: 50%;
      animation: spin 1.2s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    #loadingMessage {
      min-height: 40px;
      margin-top: 1rem;
      position: relative;
      width: 100%;
      text-align: center;
    }

    .msg {
      display: block;
      font-size: 1.4rem;
      font-weight: 600;
      color: var(--p);
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.4s ease, transform 0.4s ease;
      position: absolute;
      left: 50%;
      transform: translateX(-50%) translateY(-10px);
      width: max-content;
      white-space: nowrap;
    }
    .msg.active {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
  </style>
</head>
<body>

  <!-- OVERLAY ANIMADO CENTRADO -->
  <div id="loadingOverlay">
    <div class="d-flex flex-column align-items-center">
      <div class="spinner"></div>
      <div id="loadingMessage">
        <span class="msg active" data-msg="Procesando decisión...">Procesando decisión...</span>
        <span class="msg" data-msg="Enviando notificaciones...">Enviando notificaciones...</span>
        <span class="msg" data-msg="¡Solicitud actualizada!">¡Solicitud actualizada!</span>
      </div>
    </div>
  </div>

  <div class="container py-4">
    <div class="text-end mb-3">
      <a href="/acceso-cm" class="btn btn-outline-success">
        <i class="bi bi-arrow-left-circle"></i> Volver al Panel
      </a>
    </div>

    <div class="card p-4">
      <div class="text-center mb-4">
        <h1 class="fw-bold text-success">Solicitud #<?= htmlspecialchars($codigo) ?></h1>
        <span class="badge badge-<?= strtolower($solicitud['estado']) ?> fs-5 px-4 py-2"><?= $solicitud['estado'] ?></span>
      </div>

      <!-- MENSAJE DE ÉXITO -->
      <?php if ($success): ?>
        <div class="alert alert-success text-center">
          <i class="bi bi-check-circle-fill"></i> <?= $success ?>
        </div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- DETALLES DE LA SOLICITUD -->
      <div class="row g-3 mb-4">
        <div class="col-md-6"><strong>Solicitante:</strong> <?= htmlspecialchars($solicitud['nombre_solicitante']) ?></div>
        <div class="col-md-6"><strong>Correo:</strong> <?= htmlspecialchars($solicitud['correo_solicitante']) ?></div>
        <div class="col-md-6"><strong>Área:</strong> <?= htmlspecialchars($solicitud['unidad']) ?></div>
        <div class="col-md-6"><strong>Centro de Costo:</strong> <?= htmlspecialchars($solicitud['area_ccosto']) ?></div>
        <div class="col-md-6"><strong>Tipo:</strong> <?= $solicitud['tipo_solicitud'] ?></div>
        <div class="col-md-6"><strong>Fecha Límite:</strong> <?= date('d-m-Y', strtotime($solicitud['fecha_limite'])) ?></div>
        <div class="col-12"><strong>Justificación:</strong><div class="p-3 bg-light rounded mt-1"><?= nl2br(htmlspecialchars($solicitud['detalle'])) ?></div></div>
      </div>

      <h5 class="text-success">Artículos Solicitados</h5>
      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="table-success">
            <tr><th>Cant.</th><th>Descripción</th><th class="text-end">Unit.</th><th class="text-end">Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($articulos as $a): ?>
              <tr>
                <td class="text-center"><?= $a['cantidad'] ?></td>
                <td><?= htmlspecialchars($a['descripcion']) ?></td>
                <td class="text-end">$<?= number_format($a['valor_unitario'], 0, ',', '.') ?></td>
                <td class="text-end">$<?= number_format($a['subtotal'], 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-success fw-bold">
              <th colspan="3" class="text-end">Total:</th>
              <th class="text-end">$<?= number_format($solicitud['monto_total'], 0, ',', '.') ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ARCHIVOS ADJUNTOS -->
      <div class="text-center my-4">
        <?php if (file_exists("pdfs/solicitud_$codigo.pdf")): ?>
          <a href="/pdfs/solicitud_<?= $codigo ?>.pdf" target="_blank" class="btn btn-outline-success me-2">
            Ver PDF
          </a>
        <?php endif; ?>
        <?php foreach ($cotizaciones as $cot): ?>
          <a href="/cotizaciones/<?= $cot ?>" target="_blank" class="btn btn-outline-secondary me-1">
            <?= pathinfo($cot, PATHINFO_FILENAME) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- FORMULARIO DE DECISIÓN -->
      <?php if ($solicitud['estado'] === 'Pendiente'): ?>
        <hr class="my-5">
        <form method="post" id="decisionForm">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="accion" value="actualizar">
          <h5 class="text-center mb-4">Decisión del Jefe</h5>
          <div class="row g-4 justify-content-center">
            <div class="col-md-5">
              <button type="submit" name="estado" value="Aprobada" class="btn btn-success btn-lg w-100">
                Aprobar Solicitud
              </button>
            </div>
            <div class="col-md-5">
              <button type="submit" name="estado" value="Rechazada" class="btn btn-danger btn-lg w-100">
                Rechazar Solicitud
              </button>
            </div>
            <div class="col-12">
              <textarea class="form-control" name="comentario" rows="3" placeholder="Comentario opcional..."></textarea>
            </div>
          </div>
        </form>
      <?php else: ?>
        <div class="alert alert-info text-center mt-4">
          <strong>Procesada por:</strong> <?= htmlspecialchars($solicitud['aprobado_por']) ?>
          <?php if ($solicitud['comentario_aprobacion']): ?>
            <hr><p><strong>Comentario:</strong> <?= nl2br(htmlspecialchars($solicitud['comentario_aprobacion'])) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="/acceso-cm" class="btn btn-outline-success">
          Volver al Panel de Administración
        </a>
      </div>
    </div>
  </div>

  <!-- ANIMACIÓN DE MENSAJES CON FADE IN/OUT -->
  <script>
    document.getElementById('decisionForm')?.addEventListener('submit', function() {
      const overlay = document.getElementById('loadingOverlay');
      overlay.classList.add('show');

      const messages = [
        { text: 'Procesando decisión...', delay: 0 },
        { text: 'Enviando notificaciones...', delay: 1800 },
        { text: '¡Solicitud actualizada!', delay: 3600 }
      ];

      const spans = document.querySelectorAll('#loadingMessage .msg');
      let currentIndex = 0;

      // Reset
      spans.forEach(s => s.classList.remove('active'));

      const showNext = () => {
        if (currentIndex >= messages.length) return;

        const msg = messages[currentIndex];
        const span = spans[currentIndex];

        setTimeout(() => {
          // Salida del anterior
          if (currentIndex > 0) {
            spans[currentIndex - 1].classList.remove('active');
          }
          // Entrada del actual
          span.textContent = msg.text;
          span.classList.add('active');
          currentIndex++;
          showNext();
        }, msg.delay);
      };

      showNext();
    });
  </script>
</body>
</html>