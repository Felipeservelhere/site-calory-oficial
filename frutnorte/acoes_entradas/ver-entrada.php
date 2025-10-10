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

// Verificar se foi passado o ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "ID da entrada não informado.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../entradas.php?erro=entrada_nao_encontrada');
    exit;
}
$codentrada = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Buscar dados da entrada (com filtro por empresa_id)
    $sql_entrada = "SELECT e.*, c.Nome as fornecedor_nome, c.cnpj_cpf as fornecedor_documento,
                           c.Fantasia as fornecedor_fantasia, c.Email as fornecedor_email,
                           c.Fone as fornecedor_telefone, c.Endereco as fornecedor_endereco,
                           c.Cidade as fornecedor_cidade, c.Uf as fornecedor_uf,
                           c.CEP as fornecedor_cep, c.IE as fornecedor_ie,
                           t.Nome as transportadora_nome, t.cnpj_cpf as transportadora_documento,
                           tp.Descricao as tipo_pagamento_desc,
                           cond.Descricao as condicao_pagamento_desc,
                           td.Descricao as tipo_despesa_desc
                    FROM entradas e 
                    LEFT JOIN clientes c ON e.Codcliente = c.codcliente AND c.idcliente = ?
                    LEFT JOIN clientes t ON e.codtransp = t.codcliente AND t.idcliente = ?
                    LEFT JOIN tipopagamentos tp ON e.codtppag = tp.codtppag AND tp.idcliente = ?
                    LEFT JOIN condicoes cond ON e.codcond = cond.codcond AND cond.idcliente = ?
                    LEFT JOIN tipodespesas td ON e.codtpdes = td.codtpdes AND td.idcliente = ?
                    WHERE e.codentrada = ? AND e.idcliente = ?";
    
    $stmt_entrada = $pdo->prepare($sql_entrada);
    $stmt_entrada->execute([
        $idcliente_empresa, // fornecedor
        $idcliente_empresa, // transportadora
        $idcliente_empresa, // tipo pagamento
        $idcliente_empresa, // condição pagamento
        $idcliente_empresa, // tipo despesa
        $codentrada,        // código entrada
        $idcliente_empresa  // empresa da entrada
    ]);
    $entrada = $stmt_entrada->fetch();
    
    if (!$entrada) {
        $_SESSION['msg'] = "Entrada não encontrada ou sem permissão de acesso.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../entradas.php?erro=entrada_nao_encontrada');
        exit;
    }
    
    // Buscar itens da entrada (com filtro por empresa_id)
    $sql_itens = "SELECT ei.*, p.nome as produto_nome, p.descricao_reduzida
                  FROM entradas_itens ei
                  LEFT JOIN produtos p ON ei.Codproduto = p.codproduto AND p.idcliente = ?
                  WHERE ei.codentrada = ? AND ei.idcliente = ?
                  ORDER BY ei.seq";
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$idcliente_empresa, $codentrada, $idcliente_empresa]);
    $itens = $stmt_itens->fetchAll();
    
    // Buscar centros de custo (com filtro por empresa_id)
    $sql_cc = "SELECT ecc.*, cc.descricao as cc_descricao
               FROM entradas_cc ecc
               LEFT JOIN centro_custo cc ON ecc.codcc = cc.codigo AND cc.idcliente = ?
               WHERE ecc.codentrada = ? AND ecc.idcliente = ?";
    
    $stmt_cc = $pdo->prepare($sql_cc);
    $stmt_cc->execute([$idcliente_empresa, $codentrada, $idcliente_empresa]);
    $centros_custo = $stmt_cc->fetchAll();
    
    // Buscar descontos (com filtro por empresa_id)
    $sql_desc = "SELECT * FROM entradas_descontos 
                 WHERE codentrada = ? AND idcliente = ?
                 ORDER BY seq";
    
    $stmt_desc = $pdo->prepare($sql_desc);
    $stmt_desc->execute([$codentrada, $idcliente_empresa]);
    $descontos = $stmt_desc->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['msg'] = "Erro no banco de dados: " . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header('Location: ../entradas.php?erro=erro_banco_dados');
    exit;
}

// Função para formatar valor
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    if (empty($data) || $data === '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($data));
}
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Entrada #<?= $codentrada ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="main-content">
    <div class="content-area">
        <!-- Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../index.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">/</span>
                <a href="../entradas.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-sign-in-alt"></i>
                    Entradas
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Visualizar Entrada</span>
            </div>
            <div class="header-content">
                <div class="title-section">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="title-content">
                            <span class="title-main">Entrada #<?= str_pad($entrada['codentrada'], 6, '0', STR_PAD_LEFT) ?></span>
                            <p class="title-subtitle">
                                <?= $entrada['tipooperacao'] === 'D' ? 'Devolução' : 'Entrada' ?> - 
                                <?= formatarData($entrada['Dataentrada']) ?>
                            </p>
                        </div>
                    </h1>
                </div>
                <div class="header-actions">
                    <a href="../entradas.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                    <a href="editar-entrada.php?id=<?= $entrada['codentrada'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Editar
                    </a>
                </div>
            </div>
        </div>

        <!-- Informações da Entrada -->
        <div class="info-container">
            <!-- Informações Gerais -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Informações Gerais</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Código da Entrada:</label>
                            <span class="value"><?= str_pad($entrada['codentrada'], 6, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Tipo de Operação:</label>
                            <span class="badge <?= $entrada['tipooperacao'] === 'D' ? 'badge-warning' : 'badge-success' ?>">
                                <?= $entrada['tipooperacao'] === 'D' ? 'Devolução' : 'Entrada' ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Data de Entrada:</label>
                            <span class="value"><?= formatarData($entrada['Dataentrada']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Ano/Safra:</label>
                            <span class="value"><?= htmlspecialchars($entrada['ano_safra'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Número da Nota:</label>
                            <span class="value"><?= htmlspecialchars($entrada['numeronota'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Série:</label>
                            <span class="value"><?= htmlspecialchars($entrada['serienota'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Data da Nota:</label>
                            <span class="value"><?= formatarData($entrada['dtnota']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Chave NFE:</label>
                            <span class="value nfe-key"><?= htmlspecialchars($entrada['NumChaveNfe'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações do Fornecedor -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Fornecedor</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Nome:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Fantasia:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_fantasia'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Documento:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_documento'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>IE:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_ie'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Telefone:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_telefone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span class="value"><?= htmlspecialchars($entrada['fornecedor_email'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item full-width">
                            <label>Endereço:</label>
                            <span class="value">
                                <?= htmlspecialchars($entrada['fornecedor_endereco'] ?? 'N/A') ?> - 
                                <?= htmlspecialchars($entrada['fornecedor_cidade'] ?? '') ?>/<?= htmlspecialchars($entrada['fornecedor_uf'] ?? '') ?> - 
                                CEP: <?= htmlspecialchars($entrada['fornecedor_cep'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações de Pagamento -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Pagamento e Condições</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Tipo de Pagamento:</label>
                            <span class="value"><?= htmlspecialchars($entrada['tipo_pagamento_desc'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Condição de Pagamento:</label>
                            <span class="value"><?= htmlspecialchars($entrada['condicao_pagamento_desc'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Tipo de Despesa:</label>
                            <span class="value"><?= htmlspecialchars($entrada['tipo_despesa_desc'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Pedido:</label>
                            <span class="value"><?= htmlspecialchars($entrada['Pedido'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações de Transporte -->
            <?php if (!empty($entrada['transportadora_nome']) || !empty($entrada['placa'])): ?>
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-truck"></i> Transporte</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Transportadora:</label>
                            <span class="value"><?= htmlspecialchars($entrada['transportadora_nome'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Documento:</label>
                            <span class="value"><?= htmlspecialchars($entrada['transportadora_documento'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Placa do Veículo:</label>
                            <span class="value"><?= htmlspecialchars($entrada['placa'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Valor do Frete:</label>
                            <span class="value"><?= formatarValor($entrada['total_frete'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Totais -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-calculator"></i> Totais</h3>
                </div>
                <div class="card-content">
                    <div class="totals-grid">
                        <div class="total-item">
                            <label>Valor dos Produtos:</label>
                            <span class="total-value"><?= formatarValor($entrada['vrprodutos'] ?? 0) ?></span>
                        </div>
                        <div class="total-item">
                            <label>Frete:</label>
                            <span class="total-value"><?= formatarValor($entrada['total_frete'] ?? 0) ?></span>
                        </div>
                        <div class="total-item">
                            <label>Desconto:</label>
                            <span class="total-value"><?= formatarValor($entrada['vrdesconto'] ?? 0) ?></span>
                        </div>
                        <div class="total-item">
                            <label>ICMS:</label>
                            <span class="total-value"><?= formatarValor($entrada['vricms'] ?? 0) ?></span>
                        </div>
                        <div class="total-item">
                            <label>IPI:</label>
                            <span class="total-value"><?= formatarValor($entrada['vripi'] ?? 0) ?></span>
                        </div>
                        <div class="total-item total-final">
                            <label>Total Geral:</label>
                            <span class="total-value final"><?= formatarValor($entrada['vrTotal'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Itens da Entrada -->
            <div class="info-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-boxes"></i> Itens da Entrada (<?= count($itens) ?>)</h3>
                </div>
                <div class="card-content">
                    <?php if (!empty($itens)): ?>
                        <div class="table-responsive">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Seq</th>
                                        <th>Código</th>
                                        <th>Produto</th>
                                        <th>Un</th>
                                        <th>Qtd</th>
                                        <th>Vr. Unit.</th>
                                        <th>Total</th>
                                        <th>CFOP</th>
                                        <th>ICMS</th>
                                        <th>IPI</th>
                                        <th>PIS</th>
                                        <th>COFINS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?= $item['seq'] ?></td>
                                        <td><?= htmlspecialchars($item['Codproduto']) ?></td>
                                        <td class="product-name">
                                            <strong><?= htmlspecialchars($item['produto_nome'] ?? 'Produto não encontrado') ?></strong>
                                            <?php if (!empty($item['descricao_reduzida'])): ?>
                                                <small><?= htmlspecialchars($item['descricao_reduzida']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['Un']) ?></td>
                                        <td><?= number_format($item['Qtd'], 2, ',', '.') ?></td>
                                        <td><?= formatarValor($item['Vrunit']) ?></td>
                                        <td><?= formatarValor($item['Total']) ?></td>
                                        <td><?= htmlspecialchars($item['cfop']) ?></td>
                                        <td>
                                            <small>ST: <?= htmlspecialchars($item['sticms']) ?></small><br>
                                            <small><?= $item['percicms'] ?>% = <?= formatarValor($item['vricms']) ?></small>
                                        </td>
                                        <td>
                                            <small>ST: <?= htmlspecialchars($item['stipi']) ?></small><br>
                                            <small><?= $item['percipi'] ?>% = <?= formatarValor($item['vripi']) ?></small>
                                        </td>
                                        <td>
                                            <small>ST: <?= htmlspecialchars($item['stpis']) ?></small><br>
                                            <small><?= $item['ppis'] ?>% = <?= formatarValor($item['vl_pis']) ?></small>
                                        </td>
                                        <td>
                                            <small>ST: <?= htmlspecialchars($item['stcofins']) ?></small><br>
                                            <small><?= $item['pcofins'] ?>% = <?= formatarValor($item['vl_cofins']) ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>Nenhum item encontrado para esta entrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Centros de Custo -->
            <?php if (!empty($centros_custo)): ?>
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Centros de Custo</h3>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descrição</th>
                                    <th>Placa</th>
                                    <th>Valor</th>
                                    <th>Observações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($centros_custo as $cc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cc['codcc']) ?></td>
                                    <td><?= htmlspecialchars($cc['cc_descricao'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($cc['placa'] ?? 'N/A') ?></td>
                                    <td><?= formatarValor($cc['valor']) ?></td>
                                    <td><?= htmlspecialchars($cc['obs'] ?? 'N/A') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Descontos -->
            <?php if (!empty($descontos)): ?>
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-percentage"></i> Descontos</h3>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="simple-table">
                            <thead>
                                <tr>
                                    <th>Seq</th>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($descontos as $desconto): ?>
                                <tr>
                                    <td><?= $desconto['seq'] ?></td>
                                    <td><?= formatarData($desconto['datalcto']) ?></td>
                                    <td><?= htmlspecialchars($desconto['descricao']) ?></td>
                                    <td><?= formatarValor($desconto['valor']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Observações -->
            <?php if (!empty($entrada['obs'])): ?>
            <div class="info-card full-width">
                <div class="card-header">
                    <h3><i class="fas fa-sticky-note"></i> Observações</h3>
                </div>
                <div class="card-content">
                    <div class="observation-text">
                        <?= nl2br(htmlspecialchars($entrada['obs'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Estilos para visualização de entrada */
* {
    box-sizing: border-box;
}

.main-content {
    padding: 0;
    background: #f8fafc;
    min-height: 100vh;
}

.content-area {
    margin-top: 50px !important;
    max-width: 1400px;
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
    margin-bottom: 16px;
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

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    margin: 0;
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

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Botões */
.btn {
    padding: 12px 20px;
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
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #069b6c;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c8ffee 0%, #ffffffff 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background: linear-gradient(135deg, #ffffff 0%, #e5fff7ff 100%);
    color: #069b6c;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #c8f4ffff 0%, #ffffffff 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

/* Container de informações */
.info-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
}

.info-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.info-card.full-width {
    grid-column: 1 / -1;
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header h3 i {
    color: #10b981;
}

.card-content {
    padding: 24px;
}

/* Grid de informações */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-item label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-item .value {
    font-size: 14px;
    color: #0f172a;
    font-weight: 500;
}

.nfe-key {
    font-family: monospace;
    background: #f1f5f9;
    padding: 8px;
    border-radius: 6px;
    font-size: 12px;
    word-break: break-all;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

/* Grid de totais */
.totals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.total-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.total-item.total-final {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-color: #10b981;
}

.total-item label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.total-final label {
    color: white;
}

.total-value {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.total-value.final {
    color: white;
    font-size: 18px;
}

/* Tabelas */
.table-responsive {
    overflow-x: auto;
    margin: -24px;
    padding: 24px;
}

.items-table, .simple-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.items-table th, .simple-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #e2e8f0;
}

.items-table td, .simple-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #374151;
    vertical-align: top;
}

.items-table tr:hover, .simple-table tr:hover {
    background: #f8fafc;
}

.product-name strong {
    color: #0f172a;
    display: block;
    margin-bottom: 4px;
}

.product-name small {
    color: #6b7280;
    font-size: 11px;
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
    color: #10b981;
}

.empty-state p {
    font-size: 14px;
    margin: 0;
}

/* Observações */
.observation-text {
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #10b981;
    font-size: 14px;
    line-height: 1.6;
    color: #374151;
}

/* Responsividade */
@media (max-width: 768px) {
    .content-area {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: space-between;
    }
    
    .title-main {
        font-size: 20px;
    }
    
    .info-container {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .totals-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .card-content {
        padding: 20px;
    }
    
    .items-table, .simple-table {
        min-width: 600px;
    }
}
</style>