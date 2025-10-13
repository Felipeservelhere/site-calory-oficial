<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Faça login para continuar.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    session_destroy();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Faça login novamente.',
        'error_code' => 'INVALID_SESSION'
    ]);
    exit;
}

// ==================== CONEXÃO SISTEMA ====================
require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$idcliente = $_SESSION['empresa_id'];

// ========================================
// API PARA CRIAR CONTAS A PAGAR A PARTIR DE ENTRADA
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

/**
 * Conecta ao banco de dados
 */
function conectarBanco() {
    global $pdo; // Usa a conexão global da classe Database
    return $pdo;
}

/**
 * Obtém o próximo código de contas a pagar (sequencial normal) - COM FILTRO POR IDCLIENTE
 */
function obterProximoCodigoPagar($pdo, $idcliente) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(codpagar) as max_cod FROM contaspagar WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch();
        $proximo = ($result['max_cod'] ?? 0) + 1;
        error_log("Próximo codpagar sequencial (idcliente={$idcliente}): " . $proximo);
        return $proximo;
    } catch (Exception $e) {
        error_log("Erro ao obter próximo codpagar (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

/**
 * Obtém o próximo código de baixa de contas a pagar - COM FILTRO POR IDCLIENTE
 */
function obterProximoCodigoBaixa($pdo, $idcliente) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(codbaixapagar) as max_cod FROM baixapagar WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch();
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Erro ao obter próximo codbaixapagar (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

/**
 * Valida os dados obrigatórios para criação de contas a pagar
 */
function validarDadosEntrada($dados, $idcliente) {
    $erros = [];
    
    // Campos obrigatórios
    $camposObrigatorios = [
        'codentrada' => 'Código da entrada',
        'data_vencimento' => 'Data de vencimento',
        'codcond' => 'Condição de pagamento'
    ];
    
    foreach ($camposObrigatorios as $campo => $nome) {
        if (empty($dados[$campo])) {
            $erros[] = "{$nome} é obrigatório";
        }
    }
    
    // Validar formato de data
    if (!empty($dados['data_vencimento'])) {
        $data = DateTime::createFromFormat('Y-m-d', $dados['data_vencimento']);
        if (!$data || $data->format('Y-m-d') !== $dados['data_vencimento']) {
            $erros[] = "data_vencimento deve estar no formato YYYY-MM-DD (recebido: {$dados['data_vencimento']})";
        }
    }
    
    // Validar idcliente se passado (opcional, para fallback)
    if (!empty($dados['idcliente']) && $dados['idcliente'] != $idcliente) {
        $erros[] = "ID da empresa inválido";
    }
    
    return $erros;
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

/**
 * Busca informações completas da entrada - COM FILTRO POR IDCLIENTE
 */
function buscarInformacoesEntrada($pdo, $codentrada, $idcliente) {
    try {
        $sql = "SELECT e.codentrada, e.vrTotal, e.numeronota, e.serienota, e.Dataentrada,
                       e.Codcliente, e.codtpdes, e.codtppag, e.ano_safra, e.placa,
                       c.Nome as fornecedor_nome, c.cnpj_cpf as fornecedor_documento
                FROM entradas e 
                LEFT JOIN clientes c ON e.Codcliente = c.codcliente AND c.idcliente = ?
                WHERE e.codentrada = ? AND e.idcliente = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idcliente, $codentrada, $idcliente]);
        $entrada = $stmt->fetch();
        
        if (!$entrada) {
            throw new Exception("Entrada {$codentrada} não encontrada para a empresa {$idcliente}");
        }
        
        return $entrada;
    } catch (Exception $e) {
        throw new Exception("Erro ao buscar entrada: " . $e->getMessage());
    }
}

/**
 * Busca informações da condição de pagamento - COM FILTRO POR IDCLIENTE
 */
function buscarInformacoesCondicao($pdo, $codcond, $idcliente) {
    try {
        $sql = "SELECT codcond, Descricao, Parcelas, CondPgto1, CondPgto2, CondPgto3, CondPgto4, condpgto5, condpgto6 
                FROM condicoes 
                WHERE codcond = ? AND idcliente = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codcond, $idcliente]);
        $condicao = $stmt->fetch();
        
        if (!$condicao) {
            throw new Exception("Condição de pagamento {$codcond} não encontrada para a empresa {$idcliente}");
        }
        
        return $condicao;
    } catch (Exception $e) {
        throw new Exception("Erro ao buscar condição de pagamento: " . $e->getMessage());
    }
}

/**
 * Calcula valores automaticamente para uma parcela
 */
function calcularValoresParcela($vrtitulo, $vrdesconto = 0, $vracrescimo = 0, $vrpago = 0) {
    $vrtitulo = floatval($vrtitulo);
    $vrdesconto = floatval($vrdesconto);
    $vracrescimo = floatval($vracrescimo);
    $vrpago = floatval($vrpago);
    
    // Calcular valor final
    $vrfinal = $vrtitulo - $vrdesconto + $vracrescimo;
    
    // Calcular saldo
    $saldo = $vrfinal - $vrpago;
    
    return [
        'vrfinal' => $vrfinal,
        'saldo' => $saldo,
        'vrdocumento' => $vrtitulo
    ];
}

try {
    // Receber dados JSON
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);
    
    error_log("Dados recebidos na API criar_conta_pagar (idcliente={$idcliente}): " . print_r($dados, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos: ' . json_last_error_msg());
    }
    
    // Validar dados da entrada
    $erros = validarDadosEntrada($dados, $idcliente);
    if (!empty($erros)) {
        throw new Exception('Dados inválidos: ' . implode(', ', $erros));
    }
    
    // Conectar ao banco
    $pdo = conectarBanco();
    $pdo->beginTransaction();
    
    // Buscar informações da entrada
    $entrada = buscarInformacoesEntrada($pdo, $dados['codentrada'], $idcliente);
    error_log("Informações da entrada (idcliente={$idcliente}): " . print_r($entrada, true));
    
    // Buscar informações da condição de pagamento
    $condicao = buscarInformacoesCondicao($pdo, $dados['codcond'], $idcliente);
    error_log("Informações da condição (idcliente={$idcliente}): " . print_r($condicao, true));
    
    // Calcular parcelas baseado na condição
    $numParcelas = (int)$condicao['Parcelas'];
    $valorParcelaBase = floatval($entrada['vrTotal']) / $numParcelas;
    
    // Gerar um único codpagar para todas as parcelas
    $codpagar = obterProximoCodigoPagar($pdo, $idcliente);
    error_log("Novo codpagar gerado para todas as parcelas (idcliente={$idcliente}): {$codpagar}");
    
    // Gerar identificador único para todas as parcelas
    $identificadorUnico = uniqid();
    $responses = [];
    
    // Inserir cada parcela com o MESMO codpagar e seqcodpagar DIFERENTE
    for ($i = 1; $i <= $numParcelas; $i++) {
        $seqcodpagar = $i; // seqcodpagar será 1, 2, 3, etc.
        
        // Calcular dias de vencimento
        $diasVencimento = $condicao["CondPgto{$i}"] ?? 0;
        $dataVencimento = date('Y-m-d', strtotime($dados['data_vencimento'] . " + {$diasVencimento} days"));
        
        // Calcular valor da parcela (ajustar última parcela para evitar arredondamento)
        $valorParcela = $i < $numParcelas ? $valorParcelaBase : (floatval($entrada['vrTotal']) - ($numParcelas - 1) * $valorParcelaBase);
        
        // Calcular valores automaticamente
        $valoresCalculados = calcularValoresParcela($valorParcela);
        
        // Processar datas
        $datalancamento = date('Y-m-d');
        $dataemissao = processarData($entrada['Dataentrada']) ?: date('Y-m-d');
        
        // Preparar observações - indicar número da parcela no campo obs
        $obs = "NF: {$entrada['numeronota']}/{$entrada['serienota']} - Parcela {$i} de {$numParcelas} - Ref: {$identificadorUnico}";
        if (!empty($dados['observacoes'])) {
            $obs .= " - " . $dados['observacoes'];
        }
        
        // Preparar dados para inserção na tabela contas_pagar
        $dadosContasPagar = [
            'idcliente' => $idcliente, // ID dinâmico da empresa
            'codpagar' => $codpagar,
            'seqcodpagar' => $seqcodpagar,
            'codempresa' => 1,
            'codentrada' => $dados['codentrada'],
            'codcliente' => $entrada['Codcliente'],
            'serienota' => $entrada['serienota'] ?? '1',
            'numeronota' => $entrada['numeronota'] ?? null,
            'Datalancamento' => $datalancamento,
            'dataemissao' => $dataemissao,
            'datavencimento' => $dataVencimento,
            'datapagamento' => null, // Não pago inicialmente
            'vrtitulo' => $valorParcela,
            'vrdocumento' => $valoresCalculados['vrdocumento'],
            'vrdesconto' => 0,
            'percdesconto' => 0,
            'vracrescimo' => 0,
            'vrfinal' => $valoresCalculados['vrfinal'],
            'vrpago' => 0,
            'saldo' => $valoresCalculados['saldo'],
            'obs' => $obs,
            'codtpdes' => $entrada['codtpdes'] ?? null,
            'codcond' => $dados['codcond'],
            'codtppag' => $entrada['codtppag'] ?? null,
            'ano_safra' => $entrada['ano_safra'] ?? date('Y'),
            'caixa' => null,
            'origem' => 'ENTRADA_PRODUTOS',
            'previsao' => 'N',
            'codigoboleto' => '',
            'codigobarras' => '',
            'placa' => $entrada['placa'] ?? '',
            'dados_pagto' => '',
            'idcontrato' => null,
            'litros' => null,
            'km_nf' => null,
            'km_ant' => null,
            'media' => null,
            'dtcompetencia' => null,
            'oper1' => null,
            'oper2' => null,
            'datahora1' => null,
            'datahora2' => null
        ];
        
        error_log("Inserindo parcela {$i} (idcliente={$idcliente}): codpagar={$codpagar}, seqcodpagar={$seqcodpagar}, valor={$valorParcela}");
        
        // Inserir na tabela contas_pagar
        $campos = implode(', ', array_keys($dadosContasPagar));
        $placeholders = ':' . implode(', :', array_keys($dadosContasPagar));
        
        $sql = "INSERT INTO contaspagar ({$campos}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dadosContasPagar);
        
        $idContasPagar = $pdo->lastInsertId();
        
        error_log("Parcela {$i} inserida com ID (idcliente={$idcliente}): " . $idContasPagar);
        
        $responses[] = [
            'parcela' => $i,
            'codpagar' => $codpagar,
            'seqcodpagar' => $seqcodpagar,
            'valor' => $valorParcela,
            'vencimento' => $dataVencimento
        ];
    }
    
    // Commit da transação
    $pdo->commit();
    
    error_log("Transação commitada com sucesso (idcliente={$idcliente}) - {$numParcelas} parcelas criadas com codpagar: {$codpagar}");
    
    // Resposta de sucesso
    $response = [
        'success' => true,
        'message' => "{$numParcelas} conta(s) a pagar criada(s) com sucesso!",
        'data' => [
            'codentrada' => $dados['codentrada'],
            'codpagar' => $codpagar, // Único codpagar para todas as parcelas
            'total_parcelas' => $numParcelas,
            'valor_total' => floatval($entrada['vrTotal']),
            'identificador_unico' => $identificadorUnico,
            'parcelas' => $responses
        ]
    ];
    
    http_response_code(201);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro
    error_log("ERRO AO CRIAR CONTAS A PAGAR (idcliente={$idcliente}): " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Resposta de erro
    $response = [
        'success' => false,
        'message' => 'Erro ao criar contas a pagar: ' . $e->getMessage(),
        'error_code' => 'CREATE_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>