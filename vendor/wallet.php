<?php
// vendor/wallet.php
// Panel de billetera para VENDEDOR: saldo, historial y solicitudes de recarga/retiro

session_start();

// ===================== CONFIG / DEPENDENCIAS =====================
require_once '../config/auth.php';
require_once '../config/database.php';

// Asegúrate de que en database.php se exponga $mysqli o $conn como instancia de mysqli
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
}
if (!$db) {
    http_response_code(500);
    echo 'Error: No se pudo conectar a la base de datos. Verifica config/database.php';
    exit;
}

// Seguridad básica: solo VENDEDOR
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? $_SESSION['tipo'] ?? null;
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

// ===================== ESQUEMA =====================
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

// ===================== LÓGICA =====================
function getApprovedBalance(mysqli $db, int $userId) : float {
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
                    $success = 'Solicitud de recarga enviada.';
                } else {
                    $errors[] = 'No se pudo registrar la solicitud.';
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
                $errors[] = 'El monto excede tu saldo disponible.';
            } else {
                if (createWithdrawRequest($db, (int)$userId, $amount, $notes ?: null)) {
                    $success = 'Solicitud de retiro enviada.';
                } else {
                    $errors[] = 'No se pudo registrar la solicitud de retiro.';
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
</head>
<body class="bg-dark text-light">
<div class="container py-4">
  <h1>Billetera del Vendedor</h1>
  <p>Saldo disponible: <strong><?php echo number_format($balance, 2); ?> coins</strong></p>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', array_map('h', $errors)); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card p-3 bg-secondary">
        <h2 class="h5">Solicitar recarga</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="hidden" name="action" value="topup">
          <div class="mb-2">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Referencia</label>
            <input type="text" class="form-control" name="reference">
          </div>
          <button class="btn btn-primary">Enviar solicitud</button>
        </form>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 bg-secondary">
        <h2 class="h5">Solicitar retiro</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="hidden" name="action" value="withdraw">
          <div class="mb-2">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Notas</label>
            <input type="text" class="form-control" name="notes">
          </div>
          <button class="btn btn-warning">Enviar solicitud</button>
        </form>
      </div>
    </div>
  </div>

  <h2 class="mt-4">Historial</h2>
  <table class="table table-dark table-striped">
    <thead>
      <tr>
        <th>ID</th><th>Tipo</th><th>Monto</th><th>Estatus</th><th>Ref/Notas</th><th>Fecha</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$items): ?>
      <tr><td colspan="6">Sin movimientos</td></tr>
    <?php else: foreach ($items as $row): ?>
      <tr>
        <td><?php echo (int)$row['id']; ?></td>
        <td><?php echo h($row['type']); ?></td>
        <td><?php echo number_format((float)$row['amount'],2); ?></td>
        <td><?php echo h($row['status']); ?></td>
        <td><?php echo h($row['reference'] ?: $row['notes']); ?></td>
        <td><?php echo h($row['created_at']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>