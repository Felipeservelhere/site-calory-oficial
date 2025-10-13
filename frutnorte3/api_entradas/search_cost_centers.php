<?php
// ========================================
// API PARA BUSCAR CENTROS DE CUSTO
// ========================================

require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare("
        SELECT codigo as codcc, descricao 
        FROM centro_custo 
        WHERE ativo = 'S'
        ORDER BY descricao
    ");
    $stmt->execute();
    $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($costCenters);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>