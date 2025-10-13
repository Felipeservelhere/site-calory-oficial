<?php
session_start();
header('Content-Type: application/json');
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

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
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
    error_log("Erro conexão auth apagar_conta_sessao: " . $e->getMessage());
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Validar usuário
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
    error_log("Erro validação usuário apagar_conta_sessao: " . $e->getMessage());
    exit;
}

// ==================== PROCESSAR LIMPEZA DA SESSÃO ====================
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Limpar arrays de contas bancárias da sessão
        $contas_limpas = 0;
        
        if (isset($_SESSION['contas_bancarias_temp'])) {
            $contas_limpas += count($_SESSION['contas_bancarias_temp']);
            $_SESSION['contas_bancarias_temp'] = [];
        }
        
        if (isset($_SESSION['contas_excluir'])) {
            $contas_limpas += count($_SESSION['contas_excluir']);
            $_SESSION['contas_excluir'] = [];
        }
        
        // Log para debug
        error_log("Sessão limpa para admin_id: $admin_id, empresa_id: $idcliente_empresa - $contas_limpas contas removidas");
        
        echo json_encode([
            'success' => true,
            'message' => 'Sessão de contas bancárias limpa com sucesso',
            'contas_removidas' => $contas_limpas,
            'empresa_id' => $idcliente_empresa
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao limpar sessão: ' . $e->getMessage()
    ]);
    error_log("Erro geral apagar_conta_sessao: " . $e->getMessage());
}
?>