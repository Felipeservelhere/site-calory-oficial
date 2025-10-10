<?php
// Página de edição de condições de faturamento
include '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

// Verificar se foi passado um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../condicoesfaturamentos.php?mensagem=selecione_condicao');
    exit;
}

$id = (int)$_GET['id'];

// Buscar a condição de faturamento
$sql = "SELECT * FROM condicoes WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$condicao = $stmt->fetch();

if (!$condicao) {
    header('Location: ../condicoesfaturamentos.php?erro=condicao_nao_encontrada');
    exit;
}

// Processar formulário
if ($_POST) {
    try {
        $descricao = trim($_POST['Descricao']);
        $parcelas = (int)$_POST['Parcelas'];
        $condpgto1 = !empty($_POST['CondPgto1']) ? (int)$_POST['CondPgto1'] : null;
        $condpgto2 = !empty($_POST['CondPgto2']) ? (int)$_POST['CondPgto2'] : null;
        $condpgto3 = !empty($_POST['CondPgto3']) ? (int)$_POST['CondPgto3'] : null;
        $condpgto4 = !empty($_POST['CondPgto4']) ? (int)$_POST['CondPgto4'] : null;
        $condpgto5 = !empty($_POST['condpgto5']) ? (int)$_POST['condpgto5'] : null;
        $condpgto6 = !empty($_POST['condpgto6']) ? (int)$_POST['condpgto6'] : null;

        // Validações
        if (empty($descricao)) {
            throw new Exception('A descrição é obrigatória.');
        }

        if ($parcelas < 0 || $parcelas > 12) {
            throw new Exception('O número de parcelas deve estar entre 0 e 12.');
        }

        // Atualizar no banco
        $sql = "UPDATE condicoes SET 
                Descricao = ?, Parcelas = ?,
                CondPgto1 = ?, CondPgto2 = ?, CondPgto3 = ?, CondPgto4 = ?, 
                condpgto5 = ?, condpgto6 = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $descricao, $parcelas,
            $condpgto1, $condpgto2, $condpgto3, $condpgto4, $condpgto5, $condpgto6,
            $id
        ]);

        header('Location: ../condicoesfaturamentos.php?mensagem=editado_sucesso');
        exit;

    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Condições de faturamento</title>
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
                <a href="../condicoesfaturamentos.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Condições de Faturamento
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Condição de Faturamento</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Condição de Faturamento</span>
                    <p class="title-subtitle">Código: <?= htmlspecialchars($condicao['codcond']) ?> - <?= htmlspecialchars($condicao['Descricao']) ?></p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form id="condicaoFaturamentoForm" method="POST" class="payment-form">
                
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
                                <input type="text" id="id" name="id" class="form-input" readonly value="<?= htmlspecialchars($condicao['id']) ?>">
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                            <small class="form-help">ID gerado automaticamente pelo sistema</small>
                        </div>

                        <div class="form-group">
                            <label for="codcond" class="form-label">
                                Código da Condição
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="codcond" name="codcond" class="form-input" readonly value="<?= htmlspecialchars($condicao['codcond']) ?>">
                                <i class="fas fa-code input-icon"></i>
                            </div>
                            <small class="form-help">Código gerado automaticamente baseado no cliente</small>
                        </div>

                        <div class="form-group form-group-wide">
                            <label for="Descricao" class="form-label">
                                Descrição <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="Descricao" name="Descricao" class="form-input" maxlength="30" required value="<?= htmlspecialchars($condicao['Descricao']) ?>">
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Parcelas" class="form-label">
                                Número de Parcelas <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="number" id="Parcelas" name="Parcelas" class="form-input" min="0" max="12" required value="<?= htmlspecialchars($condicao['Parcelas']) ?>">
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
                                <input type="number" id="CondPgto1" name="CondPgto1" class="form-input" min="0" max="999" placeholder="30 Dias" value="<?= $condicao['CondPgto1'] ? htmlspecialchars($condicao['CondPgto1']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto2" class="form-label">Cond.Pgto2</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto2" name="CondPgto2" class="form-input" min="0" max="999" placeholder="60 Dias" value="<?= $condicao['CondPgto2'] ? htmlspecialchars($condicao['CondPgto2']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto3" class="form-label">Cond.Pgto3</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto3" name="CondPgto3" class="form-input" min="0" max="999" placeholder="90 Dias" value="<?= $condicao['CondPgto3'] ? htmlspecialchars($condicao['CondPgto3']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="CondPgto4" class="form-label">Cond.Pgto4</label>
                            <div class="input-wrapper">
                                <input type="number" id="CondPgto4" name="CondPgto4" class="form-input" min="0" max="999" placeholder="120 Dias" value="<?= $condicao['CondPgto4'] ? htmlspecialchars($condicao['CondPgto4']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="condpgto5" class="form-label">Cond.Pgto5</label>
                            <div class="input-wrapper">
                                <input type="number" id="condpgto5" name="condpgto5" class="form-input" min="0" max="999" placeholder="150 Dias" value="<?= $condicao['condpgto5'] ? htmlspecialchars($condicao['condpgto5']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>

                        <div class="parcela-item">
                            <label for="condpgto6" class="form-label">Cond.Pgto6</label>
                            <div class="input-wrapper">
                                <input type="number" id="condpgto6" name="condpgto6" class="form-input" min="0" max="999" placeholder="180 Dias" value="<?= $condicao['condpgto6'] ? htmlspecialchars($condicao['condpgto6']) : '' ?>">
                                <span class="input-suffix">Dias</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../condicoesfaturamentos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar à Lista
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>

                <!-- Campos ocultos para manter os valores originais -->
                <input type="hidden" name="idcliente" value="<?= htmlspecialchars($condicao['idcliente']) ?>">
                <input type="hidden" name="codcond_original" value="<?= htmlspecialchars($condicao['codcond']) ?>">
            </form>
        </div>

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
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 16px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 14px;
    color: #1e40af;
}

.alert-info i {
    color: #6B46C1;
    margin-top: 2px;
}

/* Alertas de erro */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error i {
    color: #dc2626;
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
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: #eff6ff;
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
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #6B46C1 0%, #4d328bff 100%);
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
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('condicaoFaturamentoForm');
    const parcelasInput = document.getElementById('Parcelas');
    
    // Event listener para mudança no número de parcelas
    parcelasInput.addEventListener('change', function() {
        updateParcelasVisibility();
    });
    
    // Inicializar visibilidade das parcelas
    updateParcelasVisibility();
    
    function updateParcelasVisibility() {
        const numParcelas = parseInt(parcelasInput.value) || 0;
        const parcelaItems = document.querySelectorAll('.parcela-item');
        
        parcelaItems.forEach((item, index) => {
            if (index < numParcelas && index < 6) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
                // Limpar valor se estiver oculto
                const input = item.querySelector('input');
                if (input) {
                    input.value = '';
                }
            }
        });
    }
    
    // Validação do formulário
    form.addEventListener('submit', function(e) {
        const descricao = document.getElementById('Descricao').value.trim();
        const parcelas = parseInt(document.getElementById('Parcelas').value);
        
        if (!descricao) {
            alert('Por favor, preencha a descrição.');
            e.preventDefault();
            return;
        }
        
        if (parcelas < 0 || parcelas > 12) {
            alert('O número de parcelas deve estar entre 0 e 12.');
            e.preventDefault();
            return;
        }
        
        // Validar se pelo menos uma condição de pagamento foi preenchida para parcelas > 0
        if (parcelas > 0) {
            let hasCondPgto = false;
            const condInputs = document.querySelectorAll('input[name^="CondPgto"], input[name^="condpgto"]');
            
            condInputs.forEach(input => {
                if (input.value && parseInt(input.value) >= 0) {
                    hasCondPgto = true;
                }
            });
            
            if (!hasCondPgto) {
                alert('Para pagamento parcelado, preencha pelo menos uma condição de pagamento.');
                e.preventDefault();
                return;
            }
        }
    });
    
    // Auto-focus no primeiro campo editável
    document.getElementById('Descricao').focus();
});
</script>