<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
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
    echo json_encode(['success' => false, 'message' => 'Erro na conexão auth: ' . $e->getMessage()]);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Validar usuário e obter empresa_id
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário: ' . $e->getMessage()]);
    exit;
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    // Include database connection
    require_once '../config/database.php';
    
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Validate required fields
    if (empty($data['Descricao'])) {
        throw new Exception('Descrição é obrigatória');
    }

    // ==================== VALIDAÇÃO DE SEGURANÇA ====================
    
    // Usar o idcliente da empresa logada (não confiar no input)
    $idcliente = $idcliente_empresa;
    
    // Sanitize and validate input data
    $descricao = trim($data['Descricao']);
    
    // Validate description length
    if (strlen($descricao) > 30) {
        throw new Exception('Descrição deve ter no máximo 30 caracteres');
    }
    
    if (strlen($descricao) < 1) {
        throw new Exception('Descrição não pode estar vazia');
    }
    
    // Validar caracteres na descrição
    if (!preg_match('/^[a-zA-Z0-9\sÀ-ÿ.,\-_]+$/u', $descricao)) {
        throw new Exception('Descrição contém caracteres inválidos');
    }
    
    // Verificar se já existe tipo de pagamento com mesma descrição para esta empresa
    $checkDesc = $pdo->prepare("SELECT COUNT(*) FROM tipopagamentos WHERE idcliente = ? AND Descricao = ?");
    $checkDesc->execute([$idcliente, $descricao]);
    if ($checkDesc->fetchColumn() > 0) {
        throw new Exception('Já existe um tipo de pagamento com esta descrição para sua empresa');
    }
    
    // Validar campos numéricos
    $prazo = isset($data['prazo']) ? intval($data['prazo']) : 0;
    if ($prazo < 0 || $prazo > 999) {
        throw new Exception('Prazo deve estar entre 0 e 999 dias');
    }
    
    $taxaboleto = null;
    if (!empty($data['taxaboleto'])) {
        $taxaboleto = floatval($data['taxaboleto']);
        if ($taxaboleto < 0) {
            throw new Exception('Taxa do boleto não pode ser negativa');
        }
    }
    
    $pcomissao = null;
    if (!empty($data['pcomissao'])) {
        $pcomissao = floatval($data['pcomissao']);
        if ($pcomissao < 0 || $pcomissao > 100) {
            throw new Exception('Percentual de comissão deve estar entre 0 e 100');
        }
    }
    
    $ID_BANCO = null;
    if (!empty($data['ID_BANCO'])) {
        $ID_BANCO = intval($data['ID_BANCO']);
        if ($ID_BANCO <= 0) {
            throw new Exception('ID do banco deve ser um número positivo');
        }
    }
    
    // Validar tipo de cartão
    $tipo_cartao = $data['tipo_cartao'] ?? 'O';
    if (!in_array($tipo_cartao, ['O', 'D', 'C'])) {
        throw new Exception('Tipo de cartão inválido');
    }
    
    // Validar CFOPs
    $cfop1 = null;
    if (!empty($data['cfop1'])) {
        $cfop1 = preg_replace('/[^0-9]/', '', $data['cfop1']);
        if (strlen($cfop1) > 5) {
            throw new Exception('CFOP deve ter no máximo 5 dígitos');
        }
    }
    
    $cfop2 = null;
    if (!empty($data['cfop2'])) {
        $cfop2 = preg_replace('/[^0-9]/', '', $data['cfop2']);
        if (strlen($cfop2) > 5) {
            throw new Exception('CFOP deve ter no máximo 5 dígitos');
        }
    }
    
    // Gerar próximo codtppag automaticamente baseado no idcliente
    $stmt = $pdo->prepare("SELECT MAX(codtppag) as max_cod FROM tipopagamentos WHERE idcliente = ?");
    $stmt->execute([$idcliente]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $proximoCodigo = ($result['max_cod'] ?? 0) + 1;
    
    // Verificar se o código gerado já existe (precaução extra)
    $stmt = $pdo->prepare("SELECT id FROM tipopagamentos WHERE codtppag = ? AND idcliente = ?");
    $stmt->execute([$proximoCodigo, $idcliente]);
    if ($stmt->fetch()) {
        // Se por algum motivo já existir, incrementar até encontrar um livre
        do {
            $proximoCodigo++;
            $stmt = $pdo->prepare("SELECT id FROM tipopagamentos WHERE codtppag = ? AND idcliente = ?");
            $stmt->execute([$proximoCodigo, $idcliente]);
        } while ($stmt->fetch());
    }
    
    // Prepare data for insertion
    $insertData = [
        'idcliente' => $idcliente,
        'codtppag' => $proximoCodigo,
        'Descricao' => $descricao,
        'atualiza' => isset($data['atualiza']) && $data['atualiza'] === 'S' ? 'S' : 'N',
        'imprime' => isset($data['imprime']) && $data['imprime'] === 'S' ? 'S' : 'N',
        'orcamento' => isset($data['orcamento']) && $data['orcamento'] === 'S' ? 'S' : 'N',
        'cartao' => isset($data['cartao']) && $data['cartao'] === 'S' ? 'S' : 'N',
        'nconta' => !empty($data['nconta']) ? intval($data['nconta']) : null,
        'avista' => isset($data['avista']) && $data['avista'] === 'S' ? 'S' : 'N',
        'comissao' => isset($data['comissao']) && $data['comissao'] === 'S' ? 'S' : 'N',
        'prazo' => $prazo,
        'automatico_entrada' => isset($data['automatico_entrada']) && $data['automatico_entrada'] === 'S' ? 'S' : 'N',
        'automatico_principal' => isset($data['automatico_principal']) && $data['automatico_principal'] === 'S' ? 'S' : 'N',
        'bloqueto' => isset($data['bloqueto']) && $data['bloqueto'] === 'S' ? 'S' : 'N',
        'condicional' => isset($data['condicional']) && $data['condicional'] === 'S' ? 'S' : 'N',
        'tipo_cartao' => $tipo_cartao,
        'locacao' => isset($data['locacao']) && $data['locacao'] === 'S' ? 'S' : 'N',
        'troca' => isset($data['troca']) && $data['troca'] === 'S' ? 'S' : 'N',
        'duplicata' => isset($data['duplicata']) && $data['duplicata'] === 'S' ? 'S' : 'N',
        'antecipado' => isset($data['antecipado']) && $data['antecipado'] === 'S' ? 'S' : 'N',
        'cfop1' => $cfop1,
        'cfop2' => $cfop2,
        'devolucao' => isset($data['devolucao']) && $data['devolucao'] === 'S' ? 'S' : 'N',
        'ID_BANCO' => $ID_BANCO,
        'EMITE_NFE' => isset($data['EMITE_NFE']) && $data['EMITE_NFE'] === 'S' ? 'S' : 'N',
        'EMITE_NFCE' => isset($data['EMITE_NFCE']) && $data['EMITE_NFCE'] === 'S' ? 'S' : 'N',
        'ABRE_GAVETA' => isset($data['ABRE_GAVETA']) && $data['ABRE_GAVETA'] === 'S' ? 'S' : 'N',
        'transferido' => isset($data['transferido']) && $data['transferido'] === 'S' ? 'S' : 'N',
        'taxaboleto' => $taxaboleto,
        'lotes' => isset($data['lotes']) && $data['lotes'] === 'S' ? 'S' : 'N',
        'pcomissao' => $pcomissao
    ];
    
    // Build INSERT query
    $columns = array_keys($insertData);
    $placeholders = array_fill(0, count($columns), '?');
    
    $sql = "INSERT INTO tipopagamentos (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($insertData));
    
    $tipoPagamentoId = $pdo->lastInsertId();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Tipo de pagamento cadastrado com sucesso',
        'tipopagamento' => [
            'id' => $tipoPagamentoId,
            'idcliente' => $idcliente,
            'codtppag' => $proximoCodigo,
            'Descricao' => $descricao
        ],
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);
    
} catch (PDOException $e) {
    // Database error
    $errorMessage = 'Erro no banco de dados';
    
    // Check for specific database errors
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'Erro interno: código duplicado detectado';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>