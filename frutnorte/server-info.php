<?php
// server-info.php - Coloque na raiz
echo "<h2>Informações do Servidor</h2>";

// PHP Version
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Extensões carregadas
echo "<p><strong>PDO MySQL:</strong> " . (extension_loaded('pdo_mysql') ? '✅ Carregada' : '❌ Não carregada') . "</p>";
echo "<p><strong>MySQLi:</strong> " . (extension_loaded('mysqli') ? '✅ Carregada' : '❌ Não carregada') . "</p>";

// Configurações importantes
echo "<p><strong>display_errors:</strong> " . ini_get('display_errors') . "</p>";
echo "<p><strong>error_reporting:</strong> " . ini_get('error_reporting') . "</p>";
echo "<p><strong>include_path:</strong> " . ini_get('include_path') . "</p>";

// Caminho atual
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Filename:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

// Testa se consegue escrever
echo "<p><strong>Permissão de escrita:</strong> " . (is_writable('.') ? '✅ Sim' : '❌ Não') . "</p>";

// Sessions
session_start();
echo "<p><strong>Sessão:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Ativa' : '❌ Inativa') . "</p>";
?>