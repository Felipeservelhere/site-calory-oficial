<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Grupo</title>
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
                <span class="breadcrumb-item active">Novo Grupo</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Cadastrar Novo Grupo</span>
                    <p class="title-subtitle">Preencha as informações do grupo de produtos</p>
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
                                <input type="text" id="nome" name="nome" class="form-input" maxlength="100" required placeholder="Ex: Eletrônicos, Roupas, Livros...">
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="perc_mb" class="form-label">
                                Percentual de Margem Bruta (%)
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_mb" name="perc_mb" class="form-input" step="0.01" min="0" max="999.99" placeholder="0,00">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                            <small class="form-help">Margem de lucro aplicada aos produtos deste grupo</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="perc_avista" class="form-label">
                                Percentual À Vista (%)
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="perc_avista" name="perc_avista" class="form-input" step="0.01" min="0" max="999.99" placeholder="0,00">
                                <i class="fas fa-money-bill-wave input-icon"></i>
                            </div>
                            <small class="form-help">Desconto para pagamento à vista</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../grupos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                    <button type="button" class="btn btn-info" onclick="limparFormulario()">
                        <i class="fas fa-eraser"></i>
                        Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Grupo
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<style>
/* Complete CSS styles for the group registration form */
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
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    padding: 27px 32px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
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
    color: #8b5cf6;
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    background: #faf5ff;
}

.form-input:invalid {
    border-color: #ef4444;
    background: #fef2f2;
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
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
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
    border-left: 4px solid #8b5cf6;
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
        gap: 20px;
    }
    
    .form-actions {
        padding: 20px;
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('grupoForm');
    
    // Função para mostrar toast
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
        
        const formData = {
            nome: document.getElementById('nome').value.trim(),
            perc_mb: document.getElementById('perc_mb').value || null,
            perc_avista: document.getElementById('perc_avista').value || null
        };
        
        try {
            const response = await fetch('../api_grupos/salvar_grupo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Grupo cadastrado com sucesso! Redirecionando...', 'success');
                
                // Aguardar 2 segundos e redirecionar
                setTimeout(() => {
                    window.location.href = '../grupos.php?mensagem=grupo_salvo';
                }, 2000);
                
            } else {
                showToast(result.message || 'Erro ao cadastrar grupo', 'error');
            }
        } catch (error) {
            showToast('Erro de conexão com o servidor', 'error');
        } finally {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    });
    
    // Função global para limpar formulário
    window.limparFormulario = function() {
        if (confirm('Tem certeza que deseja limpar todos os campos?')) {
            form.reset();
            
            // Remover estilos de validação
            const inputs = form.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.style.borderColor = '#e5e7eb';
                input.style.background = 'white';
            });
            
            showToast('Formulário limpo com sucesso!', 'info');
            document.getElementById('nome').focus();
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
    
    // Focar no primeiro campo
    document.getElementById('nome').focus();
});
</script>