<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json; charset=utf-8');
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
    header('Content-Type: application/json; charset=utf-8');
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
// API PARA IMPORTAR XML NFE
// ========================================

/**
 * Conecta ao banco de dados
 */
function conectarBanco() {
    global $pdo; // Usa a conexão global da classe Database
    return $pdo;
}

function produtoExiste($codigo, $idcliente) {
    try {
        $pdo = conectarBanco();
        $stmt = $pdo->prepare("SELECT codproduto FROM produtos WHERE codproduto = ? AND idcliente = ?");
        $stmt->execute([$codigo, $idcliente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        return ($result['max_cod'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Erro em obterProximoCodigoSituacaoTributaria (idcliente={$idcliente}): " . $e->getMessage());
        return 1;
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
            'idcliente' => $idcliente, // ID dinâmico da empresa
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
        $pdo = conectarBanco();
        
        // SEMPRE TENTAR CADASTRAR SITUAÇÃO TRIBUTÁRIA PRIMEIRO
        $resultadoSituacao = cadastrarSituacaoTributaria($dadosProduto, $idcliente);
        $codst = $resultadoSituacao['codst'] ?? 1;
        
        // Verificar se produto já existe
        $produtoExistente = produtoExiste($dadosProduto['codigo'], $idcliente);
        if ($produtoExistente) {
            return [
                'success' => true,
                'message' => 'Produto já cadastrado',
                'codproduto' => $dadosProduto['codigo'],
                'action' => 'existing',
                'situacao_tributaria' => $resultadoSituacao
            ];
        }
        
        // Usar o código do produto da NFE ou gerar próximo código se não existir
        $codigoProduto = $dadosProduto['codigo'];
        if (empty($codigoProduto) || !is_numeric($codigoProduto)) {
            $codigoProduto = obterProximoCodigoProduto($idcliente);
        }
        
        // DATA ATUAL - HOJE
        $dataAtual = date('Y-m-d');
        
        $dados = [
            'idcliente' => $idcliente, // ID dinâmico da empresa
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
        
        $campos = implode(', ', array_keys($dados));
        $placeholders = ':' . implode(', :', array_keys($dados));
        
        $sql = "INSERT INTO produtos ({$campos}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute($dados);
        
        return [
            'success' => true,
            'message' => 'Produto cadastrado com sucesso',
            'codproduto' => $codigoProduto,
            'action' => 'created',
            'data_cad' => $dataAtual,
            'situacao_tributaria' => $resultadoSituacao
        ];
        
    } catch (Exception $e) {
        error_log("Erro em cadastrarProduto (idcliente={$idcliente}): " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao cadastrar produto: ' . $e->getMessage(),
            'codproduto' => $dadosProduto['codigo'] ?? null,
            'action' => 'error'
        ];
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
            'idcliente' => $idcliente, // ID dinâmico da empresa
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
            'idcliente' => $idcliente, // ID dinâmico da empresa
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
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    try {
        global $idcliente;
        
        $uploadDir = '../uploads/';
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
                
                // CADASTRAR PRODUTOS AUTOMATICAMENTE
                $produtosCadastrados = 0;
                $produtosExistentes = 0;
                $produtosComErro = 0;
                $situacoesCriadas = 0;
                $situacoesExistentes = 0;
                
                foreach ($nfe_data['produtos'] as $index => $produto) {
                    $resultadoProduto = cadastrarProduto($produto, $idcliente);
                    
                    if ($resultadoProduto['success']) {
                        $nfe_data['produtos'][$index]['codigo'] = $resultadoProduto['codproduto'];
                        
                        if ($resultadoProduto['action'] === 'created') {
                            $produtosCadastrados++;
                        } elseif ($resultadoProduto['action'] === 'existing') {
                            $produtosExistentes++;
                        }
                        
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
                
                $dataAtual = date('d/m/Y');
                $mensagem = "NFE importada com sucesso em {$dataAtual}! ";
                
                if (strpos($resultadoCadastro['message'], 'já cadastrado') !== false) {
                    $mensagem .= "Fornecedor já estava cadastrado (Código: {$resultadoCadastro['codcliente']}).";
                } else {
                    $mensagem .= "Fornecedor cadastrado automaticamente (Código: {$resultadoCadastro['codcliente']}).";
                }
                
                if ($produtosCadastrados > 0) {
                    $mensagem .= " {$produtosCadastrados} produto(s) cadastrado(s) automaticamente.";
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
                
                $response = [
                    'success' => true,
                    'message' => $mensagem,
                    'data' => $nfe_data
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => "NFE importada com sucesso, mas houve erro ao cadastrar fornecedor: " . $resultadoCadastro['message'],
                    'data' => $nfe_data
                ];
            }
        } else {
            throw new Exception("Erro ao fazer upload do arquivo.");
        }
    } catch (Exception $e) {
        error_log("Erro ao processar NFE (idcliente={$idcliente}): " . $e->getMessage());
        $response = [
            'success' => false,
            'message' => "Erro ao importar NFE: " . $e->getMessage(),
            'data' => null
        ];
    }
    
    // Se for uma requisição AJAX, retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Caso contrário, definir variáveis para uso no PHP principal
    $_SESSION['import_result'] = $response;
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>