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
        'api' => 'Salvar Produtos',
        'version' => '1.3',
        'method' => 'POST only',
        'description' => 'API para cadastro de produtos com upload de foto CORRIGIDO',
        'required_fields' => ['nome', 'idcliente'],
        'status' => 'ready',
        'empresa_id' => $idcliente_empresa,
        'photo_support' => true,
        'fix_applied' => 'BLOB binding corrected + Multi-empresa'
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

    // Conectar ao banco
    $database = new Database();
    $pdo = $database->getConnection();

    // Iniciar transação
    $pdo->beginTransaction();

    // Usar ID do cliente dinâmico da sessão
    $id_cliente = $idcliente_empresa;

    // Gerar próximo código do produto (para a empresa específica)
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(codproduto), 0) + 1 as proximo_codigo FROM produtos WHERE idcliente = ?");
    $stmt->execute([$id_cliente]);
    $proximoCodigo = $stmt->fetchColumn();

    // Processar upload de foto se existir
    $fotoBlob = null;
    $fotoProcessada = false;
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fotoTmp = $_FILES['foto']['tmp_name'];
        $fotoTipo = $_FILES['foto']['type'];
        $fotoTamanho = $_FILES['foto']['size'];
        $fotoNome = $_FILES['foto']['name'];
        
        // Log do arquivo recebido
        error_log("Upload de foto recebido: {$fotoNome}, Tipo: {$fotoTipo}, Tamanho: {$fotoTamanho}, Empresa: {$id_cliente}");
        
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
        
        error_log("Erro no upload da foto: {$errorMessage}, Empresa: {$id_cliente}");
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

    // Preparar dados para inserção (SEM O CAMPO FOTO AQUI)
    $dados = [
        'idcliente' => $id_cliente,
        'codproduto' => $proximoCodigo,
        'nome' => tratarValor($_POST['nome'] ?? null),
        'descricao_reduzida' => tratarValor($_POST['descricao_reduzida'] ?? null),
        'Un' => tratarValor($_POST['Un'] ?? null),
        'Vrunit' => tratarValor($_POST['Vrunit'] ?? null, 'decimal'),
        'Frete' => tratarValor($_POST['Frete'] ?? null, 'decimal'),
        'custototal' => tratarValor($_POST['custototal'] ?? null, 'decimal'),
        'perc_mb' => tratarValor($_POST['perc_mb'] ?? null, 'decimal'),
        'Vrvenda' => tratarValor($_POST['Vrvenda'] ?? null, 'decimal'),
        'perc_avista' => tratarValor($_POST['perc_avista'] ?? null, 'decimal'),
        'Vravista' => tratarValor($_POST['Vravista'] ?? null, 'decimal'),
        'Pesobruto' => tratarValor($_POST['Pesobruto'] ?? null, 'decimal'),
        'Pesoliquido' => tratarValor($_POST['Pesoliquido'] ?? 1, 'decimal'),
        'estoque' => tratarValor($_POST['estoque'] ?? 'S'),
        'estoqueminimo' => tratarValor($_POST['estoqueminimo'] ?? null, 'decimal'),
        'saldo_estoque' => tratarValor($_POST['saldo_estoque'] ?? null, 'decimal'),
        'variedade' => tratarValor($_POST['variedade'] ?? null),
        'codgrupo' => tratarValor($_POST['codgrupo'] ?? null, 'int'),
        'CodigoBarra' => tratarValor($_POST['CodigoBarra'] ?? null),
        'NP' => tratarValor($_POST['NP'] ?? 'N'),
        'codst' => tratarValor($_POST['codst'] ?? null),
        'NCM' => tratarValor($_POST['NCM'] ?? null),
        'MAXDESC' => tratarValor($_POST['MAXDESC'] ?? null, 'decimal'),
        'MINPRECO' => tratarValor($_POST['MINPRECO'] ?? null, 'decimal'),
        'descricao_add_nfe' => tratarValor($_POST['descricao_add_nfe'] ?? null),
        'cest' => tratarValor($_POST['cest'] ?? null),
        'un_trib' => tratarValor($_POST['un_trib'] ?? null),
        'conversor_trib' => tratarValor($_POST['conversor_trib'] ?? null, 'decimal'),
        'beneficio' => tratarValor($_POST['beneficio'] ?? null),
        'origem' => tratarValor($_POST['origem'] ?? null, 'int'),
        'descpromocao' => tratarValor($_POST['descpromocao'] ?? null, 'decimal'),
        'vrpromocao' => tratarValor($_POST['vrpromocao'] ?? null, 'decimal'),
        'envia_site' => tratarValor($_POST['envia_site'] ?? 'N'),
        'ativo' => tratarValor($_POST['ativo'] ?? 'S'),
        'promocao' => tratarValor($_POST['promocao'] ?? 'N'),
        'lote' => tratarValor($_POST['lote'] ?? 'S'),
        'insumos' => tratarValor($_POST['insumos'] ?? 'N'),
        'Data_cad' => date('Y-m-d H:i:s')
    ];

    // Validações obrigatórias
    if (empty($dados['nome'])) {
        throw new Exception('Nome do produto é obrigatório');
    }

    // Verificar se o código de barras já existe (se informado) - com filtro por empresa
    if (!empty($dados['CodigoBarra'])) {
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE CodigoBarra = ? AND idcliente = ?");
        $stmt->execute([$dados['CodigoBarra'], $id_cliente]);
        if ($stmt->fetch()) {
            throw new Exception('Código de barras já existe para outro produto em sua empresa');
        }
    }

    // Verificar se o grupo existe (se informado) - com filtro por empresa
    if (!empty($dados['codgrupo'])) {
        $stmt = $pdo->prepare("SELECT codgrupo FROM grupos WHERE codgrupo = ? AND idcliente = ?");
        $stmt->execute([$dados['codgrupo'], $id_cliente]);
        if (!$stmt->fetch()) {
            throw new Exception('Grupo selecionado não existe em sua empresa');
        }
    }

    // NOVA ABORDAGEM: Inserir primeiro sem foto, depois atualizar com foto
    
    // 1. Preparar SQL de inserção SEM foto
    $campos = array_keys($dados);
    $placeholders = ':' . implode(', :', $campos);
    $camposStr = implode(', ', $campos);

    $sql = "INSERT INTO produtos ({$camposStr}) VALUES ({$placeholders})";
    $stmt = $pdo->prepare($sql);

    // Executar inserção sem foto
    foreach ($dados as $campo => $valor) {
        $stmt->bindValue(":{$campo}", $valor);
    }

    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        error_log("Erro ao inserir produto: " . $errorInfo[2] . " - Empresa: {$id_cliente}");
        throw new Exception('Erro ao inserir produto no banco de dados: ' . $errorInfo[2]);
    }

    // Pegar o ID do produto inserido
    $produtoId = $pdo->lastInsertId();

    // 2. Se há foto, atualizar o registro com a foto
    if ($fotoProcessada && $fotoBlob !== null) {
        $sqlFoto = "UPDATE produtos SET foto = ? WHERE id = ? AND idcliente = ?";
        $stmtFoto = $pdo->prepare($sqlFoto);
        
        // Bind específico para BLOB
        $stmtFoto->bindParam(1, $fotoBlob, PDO::PARAM_LOB);
        $stmtFoto->bindParam(2, $produtoId, PDO::PARAM_INT);
        $stmtFoto->bindParam(3, $id_cliente, PDO::PARAM_INT);
        
        if (!$stmtFoto->execute()) {
            $errorInfo = $stmtFoto->errorInfo();
            error_log("Erro ao salvar foto do produto: " . $errorInfo[2] . " - Empresa: {$id_cliente}");
            throw new Exception('Erro ao salvar foto do produto: ' . $errorInfo[2]);
        }
        
        error_log("Foto salva com sucesso para produto ID: {$produtoId}, Tamanho: " . strlen($fotoBlob) . " bytes, Empresa: {$id_cliente}");
    }

    // Commit da transação
    $pdo->commit();

    // Buscar dados completos do produto inserido para retorno
    $stmt = $pdo->prepare("
        SELECT p.*, g.nome as nome_grupo 
        FROM produtos p 
        LEFT JOIN grupos g ON p.codgrupo = g.codgrupo AND p.idcliente = g.idcliente 
        WHERE p.id = ? AND p.idcliente = ?
    ");
    $stmt->execute([$produtoId, $id_cliente]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    // Preparar dados de retorno (sem o BLOB da foto)
    if (isset($produto['foto'])) {
        $produto['tem_foto'] = !empty($produto['foto']);
        $produto['foto_tamanho'] = !empty($produto['foto']) ? strlen($produto['foto']) : 0;
        unset($produto['foto']); // Remove o BLOB do retorno
    }

    // Log de sucesso
    error_log("Produto inserido com sucesso. ID: {$produtoId}, Código: {$proximoCodigo}, Empresa: {$id_cliente}" . 
              ($fotoProcessada ? ", Com foto de " . strlen($fotoBlob) . " bytes" : ", Sem foto"));

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Produto cadastrado com sucesso!' . ($fotoProcessada ? ' (com foto)' : ''),
        'produto' => $produto,
        'empresa_id' => $id_cliente,
        'debug' => [
            'foto_processada' => $fotoProcessada,
            'foto_tamanho_bytes' => $fotoProcessada ? strlen($fotoBlob) : 0,
            'metodo_usado' => 'insert_then_update_blob',
            'empresa_id' => $id_cliente
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }

    // Log do erro
    error_log("Erro ao salvar produto para empresa {$idcliente_empresa}: " . $e->getMessage());

    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'empresa_id' => $idcliente_empresa,
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
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
    error_log("Erro de banco ao salvar produto para empresa {$idcliente_empresa}: " . $e->getMessage());

    // Resposta de erro genérica (não expor detalhes do banco)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Tente novamente.',
        'empresa_id' => $idcliente_empresa,
        'debug' => [
            'error_type' => 'database_error',
            'sql_state' => $e->getCode(),
            'empresa_id' => $idcliente_empresa
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>