<?php
session_start();

// Verificação básica de login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

echo "<h2>Debug - Dashboard</h2>";

// Testa conexão login
try {
    require_once 'config/databaselogin.php';
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();
    
    if ($connlogin) {
        echo "<p style='color: green;'>✅ Conexão login OK</p>";
        
        // Testa usuário
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_data = $stmt->fetch();
        
        if ($admin_data) {
            echo "<p style='color: green;'>✅ Usuário encontrado: empresa_id = " . $admin_data['empresa_id'] . "</p>";
            $empresa_id = $admin_data['empresa_id'];
        } else {
            echo "<p style='color: red;'>❌ Usuário não encontrado</p>";
            exit;
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro login: " . $e->getMessage() . "</p>";
    exit;
}

// Testa conexão sistema
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexão sistema OK</p>";
        
        // Testa consulta simples
        $stmt = $conn->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✅ Query teste OK: " . $result['test'] . "</p>";
        
        // Testa tabela clientes
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM clientes WHERE idcliente = ?");
        $stmt->execute([$empresa_id]);
        $total = $stmt->fetch()['total'];
        echo "<p style='color: green;'>✅ Clientes encontrados: " . $total . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Conexão sistema falhou</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro sistema: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php' class='btn btn-primary'>Voltar ao Dashboard</a></p>";
?>