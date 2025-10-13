<?php
// Editar entrada completa - com modais funcionais e dados carregados
session_start(); // ADICIONADO: Inicia a sessão

// ==================== VERIFICAÇÃO DE LOGIN E EMPRESA ====================
// ADICIONADO: Verificação de login e empresa_id para definir idcliente
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id']) || !is_numeric($_SESSION['empresa_id']) || (int)$_SESSION['empresa_id'] <= 0) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

$idcliente = (int)$_SESSION['empresa_id']; // ADICIONADO: Define idcliente da sessão
error_log("Editar Entrada - IDCLIENTE da sessão: {$idcliente} (" . date('Y-m-d H:i:s') . ")");

// Configuração do banco de dados
$db_config = [
    'host' => 'localhost',
    'dbname' => 'frutnorte',
    'username' => 'root',
    'password' => '@@rOOt@cAlOry@1967@@'
];

// Inicialização de variáveis
$mensagem = '';
$tipo_mensagem = 'success';
$nfe_data = null;
$entrada_data = null;

function conectarBanco() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Editar Entrada - Erro na conexão com o banco para idcliente={$GLOBALS['idcliente']} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        throw new Exception("Erro na conexão com o banco: " . $e->getMessage());
    }
}

// Verificar se foi fornecido um ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "ID da entrada não fornecido.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../entradas.php');
    exit;
}

$entrada_id = (int)$_GET['id'];

// Carregar dados da entrada existente - ADICIONADO: Filtros por idcliente
try {
    $pdo = conectarBanco();
    error_log("Editar Entrada - Carregando dados para codentrada={$entrada_id} e idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    // Buscar dados da entrada principal - ADICIONADO: Filtro por idcliente e joins com filtro
    $sql_entrada = "SELECT e.*, c.Nome as fornecedor_nome, c.codcliente as fornecedor_codigo,
                           t.Nome as transportadora_nome, t.codcliente as transportadora_codigo,
                           tp.Descricao as tipo_pagamento_nome,
                           cond.Descricao as condicao_nome,
                           td.Descricao as tipo_despesa_nome
                    FROM entradas e
                    LEFT JOIN clientes c ON e.Codcliente = c.codcliente AND c.idcliente = ?
                    LEFT JOIN clientes t ON e.codtransp = t.codcliente AND t.idcliente = ?
                    LEFT JOIN tipopagamentos tp ON e.codtppag = tp.codtppag AND tp.idcliente = ?
                    LEFT JOIN condicoes cond ON e.codcond = cond.codcond AND cond.idcliente = ?
                    LEFT JOIN tipodespesas td ON e.codtpdes = td.codtpdes AND td.idcliente = ?
                    WHERE e.codentrada = ? AND e.idcliente = ?"; // ADICIONADO: AND e.idcliente = ?
    
    $stmt_entrada = $pdo->prepare($sql_entrada);
    $stmt_entrada->execute([$idcliente, $idcliente, $idcliente, $idcliente, $idcliente, $entrada_id, $idcliente]); // ADICIONADO: Parâmetros para idcliente
    $entrada_data = $stmt_entrada->fetch(PDO::FETCH_ASSOC);
    
    if (!$entrada_data) {
        error_log("Editar Entrada - Entrada não encontrada para codentrada={$entrada_id} e idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
        $_SESSION['msg'] = "Entrada não encontrada ou você não tem permissão para editá-la.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../entradas.php');
        exit;
    }
    
    error_log("Editar Entrada - Entrada carregada com sucesso para idcliente={$idcliente}: " . print_r($entrada_data, true) . " (" . date('Y-m-d H:i:s') . ")");
    
    // Buscar itens da entrada - ADICIONADO: Filtro por idcliente
    $sql_itens = "SELECT ei.*, p.nome as produto_nome
                  FROM entradas_itens ei
                  LEFT JOIN produtos p ON ei.Codproduto = p.codproduto AND p.idcliente = ?
                  WHERE ei.codentrada = ? AND ei.idcliente = ?"; // ADICIONADO: AND ei.idcliente = ?
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$idcliente, $entrada_id, $idcliente]); // ADICIONADO: Parâmetro para idcliente
    $itens_entrada = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Editar Entrada - Itens carregados: " . count($itens_entrada) . " itens para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    // Buscar centros de custo - ADICIONADO: Filtro por idcliente
    $sql_cc = "SELECT cc.*, c.descricao as centro_custo_nome 
               FROM entradas_cc cc
               LEFT JOIN centro_custo c ON cc.codcc = c.codigo AND c.idcliente = ?
               WHERE cc.codentrada = ? AND cc.idcliente = ?"; // ADICIONADO: AND cc.idcliente = ?
    $stmt_cc = $pdo->prepare($sql_cc);
    $stmt_cc->execute([$idcliente, $entrada_id, $idcliente]); // ADICIONADO: Parâmetro para idcliente
    $centros_custo_entrada = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Editar Entrada - Centros de custo carregados: " . count($centros_custo_entrada) . " itens para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    // Buscar descontos - ADICIONADO: Filtro por idcliente
    $sql_desc = "SELECT * FROM entradas_descontos WHERE codentrada = ? AND idcliente = ? ORDER BY seq"; // ADICIONADO: AND idcliente = ?
    $stmt_desc = $pdo->prepare($sql_desc);
    $stmt_desc->execute([$entrada_id, $idcliente]); // ADICIONADO: Parâmetro para idcliente
    $descontos_entrada = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Editar Entrada - Descontos carregados: " . count($descontos_entrada) . " itens para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
} catch (Exception $e) {
    error_log("Editar Entrada - Erro ao carregar dados para codentrada={$entrada_id} e idcliente={$idcliente} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
    $_SESSION['msg'] = "Erro ao carregar dados da entrada.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../entradas.php');
    exit;
}

$entrada_numero = $entrada_data['codentrada']; // só 4

// Função para formatar data para input HTML - APRIMORADA: Mais robusta para datas inválidas
function formatDateForInput($date) {
    global $idcliente; // Para logs, se necessário
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    
    // Se já está no formato correto, retorna
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    
    // Se tem timestamp, extrai apenas a data
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $matches)) {
        return $matches[1];
    }
    
    // Tenta converter outros formatos
    $timestamp = strtotime($date);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    error_log("Editar Entrada - Data inválida para formatação (idcliente={$idcliente}): {$date} (" . date('Y-m-d H:i:s') . ")");
    return '';
}

// ADICIONADO: Função para preparar dados para o formulário (ex.: valores hidden ou displays)
function prepararDadosForm($dados) {
    $form_data = $dados;
    $form_data['data_entrada'] = formatDateForInput($dados['Dataentrada'] ?? '');
    $form_data['dt_emissao'] = formatDateForInput($dados['dtnota'] ?? '');
    $form_data['supplier_id'] = $dados['fornecedor_codigo'] ?? $dados['Codcliente'] ?? '';
    $form_data['supplier_search'] = $dados['fornecedor_nome'] ?? '';
    $form_data['transporter_id'] = $dados['transportadora_codigo'] ?? $dados['codtransp'] ?? '';
    $form_data['transporter_search'] = $dados['transportadora_nome'] ?? '';
    $form_data['total_produtos'] = $dados['vrprodutos'] ?? 0;
    $form_data['total_frete'] = $dados['total_frete'] ?? 0;
    $form_data['total_desconto'] = $dados['vrdesconto'] ?? 0;
    $form_data['total_geral'] = $dados['vrTotal'] ?? 0;
    $form_data['total_icms'] = $dados['vricms'] ?? 0;
    $form_data['total_ipi'] = $dados['vripi'] ?? 0;
    $form_data['devolucao'] = $dados['tipooperacao'] === 'D' ? 'checked' : '';
    return $form_data;
}

$entrada_form_data = prepararDadosForm($entrada_data); // ADICIONADO: Prepara dados para o form
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Entrada #<?= $entrada_numero ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Mesmo CSS do sistema original - entrada_produtos_tabela_melhorada_frete_corrigido.php */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
            min-height: 100vh;
            padding: 20px;
        }

        .main-content {
            padding: 0;
            background: transparent;
        }

        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        /* Header */
        .page-header-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 20px 32px;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header-compact::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
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

        .header-title {
            font-size: 24px;
            font-weight: 700;
        }

        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        /* Breadcrumb */
        .breadcrumb-compact {
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 32px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            backdrop-filter: blur(10px);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .breadcrumb-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .breadcrumb-item.active {
            color: white;
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Container Principal */
        .form-container-compact {
            background: white;
            margin: 0;
        }

        /* Seção Superior - Form Básico */
        .top-section-compact {
            padding: 24px 32px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-row-compact {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto auto auto auto;
            gap: 16px;
            align-items: end;
        }

        .form-group-compact {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-label-compact {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .form-input-compact,
        .form-select-compact {
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            transition: all 0.2s ease;
            min-width: 0;
        }

        .form-input-compact:focus,
        .form-select-compact:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background: #f0fdf4;
        }

        /* Toggle Devolução */
        .toggle-container-compact {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .toggle-container-compact:hover {
            border-color: #d1d5db;
        }

        .toggle-container-compact.active {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .toggle-switch-compact {
            width: 36px;
            height: 20px;
            background: #d1d5db;
            border-radius: 20px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .toggle-switch-compact::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .toggle-container-compact.active .toggle-switch-compact {
            background: #10b981;
        }

        .toggle-container-compact.active .toggle-switch-compact::before {
            transform: translateX(16px);
        }

        .toggle-label-compact {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            user-select: none;
        }

        /* Seção de Cards Modais */
        .middle-section-compact {
            padding: 20px 32px;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-cards-compact {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        .modal-card-compact {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .modal-card-compact:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
        }

        .card-icon-compact {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            margin: 0 auto 8px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .card-title-compact {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .card-status-compact {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            background: #fef3c7;
            color: #92400e;
        }

        .card-status-compact.success {
            background: #d1fae5;
            color: #065f46;
        }

        /* Seção de Produtos */
        .products-section-compact {
            padding: 0;
        }

        .products-header-compact {
            padding: 20px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .products-title-compact {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .products-actions-compact {
            display: flex;
            gap: 8px;
        }

        .products-content-compact {
            background: white;
            min-height: 300px;
            max-height: 400px;
            overflow: auto;
        }

        /* Tabela de Produtos Melhorada */
        .products-table-compact {
            width: 100%;
            min-width: 2400px;
            border-collapse: collapse;
            font-size: 11px;
        }

        .products-table-compact th {
            background: #374151;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            white-space: nowrap;
            border: 1px solid #4b5563;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .products-table-compact td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
        }

        /* COLUNAS PRINCIPAIS - MAIORES */
        .products-table-compact th:nth-child(1), 
        .products-table-compact td:nth-child(1) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(2), 
        .products-table-compact td:nth-child(2) {
            min-width: 250px;
            width: 250px;
            text-align: left !important;
        }

        .products-table-compact th:nth-child(3), 
        .products-table-compact td:nth-child(3) {
            min-width: 60px;
            width: 60px;
        }

        .products-table-compact th:nth-child(4), 
        .products-table-compact td:nth-child(4) {
            min-width: 90px;
            width: 90px;
        }

        .products-table-compact th:nth-child(5), 
        .products-table-compact td:nth-child(5) {
            min-width: 100px;
            width: 100px;
        }

        .products-table-compact th:nth-child(6), 
        .products-table-compact td:nth-child(6) {
            min-width: 90px;
            width: 90px;
        }

        .products-table-compact th:nth-child(7), 
        .products-table-compact td:nth-child(7) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(8), 
        .products-table-compact td:nth-child(8) {
            min-width: 100px;
            width: 100px;
        }

        .products-table-compact th:nth-child(9), 
        .products-table-compact td:nth-child(9) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(10), 
        .products-table-compact td:nth-child(10) {
            min-width: 70px;
            width: 70px;
        }

        .products-table-compact th:nth-child(11), 
        .products-table-compact td:nth-child(11) {
            min-width: 70px;
            width: 70px;
        }

        /* COLUNAS TRIBUTÁRIAS - MENORES */
        .products-table-compact th:nth-child(n+12), 
        .products-table-compact td:nth-child(n+12) {
            min-width: 65px;
            width: 65px;
            font-size: 10px;
        }

        /* Coluna de Ações - Fixa */
        .products-table-compact th:last-child,
        .products-table-compact td:last-child {
            min-width: 80px;
            width: 80px;
        }

        /* INPUTS DA TABELA - SEM SETAS */
        .products-table-compact td input {
            width: 100%;
            padding: 8px 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
            background: white;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .products-table-compact td input[type="number"] {
            -moz-appearance: textfield;
        }

        .products-table-compact td input[type="number"]::-webkit-outer-spin-button,
        .products-table-compact td input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .products-table-compact td:nth-child(2) input {
            text-align: left !important;
            padding-left: 10px;
        }

        .products-table-compact td input:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }

        .products-table-compact td select {
            width: 100%;
            padding: 8px 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
            background: white;
            transition: all 0.2s ease;
        }

        .products-table-compact td select:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
        }

        .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .remove-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Footer com Totais */
        .footer-section-compact {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 16px 16px;
        }

        .totals-compact {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .total-item-compact {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .total-label-compact {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .total-value-compact {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Botões */
        .btn-compact {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .btn-primary-compact:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary-compact {
            background: white;
            color: #64748b;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary-compact:hover {
            background: #f8fafc;
            border-color: #d1d5db;
        }

        .btn-success-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success-compact:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
        }

        /* NFE Import Section */
        .nfe-import-compact {
            background: #f0f9ff;
            border: 2px dashed #0ea5e9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 32px;
            text-align: center;
            display: none;
        }

        .nfe-import-compact.show {
            display: block;
        }

        /* Supplier Search */
        .supplier-search-compact {
            position: relative;
            min-width: 300px;
        }

        .supplier-dropdown-compact {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .supplier-dropdown-compact.show {
            display: block;
        }

        .supplier-item-compact {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }

        .supplier-item-compact:hover {
            background: #f8fafc;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
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

        .toast.success {
            border-left-color: #10b981;
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

        /* MODAIS - CSS completo */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            animation: modalFadeIn 0.3s ease;
            overflow: auto;
        }

        .modal.active {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            max-width: 1200px;
            width: 95%;
            max-height: 95vh;
            height: auto;
            overflow: visible;
            animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            margin: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 28px 32px;
            border-bottom: none;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 20px 20px 0 0;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.05) 50%, transparent 70%);
            pointer-events: none;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .modal-header h3 i {
            width: 40px;
            height: 40px;
            background: rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #10b981;
            backdrop-filter: blur(10px);
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            position: relative;
            z-index: 1;
            line-height: 1;
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fecaca;
            transform: rotate(90deg) scale(1.1);
        }

        .modal-body {
            padding: 40px;
            background: #fafbfc;
            min-height: 400px;
            max-height: calc(95vh - 200px);
            overflow-y: auto;
            height: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 28px;
        }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 28px;
        }

        .modal-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .modal-grid-full {
            grid-column: 1 / -1;
        }

        .modal-form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-form-label {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .modal-form-label i {
            color: #10b981;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .modal-form-label .required {
            color: #ef4444;
            font-weight: 700;
        }

        .modal-form-input,
        .modal-form-select,
        .modal-form-textarea {
            padding: 20px 24px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            color: #1f2937;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            min-height: 56px;
        }

        .modal-form-input:focus,
        .modal-form-select:focus,
        .modal-form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 
                0 0 0 4px rgba(16, 185, 129, 0.1),
                0 4px 12px rgba(16, 185, 129, 0.15);
            background: #f0fdf4;
            transform: translateY(-1px);
        }

        .modal-form-input::placeholder,
        .modal-form-textarea::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .modal-form-textarea {
            resize: vertical;
            min-height: 140px;
            font-family: inherit;
            line-height: 1.6;
        }

        .modal-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .modal-search-input {
            padding-left: 60px;
        }

        .modal-search-icon {
            position: absolute;
            left: 20px;
            color: #9ca3af;
            font-size: 20px;
            pointer-events: none;
            z-index: 1;
        }

        .modal-footer {
            padding: 28px 40px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            border-radius: 0 0 20px 20px;
        }

        .modal-btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            min-height: 52px;
        }

        .modal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .modal-btn:hover::before {
            left: 100%;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .modal-btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.5);
        }

        .modal-btn-secondary {
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .modal-btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
            transform: translateY(-1px);
        }

        .modal-btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .modal-btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.5);
        }

        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .modal-table th {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            color: white;
            padding: 20px 24px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
            border: none;
        }

        .modal-table td {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            background: white;
            vertical-align: middle;
        }

        .modal-table tr:hover td {
            background: #f8fafc;
        }

        .modal-table tr:last-child td {
            border-bottom: none;
        }

        .modal-table input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            min-height: 48px;
        }

        .modal-table input:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
        }

        .modal-table select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            min-height: 48px;
            background: white;
        }

        .modal-table select:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
        }

        .modal-remove-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 44px;
        }

        .modal-remove-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }

        .modal-remove-btn i {
            font-size: 12px;
        }

        .modal-add-section {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 28px;
            transition: all 0.3s ease;
        }

        .modal-add-section:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        /* Animações */
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            .content-wrapper {
                margin: 0 20px;
            }
            
            .form-row-compact {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .modal-cards-compact {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .content-wrapper {
                margin: 0 10px;
            }
            
            .form-row-compact {
                grid-template-columns: 1fr;
            }
            
            .modal-cards-compact {
                grid-template-columns: 1fr;
            }
            
            .totals-compact {
                flex-direction: column;
                gap: 12px;
            }
            
            .footer-section-compact {
                flex-direction: column;
                gap: 16px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .modal-content {
                width: 95%;
                max-height: 90vh;
                margin: 20px;
                border-radius: 16px;
            }
            
            .modal-header {
                padding: 24px 28px;
            }
            
            .modal-header h3 {
                font-size: 18px;
            }
            
            .modal-body {
                padding: 28px;
            }
            
            .modal-grid-2,
            .modal-grid-3 {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .modal-footer {
                padding: 24px 28px;
                flex-direction: column;
            }
            
            .modal-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="content-wrapper">                      
            <div class="page-header-compact">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div>
                            <div class="header-title">Editar Entrada #<?= $entrada_numero ?></div>
                            <div class="header-subtitle">Modificar todos os dados da entrada, importar nova NFE ou alterar fornecedor</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="ver-entrada.php?id=<?= $entrada_id ?>" class="btn-compact btn-secondary-compact">
                            <i class="fas fa-eye"></i>
                            Visualizar
                        </a>
                        <a href="../entradas.php" class="btn-compact btn-primary-compact">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </a>
                        <button class="btn-compact btn-success-compact" onclick="openNFEImport()">
                            <i class="fas fa-download"></i>
                            Importar Nova NFE
                        </button>
                    </div>
                </div>

                <!-- Breadcrumb -->
                <div class="breadcrumb-compact">
                    <a href="../index.php" class="breadcrumb-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="../entradas.php" class="breadcrumb-item">
                        <i class="fas fa-sign-in-alt"></i>
                        Entradas
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-item active">Editar Entrada #<?= $entrada_numero ?></span>
                </div>
            </div>

            <div id="toast-container" class="toast-container"></div>

            <!-- Container do Formulário -->
            <div class="form-container-compact">
                
                <!-- Seção NFE Import -->
                <div id="nfe-import-section" class="nfe-import-compact">
                    <h3 style="margin-bottom: 16px; color: #0369a1; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Importar Nova NFE (Substituir dados atuais)
                    </h3>
                    <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                        <div style="position: relative;">
                            <input type="file" id="nfe_file" name="nfe_file" accept=".xml" required style="position: absolute; left: -9999px;">
                            <label for="nfe_file" class="btn-compact btn-primary-compact" style="cursor: pointer;">
                                <i class="fas fa-file-upload"></i>
                                Selecionar XML
                            </label>
                        </div>
                        <button type="submit" class="btn-compact btn-success-compact">
                            <i class="fas fa-upload"></i>
                            Importar e Substituir
                        </button>
                        <button type="button" class="btn-compact btn-secondary-compact" onclick="closeNFEImport()">
                            Cancelar
                        </button>
                    </form>
                </div>

                <!-- Formulário Principal -->
                <form id="entradaForm">
                    <input type="hidden" name="action" value="update_entrada">
                    <input type="hidden" name="entrada_id" value="<?= $entrada_id ?>">
                    
                    <!-- Seção Superior - Campos Básicos -->
                    <div class="top-section-compact">
                        <div class="form-row-compact">
                            <!-- Toggle Devolução -->
                            <div class="form-group-compact">
                                <label class="form-label-compact">Devolução</label>
                                <div class="toggle-container-compact <?= $entrada_data['tipooperacao'] === 'D' ? 'active' : '' ?>" onclick="toggleDevolucao()">
                                    <input type="checkbox" id="devolucao" name="devolucao" <?= $entrada_data['tipooperacao'] === 'D' ? 'checked' : '' ?> style="display: none;">
                                    <div class="toggle-switch-compact"></div>
                                    <span class="toggle-label-compact">Ativar</span>
                                </div>
                            </div>
                            
                            <!-- Busca Fornecedor -->
                            <div class="form-group-compact supplier-search-compact">
                                <label for="supplier_search" class="form-label-compact">
                                    Fornecedor <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" 
                                       id="supplier_search" 
                                       name="supplier_search" 
                                       class="form-input-compact" 
                                       placeholder="Digite código ou nome do fornecedor"
                                       value="<?php echo $entrada_data['fornecedor_nome'] ?? ''; ?>"
                                       autocomplete="off"
                                       required>
                                <input type="hidden" id="supplier_id" name="supplier_id" value="<?php echo $entrada_data['fornecedor_codigo'] ?? ''; ?>">
                                <div id="supplier-dropdown" class="supplier-dropdown-compact"></div>
                            </div>
                            
                            <!-- Campos Numéricos -->
                            <div class="form-group-compact">
                                <label for="entrada_numero" class="form-label-compact">Entrada N°</label>
                                <input type="text" name="entrada_numero" id="entrada_numero" class="form-input-compact" value="<?php echo $entrada_numero; ?>" readonly>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="data_entrada" class="form-label-compact">
                                    Data Entrada <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="data_entrada" id="data_entrada" class="form-input-compact" value="<?php echo formatDateForInput($entrada_data['Dataentrada']); ?>" required>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="ano_safra" class="form-label-compact">Ano/Safra</label>
                                <input type="text" name="ano_safra" id="ano_safra" class="form-input-compact" value="<?php echo $entrada_data['ano_safra'] ?? '2025'; ?>">
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="payment_type" class="form-label-compact">
                                    Tipo Pgto. <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="payment_type" id="payment_type" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="payment_condition" class="form-label-compact">
                                    Condições <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="payment_condition" id="payment_condition" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="expense_type" class="form-label-compact">
                                    Tipo Despesa <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="expense_type" id="expense_type" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Cards Modais -->
                    <div class="middle-section-compact">
                        <div class="modal-cards-compact">
                            <div class="modal-card-compact" onclick="openModal('nfe')">
                                <div class="card-icon-compact">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">NF-E</div>
                                    <div class="card-status-compact success" id="nfe-status">OK</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" onclick="openModal('frete')">
                                <div class="card-icon-compact">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Frete</div>
                                    <div class="card-status-compact" id="frete-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" onclick="openModal('centro-custo')">
                                <div class="card-icon-compact">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Centro Custo</div>
                                    <div class="card-status-compact" id="centro-custo-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" onclick="openModal('desconto')">
                                <div class="card-icon-compact">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Desconto</div>
                                    <div class="card-status-compact" id="desconto-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" onclick="openModal('observacoes')">
                                <div class="card-icon-compact">
                                    <i class="fas fa-sticky-note"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Observações</div>
                                    <div class="card-status-compact" id="observacoes-status">Opcional</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Produtos -->
                    <div class="products-section-compact">
                        <div class="products-header-compact">
                            <div class="products-title-compact">
                                <i class="fas fa-boxes"></i>
                                Produtos da Entrada (<?= count($itens_entrada) ?>)
                            </div>
                            <div class="products-actions-compact">
                                <button type="button" class="btn-compact btn-primary-compact" onclick="addProductRow()">
                                    <i class="fas fa-plus"></i>
                                    Adicionar Produto
                                </button>
                            </div>
                        </div>
                        
                        <div class="products-content-compact">
                            <table class="products-table-compact" id="products-table">
                                <thead>
                                    <tr>
                                        <th>Cód</th>
                                        <th>Descrição</th>
                                        <th>Un</th>
                                        <th>Qtd</th>
                                        <th>Vr. Unit.</th>
                                        <th>Frete Unit.</th>
                                        <th>Desc.</th>
                                        <th>Total</th>
                                        <th>LOTE</th>
                                        <th>Estoque</th>
                                        <th>CFOP</th>
                                        <th>Sit. Trib.ICMS</th>
                                        <th>Dif. ICMS</th>
                                        <th>B. Calc.Pis</th>
                                        <th>%Pis</th>
                                        <th>Vlr PIS</th>
                                        <th>Sit. Trib. COFINS</th>
                                        <th>B.Calc.COFINS</th>
                                        <th>%COFINS</th>
                                        <th>Vlr. COFINS</th>
                                        <th>Sit. Trib.IPI</th>
                                        <th>B.Calc.IPI</th>
                                        <th>%IPI</th>
                                        <th>Vlr. IPI</th>
                                        <th>B. Calc. Ret. ICMS Sub. Trib.</th>
                                        <th>Vlr.ICMS.Ret. Sub. Trib.</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="products-tbody">
                                    <!-- Produtos da entrada carregados -->
                                    <?php if (!empty($itens_entrada)): ?>
                                        <?php foreach ($itens_entrada as $index => $item): ?>
                                        <tr>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][codproduto]" value="<?php echo htmlspecialchars($item['Codproduto']); ?>"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][descricao]" value="<?php echo htmlspecialchars($item['produto_nome'] ?? ''); ?>" readonly style="background: #f9fafb;"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][unidade]" value="<?php echo htmlspecialchars($item['Un']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][quantidade]" value="<?php echo $item['Qtd']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][valor_unitario]" value="<?php echo $item['Vrunit']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][frete_unitario]" value="<?php echo $item['freteunit']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][desconto]" value="<?php echo $item['vrdesconto']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][total]" value="<?php echo $item['Total']; ?>" step="0.01" readonly style="background: #f9fafb;"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][lote]" value=""></td>
                                            <td>
                                                <select name="produtos[<?php echo $index; ?>][estoque]">
                                                    <option value="S" <?= $item['estoque'] === 'S' ? 'selected' : '' ?>>Sim</option>
                                                    <option value="N" <?= $item['estoque'] === 'N' ? 'selected' : '' ?>>Não</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][cfop]" value="<?php echo htmlspecialchars($item['cfop']); ?>"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_icms]" value="<?php echo htmlspecialchars($item['sticms']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][dif_icms]" value="0.00" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_pis]" value="<?php echo $item['bc_pis']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_pis]" value="<?php echo $item['ppis']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_pis]" value="<?php echo $item['vl_pis']; ?>" step="0.01"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_cofins]" value="<?php echo htmlspecialchars($item['stcofins']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_cofins]" value="<?php echo $item['bc_cofins']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_cofins]" value="<?php echo $item['pcofins']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_cofins]" value="<?php echo $item['vl_cofins']; ?>" step="0.01"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_ipi]" value="<?php echo htmlspecialchars($item['stipi']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_ipi]" value="<?php echo $item['bc_ipi']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_ipi]" value="<?php echo $item['percipi']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_ipi]" value="<?php echo $item['vripi']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_icms_st]" value="<?php echo $item['bc_icms_st']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_icms_st]" value="<?php echo $item['vl_icms_st']; ?>" step="0.01"></td>
                                            <td><button type="button" class="remove-btn" onclick="removeProductRow(this)">Remover</button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Campos ocultos para dados dos modais -->
                    <input type="hidden" name="nf_numero" id="nf_numero" value="<?php echo $entrada_data['numeronota'] ?? ''; ?>">
                    <input type="hidden" name="dt_emissao" id="dt_emissao" value="<?php echo formatDateForInput($entrada_data['dtnota']); ?>">
                    <input type="hidden" name="serie_nf" id="serie_nf" value="<?php echo $entrada_data['serienota'] ?? ''; ?>">
                    <input type="hidden" name="nf_entrada" id="nf_entrada" value="<?php echo $entrada_data['notaentrada'] ?? ''; ?>">
                    <input type="hidden" name="chave_acesso" id="chave_acesso" value="<?php echo $entrada_data['NumChaveNfe'] ?? ''; ?>">
                    <input type="hidden" name="transporter_id" id="transporter_id" value="<?php echo $entrada_data['codtransp'] ?? ''; ?>">
                    <input type="hidden" name="valor_frete" id="valor_frete" value="<?php echo $entrada_data['total_frete'] ?? 0; ?>">
                    <input type="hidden" name="placa_veiculo" id="placa_veiculo" value="<?php echo $entrada_data['placa'] ?? ''; ?>">
                    <input type="hidden" name="observacoes_gerais" id="observacoes_gerais" value="<?php echo $entrada_data['obs'] ?? ''; ?>">

                    <!-- Footer com Totais -->
                    <div class="footer-section-compact">
                        <div class="totals-compact">
                            <div class="total-item-compact">
                                <span class="total-label-compact">Valor</span>
                                <span class="total-value-compact" id="total-valor"><?php echo number_format($entrada_data['vrprodutos'], 2, ',', '.'); ?></span>
                                <input type="hidden" name="total_produtos" id="total_produtos" value="<?php echo $entrada_data['vrprodutos']; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Frete</span>
                                <span class="total-value-compact" id="total-frete"><?php echo number_format($entrada_data['total_frete'], 2, ',', '.'); ?></span>
                                <input type="hidden" name="total_frete" id="total_frete_input" value="<?php echo $entrada_data['total_frete']; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Descontos</span>
                                <span class="total-value-compact" id="total-descontos"><?php echo number_format($entrada_data['vrdesconto'], 2, ',', '.'); ?></span>
                                <input type="hidden" name="total_desconto" id="total_desconto" value="<?php echo $entrada_data['vrdesconto']; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Total</span>
                                <span class="total-value-compact" id="total-final"><?php echo number_format($entrada_data['vrTotal'], 2, ',', '.'); ?></span>
                                <input type="hidden" name="total_geral" id="total_geral" value="<?php echo $entrada_data['vrTotal']; ?>">
                                <input type="hidden" name="total_icms" id="total_icms" value="<?php echo $entrada_data['vricms']; ?>">
                                <input type="hidden" name="total_ipi" id="total_ipi" value="<?php echo $entrada_data['vripi']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions-compact">
                            <a href="../entradas.php" class="btn-compact btn-secondary-compact">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                            <button type="button" class="btn-compact btn-primary-compact">
                                <i class="fas fa-save"></i>
                                Rascunho
                            </button>
                            <button type="submit" class="btn-compact btn-success-compact">
                                <i class="fas fa-save"></i>
                                SALVAR ALTERAÇÕES
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAIS COMPLETOS -->
    
    <!-- Modal NF-E -->
    <div id="modal-nfe" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice"></i> Informações da NF-E</h3>
                <button type="button" class="modal-close" onclick="closeModal('nfe')">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-grid-2">
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-hashtag"></i>
                            Número Fiscal <span class="required">*</span>
                        </label>
                        <input type="text" id="modal_nf_numero" class="modal-form-input" 
                               placeholder="Digite o número da nota fiscal"
                               value="<?php echo $entrada_data['numeronota'] ?? ''; ?>">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Data de Emissão <span class="required">*</span>
                        </label>
                        <input type="date" id="modal_dt_emissao" class="modal-form-input" 
                               value="<?php echo formatDateForInput($entrada_data['dtnota']); ?>">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-list-ol"></i>
                            Série da NF
                        </label>
                        <input type="text" id="modal_serie_nf" class="modal-form-input" 
                               placeholder="Série da nota fiscal"
                               value="<?php echo $entrada_data['serienota'] ?? ''; ?>">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-sign-in-alt"></i>
                            Número Fiscal Entrada
                        </label>
                        <input type="text" id="modal_nf_entrada" class="modal-form-input" 
                               placeholder="Número da entrada"
                               value="<?php echo $entrada_data['notaentrada'] ?? ''; ?>">
                    </div>
                </div>
                <div class="modal-form-group modal-grid-full">
                    <label class="modal-form-label">
                        <i class="fas fa-key"></i>
                        Chave de Acesso da NFE
                    </label>
                    <input type="text" id="modal_chave_acesso" class="modal-form-input" 
                           placeholder="Digite a chave de 44 dígitos da NFE" 
                           value="<?php echo $entrada_data['NumChaveNfe'] ?? ''; ?>"
                           maxlength="44">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('nfe')">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="saveModal('nfe')">
                    <i class="fas fa-check"></i>
                    Salvar Informações
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Frete -->
    <div id="modal-frete" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-truck"></i> Informações de Frete</h3>
                <button type="button" class="modal-close" onclick="closeModal('frete')">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-form-group modal-grid-full">
                    <label class="modal-form-label">
                        <i class="fas fa-shipping-fast"></i>
                        Transportadora
                    </label>
                    <div class="modal-search-wrapper">
                        <i class="fas fa-search modal-search-icon"></i>
                        <input type="text" 
                               id="modal_transporter_search" 
                               class="modal-form-input modal-search-input" 
                               placeholder="Digite código ou nome da transportadora"
                               value="<?php echo $entrada_data['transportadora_nome'] ?? ''; ?>"
                               autocomplete="off">
                        <input type="hidden" id="modal_transporter_id" value="<?php echo $entrada_data['codtransp'] ?? ''; ?>">
                        <div id="modal-transporter-dropdown" class="supplier-dropdown-compact"></div>
                    </div>
                </div>
                
                <div class="modal-grid-2">
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-dollar-sign"></i>
                            Valor do Frete (R$)
                        </label>
                        <input type="number" id="modal_valor_frete" class="modal-form-input" 
                               placeholder="0,00" step="0.01" 
                               value="<?php echo $entrada_data['total_frete'] ?? 0; ?>">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">
                            <i class="fas fa-car"></i>
                            Placa do Veículo
                        </label>
                        <input type="text" id="modal_placa_veiculo" class="modal-form-input" 
                               placeholder="ABC-1234" 
                               value="<?php echo $entrada_data['placa'] ?? ''; ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('frete')">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="saveModal('frete')">
                    <i class="fas fa-check"></i>
                    Salvar Informações
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Centro de Custo -->
    <div id="modal-centro-custo" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calculator"></i> Centros de Custo</h3>
                <button type="button" class="modal-close" onclick="closeModal('centro-custo')">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-add-section">
                    <button type="button" class="modal-btn modal-btn-primary" onclick="addCostCenter()">
                        <i class="fas fa-plus"></i>
                        Adicionar Centro de Custo
                    </button>
                </div>
                
                <table class="modal-table" id="cost-centers-table">
                    <thead>
                        <tr>
                            <th>Centro de Custo</th>
                            <th>Placa</th>
                            <th>Valor</th>
                            <th>Observações</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="cost-centers-tbody">
                        <!-- Centros de custo carregados -->
                        <?php if (!empty($centros_custo_entrada)): ?>
                            <?php foreach ($centros_custo_entrada as $index => $cc): ?>
                            <tr data-codcc="<?= $cc['codcc'] ?>">
                                <td>
                                    <select name="cost_centers[<?= $index ?>][codcc]" class="modal-form-select">
                                        <option value="">Selecione...</option>
                                    </select>
                                </td>
                                <td><input type="text" name="cost_centers[<?= $index ?>][placa]" value="<?= htmlspecialchars($cc['placa']) ?>"></td>
                                <td><input type="number" name="cost_centers[<?= $index ?>][valor]" step="0.01" value="<?= $cc['valor'] ?>"></td>
                                <td><input type="text" name="cost_centers[<?= $index ?>][obs]" value="<?= htmlspecialchars($cc['obs']) ?>"></td>
                                <td>
                                    <button type="button" class="modal-remove-btn" onclick="removeCostCenter(this)">
                                        <i class="fas fa-trash"></i>
                                        Remover
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('centro-custo')">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="saveModal('centro-custo')">
                    <i class="fas fa-check"></i>
                    Salvar Informações
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Descontos -->
    <div id="modal-desconto" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-percentage"></i> Descontos</h3>
                <button type="button" class="modal-close" onclick="closeModal('desconto')">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-add-section">
                    <button type="button" class="modal-btn modal-btn-primary" onclick="addDiscount()">
                        <i class="fas fa-plus"></i>
                        Adicionar Desconto
                    </button>
                </div>
                
                <table class="modal-table" id="discounts-table">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="discounts-tbody">
                        <!-- Descontos carregados -->
                        <?php if (!empty($descontos_entrada)): ?>
                            <?php foreach ($descontos_entrada as $index => $desc): ?>
                            <tr>
                                <td><input type="text" name="discounts[<?= $index ?>][descricao]" value="<?= htmlspecialchars($desc['descricao']) ?>"></td>
                                <td><input type="number" name="discounts[<?= $index ?>][valor]" step="0.01" value="<?= $desc['valor'] ?>"></td>
                                <td>
                                    <button type="button" class="modal-remove-btn" onclick="removeDiscount(this)">
                                        <i class="fas fa-trash"></i>
                                        Remover
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('desconto')">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="saveModal('desconto')">
                    <i class="fas fa-check"></i>
                    Salvar Informações
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Observações -->
    <div id="modal-observacoes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-sticky-note"></i> Observações</h3>
                <button type="button" class="modal-close" onclick="closeModal('observacoes')">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-form-group">
                    <label class="modal-form-label">
                        <i class="fas fa-comment"></i>
                        Observações Gerais
                    </label>
                    <textarea id="modal_observacoes_gerais" class="modal-form-textarea" 
                              placeholder="Digite as observações gerais da entrada..."><?php echo $entrada_data['obs'] ?? ''; ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('observacoes')">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="saveModal('observacoes')">
                    <i class="fas fa-check"></i>
                    Salvar Informações
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        let productRowIndex = <?= count($itens_entrada) ?>;
        let costCenterIndex = <?= count($centros_custo_entrada) ?>;
        let discountIndex = <?= count($descontos_entrada) ?>;
        
        // Função para enviar formulário via API
        document.getElementById('entradaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Mostrar loading
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            submitBtn.disabled = true;
            
            fetch('../api_entradas/atualizar_entrada.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Redirecionar após sucesso
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                } else {
                    showToast(data.message, 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                showToast('Erro na requisição: ' + error.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Função para mostrar toast
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'error' ? 'exclamation-circle' : 
                        type === 'warning' ? 'exclamation-triangle' :
                        type === 'info' ? 'info-circle' : 'info-circle';
            
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

        // Função para toggle devolução
        function toggleDevolucao() {
            const container = document.querySelector('.toggle-container-compact');
            const checkbox = document.getElementById('devolucao');
            
            container.classList.toggle('active');
            checkbox.checked = container.classList.contains('active');
        }

        // Função para abrir/fechar seção NFE
        function openNFEImport() {
            document.getElementById('nfe-import-section').classList.add('show');
        }

        function closeNFEImport() {
            document.getElementById('nfe-import-section').classList.remove('show');
        }

        // FUNÇÕES DOS MODAIS
        function openModal(modalType) {
            const modal = document.getElementById(`modal-${modalType}`);
            modal.classList.add('active');
            
            // Carregar dados específicos do modal se necessário
            if (modalType === 'centro-custo') {
                loadCostCenters();
            }
        }

        function closeModal(modalType) {
            const modal = document.getElementById(`modal-${modalType}`);
            modal.classList.remove('active');
        }

        function saveModal(modalType) {
            switch(modalType) {
                case 'nfe':
                    saveNFEModal();
                    break;
                case 'frete':
                    saveFreteModal();
                    break;
                case 'centro-custo':
                    saveCostCenterModal();
                    break;
                case 'desconto':
                    saveDiscountModal();
                    break;
                case 'observacoes':
                    saveObservacoesModal();
                    break;
            }
        }

        function saveNFEModal() {
            document.getElementById('nf_numero').value = document.getElementById('modal_nf_numero').value;
            document.getElementById('dt_emissao').value = document.getElementById('modal_dt_emissao').value;
            document.getElementById('serie_nf').value = document.getElementById('modal_serie_nf').value;
            document.getElementById('nf_entrada').value = document.getElementById('modal_nf_entrada').value;
            document.getElementById('chave_acesso').value = document.getElementById('modal_chave_acesso').value;
            
            document.getElementById('nfe-status').textContent = 'OK';
            document.getElementById('nfe-status').className = 'card-status-compact success';
            
            closeModal('nfe');
            showToast('Informações da NFE salvas com sucesso!', 'success');
        }

        function saveFreteModal() {
            document.getElementById('transporter_id').value = document.getElementById('modal_transporter_id').value;
            document.getElementById('valor_frete').value = document.getElementById('modal_valor_frete').value;
            document.getElementById('placa_veiculo').value = document.getElementById('modal_placa_veiculo').value;
            
            const valorFrete = parseFloat(document.getElementById('modal_valor_frete').value) || 0;
            if (valorFrete > 0) {
                document.getElementById('frete-status').textContent = 'OK';
                document.getElementById('frete-status').className = 'card-status-compact success';
            }
            
            closeModal('frete');
            showToast('Informações de frete salvas com sucesso!', 'success');
            calculateTotals();
        }

        function saveCostCenterModal() {
            const rows = document.querySelectorAll('#cost-centers-tbody tr');
            if (rows.length > 0) {
                document.getElementById('centro-custo-status').textContent = 'OK';
                document.getElementById('centro-custo-status').className = 'card-status-compact success';
            }
            
            closeModal('centro-custo');
            showToast('Centros de custo salvos com sucesso!', 'success');
        }

        function saveDiscountModal() {
            const rows = document.querySelectorAll('#discounts-tbody tr');
            if (rows.length > 0) {
                document.getElementById('desconto-status').textContent = 'OK';
                document.getElementById('desconto-status').className = 'card-status-compact success';
            }
            
            closeModal('desconto');
            showToast('Descontos salvos com sucesso!', 'success');
            calculateTotals();
        }

        function saveObservacoesModal() {
            document.getElementById('observacoes_gerais').value = document.getElementById('modal_observacoes_gerais').value;
            
            const obs = document.getElementById('modal_observacoes_gerais').value;
            if (obs.trim()) {
                document.getElementById('observacoes-status').textContent = 'OK';
                document.getElementById('observacoes-status').className = 'card-status-compact success';
            }
            
            closeModal('observacoes');
            showToast('Observações salvas com sucesso!', 'success');
        }

        // Função para adicionar centro de custo
        function addCostCenter() {
            const tbody = document.getElementById('cost-centers-tbody');
            const newRow = document.createElement('tr');
            
            newRow.innerHTML = `
                <td>
                    <select name="cost_centers[${costCenterIndex}][codcc]" class="modal-form-select">
                        <option value="">Selecione...</option>
                    </select>
                </td>
                <td><input type="text" name="cost_centers[${costCenterIndex}][placa]"></td>
                <td><input type="number" name="cost_centers[${costCenterIndex}][valor]" step="0.01" value="0.00"></td>
                <td><input type="text" name="cost_centers[${costCenterIndex}][obs]"></td>
                <td>
                    <button type="button" class="modal-remove-btn" onclick="removeCostCenter(this)">
                        <i class="fas fa-trash"></i>
                        Remover
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            
            // Carregar centros de custo no novo select
            loadCostCentersForSelect(newRow.querySelector('select'));
            
            costCenterIndex++;
        }

        function removeCostCenter(btn) {
            if (confirm('Tem certeza que deseja remover este centro de custo?')) {
                btn.closest('tr').remove();
            }
        }

        // Função para adicionar desconto
        function addDiscount() {
            const tbody = document.getElementById('discounts-tbody');
            const newRow = document.createElement('tr');
            
            newRow.innerHTML = `
                <td><input type="text" name="discounts[${discountIndex}][descricao]" placeholder="Descrição do desconto"></td>
                <td><input type="number" name="discounts[${discountIndex}][valor]" step="0.01" value="0.00"></td>
                <td>
                    <button type="button" class="modal-remove-btn" onclick="removeDiscount(this)">
                        <i class="fas fa-trash"></i>
                        Remover
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            discountIndex++;
        }

        function removeDiscount(btn) {
            if (confirm('Tem certeza que deseja remover este desconto?')) {
                btn.closest('tr').remove();
            }
        }

        // Função para calcular total da linha
        function calculateRowTotal(input) {
            const row = input.closest('tr');
            const quantidade = parseFloat(row.querySelector('input[name$="[quantidade]"]').value) || 0;
            const valorUnitario = parseFloat(row.querySelector('input[name$="[valor_unitario]"]').value) || 0;
            const freteUnitario = parseFloat(row.querySelector('input[name$="[frete_unitario]"]').value) || 0;
            const desconto = parseFloat(row.querySelector('input[name$="[desconto]"]').value) || 0;
            
            const total = (quantidade * valorUnitario) + (quantidade * freteUnitario) - desconto;
            row.querySelector('input[name$="[total]"]').value = total.toFixed(2);
            
            // Recalcular totais gerais
            calculateTotals();
        }

        // Função para calcular totais gerais
        function calculateTotals() {
            let totalProdutos = 0;
            let totalFrete = 0;
            let totalDescontos = 0;
            
            document.querySelectorAll('#products-tbody tr').forEach(row => {
                const quantidade = parseFloat(row.querySelector('input[name$="[quantidade]"]').value) || 0;
                const valorUnitario = parseFloat(row.querySelector('input[name$="[valor_unitario]"]').value) || 0;
                const freteUnitario = parseFloat(row.querySelector('input[name$="[frete_unitario]"]').value) || 0;
                const desconto = parseFloat(row.querySelector('input[name$="[desconto]"]').value) || 0;
                
                totalProdutos += quantidade * valorUnitario;
                totalFrete += quantidade * freteUnitario;
                totalDescontos += desconto;
            });
            
            // Adicionar frete adicional
            const freteAdicional = parseFloat(document.getElementById('valor_frete').value) || 0;
            totalFrete += freteAdicional;
            
            // Adicionar descontos dos modais
            document.querySelectorAll('#discounts-tbody input[name$="[valor]"]').forEach(input => {
                totalDescontos += parseFloat(input.value) || 0;
            });
            
            const totalGeral = totalProdutos + totalFrete - totalDescontos;
            
            document.getElementById('total-valor').textContent = totalProdutos.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('total-frete').textContent = totalFrete.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('total-descontos').textContent = totalDescontos.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('total-final').textContent = totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            
            document.getElementById('total_produtos').value = totalProdutos.toFixed(2);
            document.getElementById('total_frete_input').value = totalFrete.toFixed(2);
            document.getElementById('total_desconto').value = totalDescontos.toFixed(2);
            document.getElementById('total_geral').value = totalGeral.toFixed(2);
        }

        // Função para adicionar linha de produto
        function addProductRow() {
            const tbody = document.getElementById('products-tbody');
            const newRow = document.createElement('tr');
            
            newRow.innerHTML = `
                <td><input type="text" name="produtos[${productRowIndex}][codproduto]" value=""></td>
                <td><input type="text" name="produtos[${productRowIndex}][descricao]" value="" readonly style="background: #f9fafb;"></td>
                <td><input type="text" name="produtos[${productRowIndex}][unidade]" value="UN"></td>
                <td><input type="number" name="produtos[${productRowIndex}][quantidade]" value="1" step="0.01" onchange="calculateRowTotal(this)"></td>
                <td><input type="number" name="produtos[${productRowIndex}][valor_unitario]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                <td><input type="number" name="produtos[${productRowIndex}][frete_unitario]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                <td><input type="number" name="produtos[${productRowIndex}][desconto]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                <td><input type="number" name="produtos[${productRowIndex}][total]" value="0.00" step="0.01" readonly style="background: #f9fafb;"></td>
                <td><input type="text" name="produtos[${productRowIndex}][lote]" value=""></td>
                <td>
                    <select name="produtos[${productRowIndex}][estoque]">
                        <option value="S" selected>Sim</option>
                        <option value="N">Não</option>
                    </select>
                </td>
                <td><input type="text" name="produtos[${productRowIndex}][cfop]" value=""></td>
                <td><input type="text" name="produtos[${productRowIndex}][sit_icms]" value=""></td>
                <td><input type="number" name="produtos[${productRowIndex}][dif_icms]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][bc_pis]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][perc_pis]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][vl_pis]" value="0.00" step="0.01"></td>
                <td><input type="text" name="produtos[${productRowIndex}][sit_cofins]" value=""></td>
                <td><input type="number" name="produtos[${productRowIndex}][bc_cofins]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][perc_cofins]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][vl_cofins]" value="0.00" step="0.01"></td>
                <td><input type="text" name="produtos[${productRowIndex}][sit_ipi]" value=""></td>
                <td><input type="number" name="produtos[${productRowIndex}][bc_ipi]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][perc_ipi]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][vl_ipi]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][bc_icms_st]" value="0.00" step="0.01"></td>
                <td><input type="number" name="produtos[${productRowIndex}][vl_icms_st]" value="0.00" step="0.01"></td>
                <td><button type="button" class="remove-btn" onclick="removeProductRow(this)">Remover</button></td>
            `;
            
            tbody.appendChild(newRow);
            productRowIndex++;
            calculateTotals();
        }

        // Função para remover linha de produto
        function removeProductRow(button) {
            if (confirm('Tem certeza que deseja remover este produto?')) {
                button.closest('tr').remove();
                calculateTotals();
            }
        }

        // Busca de fornecedores
        document.getElementById('supplier_search').addEventListener('input', function() {
            const term = this.value;
            const dropdown = document.getElementById('supplier-dropdown');
            
            if (term.length < 2) {
                dropdown.classList.remove('show');
                return;
            }
            
            fetch(`api_editar_entrada.php?action=search_suppliers&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    
                    if (data.length > 0) {
                        data.forEach(supplier => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <strong>${supplier.Nome}</strong><br>
                                <small>Cód: ${supplier.codcliente} - ${supplier.cnpj_cpf}</small>
                            `;
                            item.onclick = () => {
                                document.getElementById('supplier_search').value = supplier.Nome;
                                document.getElementById('supplier_id').value = supplier.codcliente;
                                dropdown.classList.remove('show');
                            };
                            dropdown.appendChild(item);
                        });
                        dropdown.classList.add('show');
                    } else {
                        dropdown.classList.remove('show');
                    }
                })
                .catch(error => {
                    console.error('Erro na busca:', error);
                    dropdown.classList.remove('show');
                });
        });

        // Busca de transportadoras no modal
        document.getElementById('modal_transporter_search').addEventListener('input', function() {
            const term = this.value;
            const dropdown = document.getElementById('modal-transporter-dropdown');
            
            if (term.length < 2) {
                dropdown.classList.remove('show');
                return;
            }
            
            fetch(`api_editar_entrada.php?action=search_transporters&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    
                    if (data.length > 0) {
                        data.forEach(transporter => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <strong>${transporter.Nome}</strong><br>
                                <small>Cód: ${transporter.codcliente} - ${transporter.cnpj_cpf}</small>
                            `;
                            item.onclick = () => {
                                document.getElementById('modal_transporter_search').value = transporter.Nome;
                                document.getElementById('modal_transporter_id').value = transporter.codcliente;
                                dropdown.classList.remove('show');
                            };
                            dropdown.appendChild(item);
                        });
                        dropdown.classList.add('show');
                    } else {
                        dropdown.classList.remove('show');
                    }
                })
                .catch(error => {
                    console.error('Erro na busca:', error);
                    dropdown.classList.remove('show');
                });
        });

        // Função para carregar centros de custo
        function loadCostCenters() {
            fetch('api_editar_entrada.php?action=search_cost_centers')
                .then(response => response.json())
                .then(data => {
                    document.querySelectorAll('#cost-centers-tbody select').forEach(select => {
                        loadCostCentersForSelect(select, data);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar centros de custo:', error);
                });
        }

        function loadCostCentersForSelect(select, data = null) {
            if (data) {
                populateSelect(select, data);
            } else {
                fetch('api_editar_entrada.php?action=search_cost_centers')
                    .then(response => response.json())
                    .then(data => {
                        populateSelect(select, data);
                    });
            }
        }

        function populateSelect(select, data) {
            const row = select.closest('tr');
            const currentValue = row ? row.dataset.codcc : select.value;
            
            select.innerHTML = '<option value="">Selecione...</option>';
            
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.codcc;
                option.textContent = item.descricao;
                if (item.codcc == currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                const modalId = e.target.id.replace('modal-', '');
                closeModal(modalId);
            }
        });

        // Carregar dados iniciais
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar tipos de pagamento
            fetch('api_editar_entrada.php?action=search_payment_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('payment_type');
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.codtppag;
                        option.textContent = item.Descricao;
                        if (item.codtppag == '<?= $entrada_data['codtppag'] ?>') {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                });

            // Carregar condições de pagamento
            fetch('api_editar_entrada.php?action=search_conditions')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('payment_condition');
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.codcond;
                        option.textContent = item.Descricao;
                        if (item.codcond == '<?= $entrada_data['codcond'] ?>') {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                });

            // Carregar tipos de despesa
            fetch('api_editar_entrada.php?action=search_expense_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('expense_type');
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.codtpdes;
                        option.textContent = item.Descricao;
                        if (item.codtpdes == '<?= $entrada_data['codtpdes'] ?>') {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                });

            // Carregar centros de custo existentes
            loadCostCenters();

            // Atualizar status dos cards baseado nos dados existentes
            updateCardStatus();
        });

        function updateCardStatus() {
            // NFE Status
            if ('<?= $entrada_data['numeronota'] ?>') {
                document.getElementById('nfe-status').textContent = 'OK';
                document.getElementById('nfe-status').className = 'card-status-compact success';
            }

            // Frete Status
            if (<?= $entrada_data['total_frete'] ?> > 0) {
                document.getElementById('frete-status').textContent = 'OK';
                document.getElementById('frete-status').className = 'card-status-compact success';
            }

            // Centro de Custo Status
            if (<?= count($centros_custo_entrada) ?> > 0) {
                document.getElementById('centro-custo-status').textContent = 'OK';
                document.getElementById('centro-custo-status').className = 'card-status-compact success';
            }

            // Desconto Status
            if (<?= count($descontos_entrada) ?> > 0) {
                document.getElementById('desconto-status').textContent = 'OK';
                document.getElementById('desconto-status').className = 'card-status-compact success';
            }

            // Observações Status
            if ('<?= $entrada_data['obs'] ?>'.trim()) {
                document.getElementById('observacoes-status').textContent = 'OK';
                document.getElementById('observacoes-status').className = 'card-status-compact success';
            }
        }
    </script>
</body>
</html>