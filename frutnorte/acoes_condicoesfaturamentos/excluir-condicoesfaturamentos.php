<?php
// Arquivo para exclusão de condições de faturamento via AJAX
header('Content-Type: application/json');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se foi enviado o ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
    exit;
}

try {
    include '../config/database.php';
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    $id = (int)$_POST['id'];
    
    // Verificar se a condição existe
    $sql_check = "SELECT id, Descricao FROM condicoes WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $condicao = $stmt_check->fetch();
    
    if (!$condicao) {
        echo json_encode(['success' => false, 'message' => 'Condição de faturamento não encontrada']);
        exit;
    }
    
    // Verificar se a condição está sendo usada em outras tabelas
    // Aqui você pode adicionar verificações para outras tabelas que referenciam esta condição
    // Exemplo:
    /*
    $sql_usage = "SELECT COUNT(*) as total FROM vendas WHERE condicao_faturamento_id = ?";
    $stmt_usage = $pdo->prepare($sql_usage);
    $stmt_usage->execute([$id]);
    $usage = $stmt_usage->fetch();
    
    if ($usage['total'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Esta condição de faturamento não pode ser excluída pois está sendo utilizada em ' . $usage['total'] . ' venda(s)'
        ]);
        exit;
    }
    */
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Excluir a condição de faturamento
        $sql_delete = "DELETE FROM condicoes WHERE id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$id]);
        
        // Verificar se foi excluído
        if ($stmt_delete->rowCount() === 0) {
            throw new Exception('Nenhum registro foi excluído');
        }
        
        // Confirmar transação
        $pdo->commit();
        
        // Log da exclusão (opcional)
        
        echo json_encode([
            'success' => true, 
            'message' => 'Condição de faturamento excluída com sucesso',
            'deleted_id' => $id,
            'deleted_name' => $condicao['Descricao']
        ]);
        
    } catch (Exception $e) {
        // Desfazer transação
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    // Erro de banco de dados
    
    // Verificar se é erro de chave estrangeira
    if ($e->getCode() == '23000') {
        echo json_encode([
            'success' => false, 
            'message' => 'Esta condição de faturamento não pode ser excluída pois está sendo utilizada em outros registros'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro interno do servidor. Tente novamente.'
        ]);
    }
    
} catch (Exception $e) {
    // Outros erros
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>