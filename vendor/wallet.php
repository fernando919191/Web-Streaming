<?php
// vendor/wallet.php
// Panel de billetera para VENDEDOR: saldo, historial y solicitudes de recarga/retiro
// Requisitos: PHP 8.0+, extensión mysqli, Bootstrap 5 (opcional para estilos)
// Depende de: config/db.php que debe exponer $mysqli (objeto mysqli) o $conn

session_start();

// ===================== CONFIG / DEPENDENCIAS =====================
// Conexión a base de datos
$DB_CONNECTED = false;
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
    // Compatibilidad con $mysqli o $conn
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $db = $mysqli;
        $DB_CONNECTED = true;
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $db = $conn;
        $DB_CONNECTED = true;
    }
}
if (!$DB_CONNECTED) {
    http_response_code(500);
    echo 'Error: No se pudo conectar a la base de datos. Verifica config/db.php';
    exit;
}

// Seguridad básica: solo VENDEDOR
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? $_SESSION['tipo'] ?? null; // compat con distintos nombres de rol
if (!$userId || !in_array(strtolower((string)$userRole), ['vendedor','seller','ventas'])) {
    header('Location: ../login.php');
    exit;
}

// ===================== CSRF =====================
function csrf_token() : string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_verify($token) : bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// ===================== HELPERS =====================
function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function now_utc() { return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }

// ===================== ESQUEMA (auto-migración ligera) =====================
// Crea tabla transactions si no existe (si ya la tienes, puedes quitar este bloque)
$db->query("CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('credit','debit','topup_request','withdraw_request') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reference VARCHAR(190) NULL,
  notes VARCHAR(255) NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_user (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ===================== LÓGICA DE NEGOCIO =====================
function getApprovedBalance(mysqli $db, int $userId) : float {
    // Saldo = créditos aprobados - débitos aprobados
    $sql = "SELECT 
               COALESCE(SUM(CASE WHEN type IN ('credit') AND status='approved' THEN amount ELSE 0 END),0)
             - COALESCE(SUM(CASE WHEN type IN ('debit') AND status='approved' THEN amount ELSE 0 END),0) AS balance
            FROM transactions WHERE user_id=?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($res['balance'] ?? 0.0);
}

function listTransactions(mysqli $db, int $userId, int $limit = 20, int $offset = 0) : array {
    $sql = "SELECT id, type, amount, status, reference, notes, created_at, updated_at
            FROM transactions WHERE user_id=?
            ORDER BY created_at DESC, id DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iii', $userId, $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function createTopupRequest(mysqli $db, int $userId, float $amount, ?string $reference) : bool {
    $now = now_utc();
    $sql = "INSERT INTO transactions (user_id, type, amount, status, reference, created_at, updated_at)
            VALUES (?, 'topup_request', ?, 'pending', ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('idsss', $userId, $amount, $reference, $now, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createWithdrawRequest(mysqli $db, int $userId, float $amount, ?string $notes) : bool {
    // El retiro se modela como una solicitud; el admin luego registra un 'debit' aprobado si procede
    $now = now_utc();
    $sql = "INSERT INTO transactions (user_id, type, amount, status, notes, created_at, updated_at)
            VALUES (?, 'withdraw_request', ?, 'pending', ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('idsss', $userId, $amount, $notes, $now, $now);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ===================== POST HANDLERS =====================
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors[] = 'Token CSRF inválido. Actualiza la página e intenta de nuevo.';
    } else {
        if ($action === 'topup') {
            $amount = (float)($_POST['amount'] ?? 0);
            $reference = trim((string)($_POST['reference'] ?? ''));
            if ($amount <= 0) {
                $errors[] = 'Ingresa un monto válido (> 0).';
            } else {
                if (createTopupRequest($db, (int)$userId, $amount, $reference ?: null)) {
                    $success = 'Solicitud de recarga enviada. Quedará en "pending" hasta aprobación del administrador.';
                } else {
                    $errors[] = 'No se pudo registrar la solicitud. Intenta más tarde.';
                }
            }
        }
        if ($action === 'withdraw') {
            $amount = (float)($_POST['amount'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));
            $balance = getApprovedBalance($db, (int)$userId);
            if ($amount <= 0) {
                $errors[] = 'Ingresa un monto válido (> 0).';
            } elseif ($amount > $balance) {
                $errors[] = 'El monto excede tu saldo disponible (' . number_format($balance, 2) . ').';
            } else {
                if (createWithdrawRequest($db, (int)$userId, $amount, $notes ?: null)) {
                    $success = 'Solicitud de retiro enviada. Quedará en "pending" hasta aprobación del administrador.';
                } else {
                    $errors[] = 'No se pudo registrar la solicitud de retiro. Intenta más tarde.';
                }
            }
        }
    }
}

// ===================== DATOS PARA VISTA =====================
$balance = getApprovedBalance($db, (int)$userId);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page - 1) * $limit;
$items = listTransactions($db, (int)$userId, $limit, $offset);

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billetera del Vendedor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #111827; border: 1px solid #1f2937; }
    .form-control, .form-select { background:#0b1220; color:#e2e8f0; border-color:#1f2937; }
    .form-control:focus { background:#0b1220; color:#fff; border-color:#334155; box-shadow:none; }
    .table { --bs-table-bg: transparent; }
    .badge-status { text-transform: uppercase; letter-spacing:.5px; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex align-items-center mb-4">
    <h1 class="h3 m-0">Billetera del Vendedor</h1>
    <span class="ms-auto small text-secondary">Usuario #<?php echo (int)$userId; ?></span>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', array_map('h', $errors)); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-secondary">Saldo disponible</div>
            <div class="display-6 fw-semibold"><?php echo number_format($balance, 2); ?> <span class="h4">coins</span></div>
          </div>
          <div class="text-end">
            <span class="badge bg-info-subtle text-info border border-info">APROBADO</span>
          </div>
        </div>
        <hr>
        <p class="small text-secondary m-0">El saldo se calcula con <em>credit</em> aprobados menos <em>debit</em> aprobados. Las solicitudes aparecen como <em>pending</em>.</p>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card p-3">
        <h2 class="h5">Solicitar recarga</h2>
        <form method="post" class="mt-2">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="hidden" name="action" value="topup">
          <div class="mb-2">
            <label class="form-label">Monto (coins)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Referencia (opcional)</label>
            <input type="text" maxlength="190" class="form-control" name="reference" placeholder="Folio de pago, nota, etc.">
          </div>
          <button class="btn btn-primary w-100">Enviar solicitud</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card p-3">
        <h2 class="h5">Solicitar retiro</h2>
        <form method="post" class="mt-2">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="hidden" name="action" value="withdraw">
          <div class="mb-2">
            <label class="form-label">Monto (coins)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Notas (opcional)</label>
            <input type="text" maxlength="190" class="form-control" name="notes" placeholder="CLABE, wallet USDT, o nota para el admin">
          </div>
          <button class="btn btn-outline-warning w-100">Enviar solicitud</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card mt-4 p-3">
    <div class="d-flex align-items-center mb-2">
      <h2 class="h5 m-0">Historial</h2>
      <span class="ms-auto small text-secondary">Mostrando <?php echo (int)$limit; ?> por página</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle text-white">
        <thead>
          <tr class="text-secondary">
            <th>#</th>
            <th>Tipo</th>
            <th class="text-end">Monto</th>
            <th>Estatus</th>
            <th>Referencia / Notas</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="6" class="text-center text-secondary">Sin movimientos aún</td></tr>
        <?php else: foreach ($items as $row): ?>
          <tr>
            <td><?php echo (int)$row['id']; ?></td>
            <td>
              <?php 
                $type = (string)$row['type'];
                $labels = [
                  'credit' => 'Crédito',
                  'debit' => 'Débito',
                  'topup_request' => 'Solicitud de recarga',
                  'withdraw_request' => 'Solicitud de retiro'
                ];
                echo h($labels[$type] ?? $type);
              ?>
            </td>
            <td class="text-end fw-semibold"><?php echo number_format((float)$row['amount'], 2); ?></td>
            <td>
              <?php
                $status = (string)$row['status'];
                $class = 'bg-secondary';
                if ($status==='approved') $class='bg-success';
                elseif ($status==='pending') $class='bg-warning text-dark';
                elseif ($status==='rejected') $class='bg-danger';
              ?>
              <span class="badge badge-status <?php echo $class; ?>"><?php echo h($status); ?></span>
            </td>
            <td class="text-break">
              <?php
                $ref = trim((string)($row['reference'] ?? ''));
                $notes = trim((string)($row['notes'] ?? ''));
                echo h($ref ?: $notes ?: '—');
              ?>
            </td>
            <td class="text-secondary small"><?php echo h($row['created_at']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex gap-2 justify-content-end">
      <?php if ($page>1): ?>
        <a class="btn btn-sm btn-outline-light" href="?page=<?php echo $page-1; ?>">« Anterior</a>
      <?php endif; ?>
      <?php if (count($items)===$limit): ?>
        <a class="btn btn-sm btn-outline-light" href="?page=<?php echo $page+1; ?>">Siguiente »</a>
      <?php endif; ?>
    </div>
  </div>

  <p class="mt-4 small text-secondary">
    <strong>Nota:</strong> Las solicitudes (<em>topup/withdraw</em>) requieren aprobación en el panel de administración. Una vez aprobadas, el admin debe registrar
    el movimiento aprobado: <em>credit</em> (para recarga) o <em>debit</em> (para retiro/compra), lo cual impactará tu saldo.
  </p>
</div>
</body>
</html>
