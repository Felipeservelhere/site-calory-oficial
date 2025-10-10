<?php
// teste-menu.php - Testa o menu isoladamente
session_start();
$_SESSION['loggedin'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['empresa_id'] = 4;

echo "<h2>Teste do Menu</h2>";

try {
    // Testa o config.php
    if (!file_exists('config.php')) {
        throw new Exception('config.php não encontrado');
    }
    
    require_once 'config.php';
    echo "<p style='color: green;'>✅ config.php carregado</p>";
    
    // Testa o menu.php
    if (!file_exists('includes/menu.php')) {
        throw new Exception('includes/menu.php não encontrado');
    }
    
    // Tenta incluir o menu
    include 'includes/menu.php';
    echo "<p style='color: green;'>✅ menu.php incluído com sucesso</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Mostra informações da sessão
echo "<h3>Informações da Sessão:</h3>";
echo "loggedin: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "<br>";
echo "admin_id: " . ($_SESSION['admin_id'] ?? 'não definido') . "<br>";
echo "empresa_id: " . ($_SESSION['empresa_id'] ?? 'não definido') . "<br>";
?>