<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Faça login novamente.']);
    exit;
}

// ==================== CONEXÃO LOGIN (para validar empresa_id) ====================
require_once '../config/databaselogin.php';

try {
    $dbLogin = new DatabaseLogin();
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na conexão auth: ' . $e->getMessage()]);
    error_log("Erro conexão auth: " . $e->getMessage());
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Validar usuário e obter empresa_id
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário: ' . $e->getMessage()]);
    error_log("Erro validação usuário: " . $e->getMessage());
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ==================== CONEXÃO SISTEMA (para operações) ====================
require_once '../config/database.php';

// Função de log em arquivo
function logMessage($message) {
    $logFile = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Mapeamento de texto para código
$mapaTipoCliente = [
    'cliente' => '1',
    'fornecedor' => '3',
    'outro' => '4'
];

// Normaliza para minúsculo e aplica o mapa
$tipoClienteEntrada = strtolower(trim($input['tipocliente'] ?? 'cliente'));
$tipoCliente = $mapaTipoCliente[$tipoClienteEntrada] ?? '1'; // padrão = cliente

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // ==================== VALIDAÇÃO DE PERMISSÃO ====================
    // Verificar se o cliente pertence à empresa logada
    $sql_check = "SELECT id FROM clientes WHERE id = ? AND idcliente = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$input['id'], $idcliente_empresa]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado ou sem permissão para editar.']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Validate required fields
    $required = ['id', 'Nome', 'Email', 'cnpj_cpf', 'tipo_pessoa'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo obrigatório não preenchido: $field");
        }
    }
    
    // Validar CPF/CNPJ baseado no tipo de pessoa
    if ($input['tipo_pessoa'] === 'F') {
        if (!validarCPF($input['cnpj_cpf'])) {
            throw new Exception('CPF inválido');
        }
    } else {
        // Validação básica para CNPJ (pode implementar validação completa se necessário)
        $cnpj = preg_replace('/\D/', '', $input['cnpj_cpf']);
        if (strlen($cnpj) !== 14) {
            throw new Exception('CNPJ inválido');
        }
    }
    
    // Format date
    $nascimento = null;
    if (!empty($input['nascimento'])) {
        $date = DateTime::createFromFormat('Y-m-d', $input['nascimento'])
              ?: DateTime::createFromFormat('Y-m-d H:i:s', $input['nascimento'])
              ?: DateTime::createFromFormat('d/m/Y', $input['nascimento']);
        if ($date) {
            $nascimento = $date->format('Y-m-d');
        }
    }
    
    // Update cliente
    $sql = "UPDATE clientes SET 
        tipo_pessoa = :tipo_pessoa,
        Nome = :Nome,
        Fantasia = :Fantasia,
        cnpj_cpf = :cnpj_cpf,
        Email = :Email,
        celular = :celular,
        CEP = :CEP,
        Endereco = :Endereco,
        numero = :numero,
        complemento = :complemento,
        Bairro = :Bairro,
        Cidade = :Cidade,
        Uf = :Uf,
        pais = :pais,
        IE = :IE,
        IM = :IM,
        insc_rural = :insc_rural,
        insc_suframa = :insc_suframa,
        nascimento = :nascimento,
        Fone = :Fone,
        tipoconta = :tipoconta,
        Contato = :Contato,
        tipocliente = :tipocliente,
        CondPgto = :CondPgto,
        Transportadora = :Transportadora,
        PercDesconto = :PercDesconto,
        limite = :limite,
        saldo_devedor = :saldo_devedor,
        codvendedor = :codvendedor,
        NotaKG = :NotaKG,
        pdesconto_boleto = :pdesconto_boleto,
        protesto_automatico_boletos = :protesto_automatico_boletos,
        dias_protesto = :dias_protesto,
        ativo = :ativo,
        obs = :obs
        WHERE id = :id AND idcliente = :idcliente";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':id' => $input['id'],
        ':idcliente' => $idcliente_empresa, // 👈 Garante que só atualiza clientes da empresa
        ':tipo_pessoa' => $input['tipo_pessoa'],
        ':Nome' => $input['Nome'],
        ':Fantasia' => $input['Fantasia'] ?? '',
        ':cnpj_cpf' => $input['cnpj_cpf'],
        ':Email' => $input['Email'],
        ':celular' => $input['celular'] ?? '',
        ':CEP' => $input['CEP'] ?? '',
        ':Endereco' => $input['Endereco'] ?? '',
        ':numero' => $input['numero'] ?? '',
        ':complemento' => $input['complemento'] ?? '',
        ':Bairro' => $input['Bairro'] ?? '',
        ':Cidade' => $input['Cidade'] ?? '',
        ':Uf' => $input['Uf'] ?? '',
        ':pais' => $input['pais'] ?? 'BRASIL',
        ':IE' => $input['IE'] ?? '',
        ':IM' => $input['IM'] ?? '',
        ':insc_rural' => $input['insc_rural'] ?? '',
        ':insc_suframa' => $input['insc_suframa'] ?? '',
        ':nascimento' => $nascimento,
        ':Fone' => $input['Fone'] ?? '',
        ':tipoconta' => $input['tipoconta'] ?? 'Conta Corrente',
        ':Contato' => $input['Contato'] ?? '',
        ':tipocliente' => $tipoCliente,
        ':CondPgto' => $input['CondPgto'] ?? '',
        ':Transportadora' => $input['Transportadora'] ?? '',
        ':PercDesconto' => !empty($input['PercDesconto']) ? floatval($input['PercDesconto']) : 0,
        ':limite' => !empty($input['limite']) ? floatval($input['limite']) : 0,
        ':saldo_devedor' => !empty($input['saldo_devedor']) ? floatval($input['saldo_devedor']) : 0,
        ':codvendedor' => !empty($input['codvendedor']) ? intval($input['codvendedor']) : null,
        ':NotaKG' => $input['NotaKG'] ?? 'N',
        ':pdesconto_boleto' => !empty($input['pdesconto_boleto']) ? floatval($input['pdesconto_boleto']) : 0,
        ':protesto_automatico_boletos' => $input['protesto_automatico_boletos'] ?? 'N',
        ':dias_protesto' => !empty($input['dias_protesto']) ? intval($input['dias_protesto']) : 5,
        ':ativo' => $input['ativo'] ?? 'S',
        ':obs' => $input['obs'] ?? ''
    ];

    logMessage("Tentativa de atualização - Admin: $admin_id, Empresa: $idcliente_empresa, Cliente ID: " . $input['id']);
    logMessage("Params: " . json_encode($params));
    
    $stmt->execute($params);
    
    $pdo->commit();
    
    logMessage("Cliente atualizado com sucesso - ID: " . $input['id']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Cliente atualizado com sucesso!',
        'tipocliente_final' => $tipoCliente,
        'empresa_id' => $idcliente_empresa,
        'admin_id' => $admin_id
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    logMessage("ERRO ao atualizar cliente: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao atualizar cliente: ' . $e->getMessage()
    ]);
}

// Função auxiliar para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}
?>