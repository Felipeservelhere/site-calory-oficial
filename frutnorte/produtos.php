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

// ==================== CONEXÃO SISTEMA ====================
include 'config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// ==================== CONFIGURAÇÃO DE PAGINAÇÃO ====================
$registros_por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// ==================== FILTROS DE BUSCA ====================
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_ativo = isset($_GET['ativo']) ? $_GET['ativo'] : 'S'; // padrão: apenas ativos
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_grupo = isset($_GET['grupo']) ? $_GET['grupo'] : '';
$filtro_estoque = isset($_GET['estoque']) ? $_GET['estoque'] : '';

$ordem_campo = isset($_GET['ordem']) ? $_GET['ordem'] : 'data_cad';
$ordem_direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'DESC';

// ==================== MENSAGENS ====================
$mensagem = isset($_GET['mensagem']) ? $_GET['mensagem'] : '';
$erro = isset($_GET['erro']) ? $_GET['erro'] : '';

// ==================== VALIDAÇÃO DE ORDENÇÃO ====================
$campos_permitidos = ['id', 'codproduto', 'nome', 'categoria', 'grupo', 'vrunit', 'saldo_estoque', 'data_cad', 'ativo'];
if (!in_array($ordem_campo, $campos_permitidos)) $ordem_campo = 'data_cad';
if (!in_array($ordem_direcao, ['ASC', 'DESC'])) $ordem_direcao = 'DESC';

// ==================== CONSTRUÇÃO DA QUERY ====================
$where_conditions = ["p.idcliente = ?"]; // filtrar pelo cliente/empresa
$params = [$empresa_id];

if (!empty($busca)) {
    $where_conditions[] = "(p.nome LIKE ? OR p.codproduto LIKE ? OR p.descricao_reduzida LIKE ? OR p.variedade LIKE ? OR g.nome LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($filtro_ativo !== '') {
    $where_conditions[] = "p.ativo = ?";
    $params[] = $filtro_ativo;
}

if (!empty($filtro_categoria)) {
    $where_conditions[] = "p.variedade = ?";
    $params[] = $filtro_categoria;
}

if (!empty($filtro_grupo)) {
    $where_conditions[] = "p.codgrupo = ?";
    $params[] = $filtro_grupo;
}

if (!empty($filtro_estoque)) {
    switch ($filtro_estoque) {
        case 'baixo':
            $where_conditions[] = "p.saldo_estoque <= p.estoqueminimo";
            break;
        case 'zerado':
            $where_conditions[] = "p.saldo_estoque = 0";
            break;
        case 'disponivel':
            $where_conditions[] = "p.saldo_estoque > 0";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ==================== CONTAR TOTAL DE REGISTROS ====================
$sql_count = "SELECT COUNT(*) as total 
              FROM produtos p 
              LEFT JOIN grupos g ON p.codgrupo = g.codgrupo AND p.idcliente = g.idcliente 
              $where_clause";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// ==================== BUSCAR PRODUTOS COM PAGINAÇÃO ====================
$sql = "SELECT p.id, p.codproduto, p.nome, p.descricao_reduzida, p.variedade, p.codgrupo, 
               p.custototal, p.vrvenda, p.saldo_estoque, p.estoqueminimo, p.un, p.ativo, p.data_cad,
               g.nome as grupo_nome,
               CASE WHEN p.foto IS NOT NULL AND LENGTH(p.foto) > 0 THEN 1 ELSE 0 END as tem_foto
        FROM produtos p 
        LEFT JOIN grupos g ON p.codgrupo = g.codgrupo AND p.idcliente = g.idcliente 
        $where_clause 
        ORDER BY ";

switch ($ordem_campo) {
    case 'grupo':
        $sql .= "g.nome $ordem_direcao";
        break;
    case 'categoria':
        $sql .= "p.variedade $ordem_direcao";
        break;
    default:
        $sql .= "p.$ordem_campo $ordem_direcao";
        break;
}

$sql .= " LIMIT $registros_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// ==================== BUSCAR CATEGORIAS PARA O FILTRO ====================
$sql_categorias = "SELECT DISTINCT variedade FROM produtos WHERE idcliente = ? AND variedade IS NOT NULL AND variedade != '' ORDER BY variedade";
$stmt_categorias = $pdo->prepare($sql_categorias);
$stmt_categorias->execute([$empresa_id]);
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_COLUMN);

// ==================== BUSCAR GRUPOS PARA O FILTRO ====================
$sql_grupos = "SELECT codgrupo, nome FROM grupos WHERE idcliente = ? AND nome IS NOT NULL AND nome != '' ORDER BY nome";
$stmt_grupos = $pdo->prepare($sql_grupos);
$stmt_grupos->execute([$empresa_id]);
$grupos = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);
?>


<?php include 'includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos</title>
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
                <span class="breadcrumb-item active">Produtos</span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Gestão de Produtos</span>
                            <p class="title-subtitle">
                                <?= $total_registros ?> produto(s) 
                                <?= $filtro_ativo === 'S' ? 'ativo(s)' : ($filtro_ativo === 'N' ? 'inativo(s)' : '') ?> 
                                encontrado(s)
                            </p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary btn-filters" onclick="abrirModalFiltros()">
                        <i class="fas fa-filter"></i>
                        Filtros
                    </button>
                    <a href="acoes_produtos/cadastro-produtos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Produto
                    </a>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <!-- Lista de Produtos -->
        <div class="products-container">
            <?php if (empty($produtos)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="empty-title">Nenhum produto encontrado</h3>
                    <p class="empty-text">Não há produtos <?= $filtro_ativo === 'S' ? 'ativos' : ($filtro_ativo === 'N' ? 'inativos' : '') ?> cadastrados com os filtros selecionados.</p>
                    <a href="acoes_produtos/cadastro-produtos.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Cadastrar Primeiro Produto
                    </a>
                </div>
            <?php else: ?>
                <div class="products-table">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th class="sortable" onclick="ordenarPor('nome')">
                                        Produto
                                        <?php if ($ordem_campo === 'nome'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('grupo')">
                                        Grupo
                                        <?php if ($ordem_campo === 'grupo'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('categoria')">
                                        Variedade
                                        <?php if ($ordem_campo === 'categoria'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('vrvenda')">
                                        Preço Venda
                                        <?php if ($ordem_campo === 'vrvenda'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('saldo_estoque')">
                                        Estoque
                                        <?php if ($ordem_campo === 'saldo_estoque'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('ativo')">
                                        Status
                                        <?php if ($ordem_campo === 'ativo'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="sortable" onclick="ordenarPor('data_cad')">
                                        Cadastro
                                        <?php if ($ordem_campo === 'data_cad'): ?>
                                            <i class="fas fa-sort-<?= $ordem_direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr class="<?= $produto['ativo'] === 'N' ? 'produto-inativo' : '' ?>">
                                        <td>
                                            <div class="product-photo">
                                                <?php if ($produto['tem_foto']): ?>
                                                    <div class="photo-container" data-product-id="<?= $produto['id'] ?>">
                                                        <div class="photo-placeholder">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                        <img class="product-image lazy-load" 
                                                             data-src="api_produtos/buscar_foto.php?id=<?= $produto['id'] ?>" 
                                                             alt="<?= htmlspecialchars($produto['nome']) ?>"
                                                             style="display: none;">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="no-photo">
                                                        <i class="fas fa-camera-slash"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-name">
                                                <strong style="font-size: 20px;"><?= htmlspecialchars($produto['nome']) ?></strong>
                                                <?php if (!empty($produto['descricao_reduzida'])): ?>
                                                    <small><?= htmlspecialchars(substr($produto['descricao_reduzida'], 0, 50)) ?><?= strlen($produto['descricao_reduzida']) > 50 ? '...' : '' ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($produto['grupo_nome'])): ?>
                                                <span style="font-size: 16px;"><?= htmlspecialchars($produto['grupo_nome']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($produto['variedade'])): ?>
                                                <span style="font-size: 16px;"><?= htmlspecialchars($produto['variedade']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="price-info">
                                                <strong class="price-sale">R$ <?= number_format($produto['vrvenda'], 2, ',', '.') ?></strong>
                                                <?php if ($produto['custototal'] > 0): ?>
                                                    <small class="price-cost">Custo: R$ <?= number_format($produto['custototal'], 2, ',', '.') ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="stock-info">
                                                <?php
                                                $stock_class = '';
                                                if ($produto['saldo_estoque'] == 0) {
                                                    $stock_class = 'stock-zero';
                                                } elseif ($produto['saldo_estoque'] <= $produto['estoqueminimo']) {
                                                    $stock_class = 'stock-low';
                                                } else {
                                                    $stock_class = 'stock-ok';
                                                }
                                                ?>
                                                <span style="font-size: 16px;" class="<?= $stock_class ?>">
                                                    <?= $produto['saldo_estoque'] ?> <?= htmlspecialchars($produto['un']) ?>
                                                </span>
                                                <?php if ($produto['estoqueminimo'] > 0): ?>
                                                    <small class="stock-min">Mín: <?= $produto['estoqueminimo'] ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $produto['ativo'] === 'S' ? 'status-active' : 'status-inactive' ?>">
                                                <?= $produto['ativo'] === 'S' ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="date"><?= date('d/m/Y', strtotime($produto['data_cad'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="acoes_produtos/visualizar-produto.php?id=<?= $produto['id'] ?>" class="btn-action btn-view" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="acoes_produtos/editar_produto.php?id=<?= $produto['id'] ?>" class="btn-action btn-edit" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmarExclusao(<?= $produto['id'] ?>, '<?= htmlspecialchars(addslashes($produto['nome'])) ?>')" 
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
                                    <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>&ativo=<?= $filtro_ativo ?>&categoria=<?= $filtro_categoria ?>&grupo=<?= $filtro_grupo ?>&estoque=<?= $filtro_estoque ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                        Anterior
                                    </a>
                                <?php endif; ?>

                                <div class="pagination-numbers">
                                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                        <?php if ($i == $pagina_atual): ?>
                                            <span class="pagination-current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&ativo=<?= $filtro_ativo ?>&categoria=<?= $filtro_categoria ?>&grupo=<?= $filtro_grupo ?>&estoque=<?= $filtro_estoque ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-number"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>&ativo=<?= $filtro_ativo ?>&categoria=<?= $filtro_categoria ?>&grupo=<?= $filtro_grupo ?>&estoque=<?= $filtro_estoque ?>&ordem=<?= $ordem_campo ?>&direcao=<?= $ordem_direcao ?>" class="pagination-btn">
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
                Filtros de Produtos
            </h3>
            <button class="modal-close" onclick="fecharModalFiltros()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="GET" action="" class="modal-form">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-search"></i>
                    Buscar Produto
                </label>
                <div class="input-wrapper">
                    <input type="text" name="busca" class="form-input" 
                           placeholder="Nome, código, descrição..." 
                           value="<?= htmlspecialchars($busca) ?>">
                    <i class="fas fa-search input-icon"></i>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-toggle-on"></i>
                        Status
                    </label>
                    <div class="input-wrapper">
                        <select name="ativo" class="form-input">
                            <option value="S" <?= $filtro_ativo === 'S' ? 'selected' : '' ?>>Apenas Ativos</option>
                            <option value="N" <?= $filtro_ativo === 'N' ? 'selected' : '' ?>>Apenas Inativos</option>
                            <option value="" <?= $filtro_ativo === '' ? 'selected' : '' ?>>Todos</option>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-layer-group"></i>
                        Grupo
                    </label>
                    <div class="input-wrapper">
                        <select name="grupo" class="form-input">
                            <option value="">Todos</option>
                            <?php foreach ($grupos as $grupo): ?>
                                <option value="<?= htmlspecialchars($grupo['codgrupo']) ?>" <?= $filtro_grupo == $grupo['codgrupo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($grupo['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tags"></i>
                        Categoria
                    </label>
                    <div class="input-wrapper">
                        <select name="categoria" class="form-input">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= htmlspecialchars($categoria) ?>" <?= $filtro_categoria === $categoria ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-warehouse"></i>
                        Situação do Estoque
                    </label>
                    <div class="input-wrapper">
                        <select name="estoque" class="form-input">
                            <option value="">Todos</option>
                            <option value="disponivel" <?= $filtro_estoque === 'disponivel' ? 'selected' : '' ?>>Com Estoque</option>
                            <option value="baixo" <?= $filtro_estoque === 'baixo' ? 'selected' : '' ?>>Estoque Baixo</option>
                            <option value="zerado" <?= $filtro_estoque === 'zerado' ? 'selected' : '' ?>>Sem Estoque</option>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
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
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
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
}

.btn-primary {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #3b82f6;
    box-shadow: 0 2px 8px rgba(16, 83, 185, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #f7faffff 0%, #ffffffff 100%);
    box-shadow: 0 2px 8px rgba(16, 83, 185, 0.3);
    transform: translateY(-1px);
}

.btn-secondary {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #3b82f6;
    box-shadow: 0 2px 8px rgba(16, 83, 185, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #f0f9ffff 0%, #ffffffff 100%);
    box-shadow: 0 2px 8px rgba(16, 83, 185, 0.3);
    transform: translateY(-1px);
}

.btn-filters {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #3b82f6;
    box-shadow: 0 2px 8px rgba(16, 83, 185, 0.3);
}

.btn-filters:hover {
    background: linear-gradient(135deg, #ffffffff 0%, #e5fff7ff 100%);
    box-shadow: 0 2px 8px rgba(7, 32, 70, 0.3);
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

/* NOVO: Estilo para produtos inativos - fundo vermelho claro */
tr.produto-inativo {
    background: #fef2f2 !important;
    border-left: 4px solid #ef4444;
}

tr.produto-inativo:hover {
    background: #fee2e2 !important;
}

tr.produto-inativo td {
    border-bottom-color: #fecaca;
}

/* Estilos específicos para fotos dos produtos */
.product-photo {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-container {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
}

.photo-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #9ca3af;
    font-size: 20px;
    transition: all 0.3s ease;
}

.photo-placeholder.loading {
    color: #3b82f6;
}

.photo-placeholder.loading i {
    animation: pulse 1.5s infinite;
}

.product-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}

.product-image.loaded {
    display: block !important;
}

.no-photo {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    color: #9ca3af;
    font-size: 18px;
}

/* Elementos específicos da tabela de produtos */
.product-name strong {
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.product-name small {
    color: #6b7280;
    font-size: 12px;
}

.price-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.price-sale {
    color: #059669;
    font-size: 14px;
    font-weight: 600;
}

.price-cost {
    color: #6b7280;
    font-size: 11px;
}

.stock-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stock-ok {
    color: #065f46;
}

.stock-low {
    color: #92400e;
}

.stock-zero {
    color: #991b1b;
}

.stock-min {
    color: #6b7280;
    font-size: 10px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.date {
    color: #6b7280;
    font-size: 13px;
}

.text-muted {
    color: #9ca3af;
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
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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
    display: flex;
    gap: 16px;
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
    color: #3b82f6;
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
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: #f0f9ff;
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
    background: #3b82f6;
    color: white;
    border: 1px solid #3b82f6;
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
    color: #3b82f6;
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
    border-left: 4px solid #3b82f6;
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
    color: #3b82f6;
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

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
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
    
    .product-photo {
        width: 50px;
        height: 50px;
    }
    
    .photo-container, .no-photo {
        width: 50px;
        height: 50px;
    }
}
</style>

<script>
// Cache para fotos já carregadas
const photoCache = new Map();

// Intersection Observer para lazy loading
let imageObserver;

function initLazyLoading() {
    const imageObserverOptions = {
        threshold: 0.1,
        rootMargin: '50px 0px'
    };

    imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const container = entry.target;
                loadProductPhoto(container);
                observer.unobserve(container);
            }
        });
    }, imageObserverOptions);

    // Observar todos os containers de foto
    document.querySelectorAll('.photo-container').forEach(container => {
        imageObserver.observe(container);
    });
}

async function loadProductPhoto(container) {
    const productId = container.dataset.productId;
    const placeholder = container.querySelector('.photo-placeholder');
    const img = container.querySelector('.product-image');
    
    // Verificar cache primeiro
    if (photoCache.has(productId)) {
        const cachedData = photoCache.get(productId);
        if (cachedData.has_photo) {
            img.src = cachedData.photo_data;
            img.style.display = 'block';
            placeholder.style.display = 'none';
        }
        return;
    }
    
    // Mostrar loading
    placeholder.classList.add('loading');
    placeholder.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch(`api_produtos/buscar_foto.php?id=${productId}`);
        const data = await response.json();
        
        // Adicionar ao cache
        photoCache.set(productId, data);
        
        if (data.success && data.has_photo) {
            // Pré-carregar a imagem
            const tempImg = new Image();
            tempImg.onload = () => {
                img.src = data.photo_data;
                img.style.display = 'block';
                img.classList.add('loaded');
                placeholder.style.display = 'none';
            };
            tempImg.onerror = () => {
                showPhotoError(placeholder);
            };
            tempImg.src = data.photo_data;
        } else {
            showNoPhoto(placeholder);
        }
    } catch (error) {
        console.error('Erro ao carregar foto:', error);
        showPhotoError(placeholder);
    }
}

function showNoPhoto(placeholder) {
    placeholder.classList.remove('loading');
    placeholder.innerHTML = '<i class="fas fa-camera-slash"></i>';
    placeholder.style.color = '#9ca3af';
}

function showPhotoError(placeholder) {
    placeholder.classList.remove('loading');
    placeholder.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
    placeholder.style.color = '#ef4444';
    placeholder.title = 'Erro ao carregar foto';
}

function confirmarExclusao(id, nome) {
    if (confirm(`Tem certeza que deseja excluir o produto "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
        excluirProduto(id);
    }
}

function excluirProduto(id) {
    // Mostrar loading
    const toastContainer = document.getElementById('toast-container');
    const loadingToast = document.createElement('div');
    loadingToast.className = 'toast';
    loadingToast.innerHTML = `
        <i class="fas fa-spinner fa-spin"></i>
        <span>Excluindo produto...</span>
    `;
    toastContainer.appendChild(loadingToast);
    
    fetch('acoes_produtos/excluir_produto.php', {
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
            showToast('Produto excluído com sucesso!', 'success');
            
            // Recarrega a página após um delay para o usuário ver o toast
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } else {
            showToast('Erro ao excluir produto: ' + data.message, 'error');
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
    // Inicializar lazy loading das fotos
    initLazyLoading();
    
    // Verificar mensagens da URL
    const urlParams = new URLSearchParams(window.location.search);
    const mensagem = urlParams.get('mensagem');
    const erro = urlParams.get('erro');
    
    if (mensagem === 'selecione_produto') {
        showToast('Por favor, selecione um produto para editar clicando no botão "Editar" na lista abaixo.', 'info');
    } else if (erro === 'produto_nao_encontrado') {
        showToast('Produto não encontrado. Verifique se o produto ainda existe.', 'error');
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
            window.location.href = 'produtos.php';
        }
    };
    
    // Auto-submit do formulário quando mudar os selects
    const selects = document.querySelectorAll('select[name="ativo"], select[name="categoria"], select[name="grupo"], select[name="estoque"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Destacar termo de busca nos resultados
    const termoBusca = '<?= htmlspecialchars($busca) ?>';
    if (termoBusca) {
        const regex = new RegExp(`(${termoBusca})`, 'gi');
        const elementos = document.querySelectorAll('.product-name strong');
        
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