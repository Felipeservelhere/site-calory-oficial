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
    header('Location: ../tipopagamentos.php?erro=id_invalido');
    exit;
}

// Buscar o tipo de pagamento APENAS da empresa logada
$sql = "SELECT * FROM tipopagamentos WHERE id = ? AND idcliente = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $idcliente_empresa]);
$tipo_pagamento = $stmt->fetch();

if (!$tipo_pagamento) {
    header('Location: ../tipopagamentos.php?erro=tipopagamento_nao_encontrado');
    exit;
}

// Buscar contas APENAS da empresa logada
$sql_contas = "SELECT id, codconta, descricao FROM contas WHERE idcliente = ? ORDER BY codconta";
$stmt_contas = $pdo->prepare($sql_contas);
$stmt_contas->execute([$idcliente_empresa]);
$contas = $stmt_contas->fetchAll();

// Processar formulário se foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codtppag = trim($_POST['codtppag']);
        $descricao = trim($_POST['Descricao']);
        $prazo = (int)$_POST['prazo'];
        $nconta = !empty($_POST['nconta']) ? (int)$_POST['nconta'] : null;
        
        // Operações
        $atualiza = isset($_POST['atualiza']) ? 'S' : 'N';
        $imprime = isset($_POST['imprime']) ? 'S' : 'N';
        $orcamento = isset($_POST['orcamento']) ? 'S' : 'N';
        $avista = isset($_POST['avista']) ? 'S' : 'N';
        $comissao = isset($_POST['comissao']) ? 'S' : 'N';
        $abre_gaveta = isset($_POST['ABRE_GAVETA']) ? 'S' : 'N';
        
        // Cartão
        $cartao = isset($_POST['cartao']) ? 'S' : 'N';
        $tipo_cartao = $_POST['tipo_cartao'];
        
        // Fiscal
        $cfop1 = trim($_POST['cfop1']);
        $cfop2 = trim($_POST['cfop2']);
        $emite_nfe = isset($_POST['EMITE_NFE']) ? 'S' : 'N';
        $emite_nfce = isset($_POST['EMITE_NFCE']) ? 'S' : 'N';
        
        // Financeiro
        $taxaboleto = (float)$_POST['taxaboleto'];
        $pcomissao = (float)$_POST['pcomissao'];
        $duplicata = isset($_POST['duplicata']) ? 'S' : 'N';
        $bloqueto = isset($_POST['bloqueto']) ? 'S' : 'N';
        $id_banco = !empty($_POST['ID_BANCO']) ? (int)$_POST['ID_BANCO'] : null;
        
        // Avançado
        $condicional = isset($_POST['condicional']) ? 'S' : 'N';
        $locacao = isset($_POST['locacao']) ? 'S' : 'N';
        $troca = isset($_POST['troca']) ? 'S' : 'N';
        $antecipado = isset($_POST['antecipado']) ? 'S' : 'N';
        $devolucao = isset($_POST['devolucao']) ? 'S' : 'N';
        $transferido = isset($_POST['transferido']) ? 'S' : 'N';
        $lotes = isset($_POST['lotes']) ? 'S' : 'N';
        $automatico_entrada = isset($_POST['automatico_entrada']) ? 'S' : 'N';
        $automatico_principal = isset($_POST['automatico_principal']) ? 'S' : 'N';

        // Validações básicas
        if (empty($codtppag)) {
            throw new Exception('Código do tipo de pagamento é obrigatório');
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
        $sql_check = "SELECT id FROM tipopagamentos WHERE codtppag = ? AND idcliente = ? AND id != ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$codtppag, $idcliente_empresa, $id]);
        if ($stmt_check->fetch()) {
            throw new Exception('Já existe um tipo de pagamento com este código na sua empresa');
        }

        // Verificar se a descrição já existe (exceto o atual) APENAS na mesma empresa
        $sql_check_desc = "SELECT id FROM tipopagamentos WHERE Descricao = ? AND idcliente = ? AND id != ?";
        $stmt_check_desc = $pdo->prepare($sql_check_desc);
        $stmt_check_desc->execute([$descricao, $idcliente_empresa, $id]);
        if ($stmt_check_desc->fetch()) {
            throw new Exception('Já existe um tipo de pagamento com esta descrição na sua empresa');
        }

        // Validar prazo
        if ($prazo < 0 || $prazo > 999) {
            throw new Exception('Prazo deve estar entre 0 e 999 dias');
        }

        // Validar taxas
        if ($taxaboleto < 0) {
            throw new Exception('Taxa do boleto não pode ser negativa');
        }

        if ($pcomissao < 0 || $pcomissao > 100) {
            throw new Exception('Percentual de comissão deve estar entre 0 e 100');
        }

        // Validar tipo de cartão
        if (!in_array($tipo_cartao, ['O', 'D', 'C'])) {
            throw new Exception('Tipo de cartão inválido');
        }

        // Validar CFOPs
        if (!empty($cfop1)) {
            $cfop1 = preg_replace('/[^0-9]/', '', $cfop1);
            if (strlen($cfop1) > 5) {
                throw new Exception('CFOP deve ter no máximo 5 dígitos');
            }
        }

        if (!empty($cfop2)) {
            $cfop2 = preg_replace('/[^0-9]/', '', $cfop2);
            if (strlen($cfop2) > 5) {
                throw new Exception('CFOP deve ter no máximo 5 dígitos');
            }
        }

        // Atualizar no banco
        $sql_update = "UPDATE tipopagamentos SET 
                       codtppag = ?, Descricao = ?, prazo = ?, nconta = ?,
                       atualiza = ?, imprime = ?, orcamento = ?, avista = ?, comissao = ?, ABRE_GAVETA = ?,
                       cartao = ?, tipo_cartao = ?,
                       cfop1 = ?, cfop2 = ?, EMITE_NFE = ?, EMITE_NFCE = ?,
                       taxaboleto = ?, pcomissao = ?, duplicata = ?, bloqueto = ?, ID_BANCO = ?,
                       condicional = ?, locacao = ?, troca = ?, antecipado = ?, devolucao = ?,
                       transferido = ?, lotes = ?, automatico_entrada = ?, automatico_principal = ?
                       WHERE id = ? AND idcliente = ?";

        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            $codtppag, $descricao, $prazo, $nconta,
            $atualiza, $imprime, $orcamento, $avista, $comissao, $abre_gaveta,
            $cartao, $tipo_cartao,
            $cfop1, $cfop2, $emite_nfe, $emite_nfce,
            $taxaboleto, $pcomissao, $duplicata, $bloqueto, $id_banco,
            $condicional, $locacao, $troca, $antecipado, $devolucao,
            $transferido, $lotes, $automatico_entrada, $automatico_principal, 
            $id, $idcliente_empresa
        ]);

        // Definir mensagem de sucesso na sessão
        $_SESSION['toast_message'] = 'Tipo de pagamento atualizado com sucesso!';
        $_SESSION['toast_type'] = 'success';
        
        header('Location: ../tipopagamentos.php');
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
    <title>Editar tipo de pagamento</title>
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
                <span class="breadcrumb-item active">Editar Tipo de Pagamento</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Tipo de Pagamento</span>
                    <p class="title-subtitle">Código: <?= htmlspecialchars($tipo_pagamento['codtppag']) ?> - <?= htmlspecialchars($tipo_pagamento['Descricao']) ?></p>
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
            <form id="tipoPagamentoForm" method="POST" class="payment-form">
                
                <!-- Informações Básicas -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Básicas
                        </h2>
                        <p class="section-subtitle">Dados principais do tipo de pagamento</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="codtppag" class="form-label">
                                Código <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="codtppag" name="codtppag" class="form-input" 
                                       value="<?= htmlspecialchars($tipo_pagamento['codtppag']) ?>" 
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
                                       value="<?= htmlspecialchars($tipo_pagamento['Descricao']) ?>" 
                                       maxlength="30" required>
                                <i class="fas fa-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="prazo" class="form-label">Prazo (dias)</label>
                            <div class="input-wrapper">
                                <input type="number" id="prazo" name="prazo" class="form-input" 
                                       value="<?= $tipo_pagamento['prazo'] ?>" min="0" max="999">
                                <i class="fas fa-calendar-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nconta" class="form-label">Conta Caixa/Banco p/Lançto</label>
                            <div class="input-wrapper-with-button">
                                <div class="input-wrapper">
                                    <select id="nconta" name="nconta" class="form-input">
                                        <option value="">Selecione uma conta...</option>
                                        <?php foreach ($contas as $conta): ?>
                                            <option value="<?= $conta['id'] ?>" <?= $tipo_pagamento['nconta'] == $conta['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($conta['codconta']) ?> - <?= htmlspecialchars($conta['descricao']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-university input-icon"></i>
                                </div>
                                <button type="button" class="btn-add-account" id="btnAddAccount" title="Criar nova conta">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações Operacionais -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-cogs"></i>
                            Configurações Operacionais
                        </h2>
                        <p class="section-subtitle">Configurações de funcionamento do tipo de pagamento</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">Atualiza Estoque</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="atualiza" name="atualiza" value="S" <?= $tipo_pagamento['atualiza'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="atualiza-status"><?= $tipo_pagamento['atualiza'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Imprime Comprovante</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="imprime" name="imprime" value="S" <?= $tipo_pagamento['imprime'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="imprime-status"><?= $tipo_pagamento['imprime'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Permite Orçamento</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="orcamento" name="orcamento" value="S" <?= $tipo_pagamento['orcamento'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="orcamento-status"><?= $tipo_pagamento['orcamento'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">À Vista</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="avista" name="avista" value="S" <?= $tipo_pagamento['avista'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="avista-status"><?= $tipo_pagamento['avista'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Gera Comissão</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="comissao" name="comissao" value="S" <?= $tipo_pagamento['comissao'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="comissao-status"><?= $tipo_pagamento['comissao'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Abre Gaveta</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="ABRE_GAVETA" name="ABRE_GAVETA" value="S" <?= $tipo_pagamento['ABRE_GAVETA'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="ABRE_GAVETA-status"><?= $tipo_pagamento['ABRE_GAVETA'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações de Cartão -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Configurações de Cartão
                        </h2>
                        <p class="section-subtitle">Configurações específicas para pagamentos com cartão</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">É Cartão</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="cartao" name="cartao" value="S" <?= $tipo_pagamento['cartao'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="cartao-status"><?= $tipo_pagamento['cartao'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tipo_cartao" class="form-label">Tipo de Cartão</label>
                            <div class="input-wrapper">
                                <select id="tipo_cartao" name="tipo_cartao" class="form-input">
                                    <option value="O" <?= $tipo_pagamento['tipo_cartao'] === 'O' ? 'selected' : '' ?>>Outros</option>
                                    <option value="D" <?= $tipo_pagamento['tipo_cartao'] === 'D' ? 'selected' : '' ?>>Débito</option>
                                    <option value="C" <?= $tipo_pagamento['tipo_cartao'] === 'C' ? 'selected' : '' ?>>Crédito</option>
                                </select>
                                <i class="fas fa-credit-card input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações Fiscais -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-file-invoice"></i>
                            Configurações Fiscais
                        </h2>
                        <p class="section-subtitle">Configurações para emissão de notas fiscais</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cfop1" class="form-label">CFOP Dentro do Estado</label>
                            <div class="input-wrapper">
                                <input type="text" id="cfop1" name="cfop1" class="form-input" 
                                       value="<?= htmlspecialchars($tipo_pagamento['cfop1']) ?>" 
                                       maxlength="5" placeholder="Ex: 5102">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cfop2" class="form-label">CFOP Fora do Estado</label>
                            <div class="input-wrapper">
                                <input type="text" id="cfop2" name="cfop2" class="form-input" 
                                       value="<?= htmlspecialchars($tipo_pagamento['cfop2']) ?>" 
                                       maxlength="5" placeholder="Ex: 6102">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Emite NFe</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="EMITE_NFE" name="EMITE_NFE" value="S" <?= $tipo_pagamento['EMITE_NFE'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="EMITE_NFE-status"><?= $tipo_pagamento['EMITE_NFE'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Emite NFCe</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="EMITE_NFCE" name="EMITE_NFCE" value="S" <?= $tipo_pagamento['EMITE_NFCE'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="EMITE_NFCE-status"><?= $tipo_pagamento['EMITE_NFCE'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações Financeiras -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-dollar-sign"></i>
                            Configurações Financeiras
                        </h2>
                        <p class="section-subtitle">Taxas, comissões e configurações bancárias</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="taxaboleto" class="form-label">Taxa do Boleto (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="taxaboleto" name="taxaboleto" class="form-input" 
                                       value="<?= $tipo_pagamento['taxaboleto'] ?>" step="0.01" min="0">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pcomissao" class="form-label">% Comissão</label>
                            <div class="input-wrapper">
                                <input type="number" id="pcomissao" name="pcomissao" class="form-input" 
                                       value="<?= $tipo_pagamento['pcomissao'] ?>" step="0.01" min="0" max="100">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ID_BANCO" class="form-label">ID do Banco</label>
                            <div class="input-wrapper">
                                <input type="number" id="ID_BANCO" name="ID_BANCO" class="form-input" 
                                       value="<?= $tipo_pagamento['ID_BANCO'] ?>" min="1">
                                <i class="fas fa-university input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Gera Duplicata</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="duplicata" name="duplicata" value="S" <?= $tipo_pagamento['duplicata'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="duplicata-status"><?= $tipo_pagamento['duplicata'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Imprime Boleto?</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="bloqueto" name="bloqueto" value="S" <?= $tipo_pagamento['bloqueto'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="bloqueto-status"><?= $tipo_pagamento['bloqueto'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações Avançadas -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-sliders-h"></i>
                            Configurações Avançadas
                        </h2>
                        <p class="section-subtitle">Opções especiais e configurações adicionais</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label switch-label">Condicional</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="condicional" name="condicional" value="S" <?= $tipo_pagamento['condicional'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="condicional-status"><?= $tipo_pagamento['condicional'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Locação</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="locacao" name="locacao" value="S" <?= $tipo_pagamento['locacao'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="locacao-status"><?= $tipo_pagamento['locacao'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Troca</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="troca" name="troca" value="S" <?= $tipo_pagamento['troca'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="troca-status"><?= $tipo_pagamento['troca'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Antecipado</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="antecipado" name="antecipado" value="S" <?= $tipo_pagamento['antecipado'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="antecipado-status"><?= $tipo_pagamento['antecipado'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Devolução</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="devolucao" name="devolucao" value="S" <?= $tipo_pagamento['devolucao'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="devolucao-status"><?= $tipo_pagamento['devolucao'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Transferido</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="transferido" name="transferido" value="S" <?= $tipo_pagamento['transferido'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="transferido-status"><?= $tipo_pagamento['transferido'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Lotes</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="lotes" name="lotes" value="S" <?= $tipo_pagamento['lotes'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="lotes-status"><?= $tipo_pagamento['lotes'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Automático Entrada</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="automatico_entrada" name="automatico_entrada" value="S" <?= $tipo_pagamento['automatico_entrada'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="automatico_entrada-status"><?= $tipo_pagamento['automatico_entrada'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label switch-label">Automático Principal</label>
                            <div class="switch-wrapper">
                                <label class="switch">
                                    <input type="checkbox" id="automatico_principal" name="automatico_principal" value="S" <?= $tipo_pagamento['automatico_principal'] === 'S' ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span class="switch-status" id="automatico_principal-status"><?= $tipo_pagamento['automatico_principal'] === 'S' ? 'Sim' : 'Não' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="form-actions">
                    <a href="../tipopagamentos.php" class="btn btn-secondary">
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

        <!-- Modal Nova Conta -->
        <div id="modal-nova-conta" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-university"></i> Nova Conta</h3>
                    <button class="modal-close" id="modalClose">×</button>
                </div>
                <div class="modal-body">
                    <form id="contaForm">
                        <div class="form-grid">
                            <div class="form-group form-group-wide">
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
                    <button type="button" class="btn btn-secondary" id="btnCancelConta">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSaveConta">
                        <i class="fas fa-save"></i>
                        Salvar Conta
                    </button>
                </div>
            </div>
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
    max-width: 600px;
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
document.addEventListener('DOMContentLoaded', function() {
    // Setup switches
    setupSwitches();
    
    // Setup modal handlers
    setupModalHandlers();

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
    
    function setupModalHandlers() {
        const modal = document.getElementById('modal-nova-conta');
        const btnAddAccount = document.getElementById('btnAddAccount');
        const modalClose = document.getElementById('modalClose');
        const btnCancelConta = document.getElementById('btnCancelConta');
        const btnSaveConta = document.getElementById('btnSaveConta');
        
        // Abrir modal
        btnAddAccount.addEventListener('click', function() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        // Fechar modal
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('contaForm').reset();
        }
        
        modalClose.addEventListener('click', closeModal);
        btnCancelConta.addEventListener('click', closeModal);
        
        // Salvar conta
        btnSaveConta.addEventListener('click', salvarConta);
        
        // Fechar modal clicando fora
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    }
    
    // Validação do formulário
    const form = document.getElementById('tipoPagamentoForm');
    form.addEventListener('submit', function(e) {
        const codtppag = document.getElementById('codtppag').value.trim();
        const descricao = document.getElementById('Descricao').value.trim();
        
        if (!codtppag) {
            e.preventDefault();
            showToast('Código do tipo de pagamento é obrigatório', 'error');
            document.getElementById('codtppag').focus();
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
    
    // Máscara para CFOP
    const cfopInputs = document.querySelectorAll('#cfop1, #cfop2');
    cfopInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });
});

// Função para salvar conta
async function salvarConta() {
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
            document.getElementById('modal-nova-conta').classList.remove('active');
            document.body.style.overflow = '';
            
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
}

// Função para recarregar contas
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