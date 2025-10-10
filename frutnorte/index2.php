<?php
session_start();

// Verificação de Login Básica
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

// Verificar e Recuperar admin_id
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

// ==================== CONEXÃO LOGIN (empresaweb) ====================
try {
    require_once 'config/databaselogin.php';
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();

    if (!$connlogin) {
        throw new Exception('Falha na conexão com banco de autenticação');
    }

    $admin_id = $_SESSION['admin_id'];

    // Buscar empresa_id do admin autenticado
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND cargo = 'Admin' AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        $_SESSION['msg'] = "Erro de autenticação. Acesso negado.";
        $_SESSION['msg_type'] = "error";
        header("Location: login.php");
        exit;
    }

    $empresa_id = $admin_data['empresa_id'];
    $_SESSION['empresa_id'] = $empresa_id;

} catch (Exception $e) {
    error_log("Erro na autenticação: " . $e->getMessage());
    $_SESSION['msg'] = "Erro interno do sistema. Tente novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (frutnorte) ====================
$totalClientes = 0;
$clientesMesAtual = 0;
$clientesMesAnterior = 0;
$variacaoTexto = '0% este mês';

try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Falha na conexão com banco de dados');
    }

    // CONSULTAS REAIS NO BANCO `frutnorte`
    $sqlTotal = "SELECT COUNT(*) as total FROM clientes WHERE idcliente = ?";
    $stmt = $conn->prepare($sqlTotal);
    $stmt->execute([$empresa_id]);
    $totalClientes = $stmt->fetch()['total'];

    $sqlMesAtual = "
        SELECT COUNT(*) as total 
        FROM clientes 
        WHERE MONTH(data_cad) = MONTH(CURDATE())
        AND YEAR(data_cad) = YEAR(CURDATE())
        AND idcliente = ?
    ";
    $stmt = $conn->prepare($sqlMesAtual);
    $stmt->execute([$empresa_id]);
    $clientesMesAtual = $stmt->fetch()['total'];

    $sqlMesAnterior = "
        SELECT COUNT(*) as total 
        FROM clientes 
        WHERE MONTH(data_cad) = MONTH(CURDATE() - INTERVAL 1 MONTH)
        AND YEAR(data_cad) = YEAR(CURDATE() - INTERVAL 1 MONTH)
        AND idcliente = ?
    ";
    $stmt = $conn->prepare($sqlMesAnterior);
    $stmt->execute([$empresa_id]);
    $clientesMesAnterior = $stmt->fetch()['total'];

    // Cálculo da variação %
    if ($clientesMesAnterior > 0) {
        $variacao = (($clientesMesAtual - $clientesMesAnterior) / $clientesMesAnterior) * 100;
        $variacaoFormatada = number_format($variacao, 2, ',', '.');
        $variacaoTexto = ($variacao >= 0 ? '+' : '') . $variacaoFormatada . '% este mês';
    } else {
        $variacaoTexto = ($clientesMesAtual > 0) ? '+∞% este mês' : '0% este mês';
    }

} catch (Exception $e) {
    error_log("Erro nas consultas: " . $e->getMessage());
    // Continua com valores padrão em caso de erro
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
    <meta name="description" content="Sistema de gestão empresarial para controle de frutas, clientes e vendas">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 z-3" style="max-width: 400px;" role="alert">
            <?php echo htmlspecialchars($_SESSION['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Dashboard Executivo</h1>
                <p class="page-subtitle">Visão geral completa do desempenho da empresa, vendas e operações em tempo real.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-users"></i></div></div>
                    <div class="stat-title">Total de Clientes</div>
                    <div class="stat-value"><?php echo number_format($totalClientes, 0, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $variacaoTexto; ?></div>
                </div>

                <div class="stat-card secondary">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-apple-alt"></i></div></div>
                    <div class="stat-title">Produtos em Estoque</div>
                    <div class="stat-value">15,632</div>
                    <div class="stat-change">+8% esta semana</div>
                </div>

                <div class="stat-card tertiary">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div></div>
                    <div class="stat-title">Receita Mensal</div>
                    <div class="stat-value">R$ 284,7K</div>
                    <div class="stat-change">+23% vs mês anterior</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-chart-line"></i></div></div>
                    <div class="stat-title">Vendas Hoje</div>
                    <div class="stat-value">1,247</div>
                    <div class="stat-change">+5% vs ontem</div>
                </div>

                <div class="stat-card secondary">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-truck"></i></div></div>
                    <div class="stat-title">Entregas Pendentes</div>
                    <div class="stat-value">89</div>
                    <div class="stat-change">-15% esta semana</div>
                </div>

                <div class="stat-card tertiary">
                    <div class="stat-header"><div class="stat-icon"><i class="fas fa-star"></i></div></div>
                    <div class="stat-title">Satisfação Cliente</div>
                    <div class="stat-value">4.8/5</div>
                    <div class="stat-change">+0.2 este mês</div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>