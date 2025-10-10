<?php
// teste-phpmailer.php - Verifica se o PHPMailer está funcionando
echo "<h2>Teste do PHPMailer</h2>";

// Verifica se os arquivos existem
$files = [
    'PHPMailer/src/Exception.php',
    'PHPMailer/src/PHPMailer.php', 
    'PHPMailer/src/SMTP.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file - Encontrado</p>";
    } else {
        echo "<p style='color: red;'>❌ $file - Não encontrado</p>";
    }
}

// Tenta carregar o PHPMailer
try {
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "<p style='color: green;'>✅ PHPMailer carregado com sucesso!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao carregar PHPMailer: " . $e->getMessage() . "</p>";
}
?>