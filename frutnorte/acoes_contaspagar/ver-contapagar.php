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
    $sql = "SELECT 
                cp.*,
                c.Nome as fornecedor_nome,
                c.Fantasia as fornecedor_fantasia,
                c.cnpj_cpf as fornecedor_documento,
                c.Endereco as fornecedor_endereco,
                c.Cidade as fornecedor_cidade,
                c.Uf as fornecedor_uf,
                tpd.Descricao as tipo_despesa,
                tpp.Descricao as tipo_pagamento,
                cond.Descricao as condicao_pagamento,
                cp.seqcodpagar as numero_parcela,
                (SELECT COUNT(*) FROM contaspagar cp3 WHERE cp3.codpagar = cp.codpagar) as total_parcelas,
                e.numeronota as entrada_numeronota,
                e.Dataentrada as entrada_data
            FROM contaspagar cp
            LEFT JOIN clientes c ON cp.codcliente = c.codcliente
            LEFT JOIN tipodespesas tpd ON cp.codtpdes = tpd.codtpdes
            LEFT JOIN tipopagamentos tpp ON cp.codtppag = tpp.codtppag
            LEFT JOIN condicoes cond ON cp.codcond = cond.codcond
            LEFT JOIN entradas e ON cp.codentrada = e.codentrada
            WHERE cp.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $conta = $stmt->fetch();
    
    if (!$conta) {
        header('Location: ../contaspagar.php?erro=conta_nao_encontrada');
        exit;
    }
    
    // Buscar histórico de pagamentos (baixas) - CORRIGIDO
    $sql_baixas = "SELECT * FROM baixapagar WHERE codpagar = ? ORDER BY datapgto DESC";
    $stmt_baixas = $pdo->prepare($sql_baixas);
    $stmt_baixas->execute([$conta['codpagar']]);
    $baixas = $stmt_baixas->fetchAll();
    
} catch (Exception $e) {
    echo "Erro no banco de dados: " . $e->getMessage();
    exit;
}

// Funções auxiliares
function formatarValor($valor) {
    return 'R$ ' . number_format($valor ?? 0, 2, ',', '.');
}

function formatarData($data) {
    if (!$data || $data == '0000-00-00') return '-';
    return date('d/m/Y', strtotime($data));
}

function getStatusBadge($conta) {
    if ($conta['saldo'] <= 0 && $conta['vrpago'] > 0) {
        return '<span class="status-badge status-active"><i class="fas fa-check-circle"></i> Pago</span>';
    } elseif ($conta['datavencimento'] < date('Y-m-d') && ($conta['saldo'] > 0 || $conta['vrpago'] == 0)) {
        return '<span class="status-badge status-inactive"><i class="fas fa-exclamation-triangle"></i> Vencido</span>';
    } else {
        return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pendente</span>';
    }
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Conta a Pagar - #<?= $conta['codpagar'] ?></title>
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

        .conta-details {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .detail-group {
            margin-bottom: 16px;
        }

        .detail-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 16px;
            color: #374151;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            gap: 4px;
        }

        .status-active { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-inactive { background: #fee2e2; color: #dc2626; }

        .valor-destaque {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }

        .valor-pago { color: #10B981; }
        .valor-saldo { color: #EF4444; }

        .historico-pagamentos {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
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
                <span>Visualizar Conta</span>
            </div>
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Conta a Pagar #<?= $conta['codpagar'] ?>
                </h1>
                <div>
                    <a href="../contaspagar.php<?= $codentrada ? '?codentrada=' . $codentrada : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <a href="editar-contapagar.php?id=<?= $id ?><?= $codentrada ? '&codentrada=' . $codentrada : '' ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                </div>
            </div>
        </div>

        <!-- Detalhes da Conta -->
        <div class="conta-details">
            <div class="detail-grid">
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Fornecedor</div>
                        <div class="detail-value"><?= htmlspecialchars($conta['fornecedor_nome'] ?? $conta['fornecedor_fantasia'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Documento</div>
                        <div class="detail-value"><?= htmlspecialchars($conta['fornecedor_documento'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Endereço</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($conta['fornecedor_endereco'] ?? 'N/A') ?>
                            <?php if ($conta['fornecedor_cidade']): ?>
                                - <?= htmlspecialchars($conta['fornecedor_cidade']) ?>/<?= htmlspecialchars($conta['fornecedor_uf']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Tipo de Despesa</div>
                        <div class="detail-value"><?= htmlspecialchars($conta['tipo_despesa'] ?? 'Não informado') ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Condição de Pagamento</div>
                        <div class="detail-value"><?= htmlspecialchars($conta['condicao_pagamento'] ?? 'Não informada') ?></div>
                    </div>
                </div>
                
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Parcela</div>
                        <div class="detail-value">
                            <?php if ($conta['total_parcelas'] > 1): ?>
                                <?= $conta['numero_parcela'] ?> de <?= $conta['total_parcelas'] ?>
                            <?php else: ?>
                                À Vista
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Data de Lançamento</div>
                        <div class="detail-value"><?= formatarData($conta['Datalancamento']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Data de Vencimento</div>
                        <div class="detail-value"><?= formatarData($conta['datavencimento']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Nota Fiscal</div>
                        <div class="detail-value">
                            <?= $conta['numeronota'] ? htmlspecialchars($conta['numeronota']) . '/' . htmlspecialchars($conta['serienota']) : 'N/A' ?>
                        </div>
                    </div>
                    
                    <?php if ($conta['entrada_numeronota']): ?>
                    <div class="detail-group">
                        <div class="detail-label">Entrada Relacionada</div>
                        <div class="detail-value">
                            NF: <?= htmlspecialchars($conta['entrada_numeronota']) ?> 
                            (<?= formatarData($conta['entrada_data']) ?>)
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Valor do Título</div>
                        <div class="detail-value valor-destaque"><?= formatarValor($conta['vrtitulo']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Valor Pago</div>
                        <div class="detail-value valor-destaque valor-pago"><?= formatarValor($conta['vrpago']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Saldo</div>
                        <div class="detail-value valor-destaque valor-saldo"><?= formatarValor($conta['saldo']) ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><?= getStatusBadge($conta) ?></div>
                    </div>
                    
                    <?php if ($conta['obs']): ?>
                    <div class="detail-group">
                        <div class="detail-label">Observações</div>
                        <div class="detail-value"><?= nl2br(htmlspecialchars($conta['obs'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Histórico de Pagamentos -->
        <div class="historico-pagamentos">
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-history"></i>
                Histórico de Pagamentos
            </h3>
            
            <?php if (empty($baixas)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>Nenhum pagamento registrado para esta conta.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data Pagamento</th>
                            <th>Valor Pago</th>
                            <th>Tipo Pagamento</th>
                            <th>Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baixas as $baixa): ?>
                            <tr>
                                <td><?= formatarData($baixa['datapgto']) ?></td>
                                <td><?= formatarValor($baixa['vrpago']) ?></td>
                                <td><?= htmlspecialchars($baixa['tipopgto'] ?? 'N/A') ?></td>
                                <td><?= $baixa['recibo'] ? '#' . $baixa['recibo'] : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>