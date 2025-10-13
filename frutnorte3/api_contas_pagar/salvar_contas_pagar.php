<?php
// ========================================
// API PARA SALVAR CONTAS A PAGAR
// Sistema de Contas a Pagar - Frutnorte
// ========================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

require_once '../config/databaselogin.php'; // o arquivo que contém a classe Database

$database = new Database();
$pdo = $database->getConnection();

/**
 * Conecta ao banco de dados
 */
function conectarBanco() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8",
            $db_config['username'],
            $db_config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ]
        );
        error_log("API Contas Pagar - Conexão ao BD bem-sucedida em " . date('Y-m-d H:i:s'));
        return $pdo;
    } catch (PDOException $e) {
        error_log("API Contas Pagar - Erro na conexão com o banco (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        throw new Exception("Erro na conexão com o banco: " . $e->getMessage());
    }
}

/**
 * Obtém o próximo código de contas a pagar (sequencial normal) - CORRIGIDO: Usa idcliente dinâmico
 */
function obterProximoCodigoPagar($pdo, $idcliente) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(codpagar) as max_cod FROM contaspagar WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch();
        $proximo = ($result['max_cod'] ?? 0) + 1;
        error_log("API Contas Pagar - Próximo codpagar sequencial para idcliente={$idcliente}: " . $proximo . " (" . date('Y-m-d H:i:s') . ")");
        return $proximo;
    } catch (Exception $e) {
        error_log("API Contas Pagar - Erro ao obter próximo codpagar para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        return 1;
    }
}

/**
 * Obtém o próximo código de baixa de contas a pagar - CORRIGIDO: Usa idcliente dinâmico
 */
function obterProximoCodigoBaixa($pdo, $idcliente) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(codbaixapagar) as max_cod FROM baixapagar WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch();
        $proximo = ($result['max_cod'] ?? 0) + 1;
        error_log("API Contas Pagar - Próximo codbaixapagar para idcliente={$idcliente}: " . $proximo . " (" . date('Y-m-d H:i:s') . ")");
        return $proximo;
    } catch (Exception $e) {
        error_log("API Contas Pagar - Erro ao obter próximo codbaixapagar para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        return 1;
    }
}

/**
 * Obtém informações sobre parcelas existentes com base no identificador - CORRIGIDO: Usa idcliente dinâmico
 */
function obterInfoParcelasExistentes($pdo, $identificadorUnico, $idcliente) {
    try {
        // Buscar por referência no campo obs
        $stmt = $pdo->prepare("
            SELECT codpagar, MAX(seqcodpagar) as max_seq, COUNT(*) as total_parcelas
            FROM contaspagar 
            WHERE idcliente = ? AND obs LIKE ? 
            GROUP BY codpagar 
            ORDER BY codpagar DESC 
            LIMIT 1
        ");
        $likePattern = '%Ref: ' . $identificadorUnico . '%';
        $stmt->execute([$idcliente, $likePattern]);
        $result = $stmt->fetch();
        
        if ($result) {
            error_log("API Contas Pagar - Parcela existente encontrada para idcliente={$idcliente} - Codpagar: {$result['codpagar']}, Max Seq: {$result['max_seq']}, Total: {$result['total_parcelas']} (" . date('Y-m-d H:i:s') . ")");
            return $result;
        }
        
        error_log("API Contas Pagar - Nenhuma parcela existente encontrada para idcliente={$idcliente} e identificador={$identificadorUnico} (" . date('Y-m-d H:i:s') . ")");
        return null;
    } catch (Exception $e) {
        error_log("API Contas Pagar - Erro ao buscar parcelas existentes para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        return null;
    }
}

/**
 * Valida os dados obrigatórios para uma parcela - CORRIGIDO: Adiciona validação para idcliente
 */
function validarParcela($parcela) {
    $erros = [];
    
    // Campos obrigatórios (incluindo idcliente agora)
    $camposObrigatorios = [
        'idcliente' => 'ID do cliente/empresa',
        'codentrada' => 'Código da entrada',
        'codcliente' => 'Código do cliente/fornecedor',
        'vrtitulo' => 'Valor do título',
        'datavencimento' => 'Data de vencimento',
        'dataemissao' => 'Data de emissão'
    ];
    
    foreach ($camposObrigatorios as $campo => $nome) {
        if (empty($parcela[$campo])) {
            $erros[] = "{$nome} é obrigatório";
        }
    }
    
    // Validação específica para idcliente: deve ser numérico e > 0
    if (!empty($parcela['idcliente']) && (!is_numeric($parcela['idcliente']) || (int)$parcela['idcliente'] <= 0)) {
        $erros[] = "ID do cliente/empresa deve ser um número positivo válido";
    }
    
    // Validar formato de datas
    $camposDatas = ['datavencimento', 'dataemissao', 'datalancamento', 'datapagamento'];
    foreach ($camposDatas as $campo) {
        if (!empty($parcela[$campo])) {
            // Remover hora se existir
            $dataString = $parcela[$campo];
            if (strpos($dataString, ' ') !== false) {
                $dataParte = explode(' ', $dataString)[0];
            } else {
                $dataParte = $dataString;
            }
            
            $data = DateTime::createFromFormat('Y-m-d', $dataParte);
            if (!$data || $data->format('Y-m-d') !== $dataParte) {
                $erros[] = "{$campo} deve estar no formato YYYY-MM-DD (recebido: {$dataString})";
            }
        }
    }
    
    // Validar valores numéricos
    $camposNumericos = ['vrtitulo', 'vrdocumento', 'vrdesconto', 'percdesconto', 'vracrescimo', 'vrfinal', 'vrpago', 'saldo'];
    foreach ($camposNumericos as $campo) {
        if (isset($parcela[$campo]) && $parcela[$campo] !== '' && !is_numeric($parcela[$campo])) {
            $erros[] = "{$campo} deve ser um valor numérico (recebido: {$parcela[$campo]})";
        }
    }
    
    if (!empty($erros)) {
        error_log("API Contas Pagar - Erros de validação (" . date('Y-m-d H:i:s') . "): " . implode(', ', $erros));
    }
    
    return $erros;
}

/**
 * Calcula valores automaticamente para uma parcela
 */
function calcularValoresParcela($parcela) {
    $vrtitulo = floatval($parcela['vrtitulo'] ?? 0);
    $vrdesconto = floatval($parcela['vrdesconto'] ?? 0);
    $vracrescimo = floatval($parcela['vracrescimo'] ?? 0);
    $vrpago = floatval($parcela['vrpago'] ?? 0);
    
    // Calcular valor final
    $vrfinal = $vrtitulo - $vrdesconto + $vracrescimo;
    
    // Calcular saldo
    $saldo = $vrfinal - $vrpago;
    
    $calculados = [
        'vrfinal' => $vrfinal,
        'saldo' => $saldo,
        'vrdocumento' => $parcela['vrdocumento'] ?? $vrtitulo // Se não informado, usa o valor do título
    ];
    
    error_log("API Contas Pagar - Valores calculados: vrfinal={$vrfinal}, saldo={$saldo}, vrdocumento={$calculados['vrdocumento']} (" . date('Y-m-d H:i:s') . ")");
    
    return $calculados;
}

/**
 * Processa data - remove hora se existir
 */
function processarData($dataString) {
    if (empty($dataString)) {
        return null;
    }
    
    if (strpos($dataString, ' ') !== false) {
        return explode(' ', $dataString)[0];
    }
    
    return $dataString;
}

try {
    // Log inicial da API
    error_log("API Contas Pagar - Iniciada em " . date('Y-m-d H:i:s'));
    
    // Receber dados JSON
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);
    
    error_log("API Contas Pagar - Dados recebidos (JSON raw: {$input}) (" . date('Y-m-d H:i:s') . "): " . print_r($dados, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos: ' . json_last_error_msg());
    }
    
    // Extrair e validar idcliente imediatamente
    $idcliente = isset($dados['idcliente']) ? (int)$dados['idcliente'] : null;
    error_log("API Contas Pagar - IDCLIENTE extraído do JSON: {$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    if (!$idcliente || $idcliente <= 0) {
        throw new Exception('ID do cliente/empresa é obrigatório e deve ser um número positivo.');
    }
    
    // Validar dados da parcela
    $erros = validarParcela($dados);
    if (!empty($erros)) {
        throw new Exception('Dados inválidos: ' . implode(', ', $erros));
    }
    
    // Conectar ao banco
    $pdo = conectarBanco();
    $pdo->beginTransaction();
    
    error_log("API Contas Pagar - Transação iniciada para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    // LÓGICA PARA DETERMINAR codpagar E seqcodpagar
    $codpagar = null;
    $seqcodpagar = 1;
    
    // Verificar se já existe alguma parcela com o mesmo identificador_unico
    if (!empty($dados['identificador_unico'])) {
        $infoParcelas = obterInfoParcelasExistentes($pdo, $dados['identificador_unico'], $idcliente);
        
        if ($infoParcelas) {
            // Já existe uma parcela com este identificador, usar o mesmo codpagar
            $codpagar = $infoParcelas['codpagar'];
            $seqcodpagar = $infoParcelas['max_seq'] + 1;
            error_log("API Contas Pagar - Usando codpagar existente para idcliente={$idcliente}: {$codpagar}, seq={$seqcodpagar} (" . date('Y-m-d H:i:s') . ")");
        }
    }
    
    // Se não encontrou parcela existente, criar novo codpagar sequencial
    if (!$codpagar) {
        $codpagar = obterProximoCodigoPagar($pdo, $idcliente);
        error_log("API Contas Pagar - Novo codpagar gerado para idcliente={$idcliente}: {$codpagar} (" . date('Y-m-d H:i:s') . ")");
    }
    
    // Calcular valores automaticamente
    $valoresCalculados = calcularValoresParcela($dados);
    
    // Processar datas (remover hora se existir)
    $datalancamento = processarData($dados['datalancamento'] ?? '') ?: date('Y-m-d');
    $dataemissao = processarData($dados['dataemissao'] ?? '');
    $datavencimento = processarData($dados['datavencimento'] ?? '');
    $datapagamento = processarData($dados['datapagamento'] ?? '');
    
    // Preparar dados para inserção na tabela contas_pagar - CORRIGIDO: idcliente dinâmico
    $dadosContasPagar = [
        'idcliente' => $idcliente, // Agora dinâmico, do JSON
        'codpagar' => $codpagar,
        'seqcodpagar' => $seqcodpagar,
        'codempresa' => $dados['codempresa'] ?? 1,
        'codentrada' => $dados['codentrada'],
        'codcliente' => $dados['codcliente'],
        'serienota' => $dados['serienota'] ?? '1',
        'numeronota' => $dados['numeronota'] ?? null,
        'Datalancamento' => $datalancamento,
        'dataemissao' => $dataemissao,
        'datavencimento' => $datavencimento,
        'datapagamento' => $datapagamento,
        'vrtitulo' => floatval($dados['vrtitulo']),
                'vrdocumento' => floatval($valoresCalculados['vrdocumento']),
        'vrdesconto' => floatval($dados['vrdesconto'] ?? 0),
        'percdesconto' => floatval($dados['percdesconto'] ?? 0),
        'vracrescimo' => floatval($dados['vracrescimo'] ?? 0),
        'vrfinal' => floatval($valoresCalculados['vrfinal']),
        'vrpago' => floatval($dados['vrpago'] ?? 0),
        'saldo' => floatval($valoresCalculados['saldo']),
        'obs' => $dados['obs'] ?? '',
        'codtpdes' => $dados['codtpdes'] ?? null,
        'codcond' => $dados['codcond'] ?? null,
        'codtppag' => $dados['codtppag'] ?? null,
        'ano_safra' => $dados['ano_safra'] ?? date('Y'),
        'caixa' => $dados['caixa'] ?? null,
        'origem' => $dados['origem'] ?? 'ENTRADA_PRODUTOS',
        'previsao' => $dados['previsao'] ?? 'N',
        'codigoboleto' => $dados['codigoboleto'] ?? '',
        'codigobarras' => $dados['codigobarras'] ?? '',
        'placa' => $dados['placa'] ?? '',
        'dados_pagto' => $dados['dados_pagto'] ?? '',
        'idcontrato' => $dados['idcontrato'] ?? null,
        'litros' => $dados['litros'] ?? null,
        'km_nf' => $dados['km_nf'] ?? null,
        'km_ant' => $dados['km_ant'] ?? null,
        'media' => $dados['media'] ?? null,
        'dtcompetencia' => processarData($dados['dtcompetencia'] ?? ''),
        'oper1' => $dados['oper1'] ?? null,
        'oper2' => $dados['oper2'] ?? null,
        'datahora1' => $dados['datahora1'] ?? null,
        'datahora2' => $dados['datahora2'] ?? null
    ];
    
    error_log("API Contas Pagar - Dados para inserção em contaspagar (idcliente={$idcliente}): " . print_r($dadosContasPagar, true) . " (" . date('Y-m-d H:i:s') . ")");
    
    // Inserir na tabela contas_pagar
    $campos = implode(', ', array_keys($dadosContasPagar));
    $placeholders = ':' . implode(', :', array_keys($dadosContasPagar));
    
    $sql = "INSERT INTO contaspagar ({$campos}) VALUES ({$placeholders})";
    error_log("API Contas Pagar - SQL de inserção em contaspagar para idcliente={$idcliente}: {$sql} (" . date('Y-m-d H:i:s') . ")");
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dadosContasPagar);
    
    $idContasPagar = $pdo->lastInsertId();
    
    error_log("API Contas Pagar - Parcela inserida com sucesso para idcliente={$idcliente}, ID: {$idContasPagar}, codpagar={$codpagar}, seqcodpagar={$seqcodpagar} (" . date('Y-m-d H:i:s') . ")");
    
    // Se há pagamento, inserir na tabela baixapagar - CORRIGIDO: idcliente dinâmico
    $vrpago = floatval($dados['vrpago'] ?? 0);
    if ($vrpago > 0) {
        $codbaixapagar = obterProximoCodigoBaixa($pdo, $idcliente);
        
        $dadosBaixa = [
            'idcliente' => $idcliente, // Agora dinâmico
            'codempresa' => $dados['codempresa'] ?? 1,
            'codbaixapagar' => $codbaixapagar,
            'codempresadoc' => $dados['codempresa'] ?? 1,
            'codpagar' => $codpagar,
            'seqcodpagar' => $seqcodpagar,
            'datapgto' => $datapagamento ?? date('Y-m-d'),
            'vrpago' => $vrpago,
            'caixa' => $dados['caixa'] ?? null,
            'recibo' => $dados['recibo'] ?? null,
            'oper' => $dados['oper'] ?? null,
            'saldo' => floatval($valoresCalculados['saldo']),
            'tipopgto' => $dados['tipopgto'] ?? 'DINHEIRO'
        ];
        
        error_log("API Contas Pagar - Dados para inserção em baixapagar (idcliente={$idcliente}): " . print_r($dadosBaixa, true) . " (" . date('Y-m-d H:i:s') . ")");
        
        $camposBaixa = implode(', ', array_keys($dadosBaixa));
        $placeholdersBaixa = ':' . implode(', :', array_keys($dadosBaixa));
        
        $sqlBaixa = "INSERT INTO baixapagar ({$camposBaixa}) VALUES ({$placeholdersBaixa})";
        error_log("API Contas Pagar - SQL de inserção em baixapagar para idcliente={$idcliente}: {$sqlBaixa} (" . date('Y-m-d H:i:s') . ")");
        
        $stmtBaixa = $pdo->prepare($sqlBaixa);
        $stmtBaixa->execute($dadosBaixa);
        
        error_log("API Contas Pagar - Baixa inserida com sucesso para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    } else {
        error_log("API Contas Pagar - Nenhum pagamento informado (vrpago=0), pulando inserção em baixapagar para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    }
    
    // Commit da transação
    $pdo->commit();
    
    error_log("API Contas Pagar - Transação commitada com sucesso para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    // Resposta de sucesso - CORRIGIDO: Inclui idcliente na resposta
    $response = [
        'success' => true,
        'message' => 'Parcela salva com sucesso!',
        'data' => [
            'id' => $idContasPagar,
            'idcliente' => $idcliente, // Adicionado para confirmação
            'codpagar' => $codpagar,
            'seqcodpagar' => $seqcodpagar,
            'vrfinal' => $valoresCalculados['vrfinal'],
            'saldo' => $valoresCalculados['saldo'],
            'datavencimento' => $datavencimento,
            'status_pagamento' => $valoresCalculados['saldo'] <= 0 ? 'PAGO' : 'PENDENTE'
        ]
    ];
    
    error_log("API Contas Pagar - Resposta de sucesso enviada para idcliente={$idcliente}: " . print_r($response, true) . " (" . date('Y-m-d H:i:s') . ")");
    
    http_response_code(201);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("API Contas Pagar - Rollback executado devido a erro (" . date('Y-m-d H:i:s') . ")");
    }
    
    // Log do erro detalhado
    error_log("API Contas Pagar - ERRO AO SALVAR CONTA A PAGAR (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
    error_log("API Contas Pagar - Stack trace: " . $e->getTraceAsString());
    error_log("API Contas Pagar - Dados que causaram o erro: " . print_r($dados ?? [], true));
    
    // Resposta de erro
    $response = [
        'success' => false,
        'message' => 'Erro ao salvar conta a pagar: ' . $e->getMessage(),
        'error_code' => 'SAVE_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
