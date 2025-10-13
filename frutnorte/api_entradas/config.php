<?php
session_start();
// ========================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ========================================

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Acesso negado. Faça login para continuar.');
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    die('Sessão inválida. Faça login novamente.');
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
    die('Erro na conexão de autenticação.');
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do usuário autenticado
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        die('Erro de autenticação. Acesso negado.');
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    die('Erro na validação de usuário.');
}

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
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Erro na conexão com o banco: " . $e->getMessage());
    }
}

// Funções auxiliares
function obterProximoCodigoProduto($idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codproduto) as max_cod FROM produtos WHERE idcliente = ?");
        $stmt->execute([$idcliente_empresa]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

function obterProximoCodigoCliente($idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codcliente) as max_cod FROM clientes WHERE idcliente = ?");
        $stmt->execute([$idcliente_empresa]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

function obterProximoCodigoEntrada($idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codentrada) as max_cod FROM entradas WHERE idcliente = ?");
        $stmt->execute([$idcliente_empresa]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

function obterProximoCodigoSituacaoTributaria($idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT MAX(codst) as max_cod FROM sittrib WHERE idcliente = ?");
        $stmt->execute([$idcliente_empresa]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

function produtoExiste($codigo, $idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT codproduto FROM produtos WHERE codproduto = ? AND idcliente = ?");
        $stmt->execute([$codigo, $idcliente_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function situacaoTributariaExiste($codst, $idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT codst FROM sittrib WHERE codst = ? AND idcliente = ?");
        $stmt->execute([$codst, $idcliente_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function fornecedorExiste($cnpj, $idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT idcliente, codcliente FROM clientes WHERE cnpj_cpf = ? AND tipocliente = '3' AND idcliente = ?");
        $stmt->execute([$cnpj, $idcliente_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function transportadoraExiste($cnpj, $idcliente_empresa) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT idcliente, codcliente FROM clientes WHERE cnpj_cpf = ? AND transportador = 'S' AND idcliente = ?");
        $stmt->execute([$cnpj, $idcliente_empresa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

// Função para obter o idcliente_empresa global
function getIdclienteEmpresa() {
    global $idcliente_empresa;
    return $idcliente_empresa;
}
?>