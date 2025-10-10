<?php
// ========================================
// API PARA BUSCAR FORNECEDORES
// ========================================

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['term'])) {
    echo json_encode(['error' => 'Parâmetro term é obrigatório']);
    exit;
}

$term = $_GET['term'] ?? '';

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("
        SELECT codcliente, Nome, Fantasia, cnpj_cpf, Cidade, Uf 
        FROM clientes 
        WHERE tipocliente = '3' 
        AND (codcliente LIKE ? OR Nome LIKE ? OR Fantasia LIKE ?) 
        AND ativo = 'S'
        ORDER BY Nome 
        LIMIT 10
    ");
    $searchTerm = "%{$term}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($suppliers);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>