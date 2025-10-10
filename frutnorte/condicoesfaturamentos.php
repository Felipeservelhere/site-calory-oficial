<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

// ==================== CONEXÃO LOGIN (empresaweb) ====================
require_once 'config/databaselogin.php';
$dbLogin = new DatabaseLogin();
$connlogin = $dbLogin->getConnection();

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

// ==================== CONEXÃO SISTEMA (frutnorte) ====================
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// ==================== CONFIGURAÇÃO DE PAGINAÇÃO ====================
$registros_por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Filtros de busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_parcelas = isset($_GET['parcelas']) ? $_GET['parcelas'] : '';

$ordem_campo = isset($_GET['ordem']) ? $_GET['ordem'] : 'codcond';
$ordem_direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'ASC';

// Validar campos de ordenação permitidos
$campos_permitidos = ['id', 'idcliente', 'codcond', 'Descricao', 'Parcelas', 'CondPgto1', 'CondPgto2', 'CondPgto3', 'CondPgto4', 'condpgto5', 'condpgto6'];
if (!in_array($ordem_campo, $campos_permitidos)) {
    $ordem_campo = 'codcond';
}
if (!in_array($ordem_direcao, ['ASC', 'DESC'])) {
    $ordem_direcao = 'ASC';
}

// ==================== BUSCA CONDIÇÕES DE FATURAMENTO (filtrando pelo cliente logado) ====================
$where_conditions = ["idcliente = ?"];
$params = [$empresa_id];

if (!empty($busca)) {
    $where_conditions[] = "(Descricao LIKE ? OR codcond LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($filtro_parcelas !== '') {
    $where_conditions[] = "Parcelas = ?";
    $params[] = $filtro_parcelas;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM condicoes $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Buscar condições de faturamento com paginação
$sql = "SELECT id, idcliente, codcond, Descricao, Parcelas, 
               CondPgto1, CondPgto2, CondPgto3, CondPgto4, condpgto5, condpgto6
        FROM condicoes
        $where_clause 
        ORDER BY $ordem_campo $ordem_direcao 
        LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$condicoes_faturamento = $stmt->fetchAll();
?>


<?php include 'includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Condições de Faturamentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <span class="breadcrumb-item active">Condições de Faturamento</span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Gestão de Condições de Faturamento</span>
                            <p class="title-subtitle">
                                <?= $total_registros ?> condição(ões) de faturamento encontrada(s)
                            </p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary btn-filters" onclick="abrirModalFiltros()">
                        <i class="fas fa-filter"></i>
                        Filtros
                    </button>
                    <a href="acoes_condicoesfaturamentos/cadastro-condicoesfaturamentos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nova Condição de Faturamento
                    </a>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <!-- Lista de Condições de Faturamento -->
        <div class="products-container">
            
            <?php if (empty($condicoes_faturamento)): ?>
                <a href="acoes_condicoesfaturamentos/cadastro-condicoesfaturamentos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Cadastrar Primeira Condição de Faturamento
                    </a>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="empty-title">Nenhuma condição de faturamento encontrada</h3>
                    <p class="empty-text">Não há condições de faturamento cadastradas com os filtros selecionados.</p>
                    
                </div>
            <?php else: ?>
                <div class="products-table">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="ordenarPor('codcond')">
                                        Código
                                        <?php if ($ordem_campo === 'codcond'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('Descricao')">
                                        Descrição
                                        <?php if ($ordem_campo === 'Descricao'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('idcliente')">
                                        ID Cliente
                                        <?php if ($ordem_campo === 'idcliente'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('Parcelas')">
                                        Parcelas
                                        <?php if ($ordem_campo === 'Parcelas'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Condições de Pagamento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($condicoes_faturamento as $condicao): ?>
                                    <tr>
                                        <td>
                                            <strong style="font-size: 16px;"><?= htmlspecialchars($condicao['codcond']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="payment-name">
                                                <strong style="font-size: 16px;"><?= htmlspecialchars($condicao['Descricao']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size: 14px; color: #6b7280;">
                                                <?= htmlspecialchars($condicao['idcliente']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="parcelas-badge">
                                                <?= $condicao['Parcelas'] ?> parcela(s)
                                            </span>
                                        </td>
                                        <td>
                                            <div class="payment-conditions">
                                                <?php 
                                                $condicoes = [];
                                                if (!empty($condicao['CondPgto1'])) $condicoes[] = $condicao['CondPgto1'];
                                                if (!empty($condicao['CondPgto2'])) $condicoes[] = $condicao['CondPgto2'];
                                                if (!empty($condicao['CondPgto3'])) $condicoes[] = $condicao['CondPgto3'];
                                                if (!empty($condicao['CondPgto4'])) $condicoes[] = $condicao['CondPgto4'];
                                                if (!empty($condicao['condpgto5'])) $condicoes[] = $condicao['condpgto5'];
                                                if (!empty($condicao['condpgto6'])) $condicoes[] = $condicao['condpgto6'];
                                                
                                                if (!empty($condicoes)): ?>
                                                    <?php foreach (array_slice($condicoes, 0, 3) as $cond): ?>
                                                        <span class="condition-badge"><?= htmlspecialchars($cond) ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($condicoes) > 3): ?>
                                                        <span class="condition-more">+<?= count($condicoes) - 3 ?> mais</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nenhuma condição definida</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="acoes_condicoesfaturamentos/visualizar-condicoesfaturamentos.php?id=<?= $condicao['id'] ?>" class="btn-action btn-view" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="acoes_condicoesfaturamentos/editar-condicoesfaturamentos.php?id=<?= $condicao['id'] ?>" class="btn-action btn-edit" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmarExclusao(<?= $condicao['id'] ?>, '<?= htmlspecialchars(addslashes($condicao['Descricao'])) ?>')" 
                                                        class="btn-action btn-delete" title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
                                    <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>&parcelas=<?= $filtro_parcelas ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Anterior
                                    </a>
                                <?php endif; ?>

                                <div class="pagination-numbers">
                                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                        <?php if ($i == $pagina_atual): ?>
                                            <span class="pagination-current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&parcelas=<?= $filtro_parcelas ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-number"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>&parcelas=<?= $filtro_parcelas ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
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
                Filtros de Condições de Faturamento
            </h3>
            <button class="modal-close" onclick="fecharModalFiltros()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="GET" action="" class="modal-form">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-search"></i>
                    Buscar Condição de Faturamento
                </label>
                <div class="input-wrapper">
                    <input type="text" name="busca" class="form-input" 
                           placeholder="Descrição ou código..." 
                           value="<?= htmlspecialchars($busca) ?>">
                    <i class="fas fa-search input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-list-ol"></i>
                    Número de Parcelas
                </label>
                <div class="input-wrapper">
                    <select name="parcelas" class="form-input">
                        <option value="">Todas</option>
                        <option value="0" <?= $filtro_parcelas === '0' ? 'selected' : '' ?>>À vista (0)</option>
                        <option value="1" <?= $filtro_parcelas === '1' ? 'selected' : '' ?>>1 parcela</option>
                        <option value="2" <?= $filtro_parcelas === '2' ? 'selected' : '' ?>>2 parcelas</option>
                        <option value="3" <?= $filtro_parcelas === '3' ? 'selected' : '' ?>>3 parcelas</option>
                        <option value="4" <?= $filtro_parcelas === '4' ? 'selected' : '' ?>>4 parcelas</option>
                        <option value="5" <?= $filtro_parcelas === '5' ? 'selected' : '' ?>>5 parcelas</option>
                        <option value="6" <?= $filtro_parcelas === '6' ? 'selected' : '' ?>>6 parcelas</option>
                    </select>
                    <i class="fas fa-chevron-down input-icon"></i>
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
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #374151;
    vertical-align: top;
}

tr:hover {
    background: #f8fafc;
}

.payment-name strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.parcelas-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    background: #e9d5ff;
    color: #6b21a8;
}

.payment-conditions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.condition-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    background: #ede9fe;
    color: #5b21b6;
}

.condition-more {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    background: #f3f4f6;
    color: #6b7280;
}

.text-muted {
    color: #9ca3af;
    font-style: italic;
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

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #fecaca;
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
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-actions {
        flex-direction: column;
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
    
    .payment-conditions {
        flex-direction: column;
    }
}
</style>

<script>
function confirmarExclusao(id, descricao) {
    if (confirm(`Tem certeza que deseja excluir a condição de faturamento "${descricao}"?\n\nEsta ação não pode ser desfeita.`)) {
        excluirCondicaoFaturamento(id);
    }
}

function excluirCondicaoFaturamento(id) {
    // Mostrar loading
    const toastContainer = document.getElementById('toast-container');
    const loadingToast = document.createElement('div');
    loadingToast.className = 'toast';
    loadingToast.innerHTML = `
        <i class="fas fa-spinner fa-spin"></i>
        <span>Excluindo condição de faturamento...</span>
    `;
    toastContainer.appendChild(loadingToast);
    
    fetch('acoes_condicoesfaturamentos/excluir-condicoesfaturamentos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        // Remove o loading toast
        toastContainer.removeChild(loadingToast);
        
        if (data.success) {
            showToast('Condição de faturamento excluída com sucesso!', 'success');
            
            // Recarrega a página após um delay para o usuário ver o toast
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            showToast('Erro ao excluir condição de faturamento: ' + data.message, 'error');
        }
    })
    .catch(error => {
        // Remove o loading toast em caso de erro
        toastContainer.removeChild(loadingToast);
        showToast('Erro na requisição: ' + error.message, 'error');
    });
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
    
    if (mensagem === 'selecione_condicao') {
        showToast('Por favor, selecione uma condição de faturamento para editar clicando no botão "Editar" na lista abaixo.', 'info');
    } else if (erro === 'condicao_nao_encontrada') {
        showToast('Condição de faturamento não encontrada. Verifique se ainda existe.', 'error');
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
    
    // Função para limpar filtros
    window.limparFiltros = function() {
        if (confirm('Tem certeza que deseja limpar todos os filtros?')) {
            window.location.href = 'condicoesfaturamentos.php';
        }
    };
    
    // Auto-submit do formulário quando mudar os selects
    const selects = document.querySelectorAll('select[name="parcelas"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Destacar termo de busca nos resultados
    const termoBusca = '<?= htmlspecialchars($busca) ?>';
    if (termoBusca) {
        const regex = new RegExp(`(${termoBusca})`, 'gi');
        const elementos = document.querySelectorAll('.payment-name strong');
        
        elementos.forEach(elemento => {
            if (elemento.textContent.toLowerCase().includes(termoBusca.toLowerCase())) {
                elemento.innerHTML = elemento.innerHTML.replace(regex, '<mark style="background: #fef3c7; padding: 1px 2px; border-radius: 2px;">$1</mark>');
            }
        });
    }
    
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
        
        window.location.href = '?' + urlParams.toString();
    };
});
</script>