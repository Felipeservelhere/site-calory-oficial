<?php
$client_id     = '625ba407964f8db27c96283b104c991972abe5fb';
$client_secret = '02d44294238e67e49277910a851a55a9395c838c6b8b530f163fad5d1200';
$redirect_uri  = 'https://calory.com.br/callback/bling.php';
$host = '177.107.115.204';
$db   = 'integracao_bling';
$user = 'root';
$pass = '@@rOOt@cAlOry@1967@@';
$port = 33060;


try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS bling_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        access_token TEXT NOT NULL,
        refresh_token TEXT,
        expires_in INT,
        token_type VARCHAR(50),
        scope VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Erro na conexão com o banco: " . $e->getMessage());
}
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    die('Parâmetros ausentes. Acesse via fluxo OAuth do Bling.');
}

$code  = $_GET['code'];
$state = $_GET['state'];
$url = 'https://www.bling.com.br/Api/v3/oauth/token';
$basic_auth = base64_encode("$client_id:$client_secret");

$data = [
    'grant_type'    => 'authorization_code',
    'code'          => $code
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: 1.0',
    'Authorization: Basic' . $basic_auth
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

    // Insere no banco
    $stmt = $pdo->prepare("INSERT INTO bling_tokens 
        (access_token, refresh_token, expires_in, token_type, scope) 
        VALUES (:access_token, :refresh_token, :expires_in, :token_type, :scope)");
    $stmt->execute([
        ':access_token'  => $access_token,
        ':refresh_token' => $refresh_token,
        ':expires_in'    => $expires_in,
        ':token_type'    => $token_type,
        ':scope'         => $scope
    ]);

    echo "<h3>Token de acesso recebido e salvo no banco!</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} else {
    echo "<h3>Erro ao obter token:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>
