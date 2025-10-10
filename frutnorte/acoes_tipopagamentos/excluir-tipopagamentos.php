<?php
// API para excluir tipo de pagamento
header('Content-Type: application/json');

include '../config/database.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verificar se foi passado um ID
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar se o tipo de pagamento existe
    $sql_check = "SELECT id, Descricao FROM tipopagamentos WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $tipo_pagamento = $stmt_check->fetch();
    
    if (!$tipo_pagamento) {
        throw new Exception('Tipo de pagamento não encontrado');
    }
    
    // Verificar se o tipo de pagamento está sendo usado em outras tabelas
    // Aqui você pode adicionar verificações para outras tabelas que referenciam tipopagamentos
    // Por exemplo, se houver uma tabela de vendas que usa este tipo de pagamento
    
    /*
    // Exemplo de verificação (descomente e ajuste conforme sua estrutura):
    $sql_vendas = "SELECT COUNT(*) as total FROM vendas WHERE tipo_pagamento_id = ?";
    $stmt_vendas = $pdo->prepare($sql_vendas);
    $stmt_vendas->execute([$id]);
    $vendas_count = $stmt_vendas->fetch()['total'];
    
    if ($vendas_count > 0) {
        throw new Exception('Não é possível excluir este tipo de pagamento pois ele está sendo usado em ' . $vendas_count . ' venda(s)');
    }
    */
    
    // Excluir o tipo de pagamento
    $sql_delete = "DELETE FROM tipopagamentos WHERE id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([$id]);
    
    if ($stmt_delete->rowCount() === 0) {
        throw new Exception('Nenhum registro foi excluído');
    }
    
    // Log da exclusão (opcional)
    
    echo json_encode([
        'success' => true,
        'message' => 'Tipo de pagamento excluído com sucesso'
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
        'message' => 'Erro interno do servidor'
    ]);
}
?>