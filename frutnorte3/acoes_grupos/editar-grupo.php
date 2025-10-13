<?php
include '../config/database.php';

// Verificar se foi passado um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../grupos.php?mensagem=selecione_grupo');
    exit;
}
if (!isset($_GET['codgrupo']) || empty($_GET['codgrupo'])) {
    header('Location: ../grupos.php?mensagem=selecione_grupo');
    exit;
}

$grupo_id = (int)$_GET['id'];
$grupo_cod = (int)$_GET['codgrupo'];

// Buscar dados do grupo
$database = new Database();
$pdo = $database->getConnection();

try {
    // Buscar grupo principal
    $sql = "SELECT * FROM grupos WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$grupo_id]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$grupo) {
        header('Location: ../grupos.php?erro=grupo_nao_encontrado');
        exit;
    }
    
    // Buscar contagem de produtos do grupo
    $sql_produtos = "SELECT COUNT(*) as total FROM produtos WHERE codgrupo = ?";
    $stmt_produtos = $pdo->prepare($sql_produtos);
    $stmt_produtos->execute([$grupo_cod]);
    $total_produtos = $stmt_produtos->fetch()['total'];
    
} catch (Exception $e) {
    header('Location: ../grupos.php?erro=erro_banco_dados');
    exit;
}

include '../includes/menu.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Grupo</title>
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
                <a href="../grupos.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-layer-group"></i>
                    Grupos
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Grupo</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Grupo</span>
                    <p class="title-subtitle">Código: <?= str_pad($grupo['codgrupo'], 3, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($grupo['nome']) ?></p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <div class="form-container">
            <form id="grupoForm" class="group-form">
                
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-layer-group"></i>
                            Informações do Grupo
                        </h2>
                        <p class="section-subtitle">Dados básicos do grupo de produtos</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome" class="form-label">
                                Nome do Grupo <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="nome" name="nome" class="form-input" maxlength="100" required 
                                       value="<?= htmlspecialchars($grupo['nome']) ?>" placeholder="Ex: Eletrônicos, Roupas, Livros...">
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="perc_mb" class="form-label">
                                Percentual de Margem Bruta (%)
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_mb" name="perc_mb" class="form-input" step="0.01" min="0" max="999.99" 
                                       value="<?= $grupo['perc_mb'] ?>" placeholder="0,00">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                            <small class="form-help">Margem de lucro aplicada aos produtos deste grupo</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="perc_avista" class="form-label">
                                Percentual À Vista (%)
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_avista" name="perc_avista" class="form-input" step="0.01" min="0" max="999.99" 
                                       value="<?= $grupo['perc_avista'] ?>" placeholder="0,00">
                                <i class="fas fa-money-bill-wave input-icon"></i>
                            </div>
                            <small class="form-help">Desconto para pagamento à vista</small>
                        </div>
                    </div>
                </div>

                <!-- Seção de informações adicionais -->
                <div class="info-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Adicionais
                        </h2>
                        <p class="section-subtitle">Dados estatísticos do grupo</p>
                    </div>
                    
                    <div class="info-cards">
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value"><?= $total_produtos ?></div>
                                <div class="info-label">Produto(s) Associado(s)</div>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value"><?= str_pad($grupo['codgrupo'], 3, '0', STR_PAD_LEFT) ?></div>
                                <div class="info-label">Código do Grupo</div>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value">ID <?= $grupo['id'] ?></div>
                                <div class="info-label">Identificador Único</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../grupos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                    <button type="button" class="btn btn-info" onclick="resetarFormulario()">
                        <i class="fas fa-undo"></i>
                        Resetar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>

                <!-- Campos hidden -->
                <input type="hidden" id="id" name="id" value="<?= $grupo['id'] ?>">
                <input type="hidden" id="codgrupo" name="codgrupo" value="<?= $grupo['codgrupo'] ?>">
                <input type="hidden" id="idcliente" name="idcliente" value="<?= $grupo['idcliente'] ?>">
            </form>
        </div>

    </div>
</div>

<style>
/* Complete CSS styles for the group edit form */
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
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
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

.info-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
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
    color: #fcd34d;
}

.section-subtitle {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

/* Grid de formulário */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    align-items: start;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
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
    border-color: #fcd34d;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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

.form-help {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
    font-style: italic;
}

/* Cards de informação */
.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.info-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.info-card:hover {
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.1);
    border-color: #fcd34d;
}

.info-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}

.info-content {
    flex: 1;
}

.info-value {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 2px;
}

.info-label {
    font-size: 12px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
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
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #fcd34d 0%, #fcd34d 100%);
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

.btn-info {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-info:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    box-shadow: 0 4px 16px rgba(245, 158, 11, 0.4);
    transform: translateY(-1px);
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

.toast.success {
    border-left-color: #10b981;
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
    .info-section {
        padding: 24px 20px;
    }
    
    .form-grid,
    .info-cards {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .form-actions {
        padding: 20px;
        flex-direction: column;
    }
    
    .info-card {
        padding: 16px;
    }
    
    .info-value {
        font-size: 20px;
    }
}
</style>


<script>
// Global variables
let showToast;
let dadosOriginais = {};

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('grupoForm');
    
    // Armazenar dados originais para reset
    armazenarDadosOriginais();
    
    function armazenarDadosOriginais() {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            dadosOriginais[input.id || input.name] = input.value;
        });
    }
    
    function getGrupoIdFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    function getGrupoCodFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('codgrupo');
    }
    
    // Função para mostrar toast
    showToast = function(message, type = 'success') {
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
    };
    
    // Validação de formulário
    function validateForm() {
        const nome = document.getElementById('nome').value.trim();
        
        if (!nome) {
            showToast('Por favor, preencha o nome do grupo.', 'error');
            document.getElementById('nome').focus();
            return false;
        }
        
        if (nome.length < 2) {
            showToast('O nome do grupo deve ter pelo menos 2 caracteres.', 'error');
            document.getElementById('nome').focus();
            return false;
        }
        
        return true;
    }
    
    // Submit do formulário
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        // Pegar o ID da URL
        const grupoId = getGrupoIdFromURL();
        
        if (!grupoId) {
            showToast('ID do grupo não encontrado.', 'error');
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            return;
        }
        
        const formData = {
            id: grupoId,
            nome: document.getElementById('nome').value.trim(),
            perc_mb: document.getElementById('perc_mb').value || null,
            perc_avista: document.getElementById('perc_avista').value || null
        };
        
        try {
            const response = await fetch('../api_grupos/atualizar_grupo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Grupo atualizado com sucesso! Redirecionando...', 'success');
                
                // Aguardar 2 segundos e redirecionar
                setTimeout(() => {
                    window.location.href = '../grupos.php?mensagem=grupo_atualizado';
                }, 2000);
                
            } else {
                showToast(result.message || 'Erro ao atualizar grupo', 'error');
            }
        } catch (error) {
            showToast('Erro de conexão com o servidor', 'error');
        } finally {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    });
    
    // Função global para resetar formulário
    window.resetarFormulario = function() {
        if (confirm('Tem certeza que deseja resetar todos os campos para os valores originais?')) {
            // Restaurar valores originais
            Object.keys(dadosOriginais).forEach(key => {
                const field = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = dadosOriginais[key];
                }
            });
            
            showToast('Formulário resetado para os valores originais!', 'success');
        }
    };
    
    // Validação em tempo real
    const inputs = form.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#ef4444';
                this.style.background = '#fef2f2';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.background = 'white';
            }
        });
        
        input.addEventListener('input', function() {
            if (this.style.borderColor === 'rgb(239, 68, 68)') {
                this.style.borderColor = '#e5e7eb';
                this.style.background = 'white';
            }
        });
    });
});
</script>