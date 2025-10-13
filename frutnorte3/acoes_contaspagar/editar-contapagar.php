<?php
include '../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../contaspagar.php?erro=conta_nao_encontrada');
    exit;
}

$id = (int)$_GET['id'];
$codentrada = isset($_GET['codentrada']) ? (int)$_GET['codentrada'] : 0;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Buscar dados da conta a pagar - CORRIGIDO
    $sql = "SELECT cp.*, c.Nome as fornecedor_nome 
            FROM contaspagar cp 
            LEFT JOIN clientes c ON cp.codcliente = c.codcliente 
            WHERE cp.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $conta = $stmt->fetch();
    
    if (!$conta) {
        header('Location: ../contaspagar.php?erro=conta_nao_encontrada');
        exit;
    }
    
    // Buscar fornecedores - CORRIGIDO
    $sql_fornecedores = "SELECT codcliente, Nome, Fantasia FROM clientes ORDER BY Nome";
    $fornecedores = $pdo->query($sql_fornecedores)->fetchAll();
    
    // Buscar tipos de despesa - CORRIGIDO
    $sql_tipos_despesa = "SELECT codtpdes, Descricao FROM tipodespesas ORDER BY Descricao";
    $tipos_despesa = $pdo->query($sql_tipos_despesa)->fetchAll();
    
    // Buscar condições de pagamento - CORRIGIDO
    $sql_condicoes = "SELECT codcond, Descricao FROM condicoes ORDER BY Descricao";
    $condicoes = $pdo->query($sql_condicoes)->fetchAll();
    
} catch (Exception $e) {
    echo "Erro no banco de dados: " . $e->getMessage();
    exit;
}

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'codcliente' => $_POST['codcliente'],
        'codtpdes' => $_POST['codtpdes'] ?: null,
        'codcond' => $_POST['codcond'] ?: null,
        'numeronota' => $_POST['numeronota'],
        'serienota' => $_POST['serienota'],
        'dataemissao' => $_POST['dataemissao'],
        'datavencimento' => $_POST['datavencimento'],
        'vrtitulo' => str_replace(['.', ','], ['', '.'], $_POST['vrtitulo']),
        'obs' => $_POST['obs']
    ];
    
    try {
        $sql_update = "UPDATE contaspagar SET 
                      codcliente = ?, codtpdes = ?, codcond = ?, 
                      numeronota = ?, serienota = ?, dataemissao = ?, 
                      datavencimento = ?, vrtitulo = ?, obs = ?
                      WHERE id = ?";
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            $dados['codcliente'], $dados['codtpdes'], $dados['codcond'],
            $dados['numeronota'], $dados['serienota'], $dados['dataemissao'],
            $dados['datavencimento'], $dados['vrtitulo'], $dados['obs'], $id
        ]);
        
        header('Location: ../contaspagar.php?mensagem=conta_editada_sucesso' . ($codentrada ? '&codentrada=' . $codentrada : ''));
        exit;
        
    } catch (Exception $e) {
        $erro = "Erro ao atualizar conta: " . $e->getMessage();
    }
}
?>


<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Conta a Pagar - #<?= $conta['codpagar'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            padding: 0;
            background: #f8fafc;
            min-height: 100vh;
        }

        .content-area {
            margin-top: 50px;
            max-width: 1400px;
            margin: 50px auto 0;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #6B46C1 0%, #4d328b 100%);
            padding: 24px 32px;
            border-radius: 16px;
            color: white;
            margin-bottom: 24px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            color: white;
            margin: 0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-primary {
            background: white;
            color: #6B46C1;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #6B46C1;
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="content-area">
        <!-- Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="../index.php"><i class="fas fa-home"></i> Dashboard</a> / 
                <a href="../contaspagar.php<?= $codentrada ? '?codentrada=' . $codentrada : '' ?>">Contas a Pagar</a> / 
                <span>Editar Conta</span>
            </div>
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-edit"></i>
                    Editar Conta a Pagar #<?= $conta['codpagar'] ?>
                </h1>
                <a href="../contaspagar.php<?= $codentrada ? '?codentrada=' . $codentrada : '' ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- Formulário -->
        <div class="form-container">
            <?php if (isset($erro)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $erro ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fornecedor *</label>
                        <select name="codcliente" class="form-input" required>
                            <option value="">Selecione um fornecedor</option>
                            <?php foreach ($fornecedores as $fornecedor): ?>
                                <option value="<?= $fornecedor['codcliente'] ?>" 
                                    <?= $fornecedor['codcliente'] == $conta['codcliente'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fornecedor['Nome'] ?: $fornecedor['Fantasia']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de Despesa</label>
                        <select name="codtpdes" class="form-input">
                            <option value="">Selecione o tipo</option>
                            <?php foreach ($tipos_despesa as $tipo): ?>
                                <option value="<?= $tipo['codtpdes'] ?>" 
                                    <?= $tipo['codtpdes'] == $conta['codtpdes'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['Descricao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Número da Nota</label>
                        <input type="text" name="numeronota" class="form-input" 
                               value="<?= htmlspecialchars($conta['numeronota'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Série</label>
                        <input type="text" name="serienota" class="form-input" 
                               value="<?= htmlspecialchars($conta['serienota'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Data de Emissão</label>
                        <input type="date" name="dataemissao" class="form-input" 
                               value="<?= $conta['dataemissao'] ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Data de Vencimento *</label>
                        <input type="date" name="datavencimento" class="form-input" 
                               value="<?= $conta['datavencimento'] ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Valor do Título *</label>
                        <input type="text" name="vrtitulo" class="form-input" 
                               value="<?= number_format($conta['vrtitulo'], 2, ',', '.') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Condição de Pagamento</label>
                        <select name="codcond" class="form-input">
                            <option value="">Selecione a condição</option>
                            <?php foreach ($condicoes as $condicao): ?>
                                <option value="<?= $condicao['codcond'] ?>" 
                                    <?= $condicao['codcond'] == $conta['codcond'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($condicao['Descricao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea name="obs" class="form-input" rows="4"><?= htmlspecialchars($conta['obs'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="../contaspagar.php<?= $codentrada ? '?codentrada=' . $codentrada : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Formatação de valor monetário
document.querySelector('input[name="vrtitulo"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = (value / 100).toFixed(2) + '';
    value = value.replace(".", ",");
    value = value.replace(/(\d)(\d{3})(\d{3}),/g, "$1.$2.$3,");
    value = value.replace(/(\d)(\d{3}),/g, "$1.$2,");
    e.target.value = value;
});
</script>
</body>
</html>