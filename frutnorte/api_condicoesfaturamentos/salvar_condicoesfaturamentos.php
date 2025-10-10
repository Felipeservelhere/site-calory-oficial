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

    if (!isset($data['Parcelas'])) {
        throw new Exception('Número de parcelas é obrigatório');
    }

    // ==================== VALIDAÇÃO DE SEGURANÇA ====================

    // Validar número de parcelas
    $parcelas = intval($data['Parcelas']);
    if ($parcelas < 0 || $parcelas > 12) {
        throw new Exception('Número de parcelas deve estar entre 0 e 12');
    }

    // Usar o idcliente da empresa logada (não confiar no input)
    $idcliente = $idcliente_empresa;

    // Gerar próximo codcond automaticamente baseado no idcliente
    $stmt = $pdo->prepare("SELECT MAX(codcond) as max_cod FROM condicoes WHERE idcliente = ?");
    $stmt->execute([$idcliente]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $proximoCodigo = ($result['max_cod'] ?? 0) + 1;

    // Verificar se o código gerado já existe (precaução extra)
    $stmt = $pdo->prepare("SELECT id FROM condicoes WHERE codcond = ? AND idcliente = ?");
    $stmt->execute([$proximoCodigo, $idcliente]);
    if ($stmt->fetch()) {
        // Se por algum motivo já existir, incrementar até encontrar um livre
        do {
            $proximoCodigo++;
            $stmt = $pdo->prepare("SELECT id FROM condicoes WHERE codcond = ? AND idcliente = ?");
            $stmt->execute([$proximoCodigo, $idcliente]);
        } while ($stmt->fetch());
    }

    // Validar que pelo menos uma condição de pagamento foi preenchida (se parcelas > 0)
    if ($parcelas > 0) {
        $hasPaymentCondition = false;
        for ($i = 1; $i <= min($parcelas, 6); $i++) {
            $fieldName = $i <= 4 ? "CondPgto{$i}" : "condpgto{$i}";
            if (!empty($data[$fieldName]) && $data[$fieldName] !== null && $data[$fieldName] !== '') {
                $hasPaymentCondition = true;
                break;
            }
        }

        if (!$hasPaymentCondition) {
            throw new Exception('Preencha pelo menos uma condição de pagamento para o número de parcelas especificado');
        }
    }

    // Prepare data for insertion
    $insertData = [
        'idcliente'   => $idcliente,
        'codcond'     => $proximoCodigo,
        'Descricao'   => trim($data['Descricao']),
        'Parcelas'    => $parcelas,
        'CondPgto1'   => !empty($data['CondPgto1']) ? intval($data['CondPgto1']) : null,
        'CondPgto2'   => !empty($data['CondPgto2']) ? intval($data['CondPgto2']) : null,
        'CondPgto3'   => !empty($data['CondPgto3']) ? intval($data['CondPgto3']) : null,
        'CondPgto4'   => !empty($data['CondPgto4']) ? intval($data['CondPgto4']) : null,
        'condpgto5'   => !empty($data['condpgto5']) ? intval($data['condpgto5']) : null,
        'condpgto6'   => !empty($data['condpgto6']) ? intval($data['condpgto6']) : null
    ];

    // Verificar se já existe condição com mesma descrição para esta empresa
    $checkDesc = $pdo->prepare("SELECT COUNT(*) FROM condicoes WHERE idcliente = ? AND Descricao = ?");
    $checkDesc->execute([$idcliente, $insertData['Descricao']]);
    if ($checkDesc->fetchColumn() > 0) {
        throw new Exception('Já existe uma condição de faturamento com esta descrição para sua empresa');
    }

    // Build INSERT query
    $columns = array_keys($insertData);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO condicoes (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

    // Execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($insertData));

    $condicaoId = $pdo->lastInsertId();

    // Prepare response data
    $responseData = [
        'id'         => $condicaoId,
        'idcliente'  => $insertData['idcliente'],
        'codcond'    => $insertData['codcond'],
        'Descricao'  => $insertData['Descricao'],
        'Parcelas'   => $insertData['Parcelas']
    ];

    // Add payment conditions to response
    for ($i = 1; $i <= 6; $i++) {
        $fieldName = $i <= 4 ? "CondPgto{$i}" : "condpgto{$i}";
        if ($insertData[$fieldName] !== null) {
            $responseData[$fieldName] = $insertData[$fieldName];
        }
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Condição de faturamento cadastrada com sucesso',
        'condicao' => $responseData,
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);

} catch (PDOException $e) {
    $errorMessage = 'Erro no banco de dados';

    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'Erro: Código duplicado detectado';
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error' => $e->getMessage()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>