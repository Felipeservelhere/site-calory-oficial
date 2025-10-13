<?php
// ========================================
// API PARA SALVAR ENTRADA DE PRODUTOS
// ========================================

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
        'message' => 'Acesso negado. Faça login para continuar.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id']) || !is_numeric($_SESSION['empresa_id']) || (int)$_SESSION['empresa_id'] <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Faça login novamente.',
        'error_code' => 'INVALID_SESSION'
    ]);
    exit;
}

$idcliente = (int)$_SESSION['empresa_id']; // ADICIONADO: Define idcliente da sessão
error_log("Salvar Entrada - Iniciada para idcliente={$idcliente} em " . date('Y-m-d H:i:s'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

require_once 'config.php'; // Mantido: Assume que contém conectarBanco() e obterProximoCodigoEntrada() (ajuste abaixo)

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $pdo = conectarBanco();
    error_log("Salvar Entrada - Conexão ao BD bem-sucedida para idcliente={$idcliente} (" . date('Y-m-d H:i:s') . ")");
    $pdo->beginTransaction();
    
    // ADICIONADO: Ajustar obterProximoCodigoEntrada para usar idcliente (exemplo de implementação se não existir)
    // Se a função em config.php não filtra por idcliente, substitua ou adicione este código:
    function obterProximoCodigoEntrada($pdo, $idcliente) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(codentrada), 0) + 1 AS proximo FROM entradas WHERE idcliente = ?");
        $stmt->execute([$idcliente]);
        return $stmt->fetchColumn();
    }
    $codentrada = obterProximoCodigoEntrada($pdo, $idcliente); // Passa $pdo e $idcliente
    
    error_log("Salvar Entrada - Próximo codentrada gerado: {$codentrada} para idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    
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
    
    // Inserir entrada principal (SUBSTITUÍDO: idcliente fixo por $idcliente)
    $dadosEntrada = [
        'idcliente' => $idcliente,
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
        'codtransp' => $codtransp,
        'total_frete' => $total_frete,
        'dtnota' => $_POST['dt_emissao'] ?? null,
        'tipo_emitente' => 'T',
        'NumChaveNfe' => $_POST['chave_acesso'] ?? '',
        'nitens' => count($_POST['produtos'] ?? []),
        'nitenstot' => count($_POST['produtos'] ?? []),
        'total_nfe' => $_POST['total_geral'] ?? 0,
        'insumos' => 'N',
        'codmotorista' => null,
        'codtpdes' => $_POST['expense_type'] ?? null,
        'codcond' => $_POST['payment_condition'] ?? null,
        'codtppag' => $_POST['payment_type'] ?? null
    ];
    
    $camposEntrada = implode(', ', array_keys($dadosEntrada));
    $placeholdersEntrada = ':' . implode(', :', array_keys($dadosEntrada));
    
    $sqlEntrada = "INSERT INTO entradas ({$camposEntrada}) VALUES ({$placeholdersEntrada})";
    $stmtEntrada = $pdo->prepare($sqlEntrada);
    $stmtEntrada->execute($dadosEntrada);
    error_log("Salvar Entrada - Entrada principal inserida (codentrada={$codentrada}) para idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    
    // Inserir itens da entrada (SUBSTITUÍDO: idcliente fixo por $idcliente)
    if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
        $seq = 1;
        $itensInseridos = 0;
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
            $itensInseridos++;
            $seq++;
        }
        error_log("Salvar Entrada - {$itensInseridos} itens inseridos para codentrada={$codentrada} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    }
    
    // Inserir centros de custo (SUBSTITUÍDO: idcliente fixo por $idcliente)
    if (isset($_POST['cost_centers']) && is_array($_POST['cost_centers'])) {
        $ccInseridos = 0;
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
            $ccInseridos++;
        }
        error_log("Salvar Entrada - {$ccInseridos} centros de custo inseridos para codentrada={$codentrada} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    }
    
    // Inserir descontos (SUBSTITUÍDO: idcliente fixo por $idcliente)
    if (isset($_POST['discounts']) && is_array($_POST['discounts'])) {
        $seqDesc = 1;
        $descontosInseridos = 0;
        foreach ($_POST['discounts'] as $index => $desconto) {
            if (empty($desconto['descricao'])) continue;
            
            $dadosDesconto = [
                'idcliente' => $idcliente, 
                'codentrada' => $codentrada,
                'seq' => $seqDesc,
                'datalcto' => date('Y-m-d'),
                'descricao' => $desconto['descricao'],
                'valor' => $desconto['valor'] ?? 0
            ];
            
            $camposDesconto = implode(', ', array_keys($dadosDesconto));
            $placeholdersDesconto = ':' . implode(', :', array_keys($dadosDesconto));
            
            $sqlDesconto = "INSERT INTO entradas_descontos ({$camposDesconto}) VALUES ({$placeholdersDesconto})";
            $stmtDesconto = $pdo->prepare($sqlDesconto);
            $stmtDesconto->execute($dadosDesconto);
            $descontosInseridos++;
            $seqDesc++;
        }
        error_log("Salvar Entrada - {$descontosInseridos} descontos inseridos para codentrada={$codentrada} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    }
    
    $pdo->commit();
    error_log("Salvar Entrada - Transação commitada com sucesso para codentrada={$codentrada} e idcliente={$idcliente} em " . date('Y-m-d H:i:s'));
    
    $dataAtual = date('d/m/Y');
    
    $response = [
        'success' => true,
        'message' => "Entrada de produtos salva com sucesso em {$dataAtual}! Código: {$codentrada}",
        'data' => [
            'codentrada' => $codentrada,
            'data' => $dataAtual
        ]
    ];
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Salvar Entrada - Erro para idcliente={$idcliente} em " . date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => "Erro ao salvar entrada: " . $e->getMessage(),
        'error_code' => 'INTERNAL_ERROR',
        'data' => null
    ];
}

// Se for uma requisição AJAX, retornar JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode($response);
    exit;
}

// Caso contrário, definir variáveis para uso no PHP principal
$_SESSION['save_result'] = $response;
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>