<?php
// ver-contas-entrada.php
include '../config/database.php';

// Configuração
$database = new Database();
$pdo = $database->getConnection();
$codentrada = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validar se codentrada existe
if ($codentrada <= 0) {
    header('Location: entradas.php?erro=invalid_id');
    exit;
}

// Buscar detalhes da entrada (para contexto)
$sql_entrada = "SELECT e.*, c.Nome as fornecedor_nome 
                FROM entradas e 
                LEFT JOIN clientes c ON e.Codcliente = c.codcliente 
                WHERE e.codentrada = ?";
$stmt_entrada = $pdo->prepare($sql_entrada);
$stmt_entrada->execute([$codentrada]);
$entrada = $stmt_entrada->fetch();

if (!$entrada) {
    header('Location: entradas.php?erro=entrada_nao_encontrada');
    exit;
}

// Configuração de paginação para contas
$registros_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Contar total de contas a pagar para esta entrada
$sql_count = "SELECT COUNT(*) as total FROM contaspagar WHERE codentrada = ?";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute([$codentrada]);
$total_contas = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_contas / $registros_por_pagina);

// Buscar contas a pagar
$sql_contas = "SELECT * FROM contaspagar 
               WHERE codentrada = ? 
               ORDER BY datavencimento ASC 
               LIMIT $registros_por_pagina OFFSET $offset";
$stmt_contas = $pdo->prepare($sql_contas);
$stmt_contas->execute([$codentrada]);
$contas = $stmt_contas->fetchAll();

// Função para status da conta (ajuste conforme seus campos)
function getStatusClass($status) {
    return $status === 'pago' ? 'status-pago' : 'status-pendente';
}

function getStatusLabel($status) {
    return $status === 'pago' ? 'Pago' : 'Pendente';
}
?>

<?php include '../includes/menu.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar - Entrada <?= str_pad($codentrada, 6, '0', STR_PAD_LEFT) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Cole aqui os estilos CSS do entradas.php para consistência (ou linke um CSS externo) -->
    <style>
        /* Cole os estilos do <style> de entradas.php aqui, ou crie um arquivo CSS compartilhado */
        /* ... (estilos omitidos por brevidade, mas inclua-os para manter o visual) */
        
        /* Estilos adicionais para esta página */
        .entry-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pago {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pendente {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .total-pendente {
            font-size: 18px;
            font-weight: 700;
            color: #dc2626;
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="content-area">
        <!-- Header da Entrada -->
        <div class="entry-header">
            <div class="header-content">
                <h1><i class="fas fa-file-invoice-dollar"></i> Contas a Pagar da Entrada #<?= str_pad($codentrada, 6, '0', STR_PAD_LEFT) ?></h1>
                <p><strong>Nota Fiscal:</strong> <?= htmlspecialchars($entrada['numeronota'] ?? 'N/A') ?> | <strong>Fornecedor:</strong> <?= htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A') ?> | <strong>Valor Total Entrada:</strong> R$ <?= number_format($entrada['vrTotal'], 2, ',', '.') ?></p>
                <a href="../entradas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar para Entradas</a>
            </div>
        </div>

        <!-- Resumo de Contas -->
        <div class="summary-card" style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <h3>Resumo</h3>
            <p>Total de Contas: <?= $total_contas ?> | Pendente: R$ <?= number_format(array_sum(array_column($contas, 'valor')), 2, ',', '.') ?> (calcule pendentes filtrando por status)</p>
        </div>

        <!-- Lista de Contas -->
        <div class="bills-container" style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;">
            <?php if (empty($contas)): ?>
                <div class="empty-state" style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-file-invoice-dollar" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                    <h3>Nenhuma conta a pagar encontrada</h3>
                    <p>Não há contas associadas a esta entrada.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Parcela</th>
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Valor</th>
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Vencimento</th>
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Status</th>
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Observação</th>
                                <th style="padding: 16px; text-align: left; font-weight: 600;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas as $conta): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 16px;"><?= $conta['id'] ?? 'N/A' ?></td> <!-- Ajuste o campo de parcela se existir -->
                                    <td style="padding: 16px;"><strong>R$ <?= number_format($conta['valor'], 2, ',', '.') ?></strong></td>
                                    <td style="padding: 16px;"><?= date('d/m/Y', strtotime($conta['datavencimento'])) ?></td>
                                    <td style="padding: 16px;">
                                        <span class="status-badge <?= getStatusClass($conta['status'] ?? 'pendente') ?>">
                                            <?= getStatusLabel($conta['status'] ?? 'pendente') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 16px;"><?= htmlspecialchars($conta['observacao'] ?? 'N/A') ?></td>
                                    <td style="padding: 16px;">
                                        <!-- Adicione ações como editar/pagar conta -->
                                        <a href="acoes_contas/editar-conta.php?id=<?= $conta['id'] ?>" class="btn-action btn-edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação (se necessário) -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container" style="padding: 20px; border-top: 1px solid #e2e8f0;">
                        <div class="pagination" style="display: flex; justify-content: center; gap: 8px;">
                            <?php if ($pagina_atual > 1): ?>
                                <a href="?id=<?= $codentrada ?>&pagina=<?= $pagina_atual - 1 ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none;">Anterior</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <?php if ($i == $pagina_atual): ?>
                                    <span style="padding: 8px 12px; background: #10b981; color: white; border-radius: 6px;"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?id=<?= $codentrada ?>&pagina=<?= $i ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none;"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($pagina_atual < $total_paginas): ?>
                                <a href="?id=<?= $codentrada ?>&pagina=<?= $pagina_atual + 1 ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none;">Próxima</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Inclua o script de toast se necessário, similar ao de entradas.php -->
<script>
    // Cole o script de showToast e outros do entradas.php aqui se precisar de mensagens
</script>
</body>
</html>