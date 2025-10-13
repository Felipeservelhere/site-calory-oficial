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

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Include database connection
include '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// Verificar se foi passado um ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ../tipodespesas.php?erro=id_invalido');
    exit;
}

// Buscar o tipo de despesa APENAS da empresa logada
$sql = "SELECT * FROM tipodespesas WHERE id = ? AND idcliente = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $idcliente_empresa]);
$tipo_despesa = $stmt->fetch();

if (!$tipo_despesa) {
    header('Location: ../tipodespesas.php?erro=tipo_nao_encontrado');
    exit;
}

// Processar formulário se foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codtpdes = trim($_POST['codtpdes']);
        $descricao = trim($_POST['Descricao']);
        $insumos = isset($_POST['insumos']) ? 'S' : 'N';

        // Validações básicas
        if (empty($codtpdes)) {
            throw new Exception('Código do tipo de despesa é obrigatório');
        }
        if (empty($descricao)) {
            throw new Exception('Descrição é obrigatória');
        }

        // Validar descrição
        if (strlen($descricao) > 30) {
            throw new Exception('Descrição deve ter no máximo 30 caracteres');
        }

        // Validar caracteres na descrição
        if (!preg_match('/^[a-zA-Z0-9\sÀ-ÿ.,\-_]+$/u', $descricao)) {
            throw new Exception('Descrição contém caracteres inválidos');
        }

        // Verificar se o código já existe (exceto o atual) APENAS na mesma empresa
        $sql_check = "SELECT id FROM tipodespesas WHERE codtpdes = ? AND idcliente = ? AND id != ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$codtpdes, $idcliente_empresa, $id]);
        if ($stmt_check->fetch()) {
            throw new Exception('Já existe um tipo de despesa com este código na sua empresa');
        }

        // Verificar se a descrição já existe (exceto o atual) APENAS na mesma empresa
        $sql_check_desc = "SELECT id FROM tipodespesas WHERE Descricao = ? AND idcliente = ? AND id != ?";
        $stmt_check_desc = $pdo->prepare($sql_check_desc);
        $stmt_check_desc->execute([$descricao, $idcliente_empresa, $id]);
        if ($stmt_check_desc->fetch()) {
            throw new Exception('Já existe um tipo de despesa com esta descrição na sua empresa');
        }

        // Atualizar no banco
        $sql_update = "UPDATE tipodespesas SET 
                       codtpdes = ?, Descricao = ?, insumos = ?
                       WHERE id = ? AND idcliente = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$codtpdes, $descricao, $insumos, $id, $idcliente_empresa]);

        // Definir mensagem de sucesso na sessão
        $_SESSION['toast_message'] = 'Tipo de despesa atualizado com sucesso!';
        $_SESSION['toast_type'] = 'success';

        header("Location: ../tipodespesas.php?id=$id");
        exit;

    } catch (Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = 'error';
        header("Location: editar_tipodespesa.php?id=$id");
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
    <title>Editar Tipo de despesa</title>
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
                <a href="../tipodespesas.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-receipt"></i>
                    Tipos de Despesas
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Tipo de Despesa</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Tipo de Despesa</span>
                    <p class="title-subtitle">Código: <?= htmlspecialchars($tipo_despesa['codtpdes']) ?> - <?= htmlspecialchars($tipo_despesa['Descricao']) ?></p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form id="tipoDespesaForm" method="POST" class="expense-form">
                
                <!-- Informações Básicas -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h2>
                        <p class="section-subtitle">Dados principais do tipo de despesa</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group1">
                            <div class="input-wrapper">
                                <input type="text" id="codtpdes" name="codtpdes" class="form-input" 
                                       value="<?= htmlspecialchars($tipo_despesa['codtpdes']) ?>" 
                                       maxlength="11" required>
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group form-group-wide">
                            <label for="Descricao" class="form-label">
                                Descrição <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="Descricao" name="Descricao" class="form-input" 
                                       value="<?= htmlspecialchars($tipo_despesa['Descricao']) ?>" 
                                       maxlength="30" required>
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Insumos</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="insumos" name="insumos" value="S" <?= $tipo_despesa['insumos'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="insumos-status"><?= $tipo_despesa['insumos'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                            <small class="form-help">Indica se este tipo de despesa é relacionado a insumos</small>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="form-actions">
                    <a href="../tipodespesas.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>

        <input type="hidden" id="idcliente" name="idcliente" value="382">

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

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
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

.main-section:last-of-type {
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

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
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
    
    .form-grid {
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
document.addEventListener('DOMContentLoaded', function() {
    // Se houver mensagem do PHP
    <?php if (!empty($success) && $success): ?>
        const message = <?= json_encode($_SESSION['toast_message'] ?? '') ?>;
        const type = <?= json_encode($_SESSION['toast_type'] ?? 'success') ?>;
        if(message) {
            showToast(message, type);

            // Redirecionar após 3 segundos
            setTimeout(() => {
                window.location.href = '../tipodespesas.php';
            }, 3000);
        }

        <?php 
        unset($_SESSION['toast_message'], $_SESSION['toast_type']); 
        ?>
    <?php endif; ?>
});

document.addEventListener('DOMContentLoaded', function() {
    // Setup switches
    setupSwitches();

    // Função para mostrar toast
    window.showToast = function(message, type = 'success') {
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
    
    function setupSwitches() {
        const switches = document.querySelectorAll('input[type="checkbox"]');
        switches.forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                updateSwitchStatus(this);
            });
            
            // Inicializar status
            updateSwitchStatus(switchInput);
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
    
    // Validação do formulário
    const form = document.getElementById('tipoDespesaForm');
    form.addEventListener('submit', function(e) {
        const codtpdes = document.getElementById('codtpdes').value.trim();
        const descricao = document.getElementById('Descricao').value.trim();
        
        if (!codtpdes) {
            e.preventDefault();
            showToast('Código do tipo de despesa é obrigatório', 'error');
            document.getElementById('codtpdes').focus();
            return;
        }
        
        if (!descricao) {
            e.preventDefault();
            showToast('Descrição é obrigatória', 'error');
            document.getElementById('Descricao').focus();
            return;
        }
        
        // Mostrar loading no botão
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
    });
});
</script>