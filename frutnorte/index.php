<?php
// ====================== DEBUG ATIVO ======================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ====================== VERIFICAÇÃO DE LOGIN ======================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "danger";
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "danger";
    header("Location: login.php");
    exit;
}

// ==================== CONEXÃO LOGIN ====================
require_once 'config/databaselogin.php';
try {
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();
} catch (Exception $e) {
    die("Erro na conexão com o banco login: " . $e->getMessage());
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do admin
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND cargo = 'Admin' AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        $_SESSION['msg'] = "Erro de autenticação. Acesso negado.";
        $_SESSION['msg_type'] = "danger";
        header("Location: login.php");
        exit;
    }

    $empresa_id = $admin_data['empresa_id'];
    $_SESSION['empresa_id'] = $empresa_id;
} catch (Exception $e) {
    die("Erro ao buscar empresa do admin: " . $e->getMessage());
}

// ==================== CONEXÃO SISTEMA ====================
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Erro na conexão com o banco frutnorte: " . $e->getMessage());
}

// ==================== CONSULTAS ====================
try {
    // Total de clientes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clientes WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $totalClientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Clientes mês atual
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM clientes 
        WHERE MONTH(data_cad) = MONTH(CURDATE())
        AND YEAR(data_cad) = YEAR(CURDATE())
        AND empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $clientesMesAtual = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Clientes mês anterior
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM clientes 
        WHERE MONTH(data_cad) = MONTH(CURDATE() - INTERVAL 1 MONTH)
        AND YEAR(data_cad) = YEAR(CURDATE() - INTERVAL 1 MONTH)
        AND empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $clientesMesAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Variação %
    if ($clientesMesAnterior > 0) {
        $variacao = (($clientesMesAtual - $clientesMesAnterior) / $clientesMesAnterior) * 100;
        $variacaoTexto = ($variacao >= 0 ? '+' : '') . number_format($variacao, 2, ',', '.') . '% este mês';
    } else {
        $variacaoTexto = ($clientesMesAtual > 0) ? '+∞% este mês' : '0% este mês';
    }

} catch (Exception $e) {
    die("Erro nas consultas: " . $e->getMessage());
}

// ==================== HTML ====================
?>
<?php include 'includes/menu.php'; ?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Calory Sistemas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 20px;
}
.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    text-align: center;
}
.stat-title { font-weight: 600; margin-top: 10px; }
.stat-value { font-size: 1.5rem; font-weight: bold; margin-top: 5px; }
.stat-change { font-size: 0.9rem; margin-top: 5px; color: #555; }
.stat-card.secondary { background: #f0f7ff; }
.stat-card.tertiary { background: #fff8f0; }
</style>
</head>
<body>
<?php if (isset($_SESSION['msg'])): ?>
<div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 z-3" style="max-width: 400px;" role="alert">
    <?php echo htmlspecialchars($_SESSION['msg']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg'], $_SESSION['msg_type']); endif; ?>

<div class="container mt-4">
    <h1>Dashboard Executivo</h1>
    <p>Visão geral completa do desempenho da empresa.</p>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Total de Clientes</div>
            <div class="stat-value"><?php echo number_format($totalClientes, 0, ',', '.'); ?></div>
            <div class="stat-change"><?php echo $variacaoTexto; ?></div>
        </div>
        <div class="stat-card secondary">
            <div class="stat-title">Produtos em Estoque</div>
            <div class="stat-value">15.632</div>
            <div class="stat-change">+8% esta semana</div>
        </div>
        <div class="stat-card tertiary">
            <div class="stat-title">Receita Mensal</div>
            <div class="stat-value">R$ 284,7K</div>
            <div class="stat-change">+23% vs mês anterior</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
