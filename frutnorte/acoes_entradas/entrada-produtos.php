<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: login.php");
    exit;
}

// ==================== CONEXÃO SISTEMA ====================
require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$idcliente = $_SESSION['empresa_id'];

// ========================================
// SISTEMA DE ENTRADA DE PRODUTOS/DEVOLUÇÃO - VERSÃO CORRIGIDA
// CORREÇÃO: Tabela sittrib e logs detalhados + TABELA MELHORADA + FRETE CORRIGIDO
// CORREÇÃO ADICIONAL: Integração com tabela 'condicoes' para cálculo de parcelas em contas a pagar
// ========================================

// Inicialização de variáveis
$mensagem = '';
$tipo_mensagem = 'success';
$nfe_data = null;

function conectarBanco() {
    global $pdo; // Usa a conexão global da classe Database
    return $pdo;
}

// API para buscar fornecedores
// API para buscar fornecedores
if (isset($_GET['action']) && $_GET['action'] === 'search_suppliers') {
    header('Content-Type: application/json');
    $term = $_GET['term'] ?? '';
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcliente, Nome, Fantasia, cnpj_cpf, Cidade, Uf 
            FROM clientes 
            WHERE tipocliente = '3' 
            AND idcliente = ? 
            AND (codcliente LIKE ? OR Nome LIKE ? OR Fantasia LIKE ?) 
            AND ativo = 'S'
            ORDER BY Nome 
            LIMIT 10
        ");
        $searchTerm = "%{$term}%";
        $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($suppliers);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar transportadoras (clientes com transportador = 'S')
// API para buscar transportadoras (clientes com transportador = 'S')
if (isset($_GET['action']) && $_GET['action'] === 'search_transporters') {
    header('Content-Type: application/json');
    $term = $_GET['term'] ?? '';
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcliente, Nome, Fantasia, cnpj_cpf, Cidade, Uf 
            FROM clientes 
            WHERE transportador = 'S' 
            AND idcliente = ?
            AND (codcliente LIKE ? OR Nome LIKE ? OR Fantasia LIKE ?) 
            AND ativo = 'S'
            ORDER BY Nome 
            LIMIT 10
        ");
        $searchTerm = "%{$term}%";
        $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]);
        $transporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($transporters);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar produtos
if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
    header('Content-Type: application/json');
    $term = $_GET['term'] ?? '';
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codproduto, nome, descricao_reduzida, Un, Vrunit, NCM, ativo
            FROM produtos 
            WHERE idcliente = ?
            AND ativo = 'S' 
            AND (codproduto LIKE ? OR nome LIKE ? OR descricao_reduzida LIKE ?) 
            ORDER BY nome 
            LIMIT 20
        ");
        $searchTerm = "%{$term}%";
        $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($products);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar centros de custo
if (isset($_GET['action']) && $_GET['action'] === 'search_cost_centers') {
    header('Content-Type: application/json');
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codigo as codcc, descricao 
            FROM centro_custo 
            WHERE idcliente = ?
            AND ativo = 'S'
            ORDER BY descricao
        ");
        $stmt->execute([$idcliente]);
        $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($costCenters);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar tipos de pagamento
if (isset($_GET['action']) && $_GET['action'] === 'search_payment_types') {
    header('Content-Type: application/json');
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codtppag, Descricao 
            FROM tipopagamentos 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($paymentTypes);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar condições de pagamento
if (isset($_GET['action']) && $_GET['action'] === 'search_conditions') {
    header('Content-Type: application/json');
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codcond, Descricao 
            FROM condicoes 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($conditions);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para buscar tipos de despesa
if (isset($_GET['action']) && $_GET['action'] === 'search_expense_types') {
    header('Content-Type: application/json');
    global $idcliente;
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT codtpdes, Descricao 
            FROM tipodespesas 
            WHERE idcliente = ?
            ORDER BY Descricao
        ");
        $stmt->execute([$idcliente]);
        $expenseTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($expenseTypes);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// API para calcular parcelas de preview (para modal) - MOVIDO PARA O INÍCIO
if (isset($_GET['action']) && $_GET['action'] === 'calculate_parcelas') {
    header('Content-Type: application/json');
    
    // Validação básica
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Método não permitido. Use POST.']);
        exit;
    }
    
    $total = (float)($_POST['total_geral'] ?? 0);
    $codcond = $_POST['payment_condition'] ?? null;
    $dataEmissao = $_POST['dt_emissao'] ?? date('Y-m-d');
    global $idcliente;
    
    if ($total <= 0) {
        echo json_encode(['error' => 'Total geral deve ser maior que zero.']);
        exit;
    }
    
    // Prepara dados para a função
    $dadosEntrada = [
        'total_geral' => $total,
        'payment_condition' => $codcond,
        'dt_emissao' => $dataEmissao,
        'idcliente' => $idcliente // Passa idcliente para a função
    ];
    
    $parcelas = calcularParcelasContasPagar($dadosEntrada);
    
    // Se codcond existe mas parcelas=0 (à vista), retorna uma "parcela" fictícia para preview
    if ($codcond && empty($parcelas)) {
        $parcelas = [[
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'valor_parcela' => $total,
            'data_vencimento' => $dataEmissao,
            'status' => 'PAGO', // Fictício para preview
            'tipo' => 'A_VISTA'
        ]];
    }
    
    // Log para debug (opcional, remova depois)
    error_log("API calculate_parcelas chamada: total={$total}, codcond={$codcond}, data={$dataEmissao}, idcliente={$idcliente}, parcelas=" . print_r($parcelas, true));
    
    echo json_encode($parcelas);
    exit;
}

function produtoExiste($codigo, $idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT codproduto FROM produtos WHERE codproduto = ? AND idcliente = ?");
        $stmt->execute([$codigo, $idcliente]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado;
    } catch (Exception $e) {
        error_log("Erro em produtoExiste (idcliente={$idcliente}): " . $e->getMessage());
        return false;
    }
}

function obterProximoCodigoProduto($idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codproduto) as max_cod FROM produtos WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Erro em obterProximoCodigoProduto (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

function situacaoTributariaExiste($codst, $idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT codst FROM sittrib WHERE codst = ? AND idcliente = ?");
        $stmt->execute([$codst, $idcliente]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado;
    } catch (Exception $e) {
        error_log("Erro em situacaoTributariaExiste (idcliente={$idcliente}): " . $e->getMessage());
        return false;
    }
}

function obterProximoCodigoSituacaoTributaria($idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codst) as max_cod FROM sittrib WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $proximoCodigo = ($result['max_cod'] ?? 0) + 1;
        
        return $proximoCodigo;
    } catch (Exception $e) {
        error_log("Erro em obterProximoCodigoSituacaoTributaria (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

function cadastrarSituacaoTributaria($dadosProduto, $idcliente) {
    try {
        $pdo = conectarBanco();

        // Usar situação tributária do ICMS como código base, ou gerar o próximo
        $codst = !empty($dadosProduto['icms']['situacao_tributaria']) 
            ? $dadosProduto['icms']['situacao_tributaria'] 
            : obterProximoCodigoSituacaoTributaria($idcliente);

        // Verificar se já existe
        $jaExiste = situacaoTributariaExiste($codst, $idcliente);
        if ($jaExiste) {
            return [
                'success' => true,
                'message' => 'Situação tributária já existe',
                'codst' => $codst,
                'action' => 'existing'
            ];
        }

        $dados = [
            'idcliente' => $idcliente,
            'codst' => $codst,
            'sticms' => $dadosProduto['icms']['situacao_tributaria'] ?? '000',
            'percicms' => $dadosProduto['icms']['aliquota'] ?? 0,
            'stipi' => $dadosProduto['ipi']['situacao_tributaria'] ?? '',
            'percipi' => $dadosProduto['ipi']['aliquota'] ?? 0,
            'stpis' => $dadosProduto['pis']['situacao_tributaria'] ?? '',
            'percpis' => $dadosProduto['pis']['aliquota'] ?? 0,
            'stcofins' => $dadosProduto['cofins']['situacao_tributaria'] ?? '',
            'perccofins' => $dadosProduto['cofins']['aliquota'] ?? 0,
            'codbeneficio' => '',
            'perc_red_bc' => 0,
            'pdif' => 0,
            'obs_nfe' => 'Cadastrado automaticamente via importação NFE'
        ];

        $campos = implode(', ', array_keys($dados));
        $placeholders = ':' . implode(', :', array_keys($dados));

        $sql = "INSERT INTO sittrib ({$campos}) VALUES ({$placeholders})";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        return [
            'success' => true,
            'message' => 'Situação tributária cadastrada com sucesso',
            'codst' => $codst,
            'action' => 'created'
        ];

    } catch (Exception $e) {
        error_log("Erro em cadastrarSituacaoTributaria (idcliente={$idcliente}): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao cadastrar situação tributária: ' . $e->getMessage(),
            'codst' => null,
            'action' => 'error'
        ];
    }
}

function cadastrarProduto($dadosProduto, $idcliente) {
    try {
            error_log("=== INICIANDO CADASTRO DE PRODUTO (idcliente={$idcliente}) ===");
    error_log("Dados recebidos: " . print_r($dadosProduto, true));
    
    $pdo = conectarBanco();
    
    // SEMPRE TENTAR CADASTRAR SITUAÇÃO TRIBUTÁRIA PRIMEIRO
    $resultadoSituacao = cadastrarSituacaoTributaria($dadosProduto, $idcliente);
    $codst = $resultadoSituacao['codst'] ?? 1;
    
    error_log("Situação tributária: " . print_r($resultadoSituacao, true));
    
    // **CORREÇÃO: Verificar se o código do produto é válido**
    $codigoProduto = $dadosProduto['codigo'];
    error_log("Código do produto original: " . $codigoProduto);
    
    // Se código estiver vazio ou inválido, gerar próximo código
    if (empty($codigoProduto) || !is_numeric($codigoProduto) || $codigoProduto == '0') {
        $codigoProduto = obterProximoCodigoProduto($idcliente);
        error_log("Código gerado automaticamente: " . $codigoProduto);
    }
    
    // **CORREÇÃO: Verificar se produto já existe de forma mais ampla**
    $produtoExistente = produtoExiste($codigoProduto, $idcliente);
    error_log("Produto existe? " . print_r($produtoExistente, true));
    
    if ($produtoExistente) {
        error_log("Produto já cadastrado - Código: " . $codigoProduto);
        return [
            'success' => true,
            'message' => 'Produto já cadastrado',
            'codproduto' => $codigoProduto,
            'action' => 'existing',
            'situacao_tributaria' => $resultadoSituacao
        ];
    }
    
    // DATA ATUAL - HOJE
    $dataAtual = date('Y-m-d');
    
    $dados = [
        'idcliente' => $idcliente,
        'codproduto' => $codigoProduto,
        'nome' => $dadosProduto['descricao'],
        'descricao_reduzida' => substr($dadosProduto['descricao'], 0, 30),
        'Un' => $dadosProduto['unidade'] ?? 'UN',
        'Vrunit' => $dadosProduto['valor_unitario'] ?? 0,
        'Frete' => 0,
        'custototal' => $dadosProduto['valor_unitario'] ?? 0,
        'perc_mb' => 0,
        'Vrvenda' => $dadosProduto['valor_unitario'] ?? 0,
        'perc_avista' => 0,
        'Vravista' => $dadosProduto['valor_unitario'] ?? 0,
        'Pesobruto' => 0,
        'Pesoliquido' => 1,
        'estoque' => 'S',
        'estoqueminimo' => 0,
        'saldo_estoque' => 0,
        'variedade' => '',
        'codgrupo' => null,
        'CodigoBarra' => $dadosProduto['ean'] ?? '',
        'NP' => 'N',
        'codst' => $codst,
        'NCM' => $dadosProduto['ncm'] ?? '',
        'MAXDESC' => 0,
        'MINPRECO' => 0,
        'descricao_add_nfe' => null,
        'cest' => $dadosProduto['cest'] ?? '',
        'un_trib' => $dadosProduto['unidade'] ?? 'UN',
        'conversor_trib' => 1,
        'beneficio' => '',
        'origem' => 0,
        'descpromocao' => 0,
        'vrpromocao' => 0,
        'envia_site' => 'N',
        'foto' => null,
        'ativo' => 'S',
        'promocao' => 'N',
        'lote' => 'S',
        'data_cad' => $dataAtual
    ];
    
    error_log("Dados para inserção: " . print_r($dados, true));
    
    $campos = implode(', ', array_keys($dados));
    $placeholders = ':' . implode(', :', array_keys($dados));
    
    $sql = "INSERT INTO produtos ({$campos}) VALUES ({$placeholders})";
    error_log("SQL: " . $sql);
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute($dados);
    
    error_log("Resultado da inserção: " . ($resultado ? 'SUCESSO' : 'FALHA'));
    
    if ($resultado) {
        $mensagem = "Produto cadastrado com sucesso - Código: " . $codigoProduto;
        error_log($mensagem);
        return [
            'success' => true,
            'message' => $mensagem,
            'codproduto' => $codigoProduto,
            'action' => 'created',
            'data_cad' => $dataAtual,
            'situacao_tributaria' => $resultadoSituacao
        ];
    } else {
        throw new Exception("Falha na execução do INSERT");
    }
    
    } catch (Exception $e) {
        error_log("ERRO NO CADASTRO DO PRODUTO (idcliente={$idcliente}): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao cadastrar produto: ' . $e->getMessage(),
            'codproduto' => $dadosProduto['codigo'] ?? null,
            'action' => 'error'
        ];
    }
}

function fornecedorExiste($cnpj, $idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT idcliente, codcliente FROM clientes WHERE cnpj_cpf = ? AND tipocliente = '3' AND idcliente = ?");
        $stmt->execute([$cnpj, $idcliente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro em fornecedorExiste (idcliente={$idcliente}): " . $e->getMessage());
        return false;
    }
}

function transportadoraExiste($cnpj, $idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT idcliente, codcliente FROM clientes WHERE cnpj_cpf = ? AND transportador = 'S' AND idcliente = ?");
        $stmt->execute([$cnpj, $idcliente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro em transportadoraExiste (idcliente={$idcliente}): " . $e->getMessage());
        return false;
    }
}

function obterProximoCodigoCliente($idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codcliente) as max_cod FROM clientes WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Erro em obterProximoCodigoCliente (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

function obterProximoCodigoEntrada($idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codentrada) as max_cod FROM entradas WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Erro em obterProximoCodigoEntrada (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
    }
}

function cadastrarFornecedor($dadosNFE, $idcliente) {
    try {
        $pdo = conectarBanco();
        
        $fornecedorExistente = fornecedorExiste($dadosNFE['fornecedor_cnpj'], $idcliente);
        if ($fornecedorExistente) {
            return [
                'success' => true,
                'message' => 'Fornecedor já cadastrado',
                'idcliente' => $fornecedorExistente['idcliente'],
                'codcliente' => $fornecedorExistente['codcliente']
            ];
        }
        
        $proximoCodigo = obterProximoCodigoCliente($idcliente);
        
        $dados = [
            'idcliente' => $idcliente,
            'codcliente' => $proximoCodigo,
            'cnpj_cpf' => $dadosNFE['fornecedor_cnpj'],
            'Nome' => $dadosNFE['fornecedor_nome'],
            'Fantasia' => $dadosNFE['fornecedor_fantasia'] ?? '',
            'Fone' => $dadosNFE['fornecedor_endereco']['telefone'] ?? '',
            'IE' => $dadosNFE['fornecedor_ie'],
            'Data_cad' => date('Y-m-d'),
            'CEP' => $dadosNFE['fornecedor_endereco']['cep'] ?? '',
            'Endereco' => $dadosNFE['fornecedor_endereco']['logradouro'] ?? '',
            'numero' => $dadosNFE['fornecedor_endereco']['numero'] ?? '',
            'Bairro' => $dadosNFE['fornecedor_endereco']['bairro'] ?? '',
            'Cidade' => $dadosNFE['fornecedor_endereco']['municipio'] ?? '',
            'Uf' => $dadosNFE['fornecedor_endereco']['uf'] ?? '',
            'tipocliente' => '3',
            'ativo' => 'S',
            'tipo_pessoa' => 'J',
            'pais' => 'BRASIL'
        ];
        
        $campos = implode(', ', array_keys($dados));
        $placeholders = ':' . implode(', :', array_keys($dados));
        
        $sql = "INSERT INTO clientes ({$campos}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute($dados);
        $id_insert = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Fornecedor cadastrado com sucesso',
            'idcliente' => $id_insert,
            'codcliente' => $proximoCodigo
        ];
        
    } catch (Exception $e) {
        error_log("Erro em cadastrarFornecedor (idcliente={$idcliente}): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao cadastrar fornecedor: ' . $e->getMessage()
        ];
    }
}

function cadastrarTransportadora($dadosTransportadora, $idcliente) {
    try {
        $pdo = conectarBanco();
        
        $transportadoraExistente = transportadoraExiste($dadosTransportadora['cnpj'], $idcliente);
        if ($transportadoraExistente) {
            return [
                'success' => true,
                'message' => 'Transportadora já cadastrada',
                'idcliente' => $transportadoraExistente['idcliente'],
                'codcliente' => $transportadoraExistente['codcliente']
            ];
        }
        
        $proximoCodigo = obterProximoCodigoCliente($idcliente);
        
        $dados = [
            'idcliente' => $idcliente,
            'codcliente' => $proximoCodigo,
            'cnpj_cpf' => $dadosTransportadora['cnpj'],
            'Nome' => $dadosTransportadora['nome'],
            'Fantasia' => $dadosTransportadora['nome'],
            'IE' => $dadosTransportadora['ie'] ?? '',
            'Data_cad' => date('Y-m-d'),
            'Endereco' => $dadosTransportadora['endereco'] ?? '',
            'Cidade' => $dadosTransportadora['municipio'] ?? '',
            'Uf' => $dadosTransportadora['uf'] ?? '',
            'tipocliente' => '1',
            'transportador' => 'S',
            'ativo' => 'S',
            'tipo_pessoa' => 'J',
            'pais' => 'BRASIL'
        ];
        
        $campos = implode(', ', array_keys($dados));
        $placeholders = ':' . implode(', :', array_keys($dados));
        
        $sql = "INSERT INTO clientes ({$campos}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute($dados);
        $id_insert = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Transportadora cadastrada com sucesso',
            'idcliente' => $id_insert,
            'codcliente' => $proximoCodigo
        ];
        
    } catch (Exception $e) {
        error_log("Erro em cadastrarTransportadora (idcliente={$idcliente}): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao cadastrar transportadora: ' . $e->getMessage()
        ];
    }
}

function processarNFE($xmlFile) {
    if (!file_exists($xmlFile)) {
        throw new Exception("Arquivo XML não encontrado.");
    }
    
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        throw new Exception("Erro ao ler o arquivo XML.");
    }
    
    $namespaces = $xml->getNamespaces(true);
    
    $infNFe = $xml->NFe->infNFe;
    $emit = $infNFe->emit;
    $ide = $infNFe->ide;
    $dest = $infNFe->dest ?? null;
    $total = $infNFe->total;
    $transp = $infNFe->transp ?? null;
    $cobr = $infNFe->cobr ?? null;
    $pag = $infNFe->pag ?? null;
    
    $chaveAcesso = (string)$infNFe['Id'];
    $chaveAcesso = str_replace('NFe', '', $chaveAcesso);
    
    $nfeData = [
        'chave_acesso' => $chaveAcesso,
        'numero_nf' => (string)$ide->nNF,
        'serie_nf' => (string)$ide->serie,
        'data_emissao' => date('Y-m-d', strtotime((string)$ide->dhEmi)),
        'cfop' => (string)$ide->cNF,
        'modelo' => (string)$ide->mod,
        'natureza_operacao' => (string)$ide->natOp,
        
        'fornecedor_cnpj' => (string)$emit->CNPJ,
        'fornecedor_nome' => (string)$emit->xNome,
        'fornecedor_fantasia' => (string)$emit->xFant,
        'fornecedor_ie' => (string)$emit->IE,
        'fornecedor_endereco' => [
            'logradouro' => (string)$emit->enderEmit->xLgr,
            'numero' => (string)$emit->enderEmit->nro,
            'bairro' => (string)$emit->enderEmit->xBairro,
            'municipio' => (string)$emit->enderEmit->xMun,
            'uf' => (string)$emit->enderEmit->UF,
            'cep' => (string)$emit->enderEmit->CEP,
            'telefone' => (string)$emit->fone ?? '',
        ],
        
        'destinatario' => $dest ? [
            'cnpj' => (string)$dest->CNPJ,
            'nome' => (string)$dest->xNome,
            'ie' => (string)$dest->IE,
        ] : null,
        
        'totais' => [
            'valor_produtos' => (float)$total->ICMSTot->vProd,
            'valor_frete' => (float)$total->ICMSTot->vFrete,
            'valor_seguro' => (float)$total->ICMSTot->vSeg,
            'valor_desconto' => (float)$total->ICMSTot->vDesc,
            'valor_total' => (float)$total->ICMSTot->vNF,
            'valor_icms' => (float)$total->ICMSTot->vICMS,
            'valor_ipi' => (float)$total->ICMSTot->vIPI,
            'valor_pis' => (float)$total->ICMSTot->vPIS,
            'valor_cofins' => (float)$total->ICMSTot->vCOFINS,
        ],
        
        'transportadora' => $transp ? [
            'modalidade_frete' => (string)$transp->modFrete,
            'transportadora_cnpj' => (string)($transp->transporta->CNPJ ?? ''),
            'transportadora_nome' => (string)($transp->transporta->xNome ?? ''),
            'transportadora_ie' => (string)($transp->transporta->IE ?? ''),
            'transportadora_endereco' => (string)($transp->transporta->xEnder ?? ''),
            'transportadora_municipio' => (string)($transp->transporta->xMun ?? ''),
            'transportadora_uf' => (string)($transp->transporta->UF ?? ''),
            'veiculo_placa' => (string)($transp->veicTransp->placa ?? ''),
            'veiculo_uf' => (string)($transp->veicTransp->UF ?? ''),
            'volumes' => [
                'quantidade' => (string)($transp->vol->qVol ?? ''),
                'especie' => (string)($transp->vol->esp ?? ''),
                'peso_liquido' => (string)($transp->vol->pesoL ?? ''),
                'peso_bruto' => (string)($transp->vol->pesoB ?? ''),
            ]
        ] : null,
        
        'cobranca' => $cobr ? [
            'fatura_numero' => (string)($cobr->fat->nFat ?? ''),
            'fatura_valor_original' => (float)($cobr->fat->vOrig ?? 0),
            'fatura_valor_desconto' => (float)($cobr->fat->vDesc ?? 0),
            'fatura_valor_liquido' => (float)($cobr->fat->vLiq ?? 0),
            'duplicatas' => []
        ] : null,
        
        'pagamento' => $pag ? [
            'tipo_pagamento' => (string)($pag->detPag->tPag ?? ''),
                        'valor_pagamento' => (float)($pag->detPag->vPag ?? 0),
        ] : null,
        
        'produtos' => []
    ];
    
    // Processar duplicatas se existirem
    if ($cobr && isset($cobr->dup)) {
        foreach ($cobr->dup as $dup) {
            $nfeData['cobranca']['duplicatas'][] = [
                'numero' => (string)$dup->nDup,
                'vencimento' => (string)$dup->dVenc,
                'valor' => (float)$dup->vDup,
            ];
        }
    }
    
    if (isset($infNFe->det)) {
        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            $imposto = $det->imposto;
            
            $produto = [
                'codigo' => (string)$prod->cProd,
                'ean' => (string)$prod->cEAN,
                'descricao' => (string)$prod->xProd,
                'ncm' => (string)$prod->NCM,
                'cest' => (string)($prod->CEST ?? ''),
                'cfop' => (string)$prod->CFOP,
                'unidade' => (string)$prod->uCom,
                'quantidade' => (float)$prod->qCom,
                'valor_unitario' => (float)$prod->vUnCom,
                'valor_total' => (float)$prod->vProd,
                
                'icms' => [
                    'situacao_tributaria' => (string)($imposto->ICMS->ICMS00->CST ?? $imposto->ICMS->ICMS10->CST ?? $imposto->ICMS->ICMS20->CST ?? ''),
                    'base_calculo' => (float)($imposto->ICMS->ICMS00->vBC ?? $imposto->ICMS->ICMS10->vBC ?? $imposto->ICMS->ICMS20->vBC ?? 0),
                    'aliquota' => (float)($imposto->ICMS->ICMS00->pICMS ?? $imposto->ICMS->ICMS10->pICMS ?? $imposto->ICMS->ICMS20->pICMS ?? 0),
                    'valor' => (float)($imposto->ICMS->ICMS00->vICMS ?? $imposto->ICMS->ICMS10->vICMS ?? $imposto->ICMS->ICMS20->vICMS ?? 0),
                ],
                
                'pis' => [
                    'situacao_tributaria' => (string)($imposto->PIS->PISAliq->CST ?? $imposto->PIS->PISNT->CST ?? ''),
                    'base_calculo' => (float)($imposto->PIS->PISAliq->vBC ?? 0),
                    'aliquota' => (float)($imposto->PIS->PISAliq->pPIS ?? 0),
                    'valor' => (float)($imposto->PIS->PISAliq->vPIS ?? 0),
                ],
                
                'cofins' => [
                    'situacao_tributaria' => (string)($imposto->COFINS->COFINSAliq->CST ?? $imposto->COFINS->COFINSNT->CST ?? ''),
                    'base_calculo' => (float)($imposto->COFINS->COFINSAliq->vBC ?? 0),
                    'aliquota' => (float)($imposto->COFINS->COFINSAliq->pCOFINS ?? 0),
                    'valor' => (float)($imposto->COFINS->COFINSAliq->vCOFINS ?? 0),
                ],
                
                'ipi' => [
                    'situacao_tributaria' => (string)($imposto->IPI->IPITrib->CST ?? $imposto->IPI->IPINT->CST ?? ''),
                    'base_calculo' => (float)($imposto->IPI->IPITrib->vBC ?? 0),
                    'aliquota' => (float)($imposto->IPI->IPITrib->pIPI ?? 0),
                    'valor' => (float)($imposto->IPI->IPITrib->vIPI ?? 0),
                ],
            ];
            
            $nfeData['produtos'][] = $produto;
        }
    }
    
    return $nfeData;
}

// Processar upload de NFE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['nfe_file'])) {
    
    try {
        global $idcliente;
        
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadFile = $uploadDir . basename($_FILES['nfe_file']['name']);
        
        if (move_uploaded_file($_FILES['nfe_file']['tmp_name'], $uploadFile)) {
            
            $nfe_data = processarNFE($uploadFile);
            
            $resultadoCadastro = cadastrarFornecedor($nfe_data, $idcliente);
            
            if ($resultadoCadastro['success']) {
                $nfe_data['fornecedor_id'] = $resultadoCadastro['idcliente'];
                $nfe_data['fornecedor_codigo'] = $resultadoCadastro['codcliente'];
                
                // CADASTRAR PRODUTOS AUTOMATICAMENTE COM DATA ATUAL
                $produtosCadastrados = 0;
                $produtosExistentes = 0;
                $produtosComErro = 0;
                $situacoesCriadas = 0;
                $situacoesExistentes = 0;
                
                foreach ($nfe_data['produtos'] as $index => $produto) {
                    $resultadoProduto = cadastrarProduto($produto, $idcliente);
                    
                    if ($resultadoProduto['success']) {
                        // Atualizar código do produto caso tenha sido alterado
                        $nfe_data['produtos'][$index]['codigo'] = $resultadoProduto['codproduto'];
                        
                        if ($resultadoProduto['action'] === 'created') {
                            $produtosCadastrados++;
                        } elseif ($resultadoProduto['action'] === 'existing') {
                            $produtosExistentes++;
                        }
                        
                        // Contar situações tributárias
                        if (isset($resultadoProduto['situacao_tributaria'])) {
                            if ($resultadoProduto['situacao_tributaria']['action'] === 'created') {
                                $situacoesCriadas++;
                            } elseif ($resultadoProduto['situacao_tributaria']['action'] === 'existing') {
                                $situacoesExistentes++;
                            }
                        }
                    } else {
                        $produtosComErro++;
                    }
                }
                
                if ($nfe_data['transportadora'] && !empty($nfe_data['transportadora']['transportadora_cnpj'])) {
                    $dadosTransportadora = [
                        'cnpj' => $nfe_data['transportadora']['transportadora_cnpj'],
                        'nome' => $nfe_data['transportadora']['transportadora_nome'],
                        'ie' => $nfe_data['transportadora']['transportadora_ie'],
                        'endereco' => $nfe_data['transportadora']['transportadora_endereco'],
                        'municipio' => $nfe_data['transportadora']['transportadora_municipio'],
                        'uf' => $nfe_data['transportadora']['transportadora_uf']
                    ];
                    
                    $resultadoTransportadora = cadastrarTransportadora($dadosTransportadora, $idcliente);
                    
                    if ($resultadoTransportadora['success']) {
                        $nfe_data['transportadora_id'] = $resultadoTransportadora['idcliente'];
                        $nfe_data['transportadora_codigo'] = $resultadoTransportadora['codcliente'];
                    }
                }
                
                // Construir mensagem de sucesso com data atual
                $dataAtual = date('d/m/Y');
                if (strpos($resultadoCadastro['message'], 'já cadastrado') !== false) {
                    $mensagem = "NFE importada com sucesso em {$dataAtual}! Fornecedor já estava cadastrado (Código: {$resultadoCadastro['codcliente']}).";
                } else {
                    $mensagem = "NFE importada com sucesso em {$dataAtual}! Fornecedor cadastrado automaticamente (Código: {$resultadoCadastro['codcliente']}).";
                }
                
                // Adicionar informações sobre produtos
                if ($produtosCadastrados > 0) {
                    $mensagem .= " {$produtosCadastrados} produto(s) cadastrado(s) automaticamente em {$dataAtual}.";
                }
                if ($situacoesCriadas > 0) {
                    $mensagem .= " {$situacoesCriadas} situação(ões) tributária(s) criada(s).";
                }
                if ($situacoesExistentes > 0) {
                    $mensagem .= " {$situacoesExistentes} situação(ões) tributária(s) já existia(m).";
                }
                if ($produtosExistentes > 0) {
                    $mensagem .= " {$produtosExistentes} produto(s) já existia(m) no sistema.";
                }
                if ($produtosComErro > 0) {
                    $mensagem .= " {$produtosComErro} produto(s) com erro no cadastro.";
                }
                
                if (isset($nfe_data['transportadora_codigo'])) {
                    if (isset($resultadoTransportadora) && strpos($resultadoTransportadora['message'], 'já cadastrada') !== false) {
                        $mensagem .= " Transportadora já estava cadastrada (Código: {$nfe_data['transportadora_codigo']}).";
                    } else {
                        $mensagem .= " Transportadora cadastrada automaticamente (Código: {$nfe_data['transportadora_codigo']}).";
                    }
                }
                
                $tipo_mensagem = 'success';
            } else {
                $mensagem = "NFE importada com sucesso, mas houve erro ao cadastrar fornecedor: " . $resultadoCadastro['message'];
                $tipo_mensagem = 'warning';
            }
        } else {
            throw new Exception("Erro ao fazer upload do arquivo.");
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao importar NFE: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Processar envio do formulário principal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_FILES['nfe_file']) && isset($_POST['action']) && $_POST['action'] == 'save_entrada') {
    try {
        global $idcliente;
        $pdo = conectarBanco();
        $pdo->beginTransaction();
        
        // Extrair variáveis do POST que estavam indefinidas no original
        $codtpdes = !empty($_POST['codtpdes']) ? (int)$_POST['codtpdes'] : null;
        $codcond = !empty($_POST['codcond']) ? (int)$_POST['codcond'] : null;
        $codtppag = !empty($_POST['codtppag']) ? (int)$_POST['codtppag'] : null;
        
        // Obter próximo código de entrada - CORREÇÃO AQUI
        if (!empty($_POST['entrada_numero'])) {
            // Se já existe um número no POST, usar ele (para evitar duplicação)
            $codentrada = (int)$_POST['entrada_numero'];
        } else {
            // Se não existe, gerar novo código
            $codentrada = obterProximoCodigoEntrada($idcliente);
        }
        
        // Calcular total do frete (frete dos produtos + valor do frete adicional)
        $total_frete_produtos = 0;
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            foreach ($_POST['produtos'] as $produto) {
                $freteUnitario = (float)($produto['frete_unitario'] ?? 0);
                $quantidade = (float)($produto['quantidade'] ?? 0);
                $total_frete_produtos += $freteUnitario * $quantidade;
            }
        }
        $valor_frete_adicional = (float)($_POST['valor_frete'] ?? 0);
        $total_frete = $total_frete_produtos + $valor_frete_adicional;
        
        // Tratar campo codtransp - se vazio, definir como NULL
        $codtransp = null;
        if (!empty($_POST['transporter_id'])) {
            $codtransp = (int)$_POST['transporter_id'];
        }
        
        // Inserir entrada principal
        $dadosEntrada = [
            'idcliente' => $idcliente, // ID dinâmico da empresa
            'codempresa' => 1,
            'codentrada' => $codentrada,
            'serienota' => $_POST['serie_nf'] ?? '1',
            'numeronota' => $_POST['nf_numero'] ?? null,
            'Codcliente' => $_POST['supplier_id'] ?? null,
            'Dataentrada' => $_POST['data_entrada'],
            'Pedido' => $_POST['pedido'] ?? null,
            'tipooperacao' => isset($_POST['devolucao']) && $_POST['devolucao'] == 'on' ? 'D' : 'E',
            'vricms' => $_POST['total_icms'] ?? 0,
            'vripi' => $_POST['total_ipi'] ?? 0,
            'vrdesconto' => $_POST['total_desconto'] ?? 0,
            'vrprodutos' => $_POST['total_produtos'] ?? 0,
            'vrTotal' => $_POST['total_geral'] ?? 0,
            'obs' => $_POST['observacoes_gerais'] ?? '',
            'ano_safra' => $_POST['ano_safra'] ?? '',
            'cancelado' => 'N',
            'serieentrada' => $_POST['serie_nf'] ?? '',
            'notaentrada' => $_POST['nf_entrada'] ?? '',
            'placa' => $_POST['placa_veiculo'] ?? '',
            'codtransp' => $codtransp, // Agora pode ser NULL
            'total_frete' => $total_frete, // Total do frete calculado
            'dtnota' => $_POST['dt_emissao'] ?? null,
            'tipo_emitente' => 'T',
            'NumChaveNfe' => $_POST['chave_acesso'] ?? '',
            'nitens' => count($_POST['produtos'] ?? []),
            'nitenstot' => count($_POST['produtos'] ?? []),
            'total_nfe' => $_POST['total_geral'] ?? 0,
            'insumos' => 'N',
            'codmotorista' => null,
            'codtpdes' => $codtpdes, // Tipo de despesa (pode ser NULL)
            'codcond' => $codcond, // Condição de pagamento (pode ser NULL)
            'codtppag' => $codtppag // Tipo de pagamento (pode ser NULL)
        ];
        
        $camposEntrada = implode(', ', array_keys($dadosEntrada));
        $placeholdersEntrada = ':' . implode(', :', array_keys($dadosEntrada));
        
        $sqlEntrada = "INSERT INTO entradas ({$camposEntrada}) VALUES ({$placeholdersEntrada})";
        $stmtEntrada = $pdo->prepare($sqlEntrada);
        $stmtEntrada->execute($dadosEntrada);
        
        // Inserir itens da entrada
        if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
            $seq = 1;
            foreach ($_POST['produtos'] as $produto) {
                if (empty($produto['codproduto'])) continue;
                
                $dadosItem = [
                    'idcliente' => $idcliente,
                    'codentrada' => $codentrada,
                    'seq' => $seq,
                    'Codproduto' => $produto['codproduto'],
                    'Un' => $produto['unidade'] ?? '',
                    'Qtd' => $produto['quantidade'] ?? 0,
                    'Vrunit' => $produto['valor_unitario'] ?? 0,
                    'Total' => $produto['total'] ?? 0,
                    'estoque' => $produto['estoque'] ?? 'S',
                    'vrdesconto' => $produto['desconto'] ?? 0,
                    'cfop' => $produto['cfop'] ?? '',
                    'freteunit' => $produto['frete_unitario'] ?? 0,
                    'stipi' => $produto['sit_ipi'] ?? '',
                    'bc_ipi' => $produto['bc_ipi'] ?? 0,
                    'percipi' => $produto['perc_ipi'] ?? 0,
                    'vripi' => $produto['vl_ipi'] ?? 0,
                    'sticms' => $produto['sit_icms'] ?? '',
                    'bc_icms' => $produto['bc_icms'] ?? 0,
                    'percicms' => $produto['perc_icms'] ?? 0,
                    'vricms' => $produto['vl_icms'] ?? 0,
                    'bc_icms_st' => $produto['bc_icms_st'] ?? 0,
                    'vl_icms_st' => $produto['vl_icms_st'] ?? 0,
                    'stpis' => $produto['sit_pis'] ?? '',
                    'bc_pis' => $produto['bc_pis'] ?? 0,
                    'ppis' => $produto['perc_pis'] ?? 0,
                    'vl_pis' => $produto['vl_pis'] ?? 0,
                    'stcofins' => $produto['sit_cofins'] ?? '',
                    'bc_cofins' => $produto['bc_cofins'] ?? 0,
                    'pcofins' => $produto['perc_cofins'] ?? 0,
                    'vl_cofins' => $produto['vl_cofins'] ?? 0,
                    'imp_di_num' => '',
                    'imp_di_data' => '2012-01-01',
                    'imp_desem_local' => 'NOME DA CIDADE',
                    'imp_desem_uf' => 'PR',
                    'imp_desem_data' => '2012-01-01',
                    'imp_adicao_num' => '0',
                    'imp_adicao_valor' => 0,
                    'imp_pedcom_num' => '0',
                    'imp_pedcom_item' => 0,
                    'imp_basecalc' => 0,
                    'imp_aliq' => 0,
                    'imp_valor' => 0,
                    'imp_txaduana' => 0,
                                        'imp_iof' => 0
                ];
                
                $camposItem = implode(', ', array_keys($dadosItem));
                $placeholdersItem = ':' . implode(', :', array_keys($dadosItem));
                
                $sqlItem = "INSERT INTO entradas_itens ({$camposItem}) VALUES ({$placeholdersItem})";
                $stmtItem = $pdo->prepare($sqlItem);
                $stmtItem->execute($dadosItem);
                
                $seq++;
            }
        }
        
        // Inserir centros de custo
        if (isset($_POST['cost_centers']) && is_array($_POST['cost_centers'])) {
            foreach ($_POST['cost_centers'] as $index => $cc) {
                if (empty($cc['codcc'])) continue;
                
                $dadosCC = [
                    'idcliente' => $idcliente,
                    'codentrada' => $codentrada,
                    'codcc' => $cc['codcc'],
                    'placa' => $cc['placa'] ?? '',
                    'valor' => $cc['valor'] ?? 0,
                    'obs' => $cc['obs'] ?? ''
                ];
                
                $camposCC = implode(', ', array_keys($dadosCC));
                $placeholdersCC = ':' . implode(', :', array_keys($dadosCC));
                
                $sqlCC = "INSERT INTO entradas_cc ({$camposCC}) VALUES ({$placeholdersCC})";
                $stmtCC = $pdo->prepare($sqlCC);
                $stmtCC->execute($dadosCC);
            }
        }
        
        // Inserir descontos
        if (isset($_POST['discounts']) && is_array($_POST['discounts'])) {
            $seqDesc = 1;
            foreach ($_POST['discounts'] as $index => $desconto) {
                if (empty($desconto['descricao'])) continue;
                
                $dadosDesconto = [
                    'idcliente' => $idcliente,
                    'codentrada' => $codentrada,
                    'seq' => $seqDesc,
                    'datalcto' => date('Y-m-d'), // DATA ATUAL
                    'descricao' => $desconto['descricao'],
                    'valor' => $desconto['valor'] ?? 0
                ];
                
                $camposDesconto = implode(', ', array_keys($dadosDesconto));
                $placeholdersDesconto = ':' . implode(', :', array_keys($dadosDesconto));
                
                $sqlDesconto = "INSERT INTO entradas_descontos ({$camposDesconto}) VALUES ({$placeholdersDesconto})";
                $stmtDesconto = $pdo->prepare($sqlDesconto);
                $stmtDesconto->execute($dadosDesconto);
                
                $seqDesc++;
            }
        }
        
        $pdo->commit();
        
        // CHAMADA PARA A API DE CONTAS A PAGAR
        $apiResponse = chamarApiContasPagar($codentrada, $_POST, $idcliente);
        
        $dataAtual = date('d/m/Y');
        $mensagem = "Entrada de produtos salva com sucesso em {$dataAtual}! Código: {$codentrada}";
        
        // Adicionar mensagem da API se existir
        if (isset($apiResponse['message'])) {
            $mensagem .= " | " . $apiResponse['message'];
        }
        
        $tipo_mensagem = 'success';

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                // Criar overlay de loading
                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(16, 185, 129, 0.9);
                    color: white;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                `;
                
                overlay.innerHTML = `
                    <div style=\"text-align: center;\">
                        <i class=\"fas fa-check-circle\" style=\"font-size: 48px; margin-bottom: 20px;\"></i>
                        <h2 style=\"margin-bottom: 10px;\">Entrada Salva com Sucesso!</h2>
                        <p style=\"margin-bottom: 20px; opacity: 0.9;\">{$mensagem}</p>
                        <div style=\"display: flex; align-items: center; gap: 10px;\">
                            <div class=\"spinner\" style=\"width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s linear infinite;\"></div>
                            <span>Redirecionando para a lista de entradas...</span>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(overlay);
                
                // Redirecionar após 4 segundos
                setTimeout(function() {
                    window.location.href = '../entradas.php';
                }, 4000);
                
                // Adicionar estilo para o spinner
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes spin {
                        to { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            });
        </script>";
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem = "Erro ao salvar entrada: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

/**
 * Função para chamar a API de contas a pagar - CORREÇÃO PARA DUPLICIDADE
 */
function chamarApiContasPagar($codentrada, $dadosEntrada, $idcliente) {
    try {
        $parcelas = calcularParcelasContasPagar($dadosEntrada, $idcliente);
        error_log("Parcelas calculadas (idcliente={$idcliente}): " . print_r($parcelas, true));
        
        // CORREÇÃO: Se array vazio (à vista ou erro), não criar contas a pagar e retornar mensagem de sucesso
        if (empty($parcelas)) {
            error_log("Nenhuma conta a pagar criada (idcliente={$idcliente}): Pagamento à vista ou condição inválida.");
            return [
                'success' => true, 
                'message' => 'Pagamento à vista: Nenhuma conta a pagar foi criada.',
                'parcelas_criadas' => 0
            ];
        }
        
        $responses = [];
        
        // **CORREÇÃO: GERAR UM ÚNICO IDENTIFICADOR PARA TODAS AS PARCELAS**
        $identificadorUnicoComum = uniqid(); // Gera um ID único para TODAS as parcelas
        
        foreach ($parcelas as $parcela) {
            $numeroNota = $dadosEntrada['nf_numero'] ?? $dadosEntrada['numeronota'] ?? null;
            $serieNota = $dadosEntrada['serie_nf'] ?? $dadosEntrada['serienota'] ?? '1';
            
            // **USAR O MESMO IDENTIFICADOR PARA TODAS AS PARCELAS**
            $observacoes = "NF: {$numeroNota}/{$serieNota} - Parcela {$parcela['numero_parcela']} de {$parcela['total_parcelas']} - Ref: {$identificadorUnicoComum}";
            
            // **ADICIONAR INFORMAÇÕES QUE TORNAM CADA PARCELA ÚNICA**
            $dadosApi = [
                'codentrada' => $codentrada,
                'codcliente' => $dadosEntrada['supplier_id'] ?? null,
                'vrtitulo' => $parcela['valor_parcela'],
                'dataemissao' => $dadosEntrada['dt_emissao'] ?? $dadosEntrada['data_entrada'] ?? date('Y-m-d'),
                'datavencimento' => $parcela['data_vencimento'],
                'datalancamento' => date('Y-m-d'),
                'codtpdes' => !empty($dadosEntrada['codtpdes']) ? (int)$dadosEntrada['codtpdes'] : null,
                'codcond' => !empty($dadosEntrada['codcond']) ? (int)$dadosEntrada['codcond'] : null,
                'codtppag' => !empty($dadosEntrada['codtppag']) ? (int)$dadosEntrada['codtppag'] : null,
                'numeronota' => $numeroNota,
                'serienota' => $serieNota,
                'obs' => $observacoes,
                'ano_safra' => $dadosEntrada['ano_safra'] ?? date('Y'),
                'placa' => $dadosEntrada['placa_veiculo'] ?? '',
                'origem' => 'ENTRADA_PRODUTOS',
                'idcliente' => $idcliente, // Adicionado para a API
                // **CAMPOS QUE TORNAM CADA PARCELA ÚNICA:**
                'numero_parcela' => $parcela['numero_parcela'],
                'total_parcelas' => $parcela['total_parcelas'],
                // **CORREÇÃO: USAR O MESMO IDENTIFICADOR PARA TODAS AS PARCELAS**
                'identificador_unico' => $identificadorUnicoComum,
                'datavencimento_parcela' => $parcela['data_vencimento'] // Data diferente para cada parcela
            ];
            
            error_log("Enviando parcela {$parcela['numero_parcela']} com dados (idcliente={$idcliente}): " . print_r($dadosApi, true));
            
            $url = 'http://localhost/frutnorte/api_contas_pagar/salvar_contas_pagar.php';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosApi, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            error_log("Resposta HTTP Parcela {$parcela['numero_parcela']} (idcliente={$idcliente}): " . $httpCode);
            error_log("Resposta API Parcela {$parcela['numero_parcela']} (idcliente={$idcliente}): " . $response);
            
            curl_close($ch);
            
            if ($httpCode === 201) {
                $dadosResposta = json_decode($response, true);
                $responses[] = [
                    'sucesso' => true,
                    'parcela' => $parcela['numero_parcela'],
                    'mensagem' => $dadosResposta['message'] ?? 'Parcela salva com sucesso',
                    'codpagar' => $dadosResposta['data']['codpagar'] ?? null,
                    'seqcodpagar' => $dadosResposta['data']['seqcodpagar'] ?? null
                ];
            } else {
                $responses[] = [
                    'sucesso' => false,
                    'parcela' => $parcela['numero_parcela'],
                    'mensagem' => 'Erro HTTP ' . $httpCode,
                    'erro' => $response,
                    'http_code' => $httpCode
                ];
            }
        }
        
        // Processar resultados
        $parcelasSalvas = count(array_filter($responses, function($r) { return $r['sucesso']; }));
        
        if ($parcelasSalvas === count($parcelas)) {
            // Verificar se todas as parcelas têm o mesmo codpagar
            $codpagarUnico = null;
            $codpagarDiferentes = [];
            
            foreach ($responses as $response) {
                if ($response['sucesso']) {
                    $codpagar = $response['codpagar'];
                    if ($codpagarUnico === null) {
                        $codpagarUnico = $codpagar;
                    } elseif ($codpagar !== $codpagarUnico) {
                        $codpagarDiferentes[] = $codpagar;
                    }
                }
            }
            
            if (empty($codpagarDiferentes)) {
                return [
                    'success' => true, 
                    'message' => "Todas as {$parcelasSalvas} parcelas criadas com sucesso! (codpagar: {$codpagarUnico})",
                    'codpagar' => $codpagarUnico
                ];
            } else {
                return [
                    'success' => true, 
                    'message' => "Todas as {$parcelasSalvas} parcelas criadas, mas com codpagar diferentes!",
                    'codpagar' => $codpagarUnico,
                    'codpagar_diferentes' => $codpagarDiferentes
                ];
            }
        } else {
            return [
                'success' => false, 
                'message' => "Apenas {$parcelasSalvas} de " . count($parcelas) . " parcelas salvas.",
                'details' => $responses
            ];
        }
        
    } catch (Exception $e) {
        error_log("Exception em chamarApiContasPagar (idcliente={$idcliente}): " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

/**
 * Calcular parcelas baseadas na condição de pagamento da tabela 'condicoes'
 * - Se codcond null/vazio ou Parcelas=0: NÃO cria parcelas (pagamento à vista, retorna array vazio)
 * - Senão: divide em Parcelas iguais, usando CondPgto1 a CondPgtoN como dias de vencimento
 */
function calcularParcelasContasPagar($dadosEntrada, $idcliente) {
    $valorTotal = (float)($dadosEntrada['total_geral'] ?? 0);
    $codcond = $dadosEntrada['payment_condition'] ?? $dadosEntrada['codcond'] ?? null;
    $dataEmissao = $dadosEntrada['dt_emissao'] ?? $dadosEntrada['data_entrada'] ?? date('Y-m-d');
    
    // CORREÇÃO: Se codcond nulo/vazio, tratar como à vista e retornar array vazio (sem contas a pagar)
    if (!$codcond) {
        error_log("Condição de pagamento nula/vazia (idcliente={$idcliente}): Pagamento à vista, sem contas a pagar.");
        return []; // Array vazio: não cria contas a pagar
    }
    
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("
            SELECT Parcelas, CondPgto1, CondPgto2, CondPgto3, CondPgto4, condpgto5, condpgto6 
            FROM condicoes 
            WHERE codcond = ? AND idcliente = ?
        ");
        $stmt->execute([$codcond, $idcliente]);
        $condicao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // CORREÇÃO: Se não encontrado ou Parcelas=0, retornar array vazio (pagamento à vista, sem contas a pagar)
        if (!$condicao || (int)$condicao['Parcelas'] === 0) {
            error_log("Condição {$codcond} tem 0 parcelas ou não encontrada (idcliente={$idcliente}): Pagamento à vista, sem contas a pagar.");
            return []; // Array vazio: não cria contas a pagar
        }
        
        // Prosseguir apenas se Parcelas > 0
        $numParcelas = (int)$condicao['Parcelas'];
        $parcelasCondicao = [];
        
        // Coletar os dias de vencimento dos campos CondPgto1 a CondPgtoN
        for ($i = 1; $i <= $numParcelas; $i++) {
            $campoDias = "CondPgto{$i}";
            $dias = (int)($condicao[$campoDias] ?? 0);
            if ($dias > 0) {
                $parcelasCondicao[] = $dias;
            }
        }
        
        if (empty($parcelasCondicao)) {
            // Se não houver dias definidos, usa parcelas iguais sem dias específicos (vencimento na data de emissão)
            $parcelasCondicao = array_fill(0, $numParcelas, 0);
        }
        
        // Garantir que temos exatamente $numParcelas de dias
        while (count($parcelasCondicao) < $numParcelas) {
            $parcelasCondicao[] = 0; // Default para dias=0 se não definido
        }
        
        $parcelas = [];
        $totalParcelas = $numParcelas;
        $valorParcelaBase = $valorTotal / $totalParcelas;
        
        foreach ($parcelasCondicao as $index => $dias) {
            $numeroParcela = $index + 1;
            $valorParcela = $numeroParcela < $totalParcelas ? $valorParcelaBase : ($valorTotal - ($numeroParcela - 1) * $valorParcelaBase); // Ajusta a última para evitar arredondamento
            $dataVencimento = date('Y-m-d', strtotime($dataEmissao . ' + ' . $dias . ' days'));
            
            $parcelas[] = [
                'numero_parcela' => $numeroParcela,
                'total_parcelas' => $totalParcelas,
                'valor_parcela' => round($valorParcela, 2),
                'data_vencimento' => $dataVencimento
            ];
        }
        
        return $parcelas;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular parcelas (idcliente={$idcliente}): " . $e->getMessage());
        // Em caso de erro, retorna array vazio (sem contas a pagar)
        return [];
    }
}

// Variáveis finais para o template (como no exemplo)
$entrada_numero = str_pad(obterProximoCodigoEntrada($idcliente), 6, '0', STR_PAD_LEFT);
$data_atual = date('d/m/Y');
?>
<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de produtos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ESTILOS CENTRALIZADOS COM BORDAS ARREDONDADAS */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.4;
            min-height: 100vh;
            padding: 20px;
        }

        .main-content {
            padding: 0;
            background: transparent;
        }

        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        /* Header Centralizado com Bordas Arredondadas */
        .page-header-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 20px 32px;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header-compact::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            backdrop-filter: blur(10px);
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
        }

        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        /* Breadcrumb */
        .breadcrumb-compact {
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 32px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            backdrop-filter: blur(10px);
        }

        .remove-btn {
  background: #e74c3c;
  border: none;
  color: white;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.3s;
}

.remove-btn:hover {
  background: #c0392b;
}


        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .breadcrumb-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .breadcrumb-item.active {
            color: white;
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Container Principal */
        .form-container-compact {
            background: white;
            margin: 0;
        }

        /* Seção Superior - Form Básico */
        .top-section-compact {
            padding: 24px 32px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-row-compact {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto auto auto auto;
            gap: 16px;
            align-items: end;
        }

        .form-group-compact {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-label-compact {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .form-input-compact,
        .form-select-compact {
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            background: white;
            transition: all 0.2s ease;
            min-width: 0;
        }

        .form-input-compact:focus,
        .form-select-compact:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background: #f0fdf4;
        }

        /* Toggle Devolução */
        .toggle-container-compact {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .toggle-container-compact:hover {
            border-color: #d1d5db;
        }

        .toggle-container-compact.active {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .toggle-switch-compact {
            width: 36px;
            height: 20px;
            background: #d1d5db;
            border-radius: 20px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .toggle-switch-compact::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .toggle-container-compact.active .toggle-switch-compact {
            background: #10b981;
        }

        .toggle-container-compact.active .toggle-switch-compact::before {
            transform: translateX(16px);
        }

        .toggle-label-compact {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            user-select: none;
        }

        /* Seção de Cards Modais */
        .middle-section-compact {
            padding: 20px 32px;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-cards-compact {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        .modal-card-compact {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .modal-card-compact:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
        }

        .card-icon-compact {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            margin: 0 auto 8px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .card-title-compact {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .card-status-compact {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            background: #fef3c7;
            color: #92400e;
        }

        .card-status-compact.success {
            background: #d1fae5;
            color: #065f46;
        }

        /* Seção de Produtos */
        .products-section-compact {
            padding: 0;
        }

        .products-header-compact {
            padding: 20px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .products-title-compact {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .products-actions-compact {
            display: flex;
            gap: 8px;
        }

        .products-content-compact {
            background: white;
            min-height: 300px;
            max-height: 400px;
            overflow: auto;
        }

        .form-actions-compact {
            display: flex;
            gap: 10px; /* espaço entre os botões */
            justify-content: flex-end; /* alinhados à direita */
            align-items: center; /* centraliza verticalmente */
        }

        /* ========================================
           TABELA DE PRODUTOS MELHORADA
           ======================================== */
        .products-table-compact {
            width: 100%;
            min-width: 2400px; /* Aumentado para acomodar colunas maiores */
            border-collapse: collapse;
            font-size: 11px;
        }

        .products-table-compact th {
            background: #374151;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            white-space: nowrap;
            border: 1px solid #4b5563;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .products-table-compact td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
        }

        /* COLUNAS PRINCIPAIS - MAIORES */
        .products-table-compact th:nth-child(1), /* Código */
        .products-table-compact td:nth-child(1) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(2), /* Descrição - EXPANDIDA */
        .products-table-compact td:nth-child(2) {
            min-width: 250px;
            width: 250px;
            text-align: left !important;
        }

        .products-table-compact th:nth-child(3), /* Unidade */
        .products-table-compact td:nth-child(3) {
            min-width: 60px;
            width: 60px;
        }

        .products-table-compact th:nth-child(4), /* Quantidade */
        .products-table-compact td:nth-child(4) {
            min-width: 90px;
            width: 90px;
        }

        .products-table-compact th:nth-child(5), /* Valor Unitário */
        .products-table-compact td:nth-child(5) {
            min-width: 100px;
            width: 100px;
        }

        .products-table-compact th:nth-child(6), /* Frete Unit. */
        .products-table-compact td:nth-child(6) {
            min-width: 90px;
            width: 90px;
        }

        .products-table-compact th:nth-child(7), /* Desconto */
        .products-table-compact td:nth-child(7) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(8), /* Total */
        .products-table-compact td:nth-child(8) {
            min-width: 100px;
            width: 100px;
        }

        .products-table-compact th:nth-child(9), /* LOTE */
        .products-table-compact td:nth-child(9) {
            min-width: 80px;
            width: 80px;
        }

        .products-table-compact th:nth-child(10), /* Estoque */
        .products-table-compact td:nth-child(10) {
            min-width: 70px;
            width: 70px;
        }

        .products-table-compact th:nth-child(11), /* CFOP */
        .products-table-compact td:nth-child(11) {
            min-width: 70px;
            width: 70px;
        }

        /* COLUNAS TRIBUTÁRIAS - MENORES (a partir de Sit. Trib.ICMS) */
        .products-table-compact th:nth-child(n+12), /* Todas após CFOP */
        .products-table-compact td:nth-child(n+12) {
            min-width: 65px;
            width: 65px;
            font-size: 10px;
        }

        /* Coluna de Ações - Fixa */
        .products-table-compact th:last-child,
        .products-table-compact td:last-child {
            min-width: 80px;
            width: 80px;
        }

        /* INPUTS DA TABELA - SEM SETAS E MELHORADOS */
        .products-table-compact td input {
            width: 100%;
            padding: 8px 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
            background: white;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        /* REMOVER SETAS DOS CAMPOS NUMÉRICOS */
        .products-table-compact td input[type="number"] {
            -moz-appearance: textfield; /* Firefox */
        }

        .products-table-compact td input[type="number"]::-webkit-outer-spin-button,
        .products-table-compact td input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none; /* Chrome, Safari, Edge */
            margin: 0;
        }

        /* Input da descrição - alinhado à esquerda */
        .products-table-compact td:nth-child(2) input {
            text-align: left !important;
            padding-left: 10px;
        }

        .products-table-compact td input:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.1);
        }

        /* Select da tabela */
        .products-table-compact td select {
            width: 100%;
            padding: 8px 6px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
            background: white;
            transition: all 0.2s ease;
        }

        .products-table-compact td select:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
        }

        .products-table-compact .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .products-table-compact .remove-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Footer com Totais */
        .footer-section-compact {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 16px 16px;
        }

        .totals-compact {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .total-item-compact {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .total-label-compact {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .total-value-compact {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Botões */
        .btn-compact {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .btn-primary-compact:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary-compact {
            background: white;
            color: #64748b;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary-compact:hover {
            background: #f8fafc;
            border-color: #d1d5db;
        }

        .btn-success-compact {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success-compact:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
        }

        /* NFE Import Section */
        .nfe-import-compact {
            background: #f0f9ff;
            border: 2px dashed #0ea5e9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 32px;
            text-align: center;
            display: none;
        }

        .nfe-import-compact.show {
            display: block;
        }

        /* Supplier Search */
        .supplier-search-compact {
            position: relative;
            min-width: 300px;
        }

        .supplier-dropdown-compact {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .supplier-dropdown-compact.show {
            display: block;
        }

        .supplier-item-compact {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }

        .supplier-item-compact:hover {
            background: #f8fafc;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }

        .toast {
            background: white;
            border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 280px;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        .toast.warning {
            border-left-color: #f59e0b;
        }

        .toast.success {
            border-left-color: #10b981;
        }

        /* MODAIS MELHORADOS - DESIGN ELEGANTE E PROFISSIONAL */
        .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    animation: modalFadeIn 0.3s ease;
    overflow: auto; /* Adicione isso */
}

        .modal.active {
    display: flex;
    align-items: flex-start; /* Mude de center para flex-start */
    justify-content: center;
    padding: 20px;
    overflow-y: auto; /* Permite scroll no modal inteiro */
}

        .modal-content {
    background: white;
    border-radius: 20px;
    box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.05);
    max-width: 1400px; /* Aumente a largura também */
    width: 95%;
    max-height: 95vh !important; /* Use vh em vez de % */
    height: auto; /* Mude para auto */
    overflow: visible; /* Mude para visible */
    animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    margin: auto; /* Centraliza verticalmente */
}

        /* Header do Modal Elegante */
        .modal-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 28px 32px;
            border-bottom: none;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.05) 50%, transparent 70%);
            pointer-events: none;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .modal-header h3 i {
            width: 40px;
            height: 40px;
            background: rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #10b981;
            backdrop-filter: blur(10px);
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            position: relative;
            z-index: 1;
            line-height: 1;
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fecaca;
            transform: rotate(90deg) scale(1.1);
        }

        /* Body do Modal Melhorado */
        .modal-body {
    padding: 40px;
    background: #fafbfc;
    min-height: 400px; /* Defina uma altura mínima */
    max-height: calc(95vh - 200px) !important; /* Calcula baseado na altura do modal */
    overflow-y: auto;
    height: auto; /* Permite que cresça conforme o conteúdo */
}

        /* Scrollbar customizada para modal */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Grid Layout Melhorado */
        .modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 28px;
        }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 28px;
        }

        .modal-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .modal-grid-full {
            grid-column: 1 / -1;
        }

        /* Form Groups Elegantes */
        .modal-form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-form-label {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .modal-form-label i {
            color: #10b981;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .modal-form-label .required {
            color: #ef4444;
            font-weight: 700;
        }

        /* Inputs Melhorados - CAMPOS MAIORES */
        .modal-form-input,
        .modal-form-select,
        .modal-form-textarea {
            padding: 20px 24px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            color: #1f2937;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            min-height: 56px;
        }

        .modal-form-input:focus,
        .modal-form-select:focus,
        .modal-form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 
                0 0 0 4px rgba(16, 185, 129, 0.1),
                0 4px 12px rgba(16, 185, 129, 0.15);
            background: #f0fdf4;
            transform: translateY(-1px);
        }

        .modal-form-input::placeholder,
        .modal-form-textarea::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        /* Textarea Específico */
        .modal-form-textarea {
            resize: vertical;
            min-height: 140px;
            font-family: inherit;
            line-height: 1.6;
        }

        /* Search Input com Ícone */
        .modal-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .modal-search-input {
            padding-left: 60px;
        }

        .modal-search-icon {
            position: absolute;
            left: 20px;
            color: #9ca3af;
            font-size: 20px;
            pointer-events: none;
            z-index: 1;
        }

        /* Footer do Modal Elegante */
        .modal-footer {
            padding: 28px 40px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            border-radius: 0 0 20px 20px;
        }

        /* Botões do Modal Melhorados */
        .modal-btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            min-height: 52px;
        }

        .modal-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .modal-btn:hover::before {
            left: 100%;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .modal-btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.5);
        }

        .modal-btn-secondary {
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .modal-btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            color: #374151;
            transform: translateY(-1px);
        }

        .modal-btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .modal-btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.5);
        }

        /* Tabelas nos Modais */
        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .modal-table th {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            color: white;
            padding: 20px 24px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
            border: none;
        }

        .modal-table td {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            background: white;
            vertical-align: middle;
        }

        .modal-table tr:hover td {
            background: #f8fafc;
        }

        .modal-table tr:last-child td {
            border-bottom: none;
        }

        /* Input dentro da tabela - CAMPOS MAIORES */
        .modal-table input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            min-height: 48px;
        }

        .modal-table input:focus {
            outline: none;
            border-color: #10b981;
            background: #f0fdf4;
        }

        /* Botão de remoção na tabela - MELHORADO */
        .modal-remove-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 44px;
        }

        .modal-remove-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }

        .modal-remove-btn i {
            font-size: 12px;
        }

        /* Seção de Adição */
        .modal-add-section {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 28px;
            transition: all 0.3s ease;
        }

        .modal-add-section:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        /* Product Selection */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            max-height: 400px;
            overflow-y: auto;
            padding: 4px;
        }

        .product-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #10b981;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .product-item:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
        }

        .product-item:hover::before {
            transform: scaleY(1);
        }

        .product-item.selected {
            border-color: #10b981;
            background: #f0fdf4;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .product-item.selected::before {
            transform: scaleY(1);
        }

        .product-search {
            margin-bottom: 28px;
        }

        /* ========================================
           CORREÇÃO CAMPO TRANSPORTADORA NO MODAL FRETE
           ======================================== */
        
        /* Campo transportadora ocupa toda a largura do modal */
        .modal-form-group-full-width {
            grid-column: 1 / -1; /* Ocupa todas as colunas disponíveis */
            width: 100%;
        }

        .modal-form-group-full-width .supplier-search-compact {
            width: 100%;
            min-width: 100%;
            max-width: 100%;
        }

        .modal-form-group-full-width .modal-form-input {
            width: 100%;
        }

        /* Animações */
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Responsividade para Modais */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                max-height: 90vh;
                margin: 20px;
                border-radius: 16px;
            }
            
            .modal-header {
                padding: 24px 28px;
            }
            
            .modal-header h3 {
                font-size: 18px;
            }
            
            .modal-body {
                padding: 28px;
            }
            
            .modal-grid-2,
            .modal-grid-3 {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .modal-footer {
                padding: 24px 28px;
                flex-direction: column;
            }
            
            .modal-btn {
                justify-content: center;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            .content-wrapper {
                margin: 0 20px;
            }
            
            .form-row-compact {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .modal-cards-compact {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .content-wrapper {
                margin: 0 10px;
            }
            
            .form-row-compact {
                grid-template-columns: 1fr;
            }
            
            .modal-cards-compact {
                grid-template-columns: 1fr;
            }
            
            .totals-compact {
                flex-direction: column;
                gap: 12px;
            }
            
            .footer-section-compact {
                flex-direction: column;
                gap: 16px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
        /* Estilos específicos para Modal de Parcelas (herda dos modais existentes) */
#parcelas-content {
    min-height: 300px;
}

#parcelas-table {
    margin-top: 0; /* Remove margem extra da tabela genérica */
}

#parcelas-table th {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    text-align: center;
}

#parcelas-table td {
    text-align: center;
    vertical-align: middle;
}

#parcelas-table tbody tr:hover td {
    background: #f0fdf4;
}

#avista-message h3 {
    font-size: 24px;
    font-weight: 700;
}

#confirmar-parcelas-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}

#confirmar-parcelas-btn:disabled:hover {
    box-shadow: none;
}

/* Responsividade para o modal de parcelas */
@media (max-width: 768px) {
    #modal-parcelas-preview .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    #parcelas-table {
        font-size: 14px;
    }
    
    #parcelas-table th,
    #parcelas-table td {
        padding: 12px 8px;
    }
}
    </style>
</head>
<body>
    <div class="main-content">
        <div class="content-wrapper">                      
            <div class="page-header-compact">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div>
                            <div class="header-title">Entrada de produtos</div>
                            <div class="header-subtitle">Faça uma entrada ou devolução de produtos</div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn-compact btn-secondary-compact">
                            <i class="fas fa-shopping-cart"></i>
                            Pedido
                        </button>
                        <button class="btn-compact btn-success-compact" onclick="openNFEImport()">
                            <i class="fas fa-download"></i>
                            Importar NF-e
                        </button>
                    </div>
                </div>

                <!-- Breadcrumb -->
                <div class="breadcrumb-compact">
                    <a href="../index.php" class="breadcrumb-item">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="../entradas.php" class="breadcrumb-item">
                        <i class="fas fa-sign-in-alt"></i>
                        Entradas
                    </a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-item active">Nova Entrada</span>
                </div>
            </div>

            <div id="toast-container" class="toast-container"></div>

            <!-- Container do Formulário -->
            <div class="form-container-compact">
                
                <!-- Seção NFE Import -->
                <div id="nfe-import-section" class="nfe-import-compact">
                    <h3 style="margin-bottom: 16px; color: #0369a1; display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Importar NFE
                    </h3>
                    <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                        <div style="position: relative;">
                            <input type="file" id="nfe_file" name="nfe_file" accept=".xml" required style="position: absolute; left: -9999px;">
                            <label for="nfe_file" class="btn-compact btn-primary-compact" style="cursor: pointer;">
                                <i class="fas fa-file-upload"></i>
                                Selecionar XML
                            </label>
                        </div>
                        <button type="submit" class="btn-compact btn-success-compact">
                            <i class="fas fa-upload"></i>
                            Importar
                        </button>
                        <button type="button" class="btn-compact btn-secondary-compact" onclick="closeNFEImport()">
                            Cancelar
                        </button>
                    </form>
                </div>

                <!-- Formulário Principal -->
                <form id="entradaForm" method="POST">
                    <input type="hidden" name="action" value="save_entrada">
                    
                    <!-- Seção Superior - Campos Básicos -->
                    <div class="top-section-compact">
                        <div class="form-row-compact">
                            <!-- Toggle Devolução -->
                            <div class="form-group-compact">
                                <label class="form-label-compact">Devolução</label>
                                <div class="toggle-container-compact" onclick="toggleDevolucao()">
                                    <input type="checkbox" id="devolucao" name="devolucao" style="display: none;">
                                    <div class="toggle-switch-compact"></div>
                                    <span class="toggle-label-compact">Ativar</span>
                                </div>
                            </div>
                            
                            <!-- Busca Fornecedor -->
                            <div class="form-group-compact supplier-search-compact">
                                <label for="supplier_search" class="form-label-compact">
                                    Fornecedor <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" 
                                       id="supplier_search" 
                                       name="supplier_search" 
                                       class="form-input-compact" 
                                       placeholder="Digite código ou nome do fornecedor"
                                       value="<?php echo $nfe_data['fornecedor_nome'] ?? ''; ?>"
                                       autocomplete="off"
                                       required>
                                <input type="hidden" id="supplier_id" name="supplier_id" value="<?php echo $nfe_data['fornecedor_codigo'] ?? ''; ?>">
                                <div id="supplier-dropdown" class="supplier-dropdown-compact"></div>
                            </div>
                            
                            <!-- Campos Numéricos -->
                            <div class="form-group-compact">
                                <label for="entrada_numero" class="form-label-compact">Entrada N°</label>
                                <input type="text" name="entrada_numero" id="entrada_numero" class="form-input-compact" value="<?php echo $entrada_numero; ?>" readonly>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="data_entrada" class="form-label-compact">
                                    Data Entrada <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="date" name="data_entrada" id="data_entrada" class="form-input-compact" value="<?php echo $nfe_data['data_emissao'] ?? date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="ano_safra" class="form-label-compact">Ano/Safra</label>
                                <input type="text" name="ano_safra" id="ano_safra" class="form-input-compact" value="2025">
                            </div>
                            
                            <div class="form-group-compact">
                                <label for="payment_type" class="form-label-compact">
                                    Tipo Pgto. <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="payment_type" id="payment_type" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="form-group-compact">
                            <label for="payment_condition" class="form-label-compact">
                                Condições <!-- Removido o <span style="color: #ef4444;">*</span> -->
                            </label>
                            <select name="payment_condition" id="payment_condition" class="form-select-compact">
                                <option value="">Selecione...</option>
                            </select>
                        </div>

                            
                            <div class="form-group-compact">
                                <label for="expense_type" class="form-label-compact">
                                    Tipo Despesa <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="expense_type" id="expense_type" class="form-select-compact" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Cards Modais -->
                    <div class="middle-section-compact">
                        <div class="modal-cards-compact">
                            <div class="modal-card-compact" data-modal="nfe">
                                <div class="card-icon-compact">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">NF-E</div>
                                    <div class="card-status-compact <?php echo $nfe_data ? 'success' : ''; ?>" id="nfe-status">
                                        <?php echo $nfe_data ? 'OK' : 'Pendente'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="frete">
                                <div class="card-icon-compact">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Frete</div>
                                    <div class="card-status-compact" id="frete-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="centro-custo">
                                <div class="card-icon-compact">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Centro Custo</div>
                                    <div class="card-status-compact" id="centro-custo-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="desconto">
                                <div class="card-icon-compact">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Desconto</div>
                                    <div class="card-status-compact" id="desconto-status">Opcional</div>
                                </div>
                            </div>

                            <div class="modal-card-compact" data-modal="observacoes">
                                <div class="card-icon-compact">
                                    <i class="fas fa-sticky-note"></i>
                                </div>
                                <div>
                                    <div class="card-title-compact">Observações</div>
                                    <div class="card-status-compact" id="observacoes-status">Opcional</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção de Produtos -->
                    <div class="products-section-compact">
                        <div class="products-header-compact">
                            <div class="products-title-compact">
                                <i class="fas fa-boxes"></i>
                                Produtos
                            </div>
                            <div class="products-actions-compact">
                                <button type="button" class="btn-compact btn-primary-compact" onclick="openProductModal()">
                                    <i class="fas fa-plus"></i>
                                    Adicionar Produto
                                </button>
                            </div>
                        </div>
                        
                        <div class="products-content-compact">
                            <table class="products-table-compact" id="products-table">
                                <thead>
                                    <tr>
                                        <th>Cód</th>
                                        <th>Descrição</th>
                                        <th>Un</th>
                                        <th>Qtd</th>
                                        <th>Vr. Unit.</th>
                                        <th>Frete Unit.</th>
                                        <th>Desc.</th>
                                        <th>Total</th>
                                        <th>LOTE</th>
                                        <th>Estoque</th>
                                        <th>CFOP</th>
                                        <th>Sit. Trib.ICMS</th>
                                        <th>Dif. ICMS</th>
                                        <th>B. Calc.Pis</th>
                                        <th>%Pis</th>
                                        <th>Vlr PIS</th>
                                        <th>Sit. Trib. COFINS</th>
                                        <th>B.Calc.COFINS</th>
                                        <th>%COFINS</th>
                                        <th>Vlr. COFINS</th>
                                        <th>Sit. Trib.IPI</th>
                                        <th>B.Calc.IPI</th>
                                        <th>%IPI</th>
                                        <th>Vlr. IPI</th>
                                        <th>B. Calc. Ret. ICMS Sub. Trib.</th>
                                        <th>Vlr.ICMS.Ret. Sub. Trib.</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="products-tbody">
                                    <!-- Produtos serão adicionados dinamicamente -->
                                    <?php if ($nfe_data && isset($nfe_data['produtos'])): ?>
                                        <?php foreach ($nfe_data['produtos'] as $index => $produto): ?>
                                        <tr>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][codproduto]" value="<?php echo htmlspecialchars($produto['codigo']); ?>"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][descricao]" value="<?php echo htmlspecialchars($produto['descricao']); ?>"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][unidade]" value="<?php echo htmlspecialchars($produto['unidade']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][quantidade]" value="<?php echo $produto['quantidade']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][valor_unitario]" value="<?php echo $produto['valor_unitario']; ?>" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][frete_unitario]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][desconto]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][total]" value="<?php echo $produto['valor_total']; ?>" step="0.01" readonly style="background: #f9fafb;"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][lote]" value=""></td>
                                            <td>
                                                <select name="produtos[<?php echo $index; ?>][estoque]">
                                                    <option value="S" selected>Sim</option>
                                                    <option value="N">Não</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][cfop]" value="<?php echo htmlspecialchars($produto['cfop']); ?>"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_icms]" value="<?php echo htmlspecialchars($produto['icms']['situacao_tributaria']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][dif_icms]" value="0.00" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_pis]" value="<?php echo $produto['pis']['base_calculo']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_pis]" value="<?php echo $produto['pis']['aliquota']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_pis]" value="<?php echo $produto['pis']['valor']; ?>" step="0.01"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_cofins]" value="<?php echo htmlspecialchars($produto['cofins']['situacao_tributaria']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_cofins]" value="<?php echo $produto['cofins']['base_calculo']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_cofins]" value="<?php echo $produto['cofins']['aliquota']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_cofins]" value="<?php echo $produto['cofins']['valor']; ?>" step="0.01"></td>
                                            <td><input type="text" name="produtos[<?php echo $index; ?>][sit_ipi]" value="<?php echo htmlspecialchars($produto['ipi']['situacao_tributaria']); ?>"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_ipi]" value="<?php echo $produto['ipi']['base_calculo']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][perc_ipi]" value="<?php echo $produto['ipi']['aliquota']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_ipi]" value="<?php echo $produto['ipi']['valor']; ?>" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][bc_icms_st]" value="0.00" step="0.01"></td>
                                            <td><input type="number" name="produtos[<?php echo $index; ?>][vl_icms_st]" value="0.00" step="0.01"></td>
                                            <td><button type="button" class="remove-btn" onclick="removeProductRow(this)">Remover</button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer com Totais -->
                    <div class="footer-section-compact">
                        <div class="totals-compact">
                            <div class="total-item-compact">
                                <span class="total-label-compact">Valor</span>
                                <span class="total-value-compact" id="total-valor"><?php echo isset($nfe_data['totais']['valor_produtos']) ? number_format($nfe_data['totais']['valor_produtos'], 2, ',', '.') : '0,00'; ?></span>
                                <input type="hidden" name="total_produtos" id="total_produtos" value="<?php echo $nfe_data['totais']['valor_produtos'] ?? 0; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Frete</span>
                                <span class="total-value-compact" id="total-frete"><?php echo isset($nfe_data['totais']['valor_frete']) ? number_format($nfe_data['totais']['valor_frete'], 2, ',', '.') : '0,00'; ?></span>
                                <input type="hidden" name="total_frete" id="total_frete_input" value="<?php echo $nfe_data['totais']['valor_frete'] ?? 0; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Descontos</span>
                                <span class="total-value-compact" id="total-descontos"><?php echo isset($nfe_data['totais']['valor_desconto']) ? number_format($nfe_data['totais']['valor_desconto'], 2, ',', '.') : '0,00'; ?></span>
                                <input type="hidden" name="total_desconto" id="total_desconto" value="<?php echo $nfe_data['totais']['valor_desconto'] ?? 0; ?>">
                            </div>
                            <div class="total-item-compact">
                                <span class="total-label-compact">Total</span>
                                <span class="total-value-compact" id="total-final"><?php echo isset($nfe_data['totais']['valor_total']) ? number_format($nfe_data['totais']['valor_total'], 2, ',', '.') : '0,00'; ?></span>
                                <input type="hidden" name="total_geral" id="total_geral" value="<?php echo $nfe_data['totais']['valor_total'] ?? 0; ?>">
                                <input type="hidden" name="total_icms" id="total_icms" value="<?php echo $nfe_data['totais']['valor_icms'] ?? 0; ?>">
                                <input type="hidden" name="total_ipi" id="total_ipi" value="<?php echo $nfe_data['totais']['valor_ipi'] ?? 0; ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions-compact">
                            <button type="button" class="btn-compact btn-secondary-compact" onclick="window.location.reload()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="btn-compact btn-success-compact">
                                <i class="fas fa-paper-plane"></i>
                                SALVAR ENTRADA
                            </button>
                        </div>
                    </div>
                </form>
            </div>


            <!-- MODAIS MELHORADOS -->
            <!-- Modal NF-E -->
            <div id="modal-nfe" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-file-invoice"></i> Informações da NF-E</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-grid-2">
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-hashtag"></i>
                                    Número Fiscal <span class="required">*</span>
                                </label>
                                <input type="text" name="nf_numero" id="nf_numero" class="modal-form-input" 
                                       placeholder="Digite o número da nota fiscal"
                                       value="<?php echo $nfe_data['numero_nf'] ?? ''; ?>">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Data de Emissão <span class="required">*</span>
                                </label>
                                <input type="date" name="dt_emissao" id="dt_emissao" class="modal-form-input" 
                                       value="<?php echo $nfe_data['data_emissao'] ?? ''; ?>">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-list-ol"></i>
                                    Série da NF
                                </label>
                                <input type="text" name="serie_nf" id="serie_nf" class="modal-form-input" 
                                       placeholder="Série da nota fiscal"
                                       value="<?php echo $nfe_data['serie_nf'] ?? ''; ?>">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Número Fiscal Entrada
                                </label>
                                <input type="text" name="nf_entrada" id="nf_entrada" class="modal-form-input" 
                                       placeholder="Número da entrada">
                            </div>
                        </div>
                        <div class="modal-form-group modal-grid-full">
                            <label class="modal-form-label">
                                <i class="fas fa-key"></i>
                                Chave de Acesso da NFE
                            </label>
                            <input type="text" name="chave_acesso" id="chave_acesso" class="modal-form-input" 
                                   placeholder="Digite a chave de 44 dígitos da NFE" 
                                   value="<?php echo $nfe_data['chave_acesso'] ?? ''; ?>"
                                   maxlength="44">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="nfe">
                            <i class="fas fa-check"></i>
                            Salvar Informações
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Frete - CAMPO TRANSPORTADORA CORRIGIDO -->
            <div id="modal-frete" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-truck"></i> Informações de Frete</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <!-- CAMPO TRANSPORTADORA OCUPA TODA A LARGURA -->
                        <div class="modal-form-group modal-form-group-full-width">
                            <label class="modal-form-label">
                                <i class="fas fa-shipping-fast"></i>
                                Transportadora
                            </label>
                            <div class="supplier-search-compact">
                                <input type="text" 
                                       id="transporter_search" 
                                       name="transporter_search" 
                                       class="modal-form-input" 
                                       placeholder="Digite código ou nome da transportadora"
                                       value="<?php echo $nfe_data['transportadora']['transportadora_nome'] ?? ''; ?>"
                                       autocomplete="off">
                                <input type="hidden" id="transporter_id" name="transporter_id" value="<?php echo $nfe_data['transportadora_codigo'] ?? ''; ?>">
                                <div id="transporter-dropdown" class="supplier-dropdown-compact"></div>
                            </div>
                        </div>
                        
                        <!-- CAMPOS VALOR E PLACA EM GRID 2 COLUNAS -->
                        <div class="modal-grid-2">
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-dollar-sign"></i>
                                    Valor do Frete (R$)
                                </label>
                                <input type="number" name="valor_frete" id="valor_frete" class="modal-form-input" 
                                       placeholder="0,00" step="0.01" 
                                       value="<?php echo $nfe_data['totais']['valor_frete'] ?? 0; ?>" 
                                       onchange="updateFreteTotal()">
                            </div>
                            <div class="modal-form-group">
                                <label class="modal-form-label">
                                    <i class="fas fa-car"></i>
                                    Placa do Veículo
                                </label>
                                <input type="text" name="placa_veiculo" id="placa_veiculo" class="modal-form-input" 
                                       placeholder="ABC-1234" 
                                       value="<?php echo $nfe_data['transportadora']['veiculo_placa'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="frete">
                            <i class="fas fa-check"></i>
                            Salvar Frete
                        </button>
                 
                    </div>
                </div>
            </div>

            <!-- Modal Centro de Custo -->
            <div id="modal-centro-custo" class="modal">
                <div class="modal-content" style="max-width: 1000px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-calculator"></i> Centros de Custo</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-add-section">
                            <div class="modal-grid-3">
                                <div class="modal-form-group">
                                    <label class="modal-form-label">
                                        <i class="fas fa-building"></i>
                                        Centro de Custo <span class="required">*</span>
                                    </label>
                                    <select id="cost-center-select" class="modal-form-select">
                                        <option value="">Selecione um centro de custo...</option>
                                    </select>
                                </div>
                                <div class="modal-form-group">
                                    <label class="modal-form-label">
                                        <i class="fas fa-dollar-sign"></i>
                                        Valor (R$)
                                    </label>
                                    <input type="number" id="cost-center-value" class="modal-form-input" 
                                           placeholder="0,00" step="0.01">
                                </div>
                                <div style="display: flex; align-items: end;">
                                    <button type="button" class="modal-btn modal-btn-primary" onclick="addCostCenter()" style="width: 100%;">
                                        <i class="fas fa-plus"></i>
                                        Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <table class="modal-table" id="cost-center-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-building"></i> Centro de Custo</th>
                                    <th><i class="fas fa-car"></i> Placa</th>
                                    <th><i class="fas fa-dollar-sign"></i> Valor (R$)</th>
                                    <th><i class="fas fa-sticky-note"></i> Observações</th>
                                    <th><i class="fas fa-cog"></i> Ações</th>
                                </tr>
                            </thead>
                            <tbody id="cost-center-tbody">
                                <!-- Centros de custo serão adicionados dinamicamente -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="centro-custo">
                            <i class="fas fa-check"></i>
                            Salvar Centros de Custo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Desconto -->
            <div id="modal-desconto" class="modal">
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-percentage"></i> Descontos</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-add-section">
                            <div class="modal-grid-3">
                                <div class="modal-form-group" style="grid-column: 1 / 3;">
                                    <label class="modal-form-label">
                                        <i class="fas fa-tag"></i>
                                        Descrição do Desconto
                                    </label>
                                    <input type="text" id="discount-description" class="modal-form-input" 
                                           placeholder="Ex: Desconto comercial, Bonificação, etc.">
                                </div>
                                <div class="modal-form-group">
                                    <label class="modal-form-label">
                                        <i class="fas fa-dollar-sign"></i>
                                        Valor (R$) <span class="required">*</span>
                                    </label>
                                    <input type="number" id="discount-amount" class="modal-form-input" 
                                           placeholder="0,00" step="0.01">
                                </div>
                            </div>
                            <div style="text-align: center; margin-top: 16px;">
                                <button type="button" class="modal-btn modal-btn-primary" onclick="addDiscount()">
                                    <i class="fas fa-plus"></i>
                                    Adicionar Desconto
                                </button>
                            </div>
                        </div>
                        
                        <table class="modal-table" id="discount-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tag"></i> Descrição</th>
                                    <th><i class="fas fa-dollar-sign"></i> Valor (R$)</th>
                                    <th><i class="fas fa-cog"></i> Ações</th>
                                </tr>
                            </thead>
                            <tbody id="discount-tbody">
                                <!-- Descontos serão adicionados dinamicamente -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="desconto">
                            <i class="fas fa-check"></i>
                            Salvar Descontos
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Observações -->
            <div id="modal-observacoes" class="modal">
                <div class="modal-content" style="max-width: 700px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-sticky-note"></i> Observações</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label class="modal-form-label">
                                <i class="fas fa-comment-alt"></i>
                                Observações Gerais
                            </label>
                            <textarea name="observacoes_gerais" id="observacoes_gerais" class="modal-form-textarea" 
                                      placeholder="Digite suas observações sobre esta entrada de produtos..."></textarea>
                        </div>
                        <div class="modal-form-group">
                            <label class="modal-form-label">
                                <i class="fas fa-receipt"></i>
                                Observações Fiscais
                            </label>
                            <textarea name="observacoes_fiscais" id="observacoes_fiscais" class="modal-form-textarea" 
                                      placeholder="Observações relacionadas a questões fiscais..."></textarea>
                        </div>
                        <div class="modal-form-group">
                            <label class="modal-form-label">
                                <i class="fas fa-lock"></i>
                                Observações Internas
                            </label>
                            <textarea name="observacoes_internas" id="observacoes_internas" class="modal-form-textarea" 
                                      placeholder="Observações para uso interno da empresa..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary modal-save" data-section="observacoes">
                            <i class="fas fa-check"></i>
                            Salvar Observações
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Preview de Parcelas -->
<div id="modal-parcelas-preview" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-credit-card"></i> Preview das Parcelas - Contas a Pagar</h3>
            <button type="button" class="modal-close" onclick="closeParcelasModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="parcelas-content">
                <!-- Mensagem para Pagamento à Vista -->
                <div id="avista-message" style="display: none; text-align: center; padding: 40px; background: #f0fdf4; border-radius: 12px; border: 2px solid #10b981;">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 16px;"></i>
                    <h3 style="color: #065f46; margin-bottom: 8px;">Pagamento à Vista</h3>
                    <p style="color: #065f46; font-size: 16px; margin-bottom: 16px;">Nenhuma conta a pagar será criada. O valor total será considerado como pago na data de emissão.</p>
                    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <strong>Valor Total: R$ <span id="avista-total">0,00</span></strong><br>
                        <strong>Data: <span id="avista-data"></span></strong><br>
                        <strong>Status: Já Pago</strong>
                    </div>
                </div>
                
                <!-- Tabela de Parcelas -->
                <div id="parcelas-table-container" style="display: none;">
                    <p style="margin-bottom: 16px; font-weight: 600; color: #374151;">Serão criadas <strong id="total-parcelas-count">0</strong> parcelas para contas a pagar:</p>
                    <table class="modal-table" id="parcelas-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Parcela</th>
                                <th><i class="fas fa-calendar-alt"></i> Vencimento</th>
                                <th><i class="fas fa-dollar-sign"></i> Valor (R$)</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody id="parcelas-tbody">
                            <!-- Linhas dinâmicas via JS -->
                        </tbody>
                    </table>
                    <div style="margin-top: 16px; text-align: right;">
                        <strong>Total: R$ <span id="parcelas-total">0,00</span></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeParcelasModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-primary" id="confirmar-parcelas-btn" onclick="confirmarParcelas()" disabled>
                <i class="fas fa-check"></i> Confirmar e Salvar Entrada
            </button>
        </div>
    </div>
</div>

            <!-- Modal Seleção de Produtos -->
            <div id="modal-products" class="modal">
                <div class="modal-content" style="max-width: 1200px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-boxes"></i> Selecionar Produtos</h3>
                        <button type="button" class="modal-close">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="product-search">
                            <div class="modal-search-wrapper">
                                <i class="fas fa-search modal-search-icon"></i>
                                <input type="text" id="product-search-input" class="modal-form-input modal-search-input" 
                                       placeholder="Buscar produtos por código, nome ou descrição..." onkeyup="searchProducts()">
                            </div>
                        </div>
                        <div class="product-grid" id="product-grid">
                            <!-- Produtos serão carregados dinamicamente -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary modal-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="button" class="modal-btn modal-btn-primary" onclick="addSelectedProducts()">
                            <i class="fas fa-plus"></i>
                            Adicionar Selecionados
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    // Variáveis globais
    let productCount = <?php echo $nfe_data && isset($nfe_data['produtos']) ? count($nfe_data['produtos']) : 0; ?>;
    let costCenterCount = 0;
    let discountCount = 0;
    let selectedProducts = [];
    let costCenters = [];
    let modalDataStore = {};
    let parcelasPreview = [];
    let formSubmitEvent = null;
    let isSubmitting = false; // FLAG ANTI-LOOP: Evita que submit() dispare o listener novamente

    // JavaScript para gerenciar os modais e funcionalidades
    document.addEventListener('DOMContentLoaded', function() {
        // Toast notification system
        window.showToast = function(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'error' ? 'exclamation-circle' : 'info-circle';
            
            toast.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => {
                    if (toast.parentNode === container) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        };

        // Show PHP message if exists
        <?php if ($mensagem): ?>
        showToast('<?php echo addslashes($mensagem); ?>', '<?php echo $tipo_mensagem; ?>');
        <?php endif; ?>

        // Load dropdowns
        loadPaymentTypes();
        loadConditions();
        loadExpenseTypes();

        // Modal Management (código limpo, sem duplicatas)
        const modals = document.querySelectorAll('.modal');
        const modalCards = document.querySelectorAll('.modal-card-compact');
        const modalCloses = document.querySelectorAll('.modal-close, .modal-cancel');
        const modalSaves = document.querySelectorAll('.modal-save');

        // Open modals when cards are clicked
        modalCards.forEach(card => {
            card.addEventListener('click', function() {
                const modalId = this.getAttribute('data-modal');
                const modal = document.getElementById(`modal-${modalId}`);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    
                    // Load cost centers when opening cost center modal
                    if (modalId === 'centro-custo') {
                        loadCostCenters();
                    }
                }
            });
        });

        // Close modals
        modalCloses.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Save modal data
        modalSaves.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                const section = this.getAttribute('data-section');
                
                // Armazenar dados do modal no store global
                if (section === 'nfe') {
                    modalDataStore.nfe = {
                        nf_numero: document.getElementById('nf_numero').value,
                        dt_emissao: document.getElementById('dt_emissao').value,
                        serie_nf: document.getElementById('serie_nf').value,
                        nf_entrada: document.getElementById('nf_entrada').value,
                        chave_acesso: document.getElementById('chave_acesso').value
                    };
                }
                
                if (section === 'frete') {
                    modalDataStore.frete = {
                        transporter_id: document.getElementById('transporter_id').value,
                        valor_frete: document.getElementById('valor_frete').value,
                        placa_veiculo: document.getElementById('placa_veiculo').value
                    };
                }
                
                if (section === 'observacoes') {
                    modalDataStore.observacoes = {
                        observacoes_gerais: document.getElementById('observacoes_gerais').value,
                        observacoes_fiscais: document.getElementById('observacoes_fiscais').value,
                        observacoes_internas: document.getElementById('observacoes_internas').value
                    };
                }
                
                // Capturar dados das tabelas dinâmicas
                if (section === 'centro-custo') {
                    const costCenterRows = document.querySelectorAll('#cost-center-tbody tr');
                    const costCenterData = [];
                    
                    costCenterRows.forEach((row, index) => {
                        const codcc = row.querySelector('input[name*="[codcc]"]').value;
                        const placa = row.querySelector('input[name*="[placa]"]').value;
                        const valor = row.querySelector('input[name*="[valor]"]').value;
                        const obs = row.querySelector('input[name*="[obs]"]').value;
                        
                        if (codcc) {
                            costCenterData.push({
                                codcc: codcc,
                                placa: placa,
                                valor: valor,
                                obs: obs
                            });
                        }
                    });
                    
                    modalDataStore.costCenters = costCenterData;
                }
                
                if (section === 'desconto') {
                    const discountRows = document.querySelectorAll('#discount-tbody tr');
                    const discountData = [];
                    
                    discountRows.forEach((row, index) => {
                        const descricao = row.querySelector('input[name*="[descricao]"]').value;
                        const valor = row.querySelector('input[name*="[valor]"]').value;
                        
                        if (descricao) {
                            discountData.push({
                                descricao: descricao,
                                valor: valor
                            });
                        }
                    });
                    
                    modalDataStore.discounts = discountData;
                }
                
                // Atualizar campos hidden no formulário
                updateAllHiddenFields();
                
                // Update card status
                updateCardStatus(section);
                
                // Close modal
                modal.classList.remove('active');
                document.body.style.overflow = '';
                
                // Show success message
                showToast(`Dados de ${section} salvos com sucesso!`, 'success');
            });
        });

        // Função para atualizar TODOS os campos hidden
        function updateAllHiddenFields() {
            const form = document.getElementById('entradaForm');
            
            // Remover campos hidden existentes para evitar duplicatas
            const existingHiddenFields = form.querySelectorAll('input[type="hidden"][data-modal]');
            existingHiddenFields.forEach(field => field.remove());
            
            // Adicionar campos de todos os modais armazenados
            Object.keys(modalDataStore).forEach(modalType => {
                const modalData = modalDataStore[modalType];
                
                if (modalType === 'costCenters') {
                    // Criar campos para centros de custo
                    modalData.forEach((cc, index) => {
                        Object.keys(cc).forEach(fieldName => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `cost_centers[${index}][${fieldName}]`;
                            input.value = cc[fieldName];
                            input.setAttribute('data-modal', modalType);
                            form.appendChild(input);
                        });
                    });
                } else if (modalType === 'discounts') {
                    // Criar campos para descontos
                    modalData.forEach((discount, index) => {
                        Object.keys(discount).forEach(fieldName => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `discounts[${index}][${fieldName}]`;
                            input.value = discount[fieldName];
                            input.setAttribute('data-modal', modalType);
                            form.appendChild(input);
                        });
                    });
                } else {
                    // Criar campos para outros modais
                    Object.keys(modalData).forEach(fieldName => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = fieldName;
                        input.value = modalData[fieldName];
                        input.setAttribute('data-modal', modalType);
                        form.appendChild(input);
                    });
                }
            });
        }

        // Close modal when clicking outside
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    activeModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });

        function updateCardStatus(section) {
            const statusBadge = document.getElementById(`${section}-status`);
            statusBadge.textContent = 'OK';
            statusBadge.className = 'card-status-compact success';
        }

        // Load payment types
        function loadPaymentTypes() {
            fetch('?action=search_payment_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('payment_type');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    data.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.codtppag;
                        option.textContent = type.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading payment types:', error);
                });
        }

        // Load conditions
        function loadConditions() {
            fetch('?action=search_conditions')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('payment_condition');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    data.forEach(condition => {
                        const option = document.createElement('option');
                        option.value = condition.codcond;
                        option.textContent = condition.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading conditions:', error);
                });
        }

        // Load expense types
        function loadExpenseTypes() {
            fetch('?action=search_expense_types')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('expense_type');
                    select.innerHTML = '<option value="">Selecione...</option>';
                    
                    data.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.codtpdes;
                        option.textContent = type.Descricao;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading expense types:', error);
                });
        }

        // Supplier search functionality
        const supplierSearch = document.getElementById('supplier_search');
        const supplierDropdown = document.getElementById('supplier-dropdown');
        let supplierTimeout;

        supplierSearch.addEventListener('input', function() {
            clearTimeout(supplierTimeout);
            const term = this.value.trim();
            
            if (term.length < 2) {
                supplierDropdown.classList.remove('show');
                return;
            }
            
            supplierTimeout = setTimeout(() => {
                searchSuppliers(term);
            }, 300);
        });

        function searchSuppliers(term) {
            fetch(`?action=search_suppliers&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    supplierDropdown.innerHTML = '';
                    
                    if (data.length === 0) {
                        supplierDropdown.innerHTML = '<div class="supplier-item-compact">Nenhum fornecedor encontrado</div>';
                    } else {
                        data.forEach(supplier => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <div style="font-weight: 600;">${supplier.Nome}</div>
                                <div style="font-size: 11px; color: #64748b;">Código: ${supplier.codcliente} | CNPJ: ${supplier.cnpj_cpf}</div>
                                <div style="font-size: 11px; color: #64748b;">${supplier.Cidade}/${supplier.Uf}</div>
                            `;
                            item.addEventListener('click', function() {
                                supplierSearch.value = supplier.Nome;
                                document.getElementById('supplier_id').value = supplier.codcliente;
                                supplierDropdown.classList.remove('show');
                            });
                            supplierDropdown.appendChild(item);
                        });
                    }
                    
                    supplierDropdown.classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Transporter search functionality
        const transporterSearch = document.getElementById('transporter_search');
        const transporterDropdown = document.getElementById('transporter-dropdown');
        let transporterTimeout;

        if (transporterSearch) {
            transporterSearch.addEventListener('input', function() {
                clearTimeout(transporterTimeout);
                const term = this.value.trim();
                
                if (term.length < 2) {
                    transporterDropdown.classList.remove('show');
                    return;
                }
                
                transporterTimeout = setTimeout(() => {
                    searchTransporters(term);
                }, 300);
            });
        }

        function searchTransporters(term) {
            fetch(`?action=search_transporters&term=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    transporterDropdown.innerHTML = '';
                    
                    if (data.length === 0) {
                        transporterDropdown.innerHTML = '<div class="supplier-item-compact">Nenhuma transportadora encontrada</div>';
                    } else {
                        data.forEach(transporter => {
                            const item = document.createElement('div');
                            item.className = 'supplier-item-compact';
                            item.innerHTML = `
                                <div style="font-weight: 600;">${transporter.Nome}</div>
                                <div style="font-size: 11px; color: #64748b;">Código: ${transporter.codcliente} | CNPJ: ${transporter.cnpj_cpf}</div>
                                <div style="font-size: 11px; color: #64748b;">${transporter.Cidade}/${transporter.Uf}</div>
                            `;
                            item.addEventListener('click', function() {
                                transporterSearch.value = transporter.Nome;
                                document.getElementById('transporter_id').value = transporter.codcliente;
                                transporterDropdown.classList.remove('show');
                            });
                            transporterDropdown.appendChild(item);
                        });
                    }
                    
                    transporterDropdown.classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Hide dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.supplier-search-compact')) {
                supplierDropdown.classList.remove('show');
                if (transporterDropdown) {
                    transporterDropdown.classList.remove('show');
                }
            }
        });

        // NFE Import Functions
        window.openNFEImport = function() {
            document.getElementById('nfe-import-section').classList.add('show');
        }

        window.closeNFEImport = function() {
            document.getElementById('nfe-import-section').classList.remove('show');
        }

        // Toggle Devolução
        window.toggleDevolucao = function() {
            const container = document.querySelector('.toggle-container-compact');
            const checkbox = document.getElementById('devolucao');
            
            container.classList.toggle('active');
            checkbox.checked = !checkbox.checked;
            
            const label = container.querySelector('.toggle-label-compact');
            label.textContent = checkbox.checked ? 'Ativa' : 'Ativar';
        }

        // Load cost centers
        function loadCostCenters() {
            fetch('?action=search_cost_centers')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('cost-center-select');
                                    select.innerHTML = '<option value="">Selecione um centro de custo...</option>';
                
                data.forEach(cc => {
                    const option = document.createElement('option');
                    option.value = cc.codcc;
                    option.textContent = cc.descricao;
                    option.dataset.codcc = cc.codcc;
                    select.appendChild(option);
                });
                
                costCenters = data;
            })
            .catch(error => {
                console.error('Error loading cost centers:', error);
            });
        }

        // Cost Center Management
        window.addCostCenter = function() {
            const select = document.getElementById('cost-center-select');
            const selectedOption = select.options[select.selectedIndex];
            
            if (!selectedOption.value) {
                showToast('Selecione um centro de custo', 'warning');
                return;
            }
            
            const tbody = document.getElementById('cost-center-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    ${selectedOption.textContent}
                    <input type="hidden" name="cost_centers[${costCenterCount}][codcc]" value="${selectedOption.value}">
                </td>
                <td><input type="text" name="cost_centers[${costCenterCount}][placa]" class="form-input-compact" placeholder="Placa"></td>
                <td><input type="number" name="cost_centers[${costCenterCount}][valor]" class="form-input-compact" placeholder="0,00" step="0.01"></td>
                <td><input type="text" name="cost_centers[${costCenterCount}][obs]" class="form-input-compact" placeholder="Observações"></td>
                <td>
                    <button type="button" class="remove-btn" onclick="removeCostCenter(this)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            costCenterCount++;
            
            // Reset select
            select.selectedIndex = 0;
        }

        window.removeCostCenter = function(button) {
            const row = button.closest('tr');
            row.remove();
        }

        // Discount Management
        window.addDiscount = function() {
            const description = document.getElementById('discount-description').value.trim();
            const amount = document.getElementById('discount-amount').value;
            
            if (!description || !amount) {
                showToast('Preencha descrição e valor do desconto', 'warning');
                return;
            }
            
            const tbody = document.getElementById('discount-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    ${description}
                    <input type="hidden" name="discounts[${discountCount}][descricao]" value="${description}">
                </td>
                <td>
                    R$ ${parseFloat(amount).toFixed(2).replace('.', ',')}
                    <input type="hidden" name="discounts[${discountCount}][valor]" value="${amount}">
                </td>
                <td>
                    <button type="button" class="remove-btn" onclick="removeDiscount(this)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            discountCount++;
            
            // Reset inputs
            document.getElementById('discount-description').value = '';
            document.getElementById('discount-amount').value = '';
            
            // Update totals
            updateTotals();
        }

        window.removeDiscount = function(button) {
            const row = button.closest('tr');
            row.remove();
            updateTotals();
        }

        // Product Modal Management
        window.openProductModal = function() {
            const modal = document.getElementById('modal-products');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            loadProducts();
        }

        function loadProducts(searchTerm = '') {
            fetch(`?action=search_products&term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById('product-grid');
                    grid.innerHTML = '';
                    
                    if (data.length === 0) {
                        grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #64748b;">Nenhum produto encontrado</div>';
                        return;
                    }
                    
                    data.forEach(product => {
                        const item = document.createElement('div');
                        item.className = 'product-item';
                        item.dataset.productId = product.codproduto;
                        item.innerHTML = `
                            <div style="font-weight: 600; margin-bottom: 4px;">${product.nome}</div>
                            <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">Código: ${product.codproduto}</div>
                            <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">Unidade: ${product.Un}</div>
                            <div style="font-size: 11px; color: #64748b;">Preço: R$ ${parseFloat(product.Vrunit || 0).toFixed(2).replace('.', ',')}</div>
                        `;
                        
                        item.addEventListener('click', function() {
                            this.classList.toggle('selected');
                            const productId = this.dataset.productId;
                            
                            if (this.classList.contains('selected')) {
                                selectedProducts.push(product);
                            } else {
                                selectedProducts = selectedProducts.filter(p => p.codproduto !== productId);
                            }
                        });
                        
                        grid.appendChild(item);
                    });
                })
                .catch(error => {
                    console.error('Error loading products:', error);
                });
        }

        window.searchProducts = function() {
            const searchTerm = document.getElementById('product-search-input').value.trim();
            loadProducts(searchTerm);
        }

        window.addSelectedProducts = function() {
            if (selectedProducts.length === 0) {
                showToast('Selecione pelo menos um produto', 'warning');
                return;
            }
            
            const tbody = document.getElementById('products-tbody');
            
            selectedProducts.forEach(product => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="text" name="produtos[${productCount}][codproduto]" value="${product.codproduto}"></td>
                    <td><input type="text" name="produtos[${productCount}][descricao]" value="${product.nome}"></td>
                    <td><input type="text" name="produtos[${productCount}][unidade]" value="${product.Un}"></td>
                    <td><input type="number" name="produtos[${productCount}][quantidade]" value="1" step="0.01" onchange="calculateRowTotal(this)"></td>
                    <td><input type="number" name="produtos[${productCount}][valor_unitario]" value="${product.Vrunit || 0}" step="0.01" onchange="calculateRowTotal(this)"></td>
                    <td><input type="number" name="produtos[${productCount}][frete_unitario]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                    <td><input type="number" name="produtos[${productCount}][desconto]" value="0.00" step="0.01" onchange="calculateRowTotal(this)"></td>
                    <td><input type="number" name="produtos[${productCount}][total]" value="${product.Vrunit || 0}" step="0.01" readonly style="background: #f9fafb;"></td>
                    <td><input type="text" name="produtos[${productCount}][lote]" value=""></td>
                    <td>
                        <select name="produtos[${productCount}][estoque]">
                            <option value="S" selected>Sim</option>
                            <option value="N">Não</option>
                        </select>
                    </td>
                    <td><input type="text" name="produtos[${productCount}][cfop]" value=""></td>
                    <td><input type="text" name="produtos[${productCount}][sit_icms]" value=""></td>
                    <td><input type="number" name="produtos[${productCount}][dif_icms]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][bc_pis]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][perc_pis]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][vl_pis]" value="0.00" step="0.01"></td>
                    <td><input type="text" name="produtos[${productCount}][sit_cofins]" value=""></td>
                    <td><input type="number" name="produtos[${productCount}][bc_cofins]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][perc_cofins]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][vl_cofins]" value="0.00" step="0.01"></td>
                    <td><input type="text" name="produtos[${productCount}][sit_ipi]" value=""></td>
                    <td><input type="number" name="produtos[${productCount}][bc_ipi]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][perc_ipi]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][vl_ipi]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][bc_icms_st]" value="0.00" step="0.01"></td>
                    <td><input type="number" name="produtos[${productCount}][vl_icms_st]" value="0.00" step="0.01"></td>
                    <td><button type="button" class="remove-btn" onclick="removeProductRow(this)">Remover</button></td>
                `;
                tbody.appendChild(row);
                productCount++;
            });
            
            // Close modal and reset
            document.getElementById('modal-products').classList.remove('active');
            document.body.style.overflow = '';
            const addedCount = selectedProducts.length;
            selectedProducts = [];
            
            // Update totals
            updateTotals();
            
            showToast(`${addedCount} produto(s) adicionado(s)`, 'success');
        }

        // Product row management
        window.removeProductRow = function(button) {
            const row = button.closest('tr');
            row.remove();
            updateTotals();
        }

        window.calculateRowTotal = function(input) {
            const row = input.closest('tr');
            const quantidade = parseFloat(row.querySelector('input[name*="[quantidade]"]').value) || 0;
            const valorUnitario = parseFloat(row.querySelector('input[name*="[valor_unitario]"]').value) || 0;
            const freteUnitario = parseFloat(row.querySelector('input[name*="[frete_unitario]"]').value) || 0;
            const desconto = parseFloat(row.querySelector('input[name*="[desconto]"]').value) || 0;
            
            const total = (quantidade * (valorUnitario + freteUnitario)) - desconto;
            row.querySelector('input[name*="[total]"]').value = total.toFixed(2);
            
            updateTotals();
        }

        // Update frete total
        window.updateFreteTotal = function() {
            updateTotals();
        }

        function updateTotals() {
            let totalProdutos = 0;
            let totalFreteProducts = 0;
            let totalDescontos = 0;
            
            // Calculate products total and frete from products
            const productRows = document.querySelectorAll('#products-tbody tr');
            productRows.forEach(row => {
                const total = parseFloat(row.querySelector('input[name*="[total]"]').value) || 0;
                const quantidade = parseFloat(row.querySelector('input[name*="[quantidade]"]').value) || 0;
                const freteUnitario = parseFloat(row.querySelector('input[name*="[frete_unitario]"]').value) || 0;
                
                totalProdutos += total;
                totalFreteProducts += quantidade * freteUnitario;
            });
            
            // Calculate discounts total
            const discountRows = document.querySelectorAll('#discount-tbody tr');
            discountRows.forEach(row => {
                const valorInput = row.querySelector('input[name*="[valor]"]');
                if (valorInput) {
                    totalDescontos += parseFloat(valorInput.value) || 0;
                }
            });
            
            // Get additional freight value
            const valorFreteAdicional = parseFloat(document.getElementById('valor_frete')?.value || 0);
            const totalFrete = totalFreteProducts + valorFreteAdicional;
            
            const totalFinal = totalProdutos + totalFrete - totalDescontos;
            
            // Update display
            document.getElementById('total-valor').textContent = totalProdutos.toFixed(2).replace('.', ',');
            document.getElementById('total-frete').textContent = totalFrete.toFixed(2).replace('.', ',');
            document.getElementById('total-descontos').textContent = totalDescontos.toFixed(2).replace('.', ',');
            document.getElementById('total-final').textContent = totalFinal.toFixed(2).replace('.', ',');
            
            // Update hidden inputs
            document.getElementById('total_produtos').value = totalProdutos.toFixed(2);
            document.getElementById('total_frete_input').value = totalFrete.toFixed(2);
            document.getElementById('total_desconto').value = totalDescontos.toFixed(2);
            document.getElementById('total_geral').value = totalFinal.toFixed(2);
        }

        // Initialize totals calculation
        updateTotals();

        // INTERCEPTAR SUBMIT DO FORMULÁRIO PARA PREVIEW DE PARCELAS (VERSÃO CORRIGIDA COM ANTI-LOOP)
        document.getElementById('entradaForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                console.log('Submit já em andamento - ignorando listener (anti-loop)'); // DEBUG
                return; // Evita loop
            }

            console.log('=== SUBMIT INTERCEPTADO ==='); // DEBUG
            e.preventDefault(); // Impede submit imediato
            isSubmitting = true; // Ativa flag anti-loop

            const paymentCondition = document.getElementById('payment_condition').value;
            const totalGeral = document.getElementById('total_geral').value;
            const dtEmissao = document.getElementById('dt_emissao').value || new Date().toISOString().split('T')[0];
            
            console.log('Condição de pagamento:', paymentCondition); // DEBUG
            console.log('Total geral:', totalGeral); // DEBUG
            console.log('Data emissão:', dtEmissao); // DEBUG
            
            if (!paymentCondition || parseFloat(totalGeral) <= 0) {
                console.log('Sem condição ou total inválido: submit direto'); // DEBUG
                showToast('Salvando entrada sem contas a pagar...', 'info');
                isSubmitting = false; // Desativa flag
                this.submit(); // Submete direto
                return;
            }
            
            // Armazena o evento para prosseguir depois (opcional, mas mantém compatibilidade)
            formSubmitEvent = e;
            
            // Chama API para calcular parcelas
            console.log('Chamando API calculate_parcelas...'); // DEBUG
            fetch('?action=calculate_parcelas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `total_geral=${totalGeral}&payment_condition=${paymentCondition}&dt_emissao=${dtEmissao}`
            })
            .then(response => {
                console.log('Resposta da API:', response.status); // DEBUG
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dados das parcelas:', data); // DEBUG
                parcelasPreview = data;
                
                // Se array vazio ou erro, submete direto (à vista ou erro)
                if (!data || data.error || data.length === 0) {
                    console.log('Sem parcelas ou erro: submit direto'); // DEBUG
                    showToast(data?.error ? `Erro: ${data.error}. Salvando sem preview...` : 'Salvando sem contas a pagar...', 'warning');
                    isSubmitting = false; // Desativa flag
                    this.submit(); // Submete direto
                    return;
                }
                
                // Verifica se é à vista (API retorna array com 1 item fictício)
                const isAVista = data.length === 1 && data[0] && data[0].tipo === 'A_VISTA';
                console.log('É à vista?', isAVista); // DEBUG
                
                const modal = document.getElementById('modal-parcelas-preview');
                if (!modal) {
                    console.error('MODAL NÃO ENCONTRADO! Adicione o HTML do modal.'); // DEBUG
                    showToast('Erro: Modal de parcelas não encontrado. Salvando sem preview...', 'error');
                    isSubmitting = false; // Desativa flag
                    this.submit(); // Fallback
                    return;
                }
                
                if (isAVista) {
                    // Mostra mensagem à vista e submete direto (sem necessidade de confirmação)
                    document.getElementById('avista-total').textContent = parseFloat(data[0].valor_parcela).toFixed(2).replace('.', ',');
                    document.getElementById('avista-data').textContent = new Date(data[0].data_vencimento).toLocaleDateString('pt-BR');
                    document.getElementById('avista-message').style.display = 'block';
                    document.getElementById('parcelas-table-container').style.display = 'none';
                    
                    // Para à vista, mostra toast e submete direto (sem abrir modal)
                    showToast('Pagamento à vista detectado. Salvando sem contas a pagar...', 'info');
                    isSubmitting = false; // Desativa flag
                    this.submit(); // Submete direto
                                        return;
                }
                
                // Não é à vista: mostra tabela de parcelas
                const tbody = document.getElementById('parcelas-tbody');
                if (!tbody) {
                    console.error('TBODY DAS PARCELAS NÃO ENCONTRADO!'); // DEBUG
                    isSubmitting = false;
                    return;
                }
                tbody.innerHTML = '';
                let totalParcelas = 0;
                
                data.forEach(parcela => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${parcela.numero_parcela} de ${parcela.total_parcelas}</td>
                        <td>${new Date(parcela.data_vencimento).toLocaleDateString('pt-BR')}</td>
                        <td>R$ ${parseFloat(parcela.valor_parcela).toFixed(2).replace('.', ',')}</td>
                        <td><span style="color: #10b981; font-weight: 600;">Pendente</span></td>
                    `;
                    tbody.appendChild(row);
                    totalParcelas += parseFloat(parcela.valor_parcela);
                });
                
                document.getElementById('total-parcelas-count').textContent = data.length;
                document.getElementById('parcelas-total').textContent = totalParcelas.toFixed(2).replace('.', ',');
                document.getElementById('avista-message').style.display = 'none';
                document.getElementById('parcelas-table-container').style.display = 'block';
                
                // Abre o modal
                console.log('Abrindo modal de parcelas...'); // DEBUG
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Habilita botão de confirmar
                document.getElementById('confirmar-parcelas-btn').disabled = false;
            })
            .catch(error => {
                console.error('Erro ao calcular parcelas:', error); // DEBUG
                showToast('Erro ao calcular parcelas: ' + error.message + '. Salvando sem preview...', 'error');
                isSubmitting = false; // Desativa flag
                this.submit(); // Fallback: submete direto
            });
        });

        // Funções para Modal de Parcelas (ÚNICAS E SEM DUPLICATAS)
        window.closeParcelasModal = function() {
            console.log('Fechando modal de parcelas'); // DEBUG
            const modal = document.getElementById('modal-parcelas-preview');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
            parcelasPreview = []; // Limpa
            isSubmitting = false; // Desativa flag (caso tenha ficado ativa)
            formSubmitEvent = null;
        };

        window.confirmarParcelas = function() {
            console.log('Confirmando parcelas'); // DEBUG
            if (parcelasPreview.length === 0) {
                showToast('Nenhuma parcela para confirmar.', 'warning');
                return;
            }
            
            const isAVista = parcelasPreview.length === 1 && parcelasPreview[0] && parcelasPreview[0].tipo === 'A_VISTA';
            const mensagem = isAVista ? 'Salvando entrada à vista (sem contas a pagar)...' : 'Salvando entrada e criando contas a pagar...';
            
            showToast(mensagem, 'info');
            
            // Fecha modal
            closeParcelasModal();
            
            // Prossegue com o submit original (flag já ativa, evita loop)
            const form = document.getElementById('entradaForm');
            form.submit();
        };

        // Fecha modal de parcelas ao clicar fora
        const parcelasModal = document.getElementById('modal-parcelas-preview');
        if (parcelasModal) {
            parcelasModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeParcelasModal();
                }
            });
        }

        // ESC para fechar modal de parcelas (reforça o genérico)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal && activeModal.id === 'modal-parcelas-preview') {
                    closeParcelasModal();
                }
            }
        });

        // Initialize totals calculation
        updateTotals();
    });
</script>

</body>
</html>