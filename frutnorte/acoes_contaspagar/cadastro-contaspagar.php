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

    $idcliente = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ========================================
// SISTEMA DE CADASTRO DE CONTAS A PAGAR - NOVA VERSÃO
// ========================================

// Configuração do banco de dados
require_once '../config/databaselogin.php'; // o arquivo que contém a classe Database

$database = new Database();
$pdo = $database->getConnection();

// Inicialização de variáveis
$mensagem = '';
$tipo_mensagem = 'success';
$conta_data = null;

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
        throw new Exception("Erro na conexão com o banco: " . $e->getMessage());
    }
}

// API para buscar fornecedores (clientes com tipocliente = '3')
if (isset($_GET['action']) && $_GET['action'] === 'search_suppliers') {
    header('Content-Type: application/json');
    
    // Verificar sessão para APIs também
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    $term = $_GET['term'] ?? '';
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcliente, Nome, cnpj_cpf, Cidade, Uf 
            FROM clientes 
            WHERE idcliente = ? 
            AND tipocliente = '3'
            AND (codcliente LIKE ? OR Nome LIKE ? OR cnpj_cpf LIKE ?)
            ORDER BY Nome 
            LIMIT 20
        ");
        $searchTerm = "%{$term}%";
        $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($suppliers);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar todos os fornecedores
if (isset($_GET['action']) && $_GET['action'] === 'get_all_suppliers') {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcliente, Nome, cnpj_cpf, Cidade, Uf 
            FROM clientes 
            WHERE idcliente = ? 
            AND tipocliente = '3'
            ORDER BY Nome
        ");
        $stmt->execute([$idcliente]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($suppliers);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar condições
if (isset($_GET['action']) && $_GET['action'] === 'get_condition' && isset($_GET['codcond'])) {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    $codcond = (int)$_GET['codcond'];
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcond, Descricao, Parcelas, CondPgto1, CondPgto2, CondPgto3, CondPgto4, condpgto5, condpgto6 
            FROM condicoes 
            WHERE codcond = ? AND idcliente = ?
        ");
        $stmt->execute([$codcond, $idcliente]);
        $condition = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($condition) {
            echo json_encode($condition);
        } else {
            echo json_encode(['error' => 'Condição não encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar tipos de despesa
if (isset($_GET['action']) && $_GET['action'] === 'search_expense_types') {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codtpdes, Descricao 
            FROM tipodespesas 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $expenseTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($expenseTypes);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar condições de pagamento
if (isset($_GET['action']) && $_GET['action'] === 'search_conditions') {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcond, Descricao 
            FROM condicoes 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($conditions);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar tipos de pagamento
if (isset($_GET['action']) && $_GET['action'] === 'search_payment_types') {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codtppag, Descricao 
            FROM tipopagamentos 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($paymentTypes);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar entradas
if (isset($_GET['action']) && $_GET['action'] === 'search_entradas') {
    header('Content-Type: application/json');
    
    // Verificar sessão
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        echo json_encode(['error' => 'Acesso negado. Faça login para continuar.']);
        exit;
    }
    
    $term = $_GET['term'] ?? '';
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codentrada, numeronota, serienota, Dataentrada 
            FROM entradas 
            WHERE idcliente = ?
            AND (codentrada LIKE ? OR numeronota LIKE ? OR serienota LIKE ?) 
            ORDER BY Dataentrada DESC 
            LIMIT 10
        ");
        $searchTerm = "%{$term}%";
        $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]);
        $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($entradas);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function obterProximoCodigoPagar() {
    global $idcliente;
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codpagar) as max_cod FROM contaspagar WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

function calcularParcelas($codcond, $valorTotal, $dataEmissao) {
    global $idcliente;
    
    if (!$codcond) {
        return [[ 'valor_parcela' => $valorTotal, 'data_vencimento' => $dataEmissao, 'numero_parcela' => 1, 'total_parcelas' => 1 ]];
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT Parcelas, CondPgto1, CondPgto2, CondPgto3, CondPgto4, condpgto5, condpgto6 
            FROM condicoes 
            WHERE codcond = ? AND idcliente = ?
        ");
        $stmt->execute([$codcond, $idcliente]);
        $condicao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$condicao || (int)$condicao['Parcelas'] === 0) {
            return [[ 'valor_parcela' => $valorTotal, 'data_vencimento' => $dataEmissao, 'numero_parcela' => 1, 'total_parcelas' => 1 ]];
        }
        
        $numParcelas = (int)$condicao['Parcelas'];
        $parcelasCondicao = [];
        
        for ($i = 1; $i <= $numParcelas; $i++) {
            $campoDias = "CondPgto{$i}";
            $dias = (int)($condicao[$campoDias] ?? 0);
            $parcelasCondicao[] = $dias;
        }
        
        $parcelas = [];
        $totalParcelas = $numParcelas;
        $valorParcelaBase = $valorTotal / $totalParcelas;
        
        foreach ($parcelasCondicao as $index => $dias) {
            $numeroParcela = $index + 1;
            $valorParcela = $numeroParcela < $totalParcelas ? $valorParcelaBase : ($valorTotal - ($numeroParcela - 1) * $valorParcelaBase);
            $dataVencimento = date('Y-m-d', strtotime($dataEmissao . ' + ' . $dias . ' days'));
            
            $parcelas[] = [
                'numero_parcela' => $numeroParcela,
                'total_parcelas' => $totalParcelas,
                'valor_parcela' => round($valorParcela, 2),
                'data_vencimento' => $dataVencimento
            ];
        }
        
        return $parcelas;
    } catch (Exception $e) {
        return [[ 'valor_parcela' => $valorTotal, 'data_vencimento' => $dataEmissao, 'numero_parcela' => 1, 'total_parcelas' => 1 ]];
    }
}

// Processar envio do formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_conta') {
    
    // Verificar sessão no POST também
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
        $mensagem = "Acesso negado. Faça login para continuar.";
        $tipo_mensagem = 'error';
    } else {
        try {
            $pdo = conectarBanco();
            $pdo->beginTransaction();
            
            $codpagar = obterProximoCodigoPagar();
            $codempresa = 1;
            $codentrada = !empty($_POST['codentrada']) ? (int)$_POST['codentrada'] : null;
            $codcliente = (int)$_POST['codcliente'];
            $serienota = $_POST['serienota'] ?? '1';
            $numeronota = $_POST['numeronota'] ?? null;
            $datalancamento = date('Y-m-d');
            $dataemissao = $_POST['dataemissao_nfe'] ?? date('Y-m-d'); // Agora vem do modal NF-e
            $codcond = !empty($_POST['codcond']) ? (int)$_POST['codcond'] : null;
            $codtppag = !empty($_POST['codtppag']) ? (int)$_POST['codtppag'] : null;
            $codtpdes = !empty($_POST['codtpdes']) ? (int)$_POST['codtpdes'] : null;
            $vrtitulo = (float)$_POST['vrtitulo'];
            $vrdesconto = (float)($_POST['vrdesconto'] ?? 0);
            $vracrescimo = (float)($_POST['vracrescimo'] ?? 0);
            $vrfinal = $vrtitulo - $vrdesconto + $vracrescimo;
            $vrpago = 0;
            $saldo = $vrfinal;
            $obs = $_POST['obs'] ?? '';
            $ano_safra = $_POST['ano_safra'] ?? date('Y');
            $origem = 'MANUAL';
            $placa = $_POST['placa'] ?? '';
            $litros = !empty($_POST['litros']) ? (float)$_POST['litros'] : null;
            $km_nf = !empty($_POST['km_nf']) ? (float)$_POST['km_nf'] : null;
            $km_ant = !empty($_POST['km_ant']) ? (float)$_POST['km_ant'] : null;
            $media = null;
            
            // Calcular média se tiver dados de veículo
            if ($litros && $km_nf && $km_ant && $litros > 0) {
                $km_rodados = $km_nf - $km_ant;
                if ($km_rodados > 0) {
                    $media = round($km_rodados / $litros, 2);
                }
            }
            
            $previsao = 'N';
            
            // Calcular parcelas
            $parcelas = calcularParcelas($codcond, $vrfinal, $dataemissao);
            
            $seqcodpagar = 1;
            foreach ($parcelas as $parcela) {
                $dadosConta = [
                    'idcliente' => $idcliente,
                    'codpagar' => $codpagar,
                    'seqcodpagar' => $seqcodpagar,
                    'codempresa' => $codempresa,
                    'codentrada' => $codentrada,
                    'codcliente' => $codcliente,
                    'serienota' => $serienota,
                    'numeronota' => $numeronota,
                    'Datalancamento' => $datalancamento,
                    'dataemissao' => $dataemissao,
                    'datavencimento' => $parcela['data_vencimento'],
                    'datapagamento' => null,
                    'vrtitulo' => $parcela['valor_parcela'],
                    'vrdocumento' => $parcela['valor_parcela'],
                    'vrdesconto' => $vrdesconto / count($parcelas),
                    'percdesconto' => 0,
                    'vracrescimo' => $vracrescimo / count($parcelas),
                    'vrfinal' => $parcela['valor_parcela'] - ($vrdesconto / count($parcelas)) + ($vracrescimo / count($parcelas)),
                    'vrpago' => 0,
                    'saldo' => $parcela['valor_parcela'],
                    'obs' => $obs . " - Parcela {$parcela['numero_parcela']} de {$parcela['total_parcelas']}",
                    'codtpdes' => $codtpdes,
                    'codcond' => $codcond,
                    'codtppag' => $codtppag,
                    'ano_safra' => $ano_safra,
                    'caixa' => null,
                    'origem' => $origem,
                    'previsao' => $previsao,
                    'codigoboleto' => null,
                    'codigobarras' => null,
                    'placa' => $placa,
                    'dados_pagto' => null,
                    'idcontrato' => null,
                    'litros' => $litros,
                    'km_nf' => $km_nf,
                    'km_ant' => $km_ant,
                    'media' => $media,
                    'dtcompetencia' => null,
                    'oper1' => null,
                    'oper2' => null,
                    'datahora1' => null,
                    'datahora2' => null
                ];
                
                $campos = implode(', ', array_keys($dadosConta));
                $placeholders = ':' . implode(', :', array_keys($dadosConta));
                
                $sql = "INSERT INTO contaspagar ({$campos}) VALUES ({$placeholders})";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dadosConta);
                
                $seqcodpagar++;
            }
            
            $pdo->commit();
            
            $dataAtual = date('d/m/Y');
            $mensagem = "Conta a pagar salva com sucesso em {$dataAtual}! Código: {$codpagar} ({count($parcelas)} parcela(s))";
            $tipo_mensagem = 'success';
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(16, 185, 129, 0.9);
                        color: white;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        z-index: 10000;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    `;
                    
                    overlay.innerHTML = `
                        <div style=\"text-align: center;\">
                            <i class=\"fas fa-check-circle\" style=\"font-size: 48px; margin-bottom: 20px;\"></i>
                            <h2 style=\"margin-bottom: 10px;\">Conta Salva com Sucesso!</h2>
                            <p style=\"margin-bottom: 20px; opacity: 0.9;\">{$mensagem}</p>
                            <div style=\"display: flex; align-items: center; gap: 10px;\">
                                <div class=\"spinner\" style=\"width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s linear infinite;\"></div>
                                <span>Redirecionando para a lista de contas...</span>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(overlay);
                    
                    setTimeout(function() {
                        window.location.href = '../contaspagar.php?mensagem=" . urlencode($mensagem) . "';
                    }, 4000);
                    
                    const style = document.createElement('style');
                    style.textContent = `
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                });
            </script>";
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = "Erro ao salvar conta: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

$codpagar = str_pad(obterProximoCodigoPagar(), 6, '0', STR_PAD_LEFT);
$data_atual = date('d/m/Y');
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Conta a Pagar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ESTILOS ANTERIORES MANTIDOS... */
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

        .page-header-compact {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            padding: 20px 32px;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
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

        .form-container-compact {
            background: white;
            margin: 0;
        }

        .top-section-compact {
            padding: 24px 32px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-row-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
            background: #fef2f2;
        }

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
            border-color: #ef4444;
            background: #fef2f2;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.15);
        }

        .card-icon-compact {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            margin: 0 auto 8px;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
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
            background: #fee2e2;
            color: #dc2626;
        }

        .parcelas-section-compact {
            padding: 0;
        }

        .parcelas-header-compact {
            padding: 20px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .parcelas-title-compact {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .parcelas-actions-compact {
            display: flex;
            gap: 8px;
        }

        .parcelas-content-compact {
            background: white;
            min-height: 300px;
            max-height: 400px;
            overflow: auto;
        }

        .parcelas-table-compact {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .parcelas-table-compact th {
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

        .parcelas-table-compact td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
        }

        .parcelas-table-compact th:nth-child(1), .parcelas-table-compact td:nth-child(1) { min-width: 80px; }
        .parcelas-table-compact th:nth-child(2), .parcelas-table-compact td:nth-child(2) { min-width: 120px; }
        .parcelas-table-compact th:nth-child(3), .parcelas-table-compact td:nth-child(3) { min-width: 100px; }
        .parcelas-table-compact th:nth-child(4), .parcelas-table-compact td:nth-child(4) { min-width: 150px; }

        .parcelas-table-compact td input {
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

        .parcelas-table-compact td input:focus {
            outline: none;
            border-color: #ef4444;
            background: #fef2f2;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1);
        }

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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        .btn-primary-compact:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4);
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

        .supplier-search-compact {
            position: relative;
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
            border-left: 4px solid #ef4444;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 280px;
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
            max-width: 800px;
            width: 95%;
            max-height: 95vh;
            height: auto;
            overflow: visible;
            animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            margin: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            padding: 28px 32px;
            border-bottom: none;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
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
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
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

        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 28px;
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
            color: #ef4444;
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
            border-color: #ef4444;
            box-shadow: 
                0 0 0 4px rgba(239, 68, 68, 0.1),
                0 4px 12px rgba(239, 68, 68, 0.15);
            background: #fef2f2;
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .modal-btn-primary:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.5);
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

        @media (max-width: 1200px) {
            .content-wrapper {
                margin: 0 20px;
            }
            
            .form-row-compact {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .modal-cards-compact {
                grid-template-columns: repeat(2, 1fr);
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
        }

        /* Botão Listar Todos os Fornecedores */
        .list-all-btn {
            margin-top: 8px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 11px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .list-all-btn:hover {
            background: #f1f5f9;
            border-color: #d1d5db;
            color: #374151;
        }

        /* Campo Valor Parcela (inicialmente oculto) */
        .valor-parcela-group {
            display: none;
        }

        .valor-parcela-group.show {
            display: flex;
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
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <div class="header-title">Nova Conta a Pagar</div>
                            <div class="header-subtitle">Cadastre uma nova conta a pagar ou parcelas</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn-compact btn-secondary-compact" onclick="window.location.href='../contaspagar.php'">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </button>
                    </div>
                </div>

                <div class="breadcrumb-compact">
                    <a href="../index.php" class="breadcrumb-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="../contaspagar.php" class="breadcrumb-item">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Contas a Pagar
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-item active">Nova Conta</span>
                </div>
            </div>

            <div id="toast-container" class="toast-container"></div>

            <!-- Container do Formulário -->
            <div class="form-container-compact">
                
                <!-- Formulário Principal -->
                <form id="contaForm" method="POST">
                    <input type="hidden" name="action" value="save_conta">
                    
                    <!-- Seção Superior - Campos Básicos -->
                    <div class="top-section-compact">
                        <div class="form-row-compact">
                            <!-- Código da Conta -->
                            <div class="form-group-compact">
                                <label for="codpagar" class="form-label-compact">Código Conta</label>
                                <input type="text" name="codpagar" id="codpagar" class="form-input-compact" value="<?php echo $codpagar; ?>" readonly>
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
                                       placeholder="Digite código ou nome"
                                       autocomplete="off"
                                       required
                                       style="width: 250px;">
                                <input type="hidden" id="codcliente" name="codcliente" value="">
                                <div id="supplier-dropdown" class="supplier-dropdown-compact"></div>
                                <button type="button" class="list-all-btn" onclick="showAllSuppliers()">
                                    <i class="fas fa-list"></i>
                                    Listar Todos
                                </button>
                            </div>
                            
                            <!-- Data Lançamento -->
                            <div class="form-group-compact">
                                <label for="datalancamento" class="form-label-compact">Data Lançamento</label>
                                <input type="date" name="datalancamento" id="datalancamento" class="form-input-compact" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                            
                            <!-- Tipo Despesa -->
                            <div class="form-group-compact">
                                <label for="codtpdes" class="form-label-compact">
                                    Tipo Despesa <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="codtpdes" id="codtpdes" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <!-- Condição Pagamento -->
                            <div class="form-group-compact">
                                <label for="codcond" class="form-label-compact">
                                    Condição Pagto <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="codcond" id="codcond" class="form-select-compact" required onchange="toggleValorParcela()">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <!-- Tipo Pagamento -->
                            <div class="form-group-compact">
                                <label for="codtppag" class="form-label-compact">
                                    Tipo Pagamento <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="codtppag" id="codtppag" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <!-- Ano Safra -->
                            <div class="form-group-compact">
                                <label for="ano_safra" class="form-label-compact">Ano/Safra</label>
                                <input type="text" name="ano_safra" id="ano_safra" class="form-input-compact" value="<?php echo date('Y'); ?>">
                            </div>
                            
                            <!-- Valor Título -->
                            <div class="form-group-compact">
                                <label for="vrtitulo" class="form-label-compact">
                                    Valor Total (R$) <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="number" name="vrtitulo" id="vrtitulo" class="form-input-compact" step="0.01" min="0" placeholder="0,00" required onchange="calculateTotais()">
                            </div>

                            <!-- Valor Parcela (inicialmente oculto) -->
                            <div class="form-group-compact valor-parcela-group" id="valor-parcela-group">
                                <label for="valor_parcela" class="form-label-compact">
                                    Valor Parcela (R$)
                                </label>
                                <input type="number" name="valor_parcela" id="valor_parcela" class="form-input-compact" step="0.01" min="0" placeholder="0,00" onchange="calcularValorTotalPorParcela()">
                            </div>
                            
                            <!-- Desconto -->
                            <div class="form-group-compact">
                                <label for="vrdesconto" class="form-label-compact">Desconto (R$)</label>
                                <input type="number" name="vrdesconto" id="vrdesconto" class="form-input-compact" step="0.01" min="0" placeholder="0,00" value="0" onchange="calculateTotais()">
                            </div>
                            
                            <!-- Acréscimo -->
                            <div class="form-group-compact">
                                <label for="vracrescimo" class="form-label-compact">Acréscimo (R$)</label>
                                <input type="number" name="vracrescimo" id="vracrescimo" class="form-input-compact" step="0.01" min="0" placeholder="0,00" value="0" onchange="calculateTotais()">
                            </div>
                            
                            <!-- Valor Final -->
                            <div class="form-group-compact">
                                <label for="vrfinal" class="form-label-compact">Valor Final (R$)</label>
                                <input type="number" name="vrfinal" id="vrfinal" class="form-input-compact" step="0.01" readonly style="background: #f9fafb;">
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Cards Modais -->
                    <div class="middle-section-compact">
                        <div class="modal-cards-compact">
                            <div class="modal-card-compact" data-modal="entrada">
                                <div class="card-icon-compact">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Entrada</div>
                                    <div class="card-status-compact" id="entrada-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="nfe">
                                <div class="card-icon-compact">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">NF-e</div>
                                    <div class="card-status-compact" id="nfe-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="veiculo">
                                <div class="card-icon-compact">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Veículo</div>
                                    <div class="card-status-compact" id="veiculo-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="observacoes">
                                <div class="card-icon-compact">
                                    <i class="fas fa-sticky-note"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Observações</div>
                                    <div class="card-status-compact" id="observacoes-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="parcelas">
                                <div class="card-icon-compact">
                                    <i class="fas fa-list-ol"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Parcelas</div>
                                    <div class="card-status-compact" id="parcelas-status">Automático</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Parcelas -->
                    <div class="parcelas-section-compact">
                        <div class="parcelas-header-compact">
                            <div class="parcelas-title-compact">
                                <i class="fas fa-list-ol"></i>
                                Parcelas
                            </div>
                            <div class="parcelas-actions-compact">
                                <button type="button" class="btn-compact btn-primary-compact" onclick="calculateParcelas()">
                                    <i class="fas fa-sync"></i>
                                    Calcular Parcelas
                                </button>
                            </div>
                        </div>
                        
                        <div class="parcelas-content-compact">
                            <table class="parcelas-table-compact" id="parcelas-table">
                                <thead>
                                    <tr>
                                        <th>Seq</th>
                                        <th>Vencimento</th>
                                        <th>Valor (R$)</th>
                                        <th>Observações</th>
                                    </tr>
                                </thead>
                                <tbody id="parcelas-tbody">
                                    <!-- Parcelas serão adicionadas dinamicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer com Totais -->
                    <div class="footer-section-compact">
                        <div class="totals-compact">
                            <div class="total-item-compact">
                                <span class="total-label-compact">Total Títulos</span>
                                <span class="total-value-compact" id="total-titulos">0,00</span>
                                <input type="hidden" name="total_titulos" id="total_titulos_hidden" value="0">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Total Descontos</span>
                                <span class="total-value-compact" id="total-descontos">0,00</span>
                                <input type="hidden" name="total_descontos" id="total_descontos_hidden" value="0">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Total Final</span>
                                <span class="total-value-compact" id="total-final">0,00</span>
                                <input type="hidden" name="total_final" id="total_final_hidden" value="0">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Nº Parcelas</span>
                                <span class="total-value-compact" id="total-parcelas">1</span>
                                <input type="hidden" name="total_parcelas" id="total_parcelas_hidden" value="1">
                            </div>
                        </div>
                        
                        <div class="form-actions-compact">
                            <button type="button" class="btn-compact btn-secondary-compact" onclick="window.location.href='../contaspagar.php'">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="btn-compact btn-primary-compact">
                                <i class="fas fa-save"></i>
                                SALVAR CONTA
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- MODAIS -->
            <!-- Modal Entrada -->
            <div id="modal-entrada" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-sign-in-alt"></i> Vincular Entrada</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-grid">
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-search"></i>
                                    Buscar Entrada
                                </label>
                                <div class="supplier-search-compact">
                                    <input type="text" 
                                           id="entrada_search" 
                                           class="modal-form-input" 
                                           placeholder="Digite código ou número da entrada"
                                           autocomplete="off">
                                    <input type="hidden" id="codentrada" name="codentrada" value="">
                                    <div id="entrada-dropdown" class="supplier-dropdown-compact"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="entrada">
                            <i class="fas fa-check"></i>
                            Vincular Entrada
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal NF-e -->
            <div id="modal-nfe" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-file-invoice"></i> Dados da NF-e</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-grid">
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-calendar"></i>
                                    Data Emissão NF-e <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="dataemissao_nfe" id="dataemissao_nfe" class="modal-form-input" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-hashtag"></i>
                                    Série Nota
                                </label>
                                <input type="text" name="serienota" id="serienota" class="modal-form-input" value="1" maxlength="3" placeholder="Série da nota">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-barcode"></i>
                                    Número Nota/Documento
                                </label>
                                <input type="text" name="numeronota" id="numeronota" class="modal-form-input" placeholder="Número da nota fiscal">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="nfe">
                            <i class="fas fa-check"></i>
                            Salvar Dados NF-e
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Veículo -->
            <div id="modal-veiculo" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-truck"></i> Dados do Veículo</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-grid">
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-car"></i>
                                    Placa do Veículo
                                </label>
                                <input type="text" name="placa" id="placa" class="modal-form-input" placeholder="ABC-1234" maxlength="8">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-gas-pump"></i>
                                    Litros Abastecidos
                                </label>
                                <input type="number" name="litros" id="litros" class="modal-form-input" step="0.01" min="0" placeholder="0,00" onchange="calcularMedia()">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-tachometer-alt"></i>
                                    KM Nota Fiscal
                                </label>
                                <input type="number" name="km_nf" id="km_nf" class="modal-form-input" step="0.1" min="0" placeholder="0,0" onchange="calcularMedia()">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-tachometer-alt"></i>
                                    KM Anterior
                                </label>
                                <input type="number" name="km_ant" id="km_ant" class="modal-form-input" step="0.1" min="0" placeholder="0,0" onchange="calcularMedia()">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-calculator"></i>
                                    Média de Consumo (KM/L)
                                </label>
                                <input type="text" name="media" id="media" class="modal-form-input" placeholder="0,00" readonly style="background: #f9fafb;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="veiculo">
                            <i class="fas fa-check"></i>
                            Salvar Dados Veículo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Observações -->
            <div id="modal-observacoes" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-sticky-note"></i> Observações</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label class="modal-form-label">
                                <i class="fas fa-comment-alt"></i>
                                Observações Gerais
                            </label>
                            <textarea name="obs" id="obs" class="modal-form-textarea" 
                                      placeholder="Digite observações sobre esta conta a pagar..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="observacoes">
                            <i class="fas fa-check"></i>
                            Salvar Observações
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Parcelas -->
            <div id="modal-parcelas" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-list-ol"></i> Gerenciar Parcelas</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <p>Parcelas calculadas automaticamente. Edite manualmente se necessário.</p>
                        <div style="max-height: 400px; overflow-y: auto; margin-top: 20px;">
                            <table class="modal-table" id="modal-parcelas-table">
                                <thead>
                                    <tr>
                                        <th>Seq</th>
                                        <th>Vencimento</th>
                                        <th>Valor (R$)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-parcelas-tbody">
                                    <!-- Copia da tabela principal para edição -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="parcelas">
                            <i class="fas fa-check"></i>
                            Atualizar Parcelas
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Listar Todos Fornecedores -->
            <div id="modal-all-suppliers" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-list"></i> Todos os Fornecedores</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div style="max-height: 500px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #374151; color: white;">
                                        <th style="padding: 12px; text-align: left;">Código</th>
                                        <th style="padding: 12px; text-align: left;">Nome</th>
                                        <th style="padding: 12px; text-align: left;">CNPJ/CPF</th>
                                        <th style="padding: 12px; text-align: left;">Cidade/UF</th>
                                        <th style="padding: 12px; text-align: center;">Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="all-suppliers-tbody">
                                    <!-- Fornecedores serão carregados aqui -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Fechar
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    // Variáveis globais
    let parcelaCount = 1;
    let modalDataStore = {};
    let parcelasData = [];

    // Função auxiliar para formatar moeda para display (com vírgula)
    function formatCurrency(value) {
        return parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    // Função auxiliar para parsear moeda (de vírgula para ponto)
    function parseCurrency(value) {
        return parseFloat(value.replace(',', '.')) || 0;
    }

    // Calcular média de consumo
    function calcularMedia() {
        const litros = parseFloat(document.getElementById('litros').value) || 0;
        const km_nf = parseFloat(document.getElementById('km_nf').value) || 0;
        const km_ant = parseFloat(document.getElementById('km_ant').value) || 0;
        
        if (litros > 0 && km_nf > 0 && km_ant > 0 && km_nf > km_ant) {
            const km_rodados = km_nf - km_ant;
            const media = km_rodados / litros;
            document.getElementById('media').value = media.toFixed(2);
        } else {
            document.getElementById('media').value = '';
        }
    }

    // Mostrar/ocultar campo de valor da parcela baseado na condição de pagamento
    function toggleValorParcela() {
        const codcond = document.getElementById('codcond').value;
        const valorParcelaGroup = document.getElementById('valor-parcela-group');
        
        if (codcond) {
            // Buscar informações da condição para verificar número de parcelas
            fetch(`?action=get_condition&codcond=${codcond}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        valorParcelaGroup.classList.remove('show');
                        return;
                    }
                    
                    const numParcelas = parseInt(data.Parcelas) || 1;
                    
                    if (numParcelas > 1) {
                        valorParcelaGroup.classList.add('show');
                    } else {
                        valorParcelaGroup.classList.remove('show');
                        document.getElementById('valor_parcela').value = '';
                    }
                })
                .catch(error => {
                    console.error('Error checking condition:', error);
                    valorParcelaGroup.classList.remove('show');
                });
        } else {
            valorParcelaGroup.classList.remove('show');
            document.getElementById('valor_parcela').value = '';
        }
    }

    // Calcular valor total baseado no valor da parcela
    function calcularValorTotalPorParcela() {
        const valorParcela = parseFloat(document.getElementById('valor_parcela').value) || 0;
        const codcond = document.getElementById('codcond').value;
        
        if (valorParcela > 0 && codcond) {
            fetch(`?action=get_condition&codcond=${codcond}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showToast('Erro ao buscar condição de pagamento', 'error');
                        return;
                    }
                    
                    const numParcelas = parseInt(data.Parcelas) || 1;
                    const valorTotal = valorParcela * numParcelas;
                    
                    document.getElementById('vrtitulo').value = valorTotal.toFixed(2);
                    calculateTotais();
                    
                    if (document.getElementById('dataemissao_nfe').value) {
                        calculateParcelas();
                    }
                })
                .catch(error => {
                    console.error('Error calculating total from parcel:', error);
                    showToast('Erro ao calcular valor total', 'error');
                });
        }
    }

    // Mostrar todos os fornecedores
    function showAllSuppliers() {
        fetch('?action=get_all_suppliers')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('all-suppliers-tbody');
                tbody.innerHTML = '';
                
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">Erro ao carregar fornecedores</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center;">Nenhum fornecedor encontrado</td></tr>';
                    return;
                }
                
                data.forEach(supplier => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid #e5e7eb';
                    row.innerHTML = `
                        <td style="padding: 10px;">${supplier.codcliente}</td>
                        <td style="padding: 10px;">${supplier.Nome}</td>
                        <td style="padding: 10px;">${supplier.cnpj_cpf || '-'}</td>
                        <td style="padding: 10px;">${supplier.Cidade || '-'}/${supplier.Uf || '-'}</td>
                        <td style="padding: 10px; text-align: center;">
                            <button type="button" class="btn-compact btn-primary-compact" onclick="selectSupplier(${supplier.codcliente}, '${supplier.Nome.replace(/'/g, "\\'")}')" style="padding: 6px 12px; font-size: 11px;">
                                <i class="fas fa-check"></i>
                                Selecionar
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                document.getElementById('modal-all-suppliers').classList.add('active');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error loading all suppliers:', error);
                showToast('Erro ao carregar fornecedores', 'error');
            });
    }

    // Selecionar fornecedor da lista
    function selectSupplier(codcliente, nome) {
        document.getElementById('supplier_search').value = nome;
        document.getElementById('codcliente').value = codcliente;
        document.getElementById('modal-all-suppliers').classList.remove('active');
        document.body.style.overflow = '';
        showToast(`Fornecedor ${nome} selecionado`, 'success');
    }

    // JavaScript para gerenciar funcionalidades
    document.addEventListener('DOMContentLoaded', function() {
        // Toast notification system
        window.showToast = function(message, type = 'success') {
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
                    if (toast.parentNode === container) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        };

        // Show PHP message if exists
        <?php if ($mensagem): ?>
        showToast('<?php echo addslashes($mensagem); ?>', '<?php echo $tipo_mensagem; ?>');
        <?php endif; ?>

        // Load dropdowns
        loadExpenseTypes();
        loadConditions();
        loadPaymentTypes();

        // Modal Management
        const modals = document.querySelectorAll('.modal');
        const modalCards = document.querySelectorAll('.modal-card-compact');
        const modalCloses = document.querySelectorAll('.modal-close, .modal-cancel');
        const modalSaves = document.querySelectorAll('.modal-save');

        // Open modals when cards are clicked
        modalCards.forEach(card => {
            card.addEventListener('click', function() {
                const modalId = this.getAttribute('data-modal');
                const modal = document.getElementById(`modal-${modalId}`);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    
                    if (modalId === 'parcelas') {
                        const mainTbody = document.getElementById('parcelas-tbody');
                        const modalTbody = document.getElementById('modal-parcelas-tbody');
                        modalTbody.innerHTML = mainTbody.innerHTML;
                    }
                }
            });
        });

        // Close modals
        modalCloses.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Save modal data
        modalSaves.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                const section = this.getAttribute('data-section');
                
                if (section === 'entrada') {
                    modalDataStore.entrada = {
                        codentrada: document.getElementById('codentrada').value
                    };
                }
                
                if (section === 'nfe') {
                    modalDataStore.nfe = {
                        dataemissao_nfe: document.getElementById('dataemissao_nfe').value,
                        serienota: document.getElementById('serienota').value,
                        numeronota: document.getElementById('numeronota').value
                    };
                    // Criar hidden inputs se necessário
                    ['serienota', 'numeronota'].forEach(field => {
                        let hidden = document.querySelector(`input[name="${field}"]`);
                        if (!hidden) {
                            hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = field;
                            document.getElementById('contaForm').appendChild(hidden);
                        }
                        hidden.value = modalDataStore.nfe[field];
                    });
                    
                    // Data de emissão agora vem do modal NF-e
                    let dataEmissaoHidden = document.querySelector('input[name="dataemissao"]');
                    if (!dataEmissaoHidden) {
                        dataEmissaoHidden = document.createElement('input');
                        dataEmissaoHidden.type = 'hidden';
                        dataEmissaoHidden.name = 'dataemissao';
                        document.getElementById('contaForm').appendChild(dataEmissaoHidden);
                    }
                    dataEmissaoHidden.value = modalDataStore.nfe.dataemissao_nfe;
                }
                
                if (section === 'veiculo') {
                    modalDataStore.veiculo = {
                        placa: document.getElementById('placa').value,
                        litros: document.getElementById('litros').value,
                        km_nf: document.getElementById('km_nf').value,
                        km_ant: document.getElementById('km_ant').value,
                        media: document.getElementById('media').value
                    };
                    // Criar hidden inputs
                    ['placa', 'litros', 'km_nf', 'km_ant', 'media'].forEach(field => {
                        let hidden = document.querySelector(`input[name="${field}"]`);
                        if (!hidden) {
                            hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = field;
                            document.getElementById('contaForm').appendChild(hidden);
                        }
                        hidden.value = modalDataStore.veiculo[field];
                    });
                }
                
                if (section === 'observacoes') {
                    modalDataStore.observacoes = {
                        obs: document.getElementById('obs').value
                    };
                    let obsHidden = document.querySelector('input[name="obs"]');
                    if (!obsHidden) {
                        obsHidden = document.createElement('input');
                        obsHidden.type = 'hidden';
                        obsHidden.name = 'obs';
                        document.getElementById('contaForm').appendChild(obsHidden);
                    }
                    obsHidden.value = modalDataStore.observacoes.obs;
                }
                
                if (section === 'parcelas') {
                    const modalTbody = document.getElementById('modal-parcelas-tbody');
                    const mainTbody = document.getElementById('parcelas-tbody');
                    mainTbody.innerHTML = modalTbody.innerHTML;
                    calculateTotais();
                }
                
                // Update card status
                updateCardStatus(section);
                
                // Close modal
                modal.classList.remove('active');
                document.body.style.overflow = '';
                
                showToast(`Dados de ${section} salvos com sucesso!`, 'success');
            });
        });

        // Close modal when clicking outside
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    activeModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });

        function updateCardStatus(section) {
            const statusBadge = document.getElementById(`${section}-status`);
            if (statusBadge) {
                statusBadge.textContent = 'OK';
                statusBadge.className = 'card-status-compact success';
            }
        }

        // Load expense types
        function loadExpenseTypes() {
            fetch('?action=search_expense_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('codtpdes');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    if (data.error) return;
                    
                    data.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.codtpdes;
                        option.textContent = type.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading expense types:', error);
                });
        }

        // Load conditions
        function loadConditions() {
            fetch('?action=search_conditions')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('codcond');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    if (data.error) return;
                    
                    data.forEach(condition => {
                        const option = document.createElement('option');
                        option.value = condition.codcond;
                        option.textContent = condition.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading conditions:', error);
                });
        }

        // Load payment types
        function loadPaymentTypes() {
            fetch('?action=search_payment_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('codtppag');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    if (data.error) return;
                    
                    data.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.codtppag;
                        option.textContent = type.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading payment types:', error);
                });
        }

        // Supplier search functionality
        const supplierSearch = document.getElementById('supplier_search');
        const supplierDropdown = document.getElementById('supplier-dropdown');
        let supplierTimeout;

        if (supplierSearch) {
            supplierSearch.addEventListener('input', function() {
                clearTimeout(supplierTimeout);
                const term = this.value.trim();
                
                if (term.length < 2) {
                    supplierDropdown.classList.remove('show');
                    return;
                }
                
                supplierTimeout = setTimeout(() => {
                    searchSuppliers(term);
                }, 300);
            });
        }

        function searchSuppliers(term) {
            fetch(`?action=search_suppliers&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    supplierDropdown.innerHTML = '';
                    
                    if (data.length === 0) {
                        supplierDropdown.innerHTML = '<div class="supplier-item-compact">Nenhum fornecedor encontrado</div>';
                    } else {
                        data.forEach(supplier => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <div style="font-weight: 600;">${supplier.Nome}</div>
                                <div style="font-size: 11px; color: #64748b;">Código: ${supplier.codcliente} | CNPJ: ${supplier.cnpj_cpf}</div>
                                <div style="font-size: 11px; color: #64748b;">${supplier.Cidade}/${supplier.Uf}</div>
                            `;
                            item.addEventListener('click', function() {
                                supplierSearch.value = supplier.Nome;
                                document.getElementById('codcliente').value = supplier.codcliente;
                                supplierDropdown.classList.remove('show');
                            });
                            supplierDropdown.appendChild(item);
                        });
                    }
                    
                    supplierDropdown.classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Entrada search functionality
        const entradaSearch = document.getElementById('entrada_search');
        const entradaDropdown = document.getElementById('entrada-dropdown');
        let entradaTimeout;

        if (entradaSearch) {
            entradaSearch.addEventListener('input', function() {
                clearTimeout(entradaTimeout);
                const term = this.value.trim();
                
                if (term.length < 2) {
                    entradaDropdown.classList.remove('show');
                    return;
                }
                
                entradaTimeout = setTimeout(() => {
                    searchEntradas(term);
                }, 300);
            });
        }

        function searchEntradas(term) {
            fetch(`?action=search_entradas&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    entradaDropdown.innerHTML = '';
                    
                    if (data.length === 0) {
                        entradaDropdown.innerHTML = '<div class="supplier-item-compact">Nenhuma entrada encontrada</div>';
                    } else {
                        data.forEach(entrada => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <div style="font-weight: 600;">Entrada ${entrada.codentrada}</div>
                                <div style="font-size: 11px; color: #64748b;">NF: ${entrada.numeronota}/${entrada.serienota}</div>
                                <div style="font-size: 11px; color: #64748b;">Data: ${new Date(entrada.Dataentrada).toLocaleDateString('pt-BR')}</div>
                            `;
                            item.addEventListener('click', function() {
                                entradaSearch.value = `Entrada ${entrada.codentrada}`;
                                document.getElementById('codentrada').value = entrada.codentrada;
                                entradaDropdown.classList.remove('show');
                            });
                            entradaDropdown.appendChild(item);
                        });
                    }
                    
                    entradaDropdown.classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Hide dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.supplier-search-compact')) {
                supplierDropdown.classList.remove('show');
                if (entradaDropdown) {
                    entradaDropdown.classList.remove('show');
                }
            }
        });

        // Calcular totais básicos
        window.calculateTotais = function() {
            const vrtitulo = parseFloat(document.getElementById('vrtitulo').value) || 0;
            const vrdesconto = parseFloat(document.getElementById('vrdesconto').value) || 0;
            const vracrescimo = parseFloat(document.getElementById('vracrescimo').value) || 0;
            const vrfinal = vrtitulo - vrdesconto + vracrescimo;
            
            document.getElementById('vrfinal').value = vrfinal.toFixed(2);
            document.getElementById('total-titulos').textContent = formatCurrency(vrtitulo);
            document.getElementById('total-descontos').textContent = formatCurrency(vrdesconto);
            document.getElementById('total-final').textContent = formatCurrency(vrfinal);
            
            document.getElementById('total_titulos_hidden').value = vrtitulo.toFixed(2);
            document.getElementById('total_descontos_hidden').value = vrdesconto.toFixed(2);
            document.getElementById('total_final_hidden').value = vrfinal.toFixed(2);
        };

        // Calcular parcelas baseado em condição de pagamento
        window.calculateParcelas = function() {
            const codcond = document.getElementById('codcond').value;
            const dataemissao = document.getElementById('dataemissao_nfe').value || document.getElementById('dataemissao_nfe').defaultValue;
            const vrfinal = parseFloat(document.getElementById('vrfinal').value) || 0;
            
            if (!codcond || !dataemissao || vrfinal <= 0) {
                showToast('Preencha condição de pagamento, data de emissão NF-e e valor para calcular parcelas', 'warning');
                return;
            }
            
            fetch(`?action=get_condition&codcond=${codcond}`)
                .then(response => response.json())
                .then(data => {
                    let numParcelas = 1;
                    let diasVencimentos = [0];
                    
                    if (data.error) {
                        console.error('Error:', data.error);
                    } else {
                        numParcelas = parseInt(data.Parcelas) || 1;
                        diasVencimentos = [];
                        for (let i = 1; i <= numParcelas; i++) {
                            const campo = `CondPgto${i}`;
                            diasVencimentos.push(parseInt(data[campo]) || 0);
                        }
                    }
                    
                    const tbody = document.getElementById('parcelas-tbody');
                    tbody.innerHTML = '';
                    parcelaCount = 1;
                    
                    const valorParcelaBase = vrfinal / numParcelas;
                    let totalParcelasValor = 0;
                    
                    diasVencimentos.forEach((dias, index) => {
                        const numeroParcela = index + 1;
                        const valorParcela = numeroParcela < numParcelas ? valorParcelaBase : (vrfinal - (numeroParcela - 1) * valorParcelaBase);
                        const dataVencimento = new Date(dataemissao);
                        dataVencimento.setDate(dataVencimento.getDate() + dias);
                        const dataVencStr = dataVencimento.toISOString().split('T')[0];
                        const obs = `Parcela ${numeroParcela} de ${numParcelas}`;
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${numeroParcela}</td>
                            <td><input type="date" value="${dataVencStr}" onchange="updateParcelaData(${numeroParcela}, this.value)" style="width: 100px;"></td>
                            <td><input type="number" step="0.01" value="${valorParcela.toFixed(2)}" onchange="updateParcelaValor(${numeroParcela}, this.value)" style="width: 100px; text-align: right;"></td>
                            <td><input type="text" value="${obs}" style="width: 100%;"></td>
                        `;
                        tbody.appendChild(row);
                        totalParcelasValor += valorParcela;
                        parcelaCount++;
                    });
                    
                    document.getElementById('total-parcelas').textContent = numParcelas;
                    document.getElementById('total_parcelas_hidden').value = numParcelas;
                    calculateTotais();
                    
                    showToast(`${numParcelas} parcela(s) calculada(s) com sucesso!`, 'success');
                })
                .catch(error => {
                    console.error('Error calculating parcelas:', error);
                    showToast('Erro ao calcular parcelas. Verifique os dados.', 'error');
                    
                    // Fallback: parcela única
                    const tbody = document.getElementById('parcelas-tbody');
                    tbody.innerHTML = '';
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>1</td>
                        <td><input type="date" value="${dataemissao}" style="width: 100px;"></td>
                        <td><input type="number" step="0.01" value="${vrfinal.toFixed(2)}" style="width: 100px; text-align: right;"></td>
                        <td><input type="text" value="Parcela única" style="width: 100%;"></td>
                    `;
                    tbody.appendChild(row);
                    document.getElementById('total-parcelas').textContent = 1;
                    document.getElementById('total_parcelas_hidden').value = 1;
                    calculateTotais();
                });
        };

        // Funções auxiliares para atualizar parcelas individualmente
        window.updateParcelaData = function(seq, data) {
            calculateTotais();
        };

        window.updateParcelaValor = function(seq, valor) {
            let totalValor = 0;
            const inputs = document.querySelectorAll('#parcelas-tbody input[type="number"]');
            inputs.forEach(input => {
                totalValor += parseFloat(input.value) || 0;
            });
            document.getElementById('total-final').textContent = formatCurrency(totalValor);
            document.getElementById('total_final_hidden').value = totalValor.toFixed(2);
        };

        // Event listeners para campos que afetam cálculos
        document.getElementById('vrtitulo').addEventListener('input', function() {
            calculateTotais();
            if (document.getElementById('codcond').value) {
                calculateParcelas();
            }
        });

        document.getElementById('vrdesconto').addEventListener('input', function() {
            calculateTotais();
        });

        document.getElementById('vracrescimo').addEventListener('input', function() {
            calculateTotais();
        });

        document.getElementById('codcond').addEventListener('change', function() {
            toggleValorParcela();
            calculateParcelas();
        });

        // Validação do formulário antes do submit
        document.getElementById('contaForm').addEventListener('submit', function(e) {
            const requiredFields = [
                'codcliente', 'codcond', 'codtppag', 'codtpdes', 'vrtitulo'
            ];
            
            let isValid = true;
            let errorMsg = '';
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input || !input.value.trim()) {
                    isValid = false;
                    errorMsg += `Campo ${input ? input.name || field : field} é obrigatório. `;
                }
            });
            
            // Verificar se data de emissão NF-e foi preenchida
            const dataEmissaoNfe = document.getElementById('dataemissao_nfe').value;
            if (!dataEmissaoNfe) {
                isValid = false;
                errorMsg += 'Data de emissão NF-e é obrigatória. ';
            }
            
            const vrtitulo = parseFloat(document.getElementById('vrtitulo').value);
            if (vrtitulo <= 0) {
                isValid = false;
                errorMsg += 'Valor do título deve ser maior que zero. ';
            }
            
            if (!isValid) {
                e.preventDefault();
                showToast(errorMsg, 'error');
                return false;
            }
            
            // Adicionar dados dos modais ao form
            if (modalDataStore.entrada) {
                document.getElementById('codentrada').value = modalDataStore.entrada.codentrada;
            }
            
            if (modalDataStore.nfe) {
                ['serienota', 'numeronota'].forEach(field => {
                    let hidden = document.querySelector(`input[name="${field}"]`);
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = field;
                        document.getElementById('contaForm').appendChild(hidden);
                    }
                    hidden.value = modalDataStore.nfe[field];
                });
                
                // Garantir que data de emissão está definida
                let dataEmissaoHidden = document.querySelector('input[name="dataemissao"]');
                if (!dataEmissaoHidden) {
                    dataEmissaoHidden = document.createElement('input');
                    dataEmissaoHidden.type = 'hidden';
                    dataEmissaoHidden.name = 'dataemissao';
                    document.getElementById('contaForm').appendChild(dataEmissaoHidden);
                }
                dataEmissaoHidden.value = modalDataStore.nfe.dataemissao_nfe;
            }
            
            if (modalDataStore.veiculo) {
                ['placa', 'litros', 'km_nf', 'km_ant', 'media'].forEach(field => {
                    let hidden = document.querySelector(`input[name="${field}"]`);
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = field;
                        document.getElementById('contaForm').appendChild(hidden);
                    }
                    hidden.value = modalDataStore.veiculo[field];
                });
            }
            
            if (modalDataStore.observacoes) {
                let obsHidden = document.querySelector('input[name="obs"]');
                if (!obsHidden) {
                    obsHidden = document.createElement('input');
                    obsHidden.type = 'hidden';
                    obsHidden.name = 'obs';
                    document.getElementById('contaForm').appendChild(obsHidden);
                }
                obsHidden.value = modalDataStore.observacoes.obs;
            }
            
            showToast('Salvando conta a pagar...', 'info');
        });

        // Inicializar cálculos
        calculateTotais();

        // Máscara para placa
        const placaInput = document.getElementById('placa');
        if (placaInput) {
            placaInput.addEventListener('input', function(e) {
                let value = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
                if (value.length >= 3 && value.length <= 4) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                }
                e.target.value = value.slice(0, 8);
            });
        }

        // Auto-format para ano safra
        const anoSafraInput = document.getElementById('ano_safra');
        if (anoSafraInput) {
            anoSafraInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
                if (this.value.length === 4 && parseInt(this.value) < 2000) {
                    this.value = '';
                }
            });
        }
    });

    // Adicionar animação para toast
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .toast {
            animation: slideInRight 0.3s ease forwards;
        }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>