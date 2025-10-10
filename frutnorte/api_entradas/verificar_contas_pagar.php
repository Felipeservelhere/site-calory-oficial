<?php
// api_entradas/verificar_contas_pagar.php
include '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['codentrada']) || !is_numeric($_GET['codentrada'])) {
    echo json_encode(['error' => 'Código de entrada inválido']);
    exit;
}

$codentrada = (int)$_GET['codentrada'];
$database = new Database();
$pdo = $database->getConnection();

try {
    // Buscar informações básicas da entrada
    $sql_entrada = "SELECT e.codentrada, e.numeronota, e.vrTotal, c.Nome as fornecedor_nome 
                    FROM entradas e 
                    LEFT JOIN clientes c ON e.Codcliente = c.codcliente 
                    WHERE e.codentrada = ?";
    $stmt_entrada = $pdo->prepare($sql_entrada);
    $stmt_entrada->execute([$codentrada]);
    $entrada = $stmt_entrada->fetch(PDO::FETCH_ASSOC);

    if (!$entrada) {
        echo json_encode(['error' => 'Entrada não encontrada']);
        exit;
    }

    // Verificar se existem contas a pagar
    $sql_contas = "SELECT COUNT(*) as total FROM contaspagar WHERE codentrada = ?";
    $stmt_contas = $pdo->prepare($sql_contas);
    $stmt_contas->execute([$codentrada]);
    $result_contas = $stmt_contas->fetch(PDO::FETCH_ASSOC);
    
    $temContas = $result_contas['total'] > 0;
    $quantidade = $result_contas['total'];

    echo json_encode([
        'temContas' => $temContas,
        'quantidade' => $quantidade,
        'fornecedor_nome' => $entrada['fornecedor_nome'],
        'vrTotal' => $entrada['vrTotal'],
        'numeronota' => $entrada['numeronota']
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>