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

// ==================== CONEXÃO SISTEMA ====================
require_once 'config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$idcliente = $_SESSION['empresa_id'];

// ==================== CONFIGURAÇÃO DE PAGINAÇÃO ====================
$registros_por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// ==================== FILTROS DE BUSCA ====================
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_tipo_operacao = isset($_GET['tipo_operacao']) ? $_GET['tipo_operacao'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$filtro_fornecedor = isset($_GET['fornecedor']) ? $_GET['fornecedor'] : '';

$ordem_campo = isset($_GET['ordem']) ? $_GET['ordem'] : 'Dataentrada';
$ordem_direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'DESC';

// Validar campos de ordenação
$campos_permitidos = ['codentrada', 'numeronota', 'Dataentrada', 'Nome', 'vrTotal', 'tipooperacao'];
if (!in_array($ordem_campo, $campos_permitidos)) $ordem_campo = 'Dataentrada';
if (!in_array($ordem_direcao, ['ASC','DESC'])) $ordem_direcao = 'DESC';

// ==================== CONSTRUÇÃO DA QUERY ====================
$where_conditions = ["e.idcliente = ?"];
$params = [$idcliente];

// Filtro de busca
if (!empty($busca)) {
    $where_conditions[] = "(e.numeronota LIKE ? OR e.codentrada LIKE ? OR c.Nome LIKE ? OR e.NumChaveNfe LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Filtro tipo de operação
if ($filtro_tipo_operacao !== '') {
    $where_conditions[] = "e.tipooperacao = ?";
    $params[] = $filtro_tipo_operacao;
}

// Filtro por datas
if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "e.Dataentrada >= ?";
    $params[] = $filtro_data_inicio;
}
if (!empty($filtro_data_fim)) {
    $where_conditions[] = "e.Dataentrada <= ?";
    $params[] = $filtro_data_fim;
}

// Filtro por fornecedor
if (!empty($filtro_fornecedor)) {
    $where_conditions[] = "c.Nome LIKE ?";
    $params[] = "%$filtro_fornecedor%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ==================== CONTAGEM TOTAL ====================
$sql_count = "SELECT COUNT(*) as total 
              FROM entradas e 
              LEFT JOIN clientes c ON e.Codcliente = c.codcliente 
              $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// ==================== BUSCA DE ENTRADAS ====================
// CORREÇÃO: JOIN com filtro por idcliente
$sql = "SELECT e.codentrada, e.numeronota, e.serienota, e.Dataentrada, e.dtnota,
               e.vrprodutos, e.vrTotal, e.vrdesconto, e.total_frete,
               e.tipooperacao, e.NumChaveNfe, e.nitens, e.obs,
               c.Nome as fornecedor_nome, c.cnpj_cpf as fornecedor_documento,
               c.Cidade as fornecedor_cidade, c.Uf as fornecedor_uf
        FROM entradas e
        LEFT JOIN clientes c ON e.Codcliente = c.codcliente AND c.idcliente = e.idcliente  -- ADICIONADO: Filtro no JOIN
        $where_clause
        ORDER BY e.$ordem_campo $ordem_direcao
        LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entradas = $stmt->fetchAll();

// ==================== FUNÇÕES AUXILIARES ====================
function temContasPagar($codentrada, $pdo) {
    $sql = "SELECT COUNT(*) as total FROM contaspagar WHERE codentrada = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$codentrada]);
    $result = $stmt->fetch();
    return $result['total'] > 0;
}

function getTipoOperacao($tipo) {
    return $tipo === 'D' ? 'Devolução' : 'Entrada';
}

function getTipoOperacaoClass($tipo) {
    return $tipo === 'D' ? 'tipo-devolucao' : 'tipo-entrada';
}

// Verificar se há filtros ativos
$filtros_ativos = !empty($busca) || $filtro_tipo_operacao !== '' || !empty($filtro_data_inicio) || !empty($filtro_data_fim) || !empty($filtro_fornecedor);
?>


<?php include 'includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entradas</title>
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
                <span class="breadcrumb-item active">Entradas</span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Gestão de Entradas</span>
                            <p class="title-subtitle"><?= $total_registros ?> entrada(s) encontrada(s)</p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary btn-filters <?= $filtros_ativos ? 'filters-active' : '' ?>" onclick="abrirModalFiltros()">
                        <i class="fas fa-filter"></i>
                        Filtros
                        <?php if ($filtros_ativos): ?>
                            <span class="filter-count"><?= count(array_filter([$busca, $filtro_tipo_operacao, $filtro_data_inicio, $filtro_data_fim, $filtro_fornecedor])) ?></span>
                        <?php endif; ?>
                    </button>
                    <a href="acoes_entradas/entrada-produtos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nova Entrada
                    </a>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <!-- Lista de Entradas -->
        <div class="clients-container">
            <?php if (empty($entradas)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <h3 class="empty-title">Nenhuma entrada encontrada</h3>
                    <p class="empty-text">Não há entradas cadastradas com os filtros selecionados.</p>
                    <a href="acoes_entradas/entrada-produtos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Cadastrar Primeira Entrada
                    </a>
                </div>
            <?php else: ?>
                <div class="clients-table">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="sortable" onclick="ordenarPor('codentrada')">
                                        Código
                                        <?php if ($ordem_campo === 'codentrada'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('numeronota')">
                                        Nota Fiscal
                                        <?php if ($ordem_campo === 'numeronota'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('Nome')">
                                        Fornecedor
                                        <?php if ($ordem_campo === 'Nome'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('Dataentrada')">
                                        Data Entrada
                                        <?php if ($ordem_campo === 'Dataentrada'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('vrTotal')">
                                        Valor Total
                                        <?php if ($ordem_campo === 'vrTotal'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('tipooperacao')">
                                        Tipo
                                        <?php if ($ordem_campo === 'tipooperacao'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Itens</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entradas as $entrada): ?>
                                    <tr>
                                        <td>
                                            <div class="client-code">
                                                <strong><?= str_pad($entrada['codentrada'], 6, '0', STR_PAD_LEFT) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="nf-info">
                                                <strong><?= htmlspecialchars($entrada['numeronota'] ?? 'N/A') ?></strong>
                                                <?php if (!empty($entrada['serienota'])): ?>
                                                    <small>Série: <?= htmlspecialchars($entrada['serienota']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="supplier-info">
                                                <strong><?= htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A') ?></strong>
                                                <?php if (!empty($entrada['fornecedor_documento'])): ?>
                                                    <small><?= htmlspecialchars($entrada['fornecedor_documento']) ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($entrada['fornecedor_cidade'])): ?>
                                                    <small><?= htmlspecialchars($entrada['fornecedor_cidade']) ?> - <?= htmlspecialchars($entrada['fornecedor_uf']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <span class="date"><?= date('d/m/Y', strtotime($entrada['Dataentrada'])) ?></span>
                                                <?php if (!empty($entrada['dtnota']) && $entrada['dtnota'] !== $entrada['Dataentrada']): ?>
                                                    <small>NF: <?= date('d/m/Y', strtotime($entrada['dtnota'])) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="value-info">
                                                <strong class="total-value">R$ <?= number_format($entrada['vrTotal'], 2, ',', '.') ?></strong>
                                                <small>Produtos: R$ <?= number_format($entrada['vrprodutos'], 2, ',', '.') ?></small>
                                                <?php if ($entrada['total_frete'] > 0): ?>
                                                    <small>Frete: R$ <?= number_format($entrada['total_frete'], 2, ',', '.') ?></small>
                                                <?php endif; ?>
                                                <?php if ($entrada['vrdesconto'] > 0): ?>
                                                    <small>Desc.: R$ <?= number_format($entrada['vrdesconto'], 2, ',', '.') ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="operation-type-badge <?= getTipoOperacaoClass($entrada['tipooperacao']) ?>">
                                                <?= getTipoOperacao($entrada['tipooperacao']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="items-count"><?= $entrada['nitens'] ?> item(s)</span>
                                        </td>
                                        <td>
    <div class="actions">
        <a href="acoes_entradas/ver-entrada.php?id=<?= $entrada['codentrada'] ?>" class="btn-action btn-view" title="Visualizar">
            <i class="fas fa-eye"></i>
        </a>
        <a href="acoes_entradas/editar-entrada.php?id=<?= $entrada['codentrada'] ?>" class="btn-action btn-edit" title="Editar">
            <i class="fas fa-edit"></i>
        </a>
        
        <!-- BOTÃO: Ver Contas a Pagar ou Criar Conta -->
        <?php if (temContasPagar($entrada['codentrada'], $pdo)): ?>
            <a href="contaspagar.php?codentrada=<?= $entrada['codentrada'] ?>" class="btn-action btn-bills" title="Ver Contas a Pagar">
                <i class="fas fa-file-invoice-dollar"></i>
            </a>
        <?php else: ?>
            <button onclick="abrirModalCriarContaPagar(<?= $entrada['codentrada'] ?>)" 
                    class="btn-action btn-add-bill" title="Criar Conta a Pagar">
                <i class="fas fa-plus"></i>
            </button>
        <?php endif; ?>
        
        <button onclick="confirmarExclusao(<?= $entrada['codentrada'] ?>, '<?= htmlspecialchars(addslashes($entrada['numeronota'] ?? 'Entrada ' . $entrada['codentrada'])) ?>')" 
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
                                    <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>&tipo_operacao=<?= $filtro_tipo_operacao ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&fornecedor=<?= urlencode($filtro_fornecedor) ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Anterior
                                    </a>
                                <?php endif; ?>

                                <div class="pagination-numbers">
                                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                        <?php if ($i == $pagina_atual): ?>
                                            <span class="pagination-current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&tipo_operacao=<?= $filtro_tipo_operacao ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&fornecedor=<?= urlencode($filtro_fornecedor) ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-number"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>&tipo_operacao=<?= $filtro_tipo_operacao ?>&data_inicio=<?= $filtro_data_inicio ?>&data_fim=<?= $filtro_data_fim ?>&fornecedor=<?= urlencode($filtro_fornecedor) ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
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

<div id="modalCriarContaPagar" class="modal-filtros">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-file-invoice-dollar"></i>
                Criar Conta a Pagar
            </h3>
            <button class="modal-close" onclick="fecharModalCriarContaPagar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-form">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-calendar-alt"></i>
                    Data de Vencimento
                </label>
                <div class="input-wrapper">
                    <input type="date" id="data_vencimento" class="form-input" 
                           value="<?= date('Y-m-d') ?>">
                    <i class="fas fa-calendar input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-credit-card"></i>
                    Condição de Pagamento
                </label>
                <div class="input-wrapper">
                    <select id="condicao_pagamento" class="form-input">
                        <option value="">Selecione uma condição...</option>
                        <!-- As opções serão carregadas via JavaScript -->
                    </select>
                    <i class="fas fa-chevron-down input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-comment"></i>
                    Observações (Opcional)
                </label>
                <div class="input-wrapper">
                    <input type="text" id="observacoes_conta" class="form-input" 
                           placeholder="Observações para a conta...">
                    <i class="fas fa-comment input-icon"></i>
                </div>
            </div>
            
            <div id="info-entrada" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: none;">
                <h4 style="margin: 0 0 8px 0; color: #374151;">Informações da Entrada</h4>
                <div id="detalhes-entrada"></div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="fecharModalCriarContaPagar()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="criarContaPagar()" id="btnCriarConta">
                    <i class="fas fa-check"></i>
                    Criar Conta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div id="modalExclusao" class="modal-exclusao">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Confirmar Exclusão
            </h3>
            <button class="modal-close" onclick="fecharModalExclusao()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="exclusao-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            
            <div id="detalhes-exclusao" class="exclusao-detalhes">
                <!-- Detalhes serão populados via JS -->
            </div>
            
            <div id="opcoes-exclusao" class="exclusao-opcoes" style="display: none;">
                <!-- Opções serão populadas via JS -->
            </div>
            
            <div id="confirmacao-simples" class="confirmacao-simples" style="display: none;">
                <p>Tem certeza que deseja excluir esta entrada? Esta ação não pode ser desfeita.</p>
            </div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="fecharModalExclusao()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <div id="botoes-acao">
                <!-- Botões de ação serão populados via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Modal de Filtros -->
<div id="modalFiltros" class="modal-filtros">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-filter"></i>
                Filtros de Entradas
            </h3>
            <button class="modal-close" onclick="fecharModalFiltros()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal de Confirmação de Exclusão -->
<div id="modalExclusao" class="modal-exclusao">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Confirmar Exclusão
            </h3>
            <button class="modal-close" onclick="fecharModalExclusao()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="exclusao-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            
            <div id="detalhes-exclusao" class="exclusao-detalhes">
                <!-- Detalhes serão populados via JS -->
            </div>
            
            <div id="opcoes-exclusao" class="exclusao-opcoes" style="display: none;">
                <!-- Opções serão populadas via JS -->
            </div>
            
            <div id="confirmacao-simples" class="confirmacao-simples" style="display: none;">
                <p>Tem certeza que deseja excluir esta entrada? Esta ação não pode ser desfeita.</p>
            </div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="fecharModalExclusao()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <div id="botoes-acao">
                <!-- Botões de ação serão populados via JS -->
            </div>
        </div>
    </div>
</div>
        
        <form method="GET" action="" class="modal-form" id="formFiltros">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-search"></i>
                    Buscar Entrada
                </label>
                <div class="input-wrapper">
                    <input type="text" name="busca" class="form-input" 
                           placeholder="Número da nota, código, fornecedor, chave NFE..." 
                           value="<?= htmlspecialchars($busca) ?>">
                    <i class="fas fa-search input-icon"></i>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-exchange-alt"></i>
                        Tipo de Operação
                    </label>
                    <div class="input-wrapper">
                        <select name="tipo_operacao" class="form-input">
                            <option value="">Todos</option>
                            <option value="E" <?= $filtro_tipo_operacao === 'E' ? 'selected' : '' ?>>Entrada</option>
                            <option value="D" <?= $filtro_tipo_operacao === 'D' ? 'selected' : '' ?>>Devolução</option>
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
                        <input type="text" name="fornecedor" class="form-input" 
                               placeholder="Nome do fornecedor..." 
                               value="<?= htmlspecialchars($filtro_fornecedor) ?>">
                        <i class="fas fa-building input-icon"></i>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        Data Início
                    </label>
                    <div class="input-wrapper">
                        <input type="date" name="data_inicio" class="form-input" 
                               value="<?= htmlspecialchars($filtro_data_inicio) ?>">
                        <i class="fas fa-calendar input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        Data Fim
                    </label>
                    <div class="input-wrapper">
                        <input type="date" name="data_fim" class="form-input" 
                               value="<?= htmlspecialchars($filtro_data_fim) ?>">
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
/* Estilos principais mantidos do arquivo de clientes */
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
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

.btn-add-bill {
    background: #dcfce7;
    color: #166534;
    border: none;
    cursor: pointer;
}

.btn-add-bill:hover {
    background: #bbf7d0;
    transform: translateY(-1px);
}

.btn-bills {
    background: #dbeafe;
    color: #1e40af;
}

.btn-bills:hover {
    background: #bfdbfe;
    transform: translateY(-1px);
}

.breadcrumb {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    font-size: 14px;
    opacity: 0.9;
}

/* Modal de Exclusão - Estilos Elegantes */
.modal-exclusao {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1001;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-exclusao.active {
    display: flex;
}

.modal-exclusao .modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
    animation: slideInUp 0.3s ease;
}

.modal-exclusao .modal-header {
    padding: 24px;
    border-bottom: 1px solid #fee2e2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border-radius: 16px 16px 0 0;
}

.modal-exclusao .modal-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-exclusao .modal-close {
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

.modal-exclusao .modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.modal-exclusao .modal-body {
    padding: 32px 24px;
    text-align: center;
}

.exclusao-icon {
    font-size: 48px;
    color: #ef4444;
    margin-bottom: 16px;
    opacity: 0.8;
}

.exclusao-detalhes {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: left;
}

.exclusao-detalhes h4 {
    color: #dc2626;
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
}

.exclusao-detalhes p {
    margin: 0 0 8px 0;
    color: #374151;
    font-size: 14px;
}

.exclusao-detalhes .destaque {
    font-weight: 600;
    color: #dc2626;
}

.exclusao-opcoes {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: left;
}

.exclusao-opcoes h4 {
    color: #dc2626;
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
}

.exclusao-opcoes ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.exclusao-opcoes li {
    padding: 8px 0;
    border-bottom: 1px solid #fee2e2;
    color: #374151;
    font-size: 14px;
}

.exclusao-opcoes li:last-child {
    border-bottom: none;
}

.confirmacao-simples {
    text-align: center;
    color: #374151;
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.modal-exclusao .modal-actions {
    display: flex;
    gap: 12px;
    padding: 24px;
    border-top: 1px solid #fee2e2;
    justify-content: flex-end;
    background: #fafafa;
    border-radius: 0 0 16px 16px;
}

.modal-exclusao .btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    min-width: 140px;
    justify-content: center;
}

.modal-exclusao .btn-secondary {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.modal-exclusao .btn-secondary:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.modal-exclusao .btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.modal-exclusao .btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.modal-exclusao .btn-loading {
    background: #9ca3af;
    color: white;
    cursor: not-allowed;
}

.modal-exclusao .btn-loading i {
    animation: spin 1s linear infinite;
}

/* Animações específicas */
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-exclusao .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-exclusao .modal-actions {
        flex-direction: column;
    }
    
    .modal-exclusao .btn {
        width: 100%;
        justify-content: center;
    }
    
    .exclusao-detalhes, .exclusao-opcoes {
        padding: 12px;
    }
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
    cursor: pointer;
    border: none;
}

.btn-delete:hover {
    background: #fecaca;
    transform: translateY(-1px);
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
    color: #f0fdf4;
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
    position: relative;
}

.btn-primary {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #069b6c;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c8ffee 0%, #ffffffff 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #069b6c;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #c8f4ffff 0%, #ffffffff 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-filters {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #069b6c;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-filters:hover {
    background: linear-gradient(135deg, #ffffffff 0%, #e5fff7ff 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-filters.filters-active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
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

/* Estilos do Modal de Filtros */
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
    max-width: 600px;
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-radius: 16px 16px 0 0;
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
    display: flex;
    gap: 16px;
}


/* Modal de Exclusão - Estilos Elegantes */
.modal-exclusao {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1001; /* Maior que outros modais */
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.modal-exclusao.active {
    display: flex;
}

.modal-exclusao .modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
    animation: slideInUp 0.3s ease;
}

.modal-exclusao .modal-header {
    padding: 24px;
    border-bottom: 1px solid #fee2e2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); /* Gradiente vermelho para alerta */
    color: white;
    border-radius: 16px 16px 0 0;
}

.modal-exclusao .modal-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-exclusao .modal-close {
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

.modal-exclusao .modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.modal-exclusao .modal-body {
    padding: 32px 24px;
    text-align: center;
}

.exclusao-icon {
    font-size: 48px;
    color: #ef4444;
    margin-bottom: 16px;
    opacity: 0.8;
}

.exclusao-detalhes {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: left;
}

.exclusao-detalhes h4 {
    color: #dc2626;
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
}

.exclusao-detalhes p {
    margin: 0 0 8px 0;
    color: #374151;
    font-size: 14px;
}

.exclusao-detalhes .destaque {
    font-weight: 600;
    color: #dc2626;
}

.exclusao-opcoes {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: left;
}

.exclusao-opcoes h4 {
    color: #dc2626;
    margin: 0 0 12px 0;
    font-size: 16px;
    font-weight: 600;
}

.exclusao-opcoes ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.exclusao-opcoes li {
    padding: 8px 0;
    border-bottom: 1px solid #fee2e2;
    color: #374151;
    font-size: 14px;
}

.exclusao-opcoes li:last-child {
    border-bottom: none;
}

.confirmacao-simples {
    text-align: center;
    color: #374151;
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.modal-exclusao .modal-actions {
    display: flex;
    gap: 12px;
    padding: 24px;
    border-top: 1px solid #fee2e2;
    justify-content: flex-end;
    background: #fafafa;
    border-radius: 0 0 16px 16px;
}

.modal-exclusao .btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    min-width: 140px;
    justify-content: center;
}

.modal-exclusao .btn-secondary {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.modal-exclusao .btn-secondary:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.modal-exclusao .btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.modal-exclusao .btn-danger:hover:not(:disabled) {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.modal-exclusao .btn-loading {
    background: #9ca3af;
    color: white;
    cursor: not-allowed;
}

.modal-exclusao .btn-loading i {
    animation: spin 1s linear infinite;
}

/* Animações específicas */
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-exclusao .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-exclusao .modal-actions {
        flex-direction: column;
    }
    
    .modal-exclusao .btn {
        width: 100%;
        justify-content: center;
    }
    
    .exclusao-detalhes, .exclusao-opcoes {
        padding: 12px;
    }
}
.form-row .form-group {
    flex: 1;
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
    color: #10b981;
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
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: #f0fdf4;
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

/* Container de entradas */
.clients-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* Tabela */
.clients-table {
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

.btn-bills {
    background: #dbeafe;
    color: #1e40af;
}

.btn-bills:hover {
    background: #bfdbfe;
    transform: translateY(-1px);
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

/* Elementos específicos da tabela de entradas */
.client-code strong {
    color: #0f172a;
    font-size: 16px;
}

.nf-info strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.nf-info small {
    color: #6b7280;
    font-size: 12px;
}

.supplier-info strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.supplier-info small {
    color: #6b7280;
    font-size: 12px;
    display: block;
}

.date-info .date {
    color: #0f172a;
    font-weight: 600;
    display: block;
    margin-bottom: 2px;
}

.date-info small {
    color: #6b7280;
    font-size: 12px;
}

.value-info strong {
    color: #0f172a;
    display: block;
    margin-bottom: 4px;
    font-size: 16px;
}

.value-info small {
    color: #6b7280;
    font-size: 12px;
    display: block;
    margin-bottom: 2px;
}

.operation-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tipo-entrada {
    background: #d1fae5;
    color: #065f46;
}

.tipo-devolucao {
    background: #fef3c7;
    color: #92400e;
}

.items-count {
    color: #6b7280;
    font-size: 13px;
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
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
}

.btn-view {
    background: #dbeafe;
    color: #1e40af;
}

.btn-view:hover {
    background: #bfdbfe;
    transform: translateY(-1px);
}

.btn-edit {
    background: #fef3c7;
    color: #92400e;
}

.btn-edit:hover {
    background: #fde68a;
    transform: translateY(-1px);
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
    background: #10b981;
    color: white;
    border: 1px solid #10b981;
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
    color: #10b981;
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
    border-left: 4px solid #10b981;
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
    border-left-color: #0ea5e9;
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
    color: #10b981;
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
    
    .form-row {
        flex-direction: column;
        gap: 0;
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
}
</style>

<script>
    let entradaSelecionada = null;

// Função para abrir modal de criar conta a pagar
function abrirModalCriarContaPagar(codentrada) {
    entradaSelecionada = codentrada;
    
    // Mostrar loading
    document.getElementById('btnCriarConta').disabled = true;
    document.getElementById('btnCriarConta').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
    
    // Buscar informações da entrada e condições de pagamento
    Promise.all([
        buscarInformacoesEntrada(codentrada),
        buscarCondicoesPagamento()
    ]).then(([dadosEntrada, condicoes]) => {
        // Preencher informações da entrada
        document.getElementById('info-entrada').style.display = 'block';
        document.getElementById('detalhes-entrada').innerHTML = `
            <div style="font-size: 14px;">
                <strong>Entrada:</strong> ${dadosEntrada.codentrada}<br>
                <strong>Fornecedor:</strong> ${dadosEntrada.fornecedor_nome}<br>
                <strong>Valor Total:</strong> R$ ${parseFloat(dadosEntrada.vrTotal).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
            </div>
        `;
        
        // Preencher condições de pagamento
        const select = document.getElementById('condicao_pagamento');
        select.innerHTML = '<option value="">Selecione uma condição...</option>';
        condicoes.forEach(condicao => {
            const option = document.createElement('option');
            option.value = condicao.codcond;
            option.textContent = condicao.Descricao;
            select.appendChild(option);
        });
        
        // Habilitar botão
        document.getElementById('btnCriarConta').disabled = false;
        document.getElementById('btnCriarConta').innerHTML = '<i class="fas fa-check"></i> Criar Conta';
        
    }).catch(error => {
        console.error('Erro:', error);
        showToast('Erro ao carregar informações: ' + error.message, 'error');
        document.getElementById('btnCriarConta').disabled = false;
        document.getElementById('btnCriarConta').innerHTML = '<i class="fas fa-check"></i> Criar Conta';
    });
    
    // Abrir modal
    const modal = document.getElementById('modalCriarContaPagar');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Função para fechar modal
function fecharModalCriarContaPagar() {
    const modal = document.getElementById('modalCriarContaPagar');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    entradaSelecionada = null;
}

// Buscar informações da entrada
function buscarInformacoesEntrada(codentrada) {
    return fetch(`api_entradas/get_entrada_info.php?codentrada=${codentrada}`)
        .then(response => {
            if (!response.ok) throw new Error('Erro ao buscar informações da entrada');
            return response.json();
        });
}

// Buscar condições de pagamento
function buscarCondicoesPagamento() {
    return fetch('api_entradas/get_condicoes_pagamento.php')
        .then(response => {
            if (!response.ok) throw new Error('Erro ao buscar condições de pagamento');
            return response.json();
        });
}

// Criar conta a pagar
function criarContaPagar() {
    const dataVencimento = document.getElementById('data_vencimento').value;
    const codcond = document.getElementById('condicao_pagamento').value;
    const observacoes = document.getElementById('observacoes_conta').value;
    
    // Validações
    if (!dataVencimento) {
        showToast('Selecione a data de vencimento', 'warning');
        return;
    }
    
    if (!codcond) {
        showToast('Selecione uma condição de pagamento', 'warning');
        return;
    }
    
    // Mostrar loading
    const btn = document.getElementById('btnCriarConta');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
    
    // Enviar dados para a API
    fetch('api_entradas/criar_conta_pagar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            codentrada: entradaSelecionada,
            data_vencimento: dataVencimento,
            codcond: codcond,
            observacoes: observacoes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Conta a pagar criada com sucesso!', 'success');
            fecharModalCriarContaPagar();
            
            // Recarregar a página após um breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.message || 'Erro ao criar conta a pagar');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro ao criar conta a pagar: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Criar Conta';
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalCriarContaPagar').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalCriarContaPagar();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalCriarContaPagar');
        if (modal.classList.contains('active')) {
            fecharModalCriarContaPagar();
        }
    }
});
    // Função para confirmar exclusão (VERSÃO ATUALIZADA COM VERIFICAÇÃO DE CONTAS)
function confirmarExclusao(id, nome) {
    // Primeiro, verifica se há contas a pagar
    fetch(`api_entradas/verificar_contas_pagar.php?codentrada=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showToast('Erro ao verificar contas a pagar: ' + data.error, 'error');
                return;
            }

            const modal = document.getElementById('modalExclusao');
            const detalhes = document.getElementById('detalhes-exclusao');
            const opcoes = document.getElementById('opcoes-exclusao');
            const confirmacaoSimples = document.getElementById('confirmacao-simples');
            const botoesAcao = document.getElementById('botoes-acao');

            // Limpar conteúdo anterior
            detalhes.innerHTML = '';
            opcoes.innerHTML = '';
            botoesAcao.innerHTML = '';

            if (!data.temContas) {
                // Sem contas: confirmação simples
                detalhes.innerHTML = `
                    <h4>Entrada a ser excluída:</h4>
                    <p><span class="destaque">${nome}</span></p>
                    <p>Esta entrada não possui contas a pagar associadas.</p>
                `;
                confirmacaoSimples.style.display = 'block';
                opcoes.style.display = 'none';
                
                botoesAcao.innerHTML = `
                    <button type="button" class="btn btn-danger" onclick="executarExclusao(${id}, false)">
                        <i class="fas fa-trash"></i>
                        Excluir Entrada
                    </button>
                `;
            } else {
                // Tem contas: mostrar opções
                const quantidade = data.quantidade || 0;
                detalhes.innerHTML = `
                    <h4>Entrada a ser excluída:</h4>
                    <p><span class="destaque">${nome}</span></p>
                    <p>Esta entrada possui <span class="destaque">${quantidade} conta(s) a pagar</span> associada(s).</p>
                `;
                
                opcoes.innerHTML = `
                    <h4>Opções de exclusão:</h4>
                    <ul>
                        <li><strong>Excluir apenas a entrada:</strong> As contas a pagar permanecerão no sistema</li>
                        <li><strong>Excluir entrada e contas:</strong> Remove completamente a entrada e todas as contas associadas</li>
                    </ul>
                `;
                opcoes.style.display = 'block';
                confirmacaoSimples.style.display = 'none';
                
                botoesAcao.innerHTML = `
                    <button type="button" class="btn btn-secondary" onclick="executarExclusao(${id}, false)">
                        <i class="fas fa-file-alt"></i>
                        Apenas Entrada
                    </button>
                    <button type="button" class="btn btn-danger" onclick="executarExclusao(${id}, true)">
                        <i class="fas fa-trash"></i>
                        Entrada e Contas
                    </button>
                `;
            }

            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        })
        .catch(error => {
            console.error('Erro na verificação:', error);
            showToast('Erro ao verificar contas a pagar. Tente novamente.', 'error');
        });
}

// Função para excluir entrada (VERSÃO ATUALIZADA COM PARÂMETRO)
function excluirEntrada(id, excluirContas = false) {
    // Mostrar loading
    const toastContainer = document.getElementById('toast-container');
    const loadingToast = document.createElement('div');
    loadingToast.className = 'toast';
    loadingToast.innerHTML = `
        <i class="fas fa-spinner fa-spin"></i>
        <span>Excluindo entrada ${excluirContas ? '(e contas)...' : ''}</span>
    `;
    toastContainer.appendChild(loadingToast);
    
    fetch('acoes_entradas/excluir-entrada.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&excluir_contas=${excluirContas ? 1 : 0}` // Envia o parâmetro
    })
    .then(response => response.json())
    .then(data => {
        // Remove o loading toast
        toastContainer.removeChild(loadingToast);
        
        if (data.success) {
            showToast(data.message, 'success'); // Mensagem personalizada do PHP
            
            // Recarrega a página após um delay para o usuário ver o toast
            setTimeout(() => {
                window.location.reload();
            }, 2000); // Aumentei para 2s para ler a mensagem detalhada
        } else {
            showToast('Erro ao excluir: ' + data.message, 'error');
        }
    })
    .catch(error => {
        // Remove o loading toast em caso de erro
        toastContainer.removeChild(loadingToast);
        showToast('Erro na requisição: ' + error.message, 'error');
    });
}

function fecharModalExclusao() {
    const modal = document.getElementById('modalExclusao');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Função para executar a exclusão (substitui a antiga excluirEntrada)
function executarExclusao(id, excluirContas = false) {
    const modal = document.getElementById('modalExclusao');
    const botoesAcao = document.getElementById('botoes-acao');
    
    // Desabilitar botões e mostrar loading
    botoesAcao.innerHTML = `
        <button type="button" class="btn btn-loading" disabled>
            <i class="fas fa-spinner fa-spin"></i>
            Excluindo...
        </button>
    `;
    
    fetch('acoes_entradas/excluir-entrada.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&excluir_contas=${excluirContas ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            fecharModalExclusao();
            
            // Recarrega a página após um delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Erro ao excluir: ' + data.message, 'error');
            fecharModalExclusao();
        }
    })
    .catch(error => {
        showToast('Erro na requisição: ' + error.message, 'error');
        fecharModalExclusao();
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalExclusao').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalExclusao();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalExclusao');
        if (modal.classList.contains('active')) {
            fecharModalExclusao();
        }
    }
});

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
        
        if (mensagem === 'entrada_excluida') {
            showToast('Entrada excluída com sucesso!', 'success');
        } else if (mensagem === 'entrada_editada') {
            showToast('Entrada editada com sucesso!', 'success');
        } else if (erro === 'entrada_nao_encontrada') {
            showToast('Entrada não encontrada. Verifique se a entrada ainda existe.', 'error');
        } else if (erro === 'erro_banco_dados') {
            showToast('Erro no banco de dados. Tente novamente mais tarde.', 'error');
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
                window.location.href = 'entradas.php';
            }
        };
        
        // Destacar termo de busca nos resultados
        const termoBusca = '<?= htmlspecialchars($busca) ?>';
        if (termoBusca) {
            const regex = new RegExp(`(${termoBusca})`, 'gi');
            const elementos = document.querySelectorAll('.nf-info strong, .supplier-info strong');
            
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