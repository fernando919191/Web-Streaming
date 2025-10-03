<?php
// vendor/wallet.php (versión PDO, alineada con tu stack)
// Requiere: ../config/auth.php (checkAuth, checkVendor) y ../config/database.php (class Database -> getConnection(): PDO)

session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
checkAuth();
checkVendor();

// ===================== CONEXIÓN (PDO) =====================
$database = new Database();
/** @var PDO $db */
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  header('Location: ../login.php');
  exit;
}

// ===================== CSRF =====================
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
function csrf_verify(string $token): bool {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===================== AUTO-MIGRACIÓN LIGHT =====================
// Crea tabla transactions si no existe (ajusta nombres si tu esquema ya difiere)
$db->exec("CREATE TABLE IF NOT EXISTS transactions (
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

// ===================== LÓGICA =====================
function getApprovedBalance(PDO $db, int $user_id): float {
  $sql = "SELECT 
            COALESCE(SUM(CASE WHEN type='credit' AND status='approved' THEN amount ELSE 0 END),0)
          - COALESCE(SUM(CASE WHEN type='debit' AND status='approved' THEN amount ELSE 0 END),0) AS balance
          FROM transactions WHERE user_id = ?";
  $st = $db->prepare($sql);
  $st->execute([$user_id]);
  $row = $st->fetch();
  return (float)($row['balance'] ?? 0);
}

function listTransactions(PDO $db, int $user_id, int $limit=20, int $offset=0): array {
  $st = $db->prepare("SELECT id, type, amount, status, reference, notes, created_at, updated_at
                      FROM transactions WHERE user_id=?
                      ORDER BY created_at DESC, id DESC
                      LIMIT ? OFFSET ?");
  $st->bindValue(1, $user_id, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->bindValue(3, $offset, PDO::PARAM_INT);
  $st->execute();
  return $st->fetchAll();
}

function createTopupRequest(PDO $db, int $user_id, float $amount, ?string $reference): bool {
  $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
  $st = $db->prepare("INSERT INTO transactions (user_id, type, amount, status, reference, created_at, updated_at)
                      VALUES (?, 'topup_request', ?, 'pending', ?, ?, ?)");
  return $st->execute([$user_id, $amount, $reference, $now, $now]);
}

function createWithdrawRequest(PDO $db, int $user_id, float $amount, ?string $notes): bool {
  $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
  $st = $db->prepare("INSERT INTO transactions (user_id, type, amount, status, notes, created_at, updated_at)
                      VALUES (?, 'withdraw_request', ?, 'pending', ?, ?, ?)");
  return $st->execute([$user_id, $amount, $notes, $now, $now]);
}

// ===================== POST HANDLERS =====================
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if (!csrf_verify($_POST['csrf'] ?? '')) {
    $errors[] = 'Token CSRF inválido. Refresca la página e inténtalo de nuevo.';
  } else if ($action === 'topup') {
    $amount = (float)($_POST['amount'] ?? 0);
    $reference = trim((string)($_POST['reference'] ?? '')) ?: null;
    if ($amount <= 0) {
      $errors[] = 'Ingresa un monto válido (> 0).';
    } else if (!createTopupRequest($db, $user_id, $amount, $reference)) {
      $errors[] = 'No se pudo registrar la solicitud de recarga.';
    } else {
      $success = 'Solicitud de recarga enviada (pendiente de aprobación).';
    }
  } else if ($action === 'withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
    $balance = getApprovedBalance($db, $user_id);
    if ($amount <= 0) {
      $errors[] = 'Ingresa un monto válido (> 0).';
    } else if ($amount > $balance) {
      $errors[] = 'El monto excede tu saldo disponible (' . number_format($balance,2) . ').';
    } else if (!createWithdrawRequest($db, $user_id, $amount, $notes)) {
      $errors[] = 'No se pudo registrar la solicitud de retiro.';
    } else {
      $success = 'Solicitud de retiro enviada (pendiente de aprobación).';
    }
  }
}

// ===================== DATOS PARA VISTA =====================
$balance = getApprovedBalance($db, $user_id);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1) * $limit;
$items = listTransactions($db, $user_id, $limit, $offset);
$username = $_SESSION['username'] ?? 'Vendedor';

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi Wallet - Vendedor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-wallet me-2"></i>Mi Wallet</h1>
    <div class="ms-auto alert alert-info py-1 px-2 mb-0">Bienvenido, <?php echo htmlspecialchars($username); ?></div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Saldo disponible</div>
          <div class="display-6 fw-semibold"><?php echo number_format($balance, 2); ?> <span class="h5">coins</span></div>
          <p class="small text-muted mb-0">Se calcula con créditos aprobados menos débitos aprobados.</p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header">Solicitar recarga</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="topup">
            <div class="mb-2">
              <label class="form-label">Monto (coins)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Referencia (opcional)</label>
              <input type="text" maxlength="190" class="form-control" name="reference" placeholder="Folio o nota de pago">
            </div>
            <button class="btn btn-primary w-100">Enviar solicitud</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header">Solicitar retiro</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="withdraw">
            <div class="mb-2">
              <label class="form-label">Monto (coins)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Notas (opcional)</label>
              <input type="text" maxlength="190" class="form-control" name="notes" placeholder="CLABE o wallet USDT, etc.">
            </div>
            <button class="btn btn-warning w-100">Enviar solicitud</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mt-4">
    <div class="card-header">Historial</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
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
            <tr><td colspan="6" class="text-center text-muted">Sin movimientos</td></tr>
          <?php else: foreach ($items as $row): ?>
            <tr>
              <td><?php echo (int)$row['id']; ?></td>
              <td><?php 
                $labels = [
                  'credit' => 'Crédito',
                  'debit' => 'Débito',
                  'topup_request' => 'Solicitud de recarga',
                  'withdraw_request' => 'Solicitud de retiro'
                ];
                echo htmlspecialchars($labels[$row['type']] ?? $row['type']);
              ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)$row['amount'], 2); ?></td>
              <td>
                <?php
                  $status = (string)$row['status'];
                  $badge = 'secondary';
                  if ($status==='approved') $badge='success';
                  elseif ($status==='pending') $badge='warning';
                  elseif ($status==='rejected') $badge='danger';
                ?>
                <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
              </td>
              <td class="text-break"><?php echo htmlspecialchars(trim($row['reference'] ?? '') ?: trim($row['notes'] ?? '') ?: '—'); ?></td>
              <td class="text-muted small"><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 justify-content-end">
        <?php if ($page>1): ?>
          <a class="btn btn-sm btn-outline-secondary" href="?page=<?php echo $page-1; ?>">« Anterior</a>
        <?php endif; ?>
        <?php if (count($items)===$limit): ?>
          <a class="btn btn-sm btn-outline-secondary" href="?page=<?php echo $page+1; ?>">Siguiente »</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <p class="mt-3 text-muted small">Las solicitudes quedan en <em>pending</em> hasta aprobación del administrador. Al aprobar, el admin debe asentar un <strong>credit</strong> (recarga) o un <strong>debit</strong> (retiro/compra), lo que impacta el saldo.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
