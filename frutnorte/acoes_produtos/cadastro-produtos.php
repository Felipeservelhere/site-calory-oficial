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

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com DB operacional.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de dados: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// Buscar todos os grupos (filtrado por empresa)
$stmt = $pdo->prepare("SELECT codgrupo, nome, perc_mb, perc_avista FROM grupos WHERE idcliente = ? ORDER BY nome ASC");
$stmt->execute([$idcliente_empresa]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se há um novo grupo para selecionar (parâmetro da URL)
$novogrupo = isset($_GET['novogrupo']) ? $_GET['novogrupo'] : null;
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Produtos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="main-content">
    <div class="content-wrapper">
        
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
                <span class="breadcrumb-item active">Cadastro de Produtos</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Cadastro de Produto</span>
                    <p class="title-subtitle">Cadastre seus produtos com todas as informações</p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>
        <div class="form-container">
            <form id="produtoForm" class="product-form" enctype="multipart/form-data">
                
                <div class="main-section">
                    <div class="section-header-with-photo">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-box"></i>
                                Informações Básicas
                            </h2>
                            <p class="section-subtitle">Dados essenciais do produto</p>
                        </div>
                        
                        <div class="photo-upload-area">
                            <div class="photo-preview" id="photo-preview">
                                <i class="fas fa-camera"></i>
                                <span>Clique para adicionar foto</span>
                            </div>
                            <input type="file" id="foto" name="foto" class="photo-input" accept="image/*">
                            <div class="photo-info" id="photo-info" style="display: none;">
                                <small class="photo-filename"></small>
                                <button type="button" class="remove-photo" onclick="removePhoto()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group-wide">
                            <!-- Nome do Produto -->
                            <div class="form-group">
                                <label for="nome" class="form-label">
                                    Nome do Produto <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="text" id="nome" name="nome" class="form-input" maxlength="65" required>
                                    <i class="fas fa-tag input-icon"></i>
                                </div>
                            </div>

                            <!-- Descrição Reduzida -->
                            <div class="form-group">
                                <label for="descricao_reduzida" class="form-label">Descrição Reduzida</label>
                                <div class="input-wrapper">
                                    <input type="text" id="descricao_reduzida" name="descricao_reduzida" class="form-input" maxlength="30">
                                    <i class="fas fa-align-left input-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Un" class="form-label">Unidade</label>
                            <div class="input-wrapper">
                                <select id="Un" name="Un" class="form-input">
                                    <option value="">Selecione...</option>
                                    <option value="UN">Unidade</option>
                                    <option value="KG">Quilograma</option>
                                    <option value="G">Grama</option>
                                    <option value="L">Litro</option>
                                    <option value="ML">Mililitro</option>
                                    <option value="M">Metro</option>
                                    <option value="CM">Centímetro</option>
                                    <option value="M2">Metro Quadrado</option>
                                    <option value="M3">Metro Cúbico</option>
                                    <option value="PC">Peça</option>
                                    <option value="PAR">Par</option>
                                    <option value="CX">Caixa</option>
                                    <option value="PCT">Pacote</option>
                                </select>
                                <i class="fas fa-ruler input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="variedade" class="form-label">Variedade</label>
                            <div class="input-wrapper">
                                <input type="text" id="variedade" name="variedade" class="form-input" maxlength="30">
                                <i class="fas fa-layer-group input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="codgrupo" class="form-label">Grupo</label>
                            <div class="input-wrapper" style="position: relative; display: flex; align-items: center; gap: 8px;">
                                <!-- Ícone à esquerda no select -->
                                <i class="fas fa-list input-icon" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; z-index: 1;"></i>

                                <!-- Select de grupos -->
                                <select id="codgrupo" name="codgrupo" class="form-input" style="padding-left: 44px; flex: 1;">
                                    <option value="">Selecione um grupo...</option>
                                    <?php foreach($grupos as $grupo): 
                                        $selected = ($novogrupo && $novogrupo == $grupo['codgrupo']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $grupo['codgrupo']; ?>" 
                                                data-mb="<?php echo $grupo['perc_mb']; ?>" 
                                                data-desconto="<?php echo $grupo['perc_avista']; ?>"
                                                <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($grupo['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <!-- Botão + para novo grupo -->
                                <button type="button" class="btn-add-group" onclick="abrirModalGrupo()" title="Criar novo grupo">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <!-- Container compacto para os percentuais -->
                            <div id="percentuais-container" class="percentuais-compact">
                                <div class="percentuais-badge">
                                    <span class="percent-badge mb-badge">
                                        <i class="icon-chart"></i>
                                        <span class="percent-text">MB: <strong id="mb-value">0%</strong></span>
                                    </span>
                                    <span class="percent-badge discount-badge">
                                        <i class="icon-tag"></i>
                                        <span class="percent-text">Desc: <strong id="discount-value">0%</strong></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="CodigoBarra" class="form-label">Código de Barras</label>
                            <div class="input-wrapper">
                                <input type="text" id="CodigoBarra" name="CodigoBarra" class="form-input" maxlength="13">
                                <i class="fas fa-barcode input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sections-grid">
                    
                    <div class="section-card" data-modal="precos">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Preços e Custos</h3>
                                <p class="card-subtitle">Valores e margens</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="precos-status">Pendente</span>
                            </div>
                        </div>
                        <div class="card-preview" id="precos-preview">
                            <span class="preview-text">Clique para configurar preços</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="estoque">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-warehouse"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Estoque</h3>
                                <p class="card-subtitle">Controle de inventário</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="estoque-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="estoque-preview">
                            <span class="preview-text">Clique para configurar estoque</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="fiscal">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Dados Fiscais</h3>
                                <p class="card-subtitle">NCM, CEST, Origem</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="fiscal-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="fiscal-preview">
                            <span class="preview-text">Clique para configurar dados fiscais</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="promocao">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-percent"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Promoções</h3>
                                <p class="card-subtitle">Descontos especiais</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="promocao-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="promocao-preview">
                            <span class="preview-text">Clique para configurar promoções</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="configuracoes">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Configurações</h3>
                                <p class="card-subtitle">Opções gerais</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="configuracoes-status">Padrão</span>
                            </div>
                        </div>
                        <div class="card-preview" id="configuracoes-preview">
                            <span class="preview-text">Configurações padrão aplicadas</span>
                        </div>
                    </div>
                </div>

                        <div class="obs-section">
                    <label for="descricao_add_nfe" class="form-label">
                        <i class="fas fa-file-alt"></i>
                        Descrição Adicional para NFe
                    </label>
                    <textarea id="descricao_add_nfe" name="descricao_add_nfe" class="form-textarea" rows="3" placeholder="Informações adicionais que aparecerão na Nota Fiscal..."></textarea>
                </div>


                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="limparFormulario()">
                        <i class="fas fa-eraser"></i>
                        Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Produto
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Preços -->
        <div id="modal-precos" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-dollar-sign"></i> Preços e Custos</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="Vrunit" class="form-label">Valor Unitário (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Vrunit" name="Vrunit" class="form-input" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Frete" class="form-label">Frete (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Frete" name="Frete" class="form-input" step="0.01" min="0">
                                <i class="fas fa-truck input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="custototal" class="form-label">Custo Total (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="custototal" name="custototal" class="form-input" step="0.01" min="0">
                                <i class="fas fa-calculator input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="perc_mb" class="form-label">% Margem Bruta</label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_mb" name="perc_mb" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Vrvenda" class="form-label">Valor de Venda (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Vrvenda" name="Vrvenda" class="form-input" step="0.01" min="0">
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="perc_avista" class="form-label">% Desconto à Vista</label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_avista" name="perc_avista" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Vravista" class="form-label">Valor à Vista (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Vravista" name="Vravista" class="form-input" step="0.01" min="0">
                                <i class="fas fa-money-bill input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="MAXDESC" class="form-label">Desconto Máximo (%)</label>
                            <div class="input-wrapper">
                                <input type="number" id="MAXDESC" name="MAXDESC" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="MINPRECO" class="form-label">Preço Mínimo (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="MINPRECO" name="MINPRECO" class="form-input" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="precos">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Estoque -->
        <div id="modal-estoque" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-warehouse"></i> Controle de Estoque</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="estoque" class="form-label">Controla Estoque?</label>
                            <div class="input-wrapper">
                                <select id="estoque" name="estoque" class="form-input">
                                    <option value="S">Sim</option>
                                    <option value="N">Não</option>
                                </select>
                                <i class="fas fa-toggle-on input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="saldo_estoque" class="form-label">Saldo Atual</label>
                            <div class="input-wrapper">
                                <input type="number" id="saldo_estoque" name="saldo_estoque" class="form-input" step="0.01" min="0">
                                <i class="fas fa-boxes input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estoqueminimo" class="form-label">
                                Estoque Mínimo
                                <i class="fas fa-info-circle info-icon" title="Quando o produto atingir esta quantidade, será disparado um alerta para avisar que o produto está em quantidade baixa"></i>
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="estoqueminimo" name="estoqueminimo" class="form-input" step="0.01" min="0">
                                <i class="fas fa-exclamation-triangle input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Pesobruto" class="form-label">Peso Bruto (Kg)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Pesobruto" name="Pesobruto" class="form-input" step="0.001" min="0">
                                <i class="fas fa-weight input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Pesoliquido" class="form-label">Peso Líquido (Kg)</label>
                            <div class="input-wrapper">
                                <input type="number" id="Pesoliquido" name="Pesoliquido" class="form-input" step="0.001" min="0" value="1">
                                <i class="fas fa-weight-hanging input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="lote" class="form-label">Controla Lote?</label>
                            <div class="input-wrapper">
                                <select id="lote" name="lote" class="form-input">
                                    <option value="S">Sim</option>
                                    <option value="N">Não</option>
                                </select>
                                <i class="fas fa-list-ol input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="estoque">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Fiscal -->
        <div id="modal-fiscal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-receipt"></i> Informações Fiscais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="NCM" class="form-label">NCM</label>
                            <div class="input-wrapper">
                                <input type="text" id="NCM" name="NCM" class="form-input" maxlength="8" placeholder="00000000">
                                <i class="fas fa-file-invoice input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cest" class="form-label">CEST</label>
                            <div class="input-wrapper">
                                <input type="text" id="cest" name="cest" class="form-input" maxlength="7" placeholder="0000000">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="origem" class="form-label">Origem</label>
                            <div class="input-wrapper">
                                <select id="origem" name="origem" class="form-input">
                                    <option value="">Selecione...</option>
                                    <option value="0">0 - Nacional</option>
                                    <option value="1">1 - Estrangeira - Importação direta</option>
                                    <option value="2">2 - Estrangeira - Adquirida no mercado interno</option>
                                    <option value="3">3 - Nacional - Mercadoria com Conteúdo de Importação superior a 40%</option>
                                    <option value="4">4 - Nacional - Produção em conformidade com processos produtivos básicos</option>
                                    <option value="5">5 - Nacional - Mercadoria com Conteúdo de Importação inferior ou igual a 40%</option>
                                    <option value="6">6 - Estrangeira - Importação direta, sem similar nacional</option>
                                    <option value="7">7 - Estrangeira - Adquirida no mercado interno, sem similar nacional</option>
                                    <option value="8">8 - Nacional - Mercadoria com Conteúdo de Importação superior a 70%</option>
                                </select>
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="codst" class="form-label">Código ST</label>
                            <div class="input-wrapper">
                                <input type="text" id="codst" name="codst" class="form-input" maxlength="1">
                                <i class="fas fa-code input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="un_trib" class="form-label">Unidade Tributável</label>
                            <div class="input-wrapper">
                                <input type="text" id="un_trib" name="un_trib" class="form-input" maxlength="3">
                                <i class="fas fa-ruler input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="conversor_trib" class="form-label">Conversor Tributável</label>
                            <div class="input-wrapper">
                                <input type="number" id="conversor_trib" name="conversor_trib" class="form-input" step="0.0001" min="0">
                                <i class="fas fa-exchange-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="beneficio" class="form-label">Benefício Fiscal</label>
                            <div class="input-wrapper">
                                <input type="text" id="beneficio" name="beneficio" class="form-input" maxlength="10">
                                <i class="fas fa-gift input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="NP" class="form-label">Nota do Produtor</label>
                            <div class="input-wrapper">
                                <select id="NP" name="NP" class="form-input">
                                    <option value="N">Não</option>
                                    <option value="S">Sim</option>
                                </select>
                                <i class="fas fa-user-tie input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="fiscal">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Promoção -->
        <div id="modal-promocao" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-percent"></i> Promoções e Descontos</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="promocao" class="form-label">Produto em Promoção?</label>
                            <div class="input-wrapper">
                                <select id="promocao" name="promocao" class="form-input">
                                    <option value="N">Não</option>
                                    <option value="S">Sim</option>
                                </select>
                                <i class="fas fa-tags input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descpromocao" class="form-label">% Desconto Promoção</label>
                            <div class="input-wrapper">
                                <input type="number" id="descpromocao" name="descpromocao" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="vrpromocao" class="form-label">Valor Promocional (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="vrpromocao" name="vrpromocao" class="form-input" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="promocao">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Configurações -->
        <div id="modal-configuracoes" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-cogs"></i> Configurações Gerais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="ativo" class="form-label">Status do Produto</label>
                            <div class="input-wrapper">
                                <select id="ativo" name="ativo" class="form-input">
                                    <option value="S">Ativo</option>
                                    <option value="N">Inativo</option>
                                </select>
                                <i class="fas fa-toggle-on input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="envia_site" class="form-label">Enviar para Site?</label>
                            <div class="input-wrapper">
                                <select id="envia_site" name="envia_site" class="form-input">
                                    <option value="N">Não</option>
                                    <option value="S">Sim</option>
                                </select>
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="insumos" class="form-label">É Insumo?</label>
                            <div class="input-wrapper">
                                <select id="insumos" name="insumos" class="form-input">
                                    <option value="N">Não</option>
                                    <option value="S">Sim</option>
                                </select>
                                <i class="fas fa-cog input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="configuracoes">Salvar</button>
                </div>
            </div>
        </div>



        <!-- Modal Criar Grupo -->
<div id="modal-grupo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> Criar Novo Grupo</h3>
            <button class="modal-close">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="novo_grupo_nome" class="form-label">
                    Nome do Grupo <span class="required">*</span>
                </label>
                <div class="input-wrapper">
                    <input type="text" id="novo_grupo_nome" class="form-input" maxlength="30" placeholder="Digite o nome do grupo">
                    <i class="fas fa-folder input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <label for="novo_grupo_perc_mb" class="form-label">% Margem Bruta Padrão</label>
                <div class="input-wrapper">
                    <input type="number" id="novo_grupo_perc_mb" class="form-input" step="0.01" min="0" max="100" placeholder="Opcional">
                    <i class="fas fa-percentage input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <label for="novo_grupo_perc_avista" class="form-label">% Desconto à Vista Padrão</label>
                <div class="input-wrapper">
                    <input type="number" id="novo_grupo_perc_avista" class="form-input" step="0.01" min="0" max="100" placeholder="Opcional">
                    <i class="fas fa-percentage input-icon"></i>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarNovoGrupo()">
                <i class="fas fa-save"></i>
                Salvar Grupo
            </button>
        </div>
    </div>
</div>

        <input type="hidden" id="id" name="id">
        <input type="hidden" id="idcliente" name="idcliente" value="<?php echo $idcliente_empresa; ?>">
        <input type="hidden" id="codproduto" name="codproduto">

                


                <!-- Campos hidden -->
                <input type="hidden" id="id" name="id">
                <input type="hidden" id="idcliente" name="idcliente" value="<?php echo $idcliente_empresa; ?>">
                <input type="hidden" id="codproduto" name="codproduto">

                <!-- Modais permanecem iguais (preços, estoque, etc.) -->
                <!-- ... (código dos modais permanece igual) ... -->

                <!-- Modal Criar Grupo (permanece igual, mas ajustado para o novo botão) -->
                <div id="modal-grupo" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><i class="fas fa-folder-plus"></i> Criar Novo Grupo</h3>
                            <button class="modal-close">×</button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="novo_grupo_nome" class="form-label">
                                    Nome do Grupo <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo_grupo_nome" class="form-input" maxlength="30" placeholder="Digite o nome do grupo">
                                    <i class="fas fa-folder input-icon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="novo_grupo_perc_mb" class="form-label">% Margem Bruta Padrão</label>
                                <div class="input-wrapper">
                                    <input type="number" id="novo_grupo_perc_mb" class="form-input" step="0.01" min="0" max="100" placeholder="Opcional">
                                    <i class="fas fa-percentage input-icon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="novo_grupo_perc_avista" class="form-label">% Desconto à Vista Padrão</label>
                                <div class="input-wrapper">
                                    <input type="number" id="novo_grupo_perc_avista" class="form-input" step="0.01" min="0" max="100" placeholder="Opcional">
                                    <i class="fas fa-percentage input-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="salvarNovoGrupo()">
                                <i class="fas fa-save"></i>
                                Salvar Grupo
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Estilos CSS (adicionando estilos para o novo botão) -->
<style>
/* ============ RESET E BASE ============ */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.main-content {
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;
}

/* Estilo para campos calculados automaticamente */
.campo-calculado {
    background-color: #f0f9ff !important;
    border-color: #bae6fd !important;
}

.campo-calculado:focus {
    background-color: #e0f2fe !important;
    border-color: #7dd3fc !important;
}

/* Indicador visual para campos calculados */
.input-wrapper.calculado::after {
    content: '🔄';
    position: absolute;
    right: 10px;
    color: #0ea5e9;
    font-size: 12px;
}

/* Tooltip para campos calculados */
.info-calculado {
    position: relative;
    display: inline-block;
    margin-left: 5px;
    cursor: help;
}

.info-calculado .tooltip-text {
    visibility: hidden;
    width: 200px;
    background-color: #1e293b;
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 12px;
    font-weight: normal;
}

.info-calculado:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

.content-wrapper {
    margin-top: 10px !important;
    max-width: 1443px;
    margin: 0 auto;
    padding: 20px;
}

/* ============ HEADER ============ */
.page-header {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    padding: 27px 32px;
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
    margin-bottom: 12px;
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
    color: #dbeafe;
    transform: translateY(-1px);
}

.breadcrumb-separator {
    margin: 0 8px;
    opacity: 0.7;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    margin: 0;
    position: relative;
    z-index: 1;
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

/* ============ FORM CONTAINER ============ */
.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* ============ MAIN SECTION ============ */
.main-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.section-header-with-photo {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    gap: 24px;
}

.section-header {
    flex: 1;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #3b82f6;
}

.section-subtitle {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

/* ============ FORM GRID ============ */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    align-items: start;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group-wide {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.required {
    color: #ef4444;
    font-weight: 700;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 12px 16px 12px 44px;
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
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: #f0f9ff;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
}

/* ============ GRUPO COM BOTÃO + ============ */
.form-group-grupo .input-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group-grupo .input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
}

.form-group-grupo #codgrupo {
    flex: 1;
    padding-left: 44px;
}

.btn-add-group {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.btn-add-group:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

/* ============ PERCENTUAIS ============ */
.percentuais-compact {
    display: none;
    margin-top: 8px;
}

.percentuais-badge {
    display: inline-flex;
    gap: 8px;
    align-items: center;
}

.percent-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid;
}

.mb-badge {
    background: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
    border-color: rgba(46, 125, 50, 0.2);
}

.discount-badge {
    background: rgba(239, 108, 0, 0.1);
    color: #ef6c00;
    border-color: rgba(239, 108, 0, 0.2);
}

/* ============ PHOTO UPLOAD ============ */
.photo-upload-area {
    position: relative;
    flex-shrink: 0;
}

.photo-preview {
    width: 120px;
    height: 120px;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
    color: #6b7280;
    font-size: 12px;
    text-align: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.photo-preview:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
    color: #3b82f6;
}

.photo-preview.has-image {
    border-style: solid;
    border-color: #10b981;
    background: #f0fdf4;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}

.photo-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.photo-info {
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 8px;
    background: #f3f4f6;
    border-radius: 6px;
    font-size: 11px;
}

/* ============ SECTIONS GRID ============ */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.section-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.section-card:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.card-info {
    flex: 1;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 2px 0;
}

.card-subtitle {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #fef3c7;
    color: #92400e;
}

.card-preview {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.4;
}

/* ============ OBS SECTION ============ */
.obs-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.obs-section .form-label {
    font-size: 16px;
    margin-bottom: 8px;
}

.form-textarea {
    padding-left: 16px;
    resize: vertical;
    min-height: 80px;
}

/* ============ FORM ACTIONS ============ */
.form-actions {
    padding: 24px 32px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn {
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
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #374151;
}

/* ============ MODAIS ============ */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    margin: 20px;
}

.modal-header {
    padding: 24px 32px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.modal-body {
    padding: 32px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px 32px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8fafc;
}

/* ============ TOAST ============ */
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
}

.toast.error {
    border-left-color: #ef4444;
}

.toast.success {
    border-left-color: #10b981;
}

/* ============ RESPONSIVIDADE ============ */
@media (max-width: 768px) {
    .main-content {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px 24px;
    }
    
    .section-header-with-photo {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .photo-upload-area {
        order: -1;
        margin-bottom: 16px;
    }
    
    .sections-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group-wide {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .modal-content {
        width: 95%;
        margin: 10px;
    }
    
    .form-group-grupo .input-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-add-group {
        width: 100%;
    }
}

/* Loading states */
.btn.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn.loading::after {
    content: '';
    width: 14px;
    height: 14px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
// ============================================
// FUNÇÕES GLOBAIS E AUXILIARES
// ============================================

// Função para mostrar toast (global)
let showToast;
function defineShowToast() {
    showToast = function(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'exclamation-circle' : 'info-circle';
        
        toast.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => {
                if (toast.parentNode === container) {
                    container.removeChild(toast);
                }
            }, 300);
        }, 4000);
    };
}

// Função para atualizar percentuais pelo select
function atualizarPercentuais() {
    const select = document.getElementById('codgrupo');
    const container = document.getElementById('percentuais-container');
    const mbValue = document.getElementById('mb-value');
    const discountValue = document.getElementById('discount-value');
    
    if (select.value === '') {
        container.style.display = 'none';
        container.classList.remove('active');
        return;
    }
    
    const selectedOption = select.options[select.selectedIndex];
    const percMB = selectedOption.getAttribute('data-mb') || '0';
    const percDesconto = selectedOption.getAttribute('data-desconto') || '0';
    
    // Atualiza valores
    mbValue.textContent = parseFloat(percMB).toFixed(1) + '%';
    discountValue.textContent = parseFloat(percDesconto).toFixed(1) + '%';
    
    // Mostra container
    container.style.display = 'inline-flex';
    container.classList.add('active');
    
    // Atualiza cores
    updateBadgeColors(parseFloat(percMB), parseFloat(percDesconto));
}

// Função para atualizar cores dos badges
function updateBadgeColors(mb, desconto) {
    const mbElement = document.querySelector('.mb-badge');
    const discountElement = document.querySelector('.discount-badge');
    
    // Reset cores
    mbElement.style.background = 'rgba(46, 125, 50, 0.1)';
    mbElement.style.color = '#2e7d32';
    discountElement.style.background = 'rgba(239, 108, 0, 0.1)';
    discountElement.style.color = '#ef6c00';
    
    // Destaques condicionais
    if (mb > 40) {
        mbElement.style.background = 'rgba(46, 125, 50, 0.2)';
    } else if (mb < 15) {
        mbElement.style.background = 'rgba(244, 67, 54, 0.1)';
        mbElement.style.color = '#f44336';
    }
    
    if (desconto > 20) {
        discountElement.style.background = 'rgba(239, 108, 0, 0.2)';
    } else if (desconto < 5) {
        discountElement.style.background = 'rgba(156, 39, 176, 0.1)';
        discountElement.style.color = '#9c27b0';
    }
}

// Função para atualizar valores percentuais manualmente
function atualizarValoresPercentuais(percMB, percDesconto) {
    const container = document.getElementById('percentuais-container');
    const mbValue = document.getElementById('mb-value');
    const discountValue = document.getElementById('discount-value');

    // Atualiza textos
    mbValue.textContent = parseFloat(percMB).toFixed(1) + '%';
    discountValue.textContent = parseFloat(percDesconto).toFixed(1) + '%';

    // Mostra container
    container.style.display = 'inline-flex';

    // Atualiza cores
    updateBadgeColors(parseFloat(percMB), parseFloat(percDesconto));
}

// Função para adicionar novo grupo dinamicamente (sem reload)
function adicionarNovoGrupo(codgrupo, nome, perc_mb, perc_avista) {
    const select = document.getElementById('codgrupo');
    
    // Verifica se o grupo já existe (evita duplicatas)
    const existingOption = Array.from(select.options).find(option => option.value === codgrupo);
    if (existingOption) {
        showToast('Este grupo já existe na lista.', 'error');
        return;
    }
    
    // Cria novo option
    const newOption = new Option(nome, codgrupo);
    newOption.setAttribute('data-mb', perc_mb || '0');
    newOption.setAttribute('data-desconto', perc_avista || '0');
    
    // Insere no final da lista de opções
    select.appendChild(newOption);
    
    // Seleciona automaticamente o novo grupo
    select.value = codgrupo;
    
    // Atualiza os percentuais automaticamente
    atualizarValoresPercentuais(perc_mb || '0', perc_avista || '0');
    
    showToast('Novo grupo adicionado com sucesso!', 'success');
}

// Função para abrir modal de grupo
function abrirModalGrupo() {
    // Limpa campos do modal
    document.getElementById('novo_grupo_nome').value = '';
    document.getElementById('novo_grupo_perc_mb').value = '';
    document.getElementById('novo_grupo_perc_avista').value = '';
    
    // Abre o modal
    const modal = document.getElementById('modal-grupo');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Foco no campo nome
    document.getElementById('novo_grupo_nome').focus();
}

// Função para fechar modal de grupo
function fecharModalGrupo() {
    const modal = document.getElementById('modal-grupo');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Função para salvar novo grupo (sem reload)
async function salvarNovoGrupo() {
    const nome = document.getElementById('novo_grupo_nome').value.trim();
    const perc_mb = document.getElementById('novo_grupo_perc_mb').value;
    const perc_avista = document.getElementById('novo_grupo_perc_avista').value;
    
    if (!nome) {
        showToast('Por favor, digite um nome para o grupo.', 'error');
        document.getElementById('novo_grupo_nome').focus();
        return;
    }
    
    // Validação simples para percentuais (opcional)
    const mbNum = parseFloat(perc_mb);
    const avistaNum = parseFloat(perc_avista);
    if ((perc_mb && (isNaN(mbNum) || mbNum < 0 || mbNum > 100)) || 
        (perc_avista && (isNaN(avistaNum) || avistaNum < 0 || avistaNum > 100))) {
        showToast('Os percentuais devem ser números entre 0 e 100.', 'error');
        return;
    }
    
    try {
        const response = await fetch('../api_produtos/salvar_grupo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nome: nome,
                perc_mb: perc_mb || null,
                perc_avista: perc_avista || null,
                idcliente: document.getElementById('idcliente').value
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Adiciona o grupo dinamicamente sem reload
            adicionarNovoGrupo(
                result.grupo.codgrupo, 
                result.grupo.nome, 
                result.grupo.perc_mb, 
                result.grupo.perc_avista
            );
            
            // Fecha o modal
            fecharModalGrupo();
            
            showToast(result.message || 'Grupo criado com sucesso!', 'success');
            
        } else {
            showToast(result.message || 'Erro ao criar grupo', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar grupo:', error);
        showToast('Erro de conexão com o servidor', 'error');
    }
}

// Funções para upload de foto
function handlePhotoUpload(e) {
    const file = e.target.files[0];
    const photoPreview = document.getElementById('photo-preview');
    const photoInfo = document.getElementById('photo-info');
    const photoFilename = photoInfo.querySelector('.photo-filename');
    
    if (file) {
        // Validar tipo de arquivo
        if (!file.type.startsWith('image/')) {
            showToast('Por favor, selecione apenas arquivos de imagem.', 'error');
            e.target.value = '';
            return;
        }
        
        // Validar tamanho (máximo 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showToast('A imagem deve ter no máximo 5MB.', 'error');
            e.target.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview da foto">`;
            photoPreview.classList.add('has-image');
            photoFilename.textContent = file.name;
            photoInfo.style.display = 'flex';
            showToast('Foto carregada com sucesso!');
        };
        reader.readAsDataURL(file);
    }
}

function removePhoto() {
    const photoPreview = document.getElementById('photo-preview');
    const photoInfo = document.getElementById('photo-info');
    const photoInput = document.getElementById('foto');
    
    photoPreview.innerHTML = `
        <i class="fas fa-camera"></i>
        <span>Clique para adicionar foto</span>
    `;
    photoPreview.classList.remove('has-image');
    photoInfo.style.display = 'none';
    photoInput.value = '';
    
    showToast('Foto removida!');
}

// Função global para limpar formulário
window.limparFormulario = function() {
    if (confirm('Tem certeza que deseja limpar todos os campos?')) {
        const form = document.getElementById('produtoForm');
        form.reset();
        initializeDefaults();
        
        // Limpar preview da foto
        removePhoto();
        
        // Limpar campos dos modais
        document.querySelectorAll('.modal input, .modal select, .modal textarea').forEach(field => {
            if (field.type !== 'button' && field.type !== 'file') {
                field.value = '';
            }
        });
        
        // Resetar previews dos cards
        const defaultTexts = {
            'precos': 'Clique para configurar preços',
            'estoque': 'Clique para configurar estoque',
            'fiscal': 'Clique para configurar dados fiscais',
            'promocao': 'Clique para configurar promoções',
            'configuracoes': 'Configurações padrão aplicadas'
        };
        
        Object.keys(defaultTexts).forEach(section => {
            const preview = document.getElementById(`${section}-preview`);
            if (preview) {
                preview.innerHTML = `<span class="preview-text">${defaultTexts[section]}</span>`;
            }
        });
        
        // Resetar status dos cards
        document.querySelectorAll('.status-badge').forEach(badge => {
            if (badge.id === 'precos-status') {
                badge.textContent = 'Pendente';
                badge.style.background = '#fef3c7';
                badge.style.color = '#92400e';
            } else if (badge.id === 'configuracoes-status') {
                badge.textContent = 'Padrão';
                badge.style.background = '#e0e7ff';
                badge.style.color = '#3730a3';
            } else {
                badge.textContent = 'Opcional';
                badge.style.background = '#e0e7ff';
                badge.style.color = '#3730a3';
            }
        });
        
        // Remover campos hidden criados dinamicamente
        const hiddenFields = form.querySelectorAll('input[type="hidden"]');
        hiddenFields.forEach(field => {
            if (!['id', 'idcliente', 'codproduto'].includes(field.name)) {
                field.remove();
            }
        });
        
        // Resetar percentuais
        document.getElementById('percentuais-container').style.display = 'none';
        
        showToast('Formulário limpo com sucesso!');
    }
};

// ============================================
// INICIALIZAÇÃO PRINCIPAL (DOMContentLoaded)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Define showToast globalmente
    defineShowToast();
    
    const form = document.getElementById('produtoForm');
    const sectionCards = document.querySelectorAll('.section-card');
    const modals = document.querySelectorAll('.modal');
    
    // Inicializar valores padrão
    initializeDefaults();
    
    // Event listeners para cards (abrir modais)
    sectionCards.forEach(card => {
        card.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // Event listeners para modais (fechar, salvar)
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        const cancelBtn = modal.querySelector('.modal-cancel');
        const saveBtns = modal.querySelectorAll('.modal-save');
        
        closeBtn?.addEventListener('click', () => closeModal(modal.id));
        cancelBtn?.addEventListener('click', () => closeModal(modal.id));
        
        saveBtns.forEach(saveBtn => {
            saveBtn.addEventListener('click', function() {
                const section = this.getAttribute('data-section');
                if (section) {
                    saveModalData(section);
                }
                closeModal(modal.id);
            });
        });
        
        // Fechar modal clicando fora
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Event listener para o select de grupos
    document.getElementById('codgrupo').addEventListener('change', atualizarPercentuais);
    
    // Inicializar percentuais se houver um grupo selecionado
    const codgrupoSelect = document.getElementById('codgrupo');
    if (codgrupoSelect.value !== '') {
        atualizarPercentuais();
    } else {
        document.getElementById('percentuais-container').style.display = 'none';
    }
    
    // Event listeners para photo upload
    const photoInput = document.getElementById('foto');
    const photoPreview = document.getElementById('photo-preview');
    photoInput.addEventListener('change', handlePhotoUpload);
    photoPreview.addEventListener('click', () => photoInput.click());
    
    // Cálculos automáticos
    setupCalculations();
    
    // Validação de formulário
    setupValidation();
    
    // Submit do formulário
    form.addEventListener('submit', handleFormSubmit);
    
    // Event listeners para modal de grupo
    const modalGrupo = document.getElementById('modal-grupo');
    if (modalGrupo) {
        const closeBtn = modalGrupo.querySelector('.modal-close');
        const cancelBtn = modalGrupo.querySelector('.modal-cancel');
        
        closeBtn?.addEventListener('click', fecharModalGrupo);
        cancelBtn?.addEventListener('click', fecharModalGrupo);
        
        // Fechar clicando fora do modal
        modalGrupo.addEventListener('click', function(e) {
            if (e.target === modalGrupo) {
                fecharModalGrupo();
            }
        });
        
        // Permitir salvar com Enter no campo nome
        const nomeInput = document.getElementById('novo_grupo_nome');
        if (nomeInput) {
            nomeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    salvarNovoGrupo();
                }
            });
        }
    }
    
    // ============================================
    // FUNÇÕES INTERNAS DO DOMContentLoaded
    // ============================================
    
    function initializeDefaults() {
        // Valores padrão para selects e inputs
        const defaults = {
            'estoque': 'S',
            'lote': 'N',  // Ajustado para 'N' se preferir, ou 'S' como original
            'NP': 'N',
            'promocao': 'N',
            'ativo': 'S',
            'envia_site': 'N',
            'insumos': 'N'
        };
        
        Object.keys(defaults).forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = defaults[id];
        });
        
        // Peso líquido padrão
        document.getElementById('Pesoliquido').value = '1';
    }
    
    function openModal(modalId) {
        const modal = document.getElementById(`modal-${modalId}`);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    function saveModalData(section) {
        updateFormData(section);
        updateCardPreview(section);
        updateCardStatus(section);
        showToast(`Dados de ${getSectionName(section)} salvos com sucesso!`);
    }
    
    function getSectionName(section) {
        const names = {
            'precos': 'Preços',
            'estoque': 'Estoque',
            'fiscal': 'Fiscais',
            'promocao': 'Promoção',
            'configuracoes': 'Configurações'
        };
        return names[section] || section;
    }
    
    function updateFormData(section) {
        const form = document.getElementById('produtoForm');
        
        switch(section) {
            case 'precos':
                updateHiddenField(form, 'Vrunit', document.getElementById('Vrunit').value);
                updateHiddenField(form, 'Frete', document.getElementById('Frete').value);
                updateHiddenField(form, 'custototal', document.getElementById('custototal').value);
                updateHiddenField(form, 'perc_mb', document.getElementById('perc_mb').value);
                updateHiddenField(form, 'Vrvenda', document.getElementById('Vrvenda').value);
                updateHiddenField(form, 'perc_avista', document.getElementById('perc_avista').value);
                updateHiddenField(form, 'Vravista', document.getElementById('Vravista').value);
                updateHiddenField(form, 'MAXDESC', document.getElementById('MAXDESC').value);
                updateHiddenField(form, 'MINPRECO', document.getElementById('MINPRECO').value);
                break;
                
            case 'estoque':
                updateHiddenField(form, 'estoque', document.getElementById('estoque').value);
                updateHiddenField(form, 'saldo_estoque', document.getElementById('saldo_estoque').value);
                updateHiddenField(form, 'estoqueminimo', document.getElementById('estoqueminimo').value);
                updateHiddenField(form, 'Pesobruto', document.getElementById('Pesobruto').value);
                updateHiddenField(form, 'Pesoliquido', document.getElementById('Pesoliquido').value);
                updateHiddenField(form, 'lote', document.getElementById('lote').value);
                break;
                
            case 'fiscal':
                updateHiddenField(form, 'NCM', document.getElementById('NCM').value);
                updateHiddenField(form, 'cest', document.getElementById('cest').value);
                updateHiddenField(form, 'origem', document.getElementById('origem').value);
                updateHiddenField(form, 'codst', document.getElementById('codst').value);
                updateHiddenField(form, 'un_trib', document.getElementById('un_trib').value);
                updateHiddenField(form, 'conversor_trib', document.getElementById('conversor_trib').value);
                updateHiddenField(form, 'beneficio', document.getElementById('beneficio').value);
                updateHiddenField(form, 'NP', document.getElementById('NP').value);
                break;
                
            case 'promocao':
                updateHiddenField(form, 'promocao', document.getElementById('promocao').value);
                updateHiddenField(form, 'descpromocao', document.getElementById('descpromocao').value);
                updateHiddenField(form, 'vrpromocao', document.getElementById('vrpromocao').value);
                break;
                
            case 'configuracoes':
                updateHiddenField(form, 'ativo', document.getElementById('ativo').value);
                updateHiddenField(form, 'envia_site', document.getElementById('envia_site').value);
                updateHiddenField(form, 'insumos', document.getElementById('insumos').value);
                break;
        }
    }
    
    function updateHiddenField(form, fieldName, value) {
        let field = form.querySelector(`[name="${fieldName}"]`);
        
        if (field) {
            field.value = value;
        } else {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = fieldName;
            field.id = fieldName;
            field.value = value;
            form.appendChild(field);
        }
    }
    
    function updateCardPreview(section) {
        const preview = document.getElementById(`${section}-preview`);
        let previewText = '';
        
        switch(section) {
            case 'precos':
                const vrunit = document.getElementById('Vrunit').value;
                const vrvenda = document.getElementById('Vrvenda').value;
                const vravista = document.getElementById('Vravista').value;
                const info = [];
                if (vrunit) info.push(`Custo: R$ ${vrunit}`);
                if (vrvenda) info.push(`Venda: R$ ${vrvenda}`);
                if (vravista) info.push(`À Vista: R$ ${vravista}`);
                previewText = info.length > 0 ? info.join(' • ') : 'Clique para configurar preços';
                break;
                
            case 'estoque':
                const controla = document.getElementById('estoque').value;
                const saldo = document.getElementById('saldo_estoque').value;
                const minimo = document.getElementById('estoqueminimo').value;
                previewText = `Controla: ${controla === 'S' ? 'Sim' : 'Não'}`;
                if (saldo) previewText += ` • Saldo: ${saldo}`;
                if (minimo) previewText += ` • Mín: ${minimo}`;
                break;
                
            case 'fiscal':
                const ncm = document.getElementById('NCM').value;
                const cest = document.getElementById('cest').value;
                const origem = document.getElementById('origem').value;
                const fiscal = [];
                if (ncm) fiscal.push(`NCM: ${ncm}`);
                if (cest) fiscal.push(`CEST: ${cest}`);
                if (origem) fiscal.push(`Origem: ${origem}`);
                previewText = fiscal.length > 0 ? fiscal.join(' • ') : 'Clique para configurar dados fiscais';
                break;
                
            case 'promocao':
                const emPromocao = document.getElementById('promocao').value;
                const descPromo = document.getElementById('descpromocao').value;
                const vrPromo = document.getElementById('vrpromocao').value;
                if (emPromocao === 'S') {
                    const promoInfo = [];
                    if (descPromo) promoInfo.push(`${descPromo}% desc`);
                    if (vrPromo) promoInfo.push(`R$ ${vrPromo}`);
                    previewText = promoInfo.length > 0 ? `Promoção: ${promoInfo.join(' • ')}` : 'Produto em promoção';
                } else {
                    previewText = 'Produto não está em promoção';
                }
                break;
                
            case 'configuracoes':
                const ativo = document.getElementById('ativo').value;
                const site = document.getElementById('envia_site').value;
                const insumo = document.getElementById('insumos').value;
                previewText = `Status: ${ativo === 'S' ? 'Ativo' : 'Inativo'} • Site: ${site === 'S' ? 'Sim' : 'Não'} • Insumo: ${insumo === 'S' ? 'Sim' : 'Não'}`;
                break;
        }
        
        preview.innerHTML = `<span class="preview-text">${previewText}</span>`;
    }
    
    function updateCardStatus(section) {
        const statusBadge = document.getElementById(`${section}-status`);
        statusBadge.textContent = 'Preenchido';
        statusBadge.style.background = '#d1fae5';
        statusBadge.style.color = '#065f46';
    }
    
    // ============================================
// FUNÇÕES PARA CÁLCULO AUTOMÁTICO DE PREÇOS
// ============================================

// Função para calcular preço mínimo baseado no desconto máximo
function calcularPrecoMinimo() {
    const vrvenda = parseFloat(document.getElementById('Vrvenda').value) || 0;
    const maxDesc = parseFloat(document.getElementById('MAXDESC').value) || 0;
    
    if (vrvenda > 0 && maxDesc > 0) {
        const minPreco = vrvenda - (vrvenda * maxDesc / 100);
        document.getElementById('MINPRECO').value = minPreco.toFixed(2);
    } else if (vrvenda > 0 && maxDesc === 0) {
        // Se desconto máximo for 0, preço mínimo = valor de venda
        document.getElementById('MINPRECO').value = vrvenda.toFixed(2);
    }
}

// Função para validar desconto máximo em relação ao preço mínimo
function validarDescontoMaximo() {
    const vrvenda = parseFloat(document.getElementById('Vrvenda').value) || 0;
    const minPreco = parseFloat(document.getElementById('MINPRECO').value) || 0;
    const maxDescInput = document.getElementById('MAXDESC');
    
    if (vrvenda > 0 && minPreco > 0) {
        const descontoCalculado = ((vrvenda - minPreco) / vrvenda) * 100;
        const descontoAtual = parseFloat(maxDescInput.value) || 0;
        
        // Se o preço mínimo foi alterado manualmente, atualiza o desconto máximo
        if (Math.abs(descontoCalculado - descontoAtual) > 0.01) {
            maxDescInput.value = descontoCalculado.toFixed(2);
        }
    }
}

// Função para calcular todos os valores relacionados
function calcularValoresPrecos() {
    const vrunit = parseFloat(document.getElementById('Vrunit').value) || 0;
    const frete = parseFloat(document.getElementById('Frete').value) || 0;
    const percMb = parseFloat(document.getElementById('perc_mb').value) || 0;
    const percAvista = parseFloat(document.getElementById('perc_avista').value) || 0;
    const maxDesc = parseFloat(document.getElementById('MAXDESC').value) || 0;
    
    // Cálculo do custo total
    const custototal = vrunit + frete;
    if (custototal > 0) {
        document.getElementById('custototal').value = custototal.toFixed(2);
    }
    
    // Cálculo do valor de venda (se tiver custo e margem)
    if (custototal > 0 && percMb > 0) {
        const vrvenda = custototal + (custototal * percMb / 100);
        document.getElementById('Vrvenda').value = vrvenda.toFixed(2);
        
        // Cálculo do valor à vista
        if (percAvista > 0) {
            const vravista = vrvenda - (vrvenda * percAvista / 100);
            document.getElementById('Vravista').value = vravista.toFixed(2);
        }
        
        // Cálculo do preço mínimo
        calcularPrecoMinimo();
    }
    
    // Cálculo do valor promocional (se estiver em promoção)
    const promocao = document.getElementById('promocao').value;
    const descPromo = parseFloat(document.getElementById('descpromocao').value) || 0;
    const vrvendaAtual = parseFloat(document.getElementById('Vrvenda').value) || 0;
    
    if (promocao === 'S' && descPromo > 0 && vrvendaAtual > 0) {
        const vrpromocao = vrvendaAtual - (vrvendaAtual * descPromo / 100);
        document.getElementById('vrpromocao').value = vrpromocao.toFixed(2);
    }
}

// ============================================
// ATUALIZAÇÃO DO setupCalculations()
// ============================================

// Substitua a função setupCalculations() existente por esta versão atualizada:
function setupCalculations() {
    // Event listeners para cálculo automático de preços
    const camposCalculo = ['Vrunit', 'Frete', 'perc_mb', 'perc_avista', 'MAXDESC', 'Vrvenda'];
    
    camposCalculo.forEach(fieldId => {
        document.getElementById(fieldId).addEventListener('input', function() {
            calcularValoresPrecos();
        });
    });
    
    // Cálculo específico para desconto à vista
    document.getElementById('perc_avista').addEventListener('input', function() {
        const vrvenda = parseFloat(document.getElementById('Vrvenda').value) || 0;
        const percAvista = parseFloat(this.value) || 0;
        
        if (vrvenda > 0 && percAvista > 0) {
            const vravista = vrvenda - (vrvenda * percAvista / 100);
            document.getElementById('Vravista').value = vravista.toFixed(2);
        } else if (vrvenda > 0 && percAvista === 0) {
            // Se desconto à vista for 0, valor à vista = valor de venda
            document.getElementById('Vravista').value = vrvenda.toFixed(2);
        }
    });
    
    // Cálculo específico para desconto promocional
    document.getElementById('descpromocao').addEventListener('input', function() {
        const vrvenda = parseFloat(document.getElementById('Vrvenda').value) || 0;
        const descPromo = parseFloat(this.value) || 0;
        
        if (vrvenda > 0 && descPromo > 0) {
            const vrpromocao = vrvenda - (vrvenda * descPromo / 100);
            document.getElementById('vrpromocao').value = vrpromocao.toFixed(2);
        } else if (vrvenda > 0 && descPromo === 0) {
            // Se desconto promocional for 0, valor promocional = valor de venda
            document.getElementById('vrpromocao').value = vrvenda.toFixed(2);
        }
    });
    
    // Validação quando preço mínimo é alterado manualmente
    document.getElementById('MINPRECO').addEventListener('input', function() {
        validarDescontoMaximo();
    });
    
    // Cálculo inicial ao carregar a página
    setTimeout(() => {
        calcularValoresPrecos();
    }, 100);
}
    
    function setupValidation() {
        const validators = {
            required: (value) => value.trim() !== '',
            numeric: (value) => !isNaN(value) && value >= 0,
            ncm: (value) => /^\d{8}$/.test(value.replace(/\D/g, '')),
            cest: (value) => /^\d{7}$/.test(value.replace(/\D/g, ''))
        };
        
        function validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            
            if (field.hasAttribute('required') && !validators.required(value)) {
                isValid = false;
            }
            
            if (value && field.type === 'number' && !validators.numeric(value)) {
                isValid = false;
            }
            
            if (value && field.id === 'NCM' && !validators.ncm(value)) {
                isValid = false;
            }
            
            if (value && field.id === 'cest' && !validators.cest(value)) {
                isValid = false;
            }
            
            field.style.borderColor = isValid ? '#e5e7eb' : '#ef4444';
            field.style.background = isValid ? 'white' : '#fef2f2';
            
            return isValid;
        }
        
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }
    
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = collectAllFormData();
        
        if (!validateFormData(formData)) {
            showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        await salvarProduto(formData);
    }
    
    function collectAllFormData() {
        const form = document.getElementById('produtoForm');
        const formData = new FormData(form);
        
        // Coletar campos hidden criados dinamicamente
        const hiddenFields = form.querySelectorAll('input[type="hidden"]');
        hiddenFields.forEach(field => {
            if (!formData.has(field.name)) {
                formData.append(field.name, field.value);
            }
        });
        
        // Coletar dados dos modais
        collectModalData(formData, 'precos');
        collectModalData(formData, 'estoque');
        collectModalData(formData, 'fiscal');
        collectModalData(formData, 'promocao');
        collectModalData(formData, 'configuracoes');
        
        return formData;
    }
    
    function collectModalData(formData, modalId) {
        const modal = document.getElementById(`modal-${modalId}`);
        if (!modal) return;
        
        const inputs = modal.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name && input.value !== '' && input.type !== 'file') {
                formData.set(input.name, input.value);
            }
        });
    }
    
    function validateFormData(data) {
        const required = ['nome'];
        let isValid = true;
        
        for (const field of required) {
            const value = data.get ? data.get(field) : data[field];
            if (!value || value.toString().trim() === '') {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) {
                    input.style.borderColor = '#ef4444';
                    input.style.background = '#fef2f2';
                }
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    async function salvarProduto(formData) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('../api_produtos/salvar_produtos.php', {
                method: 'POST',
                body: formData // Enviando FormData para suportar upload de arquivo
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(`Produto cadastrado com sucesso! Código: ${result.produto.codproduto} - Redirecionando em 2 segundos...`);
                
                setTimeout(() => {
                    window.location.href = '../produtos.php';
                }, 2000);
                
            } else {
                showToast(result.message || 'Erro ao cadastrar produto', 'error');
            }
        } catch (error) {
            showToast('Erro de conexão com o servidor', 'error');
        } finally {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    }
});

// ============================================
// EVENT LISTENER GLOBAL PARA ESC (FECHAR MODAIS)
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModals = document.querySelectorAll('.modal.active');
        activeModals.forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// ============================================
// CSS PARA ANIMAÇÃO DE TOAST (ADICIONE AO <STYLE> SE NÃO EXISTIR)
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .toast {
        animation: slideInRight 0.3s ease forwards;
    }
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
`;
document.head.appendChild(style);
</script>