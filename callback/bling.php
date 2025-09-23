<?php
$host = '177.107.115.204';
$db   = 'integracao_bling';
$user = 'root';
$pass = '@@rOOt@cAlOry@1967@@';
$port = 33060;

// Caminho do arquivo de log
$logFile = __DIR__ . '/logs/bling.log';

// Função para registrar logs
function writeLog($message) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $message\n", FILE_APPEND);
}

// ======================= CONEXÃO =======================
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    writeLog("Conexão com o banco realizada com sucesso.");
} catch (PDOException $e) {
    writeLog("Erro na conexão com o banco: " . $e->getMessage());
    die("Erro na conexão com o banco. Verifique o log.");
}

// ======================= PARÂMETROS =======================
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    writeLog('Parâmetros ausentes: code ou state não fornecido.');
    die('Parâmetros ausentes. Acesse via fluxo OAuth do Bling.');
}

$code  = $_GET['code'];
$state = $_GET['state'];
writeLog("Parâmetros recebidos: code=$code, state=$state");

// ======================= BUSCAR CREDENCIAIS =======================
$stmt = $pdo->prepare("SELECT client_id, client_secret, cnpj FROM credenciais WHERE state = :state");
$stmt->execute([':state' => $state]);
$cred = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cred) {
    writeLog("State inválido ou não encontrado no banco: $state");
    die("State inválido ou não encontrado no banco.");
}

$client_id     = $cred['client_id'];
$client_secret = $cred['client_secret'];
$cnpj          = $cred['cnpj'];
$redirect_uri  = 'https://calory.com.br/callback/bling.php';

writeLog("Credenciais encontradas para CNPJ: $cnpj");

// ======================= REQUISIÇÃO TOKEN =======================
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
    $err = curl_error($ch);
    writeLog("Erro cURL: $err");
    die('Erro cURL. Verifique o log.');
}
curl_close($ch);

writeLog("Resposta da API Bling: $response");

$result = json_decode($response, true);

if (isset($result['access_token'])) {
    $access_token  = $result['access_token'];
    $refresh_token = $result['refresh_token'] ?? null;
    $expires_in    = $result['expires_in'] ?? null;
    $token_type    = $result['token_type'] ?? null;
    $scope         = $result['scope'] ?? null;

    // ======================= ATUALIZAR TOKEN =======================
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

    writeLog("Token atualizado com sucesso para o CNPJ: $cnpj");
    echo "<h3>Token atualizado com sucesso para o CNPJ: $cnpj</h3>";
    echo "<script>window.close();</script>";
    echo "<p>Se esta janela não fechar sozinha, <button onclick='window.close()'>clique aqui para fechar</button>.</p>";

} else {
    writeLog("Erro ao obter token: " . json_encode($result));
    echo "<h3>Erro ao obter token:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>
