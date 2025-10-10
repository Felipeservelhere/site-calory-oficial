<<?php
session_start();

// ==================== VERIFICAÇÃO DE LOGIN ====================
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['msg'] = "Acesso negado. Faça login para continuar.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");  // 👈 Ajuste o caminho para login se necessário
    exit;
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    session_destroy();
    $_SESSION['msg'] = "Sessão inválida. Faça login novamente.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO LOGIN (para validar empresa_id/idcliente) ====================
require_once '../config/databaselogin.php';

try {
    $dbLogin = new DatabaseLogin();  // 👈 Classe correta para databaselogin.php
    $connlogin = $dbLogin->getConnection();
    
    if (!$connlogin) {
        throw new Exception('Falha na conexão com DB de autenticação (frutnorte). Verifique credenciais em databaselogin.php.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de autenticação: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Buscar empresa_id (idcliente da empresa logada) do usuário autenticado (sem filtro de cargo para acesso básico)
try {
    $stmt = $connlogin->prepare("SELECT empresa_id FROM usuarios WHERE id = ? AND status = 1");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data || empty($admin_data['empresa_id'])) {
        session_destroy();
        $_SESSION['msg'] = "Erro de autenticação. Acesso negado.";
        $_SESSION['msg_type'] = "error";
        header("Location: ../login.php");
        exit;
    }

    $idcliente_empresa = $admin_data['empresa_id'];  // 👈 ID da empresa logada (idcliente da empresa)
    $_SESSION['empresa_id'] = $idcliente_empresa;
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na validação de usuário: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// ==================== CONEXÃO SISTEMA (para operações de edição) ====================
require_once '../config/database.php';

try {
    $database = new Database();  // 👈 Classe correta para database.php
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha na conexão com DB operacional (empresaweb). Verifique credenciais em database.php.');
    }
    
} catch (Exception $e) {
    $_SESSION['msg'] = 'Erro na conexão de dados: ' . $e->getMessage();
    $_SESSION['msg_type'] = "error";
    header("Location: ../login.php");
    exit;
}

// Verificar se foi passado um ID e codcliente
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['msg'] = "ID do cliente não informado.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../clientes.php?mensagem=selecione_cliente');
    exit;
}
if (!isset($_GET['codcliente']) || empty($_GET['codcliente'])) {
    $_SESSION['msg'] = "Código do cliente não informado.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../clientes.php?mensagem=selecione_cliente');
    exit;
}

$cliente_id = (int)$_GET['id'];
$cliente_cod = (int)$_GET['codcliente'];

// Buscar dados do cliente (com filtro por idcliente da empresa logada)
try {
    // Buscar cliente principal (filtrado por empresa)
    $sql = "SELECT * FROM clientes WHERE id = ? AND codcliente = ? AND idcliente = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id, $cliente_cod, $idcliente_empresa]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        $_SESSION['msg'] = "Cliente não encontrado ou sem permissão para acessar este cliente.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../clientes.php?erro=cliente_nao_encontrado');
        exit;
    }
    
    // Verificar se o idcliente do cliente bate com a empresa logada (segurança extra)
    // Verificar se o idcliente do cliente bate com a empresa logada (segurança extra)
    if ($cliente['idcliente'] != $idcliente_empresa) {
        $_SESSION['msg'] = "Acesso negado. Cliente não pertence à sua empresa.";
        $_SESSION['msg_type'] = "error";
        header('Location: ../clientes.php?erro=acesso_negado');
        exit;
    }
    
    // 👈 CORREÇÃO: Mapeamento de tipocliente (numérico do banco para string do select)
    $tipocliente_map = [
        1 => 'cliente',
        3 => 'fornecedor',
        4 => 'outro'
        // Adicione mais mapeamentos se houver outros valores no banco (ex: 2 => 'algum_outro')
    ];
    if (isset($tipocliente_map[$cliente['tipocliente']])) {
        $cliente['tipocliente'] = $tipocliente_map[$cliente['tipocliente']];
    } else {
        // Valor padrão se não mapear (ex: se for 0 ou nulo, assume 'cliente')
        $cliente['tipocliente'] = 'cliente';
    }
    
    // Buscar contas bancárias do cliente (filtrado por empresa)
    $sql_contas = "SELECT * FROM contas_clientes WHERE codcliente = ? AND idcliente = ? ORDER BY id";
    $stmt_contas = $pdo->prepare($sql_contas);
    $stmt_contas->execute([$cliente_cod, $idcliente_empresa]);
    $contas_bancarias = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro PDO ao buscar cliente ID $cliente_id: " . $e->getMessage());
    $_SESSION['msg'] = "Erro no banco de dados ao buscar cliente.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../clientes.php?erro=erro_banco_dados');
    exit;
} catch (Exception $e) {
    error_log("Erro geral ao buscar cliente ID $cliente_id: " . $e->getMessage());
    $_SESSION['msg'] = "Erro interno do servidor.";
    $_SESSION['msg_type'] = "error";
    header('Location: ../clientes.php?erro=erro_banco_dados');
    exit;
}

$nascimentoFormatado = '';
if (isset($cliente['nascimento']) && !empty($cliente['nascimento'])) {
    $nascimento = strtotime($cliente['nascimento']);
    if ($nascimento !== false) {
        $nascimentoFormatado = date('Y-m-d', $nascimento);
    }
}

include '../includes/menu.php';
?>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div class="main-content">
    <div class="content-wrapper">
        
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../index.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <span class="breadcrumb-separator">/</span>
                <a href="../clientes.php" class="breadcrumb-item breadcrumb-link">
                    <i class="fas fa-users"></i>
                    Clientes
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Editar Cliente</span>
            </div>
            <h1 class="page-title">
                <div class="title-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="title-content">
                    <span class="title-main">Editar Cliente</span>
                    <p class="title-subtitle">Código: <?= str_pad($cliente['codcliente'], 4, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($cliente['Nome']) ?></p>
                </div>
            </h1>
        </div>

        <div id="toast-container" class="toast-container"></div>

        <div class="form-container">
            <form id="clienteForm" class="client-form">
                
                <!-- Seção: Informações Principais -->
                <div class="main-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i>
                            Informações Principais
                        </h2>
                        <p class="section-subtitle">Dados essenciais do cliente</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tipo_pessoa" class="form-label">
                                Tipo de Pessoa <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <select id="tipo_pessoa" name="tipo_pessoa" class="form-input" required>
                                    <option value="F" <?= $cliente['tipo_pessoa'] === 'F' ? 'selected' : '' ?>>Pessoa Física</option>
                                    <option value="J" <?= $cliente['tipo_pessoa'] === 'J' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                                </select>
                                <i class="fas fa-user-tag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tipocliente" class="form-label">
                                Tipo de Cliente <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <select id="tipocliente" name="tipocliente" class="form-input" required>
                                    <option value="cliente" <?= $cliente['tipocliente'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                                    <option value="fornecedor" <?= $cliente['tipocliente'] === 'fornecedor' ? 'selected' : '' ?>>Fornecedor</option>
                                    <option value="outro" <?= $cliente['tipocliente'] === 'outro' ? 'selected' : '' ?>>Outro</option>
                                </select>
                                <i class="fas fa-briefcase input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group form-group-wide">
                            <label for="Nome" class="form-label">
                                Nome / Razão Social <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="Nome" name="Nome" class="form-input" maxlength="60" required value="<?= htmlspecialchars($cliente['Nome']) ?>">
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Fantasia" class="form-label">Nome Fantasia</label>
                            <div class="input-wrapper">
                                <input type="text" id="Fantasia" name="Fantasia" class="form-input" maxlength="30" value="<?= htmlspecialchars($cliente['Fantasia']) ?>">
                                <i class="fas fa-store input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cnpj_cpf" class="form-label">
                                CPF / CNPJ <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="cnpj_cpf" name="cnpj_cpf" class="form-input" maxlength="20" required value="<?= htmlspecialchars($cliente['cnpj_cpf']) ?>">
                                <i class="fas fa-id-card input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Email" class="form-label">
                                E-mail <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="email" id="Email" name="Email" class="form-input" maxlength="100" required value="<?= htmlspecialchars($cliente['Email']) ?>">
                                <i class="fas fa-envelope input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="celular" class="form-label">Celular</label>
                            <div class="input-wrapper">
                                <input type="tel" id="celular" name="celular" class="form-input" maxlength="20" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($cliente['celular']) ?>">
                                <i class="fas fa-mobile-alt input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Endereço -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Endereço
                        </h2>
                        <p class="section-subtitle">Localização e entrega</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="CEP" class="form-label">CEP</label>
                            <div class="input-wrapper">
                                <input type="text" id="CEP" name="CEP" class="form-input" maxlength="15" placeholder="00000-000" value="<?= htmlspecialchars($cliente['CEP']) ?>">
                                <i class="fas fa-search input-icon"></i>
                                <div class="input-loading" id="cep-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group form-group-wide">
                            <label for="Endereco" class="form-label">Endereço</label>
                            <div class="input-wrapper">
                                <input type="text" id="Endereco" name="Endereco" class="form-input" maxlength="60" value="<?= htmlspecialchars($cliente['Endereco']) ?>">
                                <i class="fas fa-road input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero" class="form-label">Número</label>
                            <div class="input-wrapper">
                                <input type="text" id="numero" name="numero" class="form-input" maxlength="10" value="<?= htmlspecialchars($cliente['numero']) ?>">
                                <i class="fas fa-hashtag input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="complemento" class="form-label">Complemento</label>
                            <div class="input-wrapper">
                                <input type="text" id="complemento" name="complemento" class="form-input" maxlength="30" value="<?= htmlspecialchars($cliente['complemento']) ?>">
                                <i class="fas fa-plus input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Bairro" class="form-label">Bairro</label>
                            <div class="input-wrapper">
                                <input type="text" id="Bairro" name="Bairro" class="form-input" maxlength="30" value="<?= htmlspecialchars($cliente['Bairro']) ?>">
                                <i class="fas fa-map input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Cidade" class="form-label">Cidade</label>
                            <div class="input-wrapper">
                                <input type="text" id="Cidade" name="Cidade" class="form-input" maxlength="60" value="<?= htmlspecialchars($cliente['Cidade']) ?>">
                                <i class="fas fa-city input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="Uf" class="form-label">Estado (UF)</label>
                            <div class="input-wrapper">
                                <select id="Uf" name="Uf" class="form-input">
                                    <option value="">Selecione...</option>
                                    <option value="AC" <?= $cliente['Uf'] === 'AC' ? 'selected' : '' ?>>Acre</option>
                                    <option value="AL" <?= $cliente['Uf'] === 'AL' ? 'selected' : '' ?>>Alagoas</option>
                                    <option value="AP" <?= $cliente['Uf'] === 'AP' ? 'selected' : '' ?>>Amapá</option>
                                    <option value="AM" <?= $cliente['Uf'] === 'AM' ? 'selected' : '' ?>>Amazonas</option>
                                    <option value="BA" <?= $cliente['Uf'] === 'BA' ? 'selected' : '' ?>>Bahia</option>
                                    <option value="CE" <?= $cliente['Uf'] === 'CE' ? 'selected' : '' ?>>Ceará</option>
                                    <option value="DF" <?= $cliente['Uf'] === 'DF' ? 'selected' : '' ?>>Distrito Federal</option>
                                    <option value="ES" <?= $cliente['Uf'] === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="GO" <?= $cliente['Uf'] === 'GO' ? 'selected' : '' ?>>Goiás</option>
                                    <option value="MA" <?= $cliente['Uf'] === 'MA' ? 'selected' : '' ?>>Maranhão</option>
                                    <option value="MT" <?= $cliente['Uf'] === 'MT' ? 'selected' : '' ?>>Mato Grosso</option>
                                    <option value="MS" <?= $cliente['Uf'] === 'MS' ? 'selected' : '' ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?= $cliente['Uf'] === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="PA" <?= $cliente['Uf'] === 'PA' ? 'selected' : '' ?>>Pará</option>
                                    <option value="PB" <?= $cliente['Uf'] === 'PB' ? 'selected' : '' ?>>Paraíba</option>
                                    <option value="PR" <?= $cliente['Uf'] === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                    <option value="PE" <?= $cliente['Uf'] === 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                                    <option value="PI" <?= $cliente['Uf'] === 'PI' ? 'selected' : '' ?>>Piauí</option>
                                    <option value="RJ" <?= $cliente['Uf'] === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="RN" <?= $cliente['Uf'] === 'RN' ? 'selected' : '' ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?= $cliente['Uf'] === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?= $cliente['Uf'] === 'RO' ? 'selected' : '' ?>>Rondônia</option>
                                    <option value="RR" <?= $cliente['Uf'] === 'RR' ? 'selected' : '' ?>>Roraima</option>
                                    <option value="SC" <?= $cliente['Uf'] === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="SP" <?= $cliente['Uf'] === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="SE" <?= $cliente['Uf'] === 'SE' ? 'selected' : '' ?>>Sergipe</option>
                                    <option value="TO" <?= $cliente['Uf'] === 'TO' ? 'selected' : '' ?>>Tocantins</option>
                                </select>
                                <i class="fas fa-flag input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pais" class="form-label">País</label>
                            <div class="input-wrapper">
                                <input type="text" id="pais" name="pais" class="form-input" maxlength="60" value="<?= htmlspecialchars($cliente['pais'] ?: 'BRASIL') ?>">
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Documentos -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Documentos e Inscrições
                        </h2>
                        <p class="section-subtitle">Inscrições e registros</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="IE" class="form-label">Inscrição Estadual</label>
                            <div class="input-wrapper">
                                <input type="text" id="IE" name="IE" class="form-input" maxlength="20" value="<?= htmlspecialchars($cliente['IE']) ?>">
                                <i class="fas fa-file-alt input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="IM" class="form-label">Inscrição Municipal</label>
                            <div class="input-wrapper">
                                <input type="text" id="IM" name="IM" class="form-input" maxlength="30" value="<?= htmlspecialchars($cliente['IM']) ?>">
                                <i class="fas fa-building input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="insc_rural" class="form-label">Inscrição Rural</label>
                            <div class="input-wrapper">
                                <input type="text" id="insc_rural" name="insc_rural" class="form-input" maxlength="14" value="<?= htmlspecialchars($cliente['insc_rural']) ?>">
                                <i class="fas fa-tractor input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="insc_suframa" class="form-label">Inscrição SUFRAMA</label>
                            <div class="input-wrapper">
                                <input type="text" id="insc_suframa" name="insc_suframa" class="form-input" maxlength="15" value="<?= htmlspecialchars($cliente['insc_suframa']) ?>">
                                <i class="fas fa-globe input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nascimento" class="form-label">Data de Nascimento</label>
                            <div class="input-wrapper">
                                <input type="date" id="nascimento" name="nascimento" class="form-input" value="<?= htmlspecialchars($nascimentoFormatado) ?>">
                                <i class="fas fa-calendar input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Fone" class="form-label">Telefone</label>
                            <div class="input-wrapper">
                                <input type="tel" id="Fone" name="Fone" class="form-input" maxlength="20" placeholder="(00) 0000-0000" value="<?= htmlspecialchars($cliente['Fone']) ?>">
                                <i class="fas fa-phone input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Contato" class="form-label">Pessoa de Contato</label>
                            <div class="input-wrapper">
                                <input type="text" id="Contato" name="Contato" class="form-input" maxlength="30" value="<?= htmlspecialchars($cliente['Contato']) ?>">
                                <i class="fas fa-user-friends input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Dados Comerciais -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-briefcase"></i>
                            Informações Comerciais
                        </h2>
                        <p class="section-subtitle">Condições e limites</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="CondPgto" class="form-label">Condição de Pagamento</label>
                            <div class="input-wrapper">
                                <input type="text" id="CondPgto" name="CondPgto" class="form-input" maxlength="20" placeholder="Ex: 30/60/90 dias" value="<?= htmlspecialchars($cliente['CondPgto']) ?>">
                                <i class="fas fa-credit-card input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Transportadora" class="form-label">Transportadora</label>
                            <div class="input-wrapper">
                                <input type="text" id="Transportadora" name="Transportadora" class="form-input" maxlength="40" value="<?= htmlspecialchars($cliente['Transportadora']) ?>">
                                <i class="fas fa-truck input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="PercDesconto" class="form-label">% Desconto</label>
                            <div class="input-wrapper">
                                <input type="number" id="PercDesconto" name="PercDesconto" class="form-input" step="0.01" min="0" max="100" value="<?= $cliente['PercDesconto'] ?>">
                                <i class="fas fa-percentage input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="limite" class="form-label">Limite de Crédito (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="limite" name="limite" class="form-input" step="0.01" min="0" value="<?= $cliente['limite'] ?>">
                                <i class="fas fa-dollar-sign input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="saldo_devedor" class="form-label">Saldo Devedor (R$)</label>
                            <div class="input-wrapper">
                                <input type="number" id="saldo_devedor" name="saldo_devedor" class="form-input" step="0.01" min="0" value="<?= $cliente['saldo_devedor'] ?>">
                                <i class="fas fa-balance-scale input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="codvendedor" class="form-label">Código do Vendedor</label>
                            <div class="input-wrapper">
                                <input type="number" id="codvendedor" name="codvendedor" class="form-input" min="1" value="<?= $cliente['codvendedor'] ?>">
                                <i class="fas fa-user-tie input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Contas Bancárias -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-university"></i>
                            Contas Bancárias
                        </h2>
                        <p class="section-subtitle">Dados bancários e PIX</p>
                    </div>
                    
                    <div class="contas-bancarias-section">
                        <div class="contas-header">
                            <button type="button" class="btn btn-primary btn-sm" onclick="adicionarNovaConta()">
                                <i class="fas fa-plus"></i>
                                Nova Conta Bancária
                            </button>
                        </div>
                        
                        <div class="contas-lista" id="contas-lista">
                            <!-- Lista de contas será carregada aqui -->
                        </div>

                        <!-- Formulário para nova conta -->
                        <div class="nova-conta-form" id="nova-conta-form" style="display: none;">
                            <h4><i class="fas fa-plus-circle"></i> Nova Conta Bancária</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="novo-tipoconta" class="form-label">Tipo de Conta <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <select id="novo-tipoconta" name="novo-tipoconta" class="form-input">
                                            <option value="">Selecione...</option>
                                            <option value="Conta Corrente">Conta Corrente</option>
                                            <option value="Poupança">Poupança</option>
                                            <option value="Conta Salário">Conta Salário</option>
                                            <option value="Conta Investimento">Conta Investimento</option>
                                        </select>
                                        <i class="fas fa-university input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="novo-banco" class="form-label">Banco <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-banco" name="novo-banco" class="form-input" maxlength="100" placeholder="Nome do banco">
                                        <i class="fas fa-building input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="novo-agencia" class="form-label">Agência <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-agencia" name="novo-agencia" class="form-input" maxlength="20" placeholder="0000">
                                        <i class="fas fa-code-branch input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="novo-nconta" class="form-label">Número da Conta <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-nconta" name="novo-nconta" class="form-input" maxlength="20" placeholder="00000-0">
                                        <i class="fas fa-hashtag input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="novo-chavepix" class="form-label">Chave PIX</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-chavepix" name="novo-chavepix" class="form-input" maxlength="100" placeholder="CPF, e-mail, celular ou chave aleatória">
                                        <i class="fas fa-key input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="novo-cpf_titular" class="form-label">CPF do Titular</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-cpf_titular" name="novo-cpf_titular" class="form-input cpf-mask" maxlength="14" placeholder="000.000.000-00">
                                        <i class="fas fa-id-card input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group form-group-wide">
                                    <label for="novo-nome_titular" class="form-label">Nome do Titular</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="novo-nome_titular" name="novo-nome_titular" class="form-input" maxlength="100" placeholder="Nome completo do titular da conta">
                                        <i class="fas fa-user input-icon"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="cancelarNovaConta()">
                                    <i class="fas fa-times"></i>
                                    Cancelar
                                </button>
                                <button type="button" class="btn btn-success" onclick="salvarNovaConta()">
                                    <i class="fas fa-save"></i>
                                    Salvar Conta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Configurações -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-cogs"></i>
                            Configurações Especiais
                        </h2>
                        <p class="section-subtitle">Opções especiais</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="NotaKG" class="form-label">Nota por KG</label>
                            <div class="input-wrapper">
                                <select id="NotaKG" name="NotaKG" class="form-input">
                                    <option value="S" <?= $cliente['NotaKG'] === 'S' ? 'selected' : '' ?>>Sim</option>
                                    <option value="N" <?= $cliente['NotaKG'] === 'N' ? 'selected' : '' ?>>Não</option>
                                </select>
                                <i class="fas fa-weight input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pdesconto_boleto" class="form-label">% Desconto Boleto</label>
                            <div class="input-wrapper">
                                <input type="number" id="pdesconto_boleto" name="pdesconto_boleto" class="form-input" step="0.01" min="0" max="100" value="<?= $cliente['pdesconto_boleto'] ?: '0' ?>">
                                <i class="fas fa-file-invoice input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="protesto_automatico_boletos" class="form-label">Protesto Automático</label>
                            <div class="input-wrapper">
                                <select id="protesto_automatico_boletos" name="protesto_automatico_boletos" class="form-input">
                                    <option value="N" <?= $cliente['protesto_automatico_boletos'] === 'N' ? 'selected' : '' ?>>Não</option>
                                    <option value="S" <?= $cliente['protesto_automatico_boletos'] === 'S' ? 'selected' : '' ?>>Sim</option>
                                </select>
                                <i class="fas fa-exclamation-triangle input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dias_protesto" class="form-label">Dias para Protesto</label>
                            <div class="input-wrapper">
                                <input type="number" id="dias_protesto" name="dias_protesto" class="form-input" min="1" max="365" value="<?= $cliente['dias_protesto'] ?: '5' ?>">
                                <i class="fas fa-calendar-times input-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ativo" class="form-label">Status</label>
                            <div class="input-wrapper">
                                <select id="ativo" name="ativo" class="form-input">
                                    <option value="S" <?= $cliente['ativo'] === 'S' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="N" <?= $cliente['ativo'] === 'N' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                                <i class="fas fa-toggle-on input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Observações -->
                <div class="obs-section">
                    <label for="obs" class="form-label">
                        <i class="fas fa-sticky-note"></i>
                        Observações
                    </label>
                    <textarea id="obs" name="obs" class="form-textarea" rows="3" maxlength="255" placeholder="Informações adicionais sobre o cliente..."><?= htmlspecialchars($cliente['obs']) ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="../clientes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                    <button type="button" class="btn btn-info" onclick="resetarFormulario()">
                        <i class="fas fa-undo"></i>
                        Resetar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>

                <!-- Campos hidden -->
                <input type="hidden" id="id" name="id" value="<?= $cliente['id'] ?>">
                <input type="hidden" id="idcliente" name="idcliente" value="<?= $cliente['idcliente'] ?>">
                <input type="hidden" id="codcliente" name="codcliente" value="<?= $cliente['codcliente'] ?>">
                <input type="hidden" id="Data_cad" name="Data_cad" value="<?= $cliente['Data_cad'] ?>">
            </form>
        </div>
    </div>
</div>

<style>
/* Complete CSS styles for the edit client form */
* {
    box-sizing: border-box;
}

.main-content {
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;
}

.content-wrapper {
    margin-top: 10px !important;
    max-width: 1443px;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.page-header {
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    color: white;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    pointer-events: none;
}

.breadcrumb {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    font-size: 14px;
    opacity: 0.9;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.breadcrumb-link {
    color: white;
    text-decoration: none;
    transition: all 0.2s ease;
    padding: 4px 8px;
    border-radius: 6px;
}

.breadcrumb-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #f0fdf4;
    transform: translateY(-1px);
}

.breadcrumb-separator {
    margin: 0 8px;
    opacity: 0.7;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    margin: 0;
    position: relative;
    z-index: 1;
}

.title-icon {
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

.title-main {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 2px;
}

.title-subtitle {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
}

/* Container principal */
.form-container {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

/* Seções */
.main-section,
.section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.section-header {
    margin-bottom: 24px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #fcd34d;
}

.section-subtitle {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

/* Grid de formulário */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    align-items: start;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group-wide {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.required {
    color: #ef4444;
    font-weight: 700;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    background: white;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #fcd34d;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: #eff6ff;
}

.input-icon {
    position: absolute;
    left: 14px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
}

.input-loading {
    position: absolute;
    right: 14px;
    color: #fcd34d;
    display: none;
}

.input-loading.active {
    display: block;
}

/* Seção de contas bancárias */
.contas-bancarias-section {
    margin-top: 20px;
}

.contas-header {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
}

.contas-lista {
    margin-bottom: 20px;
}

.conta-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conta-item.database {
    border-left: 4px solid #10b981;
    background: #f0fdf4;
}

.conta-item.session {
    border-left: 4px solid #f59e0b;
    background: #fffbeb;
}

.conta-info {
    flex: 1;
}

.conta-tipo {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.conta-origem {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    font-weight: 600;
}

.conta-origem.database {
    background: #d1fae5;
    color: #065f46;
}

.conta-origem.session {
    background: #fef3c7;
    color: #92400e;
}

.conta-detalhes {
    font-size: 13px;
    color: #64748b;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.conta-actions {
    display: flex;
    gap: 8px;
}

.nova-conta-form {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.nova-conta-form h4 {
    margin: 0 0 16px 0;
    color: #1e40af;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Seção de observações */
.obs-section {
    padding: 32px;
    border-bottom: 1px solid #f1f5f9;
}

.obs-section .form-label {
    font-size: 16px;
    margin-bottom: 8px;
}

.form-textarea {
    padding-left: 16px;
    resize: vertical;
    min-height: 80px;
}

/* Botões de ação */
.form-actions {
    padding: 24px 32px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #facc15 0%, #fcd34d 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #fcd34d 0%, #fcd34d 100%);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #374151;
}

.btn-info {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.btn-info:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    box-shadow: 0 4px 16px rgba(245, 158, 11, 0.4);
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4);
    transform: translateY(-1px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 12px;
}

/* Toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #3b82f6;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 280px;
    animation: slideInRight 0.3s ease;
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

/* Estados de loading */
.btn.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn.loading::after {
    content: '';
    width: 14px;
    height: 14px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsividade */
@media (max-width: 768px) {
    .main-content {
        padding: 16px;
    }
    
    .page-header {
        padding: 20px 24px;
    }
    
    .title-main {
        font-size: 20px;
    }
    
    .main-section,
    .section,
    .obs-section {
        padding: 24px 20px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        padding: 20px;
        flex-direction: column;
    }

    .conta-detalhes {
        flex-direction: column;
        gap: 4px;
    }
}
</style>


<script>
// Global variables and functions
let showToast;
let dadosOriginais = {};

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('clienteForm');
    
    // Armazenar dados originais para reset
    armazenarDadosOriginais();
    
    // Máscaras de input
    setupMasks();
    
    // Busca de CEP
    setupCEPSearch();
    
    // Validação de formulário
    setupValidation();
    
    // Submit do formulário
    form.addEventListener('submit', handleFormSubmit);
    
    // Carregar contas bancárias
    carregarContasBancarias();
    
    function armazenarDadosOriginais() {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            dadosOriginais[input.id || input.name] = input.value;
        });
    }
    
    function getClienteIdFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    function getClienteCodFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get('codcliente');
    }
    
    function setupMasks() {
        const masks = {
            cpfCnpj: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length <= 11) {
                    return numbers.replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                } else {
                    return numbers.replace(/(\d{2})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1/$2')
                                 .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                }
            },
            phone: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length <= 10) {
                    return numbers.replace(/(\d{2})(\d)/, '($1) $2')
                                 .replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    return numbers.replace(/(\d{2})(\d)/, '($1) $2')
                                 .replace(/(\d{5})(\d)/, '$1-$2');
                }
            },
            cep: (value) => {
                return value.replace(/\D/g, '')
                           .replace(/(\d{5})(\d)/, '$1-$2');
            }
        };
        
        document.getElementById('cnpj_cpf').addEventListener('input', function(e) {
            e.target.value = masks.cpfCnpj(e.target.value);
        });
        
        ['Fone', 'celular'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function(e) {
                    e.target.value = masks.phone(e.target.value);
                });
            }
        });
        
        document.getElementById('CEP').addEventListener('input', function(e) {
            e.target.value = masks.cep(e.target.value);
        });
    }
    
    function setupCEPSearch() {
        document.getElementById('CEP').addEventListener('blur', async function() {
            const cep = this.value.replace(/\D/g, '');
            if (cep.length === 8) {
                await buscarEnderecoPorCEP(cep);
            }
        });
    }
    
    async function buscarEnderecoPorCEP(cep) {
        const loading = document.getElementById('cep-loading');
        loading.classList.add('active');
        
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();
            
            if (!data.erro) {
                document.getElementById('Endereco').value = data.logradouro || '';
                document.getElementById('Bairro').value = data.bairro || '';
                document.getElementById('Cidade').value = data.localidade || '';
                document.getElementById('Uf').value = data.uf || '';
                
                showToast('Endereço encontrado e preenchido automaticamente!');
            } else {
                showToast('CEP não encontrado', 'warning');
            }
        } catch (error) {
            showToast('Erro ao buscar CEP', 'error');
        } finally {
            loading.classList.remove('active');
        }
    }
    
    function setupValidation() {
        const validators = {
            cpfCnpj: (value) => {
                const numbers = value.replace(/\D/g, '');
                if (numbers.length === 11) {
                    return validarCPF(numbers);
                } else if (numbers.length === 14) {
                    return validarCNPJ(numbers);
                }
                return false;
            },
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            required: (value) => value.trim() !== ''
        };
        
        function validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            
            if (field.hasAttribute('required') && !validators.required(value)) {
                isValid = false;
            }
            
            if (value && field.id === 'cnpj_cpf' && !validators.cpfCnpj(value)) {
                isValid = false;
            }
            
            if (value && field.type === 'email' && !validators.email(value)) {
                isValid = false;
            }
            
            field.style.borderColor = isValid ? '#e5e7eb' : '#ef4444';
            field.style.background = isValid ? 'white' : '#fef2f2';
            
            return isValid;
        }
        
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }
    
    async function handleFormSubmit(e) {
        e.preventDefault();

        const formData = collectAllFormData();

        if (!validateFormData(formData)) {
            showToast('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }

        const clienteId = getClienteIdFromURL();
        const codcliente = getClienteCodFromURL();

        if (!clienteId) {
            showToast('ID do cliente não encontrado.', 'error');
            return;
        }

        formData.id = clienteId;
        if (codcliente) {
            formData.codcliente = codcliente;
        }

        await atualizarCliente(formData);
    }
    
    function collectAllFormData() {
        const form = document.getElementById('clienteForm');
        const formData = new FormData(form);
        
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
    
    function validateFormData(data) {
        const required = ['Nome', 'Email', 'cnpj_cpf', 'tipo_pessoa', 'tipocliente'];
        let isValid = true;
        
        for (const field of required) {
            if (!data[field] || data[field].toString().trim() === '') {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) {
                    input.style.borderColor = '#ef4444';
                    input.style.background = '#fef2f2';
                }
                isValid = false;
            }
        }
        
        if (data.cnpj_cpf && !validarCpfCnpj(data.cnpj_cpf)) {
            document.getElementById('cnpj_cpf').style.borderColor = '#ef4444';
            showToast('CPF/CNPJ inválido', 'error');
            isValid = false;
        }
        
        if (data.Email && !validarEmail(data.Email)) {
            document.getElementById('Email').style.borderColor = '#ef4444';
            showToast('E-mail inválido', 'error');
            isValid = false;
        }
        
        return isValid;
    }
    
    function validarCpfCnpj(valor) {
        const numbers = valor.replace(/\D/g, '');
        if (numbers.length === 11) {
            return validarCPF(numbers);
        } else if (numbers.length === 14) {
            return validarCNPJ(numbers);
        }
        return false;
    }
    
    function validarCPF(cpf) {
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(9))) return false;
        
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        return resto === parseInt(cpf.charAt(10));
    }
    
    function validarCNPJ(cnpj) {
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        
        let tamanho = cnpj.length - 2;
        let numeros = cnpj.substring(0, tamanho);
        const digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i)) * pos--;
            if (pos < 2) pos = 9;
        }
        
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(0))) return false;
        
        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(numeros.charAt(tamanho - i)) * pos--;
            if (pos < 2) pos = 9;
        }
        
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        return resultado === parseInt(digitos.charAt(1));
    }
    
    function validarEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    async function atualizarCliente(formData) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('../api_clientes/atualizar_cliente.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                await fetch('../api_clientes/contas_bancarias_edit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'limpar'
                    })
                });
                
                showToast(`Cliente atualizado com sucesso! ${result.bank_accounts_added > 0 ? `Adicionadas ${result.bank_accounts_added} conta(s).` : ''} Redirecionando...`, 'success');
                
                setTimeout(() => {
                    window.location.href = '../clientes.php';
                }, 1500);
                
            } else {
                showToast(result.message || 'Erro ao atualizar cliente', 'error');
            }
        } catch (error) {
            showToast('Erro de conexão com o servidor', 'error');
        } finally {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    }
    
    // Define showToast function globally
    showToast = function(message, type = 'success') {
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
    
    // Função global para resetar formulário
    window.resetarFormulario = function() {
        if (confirm('Tem certeza que deseja resetar todos os campos para os valores originais?')) {
            Object.keys(dadosOriginais).forEach(key => {
                const field = document.getElementById(key) || document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = dadosOriginais[key];
                }
            });
            
            fetch('../api_clientes/contas_bancarias_edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'limpar'
                })
            });
            
            showToast('Formulário resetado para os valores originais!', 'success');
        }
    };
});

// Funções para gerenciar contas bancárias
async function carregarContasBancarias() {
    const lista = document.getElementById('contas-lista');
    const codcliente = getClienteCodFromURL();
    
    try {
        const response = await fetch(`../api_clientes/contas_bancarias_edit.php?action=listar&codcliente=${codcliente}`);
        const result = await response.json();

        if (result.success) {
            const contas = result.contas || [];
            
            if (contas.length === 0) {
                lista.innerHTML = '<p style="text-align: center; color: #64748b; padding: 20px;">Nenhuma conta bancária adicionada.</p>';
            } else {
                lista.innerHTML = contas.map(conta => `
                    <div class="conta-item ${conta.origem}">
                        <div class="conta-info">
                            <div class="conta-tipo">
                                ${conta.tipoconta}
                                <span class="conta-origem ${conta.origem}">
                                    ${conta.origem === 'database' ? 'Salvo' : 'Novo'}
                                </span>
                            </div>
                            <div class="conta-detalhes">
                                <span><strong>Banco:</strong> ${conta.banco}</span>
                                <span><strong>Agência:</strong> ${conta.agencia}</span>
                                <span><strong>Conta:</strong> ${conta.nconta}</span>
                                ${conta.chavepix ? `<span><strong>PIX:</strong> ${conta.chavepix}</span>` : ''}
                                ${conta.nome_titular ? `<span><strong>Titular:</strong> ${conta.nome_titular}</span>` : ''}
                            </div>
                        </div>
                        <div class="conta-actions">
                            <button type="button" class="btn btn-sm btn-danger" onclick="excluirConta('${conta.id}')">
                                <i class="fas fa-trash"></i>
                                ${conta.origem === 'database' ? 'Marcar p/ Exclusão' : 'Remover'}
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        } else {
            lista.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 20px;">Erro ao carregar contas bancárias.</p>';
        }
    } catch (error) {
        lista.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 20px;">Erro de conexão.</p>';
    }
}

function getClienteCodFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('codcliente');
}

window.salvarNovaConta = async function() {
    const dados = {
        tipoconta: document.getElementById('novo-tipoconta').value,
        banco: document.getElementById('novo-banco').value,
        agencia: document.getElementById('novo-agencia').value,
        nconta: document.getElementById('novo-nconta').value,
        chavepix: document.getElementById('novo-chavepix').value,
        cpf_titular: document.getElementById('novo-cpf_titular').value,
        nome_titular: document.getElementById('novo-nome_titular').value
    };
    
    if (!dados.tipoconta || !dados.banco || !dados.agencia || !dados.nconta) {
        showToast('Preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    if (dados.cpf_titular && !validarCPF(dados.cpf_titular)) {
        showToast('CPF do titular inválido. Verifique e tente novamente.', 'error');
        document.getElementById('novo-cpf_titular').focus();
        return;
    }
    
    try {
        const response = await fetch('../api_clientes/contas_bancarias_edit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'salvar',
                tipoconta: dados.tipoconta,
                banco: dados.banco,
                agencia: dados.agencia,
                nconta: dados.nconta,
                chavepix: dados.chavepix,
                cpf_titular: dados.cpf_titular,
                nome_titular: dados.nome_titular
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Conta bancária adicionada com sucesso! Total: ${result.total_contas}`);
            cancelarNovaConta();
            carregarContasBancarias();
        } else {
            showToast(result.message || 'Erro ao salvar conta bancária', 'error');
        }
    } catch (error) {
        showToast('Erro de conexão com o servidor', 'error');
    }
};

window.adicionarNovaConta = function() {
    document.getElementById('nova-conta-form').style.display = 'block';
    
    document.getElementById('novo-tipoconta').value = '';
    document.getElementById('novo-banco').value = '';
    document.getElementById('novo-agencia').value = '';
    document.getElementById('novo-nconta').value = '';
    document.getElementById('novo-chavepix').value = '';
    document.getElementById('novo-cpf_titular').value = '';
    document.getElementById('novo-nome_titular').value = '';
};

window.excluirConta = async function(id) {
    const isDatabase = id.startsWith('db_');
    const confirmMessage = isDatabase ? 
        'Tem certeza que deseja marcar esta conta para exclusão?\nEla será removida permanentemente ao salvar o cliente.' :
        'Tem certeza que deseja remover esta conta bancária da sessão?\n(Ela não foi salva ainda.)';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        const response = await fetch('../api_clientes/contas_bancarias_edit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'excluir',
                id: id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            let message = '';
            if (result.tipo === 'db') {
                message = 'Conta marcada para exclusão! (Será removida ao salvar o cliente)';
            } else if (result.tipo === 'temp') {
                message = 'Conta temporária removida da sessão!';
            } else {
                message = 'Conta processada com sucesso!';
            }
            
            showToast(message, 'success');
            carregarContasBancarias();
            
        } else {
            showToast(result.message || 'Erro ao processar exclusão', 'error');
        }
    } catch (error) {
        showToast('Erro de conexão com o servidor. Tente novamente.', 'error');
    }
};

window.cancelarNovaConta = function() {
    document.getElementById('nova-conta-form').style.display = 'none';
    
    document.getElementById('novo-tipoconta').value = '';
    document.getElementById('novo-banco').value = '';
    document.getElementById('novo-agencia').value = '';
    document.getElementById('novo-nconta').value = '';
    document.getElementById('novo-chavepix').value = '';
    document.getElementById('novo-cpf_titular').value = '';
    document.getElementById('novo-nome_titular').value = '';
    
    ['novo-cpf_titular'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.style.borderColor = '#e5e7eb';
            input.style.background = 'white';
        }
    });
};

function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9))) return false;
    
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
    }
    resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    return resto === parseInt(cpf.charAt(10));
}
</script>