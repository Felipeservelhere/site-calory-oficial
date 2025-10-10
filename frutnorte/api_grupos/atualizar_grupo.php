<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    if (empty($data['id'])) {
        throw new Exception('ID do grupo é obrigatório');
    }
    
    if (empty($data['nome'])) {
        throw new Exception('Nome do grupo é obrigatório');
    }
    
    // Sanitizar dados
    $id = (int)$data['id'];
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
    
    // Verificar se o grupo existe
    $sql_check = "SELECT id, codgrupo FROM grupos WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $grupo_existente = $stmt_check->fetch();
    
    if (!$grupo_existente) {
        throw new Exception('Grupo não encontrado');
    }
    
    // Verificar se já existe outro grupo com o mesmo nome
    $sql_check_nome = "SELECT id FROM grupos WHERE LOWER(nome) = LOWER(?) AND id != ?";
    $stmt_check_nome = $pdo->prepare($sql_check_nome);
    $stmt_check_nome->execute([$nome, $id]);
    
    if ($stmt_check_nome->fetch()) {
        throw new Exception('Já existe outro grupo com este nome');
    }
    
    // Atualizar grupo
    $sql = "UPDATE grupos SET nome = ?, perc_mb = ?, perc_avista = ? WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $nome,
        $perc_mb,
        $perc_avista,
        $id
    ]);
    
    if (!$result) {
        throw new Exception('Erro ao atualizar grupo no banco de dados');
    }
    
    // Verificar se alguma linha foi afetada
    if ($stmt->rowCount() === 0) {
        // Pode ser que não houve mudanças, mas isso não é necessariamente um erro
        // Vamos verificar se o grupo ainda existe
        $sql_verify = "SELECT * FROM grupos WHERE id = ?";
        $stmt_verify = $pdo->prepare($sql_verify);
        $stmt_verify->execute([$id]);
        $grupo_verificado = $stmt_verify->fetch();
        
        if (!$grupo_verificado) {
            throw new Exception('Grupo não encontrado após tentativa de atualização');
        }
    }
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Grupo atualizado com sucesso',
        'data' => [
            'id' => $id,
            'codgrupo' => $grupo_existente['codgrupo'],
            'nome' => $nome,
            'perc_mb' => $perc_mb,
            'perc_avista' => $perc_avista
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