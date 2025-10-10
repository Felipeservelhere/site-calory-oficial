<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO LOGIN (para validar empresa_id) ====================
require_once '../config/databaselogin.php';

try {
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de autenticação: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do usuário autenticado
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        $_SESSION['msg'] = "Erro de autenticação. Acesso negado.";
        $_SESSION['msg_type'] = "error";
        header("Location: ../login.php");
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    $_SESSION['empresa_id'] = $idcliente_empresa;
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na validação de usuário: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações principais) ====================
require_once '../config/database.php';

// Verificar se foi fornecido um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "ID do produto não informado.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../produtos.php?mensagem=selecione_produto');
    exit;
}

$produto_id = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Buscar dados do produto (com filtro por empresa)
    $stmt = $pdo->prepare("
        SELECT p.*, g.nome as grupo_nome,
               CASE WHEN p.foto IS NOT NULL AND LENGTH(p.foto) > 0 THEN 1 ELSE 0 END as tem_foto
        FROM produtos p 
        LEFT JOIN grupos g ON p.codgrupo = g.codgrupo AND p.idcliente = g.idcliente 
        WHERE p.id = ? AND p.idcliente = ?
    ");
    $stmt->execute([$produto_id, $idcliente_empresa]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $_SESSION['msg'] = "Produto não encontrado ou sem permissão de acesso.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../produtos.php?erro=produto_nao_encontrado');
        exit;
    }
    
    // Buscar grupos para o select (filtrado por empresa)
    $stmt_grupos = $pdo->prepare("SELECT codgrupo, nome FROM grupos WHERE idcliente = ? ORDER BY nome");
    $stmt_grupos->execute([$idcliente_empresa]);
    $grupos = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['msg'] = "Erro no banco de dados: " . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header('Location: ../produtos.php?erro=erro_banco_dados');
    exit;
}

// Função para obter valor seguro do array
function getValue($array, $key, $default = '') {
    return isset($array[$key]) && $array[$key] !== null ? $array[$key] : $default;
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produtos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="main-content">
    <div class="content-area">
        <!-- Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../index.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">/</span>
                <a href="../produtos.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-box"></i>
                    Produtos
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Produto</span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Editar Produto</span>
                            <p class="title-subtitle"><?= htmlspecialchars(getValue($produto, 'nome')) ?></p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <a href="../produtos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
            </div>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <!-- Formulário de Edição -->
        <div class="form-container">
            <form id="formEditarProduto" enctype="multipart/form-data">
                <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">
                
                <!-- Status do Produto -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-toggle-on"></i>
                            Status do Produto
                        </h3>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-power-off"></i>
                                Status
                            </label>
                            <div class="toggle-container">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="ativo" id="ativo" <?= getValue($produto, 'ativo') === 'S' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label" id="toggle-label">
                                    <?= getValue($produto, 'ativo') === 'S' ? 'Produto Ativo' : 'Produto Inativo' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Foto do Produto -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-camera"></i>
                            Foto do Produto
                        </h3>
                    </div>
                    <div class="photo-upload-section">
                        <div class="current-photo">
                            <?php if (getValue($produto, 'tem_foto')): ?>
                                <div class="photo-preview" id="current-photo">
                                    <img id="current-photo-img" src="" alt="Foto atual" style="display: none;">
                                    <div class="photo-loading">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span>Carregando foto...</span>
                                    </div>
                                    <div class="photo-overlay">
                                        <button type="button" class="btn-photo-action" onclick="removerFoto()" title="Remover foto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-photo-placeholder" id="no-photo-placeholder">
                                    <i class="fas fa-camera-slash"></i>
                                    <span>Nenhuma foto</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="photo-upload">
                            <div class="upload-area" id="upload-area">
                                <input type="file" id="foto" name="foto" accept="image/*" style="display: none;">
                                <div class="upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h4>Clique para selecionar uma nova foto</h4>
                                    <p>ou arraste e solte aqui</p>
                                    <small>JPG, PNG, GIF ou WEBP - Máximo 5MB</small>
                                </div>
                            </div>
                            
                            <div class="photo-preview-new" id="photo-preview" style="display: none;">
                                <img id="preview-img" src="" alt="Preview">
                                <button type="button" class="btn-remove-preview" onclick="removerPreview()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações Básicas -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group form-group-wide">
                            <label class="form-label required">
                                <i class="fas fa-tag"></i>
                                Nome do Produto
                            </label>
                            <input type="text" name="nome" class="form-input" 
                                   placeholder="Digite o nome do produto" 
                                   value="<?= htmlspecialchars(getValue($produto, 'nome')) ?>" required maxlength="65">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                Descrição Reduzida
                            </label>
                            <input type="text" name="descricao_reduzida" class="form-input" 
                                   placeholder="Descrição breve do produto"
                                   value="<?= htmlspecialchars(getValue($produto, 'descricao_reduzida')) ?>" maxlength="30">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-balance-scale"></i>
                                Unidade
                            </label>
                            <select name="Un" class="form-input">
                                <option value="">Selecione...</option>
                                <option value="UN" <?= getValue($produto, 'Un') === 'UN' ? 'selected' : '' ?>>Unidade</option>
                                <option value="KG" <?= getValue($produto, 'Un') === 'KG' ? 'selected' : '' ?>>Quilograma</option>
                                <option value="G" <?= getValue($produto, 'Un') === 'G' ? 'selected' : '' ?>>Grama</option>
                                <option value="L" <?= getValue($produto, 'Un') === 'L' ? 'selected' : '' ?>>Litro</option>
                                <option value="ML" <?= getValue($produto, 'Un') === 'ML' ? 'selected' : '' ?>>Mililitro</option>
                                <option value="M" <?= getValue($produto, 'Un') === 'M' ? 'selected' : '' ?>>Metro</option>
                                <option value="CM" <?= getValue($produto, 'Un') === 'CM' ? 'selected' : '' ?>>Centímetro</option>
                                <option value="M2" <?= getValue($produto, 'Un') === 'M2' ? 'selected' : '' ?>>Metro Quadrado</option>
                                <option value="M3" <?= getValue($produto, 'Un') === 'M3' ? 'selected' : '' ?>>Metro Cúbico</option>
                                <option value="PC" <?= getValue($produto, 'Un') === 'PC' ? 'selected' : '' ?>>Peça</option>
                                <option value="PAR" <?= getValue($produto, 'Un') === 'PAR' ? 'selected' : '' ?>>Par</option>
                                <option value="CX" <?= getValue($produto, 'Un') === 'CX' ? 'selected' : '' ?>>Caixa</option>
                                <option value="PCT" <?= getValue($produto, 'Un') === 'PCT' ? 'selected' : '' ?>>Pacote</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tags"></i>
                                Variedade
                            </label>
                            <input type="text" name="variedade" class="form-input" 
                                   placeholder="Ex: Eletrônicos, Roupas, etc." 
                                   value="<?= htmlspecialchars(getValue($produto, 'variedade')) ?>" maxlength="30">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-layer-group"></i>
                                Grupo
                            </label>
                            <select name="codgrupo" class="form-input">
                                <option value="">Selecione um grupo</option>
                                <?php foreach ($grupos as $grupo): ?>
                                    <option value="<?= $grupo['codgrupo'] ?>" <?= getValue($produto, 'codgrupo') == $grupo['codgrupo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($grupo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-barcode"></i>
                                Código de Barras
                            </label>
                            <input type="text" name="CodigoBarra" class="form-input" 
                                   placeholder="Digite o código de barras" 
                                   value="<?= htmlspecialchars(getValue($produto, 'CodigoBarra')) ?>" maxlength="13">
                        </div>
                    </div>
                </div>

                <!-- Preços e Custos -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-dollar-sign"></i>
                            Preços e Custos
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shopping-cart"></i>
                                Valor Unitário (R$)
                            </label>
                            <input type="number" name="Vrunit" class="form-input" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'Vrunit') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-truck"></i>
                                Frete (R$)
                            </label>
                            <input type="number" name="Frete" class="form-input" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'Frete') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calculator"></i>
                                Custo Total (R$)
                            </label>
                            <input type="number" name="custototal" class="form-input" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'custototal') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-percentage"></i>
                                % Margem Bruta
                            </label>
                            <input type="number" name="perc_mb" class="form-input" 
                                   step="0.01" min="0" max="100" placeholder="0,00"
                                   value="<?= getValue($produto, 'perc_mb') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tag"></i>
                                Valor de Venda (R$)
                            </label>
                            <input type="number" name="Vrvenda" class="form-input" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'vrvenda') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-percentage"></i>
                                % Desconto à Vista
                            </label>
                            <input type="number" name="perc_avista" class="form-input" 
                                   step="0.01" min="0" max="100" placeholder="0,00"
                                   value="<?= getValue($produto, 'perc_avista') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-money-bill"></i>
                                Valor à Vista (R$)
                            </label>
                            <input type="number" name="Vravista" class="form-input" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'Vravista') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-arrow-down"></i>
                                Desconto Máximo (%)
                            </label>
                            <input type="number" name="MAXDESC" class="form-input" 
                                   step="0.01" min="0" max="100" placeholder="0,00"
                                   value="<?= getValue($produto, 'MAXDESC') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-dollar-sign"></i>
                                Preço Mínimo (R$)
                                <small style="color: #10b981; font-weight: normal;">(Calculado automaticamente)</small>
                            </label>
                            <input type="number" name="MINPRECO" class="form-input calculated-field" 
                                   step="0.01" min="0" placeholder="0,00"
                                   value="<?= getValue($produto, 'MINPRECO') ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Estoque -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-warehouse"></i>
                            Controle de Estoque
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-toggle-on"></i>
                                Controla Estoque?
                            </label>
                            <select name="estoque" class="form-input">
                                <option value="S" <?= getValue($produto, 'estoque', 'S') === 'S' ? 'selected' : '' ?>>Sim</option>
                                <option value="N" <?= getValue($produto, 'estoque', 'S') === 'N' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-boxes"></i>
                                Saldo Atual
                            </label>
                            <input type="number" name="saldo_estoque" class="form-input" 
                                   step="0.01" min="0" placeholder="0"
                                   value="<?= getValue($produto, 'saldo_estoque') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-exclamation-triangle"></i>
                                Estoque Mínimo
                            </label>
                            <input type="number" name="estoqueminimo" class="form-input" 
                                   step="0.01" min="0" placeholder="0"
                                   value="<?= getValue($produto, 'estoqueminimo') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-weight"></i>
                                Peso Bruto (Kg)
                            </label>
                            <input type="number" name="Pesobruto" class="form-input" 
                                   step="0.001" min="0" placeholder="0,000"
                                   value="<?= getValue($produto, 'Pesobruto') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-weight-hanging"></i>
                                Peso Líquido (Kg)
                            </label>
                            <input type="number" name="Pesoliquido" class="form-input" 
                                   step="0.001" min="0" placeholder="1,000"
                                   value="<?= getValue($produto, 'Pesoliquido', '1') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-list-ol"></i>
                                Controla Lote?
                            </label>
                            <select name="lote" class="form-input">
                                <option value="S" <?= getValue($produto, 'lote', 'S') === 'S' ? 'selected' : '' ?>>Sim</option>
                                <option value="N" <?= getValue($produto, 'lote', 'S') === 'N' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Informações Fiscais -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-file-invoice"></i>
                            Informações Fiscais
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-file-invoice"></i>
                                NCM
                            </label>
                            <input type="text" name="NCM" class="form-input" 
                                   maxlength="8" placeholder="00000000"
                                   value="<?= htmlspecialchars(getValue($produto, 'NCM')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-file-alt"></i>
                                CEST
                            </label>
                            <input type="text" name="cest" class="form-input" 
                                   maxlength="7" placeholder="0000000"
                                   value="<?= htmlspecialchars(getValue($produto, 'cest')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-globe"></i>
                                Origem
                            </label>
                            <select name="origem" class="form-input">
                                <option value="">Selecione...</option>
                                <option value="0" <?= getValue($produto, 'origem') == '0' ? 'selected' : '' ?>>0 - Nacional</option>
                                <option value="1" <?= getValue($produto, 'origem') == '1' ? 'selected' : '' ?>>1 - Estrangeira - Importação direta</option>
                                <option value="2" <?= getValue($produto, 'origem') == '2' ? 'selected' : '' ?>>2 - Estrangeira - Adquirida no mercado interno</option>
                                <option value="3" <?= getValue($produto, 'origem') == '3' ? 'selected' : '' ?>>3 - Nacional - Mercadoria com Conteúdo de Importação superior a 40%</option>
                                <option value="4" <?= getValue($produto, 'origem') == '4' ? 'selected' : '' ?>>4 - Nacional - Produção em conformidade com processos produtivos básicos</option>
                                <option value="5" <?= getValue($produto, 'origem') == '5' ? 'selected' : '' ?>>5 - Nacional - Mercadoria com Conteúdo de Importação inferior ou igual a 40%</option>
                                <option value="6" <?= getValue($produto, 'origem') == '6' ? 'selected' : '' ?>>6 - Estrangeira - Importação direta, sem similar nacional</option>
                                <option value="7" <?= getValue($produto, 'origem') == '7' ? 'selected' : '' ?>>7 - Estrangeira - Adquirida no mercado interno, sem similar nacional</option>
                                <option value="8" <?= getValue($produto, 'origem') == '8' ? 'selected' : '' ?>>8 - Nacional - Mercadoria com Conteúdo de Importação superior a 70%</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-code"></i>
                                Código ST
                            </label>
                            <input type="text" name="codst" class="form-input" 
                                   maxlength="1"
                                   value="<?= htmlspecialchars(getValue($produto, 'codst')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-ruler"></i>
                                Unidade Tributável
                            </label>
                            <input type="text" name="un_trib" class="form-input" 
                                   maxlength="3"
                                   value="<?= htmlspecialchars(getValue($produto, 'un_trib')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-exchange-alt"></i>
                                Conversor Tributável
                            </label>
                            <input type="number" name="conversor_trib" class="form-input" 
                                   step="0.0001" min="0"
                                   value="<?= getValue($produto, 'conversor_trib') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-gift"></i>
                                Benefício Fiscal
                            </label>
                            <input type="text" name="beneficio" class="form-input" 
                                   maxlength="10"
                                   value="<?= htmlspecialchars(getValue($produto, 'beneficio')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user-tie"></i>
                                Nota do Produtor
                            </label>
                            <select name="NP" class="form-input">
                                <option value="N" <?= getValue($produto, 'NP', 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                <option value="S" <?= getValue($produto, 'NP', 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Promoções -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-percent"></i>
                            Promoções e Descontos
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tags"></i>
                                Produto em Promoção?
                            </label>
                            <select name="promocao" class="form-input">
                                <option value="N" <?= getValue($produto, 'promocao', 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                <option value="S" <?= getValue($produto, 'promocao', 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-percentage"></i>
                                % Desconto Promoção
                            </label>
                            <input type="number" name="descpromocao" class="form-input" 
                                   step="0.01" min="0" max="100"
                                   value="<?= getValue($produto, 'descpromocao') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-dollar-sign"></i>
                                Valor Promocional (R$)
                            </label>
                            <input type="number" name="vrpromocao" class="form-input" 
                                   step="0.01" min="0"
                                   value="<?= getValue($produto, 'vrpromocao') ?>">
                        </div>
                    </div>
                </div>

                <!-- Configurações Gerais -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-cogs"></i>
                            Configurações Gerais
                        </h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-globe"></i>
                                Enviar para Site?
                            </label>
                            <select name="envia_site" class="form-input">
                                <option value="N" <?= getValue($produto, 'envia_site', 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                <option value="S" <?= getValue($produto, 'envia_site', 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-cog"></i>
                                É Insumo?
                            </label>
                            <select name="insumos" class="form-input">
                                <option value="N" <?= getValue($produto, 'insumos', 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                <option value="S" <?= getValue($produto, 'insumos', 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Descrição Adicional para NFe -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Descrição Adicional para NFe
                        </h3>
                    </div>
                    <div class="form-row">
                        <div class="form-group form-group-wide">
                            <textarea name="descricao_add_nfe" class="form-textarea" rows="3" 
                                      placeholder="Informações adicionais que aparecerão na Nota Fiscal..."><?= htmlspecialchars(getValue($produto, 'descricao_add_nfe')) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../produtos.php'">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Estilos base */
* {
    box-sizing: border-box;
}

.main-content {
    padding: 0;
    background: #f8fafc;
    min-height: 100vh;
}

.content-area {
    margin-top: 30px !important;
    max-width: 1443px;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.page-header {
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
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
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #374151;
}

/* Container do formulário */
.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* Seções do formulário */
.form-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.form-section:last-child {
    border-bottom: none;
}

.section-header {
    margin-bottom: 24px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #fcd34d;
    font-size: 16px;
}

/* Linhas e grupos de formulário */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row:last-child {
    margin-bottom: 0;
}

.form-group {
    flex: 1;
}

.form-group-wide {
    flex: 2;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-label i {
    color: #fcd34d;
    font-size: 12px;
}

.form-label.required::after {
    content: '*';
    color: #ef4444;
    margin-left: 4px;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    background: white;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: #f0fdf4;
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: #9ca3af;
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* Campo calculado automaticamente */
.calculated-field {
    background: #f8fafc !important;
    border-color: #10b981 !important;
    color: #059669 !important;
    font-weight: 600;
}

.calculated-field:focus {
    background: #f0fdf4 !important;
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* Toggle Switch */
.toggle-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #10b981;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

/* Seção de upload de foto */
.photo-upload-section {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

.current-photo {
    flex: 0 0 200px;
}

.photo-preview {
    position: relative;
    width: 200px;
    height: 200px;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-loading {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    color: #6b7280;
    gap: 8px;
}

.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.photo-preview:hover .photo-overlay {
    opacity: 1;
}

.btn-photo-action {
    background: #ef4444;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-photo-action:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.no-photo-placeholder {
    width: 200px;
    height: 200px;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 14px;
    gap: 8px;
}

.no-photo-placeholder i {
    font-size: 32px;
}

.photo-upload {
    flex: 1;
}

.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fafafa;
}

.upload-area:hover {
    border-color: #fcd34d;
    background: #f0fdf4;
}

.upload-area.dragover {
    border-color: #fcd34d;
    background: #f0fdf4;
    transform: scale(1.02);
}

.upload-content i {
    font-size: 48px;
    color: #fcd34d;
    margin-bottom: 16px;
}

.upload-content h4 {
    font-size: 18px;
    font-weight: 600;
    color: #374151;
    margin: 0 0 8px 0;
}

.upload-content p {
    font-size: 14px;
    color: #6b7280;
    margin: 0 0 8px 0;
}

.upload-content small {
    font-size: 12px;
    color: #9ca3af;
}

.photo-preview-new {
    position: relative;
    margin-top: 20px;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
}

.photo-preview-new img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.btn-remove-preview {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-remove-preview:hover {
    background: #dc2626;
    transform: scale(1.1);
}

/* Ações do formulário */
.form-actions {
    padding: 24px 32px;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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
    border-left: 4px solid #fcd34d;
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

/* Animações */
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
    
    .form-section {
        padding: 24px 20px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 16px;
    }
    
    .photo-upload-section {
        flex-direction: column;
        gap: 20px;
    }
    
    .current-photo {
        flex: none;
        align-self: center;
    }
    
    .form-actions {
        flex-direction: column;
        padding: 20px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .title-main {
        font-size: 20px;
    }
}
</style>


<script>
// Variáveis globais
let fotoRemovida = false;

document.addEventListener('DOMContentLoaded', function() {
    // Carregar foto atual se existir
    <?php if (getValue($produto, 'tem_foto')): ?>
        loadCurrentPhoto();
    <?php endif; ?>
    
    // Toggle switch
    const toggleInput = document.getElementById('ativo');
    const toggleLabel = document.getElementById('toggle-label');
    
    toggleInput.addEventListener('change', function() {
        toggleLabel.textContent = this.checked ? 'Produto Ativo' : 'Produto Inativo';
    });
    
    // Upload de foto
    const uploadArea = document.getElementById('upload-area');
    const fotoInput = document.getElementById('foto');
    const photoPreview = document.getElementById('photo-preview');
    const previewImg = document.getElementById('preview-img');
    
    // Click para selecionar arquivo
    uploadArea.addEventListener('click', () => {
        fotoInput.click();
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    // Mudança no input de arquivo
    fotoInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
    
    // Submissão do formulário
    document.getElementById('formEditarProduto').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarProduto();
    });
    
    // Cálculos automáticos
    setupCalculations();
});

<?php if (getValue($produto, 'tem_foto')): ?>
async function loadCurrentPhoto() {
    try {
        const response = await fetch(`../api_produtos/buscar_foto.php?id=<?= $produto['id'] ?>`);
        const data = await response.json();
        
        if (data.success && data.has_photo) {
            const img = document.getElementById('current-photo-img');
            const loading = document.querySelector('.photo-loading');
            
            img.src = data.photo_data;
            img.style.display = 'block';
            loading.style.display = 'none';
        } else {
            document.querySelector('.photo-loading').innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>Erro ao carregar foto</span>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar foto atual:', error);
        document.querySelector('.photo-loading').innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <span>Erro ao carregar foto</span>
        `;
    }
}
<?php endif; ?>

function setupCalculations() {
    // Cálculo automático do custo total
    ['Vrunit', 'Frete'].forEach(fieldName => {
        const field = document.querySelector(`input[name="${fieldName}"]`);
        if (field) {
            field.addEventListener('input', function() {
                const vrunit = parseFloat(document.querySelector('input[name="Vrunit"]').value) || 0;
                const frete = parseFloat(document.querySelector('input[name="Frete"]').value) || 0;
                const custototal = vrunit + frete;
                document.querySelector('input[name="custototal"]').value = custototal.toFixed(2);
            });
        }
    });
    
    // Cálculo automático do valor de venda baseado no custo e margem
    const percMbField = document.querySelector('input[name="perc_mb"]');
    if (percMbField) {
        percMbField.addEventListener('input', function() {
            const custototal = parseFloat(document.querySelector('input[name="custototal"]').value) || 0;
            const percMb = parseFloat(this.value) || 0;
            
            if (custototal > 0 && percMb > 0) {
                const vrvenda = custototal + (custototal * percMb / 100);
                document.querySelector('input[name="Vrvenda"]').value = vrvenda.toFixed(2);
                
                // Recalcular preço mínimo quando valor de venda muda
                calculateMinPrice();
            }
        });
    }
    
    // Cálculo automático do valor à vista
    const percAvistaField = document.querySelector('input[name="perc_avista"]');
    if (percAvistaField) {
        percAvistaField.addEventListener('input', function() {
            const vrvenda = parseFloat(document.querySelector('input[name="Vrvenda"]').value) || 0;
            const percAvista = parseFloat(this.value) || 0;
            
            if (vrvenda > 0 && percAvista > 0) {
                const vravista = vrvenda - (vrvenda * percAvista / 100);
                document.querySelector('input[name="Vravista"]').value = vravista.toFixed(2);
            }
        });
    }
    
    // Cálculo automático do valor promocional
    const descPromocaoField = document.querySelector('input[name="descpromocao"]');
    if (descPromocaoField) {
        descPromocaoField.addEventListener('input', function() {
            const vrvenda = parseFloat(document.querySelector('input[name="Vrvenda"]').value) || 0;
            const descPromo = parseFloat(this.value) || 0;
            
            if (vrvenda > 0 && descPromo > 0) {
                const vrpromocao = vrvenda - (vrvenda * descPromo / 100);
                document.querySelector('input[name="vrpromocao"]').value = vrpromocao.toFixed(2);
            }
        });
    }
    
    // NOVO: Cálculo automático do preço mínimo baseado no desconto máximo
    const maxDescField = document.querySelector('input[name="MAXDESC"]');
    const vrvendaField = document.querySelector('input[name="Vrvenda"]');
    
    if (maxDescField) {
        maxDescField.addEventListener('input', calculateMinPrice);
    }
    
    if (vrvendaField) {
        vrvendaField.addEventListener('input', calculateMinPrice);
    }
    
    // Calcular preço mínimo inicial se já houver valores
    calculateMinPrice();
}

// NOVA FUNÇÃO: Calcular preço mínimo automaticamente
function calculateMinPrice() {
    const vrvenda = parseFloat(document.querySelector('input[name="Vrvenda"]').value) || 0;
    const maxDesc = parseFloat(document.querySelector('input[name="MAXDESC"]').value) || 0;
    
    if (vrvenda > 0 && maxDesc > 0) {
        // Fórmula: Preço Mínimo = Valor de Venda - (Valor de Venda × Desconto Máximo ÷ 100)
        const minpreco = vrvenda - (vrvenda * maxDesc / 100);
        document.querySelector('input[name="MINPRECO"]').value = minpreco.toFixed(2);
        
        // Adicionar efeito visual para mostrar que foi calculado
        const minprecoField = document.querySelector('input[name="MINPRECO"]');
        minprecoField.style.background = '#f0fdf4';
        minprecoField.style.borderColor = '#10b981';
        
        setTimeout(() => {
            minprecoField.style.background = '#f8fafc';
        }, 1000);
    } else if (vrvenda > 0 && maxDesc === 0) {
        // Se não há desconto máximo, o preço mínimo é igual ao valor de venda
        document.querySelector('input[name="MINPRECO"]').value = vrvenda.toFixed(2);
    }
}

function handleFileSelect(file) {
    // Validar tipo de arquivo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showToast('Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WEBP.', 'error');
        return;
    }
    
    // Validar tamanho (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showToast('Arquivo muito grande. Máximo 5MB.', 'error');
        return;
    }
    
    // Mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('preview-img').src = e.target.result;
        document.getElementById('photo-preview').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function removerPreview() {
    document.getElementById('photo-preview').style.display = 'none';
    document.getElementById('foto').value = '';
}

function removerFoto() {
    if (confirm('Tem certeza que deseja remover a foto atual?')) {
        fotoRemovida = true;
        document.getElementById('current-photo').style.display = 'none';
        document.getElementById('no-photo-placeholder').style.display = 'flex';
        showToast('Foto marcada para remoção. Salve as alterações para confirmar.', 'warning');
    }
}

async function salvarProduto() {
    const form = document.getElementById('formEditarProduto');
    const formData = new FormData(form);
    
    // Adicionar flag de remoção de foto
    if (fotoRemovida) {
        formData.append('remover_foto', '1');
    }
    
    // Converter checkbox para S/N
    const ativo = document.getElementById('ativo').checked ? 'S' : 'N';
    formData.set('ativo', ativo);
    
    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../api_produtos/atualizar_produto.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Produto atualizado com sucesso!', 'success');
            
            // Redirecionar após um delay
            setTimeout(() => {
                window.location.href = '../produtos.php';
            }, 1500);
        } else {
            showToast('Erro ao atualizar produto: ' + data.message, 'error');
        }
    } catch (error) {
        showToast('Erro na requisição: ' + error.message, 'error');
    } finally {
        // Restaurar botão
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'check-circle' : 
                type === 'error' ? 'exclamation-circle' : 
                type === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
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
</script>