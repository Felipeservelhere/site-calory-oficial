<?php
header('Content-Type: application/json');

try {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Verificar se o ID foi enviado
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception('ID do tipo de despesa não informado');
    }

    $id = (int)$_POST['id'];

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Conectar ao banco
    include '../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar se o tipo de despesa existe
    $sql_check = "SELECT id, Descricao FROM tipodespesas WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $tipo_despesa = $stmt_check->fetch();

    if (!$tipo_despesa) {
        throw new Exception('Tipo de despesa não encontrado');
    }

    // Verificar se há registros relacionados (opcional - dependendo da estrutura do seu sistema)
    // Exemplo: verificar se há despesas cadastradas com este tipo
    /*
    $sql_relacionados = "SELECT COUNT(*) as total FROM despesas WHERE idtipodespesa = ?";
    $stmt_relacionados = $pdo->prepare($sql_relacionados);
    $stmt_relacionados->execute([$id]);
    $total_relacionados = $stmt_relacionados->fetch()['total'];

    if ($total_relacionados > 0) {
        throw new Exception('Não é possível excluir este tipo de despesa pois existem ' . $total_relacionados . ' despesa(s) cadastrada(s) com este tipo');
    }
    */

    // Excluir o tipo de despesa
    $sql_delete = "DELETE FROM tipodespesas WHERE id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $result = $stmt_delete->execute([$id]);

    if (!$result) {
        throw new Exception('Erro ao excluir tipo de despesa do banco de dados');
    }

    // Verificar se realmente foi excluído
    if ($stmt_delete->rowCount() === 0) {
        throw new Exception('Nenhum registro foi excluído. Verifique se o tipo de despesa ainda existe');
    }

    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Tipo de despesa excluído com sucesso',
        'tipo_despesa' => [
            'id' => $id,
            'descricao' => $tipo_despesa['Descricao']
        ]
    ]);

} catch (Exception $e) {
    // Log do erro (opcional)
    error_log("Erro ao excluir tipo de despesa: " . $e->getMessage());
    
    // Retornar erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    // Log do erro de banco
    error_log("Erro de banco ao excluir tipo de despesa: " . $e->getMessage());
    
    // Retornar erro genérico
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Tente novamente mais tarde.'
    ]);
}
?>