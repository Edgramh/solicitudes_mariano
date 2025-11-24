<?php
// admin.php
session_start();
require_once 'functions.php';

if (isset($_GET['logout'])) {
    if (isset($pdo) && isset($_SESSION['admin_user'])) log_action($pdo, $_SESSION['admin_user'], 'CERRÓ SESIÓN');
    session_destroy();
    header('Location: /inicio-cm');
    exit;
}
if (!isset($_SESSION['admin_user'])) {
    header('Location: /inicio-cm');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=coleg115_solicitudes;charset=utf8mb4", 'coleg115_solicitudes', 'm;uJW)n#=r[@', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
$stmt->execute([$_SESSION['admin_user']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /inicio-cm');
    exit;
}

$user_email = $user['email'];
$user_name = $user['nombre'] ?? ucwords(explode('@', $user_email)[0]);
$es_superadmin = (bool)$user['es_superadmin'];
$area_usuario = $user['area'];

// FILTROS
$search = trim($_GET['search'] ?? '');
$unidad = $_GET['unidad'] ?? '';
$estado = $_GET['estado'] ?? '';  // CORREGIDO: era "for(...)"
$tipo_solicitud = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// CONSULTA PRINCIPAL
$sql = "SELECT s.*, COUNT(a.id) AS num_articulos
        FROM solicitudes s
        LEFT JOIN articulos a ON s.id = a.solicitud_id
        WHERE 1=1";
$params = [];

if (!$es_superadmin && $area_usuario) {
    $sql .= " AND s.unidad = ?";
    $params[] = $area_usuario;
}
if ($search !== '') {
    $sql .= " AND (s.codigo LIKE ? OR s.nombre_solicitante LIKE ? OR s.correo_solicitante LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($es_superadmin && $unidad !== '') {
    $sql .= " AND s.unidad = ?";
    $params[] = $unidad;
}
if ($estado !== '') {
    $sql .= " AND s.estado = ?";
    $params[] = $estado;
}
if ($tipo_solicitud !== '') {
    $sql .= " AND s.tipo_solicitud = ?";
    $params[] = $tipo_solicitud;
}
if ($fecha_desde !== '') {
    $sql .= " AND DATE(s.fecha_creacion) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $sql .= " AND DATE(s.fecha_creacion) <= ?";
    $params[] = $fecha_hasta;
}

$sql .= " GROUP BY s.id ORDER BY s.fecha_creacion DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

// TOTAL PARA PAGINACIÓN (CORREGIDO)
$count_sql = "SELECT COUNT(DISTINCT s.id) FROM solicitudes s WHERE 1=1";
$count_params = [];

if (!$es_superadmin && $area_usuario) {
    $count_sql .= " AND s.unidad = ?";
    $count_params[] = $area_usuario;
}
if ($search !== '') {
    $count_sql .= " AND (s.codigo LIKE ? OR s.nombre_solicitante LIKE ? OR s.correo_solicitante LIKE ?)";
    $like = "%$search%";
    $count_params[] = $like; $count_params[] = $like; $count_params[] = $like;
}
if ($es_superadmin && $unidad !== '') {
    $count_sql .= " AND s.unidad = ?";
    $count_params[] = $unidad;
}
if ($estado !== '') {
    $count_sql .= " AND s.estado = ?";
    $count_params[] = $estado;
}
if ($tipo_solicitud !== '') {
    $count_sql .= " AND s.tipo_solicitud = ?";
    $count_params[] = $tipo_solicitud;
}
if ($fecha_desde !== '') {
    $count_sql .= " AND DATE(s.fecha_creacion) >= ?";
    $count_params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $count_sql .= " AND DATE(s.fecha_creacion) <= ?";
    $count_params[] = $fecha_hasta;
}

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($count_params);
$total = $stmt_count->fetchColumn();
$total_pages = ceil($total / $per_page);

// ESTADÍSTICAS
$stats_sql = "SELECT COUNT(*) as total,
    SUM(CASE WHEN estado='Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado='Aprobada' THEN 1 ELSE 0 END) as aprobadas,
    SUM(CASE WHEN estado='Rechazada' THEN 1 ELSE 0 END) as rechazadas
    FROM solicitudes WHERE 1=1" . (!$es_superadmin && $area_usuario ? " AND unidad=?" : "");
$stats_params = !$es_superadmin && $area_usuario ? [$area_usuario] : [];
$stmt_stats = $pdo->prepare($stats_sql);
$stmt_stats->execute($stats_params);
$row = $stmt_stats->fetch();
$stats = [
    'total' => (int)$row['total'],
    'pendientes' => (int)$row['pendientes'],
    'aprobadas' => (int)$row['aprobadas'],
    'rechazadas' => (int)$row['rechazadas']
];

$areas = $es_superadmin ? $pdo->query("SELECT DISTINCT unidad FROM solicitudes WHERE unidad IS NOT NULL AND unidad!='' ORDER BY unidad")->fetchAll(PDO::FETCH_COLUMN) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Administrativo - Solicitudes</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --p:#10b981;--pd:#059669;--bg:#ecfdf5;--b:#d1fae5;--t:#1f2937;--w:#ffffff;--s:rgba(16,185,129,0.1);
    }
    body{
      background:linear-gradient(135deg,#f0fdf4,#ecfdf5);
      font-family:'Inter',sans-serif;
      color:var(--t);
      min-height:100vh;
    }
    .card{
      border:1px solid var(--b);
      border-radius:24px;
      box-shadow:0 20px 25px -5px rgba(0,0,0,.1);
      padding:2rem;
      margin-bottom:2rem;
      position:relative;
      overflow:hidden;
      background:var(--w);
    }
    .card::before{
      content:'';
      position:absolute;
      top:0;left:0;right:0;
      height:6px;
      background:linear-gradient(135deg,var(--p),var(--pd));
      border-radius:24px 24px 0 0;
    }
    .stat{
      background:var(--w);
      border:1px solid var(--b);
      border-radius:16px;
      padding:1.8rem;
      text-align:center;
      transition:.2s;
    }
    .stat:hover{
      transform:translateY(-5px);
      box-shadow:0 15px 25px var(--s);
    }
    .stat-number{
      font-size:2.9rem;
      font-weight:700;
      color:var(--p);
    }
    .filters{
      background:var(--bg);
      border-radius:18px;
      padding:1.8rem;
      margin-bottom:2rem;
      border:1px solid var(--b);
    }
    .badge-pendiente{background:#fbbf24 !important;color:#78350f !important;font-weight:600;}
    .badge-aprobada{background:#10b981 !important;color:white !important;font-weight:600;}
    .badge-rechazada{background:#ef4444 !important;color:white !important;font-weight:600;}
    .table{font-size:0.89rem;}
    .table th,.table td{padding:0.65rem 0.5rem;vertical-align:middle;}
    .table .btn{font-size:0.8rem;padding:0.35rem 0.7rem;}
    .form-label{font-weight:600;font-size:0.9rem;color:#374151;margin-bottom:0.4rem;display:block;}

    /* TÍTULO RESPONSIVO */
    .titulo-admin{font-size:1.75rem;line-height:1.2;}
    @media (min-width:768px){.titulo-admin{font-size:2.75rem !important;}}

    /* ANCHO PERSONALIZADO */
    .col-lg-1_5{flex:0 0 12.5%;max-width:12.5%;}
    @media (max-width:1200px){.col-lg-1_5{flex:0 0 16.666%;max-width:16.666%;}}

    /* BOTÓN FILTRAR */
    .btn-filtrar-custom{
      min-height:44px;
      font-weight:600;
      border-radius:16px;
      transition:all .2s;
      box-shadow:0 4px 15px rgba(16,185,129,.25);
      width:130px !important;
    }
    .btn-filtrar-custom:hover{
      transform:translateY(-2px);
      box-shadow:0 8px 25px rgba(16,185,129,.35);
    }

    /* RESPONSIVE */
    @media (max-width:767.98px){
      .stat{padding:1.2rem 0.8rem;}
      .stat-number{font-size:2.2rem !important;}
      .filters{padding:1.4rem !important;}
      .card{padding:1.5rem;}
      .table{font-size:0.82rem;}
      .table th,.table td{padding:0.5rem 0.3rem;}
    }

    /* SOLO MÓVIL: Desde y Hasta → 50% */
    @media (max-width:576px){
      .filters .col-6.col-sm-4.col-md-2.col-lg-1_5{
        flex:0 0 50% !important;
        max-width:50% !important;
      }
    }
    @media (max-width: 576px) {
  .filters .col-6.col-sm-4.col-md-2.col-lg-1_5 {
    flex: 0 0 50% !important;
    max-width: 50% !important;
    width: 50% !important;
  }
  /* Opcional: mejora la presentación */
  .filters input[type="date"] {
    width: 100% !important;
    font-size: 14px;
  }
}
  </style>
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noodp, notranslate, noimageindex">
  <meta name="googlebot" content="noindex, nofollow, noarchive">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
</head>
<body>
<div class="container py-3 py-md-4">

  <!-- HEADER -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 mb-md-5 gap-3">
    <div class="d-flex align-items-center gap-3 flex-grow-1 justify-content-center justify-content-md-start">
      <img src="/assets/img/logo-colegio.png" alt="Colegio Mariano" class="img-fluid" style="height:70px;width:auto;">
      <div class="text-center text-md-start">
        <h1 class="fw-bold mb-0 titulo-admin" style="background:linear-gradient(135deg,#10b981,#059669);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
          Panel Administrativo
        </h1>
        <p class="mb-0 text-muted small">
          Gestión de Solicitudes
          <?= !$es_superadmin ? '<span class="badge bg-info fs-6 ms-1">' . htmlspecialchars($area_usuario) . '</span>' : '<span class="badge bg-success fs-6 ms-1">Todas las Áreas</span>' ?>
        </p>
      </div>
    </div>
    <div class="text-center text-md-end">
      <div class="d-inline-block">
        <strong class="d-block"><?= htmlspecialchars($user_name) ?></strong>
        <small class="text-muted d-block"><?= htmlspecialchars($user_email) ?></small>
        <div class="mt-2 d-flex gap-1 justify-content-center justify-content-md-end">
          <?php if ($es_superadmin): ?>
            <a href="/ver-logs-cm" class="btn btn-outline-warning btn-sm"><i class="bi bi-journal-text"></i> Logs</a>
          <?php endif; ?>
          <a href="?logout=1" class="btn btn-outline-danger btn-sm">Salir</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ESTADÍSTICAS -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat h-100 d-flex flex-column justify-content-center">Total<div class="stat-number"><?= number_format($stats['total']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat h-100 d-flex flex-column justify-content-center">Pendientes<div class="stat-number"><?= number_format($stats['pendientes']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat h-100 d-flex flex-column justify-content-center">Aprobadas<div class="stat-number"><?= number_format($stats['aprobadas']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat h-100 d-flex flex-column justify-content-center">Rechazadas<div class="stat-number"><?= number_format($stats['rechazadas']) ?></div></div></div>
  </div>

  <!-- FILTROS -->
  <div class="filters">
    <form method="get" class="row g-2 g-md-3 align-items-end">
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <label class="form-label">Buscar</label>
        <input type="text" class="form-control form-control-sm" name="search" placeholder="Código, nombre o email" value="<?= htmlspecialchars($search) ?>">
      </div>

      <?php if ($es_superadmin): ?>
        <div class="col-12 col-sm-6 col-md-3 col-lg-2">
          <label class="form-label">Área</label>
          <select class="form-select form-select-sm" name="unidad">
            <option value="">Todas</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= htmlspecialchars($a) ?>" <?= $unidad === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label">Estado</label>
        <select class="form-select form-select-sm" name="estado">
          <option value="">Todos</option>
          <option value="Pendiente" <?= $estado==='Pendiente'?'selected':'' ?>>Pendiente</option>
          <option value="Aprobada" <?= $estado==='Aprobada'?'selected':'' ?>>Aprobada</option>
          <option value="Rechazada" <?= $estado==='Rechazada'?'selected':'' ?>>Rechazada</option>
        </select>
      </div>

      <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label">Tipo</label>
        <select class="form-select form-select-sm" name="tipo">
          <option value="">Todos</option>
          <option value="Compra" <?= $tipo_solicitud==='Compra'?'selected':'' ?>>Compra</option>
          <option value="Reembolso" <?= $tipo_solicitud==='Reembolso'?'selected':'' ?>>Reembolso</option>
        </select>
      </div>

      <!-- CAMPOS DE FECHAS -->
      <div class="col-6 col-sm-4 col-md-2 col-lg-1_5">
        <label class="form-label small">Desde</label>
        <input type="date" class="form-control form-control-sm" name="desde" value="<?= $fecha_desde ?>">
      </div>
      <div class="col-6 col-sm-4 col-md-2 col-lg-1_5">
        <label class="form-label small">Hasta</label>
        <input type="date" class="form-control form-control-sm" name="hasta" value="<?= $fecha_hasta ?>">
      </div>

      <div class="col-12 col-md-4 col-lg-2 d-flex justify-content-center">
        <button type="submit" class="btn btn-primary btn-filtrar-custom d-flex align-items-center justify-content-center gap-2" style="min-height:44px;">
          <i class="bi bi-funnel-fill"></i>
          <span>Filtrar</span>
        </button>
      </div>
    </form>
  </div>

  <!-- TABLA DE SOLICITUDES -->
  <div class="card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h3 class="mb-0" style="font-size:16px;font-weight:600;">
        Solicitudes (Pág <?= $page ?> de <?= $total_pages ?>)
      </h3>
        <a href="/exportar-cm?search=<?= urlencode($search) ?>&estado=<?= urlencode($estado) ?>&tipo=<?= urlencode($tipo_solicitud) ?>&desde=<?= $fecha_desde ?>&hasta=<?= $fecha_hasta ?>&unidad=<?= urlencode($unidad) ?>" 
           class="btn btn-outline-secondary btn-sm" 
           target="_blank">
           Exportar CSV
        </a>
    </div>

    <?php if (empty($solicitudes)): ?>
      <div class="text-center py-5"><h5 class="text-muted">No se encontraron solicitudes.</h5></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Código</th><th>Solicitante</th><th>Área / CC</th><th>Tipo</th><th>Creación</th><th>Límite</th><th>Monto</th><th>Art.</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($solicitudes as $sol): ?>
              <tr>
                <td><strong>#<?= htmlspecialchars($sol['codigo']) ?></strong></td>
                <td><?= htmlspecialchars($sol['nombre_solicitante']) ?><br><small class="text-muted"><?= htmlspecialchars($sol['correo_solicitante']) ?></small></td>
                <td><?= htmlspecialchars($sol['unidad']) ?><br><small class="text-muted"><?= htmlspecialchars($sol['area_ccosto']) ?></small></td>
                <td><span class="badge bg-<?= $sol['tipo_solicitud']==='Compra'?'primary':'info text-dark' ?>"><?= $sol['tipo_solicitud'] ?></span></td>
                <td><?= date('d/m/Y', strtotime($sol['fecha_creacion'])) ?></td>
                <td><?= date('d/m/Y', strtotime($sol['fecha_limite'])) ?></td>
                <td class="text-end fw-bold">$<?= number_format($sol['monto_total']) ?></td>
                <td class="text-center"><?= $sol['num_articulos'] ?></td>
                <td><span class="badge badge-<?= strtolower($sol['estado']) ?>"><?= $sol['estado'] ?></span></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="aprobar.php?codigo=<?= $sol['codigo'] ?>" class="btn btn-outline-primary">Ver</a>
                    <?php if (file_exists("pdfs/solicitud_{$sol['codigo']}.pdf")): ?>
                      <a href="pdfs/solicitud_<?= $sol['codigo'] ?>.pdf" target="_blank" class="btn btn-outline-success">PDF</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
                <!-- PAGINACIÓN -->
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Paginación de solicitudes" class="mt-4">
            <ul class="pagination justify-content-center pagination-sm">
              <!-- Anterior -->
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado) ?>&tipo=<?= urlencode($tipo_solicitud) ?>&desde=<?= $fecha_desde ?>&hasta=<?= $fecha_hasta ?>&unidad=<?= urlencode($unidad) ?>" tabindex="-1">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
              <!-- Páginas -->
              <?php
              $start = max(1, $page - 2);
              $end = min($total_pages, $page + 2);
              if ($start > 1) {
                  echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'&estado='.urlencode($estado).'&tipo='.urlencode($tipo_solicitud).'&desde='.$fecha_desde.'&hasta='.$fecha_hasta.'&unidad='.urlencode($unidad).'">1</a></li>';
                  if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
              }
              for ($i = $start; $i <= $end; $i++):
              ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado) ?>&tipo=<?= urlencode($tipo_solicitud) ?>&desde=<?= $fecha_desde ?>&hasta=<?= $fecha_hasta ?>&unidad=<?= urlencode($unidad) ?>">
                    <?= $i ?>
                  </a>
                </li>
              <?php endfor; ?>
              <?php
              if ($end < $total_pages) {
                  if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                  echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'&estado='.urlencode($estado).'&tipo='.urlencode($tipo_solicitud).'&desde='.$fecha_desde.'&hasta='.$fecha_hasta.'&unidad='.urlencode($unidad).'">'.$total_pages.'</a></li>';
              }
              ?>
              <!-- Siguiente -->
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado) ?>&tipo=<?= urlencode($tipo_solicitud) ?>&desde=<?= $fecha_desde ?>&hasta=<?= $fecha_hasta ?>&unidad=<?= urlencode($unidad) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <footer class="text-center mt-5 text-muted small">
    Sistema de Solicitud de Compra/Reembolso — Colegio Mariano de Schoenstatt<br>
    <small>Creado por Edgardo Mendoza</small>
  </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>