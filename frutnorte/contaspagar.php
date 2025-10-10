<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

$idcliente = (int)$_SESSION['empresa_id'];  // Garante inteiro para segurança

// ==================== CONEXÃO SISTEMA ====================
require_once 'config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// ==================== CONFIGURAÇÃO DE PAGINAÇÃO ====================
$registros_por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// ==================== FILTROS DE BUSCA ====================
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_fornecedor = isset($_GET['fornecedor']) ? $_GET['fornecedor'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$filtro_codentrada = isset($_GET['codentrada']) ? (int)$_GET['codentrada'] : 0;
$nome_entrada_filtrada = '';

// Buscar nome da entrada se filtrado (já filtrado por idcliente - correto)
if ($filtro_codentrada > 0) {
    $sql_entrada = "SELECT numeronota, Dataentrada FROM entradas WHERE codentrada = ? AND idcliente = ?";
    $stmt_entrada = $pdo->prepare($sql_entrada);
    $stmt_entrada->execute([$filtro_codentrada, $idcliente]);
    $entrada_info = $stmt_entrada->fetch(PDO::FETCH_ASSOC);
    if ($entrada_info) {
        $nome_entrada_filtrada = " (Entrada #{$filtro_codentrada} - NF: " . htmlspecialchars($entrada_info['numeronota'] ?? 'N/A') . ", Data: " . date('d/m/Y', strtotime($entrada_info['Dataentrada'])) . ")";
    }
}

// ==================== ORDENÇÃO ====================
$ordem_campo = isset($_GET['ordem']) ? $_GET['ordem'] : 'datavencimento';
$ordem_direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'ASC';
$campos_permitidos = ['codpagar','numeronota','dataemissao','Datalancamento','datavencimento','vrtitulo','vrpago','saldo','seqcodpagar'];
if (!in_array($ordem_campo, $campos_permitidos)) $ordem_campo = 'datavencimento';
if (!in_array($ordem_direcao, ['ASC','DESC'])) $ordem_direcao = 'ASC';

// ==================== CONSTRUÇÃO DA QUERY ====================
$where_conditions = ["cp.idcliente = ?"];
$params = [$idcliente];

if ($filtro_codentrada > 0) {
    $where_conditions[] = "cp.codentrada = ?";
    $params[] = $filtro_codentrada;
}

if (!empty($busca)) {
    $where_conditions[] = "(c.Nome LIKE ? OR c.Fantasia LIKE ? OR cp.numeronota LIKE ? OR cp.obs LIKE ? OR tpd.Descricao LIKE ?)";
    $params = array_merge($params, ["%$busca%", "%$busca%", "%$busca%", "%$busca%", "%$busca%"]);
}

if ($filtro_status !== '') {
    if ($filtro_status == 'PENDENTE') {
        $where_conditions[] = "(cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0)";  // MELHORIA: COALESCE para nulos
    } elseif ($filtro_status == 'PAGO') {
        $where_conditions[] = "cp.saldo <= 0 AND COALESCE(cp.vrpago, 0) > 0";  // MELHORIA: COALESCE
    } elseif ($filtro_status == 'VENCIDO') {
        $where_conditions[] = "cp.datavencimento < CURDATE() AND (cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0)";  // MELHORIA: COALESCE
    }
}

if (!empty($filtro_fornecedor)) {
    $where_conditions[] = "cp.codcliente = ?";
    $params[] = $filtro_fornecedor;
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "cp.datavencimento >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "cp.datavencimento <= ?";
    $params[] = $filtro_data_fim;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ==================== CONTAGEM TOTAL ====================
// JOINs já filtrados por idcliente (correto)
$sql_count = "SELECT COUNT(*) as total 
              FROM contaspagar cp
              LEFT JOIN clientes c ON cp.codcliente = c.codcliente AND c.idcliente = ?
              LEFT JOIN tipodespesas tpd ON cp.codtpdes = tpd.codtpdes AND tpd.idcliente = ?
              $where_clause";  // where_clause já inclui cp.idcliente
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute(array_merge([$idcliente, $idcliente], $params));  // Parâmetros corretos
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Log para depuração (remova em produção)
error_log("Contagem de contas a pagar para idcliente={$idcliente}: {$total_registros} registros com filtros: " . print_r($params, true));

// ==================== BUSCA CONTAS A PAGAR ====================
// MELHORIA: Subquery com filtro idcliente para total_parcelas
$sql = "SELECT 
            cp.*,
            c.Nome as fornecedor_nome,
            c.Fantasia as fornecedor_fantasia,
            c.cnpj_cpf as fornecedor_cnpj,  /* ADICIONADO: CNPJ para relatórios */
            tpd.Descricao as tipo_despesa,
            tpp.Descricao as tipo_pagamento,
            cp.seqcodpagar as numero_parcela,
            (SELECT COUNT(*) FROM contaspagar cp3 WHERE cp3.codpagar = cp.codpagar AND cp3.idcliente = ?) as total_parcelas,
            CASE 
                WHEN cp.saldo <= 0 AND COALESCE(cp.vrpago, 0) > 0 THEN 'PAGO'
                WHEN cp.datavencimento < CURDATE() AND (cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0) THEN 'VENCIDO'
                ELSE 'PENDENTE'
            END as status_pagamento,
            CASE 
                WHEN cp.datavencimento < CURDATE() AND (cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0) THEN 'VENCIDO'
                WHEN cp.datavencimento = CURDATE() AND (cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0) THEN 'HOJE'
                ELSE 'PENDENTE'
            END as status_cor
        FROM contaspagar cp
        LEFT JOIN clientes c ON cp.codcliente = c.codcliente AND c.idcliente = ?
        LEFT JOIN tipodespesas tpd ON cp.codtpdes = tpd.codtpdes AND tpd.idcliente = ?
        LEFT JOIN tipopagamentos tpp ON cp.codtppag = tpp.codtppag AND tpp.idcliente = ?
        $where_clause
        ORDER BY $ordem_campo $ordem_direcao
        LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$idcliente, $idcliente, $idcliente, $idcliente], $params));  // Parâmetros: subquery + JOINs + filtros
$contaspagar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log para depuração
error_log("Busca de contas a pagar para idcliente={$idcliente}: " . count($contaspagar) . " registros retornados em " . date('Y-m-d H:i:s'));

// ==================== BUSCA FORNECEDORES ====================
// Sua query original, com melhorias: + campos extras, LIMIT, filtro ativo
$sql_fornecedores = "SELECT DISTINCT c.codcliente, c.Nome, c.Fantasia, c.cnpj_cpf, c.Cidade, c.Uf  -- ADICIONADO: Campos extras
                     FROM clientes c 
                     INNER JOIN contaspagar cp ON c.codcliente = cp.codcliente
                     WHERE c.idcliente = ? AND cp.idcliente = ? AND c.ativo = 'S'  -- MELHORIA: + filtro ativo
                     ORDER BY c.Nome
                     LIMIT 100";  // MELHORIA: LIMIT para performance
$stmt_fornecedores = $pdo->prepare($sql_fornecedores);
$stmt_fornecedores->execute([$idcliente, $idcliente]);
$fornecedores = $stmt_fornecedores->fetchAll(PDO::FETCH_ASSOC);

// Log para depuração
error_log("Fornecedores com contas para idcliente={$idcliente}: " . count($fornecedores) . " resultados");

// ==================== CALCULO DE TOTAIS ====================
// MELHORIA: COALESCE para evitar NULL em somas
$sql_totais = "SELECT 
                COALESCE(SUM(cp.vrtitulo), 0) as total_titulo,  -- MELHORIA: COALESCE
                COALESCE(SUM(cp.vrpago), 0) as total_pago,      -- MELHORIA: COALESCE
                COALESCE(SUM(cp.saldo), 0) as total_saldo,      -- MELHORIA: COALESCE
                COALESCE(SUM(CASE WHEN cp.datavencimento < CURDATE() AND (cp.saldo > 0 OR COALESCE(cp.vrpago, 0) = 0) THEN cp.saldo ELSE 0 END), 0) as total_vencido  -- MELHORIA: COALESCE
               FROM contaspagar cp
               $where_clause";
$stmt_totais = $pdo->prepare($sql_totais);
$stmt_totais->execute($params);
$totais = $stmt_totais->fetch(PDO::FETCH_ASSOC);

// ==================== FUNÇÕES AUXILIARES ====================
function formatarValor($valor) {
    return 'R$ ' . number_format((float)($valor ?? 0), 2, ',', '.');  // MELHORIA: Cast para float, trata nulos
}

function formatarData($data) {
    if (empty($data) || $data == '0000-00-00' || $data == 'NULL') return '-';
    return date('d/m/Y', strtotime($data));
}

function getStatusBadge($status) {
    switch ($status) {
        case 'PAGO':
            return '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Pago</span>';
        case 'VENCIDO':
            return '<span class="status-badge status-inactive"><i class="fas fa-exclamation-triangle"></i> Vencido</span>';
        case 'HOJE':
            return '<span class="status-badge status-warning"><i class="fas fa-clock"></i> Vence Hoje</span>';  // MELHORIA: Badge para 'HOJE'
        default:
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pendente</span>';
    }
}

// ==================== VERIFICAR FILTROS ATIVOS ====================
$filtros_ativos = !empty($busca) || $filtro_status !== '' || !empty($filtro_fornecedor) || !empty($filtro_data_inicio) || !empty($filtro_data_fim) || $filtro_codentrada > 0;

// Log final de filtros ativos
error_log("Filtros ativos em contaspagar para idcliente={$idcliente}: " . ($filtros_ativos ? 'Sim' : 'Não'));
?>

 
<?php include 'includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar<?= $nome_entrada_filtrada ? ' - ' . $nome_entrada_filtrada : '' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="main-content">
    <!-- Conteúdo Principal -->
    <div class="content-area">
        <!-- Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="index.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Contas a Pagar<?= $nome_entrada_filtrada ?></span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Gestão de Contas a Pagar<?= $nome_entrada_filtrada ?></span>
                            <p class="title-subtitle">
                                <?= $total_registros ?> conta(s) a pagar encontrada(s)
                                <?php if ($filtro_codentrada > 0): ?>
                                    <span class="filter-indicator"><i class="fas fa-filter"></i> Filtrado por Entrada #<?= str_pad($filtro_codentrada, 6, '0', STR_PAD_LEFT) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary btn-filters <?= $filtros_ativos ? 'filters-active' : '' ?>" onclick="abrirModalFiltros()">
                        <i class="fas fa-filter"></i>
                        Filtros
                        <?php if ($filtros_ativos): ?>
                            <span class="filter-count"><?= count(array_filter([$busca, $filtro_status, $filtro_fornecedor, $filtro_data_inicio, $filtro_data_fim, $filtro_codentrada])) ?></span>
                        <?php endif; ?>
                    </button>
                    <a href="acoes_contaspagar/cadastro-contaspagar.php<?= $filtro_codentrada > 0 ? '?codentrada=' . $filtro_codentrada : '' ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nova Conta a pagar
                    </a>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <!-- Cards de Resumo -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= formatarValor($totais['total_titulo']) ?></div>
                    <div class="card-label">Total em Títulos</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="card-icon paid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= formatarValor($totais['total_pago']) ?></div>
                    <div class="card-label">Total Pago</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="card-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= formatarValor($totais['total_saldo']) ?></div>
                    <div class="card-label">Saldo Pendente</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="card-icon overdue">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= formatarValor($totais['total_vencido']) ?></div>
                    <div class="card-label">Vencido</div>
                </div>
            </div>
        </div>

        <!-- Lista de Contas a Pagar -->
        <div class="products-container">
            <?php if (empty($contaspagar)): ?>
                <a href="acoes_contaspagar/cadastro-contaspagar.php<?= $filtro_codentrada > 0 ? '?codentrada=' . $filtro_codentrada : '' ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Criar Primeira Conta a Pagar
                    </a>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="empty-title">Nenhuma conta a pagar encontrada</h3>
                    <p class="empty-text">
                        Não há contas a pagar com os filtros selecionados.
                        <?php if ($filtro_codentrada > 0): ?>
                            <br><strong>Filtrado por Entrada #<?= str_pad($filtro_codentrada, 6, '0', STR_PAD_LEFT) ?>. Tente remover o filtro.</strong>
                        <?php endif; ?>
                    </p>
                    
                </div>
            <?php else: ?>
                <div class="products-table">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="ordenarPor('codpagar')">
                                        Código
                                        <?php if ($ordem_campo === 'codpagar'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Fornecedor</th>
                                    <th>Tipo Despesa</th>
                                    <th class="sortable" onclick="ordenarPor('seqcodpagar')"> <!-- MUDAR 'parcela' para 'seqcodpagar' -->
    Parcela
    <?php if ($ordem_campo === 'seqcodpagar'): ?> <!-- MUDAR 'parcela' para 'seqcodpagar' -->
        <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
    <?php else: ?>
        <i class="fas fa-sort"></i>
    <?php endif; ?>
</th>
                                    <th class="sortable" onclick="ordenarPor('Datalancamento')">
                                        Lançamento
                                        <?php if ($ordem_campo === 'Datalancamento'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('numeronota')">
                                        Nota
                                        <?php if ($ordem_campo === 'numeronota'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('dataemissao')">
                                        Emissão
                                        <?php if ($ordem_campo === 'dataemissao'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('datavencimento')">
                                        Vencimento
                                        <?php if ($ordem_campo === 'datavencimento'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('vrtitulo')">
                                        Valor
                                        <?php if ($ordem_campo === 'vrtitulo'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('vrpago')">
                                        Pago
                                        <?php if ($ordem_campo === 'vrpago'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('saldo')">
                                        Saldo
                                        <?php if ($ordem_campo === 'saldo'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contaspagar as $conta): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $conta['codpagar'] ?></strong>
                                        </td>
                                        <td>
                                            <div class="supplier-name">
                                                <strong><?= htmlspecialchars($conta['fornecedor_nome'] ?? $conta['fornecedor_fantasia'] ?? 'N/A') ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-type">
                                                <?= htmlspecialchars($conta['tipo_despesa'] ?? 'Não informado') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="parcel-info">
                                                <?php 
                                                $numero_parcela = $conta['numero_parcela'] ?? 1;
                                                $total_parcelas = $conta['total_parcelas'] ?? 1;
                                                
                                                if ($total_parcelas > 1) {
                                                    echo "<span class='parcel-badge'>$numero_parcela/$total_parcelas</span>";
                                                } else {
                                                    echo "<span class='parcel-badge single'>À Vista</span>";
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: #6b7280;">
                                                <?= formatarData($conta['Datalancamento']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($conta['numeronota']): ?>
                                                <?= $conta['numeronota'] ?>/<?= $conta['serienota'] ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: #6b7280;">
                                                <?= formatarData($conta['dataemissao']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= formatarData($conta['datavencimento']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="value-title"><?= formatarValor($conta['vrtitulo']) ?></span>
                                        </td>
                                        <td>
                                            <span class="value-paid"><?= formatarValor($conta['vrpago']) ?></span>
                                        </td>
                                        <td>
                                            <span class="value-<?= $conta['saldo'] > 0 ? 'negative' : 'positive' ?>">
                                                <?= formatarValor($conta['saldo']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($conta['status_pagamento']) ?>
                                        </td>
                                        <td>
    <div class="actions">
        <a href="acoes_contaspagar/ver-contapagar.php?id=<?= $conta['id'] ?><?= $filtro_codentrada > 0 ? '&codentrada=' . $filtro_codentrada : '' ?>" class="btn-action btn-view" title="Visualizar">
            <i class="fas fa-eye"></i>
        </a>
        <a href="acoes_contaspagar/editar-contapagar.php?id=<?= $conta['id'] ?><?= $filtro_codentrada > 0 ? '&codentrada=' . $filtro_codentrada : '' ?>" class="btn-action btn-edit" title="Editar">
            <i class="fas fa-edit"></i>
        </a>
        <a href="acoes_contaspagar/pagar-contapagar.php?id=<?= $conta['id'] ?><?= $filtro_codentrada > 0 ? '&codentrada=' . $filtro_codentrada : '' ?>" class="btn-action btn-pay" title="Registrar Pagamento">
            <i class="fas fa-money-bill-wave"></i>
        </a>
    </div>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination">
                                <?php if ($pagina_atual > 1): ?>
                                    <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>&status=<?= $filtro_status ?>&fornecedor=<?= $filtro_fornecedor ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&codentrada=<?= $filtro_codentrada ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Anterior
                                    </a>
                                <?php endif; ?>

                                <div class="pagination-numbers">
                                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                        <?php if ($i == $pagina_atual): ?>
                                            <span class="pagination-current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&status=<?= $filtro_status ?>&fornecedor=<?= $filtro_fornecedor ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&codentrada=<?= $filtro_codentrada ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-number"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>&status=<?= $filtro_status ?>&fornecedor=<?= $filtro_fornecedor ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&codentrada=<?= $filtro_codentrada ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
                                        Próxima
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pagination-info">
                                Mostrando <?= ($offset + 1) ?> a <?= min($offset + $registros_por_pagina, $total_registros) ?> de <?= $total_registros ?> registros
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Filtros -->
<div id="modalFiltros" class="modal-filtros">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-filter"></i>
                Filtros de Contas a Pagar
                <?php if ($filtro_codentrada > 0): ?>
                    <span style="font-size: 12px; opacity: 0.8;">(Entrada #<?= str_pad($filtro_codentrada, 6, '0', STR_PAD_LEFT) ?> preservada)</span>
                <?php endif; ?>
            </h3>
            <button class="modal-close" onclick="fecharModalFiltros()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="GET" action="" class="modal-form">
            <!-- NOVO: Campo hidden para preservar codentrada -->
            <input type="hidden" name="codentrada" value="<?= $filtro_codentrada ?>">
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-search"></i>
                    Buscar
                </label>
                <div class="input-wrapper">
                    <input type="text" name="busca" class="form-input" 
                           placeholder="Fornecedor, nota fiscal, tipo despesa ou observações..." 
                           value="<?= htmlspecialchars($busca) ?>">
                    <i class="fas fa-search input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-toggle-on"></i>
                    Status
                </label>
                <div class="input-wrapper">
                    <select name="status" class="form-input">
                        <option value="">Todos</option>
                        <option value="PENDENTE" <?= $filtro_status === 'PENDENTE' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="PAGO" <?= $filtro_status === 'PAGO' ? 'selected' : '' ?>>Pagas</option>
                        <option value="VENCIDO" <?= $filtro_status === 'VENCIDO' ? 'selected' : '' ?>>Vencidas</option>
                    </select>
                    <i class="fas fa-chevron-down input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-truck"></i>
                    Fornecedor
                </label>
                <div class="input-wrapper">
                    <select name="fornecedor" class="form-input">
                        <option value="">Todos</option>
                        <?php foreach ($fornecedores as $fornecedor): ?>
                            <option value="<?= $fornecedor['codcliente'] ?>" <?= $filtro_fornecedor == $fornecedor['codcliente'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fornecedor['Nome'] ?: $fornecedor['Fantasia']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-chevron-down input-icon"></i>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i>
                        Data Início
                    </label>
                    <div class="input-wrapper">
                        <input type="date" name="data_inicio" class="form-input" 
                               value="<?= $filtro_data_inicio ?>">
                        <i class="fas fa-calendar input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i>
                        Data Fim
                    </label>
                    <div class="input-wrapper">
                        <input type="date" name="data_fim" class="form-input" 
                               value="<?= $filtro_data_fim ?>">
                        <i class="fas fa-calendar input-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                    <i class="fas fa-eraser"></i>
                    Limpar Filtros
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Aplicar Filtros
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Estilos principais mantidos do arquivo original */
* {
    box-sizing: border-box;
}

.main-content {
    padding: 0;
    background: #f8fafc;
    min-height: 100vh;
}

.content-area {
    margin-top: 50px !important;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.page-header {
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.2);
    color: white;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
}

.breadcrumb {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    font-size: 14px;
    opacity: 0.9;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.breadcrumb-link {
    color: white;
    text-decoration: none;
    transition: all 0.2s ease;
    padding: 4px 8px;
    border-radius: 6px;
}

.breadcrumb-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #e9d5ff;
    transform: translateY(-1px);
}

.breadcrumb-separator {
    margin: 0 8px;
    opacity: 0.7;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    margin: 0;
}

.title-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    backdrop-filter: blur(10px);
}

.title-main {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 2px;
}

.title-subtitle {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Botões */
.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #ffffff 0%, #f3e8ffff 100%);
    color: #6B46C1;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #f7faffff 0%, #ffffffff 100%);
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
    transform: translateY(-1px);
}

.btn-secondary {
    background: linear-gradient(135deg, #ffffff 0%, #f3e8ffff 100%);
    color: #6B46C1;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #faf5ffff 0%, #ffffffff 100%);
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
    transform: translateY(-1px);
}

.btn-filters {
    background: linear-gradient(135deg, #ffffff 0%, #f3e8ffff 100%);
    color: #6B46C1;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
}

.btn-filters:hover {
    background: linear-gradient(135deg, #ffffffff 0%, #f3e8ffff 100%);
    box-shadow: 0 2px 8px rgba(77, 50, 139, 0.3);
    transform: translateY(-1px);
}

/* Cards de Resumo */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.card-icon {
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
}

.card-icon.paid {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.card-icon.pending {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
}

.card-icon.overdue {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
}

.card-content {
    flex: 1;
}

.card-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}

.card-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

/* Container de produtos */
.products-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* Tabela */
.products-table {
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f8fafc;
    padding: 16px 5px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 16px 5px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #374151;
    vertical-align: top;
}

tr:hover {
    background: #f8fafc;
}

.supplier-name strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.value-title {
    color: #374151;
    font-weight: 600;
}

.value-paid {
    color: #10B981;
    font-weight: 600;
}

.value-positive {
    color: #10B981;
    font-weight: 600;
}

.value-negative {
    color: #EF4444;
    font-weight: 600;
}

.text-muted {
    color: #9ca3af;
    font-style: italic;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    gap: 4px;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-inactive {
    background: #fee2e2;
    color: #dc2626;
}

/* Ações */
.actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 8px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
}

.btn-view {
    background: #e9d5ff;
    color: #6b21a8;
}

.btn-view:hover {
    background: #d8b4fe;
    transform: translateY(-1px);
}

.btn-edit {
    background: #f3e8ff;
    color: #7e22ce;
}

.btn-edit:hover {
    background: #e9d5ff;
    transform: translateY(-1px);
}

.btn-pay {
    background: #d1fae5;
    color: #065f46;
}

.btn-pay:hover {
    background: #a7f3d0;
    transform: translateY(-1px);
}

/* Modal de Filtros */
.modal-filtros {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-filtros.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideInUp 0.3s ease;
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
    color: white;
    border-radius: 11px 11px 0 0;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 16px;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.modal-form {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-label i {
    color: #6B46C1;
    font-size: 12px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    background: white;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-input:focus {
    outline: none;
    border-color: #6B46C1;
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
    background: #faf5ff;
}

.input-icon {
    position: absolute;
    left: 12px;
    color: #9ca3af;
    font-size: 12px;
    pointer-events: none;
    z-index: 1;
}

.modal-actions {
    display: flex;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
    justify-content: flex-end;
}

/* Paginação */
.pagination-container {
    padding: 24px 32px;
    border-top: 1px solid #f1f5f9;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.pagination-btn, .pagination-number {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination-btn:hover, .pagination-number:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    transform: translateY(-1px);
}

.pagination-current {
    padding: 8px 12px;
    border-radius: 6px;
    background: #6B46C1;
    color: white;
    border: 1px solid #6B46C1;
    font-weight: 600;
}

.pagination-numbers {
    display: flex;
    gap: 4px;
}

.pagination-info {
    text-align: center;
    color: #6b7280;
    font-size: 13px;
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #6b7280;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 24px;
    opacity: 0.3;
    color: #6B46C1;
}

.empty-title {
    font-size: 20px;
    font-weight: 600;
    color: #374151;
    margin: 0 0 8px 0;
}

.empty-text {
    font-size: 14px;
    margin: 0 0 24px 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #6B46C1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 280px;
    animation: slideInRight 0.3s ease;
}

.toast.error {
    border-left-color: #ef4444;
}

.toast.warning {
    border-left-color: #f59e0b;
}

.toast.info {
    border-left-color: #8b5cf6;
}

/* Colunas ordenáveis */
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.2s ease;
}

.sortable:hover {
    background: #f1f5f9;
    color: #6B46C1;
}

.sortable i {
    margin-left: 6px;
    font-size: 10px;
    opacity: 0.6;
}

.sortable:hover i {
    opacity: 1;
}

/* NOVOS ESTILOS PARA PARCELAS E TIPO DE DESPESA */
.parcel-info {
    display: flex;
    justify-content: center;
}

.parcel-badge {
    background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%);
    color: white;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-align: center;
    min-width: 50px;
    box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
}

.parcel-badge.single {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.badge-type {
    background: #F3F4F6;
    color: #374151;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #E5E7EB;
    display: inline-block;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* NOVOS ESTILOS PARA O FILTRO DE ENTRADA */
.btn-filters.filters-active {
    background: linear-gradient(135deg, #6B46C1 0%, #4d328b 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(107, 70, 193, 0.4);
}

.filter-count {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    position: absolute;
    top: -8px;
    right: -8px;
}

.filter-indicator {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 8px;
    border-radius: 4px;
    margin-left: 8px;
    font-size: 12px;
    opacity: 0.9;
}

/* Ajuste para ações com codentrada */
.btn-action[href*="codentrada"] {
    position: relative;
}

.btn-action[href*="codentrada"]::after {
    content: '';
    position: absolute;
    top: 2px;
    right: 2px;
    width: 6px;
    height: 6px;
    background: #10B981;
    border-radius: 50%;
    opacity: 0.7;
}

/* Animações */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsividade */
@media (max-width: 768px) {
    .content-area {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: space-between;
    }
    
    .title-main {
        font-size: 20px;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .actions {
        flex-direction: column;
        gap: 4px;
    }
    
    th, td {
        padding: 12px 16px;
    }
    
    .pagination {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .pagination-container {
        padding: 20px;
    }
}
</style>

<script>
function registrarPagamento(id, fornecedor, saldo) {
    if (saldo <= 0) {
        showToast('Esta conta já está quitada!', 'warning');
        return;
    }
    
    const valor = prompt(`Registrar pagamento para ${fornecedor}\n\nSaldo pendente: R$ ${saldo.toFixed(2)}\n\nDigite o valor do pagamento:`, saldo.toFixed(2));
    
    if (valor && !isNaN(valor) && parseFloat(valor) > 0) {
        // Implementar lógica de pagamento aqui
        showToast(`Pagamento de R$ ${parseFloat(valor).toFixed(2)} registrado com sucesso!`, 'success');
        // Recarregar a página após um tempo para atualizar os dados
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    } else if (valor !== null) {
        showToast('Valor inválido!', 'error');
    }
}

// Função para mostrar toast
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                type === 'error' ? 'exclamation-circle' : 
                type === 'warning' ? 'exclamation-triangle' :
                type === 'info' ? 'info-circle' : 'info-circle';
    
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => {
            if (container.contains(toast)) {
                container.removeChild(toast);
            }
        }, 300);
    }, 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Verificar mensagens da URL
    const urlParams = new URLSearchParams(window.location.search);
    const mensagem = urlParams.get('mensagem');
    const erro = urlParams.get('erro');
    
    if (mensagem) {
        showToast(mensagem, 'success');
    } else if (erro) {
        showToast(erro, 'error');
    }
    
    // Função para abrir modal de filtros
    window.abrirModalFiltros = function() {
        const modal = document.getElementById('modalFiltros');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    
    // Função para fechar modal de filtros
    window.fecharModalFiltros = function() {
        const modal = document.getElementById('modalFiltros');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };
    
    // Fechar modal clicando fora
    document.getElementById('modalFiltros').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalFiltros();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModalFiltros();
        }
    });
    
    // Função para limpar filtros (atualizada para remover codentrada também)
    window.limparFiltros = function() {
        if (confirm('Tem certeza que deseja limpar todos os filtros? Isso removerá o filtro de entrada também.')) {
            window.location.href = 'contaspagar.php';
        }
    };
    
    // Auto-submit do formulário quando mudar os selects
    const selects = document.querySelectorAll('select[name="status"], select[name="fornecedor"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Destacar termo de busca nos resultados
    const termoBusca = '<?= htmlspecialchars($busca) ?>';
    if (termoBusca) {
        const regex = new RegExp(`(${termoBusca})`, 'gi');
        const elementos = document.querySelectorAll('.supplier-name strong, .badge-type');
        
        elementos.forEach(elemento => {
            if (elemento.textContent.toLowerCase().includes(termoBusca.toLowerCase())) {
                elemento.innerHTML = elemento.innerHTML.replace(regex, '<mark style="background: #d1fae5; padding: 1px 2px; border-radius: 2px;">$1</mark>');
            }
        });
    }
    
    // Função para ordenar (atualizada para preservar codentrada)
    window.ordenarPor = function(campo) {
        const urlParams = new URLSearchParams(window.location.search);
        const ordemAtual = urlParams.get('ordem');
        const direcaoAtual = urlParams.get('direcao');
        
        let novaDirecao = 'ASC';
        if (ordemAtual === campo && direcaoAtual === 'ASC') {
            novaDirecao = 'DESC';
        }
        
        urlParams.set('ordem', campo);
        urlParams.set('direcao', novaDirecao);
        
        // Preservar codentrada se existir
        <?php if ($filtro_codentrada > 0): ?>
            urlParams.set('codentrada', '<?= $filtro_codentrada ?>');
        <?php endif; ?>
        
        window.location.href = '?' + urlParams.toString();
    };
});
</script>
</body>
</html>