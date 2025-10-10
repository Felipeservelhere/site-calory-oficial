<?php
include '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['codentrada'])) {
    echo json_encode(['error' => 'Código da entrada não fornecido']);
    exit;
}

$codentrada = (int)$_GET['codentrada'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $sql = "SELECT e.codentrada, e.vrTotal, c.Nome as fornecedor_nome
            FROM entradas e 
            LEFT JOIN clientes c ON e.Codcliente = c.codcliente 
            WHERE e.codentrada = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$codentrada]);
    $entrada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entrada) {
        echo json_encode(['error' => 'Entrada não encontrada']);
        exit;
    }
    
    echo json_encode($entrada);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>