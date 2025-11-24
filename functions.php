<?php
// functions.php - LOGS + VALIDACIÓN
function log_action($pdo, $user_email, $accion, $detalle = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_line = date('Y-m-d H:i:s') . " | $user_email | $accion | $ip | $detalle\n";
    
    // Guardar en archivo
    file_put_contents('logs/acciones.log', $log_line, FILE_APPEND | LOCK_EX);
    
    // También guardar en BD (opcional futuro)
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (usuario, accion, detalle, ip, fecha) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_email, $accion, $detalle, $ip]);
    } catch (Exception $e) {
        // Si no existe la tabla logs, no rompe nada
    }
}

function validar_archivo($file, $allowed_types = ['pdf', 'jpg', 'jpeg', 'png']) {
    if ($file['error'] !== UPLOAD_ERR_OK) return "Error en subida.";
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png'
    ];
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($mime, $allowed_mimes) || !in_array($ext, array_keys($allowed_mimes))) {
        return "Solo se permiten PDF, JPG o PNG.";
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10 MB
        return "Archivo demasiado grande (máx 10 MB).";
    }
    
    return true; // válido
}
?>