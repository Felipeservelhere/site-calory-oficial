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
require_once __DIR__ . '/config/databaselogin.php';
$dbLogin = new DatabaseLogin();
$connlogin = $dbLogin->getConnection();

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do admin autenticado com segurança multi-tenant
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

// ==================== CONEXÃO SISTEMA (frutnorte) ====================
require_once __DIR__ . '/config/database.php';
$db = new Database();
$conn = $db->getConnection();

// ==================== FILTRO DE PERÍODO ====================
$periodo = $_GET['periodo'] ?? 'mes_atual';

// Define as datas com base no período selecionado
switch ($periodo) {
    case 'hoje':
        $dataInicio = date('Y-m-d 00:00:00');
        $dataFim = date('Y-m-d 23:59:59');
        $periodoAnteriorInicio = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $periodoAnteriorFim = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $labelPeriodo = 'Hoje';
        $labelComparacao = 'vs ontem';
        break;
    
    case 'ontem':
        $dataInicio = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $dataFim = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $periodoAnteriorInicio = date('Y-m-d 00:00:00', strtotime('-2 days'));
        $periodoAnteriorFim = date('Y-m-d 23:59:59', strtotime('-2 days'));
        $labelPeriodo = 'Ontem';
        $labelComparacao = 'vs anteontem';
        break;
    
    case 'semana':
        $dataInicio = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $dataFim = date('Y-m-d 23:59:59');
        $periodoAnteriorInicio = date('Y-m-d 00:00:00', strtotime('monday last week'));
        $periodoAnteriorFim = date('Y-m-d 23:59:59', strtotime('sunday last week'));
        $labelPeriodo = 'Esta Semana';
        $labelComparacao = 'vs semana passada';
        break;
    
    case 'mes_atual':
        $dataInicio = date('Y-m-01 00:00:00');
        $dataFim = date('Y-m-t 23:59:59');
        $periodoAnteriorInicio = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $periodoAnteriorFim = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $labelPeriodo = 'Este Mês';
        $labelComparacao = 'vs mês anterior';
        break;
    
    case 'mes_passado':
        $dataInicio = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $dataFim = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $periodoAnteriorInicio = date('Y-m-01 00:00:00', strtotime('first day of -2 months'));
        $periodoAnteriorFim = date('Y-m-t 23:59:59', strtotime('last day of -2 months'));
        $labelPeriodo = 'Mês Passado';
        $labelComparacao = 'vs 2 meses atrás';
        break;
    
    case 'ano':
        $dataInicio = date('Y-01-01 00:00:00');
        $dataFim = date('Y-12-31 23:59:59');
        $periodoAnteriorInicio = date('Y-01-01 00:00:00', strtotime('-1 year'));
        $periodoAnteriorFim = date('Y-12-31 23:59:59', strtotime('-1 year'));
        $labelPeriodo = 'Este Ano';
        $labelComparacao = 'vs ano anterior';
        break;
    
    default:
        $dataInicio = date('Y-m-01 00:00:00');
        $dataFim = date('Y-m-t 23:59:59');
        $periodoAnteriorInicio = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $periodoAnteriorFim = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        $labelPeriodo = 'Este Mês';
        $labelComparacao = 'vs mês anterior';
}

// ==================== CONSULTAS REAIS - APENAS CLIENTES ====================

// Total de clientes
$sqlTotal = "SELECT COUNT(*) as total FROM clientes WHERE idcliente = ?";
$stmt = $conn->prepare($sqlTotal);
$stmt->execute([$empresa_id]);
$totalClientes = $stmt->fetch()['total'];

// Clientes no período atual
$sqlPeriodoAtual = "
    SELECT COUNT(*) as total 
    FROM clientes 
    WHERE data_cad BETWEEN ? AND ?
    AND idcliente = ?
";
$stmt = $conn->prepare($sqlPeriodoAtual);
$stmt->execute([$dataInicio, $dataFim, $empresa_id]);
$clientesPeriodoAtual = $stmt->fetch()['total'];

// Clientes no período anterior
$stmt = $conn->prepare($sqlPeriodoAtual);
$stmt->execute([$periodoAnteriorInicio, $periodoAnteriorFim, $empresa_id]);
$clientesPeriodoAnterior = $stmt->fetch()['total'];

// Variação de clientes
if ($clientesPeriodoAnterior > 0) {
    $variacaoClientes = (($clientesPeriodoAtual - $clientesPeriodoAnterior) / $clientesPeriodoAnterior) * 100;
    $variacaoClientesTexto = ($variacaoClientes >= 0 ? '+' : '') . number_format($variacaoClientes, 1, ',', '.') . '% ' . $labelComparacao;
} else {
    $variacaoClientesTexto = ($clientesPeriodoAtual > 0) ? 'Novos clientes no período' : 'Sem novos clientes';
}

// ==================== DADOS MOCKADOS (VALORES PADRÃO) ====================

// Produtos (mock)
$totalProdutos = 156;

// Vendas (mock baseado no período)
$vendasMock = [
    'hoje' => ['total' => 23, 'variacao' => '+5%'],
    'ontem' => ['total' => 19, 'variacao' => '-8%'],
    'semana' => ['total' => 142, 'variacao' => '+12%'],
    'mes_atual' => ['total' => 587, 'variacao' => '+18%'],
    'mes_passado' => ['total' => 498, 'variacao' => '+7%'],
    'ano' => ['total' => 5834, 'variacao' => '+23%']
];

$vendasData = $vendasMock[$periodo];
$totalVendas = $vendasData['total'];
$variacaoVendas = $vendasData['variacao'] . ' ' . $labelComparacao;

// Receita (mock baseado no período)
$receitaMock = [
    'hoje' => ['total' => 12450.80, 'variacao' => '+8%'],
    'ontem' => ['total' => 9876.50, 'variacao' => '-3%'],
    'semana' => ['total' => 78540.90, 'variacao' => '+15%'],
    'mes_atual' => ['total' => 284732.45, 'variacao' => '+23%'],
    'mes_passado' => ['total' => 231564.20, 'variacao' => '+11%'],
    'ano' => ['total' => 2847324.50, 'variacao' => '+28%']
];

$receitaData = $receitaMock[$periodo];
$totalReceita = $receitaData['total'];
$variacaoReceita = $receitaData['variacao'] . ' ' . $labelComparacao;

// Estoque (mock)
$totalEstoque = 2847;
$estoqueVariacao = '+3% esta semana';

// Entregas (mock)
$entregasPendentes = 47;
$entregasVariacao = '-12% esta semana';

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
    <style>
        /* Filtros de período */
        .period-filters {
            background: white;
            border-radius: 14px;
            padding: 20px 28px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .filter-label i {
            margin-right: 8px;
            color: #3B82F6;
        }

        .period-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .period-btn {
            padding: 10px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .period-btn:hover {
            background: #f1f5f9;
            color: #3B82F6;
            border-color: #3B82F6;
            transform: translateY(-1px);
            text-decoration: none;
        }

        .period-btn.active {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
            border-color: #3B82F6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            font-weight: 600;
        }

        .period-btn i {
            font-size: 11px;
        }

        /* Cards clicáveis */
        .stat-card {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .stat-card:hover {
            text-decoration: none;
            color: inherit;
        }

        .card-link-icon {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.2s ease;
            color: #3B82F6;
            font-size: 12px;
        }

        .stat-card:hover .card-link-icon {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Indicador de novos */
        .new-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        /* Mock badge (dados simulados) */
        .mock-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            opacity: 0.7;
        }

        /* Responsividade dos filtros */
        @media (max-width: 768px) {
            .period-filters {
                padding: 16px 20px;
            }

            .period-buttons {
                gap: 6px;
            }

            .period-btn {
                padding: 8px 14px;
                font-size: 12px;
            }
        }

        @media (max-width: 640px) {
            .period-buttons {
                flex-direction: column;
            }

            .period-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
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
                <p class="page-subtitle">Visão geral completa do desempenho da empresa em tempo real - <?php echo $labelPeriodo; ?></p>
            </div>

            <!-- Filtros de Período -->
            <div class="period-filters">
                <div class="filter-label">
                    <i class="fas fa-calendar-alt"></i>
                    Período de Análise
                </div>
                <div class="period-buttons">
                    <a href="?periodo=hoje" class="period-btn <?php echo $periodo == 'hoje' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Hoje
                    </a>
                    <a href="?periodo=ontem" class="period-btn <?php echo $periodo == 'ontem' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Ontem
                    </a>
                    <a href="?periodo=semana" class="period-btn <?php echo $periodo == 'semana' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Esta Semana
                    </a>
                    <a href="?periodo=mes_atual" class="period-btn <?php echo $periodo == 'mes_atual' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Este Mês
                    </a>
                    <a href="?periodo=mes_passado" class="period-btn <?php echo $periodo == 'mes_passado' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Mês Passado
                    </a>
                    <a href="?periodo=ano" class="period-btn <?php echo $periodo == 'ano' ? 'active' : ''; ?>">
                        <i class="fas fa-circle"></i>
                        Este Ano
                    </a>
                </div>
            </div>

            <!-- Cards Clicáveis -->
            <div class="stats-grid">
                <!-- Card 1: Clientes (DADOS REAIS) -->
                <a href="clientes.php" class="stat-card">
                    <?php if ($clientesPeriodoAtual > 0): ?>
                        <div class="new-badge"><?php echo $clientesPeriodoAtual; ?> novos</div>
                    <?php endif; ?>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-title">Total de Clientes</div>
                    <div class="stat-value"><?php echo number_format($totalClientes, 0, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $variacaoClientesTexto; ?></div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <!-- Card 2: Produtos (MOCK) -->
                <a href="produtos.php" class="stat-card secondary">
                    <div class="mock-badge">Demo</div>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                    </div>
                    <div class="stat-title">Produtos Cadastrados</div>
                    <div class="stat-value"><?php echo number_format($totalProdutos, 0, ',', '.'); ?></div>
                    <div class="stat-change">Total no sistema</div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <!-- Card 3: Vendas (MOCK) -->
                <a href="vendas.php" class="stat-card tertiary">
                    <div class="mock-badge">Demo</div>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                    <div class="stat-title">Vendas - <?php echo $labelPeriodo; ?></div>
                    <div class="stat-value"><?php echo number_format($totalVendas, 0, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $variacaoVendas; ?></div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <!-- Card 4: Receita (MOCK) -->
                <a href="relatorio-contas.php" class="stat-card">
                    <div class="mock-badge">Demo</div>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                    <div class="stat-title">Receita - <?php echo $labelPeriodo; ?></div>
                    <div class="stat-value">R$ <?php echo number_format($totalReceita, 2, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $variacaoReceita; ?></div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <!-- Card 5: Estoque (MOCK) -->
                <a href="estoque.php" class="stat-card secondary">
                    <div class="mock-badge">Demo</div>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
                    </div>
                    <div class="stat-title">Itens em Estoque</div>
                    <div class="stat-value"><?php echo number_format($totalEstoque, 0, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $estoqueVariacao; ?></div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>

                <!-- Card 6: Entregas (MOCK) -->
                <a href="vendas.php" class="stat-card tertiary">
                    <div class="mock-badge">Demo</div>
                    <div class="stat-header">
                        <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    </div>
                    <div class="stat-title">Entregas Pendentes</div>
                    <div class="stat-value"><?php echo number_format($entregasPendentes, 0, ',', '.'); ?></div>
                    <div class="stat-change"><?php echo $entregasVariacao; ?></div>
                    <div class="card-link-icon">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animação suave ao clicar nos cards
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function(e) {
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
            this.style.transform = '';
        }, 100);
    });
});

// Animação de entrada dos cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>
</body>
</html>