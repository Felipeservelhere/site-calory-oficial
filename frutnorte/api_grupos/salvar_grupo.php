<?php
session_start();
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

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Incluir configuração do banco
    require_once '../config/database.php';
    
    // Obter dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Validar campos obrigatórios
    if (empty($data['nome'])) {
        throw new Exception('Nome do grupo é obrigatório');
    }
    
    // Sanitizar dados
    $nome = trim($data['nome']);
    $perc_mb = !empty($data['perc_mb']) ? floatval($data['perc_mb']) : null;
    $perc_avista = !empty($data['perc_avista']) ? floatval($data['perc_avista']) : null;
    
    // Validar nome
    if (strlen($nome) < 2) {
        throw new Exception('Nome do grupo deve ter pelo menos 2 caracteres');
    }
    
    if (strlen($nome) > 100) {
        throw new Exception('Nome do grupo não pode ter mais de 100 caracteres');
    }
    
    // Validar percentuais
    if ($perc_mb !== null && ($perc_mb < 0 || $perc_mb > 999.99)) {
        throw new Exception('Percentual de margem bruta deve estar entre 0 e 999,99');
    }
    
    if ($perc_avista !== null && ($perc_avista < 0 || $perc_avista > 999.99)) {
        throw new Exception('Percentual à vista deve estar entre 0 e 999,99');
    }
    
    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se já existe um grupo com o mesmo nome (na mesma empresa)
    $sql_check = "SELECT id FROM grupos WHERE LOWER(nome) = LOWER(?) AND idcliente = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$nome, $idcliente_empresa]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('Já existe um grupo com este nome');
    }
    
    // Obter o próximo código de grupo (para a empresa específica)
    $sql_max_code = "SELECT COALESCE(MAX(codgrupo), 0) + 1 as next_code FROM grupos WHERE idcliente = ?";
    $stmt_max_code = $pdo->prepare($sql_max_code);
    $stmt_max_code->execute([$idcliente_empresa]);
    $next_code = $stmt_max_code->fetch()['next_code'];
    
    // Inserir novo grupo com empresa_id dinâmico
    $sql = "INSERT INTO grupos (idcliente, codgrupo, nome, perc_mb, perc_avista) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $idcliente_empresa,  // ID dinâmico da empresa
        $next_code,
        $nome,
        $perc_mb,
        $perc_avista
    ]);
    
    if (!$result) {
        throw new Exception('Erro ao salvar grupo no banco de dados');
    }
    
    $grupo_id = $pdo->lastInsertId();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Grupo cadastrado com sucesso',
        'data' => [
            'id' => $grupo_id,
            'codgrupo' => $next_code,
            'nome' => $nome,
            'perc_mb' => $perc_mb,
            'perc_avista' => $perc_avista,
            'empresa_id' => $idcliente_empresa
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>