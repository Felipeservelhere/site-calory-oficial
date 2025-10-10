<?php
// API separada para editar entrada - evitar conflito com HTML
session_start(); // ADICIONADO: Inicia a sessão

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN E EMPRESA ====================
// ADICIONADO: Verificação de login e empresa_id para definir idcliente
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Acesso negado. Faça login para continuar.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id']) || !is_numeric($_SESSION['empresa_id']) || (int)$_SESSION['empresa_id'] <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sessão inválida. Faça login novamente.',
        'error_code' => 'INVALID_SESSION'
    ]);
    exit;
}

$idcliente = (int)$_SESSION['empresa_id']; // ADICIONADO: Define idcliente da sessão
error_log("API Editar Entrada - Iniciada para idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

// Configuração do banco de dados
$db_config = [
    'host' => 'localhost',
    'dbname' => 'frutnorte',
    'username' => 'root',
    'password' => '@@rOOt@cAlOry@1967@@'
];

function conectarBanco() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        error_log("API Editar Entrada - Conexão ao BD bem-sucedida para idcliente={$GLOBALS['idcliente']} (" . date('Y-m-d H:i:s') . ")");
        return $pdo;
    } catch (PDOException $e) {
        error_log("API Editar Entrada - Erro na conexão com o banco para idcliente={$GLOBALS['idcliente']} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro na conexão: ' . $e->getMessage(), 'error_code' => 'DB_CONNECTION_ERROR']);
        exit;
    }
}

// Verificar ação
if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ação não especificada',
        'error_code' => 'MISSING_ACTION'
    ]);
    exit;
}

try {
    $pdo = conectarBanco();
    $action = $_GET['action'];
    error_log("API Editar Entrada - Ação solicitada: {$action} para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    
    switch ($action) {
        case 'search_suppliers':
            $term = trim($_GET['term'] ?? '');
            if (empty($term)) {
                echo json_encode([]);
                exit;
            }
            error_log("API Editar Entrada - Busca de fornecedores: term='{$term}' para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codcliente, Nome, Fantasia, cnpj_cpf, Cidade, Uf 
                FROM clientes 
                WHERE tipocliente = '3' 
                AND idcliente = ?  -- ADICIONADO: Filtro por idcliente
                AND (codcliente LIKE ? OR Nome LIKE ? OR Fantasia LIKE ?) 
                AND ativo = 'S'
                ORDER BY Nome 
                LIMIT 10
            ");
            $searchTerm = "%{$term}%";
            $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]); // ADICIONADO: Bind idcliente
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Fornecedores encontrados: " . count($suppliers) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($suppliers);
            break;
            
        case 'search_transporters':
            $term = trim($_GET['term'] ?? '');
            if (empty($term)) {
                echo json_encode([]);
                exit;
            }
            error_log("API Editar Entrada - Busca de transportadoras: term='{$term}' para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codcliente, Nome, Fantasia, cnpj_cpf, Cidade, Uf 
                FROM clientes 
                WHERE transportador = 'S' 
                AND idcliente = ?  -- ADICIONADO: Filtro por idcliente
                AND (codcliente LIKE ? OR Nome LIKE ? OR Fantasia LIKE ?) 
                AND ativo = 'S'
                ORDER BY Nome 
                LIMIT 10
            ");
            $searchTerm = "%{$term}%";
            $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]); // ADICIONADO: Bind idcliente
            $transporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Transportadoras encontradas: " . count($transporters) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($transporters);
            break;
            
        case 'search_products':
            $term = trim($_GET['term'] ?? '');
            if (empty($term)) {
                echo json_encode([]);
                exit;
            }
            error_log("API Editar Entrada - Busca de produtos: term='{$term}' para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codproduto, nome, descricao_reduzida, Un, Vrunit, NCM, ativo
                FROM produtos 
                WHERE idcliente = ?  -- ADICIONADO: Filtro por idcliente
                AND ativo = 'S' 
                AND (codproduto LIKE ? OR nome LIKE ? OR descricao_reduzida LIKE ?) 
                ORDER BY nome 
                LIMIT 20
            ");
            $searchTerm = "%{$term}%";
            $stmt->execute([$idcliente, $searchTerm, $searchTerm, $searchTerm]); // ADICIONADO: Bind idcliente
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Produtos encontrados: " . count($products) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($products);
            break;
            
        case 'search_cost_centers':
            error_log("API Editar Entrada - Busca de centros de custo para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codigo as codcc, descricao 
                FROM centro_custo 
                WHERE idcliente = ?  -- ADICIONADO: Filtro por idcliente
                AND ativo = 'S'
                ORDER BY descricao
            ");
            $stmt->execute([$idcliente]); // ADICIONADO: Bind idcliente
            $costCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Centros de custo encontrados: " . count($costCenters) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($costCenters);
            break;
            
        case 'search_payment_types':
            error_log("API Editar Entrada - Busca de tipos de pagamento para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codtppag, Descricao 
                FROM tipopagamentos 
                WHERE idcliente = ?
                ORDER BY Descricao
            ");
            $stmt->execute([$idcliente]); // ADICIONADO: Bind idcliente
            $paymentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Tipos de pagamento encontrados: " . count($paymentTypes) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($paymentTypes);
            break;
            
        case 'search_conditions':
            error_log("API Editar Entrada - Busca de condições de pagamento para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codcond, Descricao 
                FROM condicoes 
                WHERE idcliente = ?
                ORDER BY Descricao
            ");
            $stmt->execute([$idcliente]); // ADICIONADO: Bind idcliente
            $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Condições encontradas: " . count($conditions) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($conditions);
            break;
            
        case 'search_expense_types':
            error_log("API Editar Entrada - Busca de tipos de despesa para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            $stmt = $pdo->prepare("
                SELECT codtpdes, Descricao 
                FROM tipodespesas 
                WHERE idcliente = ?
                ORDER BY Descricao
            ");
            $stmt->execute([$idcliente]); // ADICIONADO: Bind idcliente
            $expenseTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("API Editar Entrada - Tipos de despesa encontrados: " . count($expenseTypes) . " para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            echo json_encode($expenseTypes);
            break;
            
        default:
            error_log("API Editar Entrada - Ação inválida: {$action} para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ação inválida',
                'error_code' => 'INVALID_ACTION'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("API Editar Entrada - Erro geral para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . "): " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>