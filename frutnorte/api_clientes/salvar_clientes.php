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

// ==================== CONEXÃO LOGIN (para validar empresa_id/idcliente) ====================
require_once '../config/databaselogin.php';
$dbLogin = new DatabaseLogin();  // Assumindo que a classe é Database em databaselogin.php
$connlogin = $dbLogin->getConnection();

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente) do usuário autenticado (sem filtro de cargo)
$stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
$stmt->execute([$admin_id]);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_data || empty($admin_data['empresa_id'])) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
    exit;
}

$idcliente = $admin_data['empresa_id'];  // 👈 idcliente = empresa_id da sessão
$_SESSION['empresa_id'] = $idcliente;  // Atualiza na sessão

// ==================== CONEXÃO SISTEMA (para operações de clientes) ====================
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['Nome']) || empty($input['Email']) || empty($input['cnpj_cpf'])) {
        throw new Exception('Campos obrigatórios não preenchidos: Nome, Email e CPF/CNPJ são obrigatórios');
    }

    $cnpj_cpf_limpo = preg_replace('/\D/', '', $input['cnpj_cpf']);
    $stmt = $db->prepare("SELECT id FROM clientes WHERE cnpj_cpf = ? AND id != ? AND idcliente = ?");
    $stmt->execute([$cnpj_cpf_limpo, $input['id'] ?? 0, $idcliente]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('CPF/CNPJ já cadastrado no sistema');
    }

    $stmt = $db->prepare("SELECT id FROM clientes WHERE Email = ? AND id != ? AND idcliente = ?");
    $stmt->execute([trim($input['Email']), $input['id'] ?? 0, $idcliente]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('E-mail já cadastrado no sistema');
    }

    $stmt = $db->prepare("SELECT MAX(codcliente) as max_cod FROM clientes WHERE idcliente = ?");
    $stmt->execute([$idcliente]);
    $result = $stmt->fetch();
    $proximoCodCliente = ($result['max_cod'] ?? 0) + 1;

    // Buscar tipo de conta da primeira conta bancária
    $tipoconta = null;
    if (isset($_SESSION['contas_bancarias_temp']) && is_array($_SESSION['contas_bancarias_temp']) && !empty($_SESSION['contas_bancarias_temp'])) {
        $primeiraConta = reset($_SESSION['contas_bancarias_temp']);
        $tipoconta = $primeiraConta['tipoconta'] ?? null;
    }

    // Mapear tipocliente para números
    $tipoclienteMap = [
        'cliente' => 1,
        'fornecedor' => 2,
        'funcionario' => 3,
        'outro' => 4
    ];

    $tipoclienteEnviado = isset($input['tipocliente']) ? strtolower($input['tipocliente']) : 'cliente';
    $tipoclienteNumerico = $tipoclienteMap[$tipoclienteEnviado] ?? 1;

    // Processar campos motorista e transportadora
    $motorista = 'N';
    $transportadora = 'N';
    
    if ($tipoclienteEnviado === 'funcionario' && isset($input['motorista'])) {
        $motorista = $input['motorista'] === 'S' ? 'S' : 'N';
    } elseif (in_array($tipoclienteEnviado, ['cliente', 'fornecedor', 'outro']) && isset($input['transportadora'])) {
        $transportadora = $input['transportadora'] === 'S' ? 'S' : 'N';
    }

    $dados = [
        'idcliente' => $idcliente,
        'codcliente' => $proximoCodCliente,
        'Nome' => trim($input['Nome']),
        'Fantasia' => isset($input['Fantasia']) && !empty(trim($input['Fantasia'])) ? trim($input['Fantasia']) : null,
        'cnpj_cpf' => $cnpj_cpf_limpo,
        'IE' => isset($input['IE']) && !empty(trim($input['IE'])) ? trim($input['IE']) : null,
        'IM' => isset($input['IM']) && !empty(trim($input['IM'])) ? trim($input['IM']) : null,
        'insc_rural' => isset($input['insc_rural']) && !empty(trim($input['insc_rural'])) ? trim($input['insc_rural']) : null,
        'insc_suframa' => isset($input['insc_suframa']) && !empty(trim($input['insc_suframa'])) ? trim($input['insc_suframa']) : null,
        'nascimento' => isset($input['nascimento']) && !empty($input['nascimento']) ? $input['nascimento'] : null,
        'Email' => trim($input['Email']),
        'Fone' => isset($input['Fone']) && !empty($input['Fone']) ? preg_replace('/\D/', '', $input['Fone']) : null,
        'celular' => isset($input['celular']) && !empty($input['celular']) ? preg_replace('/\D/', '', $input['celular']) : null,
        'Contato' => isset($input['Contato']) && !empty(trim($input['Contato'])) ? trim($input['Contato']) : null,
        'CEP' => isset($input['CEP']) && !empty($input['CEP']) ? preg_replace('/\D/', '', $input['CEP']) : null,
        'Endereco' => isset($input['Endereco']) && !empty(trim($input['Endereco'])) ? trim($input['Endereco']) : null,
        'numero' => isset($input['numero']) && !empty(trim($input['numero'])) ? trim($input['numero']) : null,
        'complemento' => isset($input['complemento']) && !empty(trim($input['complemento'])) ? trim($input['complemento']) : null,
        'Bairro' => isset($input['Bairro']) && !empty(trim($input['Bairro'])) ? trim($input['Bairro']) : null,
        'Cidade' => isset($input['Cidade']) && !empty(trim($input['Cidade'])) ? trim($input['Cidade']) : null,
        'Uf' => isset($input['Uf']) && !empty($input['Uf']) ? $input['Uf'] : null,
        'pais' => isset($input['pais']) && !empty(trim($input['pais'])) ? trim($input['pais']) : 'BRASIL',
        'tipocliente' => $tipoclienteNumerico,
        'CondPgto' => isset($input['CondPgto']) && !empty(trim($input['CondPgto'])) ? trim($input['CondPgto']) : null,
        'Transportadora' => isset($input['Transportadora']) && !empty(trim($input['Transportadora'])) ? trim($input['Transportadora']) : null,
        'PercDesconto' => isset($input['PercDesconto']) && is_numeric($input['PercDesconto']) ? floatval($input['PercDesconto']) : null,
        'limite' => isset($input['limite']) && is_numeric($input['limite']) ? floatval($input['limite']) : null,
        'saldo_devedor' => isset($input['saldo_devedor']) && is_numeric($input['saldo_devedor']) ? floatval($input['saldo_devedor']) : 0,
        'codvendedor' => isset($input['codvendedor']) && is_numeric($input['codvendedor']) ? intval($input['codvendedor']) : null,
        'NotaKG' => isset($input['NotaKG']) && !empty($input['NotaKG']) ? $input['NotaKG'] : 'S',
        'pdesconto_boleto' => isset($input['pdesconto_boleto']) && is_numeric($input['pdesconto_boleto']) ? floatval($input['pdesconto_boleto']) : 0,
        'protesto_automatico_boletos' => isset($input['protesto_automatico_boletos']) && !empty($input['protesto_automatico_boletos']) ? $input['protesto_automatico_boletos'] : 'N',
        'dias_protesto' => isset($input['dias_protesto']) && is_numeric($input['dias_protesto']) ? intval($input['dias_protesto']) : 5,
        'ativo' => isset($input['ativo']) && !empty($input['ativo']) ? $input['ativo'] : 'S',
        'tipo_pessoa' => isset($input['tipo_pessoa']) && !empty($input['tipo_pessoa']) ? $input['tipo_pessoa'] : 'F',
        'senha' => isset($input['senha']) && !empty(trim($input['senha'])) ? trim($input['senha']) : null,
        'obs' => isset($input['obs']) && !empty(trim($input['obs'])) ? trim($input['obs']) : null,
        'Data_cad' => date('Y-m-d'),
        'tipoconta' => $tipoconta,
        'motorista' => $motorista,
        'transportador' => $transportadora
    ];

    $sql = "INSERT INTO clientes (
        idcliente, codcliente, Nome, Fantasia, cnpj_cpf, IE, IM, insc_rural, insc_suframa, nascimento,
        Email, Fone, celular, Contato, CEP, Endereco, numero, complemento, Bairro, Cidade, Uf, pais,
        tipocliente, CondPgto, Transportadora, PercDesconto, limite, saldo_devedor, codvendedor,
        NotaKG, pdesconto_boleto, protesto_automatico_boletos, dias_protesto, ativo, tipo_pessoa, senha, obs, Data_cad,
        tipoconta, motorista, transportador
    ) VALUES (
        :idcliente, :codcliente, :Nome, :Fantasia, :cnpj_cpf, :IE, :IM, :insc_rural, :insc_suframa, :nascimento,
        :Email, :Fone, :celular, :Contato, :CEP, :Endereco, :numero, :complemento, :Bairro, :Cidade, :Uf, :pais,
        :tipocliente, :CondPgto, :Transportadora, :PercDesconto, :limite, :saldo_devedor, :codvendedor,
        :NotaKG, :pdesconto_boleto, :protesto_automatico_boletos, :dias_protesto, :ativo, :tipo_pessoa, :senha, :obs, :Data_cad,
        :tipoconta, :motorista, :transportador
    )";

    $stmt = $db->prepare($sql);
    $stmt->execute($dados);
    $clienteId = $db->lastInsertId();

    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$clienteId]);
    $cliente = $stmt->fetch();

    // Salvar contas bancárias
    $contasSalvas = [];
    if (isset($_SESSION['contas_bancarias_temp']) && is_array($_SESSION['contas_bancarias_temp']) && !empty($_SESSION['contas_bancarias_temp'])) {
        $sqlConta = "INSERT INTO contas_clientes (idcliente, codcliente, banco, agencia, nconta, chavepix, cpf_titular, nome_titular) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtConta = $db->prepare($sqlConta);
        
        foreach ($_SESSION['contas_bancarias_temp'] as $conta) {
            if (!empty($conta['banco']) && !empty($conta['agencia']) && !empty($conta['nconta'])) {
                $stmtConta->execute([
                    $idcliente,
                    $proximoCodCliente,
                    $conta['banco'],
                    $conta['agencia'],
                    $conta['nconta'],
                    $conta['chavepix'] ?? '',
                    $conta['cpf_titular'] ?? '',
                    $conta['nome_titular'] ?? ''
                ]);
                
                $contasSalvas[] = $conta;
            }
        }
        
        $_SESSION['contas_bancarias_temp'] = [];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cliente cadastrado com sucesso!',
        'cliente' => [
            'id' => $clienteId,
            'codcliente' => $cliente['codcliente'],
            'Nome' => $cliente['Nome'],
            'tipocliente' => $tipoclienteNumerico,
            'tipoconta' => $tipoconta,
            'motorista' => $motorista,
            'transportadora' => $transportadora
        ],
        'contas_salvas' => $contasSalvas,
        'total_contas' => count($contasSalvas)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
