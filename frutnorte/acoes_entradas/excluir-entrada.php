<?php
// acoes_entradas/excluir-entrada.php
session_start(); // ADICIONADO: Inicia a sessão

include '../config/database.php'; // Ajuste o caminho

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// ==================== VERIFICAÇÃO DE LOGIN E EMPRESA ====================
// ADICIONADO: Verificação de login e empresa_id para definir idcliente
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Faça login para continuar.'
    ]);
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id']) || !is_numeric($_SESSION['empresa_id']) || (int)$_SESSION['empresa_id'] <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Faça login novamente.'
    ]);
    exit;
}

$idcliente = (int)$_SESSION['empresa_id']; // ADICIONADO: Define idcliente da sessão
error_log("Excluir Entrada - Iniciada para idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

$id = $_POST['id'] ?? 0;
$excluir_contas = isset($_POST['excluir_contas']) && $_POST['excluir_contas'] == '1'; // true se 1

if (!is_numeric($id) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $pdo->beginTransaction(); // Inicia transação

    error_log("Excluir Entrada - Iniciando deleções para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

    // 1. Verificar se a entrada existe para o idcliente atual (opcional, mas recomendado para segurança)
    $stmtCheck = $pdo->prepare("SELECT codentrada FROM entradas WHERE codentrada = ? AND idcliente = ?");
    $stmtCheck->execute([$id, $idcliente]);
    if (!$stmtCheck->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Entrada não encontrada ou não pertence à sua empresa.']);
        exit;
    }

    // 2. Se excluir_contas == true, deletar contas a pagar primeiro
    $contasDeletadas = 0;
    if ($excluir_contas) {
        // Deletar contas a pagar associadas (com filtro idcliente)
        $stmt = $pdo->prepare("DELETE FROM contaspagar WHERE codentrada = ? AND idcliente = ?");
        $stmt->execute([$id, $idcliente]);
        $contasDeletadas = $stmt->rowCount();
        error_log("Excluir Entrada - Contas a pagar deletadas: {$contasDeletadas} para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    }

    // 3. Deletar itens da entrada (com filtro idcliente)
    $stmt = $pdo->prepare("DELETE FROM entradas_itens WHERE codentrada = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente]);
    error_log("Excluir Entrada - Itens da entrada deletados para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

    // 4. Deletar centros de custo (com filtro idcliente)
    $stmt = $pdo->prepare("DELETE FROM entradas_cc WHERE codentrada = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente]);
    error_log("Excluir Entrada - Centros de custo deletados para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

    // 5. Deletar descontos (com filtro idcliente)
    $stmt = $pdo->prepare("DELETE FROM entradas_descontos WHERE codentrada = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente]);
    error_log("Excluir Entrada - Descontos deletados para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

    // 6. Deletar a entrada principal (com filtro idcliente)
    $stmt = $pdo->prepare("DELETE FROM entradas WHERE codentrada = ? AND idcliente = ?");
    $stmt->execute([$id, $idcliente]);
    $entradaDeletada = $stmt->rowCount() > 0;
    error_log("Excluir Entrada - Entrada principal deletada: " . ($entradaDeletada ? 'Sim' : 'Não') . " para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

    $pdo->commit(); // Confirma transação

    if ($entradaDeletada) {
        $msg = "Entrada excluída com sucesso!";
        if ($excluir_contas && $contasDeletadas > 0) {
            $msg .= " {$contasDeletadas} conta(s) a pagar também foram excluída(s).";
        }
        error_log("Excluir Entrada - Sucesso para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        throw new Exception("Entrada não encontrada ou já excluída.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Reverte transação em caso de erro
    }
    error_log("Excluir Entrada - Erro para codentrada={$id} e idcliente={$idcliente} em " . date('Y-m-d H:i:s') . ": " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>