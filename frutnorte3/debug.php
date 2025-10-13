<?php
// debug.php - Coloque na raiz do sistema temporariamente
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug do Sistema</h2>";

// Testa a conexão com o banco
try {
    require_once 'config/databaselogin.php';
    $db = new DatabaseLogin();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexão com banco de dados OK</p>";
    } else {
        echo "<p style='color: red;'>❌ Erro na conexão com banco de dados</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro PDO: " . $e->getMessage() . "</p>";
}

// Testa sessões
session_start();
echo "<p>Sessão ID: " . session_id() . "</p>";
echo "<p>Session status: " . session_status() . "</p>";

// Testa includes
echo "<p>Includepath: " . get_include_path() . "</p>";

// Testa permissões
echo "<p>Permissões da pasta: " . substr(sprintf('%o', fileperms('.')), -4) . "</p>";

phpinfo();
?>