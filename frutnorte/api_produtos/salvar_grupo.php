<?php
session_start();
include '../config/database.php';

header('Content-Type: application/json');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $idcliente = $idcliente_empresa;

    if (!isset($data['nome']) || empty(trim($data['nome']))) {
        echo json_encode(['success' => false, 'message' => 'Nome do grupo é obrigatório']);
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar se já existe um grupo com o mesmo nome para este cliente
    $stmt = $pdo->prepare("SELECT codgrupo FROM grupos WHERE nome = ? AND idcliente = ?");
    $stmt->execute([trim($data['nome']), $idcliente]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe um grupo com este nome em sua empresa']);
        exit;
    }

    // Gerar o próximo codgrupo para este idcliente
    $stmtMax = $pdo->prepare("SELECT MAX(codgrupo) AS max_cod FROM grupos WHERE idcliente = ?");
    $stmtMax->execute([$idcliente]);
    $result = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $proximoCodGrupo = ($result['max_cod'] ?? 0) + 1;

    // Inserir novo grupo
    $stmtInsert = $pdo->prepare("
        INSERT INTO grupos (idcliente, codgrupo, nome, perc_mb, perc_avista) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $idcliente,
        $proximoCodGrupo,
        trim($data['nome']),
        isset($data['perc_mb']) && $data['perc_mb'] !== '' ? $data['perc_mb'] : null,
        isset($data['perc_avista']) && $data['perc_avista'] !== '' ? $data['perc_avista'] : null
    ]);

    // Log de sucesso
    error_log("Grupo criado com sucesso: {$data['nome']} (Cod: {$proximoCodGrupo}) para empresa: {$idcliente}");

    echo json_encode([
        'success' => true,
        'message' => 'Grupo criado com sucesso',
        'grupo' => [
            'codgrupo' => $proximoCodGrupo,
            'nome' => trim($data['nome']),
            'perc_mb' => isset($data['perc_mb']) && $data['perc_mb'] !== '' ? $data['perc_mb'] : null,
            'perc_avista' => isset($data['perc_avista']) && $data['perc_avista'] !== '' ? $data['perc_avista'] : null,
            'empresa_id' => $idcliente
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro ao salvar grupo para empresa {$idcliente_empresa}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>