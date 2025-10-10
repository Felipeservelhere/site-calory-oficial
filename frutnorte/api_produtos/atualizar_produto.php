<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login para continuar.']);
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
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
    echo json_encode(['success' => false, 'message' => 'Erro na conexão de autenticação.']);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id do usuário autenticado
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Erro de autenticação. Acesso negado.']);
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na validação de usuário.']);
    exit;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// If accessed via GET (browser), show API info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'api' => 'Atualizar Produtos',
        'version' => '1.0',
        'method' => 'POST only',
        'description' => 'API para atualização de produtos com upload de foto',
        'required_fields' => ['produto_id'],
        'status' => 'ready',
        'empresa_id' => $idcliente_empresa,
        'photo_support' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Incluir conexão com banco
require_once '../config/database.php';

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Verificar se foi fornecido o ID do produto
    if (!isset($_POST['produto_id']) || empty($_POST['produto_id'])) {
        throw new Exception('ID do produto é obrigatório.');
    }

    $produto_id = (int)$_POST['produto_id'];

    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();

    // Iniciar transação
    $pdo->beginTransaction();

    // Usar ID do cliente dinâmico da sessão
    $id_cliente = $idcliente_empresa;

    // Verificar se o produto existe e pertence ao cliente
    $stmt = $pdo->prepare("SELECT id, codproduto FROM produtos WHERE id = ? AND idcliente = ?");
    $stmt->execute([$produto_id, $id_cliente]);
    $produto_existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto_existente) {
        throw new Exception('Produto não encontrado ou não pertence à sua empresa.');
    }

    // Processar remoção de foto se solicitado
    if (isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1') {
        $stmt = $pdo->prepare("UPDATE produtos SET foto = NULL WHERE id = ? AND idcliente = ?");
        $stmt->execute([$produto_id, $id_cliente]);
    }

    // Processar upload de nova foto se existir
    $fotoBlob = null;
    $fotoProcessada = false;
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fotoTmp = $_FILES['foto']['tmp_name'];
        $fotoTipo = $_FILES['foto']['type'];
        $fotoTamanho = $_FILES['foto']['size'];
        
        // Validar tipo de arquivo
        $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(strtolower($fotoTipo), $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WEBP.');
        }
        
        // Validar tamanho (máximo 5MB)
        if ($fotoTamanho > 5 * 1024 * 1024) {
            throw new Exception('Arquivo muito grande. Máximo 5MB.');
        }
        
        // Verificar se o arquivo temporário existe
        if (!file_exists($fotoTmp)) {
            throw new Exception('Erro no upload: arquivo temporário não encontrado.');
        }
        
        // Converter para BLOB
        $fotoBlob = file_get_contents($fotoTmp);
        
        if ($fotoBlob === false) {
            throw new Exception('Erro ao ler o arquivo de imagem.');
        }
        
        $fotoProcessada = true;
    } else if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Se houve erro no upload
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do servidor)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
            UPLOAD_ERR_CANT_WRITE => 'Erro de escrita no disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        $errorCode = $_FILES['foto']['error'];
        $errorMessage = isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : 'Erro desconhecido no upload';
        
        throw new Exception("Erro no upload da foto: {$errorMessage}");
    }

    // Função para tratar valores vazios
    function tratarValor($valor, $tipo = 'string') {
        if ($valor === '' || $valor === null || !isset($valor)) {
            return null;
        }
        
        if ($tipo === 'decimal') {
            return is_numeric($valor) ? (float)$valor : null;
        }
        
        if ($tipo === 'int') {
            return is_numeric($valor) ? (int)$valor : null;
        }
        
        return trim($valor);
    }

    // Preparar dados para atualização
    $campos_atualizacao = [];
    $valores = [];

    // Lista de campos que podem ser atualizados
    $campos_permitidos = [
        'nome' => 'string',
        'descricao_reduzida' => 'string',
        'Un' => 'string',  // CAMPO UNIDADE - IMPORTANTE
        'Vrunit' => 'decimal',
        'Frete' => 'decimal',
        'custototal' => 'decimal',
        'perc_mb' => 'decimal',
        'Vrvenda' => 'decimal',
        'perc_avista' => 'decimal',
        'Vravista' => 'decimal',
        'Pesobruto' => 'decimal',
        'Pesoliquido' => 'decimal',
        'estoque' => 'string',
        'estoqueminimo' => 'decimal',
        'saldo_estoque' => 'decimal',
        'variedade' => 'string',
        'codgrupo' => 'int',
        'CodigoBarra' => 'string',
        'NP' => 'string',
        'codst' => 'string',
        'NCM' => 'string',
        'MAXDESC' => 'decimal',
        'MINPRECO' => 'decimal',
        'descricao_add_nfe' => 'string',
        'cest' => 'string',
        'un_trib' => 'string',
        'conversor_trib' => 'decimal',
        'beneficio' => 'string',
        'origem' => 'int',
        'descpromocao' => 'decimal',
        'vrpromocao' => 'decimal',
        'envia_site' => 'string',
        'ativo' => 'string',
        'promocao' => 'string',
        'lote' => 'string',
        'insumos' => 'string'
    ];

    // Processar cada campo
    foreach ($campos_permitidos as $campo => $tipo) {
        if (isset($_POST[$campo])) {
            $valor = tratarValor($_POST[$campo], $tipo);
            $campos_atualizacao[] = "{$campo} = ?";
            $valores[] = $valor;
        }
    }

    // Validações obrigatórias
    if (isset($_POST['nome']) && empty(tratarValor($_POST['nome']))) {
        throw new Exception('Nome do produto é obrigatório');
    }

    // Verificar se o código de barras já existe para outro produto (se informado)
    if (isset($_POST['CodigoBarra']) && !empty($_POST['CodigoBarra'])) {
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE CodigoBarra = ? AND idcliente = ? AND id != ?");
        $stmt->execute([tratarValor($_POST['CodigoBarra']), $id_cliente, $produto_id]);
        if ($stmt->fetch()) {
            throw new Exception('Código de barras já existe para outro produto');
        }
    }

    // Verificar se o grupo existe (se informado) - com filtro por empresa
    if (isset($_POST['codgrupo']) && !empty($_POST['codgrupo'])) {
        $stmt = $pdo->prepare("SELECT codgrupo FROM grupos WHERE codgrupo = ? AND idcliente = ?");
        $stmt->execute([tratarValor($_POST['codgrupo'], 'int'), $id_cliente]);
        if (!$stmt->fetch()) {
            throw new Exception('Grupo selecionado não existe em sua empresa');
        }
    }

    // Se há campos para atualizar
    if (!empty($campos_atualizacao)) {
        // Adicionar data de atualização
        $campos_atualizacao[] = "Data_alt = ?";
        $valores[] = date('Y-m-d H:i:s');
        
        // Adicionar condições WHERE
        $valores[] = $produto_id;
        $valores[] = $id_cliente;

        $sql = "UPDATE produtos SET " . implode(', ', $campos_atualizacao) . " WHERE id = ? AND idcliente = ?";
        $stmt = $pdo->prepare($sql);

        if (!$stmt->execute($valores)) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Erro ao atualizar produto no banco de dados: ' . $errorInfo[2]);
        }
    }

    // Atualizar foto se necessário
    if ($fotoProcessada && $fotoBlob !== null) {
        $sqlFoto = "UPDATE produtos SET foto = ? WHERE id = ? AND idcliente = ?";
        $stmtFoto = $pdo->prepare($sqlFoto);
        
        // Bind específico para BLOB
        $stmtFoto->bindParam(1, $fotoBlob, PDO::PARAM_LOB);
        $stmtFoto->bindParam(2, $produto_id, PDO::PARAM_INT);
        $stmtFoto->bindParam(3, $id_cliente, PDO::PARAM_INT);
        
        if (!$stmtFoto->execute()) {
            $errorInfo = $stmtFoto->errorInfo();
            throw new Exception('Erro ao atualizar foto do produto: ' . $errorInfo[2]);
        }
    }

    // Commit da transação
    $pdo->commit();

    // Buscar dados completos do produto atualizado para retorno
    $stmt = $pdo->prepare("
        SELECT p.*, g.nome as nome_grupo,
               CASE WHEN p.foto IS NOT NULL AND LENGTH(p.foto) > 0 THEN 1 ELSE 0 END as tem_foto
        FROM produtos p 
        LEFT JOIN grupos g ON p.codgrupo = g.codgrupo AND p.idcliente = g.idcliente 
        WHERE p.id = ? AND p.idcliente = ?
    ");
    $stmt->execute([$produto_id, $id_cliente]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    // Preparar dados de retorno (sem o BLOB da foto)
    if (isset($produto['foto'])) {
        $produto['foto_tamanho'] = !empty($produto['foto']) ? strlen($produto['foto']) : 0;
        unset($produto['foto']); // Remove o BLOB do retorno
    }

    // Log de sucesso
    error_log("Produto atualizado com sucesso. ID: {$produto_id}, Empresa: {$id_cliente}" . 
              ($fotoProcessada ? ", Com nova foto de " . strlen($fotoBlob) . " bytes" : ""));

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Produto atualizado com sucesso!' . ($fotoProcessada ? ' (com nova foto)' : ''),
        'produto' => $produto,
        'empresa_id' => $id_cliente,
        'debug' => [
            'campos_atualizados' => count($campos_atualizacao),
            'foto_processada' => $fotoProcessada,
            'foto_tamanho_bytes' => $fotoProcessada ? strlen($fotoBlob) : 0
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log do erro
    error_log("Erro ao atualizar produto: " . $e->getMessage());

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'empresa_id' => $idcliente_empresa,
            'files_data' => isset($_FILES['foto']) ? [
                'name' => $_FILES['foto']['name'] ?? 'N/A',
                'type' => $_FILES['foto']['type'] ?? 'N/A',
                'size' => $_FILES['foto']['size'] ?? 'N/A',
                'error' => $_FILES['foto']['error'] ?? 'N/A'
            ] : 'No file uploaded'
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Rollback em caso de erro de banco
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log do erro
    error_log("Erro de banco ao atualizar produto: " . $e->getMessage());

    // Resposta de erro genérica (não expor detalhes do banco)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Tente novamente.',
        'debug' => [
            'error_type' => 'database_error',
            'sql_state' => $e->getCode()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>