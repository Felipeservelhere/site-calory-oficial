<?php
$host = '177.107.115.204';
$db   = 'integracao_bling';
$user = 'root';
$pass = '@@rOOt@cAlOry@1967@@';
$port = 33060;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco: " . $e->getMessage());
}

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    die('Parâmetros ausentes. Acesse via fluxo OAuth do Bling.');
}

$code  = $_GET['code'];
$state = $_GET['state'];

// 1️⃣ Buscar credenciais e CNPJ pelo state
$stmt = $pdo->prepare("SELECT client_id, client_secret, cnpj FROM credenciais WHERE state = :state");
$stmt->execute([':state' => $state]);
$cred = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cred) {
    die("State inválido ou não encontrado no banco.");
}

$client_id     = $cred['client_id'];
$client_secret = $cred['client_secret'];
$cnpj          = $cred['cnpj'];
$redirect_uri  = 'https://calory.com.br/callback/bling.php';

// 2️⃣ Solicitar token ao Bling
$url = 'https://www.bling.com.br/Api/v3/oauth/token';
$basic_auth = base64_encode("$client_id:$client_secret");

$data = [
    'grant_type' => 'authorization_code',
    'code'       => $code
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: 1.0',
    'Authorization: Basic ' . $basic_auth
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    die('Erro cURL: ' . curl_error($ch));
}
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['access_token'])) {
    $access_token  = $result['access_token'];
    $refresh_token = $result['refresh_token'] ?? null;
    $expires_in    = $result['expires_in'] ?? null;
    $token_type    = $result['token_type'] ?? null;
    $scope         = $result['scope'] ?? null;

    // 3️⃣ Atualizar token existente na tabela bling_tokens usando o CNPJ
    $stmt = $pdo->prepare("UPDATE bling_tokens SET 
        access_token = :access_token,
        refresh_token = :refresh_token,
        expires_in = :expires_in,
        token_type = :token_type,
        scope = :scope
        WHERE cnpj = :cnpj");

    $stmt->execute([
        ':access_token'  => $access_token,
        ':refresh_token' => $refresh_token,
        ':expires_in'    => $expires_in,
        ':token_type'    => $token_type,
        ':scope'         => $scope,
        ':cnpj'          => $cnpj
    ]);

    // Mensagem de sucesso bonita
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integração Concluída - Calory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .success-icon {
            font-size: 80px;
            color: #10b981;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 600;
        }
        
        p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 16px;
        }
        
        .cnpj-info {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: monospace;
            font-weight: bold;
            color: #374151;
        }
        
        .close-button {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .close-button:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .auto-close {
            margin-top: 15px;
            font-size: 14px;
            color: #9ca3af;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto 20px;
            box-shadow: inset 0px 0px 0px #10b981;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #10b981;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }
        
        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 40px #10b981;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>
        
        <h1>✅ Integração Concluída!</h1>
        
        <p>Sua integração com o Bling foi configurada com sucesso. Agora você pode sincronizar seus dados automaticamente.</p>
        
        <div class="cnpj-info">
            CNPJ: {$cnpj}
        </div>
        
        <button class="close-button" onclick="fecharJanela()">Fechar Janela</button>
        
        <div class="auto-close">
            Esta janela será fechada automaticamente em <span id="countdown">5</span> segundos...
        </div>
    </div>

    <script>
        function fecharJanela() {
            window.open('', '_self');
            window.close();
        }
        
        // Contador regressivo para fechar automaticamente
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                fecharJanela();
            }
        }, 1000);
        
        // Tentativa de fechar automaticamente após 5 segundos
        setTimeout(fecharJanela, 5000);
    </script>
</body>
</html>
HTML;

} else {
    echo "<h3>Erro ao obter token:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>