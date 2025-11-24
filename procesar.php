<?php
ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// === CONEXIÓN BD ===
$pdo = new PDO("mysql:host=localhost;dbname=coleg115_solicitudes;charset=utf8mb4", 'coleg115_solicitudes', 'm;uJW)n#=r[@', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

    $required = ['nombre_completo','email','departamento','tipo_solicitud','area_ccosto','fecha_limite','proposito','monto_total'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) throw new Exception("Falta campo obligatorio.");
    }

    $email = trim($_POST['email']);
    if (!str_ends_with(strtolower($email), '@colegiomariano.cl'))
        throw new Exception("Correo debe ser del dominio @colegiomariano.cl");

    $articulos = json_decode($_POST['articulos'] ?? '', true);
    if (!is_array($articulos) || empty($articulos)) throw new Exception('Artículos inválidos.');

    // === VALIDACIÓN CAMPOS REEMBOLSO (solo si es reembolso) ===
    if ($_POST['tipo_solicitud'] === 'Reembolso') {
        $required_reembolso = ['nombre_bancario', 'rut', 'banco', 'tipo_cuenta', 'cuenta', 'email_bancario'];
        foreach ($required_reembolso as $f) {
            if (empty(trim($_POST[$f] ?? ''))) {
                throw new Exception("Campo obligatorio para reembolso: " . ucwords(str_replace('_', ' ', $f)));
            }
        }
        if (!filter_var($_POST['email_bancario'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email de notificación bancaria inválido.");
        }
    }

    // === OBTENER EMAIL DEL JEFE ===
    $stmt_jefe = $pdo->prepare("SELECT email FROM admins WHERE area = ? AND es_superadmin = 0 LIMIT 1");
    $stmt_jefe->execute([trim($_POST['departamento'])]);
    $jefe_row = $stmt_jefe->fetch();
    $jefe_email = $jefe_row['email'] ?? 'informatica@colegiomariano.cl';

    // === CÓDIGO ÚNICO ===
    do {
        $codigo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $stmt = $pdo->prepare("SELECT id FROM solicitudes WHERE codigo = ?");
        $stmt->execute([$codigo]);
    } while ($stmt->rowCount() > 0);

    // === GUARDAR SOLICITUD ===
    $stmt = $pdo->prepare("INSERT INTO solicitudes (codigo, nombre_solicitante, correo_solicitante, unidad, tipo_solicitud, area_ccosto, fecha_limite, detalle, monto_total, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
    $stmt->execute([
        $codigo,
        $_POST['nombre_completo'],
        $email,
        $_POST['departamento'],
        $_POST['tipo_solicitud'],
        $_POST['area_ccosto'],
        $_POST['fecha_limite'],
        $_POST['proposito'],
        $_POST['monto_total']
    ]);
    $solicitud_id = $pdo->lastInsertId();

    // === ARTÍCULOS ===
    $stmt_art = $pdo->prepare("INSERT INTO articulos (solicitud_id, cantidad, descripcion, valor_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
    foreach ($articulos as $a) {
        $stmt_art->execute([$solicitud_id, $a['cantidad'], $a['descripcion'], $a['valor_unitario'] ?? 0, $a['subtotal'] ?? 0]);
    }

    // === COTIZACIONES ===
    $cotizaciones_paths = [];
    $upload_dir = __DIR__ . '/cotizaciones';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (!empty($_FILES['cotizaciones']['name'][0])) {
        foreach ($_FILES['cotizaciones']['tmp_name'] as $i => $tmp) {
            if ($_FILES['cotizaciones']['error'][$i] !== 0) continue;
            $ext = pathinfo($_FILES['cotizaciones']['name'][$i], PATHINFO_EXTENSION);
            $safe = "cotizacion_{$codigo}_" . ($i + 1) . "." . strtolower($ext);
            $path = "$upload_dir/$safe";
            if (move_uploaded_file($tmp, $path)) $cotizaciones_paths[] = $path;
        }
    }

    // === GENERACIÓN DEL PDF ===
    require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('Sistema de Solicitudes');
    $pdf->SetAuthor($_POST['nombre_completo']);
    $pdf->SetTitle('Solicitud de ' . $_POST['tipo_solicitud']);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    // Título verde
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetFillColor(16, 185, 129);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 18, "Solicitud de " . $_POST['tipo_solicitud'], 0, 1, 'C', true);

    // Código
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 10, "Código: #$codigo", 0, 1, 'C');
    $pdf->Ln(8);

    // === DATOS GENERALES ===
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFillColor(240, 253, 244);

    $datos = [
        'Solicitante:' => $_POST['nombre_completo'],
        'Correo Electrónico:' => $email,
        'Área o Departamento:' => $_POST['departamento'],
        'Centro de Costo:' => $_POST['area_ccosto'],
        'Tipo de Solicitud:' => $_POST['tipo_solicitud'],
        'Fecha de Creación:' => date('d - m - Y'),
        'Fecha Límite:' => date('d-m-Y', strtotime($_POST['fecha_limite'])),
        'Justificación:' => $_POST['proposito'],
        'Monto Total:' => '$' . number_format($_POST['monto_total'], 0, ',', '.')
    ];

    // === CAMPOS DE REEMBOLSO (solo si aplica) ===
    if ($_POST['tipo_solicitud'] === 'Reembolso') {
        $datos_reembolso = [
            'Titular de la Cuenta:' => $_POST['nombre_bancario'] ?? 'No especificado',
            'RUT del Titular:' => $_POST['rut'] ?? 'No especificado',
            'Banco:' => $_POST['banco'] ?? 'No especificado',
            'Tipo de Cuenta:' => $_POST['tipo_cuenta'] ?? 'No especificado',
            'N° de Cuenta:' => $_POST['cuenta'] ?? 'No especificado',
            'Email Notificación:' => $_POST['email_bancario'] ?? 'No especificado'
        ];
        $datos = array_merge($datos, $datos_reembolso);
    }

    // === IMPRIMIR DATOS EN PDF ===
    foreach ($datos as $label => $value) {
        $pdf->SetFont('helvetica', 'B', 11);
        $isReembolso = in_array($label, [
            'Titular de la Cuenta:', 'RUT del Titular:', 'Banco:',
            'Tipo de Cuenta:', 'N° de Cuenta:', 'Email Notificación:'
        ]);
        $fillColor = $isReembolso ? [219, 234, 254] : [240, 253, 244];
        $pdf->SetFillColor(...$fillColor);
        $pdf->Cell(55, 8, $label, 0, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 8, $value, 0, 'L', true);
        $pdf->Ln(1);
    }

    $pdf->Ln(10);

    // === TABLA ARTÍCULOS ===
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(16, 185, 129);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(20, 10, 'Cant.', 1, 0, 'C', true);
    $pdf->Cell(90, 10, 'Descripción', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Valor Unit.', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Subtotal', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetFillColor(249, 250, 251);

    foreach ($articulos as $a) {
        $pdf->Cell(20, 10, $a['cantidad'], 1, 0, 'C', true);
        $pdf->Cell(90, 10, $a['descripcion'], 1, 0, 'L', true);
        $pdf->Cell(35, 10, '$' . number_format($a['valor_unitario'] ?? 0, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(35, 10, '$' . number_format($a['subtotal'] ?? 0, 0, ',', '.'), 1, 1, 'R', true);
    }

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 10, 'Generado automáticamente por el Sistema de Solicitudes de Compra/Reembolso • Colegio Mariano', 0, 1, 'C');

    $pdf_dir = __DIR__ . '/pdfs';
    if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0755, true);
    $pdf_path = "$pdf_dir/solicitud_$codigo.pdf";
    $pdf->Output($pdf_path, 'F');

    log_action($pdo, $email, 'CREÓ SOLICITUD', "Código: #$codigo");

    // === URL DE GESTIÓN (1 SOLO BOTÓN) ===
    $gestion_url = "https://solicitudes.colegiomariano.cl/gestion-cm/$codigo";

    // === PLANTILLAS HTML ===
    $html_solicitante = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{margin:0;padding:0;background:#f9fafb;font-family:Arial,sans-serif}
        .container{max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1)}
        .header{background:linear-gradient(135deg,#10b981,#059669);padding:35px 20px;text-align:center;color:white}
        .header h1{font-size:28px;margin:0}
        .content{padding:35px 30px;color:#1f2937;line-height:1.6}
        .code{font-size:36px;font-weight:bold;color:#10b981;text-align:center;margin:25px 0;letter-spacing:2px}
        .footer{background:#f0fdf4;padding:25px;text-align:center;color:#6b7280;font-size:13px}
    </style></head><body>
    <div class="container">
        <div class="header"><h1>Solicitud Recibida</h1></div>
        <div class="content">
            <p style="font-size:18px">¡Hola <strong>' . htmlspecialchars($_POST['nombre_completo']) . '</strong>!</p>
            <p>Tu solicitud ha sido <strong>registrada exitosamente</strong>.</p>
            <div class="code">#' . $codigo . '</div>
            <p><strong>Tipo:</strong> ' . $_POST['tipo_solicitud'] . '<br>
               <strong>Área:</strong> ' . htmlspecialchars($_POST['departamento']) . '<br>
               <strong>Monto:</strong> $' . number_format($_POST['monto_total'], 0, ',', '.') . '</p>
            <p>Tu solicitud está siendo evaluada por el jefe de área. Te notificaremos cuando haya una respuesta.</p>
            <p style="color:#6b7280;font-size:14px">PDF adjunto con todos los detalles.</p>
        </div>
        <div class="footer">Sistema de Solicitudes de Compra/Reembolso • Colegio Mariano<br>| No responder este correo |</div>
    </div></body></html>';

    $html_jefe = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{margin:0;padding:0;background:#f9fafb;font-family:Arial,sans-serif}
        .container{max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1)}
        .header{background:linear-gradient(135deg,#f59e0b,#d97706);padding:35px 20px;text-align:center;color:white}
        .header h1{font-size:28px;margin:0}
        .content{padding:35px 30px;color:#1f2937;line-height:1.6}
        .code{font-size:36px;font-weight:bold;color:#d97706;text-align:center;margin:25px 0;letter-spacing:2px}
        .btn{display:inline-block;padding:18px 40px;margin:12px;border-radius:12px;text-decoration:none;font-weight:bold;font-size:17px;color:white !important;background:#10b981 !important;}
        .footer{background:#f3f4fb;padding:25px;text-align:center;color:#6b7280;font-size:13px}
    </style></head><body>
    <div class="container">
        <div class="header"><h1>Nueva Solicitud Pendiente</h1></div>
        <div class="content">
            <p><strong>' . htmlspecialchars($_POST['nombre_completo']) . '</strong> ha creado una nueva solicitud.</p>
            <div class="code">#' . $codigo . '</div>
            <p><strong>Tipo:</strong> ' . $_POST['tipo_solicitud'] . '<br>
               <strong>Monto:</strong> $' . number_format($_POST['monto_total'], 0, ',', '.') . '</p>
            <p>PDF y cotizaciones adjuntas.</p>
            <div style="text-align:center;margin:35px 0;">
                <a href="' . $gestion_url . '" class="btn">
                    Gestionar Solicitud
                </a>
            </div>
            <p style="text-align:center;font-size:14px;color:#6b7280;">
                Ver detalles, aprobar o rechazar.
            </p>
        </div>
        <div class="footer">Sistema de Solicitudes de Compra/Reembolso • Colegio Mariano<br>Acción requerida</div>
    </div></body></html>';

    $html_compras = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{margin:0;padding:0;background:#f9fafb;font-family:Arial,sans-serif}
        .container{max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1)}
        .header{background:linear-gradient(135deg,#3b82f6,#2563eb);padding:35px 20px;text-align:center;color:white}
        .header h1{font-size:28px;margin:0}
        .content{padding:35px 30px;color:#1f2937;line-height:1.6}
        .code{font-size:36px;font-weight:bold;color:#3b82f6;text-align:center;margin:25px 0;letter-spacing:2px}
        table{width:100%;border-collapse:collapse;margin:20px 0}
        td{padding:12px;border:1px solid #e5e7eb}
        .label{background:#eff6ff;font-weight:bold}
        .footer{background:#eff6ff;padding:25px;text-align:center;color:#6b7280;font-size:13px}
    </style></head><body>
    <div class="container">
        <div class="header"><h1>Nueva Solicitud Creada</h1></div>
        <div class="content">
            <div class="code">#' . $codigo . '</div>
            <table>
                <tr><td class="label">Solicitante</td><td>' . htmlspecialchars($_POST['nombre_completo']) . '</td></tr>
                <tr><td class="label">Correo</td><td>' . $email . '</td></tr>
                <tr><td class="label">Área</td><td>' . htmlspecialchars($_POST['departamento']) . '</td></tr>
                <tr><td class="label">Tipo</td><td>' . $_POST['tipo_solicitud'] . '</td></tr>
                <tr><td class="label">Justificación</td><td>' . nl2br(htmlspecialchars($_POST['proposito'])) . '</td></tr>
                <tr><td class="label">Monto</td><td>$' . number_format($_POST['monto_total'], 0, ',', '.') . '</td></tr>
            </table>
            <p><strong>El PDF será enviado cuando la jefatura apruebe.</strong></p>
        </div>
        <div class="footer">Sistema de Solicitudes de Compra/Reembolso • Colegio Mariano<br>Notificación automática</div>
    </div></body></html>';

    // === SMTP ===
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'mail.colegiomariano.cl';
    $mail->SMTPAuth = true;
    $mail->Username = 'no-reply@colegiomariano.cl';
    $mail->Password = '=pXF$Wt#%U8d';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
    $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
    $mail->setFrom('no-reply@colegiomariano.cl', 'Sistema de Solicitudes - Colegio Mariano');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    // === ENVÍOS ===
    try {
        $mail->clearAllRecipients(); $mail->clearAttachments();
        $mail->addAddress($email);
        $mail->addAttachment($pdf_path);
        $mail->Subject = "Solicitud recibida: #$codigo";
        $mail->Body = $html_solicitante;
        $mail->send();
    } catch (Exception $e) { error_log("ERROR solicitante: " . $e->getMessage()); }

    try {
        $mail->clearAllRecipients(); $mail->clearAttachments();
        $mail->addAddress($jefe_email);
        $mail->addAttachment($pdf_path);
        foreach ($cotizaciones_paths as $p) if (file_exists($p)) $mail->addAttachment($p);
        $mail->Subject = "Nueva Solicitud: #$codigo";
        $mail->Body = $html_jefe;
        $mail->send();
    } catch (Exception $e) { error_log("ERROR jefe ($jefe_email): " . $e->getMessage()); }

    try {
        $mail->clearAllRecipients(); $mail->clearAttachments();
        $mail->addAddress('compras@colegiomariano.cl');
        $mail->Subject = "Nueva Solicitud #$codigo - " . $_POST['tipo_solicitud'];
        $mail->Body = $html_compras;
        $mail->send();
    } catch (Exception $e) { error_log("ERROR compras: " . $e->getMessage()); }

    ob_end_clean();
    echo json_encode(['status' => 'success', 'message' => "Solicitud enviada con éxito. Código: $codigo", 'codigo' => $codigo]);

} catch (Exception $e) {
    error_log("ERROR GENERAL: " . $e->getMessage());
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>