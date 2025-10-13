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
    
    // Obter ID do grupo
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('ID do grupo não fornecido');
    }
    
    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se o grupo existe
    $sql_check = "SELECT id, nome, codgrupo FROM grupos WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $grupo = $stmt_check->fetch();
    
    if (!$grupo) {
        throw new Exception('Grupo não encontrado');
    }
    
    // Verificar se há produtos associados ao grupo
    $sql_produtos = "SELECT COUNT(*) as total FROM produtos WHERE codgrupo = ?";
    $stmt_produtos = $pdo->prepare($sql_produtos);
    $stmt_produtos->execute([$grupo['codgrupo']]);
    $total_produtos = $stmt_produtos->fetch()['total'];
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Se há produtos associados, remover a associação (definir codgrupo como NULL)
        if ($total_produtos > 0) {
            $sql_update_produtos = "UPDATE produtos SET codgrupo = NULL WHERE codgrupo = ?";
            $stmt_update_produtos = $pdo->prepare($sql_update_produtos);
            $stmt_update_produtos->execute([$grupo['codgrupo']]);
        }
        
        // Excluir o grupo
        $sql_delete = "DELETE FROM grupos WHERE id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $result = $stmt_delete->execute([$id]);
        
        if (!$result) {
            throw new Exception('Erro ao excluir grupo');
        }
        
        // Confirmar transação
        $pdo->commit();
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'message' => $total_produtos > 0 ? 
                "Grupo '{$grupo['nome']}' excluído com sucesso. {$total_produtos} produto(s) foram desvinculados do grupo." :
                "Grupo '{$grupo['nome']}' excluído com sucesso.",
            'produtos_afetados' => $total_produtos
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        throw $e;
    }
    
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