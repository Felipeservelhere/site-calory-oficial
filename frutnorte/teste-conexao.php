<?php
// teste-conexao.php
echo "<h2>Teste de Conexão com Banco no Servidor</h2>";

try {
    include 'config/databaselogin.php';
    
    $db = new DatabaseLogin();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexão com banco estabelecida!</p>";
        
        // Tenta uma query simples
        $result = $conn->query("SELECT DATABASE() as db");
        $db_name = $result->fetch()['db'];
        echo "<p>Banco conectado: " . $db_name . "</p>";
        
        // Testa tabela de usuários
        $result = $conn->query("SHOW TABLES LIKE 'usuarios'");
        if ($result->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Tabela 'usuarios' encontrada</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Tabela 'usuarios' não encontrada</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Falha na conexão com o banco</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Informações do PHP</h3>";
echo "PHP Version: " . phpversion();
?>