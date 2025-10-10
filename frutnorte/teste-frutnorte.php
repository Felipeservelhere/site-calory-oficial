<?php
// teste-frutnorte.php - Testa conexão com banco frutnorte
echo "<h2>Teste de Conexão - Banco frutnorte</h2>";

try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexão com frutnorte estabelecida!</p>";
        
        // Mostra informações do banco
        $stmt = $conn->query("SELECT DATABASE() as db");
        $db_name = $stmt->fetch()['db'];
        echo "<p>Banco conectado: " . $db_name . "</p>";
        
        // Testa tabelas
        $tables = ['clientes', 'produtos', 'vendas'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Tabela '$table' encontrada</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Tabela '$table' não encontrada</p>";
            }
        }
        
        // Testa consulta com empresa_id
        $empresa_id = 1; // Teste com um ID qualquer
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clientes WHERE idcliente = ?");
        $stmt->execute([$empresa_id]);
        $total = $stmt->fetch()['total'];
        echo "<p>Clientes para empresa_id {$empresa_id}: " . $total . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Falha na conexão com frutnorte</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Problema provável:</strong> Credenciais incorretas no config/database.php</p>";
}
?>