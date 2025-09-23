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
        scope = :scope,
        updated_at = NOW()
        WHERE cnpj = :cnpj");

    $stmt->execute([
        ':access_token'  => $access_token,
        ':refresh_token' => $refresh_token,
        ':expires_in'    => $expires_in,
        ':token_type'    => $token_type,
        ':scope'         => $scope,
        ':cnpj'          => $cnpj
    ]);

    echo "<h3>Token atualizado com sucesso para o CNPJ: $cnpj</h3>";
    echo "<script>window.close();</script>";
    echo "<p>Se esta janela não fechar sozinha, <button onclick='window.close()'>clique aqui para fechar</button>.</p>";

} else {
    echo "<h3>Erro ao obter token:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>
