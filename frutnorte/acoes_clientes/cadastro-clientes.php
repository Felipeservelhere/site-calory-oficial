<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");  // 👈 Ajuste o caminho para login se necessário
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO LOGIN (para validar empresa_id/idcliente) ====================
require_once '../config/databaselogin.php';

try {
    $dbLogin = new DatabaseLogin();  // 👈 Classe correta para databaselogin.php
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação (frutnorte). Verifique credenciais em databaselogin.php.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de autenticação: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente da empresa logada) do usuário autenticado (sem filtro de cargo para acesso básico)
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

    $idcliente_empresa = $admin_data['empresa_id'];  // 👈 ID da empresa logada (idcliente da empresa)
    $_SESSION['empresa_id'] = $idcliente_empresa;
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na validação de usuário: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (opcional para este formulário, mas para consistência) ====================
require_once '../config/database.php';

try {
    $database = new Database();  // 👈 Classe correta para database.php
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com DB operacional (empresaweb). Verifique credenciais em database.php.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de dados: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// Para cadastro novo, $cliente não existe; inicializar como array vazio para evitar warnings
$cliente = [];  // 👈 Para novos cadastros, campos editáveis serão vazios/padrão
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Clientes</title>
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
                <a href="../clientes.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-users"></i>
                    Clientes
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Cadastro de Clientes</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Cadastro de Cliente</span>
                    <p class="title-subtitle">Cadastre seus clientes com todos os dados</p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>
        <div class="form-container">
            <form id="clienteForm" class="client-form">
                
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i>
                            Informações Principais
                        </h2>
                        <p class="section-subtitle">Dados essenciais do cliente</p>
                    </div>
                    
                    <div class="form-grid" style="display: flex; gap: 20px;">
    <!-- Tipo de Pessoa -->
    <div class="form-group" style="flex: 1;">
        <label for="tipo_pessoa" class="form-label">
            Tipo de Pessoa <span class="required">*</span>
        </label>
        <div class="input-wrapper">
            <select id="tipo_pessoa" name="tipo_pessoa" class="form-input" required>
                <option value="F" <?= (isset($cliente['tipo_pessoa']) && $cliente['tipo_pessoa'] === 'F') ? 'selected' : '' ?>>Pessoa Física</option>
                <option value="J" <?= (isset($cliente['tipo_pessoa']) && $cliente['tipo_pessoa'] === 'J') ? 'selected' : '' ?>>Pessoa Jurídica</option>
            </select>
            <i class="fas fa-user-tag input-icon"></i>
        </div>
    </div>

    <!-- Tipo de Cliente -->
    <div class="form-group" style="flex: 1;">
        <label for="tipocliente" class="form-label">
            Tipo de Cliente <span class="required">*</span>
        </label>
        <div class="input-wrapper">
            <select id="tipocliente" name="tipocliente" class="form-input" required>
    <option value="cliente" <?= (isset($cliente['tipocliente']) && $cliente['tipocliente'] === 'cliente') ? 'selected' : '' ?>>Cliente</option>
    <option value="fornecedor" <?= (isset($cliente['tipocliente']) && $cliente['tipocliente'] === 'fornecedor') ? 'selected' : '' ?>>Fornecedor</option>
    <option value="funcionario" <?= (isset($cliente['tipocliente']) && $cliente['tipocliente'] === 'funcionario') ? 'selected' : '' ?>>Funcionário</option>
    <option value="outro" <?= (isset($cliente['tipocliente']) && $cliente['tipocliente'] === 'outro') ? 'selected' : '' ?>>Outro</option>  <!-- CORREÇÃO: isset($cliente['tipocliente']) -->
</select>
            <i class="fas fa-briefcase input-icon"></i>
        </div>
    </div>
                        </div>

                        <!-- Campo Switch para Motorista/Transportadora -->
                        <div class="form-grid" id="switch-container" style="display: none;">
                            <div class="form-group">
                                <label class="form-label switch-label">
                                    <span id="switch-text">Motorista</span>
                                </label>
                                <div class="switch-wrapper">
                                    <label class="switch">
                                        <input type="checkbox" id="switch-field" name="motorista" value="S">
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="switch-status" id="switch-status">Não</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-grid">

                        <div class="form-group form-group-wide">
                            <label for="Nome" class="form-label">
                                Nome / Razão Social <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="Nome" name="Nome" class="form-input" maxlength="60" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Fantasia" class="form-label">Nome Fantasia</label>
                            <div class="input-wrapper">
                                <input type="text" id="Fantasia" name="Fantasia" class="form-input" maxlength="30">
                                <i class="fas fa-store input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cnpj_cpf" class="form-label">
                                CPF / CNPJ <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="cnpj_cpf" name="cnpj_cpf" class="form-input" maxlength="20" required>
                                <i class="fas fa-id-card input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Email" class="form-label">
                                E-mail <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="email" id="Email" name="Email" class="form-input" maxlength="100" required>
                                <i class="fas fa-envelope input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="celular" class="form-label">Celular</label>
                            <div class="input-wrapper">
                                <input type="tel" id="celular" name="celular" class="form-input" maxlength="20" placeholder="(00) 00000-0000">
                                <i class="fas fa-mobile-alt input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sections-grid">
                    
                    <div class="section-card" data-modal="endereco">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Endereço</h3>
                                <p class="card-subtitle">Localização e entrega</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="endereco-status">Pendente</span>
                            </div>
                        </div>
                        <div class="card-preview" id="endereco-preview">
                            <span class="preview-text">Clique para adicionar endereço</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="documentos">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Documentos</h3>
                                <p class="card-subtitle">Inscrições e registros</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="documentos-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="documentos-preview">
                            <span class="preview-text">Clique para adicionar documentos</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="comercial">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Dados Comerciais</h3>
                                <p class="card-subtitle">Condições e limites</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="comercial-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="comercial-preview">
                            <span class="preview-text">Clique para configurar dados comerciais</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="contas-bancarias">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Contas Bancárias</h3>
                                <p class="card-subtitle">Dados bancários e PIX</p>
                            </div>
                            <div class="card-status">
                                <span class="status-badge" id="contas-bancarias-status">Opcional</span>
                            </div>
                        </div>
                        <div class="card-preview" id="contas-bancarias-preview">
                            <span class="preview-text">Clique para adicionar contas bancárias</span>
                        </div>
                    </div>

                    <div class="section-card" data-modal="configuracoes">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title">Configurações</h3>
                                <p class="card-subtitle">Opções especiais</p>
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
                    <label for="obs" class="form-label">
                        <i class="fas fa-sticky-note"></i>
                        Observações
                    </label>
                    <textarea id="obs" name="obs" class="form-textarea" rows="3" maxlength="255" placeholder="Informações adicionais sobre o cliente..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="limparFormulario()">
                        <i class="fas fa-eraser"></i>
                        Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Cliente
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Endereço -->
        <div id="modal-endereco" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Endereço do Cliente</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="CEP" class="form-label">CEP</label>
                            <div class="input-wrapper">
                                <input type="text" id="CEP" name="CEP" class="form-input" maxlength="15" placeholder="00000-000">
                                <i class="fas fa-search input-icon"></i>
                                <div class="input-loading" id="cep-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group form-group-wide">
                            <label for="Endereco" class="form-label">Endereço</label>
                            <div class="input-wrapper">
                                <input type="text" id="Endereco" name="Endereco" class="form-input" maxlength="60">
                                <i class="fas fa-road input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero" class="form-label">Número</label>
                            <div class="input-wrapper">
                                <input type="text" id="numero" name="numero" class="form-input" maxlength="10">
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="complemento" class="form-label">Complemento</label>
                            <div class="input-wrapper">
                                <input type="text" id="complemento" name="complemento" class="form-input" maxlength="30">
                                <i class="fas fa-plus input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Bairro" class="form-label">Bairro</label>
                            <div class="input-wrapper">
                                <input type="text" id="Bairro" name="Bairro" class="form-input" maxlength="30">
                                <i class="fas fa-map input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Cidade" class="form-label">Cidade</label>
                            <div class="input-wrapper">
                                <input type="text" id="Cidade" name="Cidade" class="form-input" maxlength="60">
                                <i class="fas fa-city input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Uf" class="form-label">Estado (UF)</label>
                            <div class="input-wrapper">
                                <select id="Uf" name="Uf" class="form-input">
                                    <option value="">Selecione...</option>
                                    <option value="AC">Acre</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="AP">Amapá</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="BA">Bahia</option>
                                    <option value="CE">Ceará</option>
                                    <option value="DF">Distrito Federal</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MA">Maranhão</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="PA">Pará</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="PR">Paraná</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="RR">Roraima</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="TO">Tocantins</option>
                                </select>
                                <i class="fas fa-flag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pais" class="form-label">País</label>
                            <div class="input-wrapper">
                                <input type="text" id="pais" name="pais" class="form-input" maxlength="60" value="BRASIL">
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="endereco">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Documentos -->
        <div id="modal-documentos" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-file-alt"></i> Documentos e Inscrições</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="IE" class="form-label">Inscrição Estadual</label>
                            <div class="input-wrapper">
                                <input type="text" id="IE" name="IE" class="form-input" maxlength="20">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="IM" class="form-label">Inscrição Municipal</label>
                            <div class="input-wrapper">
                                <input type="text" id="IM" name="IM" class="form-input" maxlength="30">
                                <i class="fas fa-building input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="insc_rural" class="form-label">Inscrição Rural</label>
                            <div class="input-wrapper">
                                <input type="text" id="insc_rural" name="insc_rural" class="form-input" maxlength="14">
                                <i class="fas fa-tractor input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="insc_suframa" class="form-label">Inscrição SUFRAMA</label>
                            <div class="input-wrapper">
                                <input type="text" id="insc_suframa" name="insc_suframa" class="form-input" maxlength="15">
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nascimento" class="form-label">Data de Nascimento</label>
                            <div class="input-wrapper">
                                <input type="date" id="nascimento" name="nascimento" class="form-input">
                                <i class="fas fa-calendar input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Fone" class="form-label">Telefone</label>
                            <div class="input-wrapper">
                                <input type="tel" id="Fone" name="Fone" class="form-input" maxlength="20" placeholder="(00) 0000-0000">
                                <i class="fas fa-phone input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Contato" class="form-label">Pessoa de Contato</label>
                            <div class="input-wrapper">
                                <input type="text" id="Contato" name="Contato" class="form-input" maxlength="30">
                                <i class="fas fa-user-friends input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="documentos">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Comercial -->
        <div id="modal-comercial" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-briefcase"></i> Informações Comerciais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">

                        <div class="form-group">
                            <label for="CondPgto" class="form-label">Condição de Pagamento</label>
                            <div class="input-wrapper">
                                <input type="text" id="CondPgto" name="CondPgto" class="form-input" maxlength="20" placeholder="Ex: 30/60/90 dias">
                                <i class="fas fa-credit-card input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Transportadora" class="form-label">Transportadora</label>
                            <div class="input-wrapper">
                                <input type="text" id="Transportadora" name="Transportadora" class="form-input" maxlength="40">
                                <i class="fas fa-truck input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="PercDesconto" class="form-label">% Desconto</label>
                            <div class="input-wrapper">
                                <input type="number" id="PercDesconto" name="PercDesconto" class="form-input" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="limite" class="form-label">Limite de Crédito (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="limite" name="limite" class="form-input" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="saldo_devedor" class="form-label">Saldo Devedor (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="saldo_devedor" name="saldo_devedor" class="form-input" step="0.01" min="0" readonly>
                                <i class="fas fa-balance-scale input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="codvendedor" class="form-label">Código do Vendedor</label>
                            <div class="input-wrapper">
                                <input type="number" id="codvendedor" name="codvendedor" class="form-input" min="1">
                                <i class="fas fa-user-tie input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancelar</button>
                    <button type="button" class="btn btn-primary modal-save" data-section="comercial">Salvar</button>
                </div>
            </div>
        </div>

        <!-- Modal Contas Bancárias -->
        <div id="modal-contas-bancarias" class="modal">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3><i class="fas fa-university"></i> Contas Bancárias do Cliente</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="contas-header">
                        <button type="button" class="btn btn-primary btn-sm" onclick="adicionarNovaConta()">
                            <i class="fas fa-plus"></i>
                            Nova Conta Bancária
                        </button>
                    </div>
                    
                    <div class="contas-lista" id="contas-lista">
                        <!-- Lista de contas será carregada aqui -->
                    </div>

                    <!-- Formulário para nova conta -->
                    <div class="nova-conta-form" id="nova-conta-form" style="display: none;">
                        <h4><i class="fas fa-plus-circle"></i> Nova Conta Bancária</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="novo-tipoconta" class="form-label">Tipo de Conta <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <select id="novo-tipoconta" class="form-input" required>
                                        <option value="">Selecione...</option>
                                        <option value="Conta Corrente">Conta Corrente</option>
                                        <option value="Poupança">Poupança</option>
                                        <option value="Conta Salário">Conta Salário</option>
                                        <option value="Conta Investimento">Conta Investimento</option>
                                    </select>
                                    <i class="fas fa-university input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-banco" class="form-label">Banco <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-banco" class="form-input" maxlength="100" required placeholder="Nome do banco">
                                    <i class="fas fa-building input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-agencia" class="form-label">Agência <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-agencia" class="form-input" maxlength="20" required placeholder="0000">
                                    <i class="fas fa-code-branch input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-nconta" class="form-label">Número da Conta <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-nconta" class="form-input" maxlength="20" required placeholder="00000-0">
                                    <i class="fas fa-hashtag input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-chavepix" class="form-label">Chave PIX</label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-chavepix" class="form-input" maxlength="100" placeholder="CPF, e-mail, celular ou chave aleatória">
                                    <i class="fas fa-key input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-cpf-titular" class="form-label">CPF do Titular</label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-cpf-titular" class="form-input" maxlength="14" placeholder="000.000.000-00">
                                    <i class="fas fa-id-card input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="novo-nome-titular" class="form-label">Nome do Titular</label>
                                <div class="input-wrapper">
                                    <input type="text" id="novo-nome-titular" class="form-input" maxlength="100" placeholder="Nome completo do titular">
                                    <i class="fas fa-user input-icon"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="cancelarNovaConta()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="button" class="btn btn-success" onclick="salvarNovaConta()">
                                <i class="fas fa-save"></i>
                                Salvar Conta
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Fechar</button>
                </div>
            </div>
        </div>

        <!-- Modal Configurações -->
        <div id="modal-configuracoes" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-cogs"></i> Configurações Especiais</h3>
                    <button class="modal-close">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="NotaKG" class="form-label">Nota por KG</label>
                            <div class="input-wrapper">
                                <select id="NotaKG" name="NotaKG" class="form-input">
                                    <option value="S">Sim</option>
                                    <option value="N">Não</option>
                                </select>
                                <i class="fas fa-weight input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pdesconto_boleto" class="form-label">% Desconto Boleto</label>
                            <div class="input-wrapper">
                                <input type="number" id="pdesconto_boleto" name="pdesconto_boleto" class="form-input" step="0.01" min="0" max="100" value="0">
                                <i class="fas fa-file-invoice input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="protesto_automatico_boletos" class="form-label">Protesto Automático</label>
                            <div class="input-wrapper">
                                <select id="protesto_automatico_boletos" name="protesto_automatico_boletos" class="form-input">
                                    <option value="N">Não</option>
                                    <option value="S">Sim</option>
                                </select>
                                <i class="fas fa-exclamation-triangle input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dias_protesto" class="form-label">Dias para Protesto</label>
                            <div class="input-wrapper">
                                <input type="number" id="dias_protesto" name="dias_protesto" class="form-input" min="1" max="365" value="5">
                                <i class="fas fa-calendar-times input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ativo" class="form-label">Status</label>
                            <div class="input-wrapper">
                                <select id="ativo" name="ativo" class="form-input">
                                    <option value="S">Ativo</option>
                                    <option value="N">Inativo</option>
                                </select>
                                <i class="fas fa-toggle-on input-icon"></i>
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

        <input type="hidden" id="idcliente" name="idcliente" value="<?php echo $idcliente_empresa; ?>">
        <input type="hidden" id="codcliente" name="codcliente">
        <input type="hidden" id="Data_cad" name="Data_cad">

    </div>
</div>

<style>
/* Estilos completamente redesenhados para layout compacto com modais */

/* Reset e base */
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

.breadcrumb {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    font-size: 14px;
    opacity: 0.9;
}

/* Adicionar ao CSS existente */
.form-input[readonly] {
    background-color: #f9fafb !important;
    color: #9ca3af !important;
    cursor: not-allowed;
    border-color: #d1d5db !important;
}

.form-input[readonly]:focus {
    box-shadow: none !important;
    border-color: #d1d5db !important;
    background-color: #f9fafb !important;
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
    color: #10b981;
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

.form-group-wide {
    grid-column: 1 / -1;
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
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    background: #f0fdf4;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
}

.input-loading {
    position: absolute;
    right: 14px;
    color: #10b981;
    display: none;
}

.input-loading.active {
    display: block;
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
    background-color: #10b981;
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

/* Grid de seções - Agora com 5 cards em grid responsivo */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
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
    border-color: #10b981;
    background: #f0fdf4;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
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
    background: #fef3c7;
    color: #92400e;
}

.card-preview {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.4;
}

.preview-text {
    font-style: italic;
}

/* Seção de observações */
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
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

.btn-info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-info:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4);
    transform: translateY(-1px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 12px;
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

.modal-large {
    max-width: 1000px;
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
    color: #10b981;
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

/* Estilos específicos para contas bancárias */
.contas-header {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
}

.contas-lista {
    margin-bottom: 20px;
}

.conta-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conta-info {
    flex: 1;
}

.conta-tipo {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 4px;
}

.conta-detalhes {
    font-size: 13px;
    color: #64748b;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.conta-actions {
    display: flex;
    gap: 8px;
}

.nova-conta-form {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.nova-conta-form h4 {
    margin: 0 0 16px 0;
    color: #065f46;
    display: flex;
    align-items: center;
    gap: 8px;
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
    .sections-grid,
    .obs-section {
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

    .conta-detalhes {
        flex-direction: column;
        gap: 4px;
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
    const form = document.getElementById('clienteForm');
    const sectionCards = document.querySelectorAll('.section-card');
    const modals = document.querySelectorAll('.modal');
    const tipoClienteSelect = document.getElementById('tipocliente');
    const switchContainer = document.getElementById('switch-container');
    const switchField = document.getElementById('switch-field');
    const switchText = document.getElementById('switch-text');
    const switchStatus = document.getElementById('switch-status');
    
    // Inicializar valores padrão
    initializeDefaults();
    
    // Event listener para mudança do tipo de cliente
    tipoClienteSelect.addEventListener('change', function() {
        handleTipoClienteChange(this.value);
    });
    
    // Event listener para o switch
    switchField.addEventListener('change', function() {
        updateSwitchStatus();
    });
    
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
    
    // Máscaras de input
    setupMasks();
    
    // Busca de CEP
    setupCEPSearch();
    
    // Validação de formulário
    setupValidation();
    
    // Submit do formulário
    form.addEventListener('submit', handleFormSubmit);
    
    function initializeDefaults() {
        document.getElementById('tipo_pessoa').value = 'F';
        document.getElementById('pais').value = 'BRASIL';
        document.getElementById('pdesconto_boleto').value = '0';
        document.getElementById('protesto_automatico_boletos').value = 'N';
        document.getElementById('dias_protesto').value = '5';
        document.getElementById('ativo').value = 'S';
        document.getElementById('NotaKG').value = 'S';
        document.getElementById('Data_cad').value = new Date().toISOString().split('T')[0];
        
        // Inicializar o switch baseado no tipo de cliente padrão
        handleTipoClienteChange('cliente');
    }
    
    function handleTipoClienteChange(tipoCliente) {
    if (tipoCliente === 'funcionario') {
        // Mostrar switch para Motorista
        switchContainer.style.display = 'block';
        switchText.textContent = 'Motorista';
        switchField.name = 'motorista';
        switchField.checked = false;
        updateSwitchStatus();
        // Desabilitar campo transportadora
        toggleTransportadoraField(false);
    } else if (['cliente', 'fornecedor', 'outro'].includes(tipoCliente)) {
        // Mostrar switch para Transportadora
        switchContainer.style.display = 'block';
        switchText.textContent = 'Transportadora';
        switchField.name = 'transportadora';
        switchField.checked = false;
        updateSwitchStatus();
        // Inicialmente desabilitar campo transportadora
        toggleTransportadoraField(false);
    } else {
        // Ocultar switch
        switchContainer.style.display = 'none';
        // Desabilitar campo transportadora
        toggleTransportadoraField(false);
    }
}

// Atualizar a função updateSwitchStatus
function updateSwitchStatus() {
    if (switchField.checked) {
        switchStatus.textContent = 'Sim';
        switchStatus.style.color = '#10b981';
        switchField.value = 'S';
        
        // Se for transportadora e estiver marcado como Sim, habilitar o campo
        if (switchField.name === 'transportadora') {
            toggleTransportadoraField(true);
        }
    } else {
        switchStatus.textContent = 'Não';
        switchStatus.style.color = '#6b7280';
        switchField.value = 'N';
        
        // Se for transportadora e estiver marcado como Não, desabilitar o campo
        if (switchField.name === 'transportadora') {
            toggleTransportadoraField(false);
        }
    }
}

// Adicionar nova função para controlar o campo transportadora
function toggleTransportadoraField(habilitar) {
    const transportadoraInput = document.getElementById('Transportadora');
    if (transportadoraInput) {
        if (habilitar) {
            transportadoraInput.removeAttribute('readonly');
            transportadoraInput.style.background = 'white';
            transportadoraInput.style.color = '#374151';
            transportadoraInput.placeholder = 'Digite o nome da transportadora';
        } else {
            transportadoraInput.setAttribute('readonly', 'readonly');
            transportadoraInput.style.background = '#f9fafb';
            transportadoraInput.style.color = '#9ca3af';
            transportadoraInput.placeholder = 'Habilite "Transportadora: Sim" para editar';
            transportadoraInput.value = ''; // Limpar o campo quando desabilitado
        }
    }
}

// Também precisamos inicializar o estado do campo transportadora
function initializeDefaults() {
    document.getElementById('tipo_pessoa').value = 'F';
    document.getElementById('pais').value = 'BRASIL';
    document.getElementById('pdesconto_boleto').value = '0';
    document.getElementById('protesto_automatico_boletos').value = 'N';
    document.getElementById('dias_protesto').value = '5';
    document.getElementById('ativo').value = 'S';
    document.getElementById('NotaKG').value = 'S';
    document.getElementById('Data_cad').value = new Date().toISOString().split('T')[0];    
    // Inicializar campo transportadora como desabilitado
    toggleTransportadoraField(false);
    
    // Inicializar o switch baseado no tipo de cliente padrão
    handleTipoClienteChange('cliente');
}
    
    function openModal(modalId) {
        const modal = document.getElementById(`modal-${modalId}`);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Se for o modal de contas bancárias, carregar as contas
            if (modalId === 'contas-bancarias') {
                carregarContasBancarias();
            }
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
        // Primeiro, adicionar os dados aos campos hidden do formulário
        updateFormData(section);
        // Depois, atualizar a visualização
        updateCardPreview(section);
        updateCardStatus(section);
        showToast(`Dados de ${section} salvos com sucesso!`);
    }
    
    function updateFormData(section) {
        // Criar ou atualizar campos hidden no formulário principal
        const form = document.getElementById('clienteForm');
        
        switch(section) {
            case 'endereco':
                updateHiddenField(form, 'CEP', document.getElementById('CEP').value);
                updateHiddenField(form, 'Endereco', document.getElementById('Endereco').value);
                updateHiddenField(form, 'numero', document.getElementById('numero').value);
                updateHiddenField(form, 'complemento', document.getElementById('complemento').value);
                updateHiddenField(form, 'Bairro', document.getElementById('Bairro').value);
                updateHiddenField(form, 'Cidade', document.getElementById('Cidade').value);
                updateHiddenField(form, 'Uf', document.getElementById('Uf').value);
                updateHiddenField(form, 'pais', document.getElementById('pais').value);
                break;
                
            case 'documentos':
                updateHiddenField(form, 'IE', document.getElementById('IE').value);
                updateHiddenField(form, 'IM', document.getElementById('IM').value);
                updateHiddenField(form, 'insc_rural', document.getElementById('insc_rural').value);
                updateHiddenField(form, 'insc_suframa', document.getElementById('insc_suframa').value);
                updateHiddenField(form, 'nascimento', document.getElementById('nascimento').value);
                updateHiddenField(form, 'Fone', document.getElementById('Fone').value);
                updateHiddenField(form, 'Contato', document.getElementById('Contato').value);
                break;
                
            case 'comercial':
                updateHiddenField(form, 'tipocliente', document.getElementById('tipocliente').value);
                updateHiddenField(form, 'CondPgto', document.getElementById('CondPgto').value);
                updateHiddenField(form, 'Transportadora', document.getElementById('Transportadora').value);
                updateHiddenField(form, 'PercDesconto', document.getElementById('PercDesconto').value);
                updateHiddenField(form, 'limite', document.getElementById('limite').value);
                updateHiddenField(form, 'saldo_devedor', document.getElementById('saldo_devedor').value);
                updateHiddenField(form, 'codvendedor', document.getElementById('codvendedor').value);
                break;
                
            case 'configuracoes':
                updateHiddenField(form, 'NotaKG', document.getElementById('NotaKG').value);
                updateHiddenField(form, 'pdesconto_boleto', document.getElementById('pdesconto_boleto').value);
                updateHiddenField(form, 'protesto_automatico_boletos', document.getElementById('protesto_automatico_boletos').value);
                updateHiddenField(form, 'dias_protesto', document.getElementById('dias_protesto').value);
                updateHiddenField(form, 'ativo', document.getElementById('ativo').value);
                break;
        }
    }
    
    function updateHiddenField(form, fieldName, value) {
        let field = form.querySelector(`[name="${fieldName}"]`);
        
        // Se o campo já existe (pode ser visível ou hidden), atualizar
        if (field) {
            field.value = value;
        } else {
            // Se não existe, criar um campo hidden
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
            case 'endereco':
                const endereco = document.getElementById('Endereco').value;
                const cidade = document.getElementById('Cidade').value;
                const uf = document.getElementById('Uf').value;
                if (endereco || cidade) {
                    previewText = `${endereco || 'Endereço'}, ${cidade || 'Cidade'} - ${uf || 'UF'}`;
                } else {
                    previewText = 'Clique para adicionar endereço';
                }
                break;
                
            case 'documentos':
                const ie = document.getElementById('IE').value;
                const im = document.getElementById('IM').value;
                const contato = document.getElementById('Contato').value;
                const docs = [ie && 'IE', im && 'IM', contato && 'Contato'].filter(Boolean);
                previewText = docs.length > 0 ? docs.join(', ') : 'Clique para adicionar documentos';
                break;
                
            case 'comercial':
                const tipo = document.getElementById('tipocliente').value;
                const limite = document.getElementById('limite').value;
                const cond = document.getElementById('CondPgto').value;
                const info = [];
                if (tipo) info.push(`Tipo: ${tipo}`);
                if (limite) info.push(`Limite: R$ ${limite}`);
                if (cond) info.push(`Pagto: ${cond}`);
                previewText = info.length > 0 ? info.join(' • ') : 'Clique para configurar dados comerciais';
                break;
                
            case 'configuracoes':
                const notakg = document.getElementById('NotaKG').value;
                const ativo = document.getElementById('ativo').value;
                const protesto = document.getElementById('protesto_automatico_boletos').value;
                previewText = `Status: ${ativo === 'S' ? 'Ativo' : 'Inativo'} • Nota KG: ${notakg === 'S' ? 'Sim' : 'Não'} • Protesto: ${protesto === 'S' ? 'Sim' : 'Não'}`;
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
    
    function setupMasks() {
        const masks = {
            cpfCnpj: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length <= 11) {
                    return numbers.replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                } else {
                    return numbers.replace(/(\d{2})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1/$2')
                                 .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                }
            },
            cpf: (value) => {
                const numbers = value.replace(/\D/g, '');
                return numbers.replace(/(\d{3})(\d)/, '$1.$2')
                             .replace(/(\d{3})(\d)/, '$1.$2')
                             .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            },
            phone: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length <= 10) {
                    return numbers.replace(/(\d{2})(\d)/, '($1) $2')
                                 .replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    return numbers.replace(/(\d{2})(\d)/, '($1) $2')
                                 .replace(/(\d{5})(\d)/, '$1-$2');
                }
            },
            cep: (value) => {
                return value.replace(/\D/g, '')
                           .replace(/(\d{5})(\d)/, '$1-$2');
            }
        };
        
        document.getElementById('cnpj_cpf').addEventListener('input', function(e) {
            e.target.value = masks.cpfCnpj(e.target.value);
        });
        
        ['Fone', 'celular'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function(e) {
                    e.target.value = masks.phone(e.target.value);
                });
            }
        });
        
        document.getElementById('CEP').addEventListener('input', function(e) {
            e.target.value = masks.cep(e.target.value);
        });

        // Máscara para CPF do titular
        const cpfTitularInput = document.getElementById('novo-cpf-titular');
        if (cpfTitularInput) {
            cpfTitularInput.addEventListener('input', function(e) {
                e.target.value = masks.cpf(e.target.value);
            });
        }
    }
    
    function setupCEPSearch() {
        document.getElementById('CEP').addEventListener('blur', async function() {
            const cep = this.value.replace(/\D/g, '');
            if (cep.length === 8) {
                await buscarEnderecoPorCEP(cep);
            }
        });
    }
    
    async function buscarEnderecoPorCEP(cep) {
        const loading = document.getElementById('cep-loading');
        loading.classList.add('active');
        
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();
            
            if (!data.erro) {
                document.getElementById('Endereco').value = data.logradouro || '';
                document.getElementById('Bairro').value = data.bairro || '';
                document.getElementById('Cidade').value = data.localidade || '';
                document.getElementById('Uf').value = data.uf || '';
                
                // Atualizar também os campos hidden do formulário
                updateFormData('endereco');
                
                showToast('Endereço encontrado e preenchido automaticamente!');
            } else {
                showToast('CEP não encontrado', 'warning');
            }
        } catch (error) {
            showToast('Erro ao buscar CEP', 'error');
        } finally {
            loading.classList.remove('active');
        }
    }
    
    function setupValidation() {
        const validators = {
            cpfCnpj: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length === 11) {
                    return validarCPF(numbers);
                } else if (numbers.length === 14) {
                    return validarCNPJ(numbers);
                }
                return false;
            },
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            required: (value) => value.trim() !== ''
        };
        
        function validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            
            if (field.hasAttribute('required') && !validators.required(value)) {
                isValid = false;
            }
            
            if (value && field.id === 'cnpj_cpf' && !validators.cpfCnpj(value)) {
                isValid = false;
            }
            
            if (value && field.type === 'email' && !validators.email(value)) {
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
        
        // Coletar TODOS os dados do formulário
        const formData = collectAllFormData();
        
        // Validar campos obrigatórios
        if (!validateFormData(formData)) {
            showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        await salvarCliente(formData);
    }
    
    function collectAllFormData() {
        const form = document.getElementById('clienteForm');
        const formData = new FormData(form);
        
        // Coletar também todos os campos hidden que foram criados dinamicamente
        const hiddenFields = form.querySelectorAll('input[type="hidden"]');
        hiddenFields.forEach(field => {
            if (!formData.has(field.name)) {
                formData.append(field.name, field.value);
            }
        });
        
        // Coletar dados dos modais que podem não estar no form principal
        collectModalData(formData, 'endereco');
        collectModalData(formData, 'documentos');
        collectModalData(formData, 'comercial');
        collectModalData(formData, 'configuracoes');
        
        // Converter para objeto simples
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
    
    function collectModalData(formData, modalId) {
        const modal = document.getElementById(`modal-${modalId}`);
        if (!modal) return;
        
        const inputs = modal.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name && input.value !== '') {
                formData.set(input.name, input.value);
            }
        });
    }
    
    function validateFormData(data) {
    const required = ['Nome', 'Email', 'cnpj_cpf', 'tipo_pessoa', 'tipocliente'];  // ADICIONADO: tipocliente
    let isValid = true;
    
    for (const field of required) {
        if (!data[field] || data[field].toString().trim() === '') {
            // Destacar campo faltante
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                input.style.borderColor = '#ef4444';
                input.style.background = '#fef2f2';
            }
            isValid = false;
        }
    }
        
        // Validar CPF/CNPJ
        if (data.cnpj_cpf && !validarCpfCnpj(data.cnpj_cpf)) {
            document.getElementById('cnpj_cpf').style.borderColor = '#ef4444';
            showToast('CPF/CNPJ inválido', 'error');
            isValid = false;
        }
        
        // Validar email
        if (data.Email && !validarEmail(data.Email)) {
            document.getElementById('Email').style.borderColor = '#ef4444';
            showToast('E-mail inválido', 'error');
            isValid = false;
        }
        
        return isValid;
    }
    
    // Funções de validação de CPF/CNPJ
    function validarCpfCnpj(valor) {
        const numbers = valor.replace(/\D/g, '');
        if (numbers.length === 11) {
            return validarCPF(numbers);
        } else if (numbers.length === 14) {
            return validarCNPJ(numbers);
        }
        return false;
    }
    
    function validarCPF(cpf) {
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(9))) return false;
        
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        return resto === parseInt(cpf.charAt(10));
    }
    
    function validarCNPJ(cnpj) {
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        
        let tamanho = cnpj.length - 2;
        let numeros = cnpj.substring(0, tamanho);
        const digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i)) * pos--;
            if (pos < 2) pos = 9;
        }
        
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(0))) return false;
        
        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i)) * pos--;
            if (pos < 2) pos = 9;
        }
        
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        return resultado === parseInt(digitos.charAt(1));
    }
    
    function validarEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    async function salvarCliente(formData) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        try {
            
            const response = await fetch('../api_clientes/salvar_clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
        // CORREÇÃO: Map reverso para mostrar nome legível no toast
        const tipoMapReverso = {1: 'Cliente', 2: 'Funcionário', 3: 'Fornecedor', 4: 'Outro'};
        const tipoLegivel = tipoMapReverso[result.cliente.tipocliente] || 'Desconhecido';
        
        showToast(`Cliente "${tipoLegivel}" (${result.cliente.tipocliente}) cadastrado com sucesso! ID: ${result.cliente.id} | Código: ${result.cliente.codcliente} - Redirecionando em 2 segundos...`);
                
                // Aguardar 2 segundos e redirecionar
                setTimeout(() => {
                    window.location.href = '../clientes.php';
                }, 2000);
                
            } else {
                showToast(result.message || 'Erro ao cadastrar cliente', 'error');
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
            document.querySelectorAll('.modal input, .modal select, .modal textarea').forEach(field => {
                if (field.type !== 'button') {
                    field.value = '';
                }
            });
            
            // Resetar previews dos cards
            document.querySelectorAll('.card-preview').forEach(preview => {
                const section = preview.id.replace('-preview', '');
                const defaultTexts = {
                    'endereco': 'Clique para adicionar endereço',
                    'documentos': 'Clique para adicionar documentos',
                    'comercial': 'Clique para configurar dados comerciais',
                    'contas-bancarias': 'Clique para adicionar contas bancárias',
                    'configuracoes': 'Configurações padrão aplicadas'
                };
                preview.innerHTML = `<span class="preview-text">${defaultTexts[section] || 'Clique para configurar'}</span>`;
            });
            
            // Resetar status dos cards
            document.querySelectorAll('.status-badge').forEach(badge => {
                if (badge.id === 'endereco-status') {
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
                if (!['idcliente', 'codcliente', 'Data_cad'].includes(field.name)) {
                    field.remove();
                }
            });
            
            showToast('Formulário limpo com sucesso!');
        }
    };
});

// Funções para gerenciar contas bancárias
window.openModal = function(modalId) {
    const modal = document.getElementById(`modal-${modalId}`);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Se for o modal de contas bancárias, carregar as contas
        if (modalId === 'contas-bancarias') {
            carregarContasBancarias();
        }
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
};

async function carregarContasBancarias() {
    const lista = document.getElementById('contas-lista');
    
    try {
        // Buscar contas da sessão temporária
        const response = await fetch('../api_clientes/contas_bancarias.php?action=listar');
        const result = await response.json();
        
        if (result.success) {
            const contas = result.contas || [];
            
            if (contas.length === 0) {
                lista.innerHTML = '<p style="text-align: center; color: #64748b; padding: 20px;">Nenhuma conta bancária adicionada.</p>';
            } else {
                lista.innerHTML = contas.map(conta => `
                    <div class="conta-item">
                        <div class="conta-info">
                            <div class="conta-tipo">${conta.tipoconta}</div>
                            <div class="conta-detalhes">
                                <span><strong>Banco:</strong> ${conta.banco}</span>
                                <span><strong>Agência:</strong> ${conta.agencia}</span>
                                <span><strong>Conta:</strong> ${conta.nconta}</span>
                                ${conta.chavepix ? `<span><strong>PIX:</strong> ${conta.chavepix}</span>` : ''}
                                ${conta.cpf_titular ? `<span><strong>CPF Titular:</strong> ${conta.cpf_titular}</span>` : ''}
                                ${conta.nome_titular ? `<span><strong>Nome Titular:</strong> ${conta.nome_titular}</span>` : ''}
                            </div>
                        </div>
                        <div class="conta-actions">
                            <button type="button" class="btn btn-sm btn-danger" onclick="excluirConta('${conta.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
            
            // Atualizar preview do card de contas bancárias
            updateContasBancariasPreview(contas);
        } else {
            lista.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 20px;">Erro ao carregar contas bancárias.</p>';
        }
    } catch (error) {
        lista.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 20px;">Erro de conexão.</p>';
    }
}

function updateContasBancariasPreview(contas) {
    const preview = document.getElementById('contas-bancarias-preview');
    const statusBadge = document.getElementById('contas-bancarias-status');
    
    if (contas.length === 0) {
        preview.innerHTML = '<span class="preview-text">Clique para adicionar contas bancárias</span>';
        statusBadge.textContent = 'Opcional';
        statusBadge.style.background = '#e0e7ff';
        statusBadge.style.color = '#3730a3';
    } else {
        const contasText = contas.map(conta => `${conta.banco} (${conta.tipoconta})`).join(', ');
        preview.innerHTML = `<span class="preview-text">${contas.length} conta(s): ${contasText}</span>`;
        statusBadge.textContent = 'Preenchido';
        statusBadge.style.background = '#d1fae5';
        statusBadge.style.color = '#065f46';
    }
}

window.adicionarNovaConta = function() {
    document.getElementById('nova-conta-form').style.display = 'block';
    
    // Limpar campos
    document.getElementById('novo-tipoconta').value = '';
    document.getElementById('novo-banco').value = '';
    document.getElementById('novo-agencia').value = '';
    document.getElementById('novo-nconta').value = '';
    document.getElementById('novo-chavepix').value = '';
    document.getElementById('novo-cpf-titular').value = '';
    document.getElementById('novo-nome-titular').value = '';
};

window.cancelarNovaConta = function() {
    document.getElementById('nova-conta-form').style.display = 'none';
};

window.salvarNovaConta = async function() {
    const dados = {
        tipoconta: document.getElementById('novo-tipoconta').value,
        banco: document.getElementById('novo-banco').value,
        agencia: document.getElementById('novo-agencia').value,
        nconta: document.getElementById('novo-nconta').value,
        chavepix: document.getElementById('novo-chavepix').value,
        cpf_titular: document.getElementById('novo-cpf-titular').value,
        nome_titular: document.getElementById('novo-nome-titular').value
    };
    
    // Validação
    if (!dados.tipoconta || !dados.banco || !dados.agencia || !dados.nconta) {
        showToast('Preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    try {
        const response = await fetch('../api_clientes/contas_bancarias.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'salvar',
                tipoconta: dados.tipoconta,
                banco: dados.banco,
                agencia: dados.agencia,
                nconta: dados.nconta,
                chavepix: dados.chavepix,
                cpf_titular: dados.cpf_titular,
                nome_titular: dados.nome_titular
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conta bancária adicionada na sessão!');
            cancelarNovaConta();
            carregarContasBancarias();
        } else {
            showToast(result.message || 'Erro ao salvar conta bancária', 'error');
        }
    } catch (error) {
        showToast('Erro de conexão com o servidor', 'error');
    }
};

window.excluirConta = async function(id) {
    if (!confirm('Tem certeza que deseja excluir esta conta bancária?')) {
        return;
    }
    
    try {
        const response = await fetch('../api_clientes/contas_bancarias.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'excluir',
                id: id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Conta bancária removida da sessão!');
            carregarContasBancarias();
        } else {
            showToast(result.message || 'Erro ao excluir conta bancária', 'error');
        }
    } catch (error) {
        showToast('Erro de conexão com o servidor', 'error');
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
</script>