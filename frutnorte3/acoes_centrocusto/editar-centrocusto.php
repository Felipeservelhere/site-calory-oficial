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
    header("Location: ../centrocusto.php");
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
    header("Location: ../centrocusto.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações) ====================
require_once '../config/database.php';

// Verificar se foi passado um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "Selecione um centro de custo para editar.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../centrocusto.php');
    exit;
}

$id = (int)$_GET['id'];
$errors = [];
$centro = [];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Buscar dados do centro de custo, verificando se pertence à empresa logada
    $sql = "SELECT * FROM centro_custo WHERE id = ? AND idcliente = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $idcliente_empresa]);
    $centro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$centro) {
        $_SESSION['msg'] = "Centro de custo não encontrado ou sem permissão para editar.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../centrocusto.php');
        exit;
    }
    
    // Processar formulário de edição
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? $_POST['ativo'] : 'N';
        
        // Validações
        if (empty($descricao)) {
            $errors[] = 'Descrição é obrigatória';
        }
        
        if (strlen($descricao) > 255) {
            $errors[] = 'Descrição muito longa (máximo 255 caracteres)';
        }
        
        if (empty($errors)) {
            try {
                $sql_update = "UPDATE centro_custo SET 
                              descricao = ?, 
                              ativo = ?,
                              data_alt = NOW()
                              WHERE id = ? AND idcliente = ?";
                
                $stmt_update = $pdo->prepare($sql_update);
                
                if ($stmt_update->execute([$descricao, $ativo, $id, $idcliente_empresa])) {
                    $_SESSION['msg'] = "Centro de custo atualizado com sucesso!";
                    $_SESSION['msg_type'] = "success";
                    header('Location: ../centrocusto.php');
                    exit;
                } else {
                    $errors[] = 'Erro ao atualizar centro de custo';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erro no banco de dados: ' . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    $errors[] = 'Erro no sistema: ' . $e->getMessage();
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Centro de custos</title>
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
                <a href="../centrocusto.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-building"></i>
                    Centros de Custo
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Centro de Custo</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Centro de Custo</span>
                    <p class="title-subtitle">Altere as informações do centro de custo</p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Erro ao salvar:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" class="expense-form">
                
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h2>
                        <p class="section-subtitle">Dados principais do centro de custo</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group form-group-wide">
                            <label for="descricao" class="form-label">
                                Descrição <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="descricao" name="descricao" class="form-input" 
                                       value="<?= htmlspecialchars($centro['descricao']) ?>"
                                       maxlength="255" required>
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                            <small class="form-help">Descrição detalhada do centro de custo</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Status</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="ativo" name="ativo" value="S" 
                                           <?= $centro['ativo'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="ativo-status">
                                    <?= $centro['ativo'] === 'S' ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                            <small class="form-help">Define se o centro de custo está ativo no sistema</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../centrocusto.php" class="btn btn-secondary">
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

    </div>
</div>

<style>
/* Reutilizando os estilos do cadastro de tipos de despesas */
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
    grid-template-columns: 1fr auto;
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
    background-color: #059669;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.switch-status {
    font-size: 14px;
    font-weight: 600;
    color: #6b7280 !important;
    min-width: 50px;
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
    background: linear-gradient(135deg, #4d328bff 0%, #3b2171ff 100%);
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

/* Alertas */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-error i {
    color: #dc2626;
    margin-top: 2px;
}

.alert ul {
    margin: 4px 0 0 0;
    padding-left: 16px;
}

.alert li {
    margin-bottom: 4px;
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
        margin-bottom: 20px;
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
    // Setup switches
    setupSwitches();
    
    function setupSwitches() {
        const switches = document.querySelectorAll('input[type="checkbox"]');
        switches.forEach(switchInput => {
            switchInput.addEventListener('change', function() {
                updateSwitchStatus(this);
            });
            
            // Inicializar status do switch
            updateSwitchStatus(switchInput);
        });
    }
    
    function updateSwitchStatus(switchInput) {
        const statusSpan = document.getElementById(switchInput.id + '-status');
        if (!statusSpan) return;
        
        if (switchInput.checked) {
            statusSpan.textContent = 'Ativo';
            statusSpan.style.color = '#6B46C1';
            switchInput.value = 'S';
        } else {
            statusSpan.textContent = 'Inativo';
            statusSpan.style.color = '#6b7280';
            switchInput.value = 'N';
        }
    }
    
    // Validação em tempo real
    const form = document.querySelector('.expense-form');
    const inputs = form.querySelectorAll('input[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    function validateField(field) {
        const wrapper = field.closest('.input-wrapper') || field.closest('.form-group');
        
        // Remove erro anterior
        wrapper.classList.remove('error');
        field.style.borderColor = '#e5e7eb';
        field.style.background = 'white';
        
        // Validar campo
        let isValid = true;
        
        if (field.hasAttribute('required') && !field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ef4444';
            field.style.background = '#fef2f2';
        }
        
        return isValid;
    }
    
    // Validação antes do submit
    form.addEventListener('submit', function(e) {
        let isFormValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isFormValid = false;
            }
        });
        
        if (!isFormValid) {
            e.preventDefault();
            
            // Scroll para o primeiro erro
            const firstError = form.querySelector('input[style*="border-color: rgb(239, 68, 68)"]');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
});
</script>