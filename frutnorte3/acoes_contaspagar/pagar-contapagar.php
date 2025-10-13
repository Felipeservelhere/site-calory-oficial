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
    
    // Buscar tipos de pagamento - CORRIGIDO
    $sql_tipos_pagamento = "SELECT codtppag, Descricao FROM tipopagamentos ORDER BY Descricao";
    $tipos_pagamento = $pdo->query($sql_tipos_pagamento)->fetchAll();
    
} catch (Exception $e) {
    echo "Erro no banco de dados: " . $e->getMessage();
    exit;
}

// Processar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor_pago = str_replace(['.', ','], ['', '.'], $_POST['valor_pago']);
    $data_pagamento = $_POST['data_pagamento'];
    $tipo_pagamento = $_POST['tipo_pagamento'];
    $observacoes = $_POST['observacoes'];
    
    if ($valor_pago <= 0) {
        $erro = "Valor do pagamento deve ser maior que zero.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Inserir registro na tabela baixapagar - CORRIGIDO
            $sql_baixa = "INSERT INTO baixapagar 
                         (idcliente, codempresa, codbaixapagar, codempresadoc, codpagar, seqcodpagar, 
                          datapgto, vrpago, tipopgto, saldo) 
                         VALUES (?, 1, ?, 1, ?, ?, ?, ?, ?, ?)";
            
            $stmt_baixa = $pdo->prepare($sql_baixa);
            
            // Gerar código único para baixapagar
            $codbaixapagar = time(); // Usar timestamp como código único
            $idcliente = $conta['idcliente']; // Usar o idcliente da conta original
            
            $stmt_baixa->execute([
                $idcliente,
                $codbaixapagar,
                $conta['codpagar'],
                $conta['seqcodpagar'],
                $data_pagamento,
                $valor_pago,
                $tipo_pagamento,
                $conta['saldo'] - $valor_pago
            ]);
            
            // 2. Atualizar contas a pagar - CORRIGIDO
            $novo_saldo = $conta['saldo'] - $valor_pago;
            $novo_vrpago = $conta['vrpago'] + $valor_pago;
            
            $sql_update = "UPDATE contaspagar SET 
                          vrpago = ?, saldo = ?, datapagamento = ?
                          WHERE id = ?";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                $novo_vrpago,
                $novo_saldo,
                $data_pagamento,
                $id
            ]);
            
            $pdo->commit();
            
            header('Location: ../contaspagar.php?mensagem=pagamento_registrado_sucesso' . ($codentrada ? '&codentrada=' . $codentrada : ''));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao registrar pagamento: " . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pagamento - #<?= $conta['codpagar'] ?></title>
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
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
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
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
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

        .info-conta {
            background: #f0fdf4;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .info-label {
            color: #374151;
            font-weight: 500;
        }

        .info-value {
            color: #065f46;
            font-weight: 600;
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
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
                <span>Registrar Pagamento</span>
            </div>
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Registrar Pagamento
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

            <!-- Informações da Conta -->
            <div class="info-conta">
                <div class="info-item">
                    <span class="info-label">Fornecedor:</span>
                    <span class="info-value"><?= htmlspecialchars($conta['fornecedor_nome'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Número da Conta:</span>
                    <span class="info-value">#<?= $conta['codpagar'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Valor Original:</span>
                    <span class="info-value">R$ <?= number_format($conta['vrtitulo'], 2, ',', '.') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Já Pago:</span>
                    <span class="info-value">R$ <?= number_format($conta['vrpago'], 2, ',', '.') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Saldo Pendente:</span>
                    <span class="info-value" style="color: #dc2626;">R$ <?= number_format($conta['saldo'], 2, ',', '.') ?></span>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Valor do Pagamento *</label>
                    <input type="text" name="valor_pago" class="form-input" 
                           value="<?= number_format($conta['saldo'], 2, ',', '.') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Data do Pagamento *</label>
                    <input type="date" name="data_pagamento" class="form-input" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de Pagamento *</label>
                    <select name="tipo_pagamento" class="form-input" required>
                        <option value="">Selecione o tipo</option>
                        <?php foreach ($tipos_pagamento as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo['Descricao']) ?>">
                                <?= htmlspecialchars($tipo['Descricao']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="../contaspagar.php<?= $codentrada ? '?codentrada=' . $codentrada : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Registrar Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Formatação de valor monetário
document.querySelector('input[name="valor_pago"]').addEventListener('input', function(e) {
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