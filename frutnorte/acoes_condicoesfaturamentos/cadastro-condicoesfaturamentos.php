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
    $_SESSION['msg'] = "Erro na conexão: " . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../condicoesfaturamentos.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do admin autenticado
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
    $_SESSION['msg'] = "Erro na validação de usuário: " . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../condicoesfaturamentos.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações) ====================
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Falha na conexão com DB operacional.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = "Erro na conexão com banco de dados: " . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../condicoesfaturamentos.php");
    exit;
}

// Verificar se é edição (tem ID na URL)
$condicao = [];
$isEdicao = false;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $isEdicao = true;
    $condicao_id = $_GET['id'];
    
    try {
        // Buscar dados da condição de faturamento, verificando se pertence à empresa logada
        $sql = "SELECT * FROM condicoesfaturamento WHERE id = ? AND idcliente = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$condicao_id, $idcliente_empresa]);
        $condicao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$condicao) {
            $_SESSION['msg'] = "Condição de faturamento não encontrada ou sem permissão para editar.";
            $_SESSION['msg_type'] = "error";
            header("Location: ../condicoesfaturamentos.php");
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['msg'] = "Erro ao carregar dados da condição de faturamento: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
        header("Location: ../condicoesfaturamentos.php");
        exit;
    }
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdicao ? 'Editar Condição de Faturamento' : 'Cadastro de Condições de Faturamento' ?></title>
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
                <a href="../condicoesfaturamentos.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Condições de Faturamento
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Cadastro de Condição de Faturamento</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Cadastro de Condição de Faturamento</span>
                    <p class="title-subtitle">Configure condições de pagamento e parcelamento</p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>
        <div class="form-container">
            <form id="condicaoFaturamentoForm" class="payment-form">
                
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h2>
                        <p class="section-subtitle">Dados principais da condição de faturamento</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="id" class="form-label">
                                ID
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="id" name="id" class="form-input" readonly placeholder="Será gerado automaticamente">
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                            <small class="form-help">O ID será gerado automaticamente pelo sistema</small>
                        </div>

                        <div class="form-group">
                            <label for="codcond" class="form-label">
                                Código da Condição
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="codcond" name="codcond" class="form-input" readonly placeholder="Será gerado automaticamente">
                                <i class="fas fa-code input-icon"></i>
                            </div>
                            <small class="form-help">O código será gerado automaticamente baseado no cliente</small>
                        </div>

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
                            <label for="Parcelas" class="form-label">
                                Número de Parcelas <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="Parcelas" name="Parcelas" class="form-input" min="0" max="12" value="1" required>
                                <i class="fas fa-list-ol input-icon"></i>
                            </div>
                            <small class="form-help">Informe o número de parcelas (0 a 12)</small>
                        </div>
                    </div>
                </div>

                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Condições de Pagamento
                        </h2>
                        <p class="section-subtitle">Configure os prazos para cada parcela em dias</p>
                        <div class="alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span><strong>Atenção:</strong> Acima de 6 parcelas informe as 6 primeiras parcelas normalmente e as próximas o Sistema considera um intervalo de 30 dias entre elas.</span>
                        </div>
                    </div>
                    
                    <div class="parcelas-grid" id="parcelasContainer">
                        <div class="parcela-item">
                            <label for="CondPgto1" class="form-label">Cond.Pgto1</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto1" name="CondPgto1" class="form-input" min="0" max="999" placeholder="30 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto2" class="form-label">Cond.Pgto2</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto2" name="CondPgto2" class="form-input" min="0" max="999" placeholder="60 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto3" class="form-label">Cond.Pgto3</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto3" name="CondPgto3" class="form-input" min="0" max="999" placeholder="90 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto4" class="form-label">Cond.Pgto4</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto4" name="CondPgto4" class="form-input" min="0" max="999" placeholder="120 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="condpgto5" class="form-label">Cond.Pgto5</label>
                            <div class="input-wrapper">
                                <input type="number" id="condpgto5" name="condpgto5" class="form-input" min="0" max="999" placeholder="150 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="condpgto6" class="form-label">Cond.Pgto6</label>
                            <div class="input-wrapper">
                                <input type="number" id="condpgto6" name="condpgto6" class="form-input" min="0" max="999" placeholder="180 Dias">
                                <span class="input-suffix">Dias</span>
                            </div>
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
                        Salvar Condição de Faturamento
                    </button>
                </div>
            </form>
        </div>

        <input type="hidden" id="idcliente" name="idcliente" value="<?= htmlspecialchars($idcliente_empresa) ?>">

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

.main-section:last-child {
    border-bottom: none;
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

.alert-info {
    background: #f3f4f6;
    border: 1px solid #ddd6fe;
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 16px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 14px;
    color: #5b21b6;
}

.alert-info i {
    color: #6B46C1;
    margin-top: 2px;
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

.input-suffix {
    position: absolute;
    right: 14px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
    pointer-events: none;
}

/* Grid de parcelas */
.parcelas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: start;
}

.parcela-item {
    display: flex;
    flex-direction: column;
}

.parcela-item .form-input {
    padding-left: 16px;
    padding-right: 50px;
}

.parcela-item .input-wrapper {
    position: relative;
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
    
    .main-section {
        padding: 24px 20px;
    }
    
    .form-grid,
    .parcelas-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        padding: 20px;
        flex-direction: column;
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
    const form = document.getElementById('condicaoFaturamentoForm');
    const parcelasInput = document.getElementById('Parcelas');
    
    // Event listener para mudança no número de parcelas
    parcelasInput.addEventListener('change', function() {
        updateParcelasVisibility();
    });
    
    // Inicializar visibilidade das parcelas
    updateParcelasVisibility();
    
    // Submit do formulário
    form.addEventListener('submit', handleFormSubmit);
    
    function updateParcelasVisibility() {
        const numParcelas = parseInt(parcelasInput.value) || 1;
        const parcelaItems = document.querySelectorAll('.parcela-item');
        
        parcelaItems.forEach((item, index) => {
            if (index < numParcelas && index < 6) {
                item.style.display = 'flex';
                // Tornar obrigatório se estiver visível
                const input = item.querySelector('input');
                input.required = true;
            } else {
                item.style.display = 'none';
                // Remover obrigatoriedade se estiver oculto
                const input = item.querySelector('input');
                input.required = false;
                input.value = '';
            }
        });
    }
    
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        // Coletar dados do formulário
        const formData = collectFormData();
        
        // Validar campos obrigatórios
        if (!validateFormData(formData)) {
            showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        await salvarCondicaoFaturamento(formData);
    }
    
    function collectFormData() {
        const form = document.getElementById('condicaoFaturamentoForm');
        const formData = new FormData(form);
        
        // Converter para objeto
        const data = {};
        for (let [key, value] of formData.entries()) {
            // Converter valores vazios para null para campos opcionais
            if (key.includes('CondPgto') || key.includes('condpgto')) {
                data[key] = value === '' ? null : parseInt(value);
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }
    
    function validateFormData(data) {
        const required = ['Descricao', 'Parcelas'];
        let isValid = true;
        
        // Limpar estilos de erro anteriores
        document.querySelectorAll('.form-input').forEach(input => {
            input.style.borderColor = '#e5e7eb';
            input.style.background = 'white';
        });
        
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
        
        // Validar se pelo menos uma condição de pagamento foi preenchida
        const numParcelas = parseInt(data.Parcelas) || 1;
        let hasCondPgto = false;
        
        for (let i = 1; i <= Math.min(numParcelas, 6); i++) {
            const fieldName = i <= 4 ? `CondPgto${i}` : `condpgto${i}`;
            if (data[fieldName] !== null && data[fieldName] !== undefined && data[fieldName] !== '') {
                hasCondPgto = true;
                break;
            }
        }
        
        if (!hasCondPgto) {
            showToast('Preencha pelo menos uma condição de pagamento.', 'error');
            isValid = false;
        }
        
        return isValid;
    }
    
    async function salvarCondicaoFaturamento(formData) {
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    try {
        
        const response = await fetch('../api_condicoesfaturamentos/salvar_condicoesfaturamentos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        
        // Primeiro obtenha o texto da resposta
        const responseText = await response.text();
        
        let result;
        try {
            // Tente parsear como JSON
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erro ao parsear JSON:', parseError);
            // Se não for JSON, é um erro do servidor
            throw new Error(`Resposta inválida do servidor: ${responseText.substring(0, 100)}...`);
        }
        
        
        if (result.success) {
            showToast(`Condição de faturamento cadastrada com sucesso! ID: ${result.condicao.id}, Código: ${result.condicao.codcond} - Redirecionando em 2 segundos...`);
            
            setTimeout(() => {
                window.location.href = '../condicoesfaturamentos.php';
            }, 2000);
            
        } else {
            showToast(result.message || 'Erro ao cadastrar condição de faturamento', 'error');
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        showToast('Erro: ' + error.message, 'error');
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
            
            // Resetar valores padrão
            document.getElementById('Parcelas').value = '1';
            
            // Atualizar visibilidade das parcelas
            updateParcelasVisibility();
            
            // Limpar estilos de erro
            document.querySelectorAll('.form-input').forEach(input => {
                input.style.borderColor = '#e5e7eb';
                input.style.background = 'white';
            });
            
            showToast('Formulário limpo com sucesso!');
        }
    };
});
</script>