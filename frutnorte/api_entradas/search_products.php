<?php
// ========================================
// API PARA BUSCAR PRODUTOS
// ========================================

require_once 'config.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("
        SELECT codproduto, nome, descricao_reduzida, Un, Vrunit, NCM, ativo
        FROM produtos 
        WHERE ativo = 'S' 
        AND (codproduto LIKE ? OR nome LIKE ? OR descricao_reduzida LIKE ?) 
        ORDER BY nome 
        LIMIT 20
    ");
    $searchTerm = "%{$term}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>