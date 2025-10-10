<?php
session_start();
// ========================================
// API PARA SALVAR ENTRADA DE PRODUTOS
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Faça login novamente.']);
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão de autenticação.']);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do usuário autenticado
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário.']);
    exit;
}

require_once '../config/databaselogin.php'; // o arquivo que contém a classe Database

$database = new Database();
$pdo = $database->getConnection();

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

function obterProximoCodigoEntrada($idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codentrada) as max_cod FROM entradas WHERE idcliente = ?");
        $stmt->execute([$idcliente_empresa]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON do corpo da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados JSON inválidos']);
    exit;
}

try {
    $pdo = conectarBanco();
    $pdo->beginTransaction();
    
    // Obter próximo código de entrada (com filtro por empresa)
    $codentrada = obterProximoCodigoEntrada($idcliente_empresa);
    
    // Calcular total do frete (frete dos produtos + valor do frete adicional)
    $total_frete_produtos = 0;
    if (isset($data['produtos']) && is_array($data['produtos'])) {
        foreach ($data['produtos'] as $produto) {
            $freteUnitario = (float)($produto['frete_unitario'] ?? 0);
            $quantidade = (float)($produto['quantidade'] ?? 0);
            $total_frete_produtos += $freteUnitario * $quantidade;
        }
    }
    $valor_frete_adicional = (float)($data['valor_frete'] ?? 0);
    $total_frete = $total_frete_produtos + $valor_frete_adicional;
    
    // Tratar campo codtransp - se vazio, definir como NULL
    $codtransp = null;
    if (!empty($data['transporter_id'])) {
        $codtransp = (int)$data['transporter_id'];
    }
    
    // Inserir entrada principal
    $dadosEntrada = [
        'idcliente' => $idcliente_empresa, // ID dinâmico da empresa
        'codempresa' => 1,
        'codentrada' => $codentrada,
        'serienota' => $data['serie_nf'] ?? '1',
        'numeronota' => $data['nf_numero'] ?? null,
        'Codcliente' => $data['supplier_id'] ?? null,
        'Dataentrada' => $data['data_entrada'],
        'Pedido' => $data['pedido'] ?? null,
        'tipooperacao' => isset($data['devolucao']) && $data['devolucao'] == 'on' ? 'D' : 'E',
        'vricms' => $data['total_icms'] ?? 0,
        'vripi' => $data['total_ipi'] ?? 0,
        'vrdesconto' => $data['total_desconto'] ?? 0,
        'vrprodutos' => $data['total_produtos'] ?? 0,
        'vrTotal' => $data['total_geral'] ?? 0,
        'obs' => $data['observacoes_gerais'] ?? '',
        'ano_safra' => $data['ano_safra'] ?? '',
        'cancelado' => 'N',
        'serieentrada' => $data['serie_nf'] ?? '',
        'notaentrada' => $data['nf_entrada'] ?? '',
        'placa' => $data['placa_veiculo'] ?? '',
        'codtransp' => $codtransp,
        'total_frete' => $total_frete,
        'dtnota' => $data['dt_emissao'] ?? null,
        'tipo_emitente' => 'T',
        'NumChaveNfe' => $data['chave_acesso'] ?? '',
        'nitens' => count($data['produtos'] ?? []),
        'nitenstot' => count($data['produtos'] ?? []),
        'total_nfe' => $data['total_geral'] ?? 0,
        'insumos' => 'N',
        'codmotorista' => null,
        'codtpdes' => $data['expense_type'] ?? null,
        'codcond' => $data['payment_condition'] ?? null,
        'codtppag' => $data['payment_type'] ?? null
    ];
    
    $camposEntrada = implode(', ', array_keys($dadosEntrada));
    $placeholdersEntrada = ':' . implode(', :', array_keys($dadosEntrada));
    
    $sqlEntrada = "INSERT INTO entradas ({$camposEntrada}) VALUES ({$placeholdersEntrada})";
    $stmtEntrada = $pdo->prepare($sqlEntrada);
    $stmtEntrada->execute($dadosEntrada);
    
    // Inserir itens da entrada
    if (isset($data['produtos']) && is_array($data['produtos'])) {
        $seq = 1;
        foreach ($data['produtos'] as $produto) {
            if (empty($produto['codproduto'])) continue;
            
            $dadosItem = [
                'idcliente' => $idcliente_empresa,
                'codentrada' => $codentrada,
                'seq' => $seq,
                'Codproduto' => $produto['codproduto'],
                'Un' => $produto['unidade'] ?? '',
                'Qtd' => $produto['quantidade'] ?? 0,
                'Vrunit' => $produto['valor_unitario'] ?? 0,
                'Total' => $produto['total'] ?? 0,
                'estoque' => $produto['estoque'] ?? 'S',
                'vrdesconto' => $produto['desconto'] ?? 0,
                'cfop' => $produto['cfop'] ?? '',
                'freteunit' => $produto['frete_unitario'] ?? 0,
                'stipi' => $produto['sit_ipi'] ?? '',
                'bc_ipi' => $produto['bc_ipi'] ?? 0,
                'percipi' => $produto['perc_ipi'] ?? 0,
                'vripi' => $produto['vl_ipi'] ?? 0,
                'sticms' => $produto['sit_icms'] ?? '',
                'bc_icms' => $produto['bc_icms'] ?? 0,
                'percicms' => $produto['perc_icms'] ?? 0,
                'vricms' => $produto['vl_icms'] ?? 0,
                'bc_icms_st' => $produto['bc_icms_st'] ?? 0,
                'vl_icms_st' => $produto['vl_icms_st'] ?? 0,
                'stpis' => $produto['sit_pis'] ?? '',
                'bc_pis' => $produto['bc_pis'] ?? 0,
                'ppis' => $produto['perc_pis'] ?? 0,
                'vl_pis' => $produto['vl_pis'] ?? 0,
                'stcofins' => $produto['sit_cofins'] ?? '',
                'bc_cofins' => $produto['bc_cofins'] ?? 0,
                'pcofins' => $produto['perc_cofins'] ?? 0,
                'vl_cofins' => $produto['vl_cofins'] ?? 0,
                'imp_di_num' => '',
                'imp_di_data' => '2012-01-01',
                'imp_desem_local' => 'NOME DA CIDADE',
                'imp_desem_uf' => 'PR',
                'imp_desem_data' => '2012-01-01',
                'imp_adicao_num' => '0',
                'imp_adicao_valor' => 0,
                'imp_pedcom_num' => '0',
                'imp_pedcom_item' => 0,
                'imp_basecalc' => 0,
                'imp_aliq' => 0,
                'imp_valor' => 0,
                'imp_txaduana' => 0,
                'imp_iof' => 0
            ];
            
            $camposItem = implode(', ', array_keys($dadosItem));
            $placeholdersItem = ':' . implode(', :', array_keys($dadosItem));
            
            $sqlItem = "INSERT INTO entradas_itens ({$camposItem}) VALUES ({$placeholdersItem})";
            $stmtItem = $pdo->prepare($sqlItem);
            $stmtItem->execute($dadosItem);
            
            $seq++;
        }
    }
    
    // Inserir centros de custo
    if (isset($data['cost_centers']) && is_array($data['cost_centers'])) {
        foreach ($data['cost_centers'] as $index => $cc) {
            if (empty($cc['codcc'])) continue;
            
            $dadosCC = [
                'idcliente' => $idcliente_empresa,
                'codentrada' => $codentrada,
                'codcc' => $cc['codcc'],
                'placa' => $cc['placa'] ?? '',
                'valor' => $cc['valor'] ?? 0,
                'obs' => $cc['obs'] ?? ''
            ];
            
            $camposCC = implode(', ', array_keys($dadosCC));
            $placeholdersCC = ':' . implode(', :', array_keys($dadosCC));
            
            $sqlCC = "INSERT INTO entradas_cc ({$camposCC}) VALUES ({$placeholdersCC})";
            $stmtCC = $pdo->prepare($sqlCC);
            $stmtCC->execute($dadosCC);
        }
    }
    
    // Inserir descontos
    if (isset($data['discounts']) && is_array($data['discounts'])) {
        $seqDesc = 1;
        foreach ($data['discounts'] as $index => $desconto) {
            if (empty($desconto['descricao'])) continue;
            
            $dadosDesconto = [
                'idcliente' => $idcliente_empresa,
                'codentrada' => $codentrada,
                'seq' => $seqDesc,
                'datalcto' => date('Y-m-d'),
                'descricao' => $desconto['descricao'],
                'valor' => $desconto['valor'] ?? 0
            ];
            
            $camposDesconto = implode(', ', array_keys($dadosDesconto));
            $placeholdersDesconto = ':' . implode(', :', array_keys($dadosDesconto));
            
            $sqlDesconto = "INSERT INTO entradas_descontos ({$camposDesconto}) VALUES ({$placeholdersDesconto})";
            $stmtDesconto = $pdo->prepare($sqlDesconto);
            $stmtDesconto->execute($dadosDesconto);
            
            $seqDesc++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Entrada de produtos salva com sucesso!',
        'data' => [
            'codentrada' => $codentrada,
            'data_entrada' => date('d/m/Y'),
            'empresa_id' => $idcliente_empresa
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar entrada: ' . $e->getMessage()
    ]);
}
?>