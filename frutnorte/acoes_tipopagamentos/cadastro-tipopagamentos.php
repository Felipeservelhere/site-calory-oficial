<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    header('Location: ../login.php');
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
    die("Erro de autenticação: " . $e->getMessage());
}

$admin_id = $_SESSION['admin_id'];

// Validar usuário e obter empresa_id
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    $idcliente = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Tipos de pagamentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="main-content">
    <div class="content-wrapper">
        
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../index.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">/</span>
                <a href="../tipopagamentos.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-credit-card"></i>
                    Tipos de Pagamento
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Cadastro de Tipo de Pagamento</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Cadastro de Tipo de Pagamento</span>
                    <p class="title-subtitle">Configure formas de pagamento e suas condições</p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>
        <div class="form-container">
            <form id="tipoPagamentoForm" class="payment-form">
                
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h2>
                        <p class="section-subtitle">Dados principais do tipo de pagamento</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group1">
                            <label for="codtppag" class="form-label">
                                Código
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="codtppag" name="codtppag" class="form-input" readonly placeholder="Será gerado automaticamente">
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                            <small class="form-help">O código será gerado automaticamente baseado no cliente</small>
                        </div>
                        </div>
                        <div class="form-group">

                        <div class="form-group form-group-wide">
                            <label for="Descricao" class="form-label">
                                Descrição <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="Descricao" name="Descricao" class="form-input" maxlength="30" required>
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="prazo" class="form-label">Prazo (dias)</label>
                            <div class="input-wrapper">
                                <input type="number" id="prazo" name="prazo" class="form-input" min="0" max="999" value="0">
                                <i class="fas fa-calendar-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nconta" class="form-label">Conta Caixa/Banco p/Lançto</label>
                            <div class="input-wrapper-with-button">
                                <div class="input-wrapper">
                                    <select id="nconta" name="nconta" class="form-input">
                                        <option value="">Selecione uma conta...</option>
                                    </select>
                                    <i class="fas fa-university input-icon"></i>
                                </div>
                                <button type="button" class="btn-add-account" onclick="abrirModalConta()" title="Criar nova conta">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sections-grid">
                    
                    <div class="section-card" data-modal="operacoes">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Operações</h3>
                                <p class="card-subtitle">Configurações operacionais</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="operacoes-status">Padrão</span>
                            </div>
                        </div>
                        <div class="card-preview" id="operacoes-preview">
                            <span class="preview-text">Configurações padrão aplicadas</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="cartao">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Cartão</h3>
                                <p class="card-subtitle">Configurações de cartão</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="cartao-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="cartao-preview">
                            <span class="preview-text">Clique para configurar cartão</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="fiscal">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Fiscal</h3>
                                <p class="card-subtitle">Configurações fiscais</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="fiscal-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="fiscal-preview">
                            <span class="preview-text">Clique para configurar dados fiscais</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="financeiro">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Financeiro</h3>
                                <p class="card-subtitle">Taxas e comissões</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="financeiro-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="financeiro-preview">
                            <span class="preview-text">Clique para configurar dados financeiros</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="avancado">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Avançado</h3>
                                <p class="card-subtitle">Configurações especiais</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="avancado-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="avancado-preview">
                            <span class="preview-text">Clique para configurar opções avançadas</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="limparFormulario()">
                        <i class="fas fa-eraser"></i>
                        Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Tipo de Pagamento
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Nova Conta -->
        <div id="modal-nova-conta" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-university"></i> Nova Conta</h3>
                    <button class="modal-close" onclick="fecharModalConta()">×</button>
                </div>
                <div class="modal-body">
                    <form id="contaForm">
                        <div class="form-grid">
                            <div class="form-group" style="display: none;">
    <label for="codtppag" class="form-label">Código</label>
    <div class="input-wrapper">
        <input type="text" id="codtppag" name="codtppag" class="form-input" readonly placeholder="Será gerado automaticamente">
        <i class="fas fa-hashtag input-icon"></i>
    </div>
    <small class="form-help">O código será gerado automaticamente baseado no cliente</small>
</div>


                            <div class="form-group">
                                <label for="conta-descricao" class="form-label">
                                    Descrição <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="text" id="conta-descricao" name="descricao" class="form-input" maxlength="255" required>
                                    <i class="fas fa-tag input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="conta-tipo" class="form-label">
                                    Tipo <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <select id="conta-tipo" name="tipo" class="form-input" required>
                                        <option value="">Selecione o tipo...</option>
                                        <option value="C">Caixa</option>
                                        <option value="B">Banco</option>
                                    </select>
                                    <i class="fas fa-list input-icon"></i>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalConta()">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarConta()">
                        <i class="fas fa-save"></i>
                        Salvar Conta
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Operações -->
        <div id="modal-operacoes" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-cogs"></i> Configurações Operacionais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">Atualiza Estoque</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="atualiza" name="atualiza" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="atualiza-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Imprime Comprovante</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="imprime" name="imprime" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="imprime-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Permite Orçamento</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="orcamento" name="orcamento" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="orcamento-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">À Vista</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="avista" name="avista" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="avista-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Gera Comissão</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="comissao" name="comissao" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="comissao-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Abre Gaveta</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="ABRE_GAVETA" name="ABRE_GAVETA" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="ABRE_GAVETA-status">Não</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="operacoes">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Cartão -->
        <div id="modal-cartao" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-credit-card"></i> Configurações de Cartão</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">É Cartão</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="cartao" name="cartao" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="cartao-status-switch">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tipo_cartao" class="form-label">Tipo de Cartão</label>
                            <div class="input-wrapper">
                                <select id="tipo_cartao" name="tipo_cartao" class="form-input">
                                    <option value="O">Outros</option>
                                    <option value="D">Débito</option>
                                    <option value="C">Crédito</option>
                                </select>
                                <i class="fas fa-credit-card input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="cartao">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Fiscal -->
        <div id="modal-fiscal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-file-invoice"></i> Configurações Fiscais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cfop1" class="form-label">CFOP Dentro do Estado</label>
                            <div class="input-wrapper">
                                <input type="text" id="cfop1" name="cfop1" class="form-input" maxlength="5" placeholder="Ex: 5102">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cfop2" class="form-label">CFOP Fora do Estado</label>
                            <div class="input-wrapper">
                                <input type="text" id="cfop2" name="cfop2" class="form-input" maxlength="5" placeholder="Ex: 6102">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Emite NFe</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="EMITE_NFE" name="EMITE_NFE" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="EMITE_NFE-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Emite NFCe</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="EMITE_NFCE" name="EMITE_NFCE" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="EMITE_NFCE-status">Não</span>
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

        <!-- Modal Financeiro -->
        <div id="modal-financeiro" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-dollar-sign"></i> Configurações Financeiras</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="taxaboleto" class="form-label">Taxa do Boleto (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="taxaboleto" name="taxaboleto" class="form-input" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pcomissao" class="form-label">% Comissão</label>
                            <div class="input-wrapper">
                                <input type="number" id="pcomissao" name="pcomissao" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Gera Duplicata</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="duplicata" name="duplicata" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="duplicata-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Imprime Boleto?</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="bloqueto" name="bloqueto" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="bloqueto-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ID_BANCO" class="form-label">ID do Banco</label>
                            <div class="input-wrapper">
                                <input type="number" id="ID_BANCO" name="ID_BANCO" class="form-input" min="1">
                                <i class="fas fa-university input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="financeiro">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Avançado -->
        <div id="modal-avancado" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-sliders-h"></i> Configurações Avançadas</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">Condicional</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="condicional" name="condicional" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="condicional-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Locação</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="locacao" name="locacao" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="locacao-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Troca</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="troca" name="troca" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="troca-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Antecipado</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="antecipado" name="antecipado" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="antecipado-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Devolução</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="devolucao" name="devolucao" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="devolucao-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Transferido</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="transferido" name="transferido" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="transferido-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Lotes</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="lotes" name="lotes" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="lotes-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Automático Entrada</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="automatico_entrada" name="automatico_entrada" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="automatico_entrada-status">Não</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Automático Principal</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="automatico_principal" name="automatico_principal" value="S">
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="automatico_principal-status">Não</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="avancado">Salvar</button>
                </div>
            </div>
        </div>

        <input type="hidden" id="idcliente" name="idcliente" value="<?php echo $idcliente; ?>">

    </div>
</div>

<style>
/* Reutilizando os estilos do cadastro de clientes */
* {
    box-sizing: border-box;
}

.main-content {
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;
}

.content-wrapper {
    margin-top: 10px !important;
    max-width: 1443px;
    margin: 0 auto;
    padding: 20px;
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
    color: #e9d5ff;
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

/* Container principal */
.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* Seção principal */
.main-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.section-header {
    margin-bottom: 24px;
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
    color: #6B46C1;
}

.section-subtitle {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

/* Grid de formulário */
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

.form-group1 {
    display: none;
    flex-direction: column;
}

.form-group-wide {
    grid-column: 2 / -2;
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

.form-help {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
    font-style: italic;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper-with-button {
    display: flex;
    align-items: center;
    gap: 8px;
}

.input-wrapper-with-button .input-wrapper {
    flex: 1;
}

.btn-add-account {
    width: 44px;
    height: 44px;
    border: 2px solid #6B46C1;
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 16px;
}

.btn-add-account:hover {
    background: linear-gradient(135deg, #5b21b6 0%, #4d328bff 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.form-input {
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

.form-input:focus {
    outline: none;
    border-color: #6B46C1;
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
    background: #faf5ff;
}

.form-input[readonly] {
    background: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
}

/* Estilos para o switch */
.switch-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
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

.slider:before {
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

input:checked + .slider {
    background-color: #6B46C1;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.switch-status {
    font-size: 14px;
    font-weight: 600;
    color: #6b7280;
    min-width: 40px;
}

.switch-label {
    margin-bottom: 8px !important;
}

/* Grid de seções */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    padding: 32px;
}

/* Cards de seção */
.section-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.section-card:hover {
    border-color: #6B46C1;
    background: #faf5ff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.15);
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
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
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

.card-status {
    display: flex;
    align-items: center;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f3e8ff;
    color: #6b21a8;
}

.card-preview {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.4;
}

.preview-text {
    font-style: italic;
}

/* Botões de ação */
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
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5b21b6 0%, #4d328bff 100%);
    box-shadow: 0 4px 16px rgba(107, 70, 193, 0.4);
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

/* Modais */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
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
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

.modal-header {
    padding: 24px 32px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
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

.modal-header h3 i {
    color: #6B46C1;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
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

/* Animações */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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
    .main-content {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px 24px;
    }
    
    .title-main {
        font-size: 20px;
    }
    
    .main-section,
    .sections-grid {
        padding: 24px 20px;
    }
    
    .sections-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        padding: 20px;
        flex-direction: column;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 20px;
    }
    
    .input-wrapper-with-button {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-add-account {
        width: 100%;
        height: 44px;
    }
}

/* Estados de loading */
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
// Global variables and functions
let showToast;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('tipoPagamentoForm');
    const sectionCards = document.querySelectorAll('.section-card');
    const modals = document.querySelectorAll('.modal');
    
    // Inicializar valores padrão
    initializeDefaults();
    
    // Carregar contas para o select
    carregarContas();
    
    // Event listeners para cards
    sectionCards.forEach(card => {
        card.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // Event listeners para modais
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        const cancelBtn = modal.querySelector('.modal-cancel');
        const saveBtn = modal.querySelector('.modal-save');
        
        closeBtn?.addEventListener('click', () => closeModal(modal.id));
        cancelBtn?.addEventListener('click', () => closeModal(modal.id));
        saveBtn?.addEventListener('click', function() {
            const section = this.getAttribute('data-section');
            saveModalData(section);
            closeModal(modal.id);
        });
        
        // Fechar modal clicando fora
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Setup switches
    setupSwitches();
    
    // Submit do formulário
    form.addEventListener('submit', handleFormSubmit);
    
    function initializeDefaults() {
        // Valores padrão conforme a estrutura da tabela
        document.getElementById('prazo').value = '0';
        document.getElementById('tipo_cartao').value = 'O';
        
        // Inicializar todos os switches como "N" (Não)
        const switches = document.querySelectorAll('input[type="checkbox"]');
        switches.forEach(switchInput => {
            switchInput.checked = false;
            switchInput.value = 'N';
            updateSwitchStatus(switchInput);
        });
    }
    
    async function carregarContas() {
        try {
            const response = await fetch('../api_tipopagamentos/get_contas.php');
            const result = await response.json();
            
            if (result.success) {
                const select = document.getElementById('nconta');
                select.innerHTML = '<option value="">Selecione uma conta...</option>';
                
                result.contas.forEach(conta => {
                    const option = document.createElement('option');
                    option.value = conta.id;
                    option.textContent = `${conta.codconta} - ${conta.descricao}`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar contas:', error);
        }
    }
    
    function setupSwitches() {
        const switches = document.querySelectorAll('input[type="checkbox"]');
        switches.forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                updateSwitchStatus(this);
            });
        });
    }
    
    function updateSwitchStatus(switchInput) {
        const statusSpan = document.getElementById(switchInput.id + '-status');
        if (!statusSpan) return;
        
        if (switchInput.checked) {
            statusSpan.textContent = 'Sim';
            statusSpan.style.color = '#3b82f6';
            switchInput.value = 'S';
        } else {
            statusSpan.textContent = 'Não';
            statusSpan.style.color = '#6b7280';
            switchInput.value = 'N';
        }
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
        updateCardPreview(section);
        updateCardStatus(section);
        showToast(`Configurações de ${section} salvas com sucesso!`);
    }
    
    function updateCardPreview(section) {
        const preview = document.getElementById(`${section}-preview`);
        let previewText = '';
        
        switch(section) {
            case 'operacoes':
                const operacoes = [];
                if (document.getElementById('atualiza').checked) operacoes.push('Atualiza Estoque');
                if (document.getElementById('imprime').checked) operacoes.push('Imprime');
                if (document.getElementById('orcamento').checked) operacoes.push('Orçamento');
                if (document.getElementById('avista').checked) operacoes.push('À Vista');
                if (document.getElementById('comissao').checked) operacoes.push('Comissão');
                if (document.getElementById('ABRE_GAVETA').checked) operacoes.push('Abre Gaveta');
                previewText = operacoes.length > 0 ? operacoes.join(', ') : 'Configurações padrão aplicadas';
                break;
                
            case 'cartao':
                const isCartao = document.getElementById('cartao').checked;
                const tipoCartao = document.getElementById('tipo_cartao').value;
                const tipoTexto = tipoCartao === 'D' ? 'Débito' : tipoCartao === 'C' ? 'Crédito' : 'Outros';
                previewText = isCartao ? `É Cartão: Sim • Tipo: ${tipoTexto}` : 'Não é cartão';
                break;
                
            case 'fiscal':
                const fiscal = [];
                const cfop1 = document.getElementById('cfop1').value;
                const cfop2 = document.getElementById('cfop2').value;
                if (cfop1) fiscal.push(`CFOP Dentro: ${cfop1}`);
                if (cfop2) fiscal.push(`CFOP Fora: ${cfop2}`);
                if (document.getElementById('EMITE_NFE').checked) fiscal.push('NFe');
                if (document.getElementById('EMITE_NFCE').checked) fiscal.push('NFCe');
                previewText = fiscal.length > 0 ? fiscal.join(' • ') : 'Clique para configurar dados fiscais';
                break;
                
            case 'financeiro':
                const financeiro = [];
                const taxa = document.getElementById('taxaboleto').value;
                const comissao = document.getElementById('pcomissao').value;
                if (taxa) financeiro.push(`Taxa: R$ ${taxa}`);
                if (comissao) financeiro.push(`Comissão: ${comissao}%`);
                if (document.getElementById('duplicata').checked) financeiro.push('Duplicata');
                if (document.getElementById('bloqueto').checked) financeiro.push('Bloqueto');
                previewText = financeiro.length > 0 ? financeiro.join(' • ') : 'Clique para configurar dados financeiros';
                break;
                
            case 'avancado':
                const avancado = [];
                if (document.getElementById('condicional').checked) avancado.push('Condicional');
                if (document.getElementById('locacao').checked) avancado.push('Locação');
                if (document.getElementById('troca').checked) avancado.push('Troca');
                if (document.getElementById('antecipado').checked) avancado.push('Antecipado');
                if (document.getElementById('devolucao').checked) avancado.push('Devolução');
                if (document.getElementById('transferido').checked) avancado.push('Transferido');
                if (document.getElementById('lotes').checked) avancado.push('Lotes');
                if (document.getElementById('automatico_entrada').checked) avancado.push('Auto Entrada');
                if (document.getElementById('automatico_principal').checked) avancado.push('Auto Principal');
                previewText = avancado.length > 0 ? avancado.join(', ') : 'Clique para configurar opções avançadas';
                break;
        }
        
        preview.innerHTML = `<span class="preview-text">${previewText}</span>`;
    }
    
    function updateCardStatus(section) {
        const statusBadge = document.getElementById(`${section}-status`);
        statusBadge.textContent = 'Configurado';
        statusBadge.style.background = '#dbeafe';
        statusBadge.style.color = '#1e40af';
    }
    
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        // Coletar dados do formulário
        const formData = collectFormData();
        
        // Validar campos obrigatórios (agora só a descrição é obrigatória)
        if (!validateFormData(formData)) {
            showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        await salvarTipoPagamento(formData);
    }
    
    function collectFormData() {
        const form = document.getElementById('tipoPagamentoForm');
        const formData = new FormData(form);
        
        // Coletar também dados dos modais
        const allInputs = document.querySelectorAll('#tipoPagamentoForm input, #tipoPagamentoForm select, .modal input, .modal select');
        allInputs.forEach(input => {
            if (input.name && input.type !== 'button') {
                if (input.type === 'checkbox') {
                    formData.set(input.name, input.checked ? 'S' : 'N');
                } else {
                    formData.set(input.name, input.value);
                }
            }
        });
        
        // Converter para objeto
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
    
    function validateFormData(data) {
        // Agora só a descrição é obrigatória, o código será gerado automaticamente
        const required = ['Descricao'];
        let isValid = true;
        
        for (const field of required) {
            if (!data[field] || data[field].toString().trim() === '') {
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
    
    async function salvarTipoPagamento(formData) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('../api_tipopagamentos/salvar_tipopagamentos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(`Tipo de pagamento cadastrado com sucesso! Código: ${result.tipopagamento.codtppag} - Redirecionando em 2 segundos...`);
                
                setTimeout(() => {
                    window.location.href = '../tipopagamentos.php';
                }, 2000);
                
            } else {
                showToast(result.message || 'Erro ao cadastrar tipo de pagamento', 'error');
            }
        } catch (error) {
            showToast('Erro de conexão com o servidor', 'error');
        } finally {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    }
    
    // Define showToast function globally
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
    
    // Função global para limpar formulário
    window.limparFormulario = function() {
        if (confirm('Tem certeza que deseja limpar todos os campos?')) {
            form.reset();
            initializeDefaults();
            
            // Limpar também os campos dos modais
            document.querySelectorAll('.modal input, .modal select').forEach(field => {
                if (field.type !== 'button') {
                    if (field.type === 'checkbox') {
                        field.checked = false;
                        field.value = 'N';
                        updateSwitchStatus(field);
                    } else {
                        field.value = '';
                    }
                }
            });
            
            // Resetar previews dos cards
            document.querySelectorAll('.card-preview').forEach(preview => {
                const section = preview.id.replace('-preview', '');
                const defaultTexts = {
                    'operacoes': 'Configurações padrão aplicadas',
                    'cartao': 'Clique para configurar cartão',
                    'fiscal': 'Clique para configurar dados fiscais',
                    'financeiro': 'Clique para configurar dados financeiros',
                    'avancado': 'Clique para configurar opções avançadas'
                };
                preview.innerHTML = `<span class="preview-text">${defaultTexts[section] || 'Clique para configurar'}</span>`;
            });
            
            // Resetar status dos cards
            document.querySelectorAll('.status-badge').forEach(badge => {
                if (badge.id === 'operacoes-status') {
                    badge.textContent = 'Padrão';
                    badge.style.background = '#e0e7ff';
                    badge.style.color = '#3730a3';
                } else {
                    badge.textContent = 'Opcional';
                    badge.style.background = '#e0e7ff';
                    badge.style.color = '#3730a3';
                }
            });
            
            showToast('Formulário limpo com sucesso!');
        }
    };
});

// Funções globais para o modal de conta
window.abrirModalConta = function() {
    const modal = document.getElementById('modal-nova-conta');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
};

window.fecharModalConta = function() {
    const modal = document.getElementById('modal-nova-conta');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Limpar formulário da conta
    const form = document.getElementById('contaForm');
    form.reset();
};

window.salvarConta = async function() {
    const form = document.getElementById('contaForm');
    const formData = new FormData(form);
    
    // Validar campos obrigatórios
    const descricao = formData.get('descricao');
    const tipo = formData.get('tipo');
    
    if (!descricao || !tipo) {
        showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    const data = {
        descricao: descricao,
        tipo: tipo,
        idcliente: document.getElementById('idcliente').value
    };
    
    try {
        const response = await fetch('../api_tipopagamentos/salvar_conta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Conta cadastrada com sucesso! Código: ${result.conta.codconta}`);
            
            // Fechar modal
            fecharModalConta();
            
            // Recarregar lista de contas
            await carregarContas();
            
            // Selecionar a conta recém-criada
            const select = document.getElementById('nconta');
            select.value = result.conta.id;
            
        } else {
            showToast(result.message || 'Erro ao cadastrar conta', 'error');
        }
    } catch (error) {
        showToast('Erro de conexão com o servidor', 'error');
    }
};

// Função global para abrir modal
window.openModal = function(modalId) {
    const modal = document.getElementById(`modal-${modalId}`);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
};

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

// Função para recarregar contas (reutilizada)
async function carregarContas() {
    try {
        const response = await fetch('../api_tipopagamentos/get_contas.php');
        const result = await response.json();
        
        if (result.success) {
            const select = document.getElementById('nconta');
            const selectedValue = select.value; // Preservar seleção atual
            select.innerHTML = '<option value="">Selecione uma conta...</option>';
            
            result.contas.forEach(conta => {
                const option = document.createElement('option');
                option.value = conta.id;
                option.textContent = `${conta.codconta} - ${conta.descricao}`;
                select.appendChild(option);
            });
            
            // Restaurar seleção se ainda existir
            if (selectedValue) {
                select.value = selectedValue;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar contas:', error);
    }
}
</script>