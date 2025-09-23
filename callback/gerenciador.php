<?php
$host = '177.107.115.204';
$db   = 'integracao_bling';
$user = 'root';
$pass = '@@rOOt@cAlOry@1967@@';
$port = 33060;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco: " . $e->getMessage());
}

$message = '';
$existing = null;
$isError = false;

// Processar exclusão
if (isset($_GET['delete'])) {
    $cnpjToDelete = preg_replace('/\D/', '', $_GET['delete']);
    
    if ($cnpjToDelete) {
        try {
            $pdo->beginTransaction();
            
            // Excluir das credenciais
            $stmt = $pdo->prepare("DELETE FROM credenciais WHERE cnpj = :cnpj");
            $stmt->execute([':cnpj' => $cnpjToDelete]);
            
            // Excluir dos tokens
            $stmt = $pdo->prepare("DELETE FROM bling_tokens WHERE cnpj = :cnpj");
            $stmt->execute([':cnpj' => $cnpjToDelete]);
            
            $pdo->commit();
            $message = "✅ CNPJ excluído com sucesso!";
            $isError = false;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Erro ao excluir CNPJ: " . $e->getMessage();
            $isError = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnpj = preg_replace('/\D/', '', $_POST['cnpj']);
    $link = trim($_POST['link']);
    $client_secret = trim($_POST['client_secret']);

    if ($cnpj && $link && $client_secret) {
        // Extrair client_id e state do link
        $client_id = '';
        $state = '';
        
        // Parse do link para extrair client_id e state
        $parsedUrl = parse_url($link);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);
            $client_id = $params['client_id'] ?? '';
            $state = $params['state'] ?? '';
        }

        if (!$client_id || !$state) {
            $message = "❌ Link inválido! Certifique-se de que contém client_id e state.";
            $isError = true;
        } else {
            // Verifica se o CNPJ já existe
            $stmt = $pdo->prepare("SELECT * FROM credenciais WHERE cnpj = :cnpj");
            $stmt->execute([':cnpj' => $cnpj]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $message = "❌ CNPJ já cadastrado!";
                $isError = true;
            } else {
                // Inserir em credenciais
                $stmt = $pdo->prepare("INSERT INTO credenciais (cnpj, client_id, client_secret, state, link) 
                                       VALUES (:cnpj, :client_id, :client_secret, :state, :link)");
                $stmt->execute([
                    ':cnpj' => $cnpj,
                    ':client_id' => $client_id,
                    ':client_secret' => $client_secret,
                    ':state' => $state,
                    ':link' => $link
                ]);

                // Inserir em bling_tokens
                $stmt = $pdo->prepare("INSERT INTO bling_tokens (cnpj) VALUES (:cnpj)");
                $stmt->execute([':cnpj' => $cnpj]);

                $existing = [
                    'cnpj' => $cnpj,
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'state' => $state,
                    'link' => $link
                ];

                $message = "✅ CNPJ cadastrado com sucesso!";
                $isError = false;
            }
        }
    } else {
        $message = "❌ Preencha todos os campos!";
        $isError = true;
    }
}

// Buscar todos os CNPJs cadastrados para exibir na lista
$stmt = $pdo->prepare("SELECT cnpj, client_id, state, link FROM credenciais ORDER BY cnpj");
$stmt->execute();
$allCredentials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador Bling - Calory</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #278b82;
            --primary-dark: #1f6c67;
            --secondary: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 15px;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            line-height: 1.5;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px 15px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
        }

        h1 {
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .logo {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .subtitle {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px 15px;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 0.9rem;
        }

        .input-with-icon input {
            padding-left: 40px;
        }

        input, textarea {
            width: 100%;
            padding: 22px 50px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            resize: vertical;
        }

        textarea {
            min-height: 80px;
            font-family: monospace;
            font-size: 13px;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(39, 139, 130, 0.2);
        }

        button {
            padding: 14px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-full {
            width: 100%;
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 14px;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            width: 100%;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .data-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px 15px;
            margin-top: 15px;
            width: 100%;
        }

        .data-card h3 {
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .cnpjs-list {
            width: 100%;
            margin-top: 20px;
        }

        .cnpj-item {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }

        .cnpj-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .cnpj-number {
            font-weight: bold;
            color: var(--primary);
            font-size: 1rem;
            font-family: monospace;
        }

        .cnpj-actions {
            display: flex;
            gap: 10px;
        }

        .cnpj-details {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }

        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: var(--gray);
        }

        .detail-value {
            word-break: break-all;
            font-family: monospace;
        }

        .link-preview {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.8rem;
        }

        .link-preview:hover {
            text-decoration: underline;
        }

        .form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e1e5ee;
            flex-wrap: wrap;
            gap: 10px;
        }

        .form-title {
            color: var(--primary);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .required {
            color: var(--danger);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .required::after {
            content: " *";
            color: var(--danger);
        }

        .link-help {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.8rem;
            border-left: 3px solid var(--info);
        }

        .link-help code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.75rem;
        }

        @media (max-width: 480px) {
            .link-help code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.45rem;
        }
            body {
                padding: 10px;
            }
            
            header {
                padding: 15px 10px;
                margin-bottom: 15px;
            }
            
            h1 {
                font-size: 1.3rem;
                gap: 6px;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .subtitle {
                font-size: 0.8rem;
            }
            
            .card {
                padding: 15px 10px;
                margin-bottom: 15px;
            }
            
            .form-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .form-title {
                font-size: 1rem;
            }
            
            input, textarea {
                padding: 10px 50px;
                font-size: 13px;
            }
            
            button {
                padding: 12px;
                font-size: 14px;
            }
            
            .cnpj-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cnpj-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .cnpj-actions button {
                flex: 1;
                margin: 2px;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                min-width: auto;
                margin-bottom: 2px;
            }
        }

        @media (max-width: 360px) {
            h1 {
                font-size: 1.1rem;
            }
            
            .card {
                padding: 12px 8px;
            }
            
            input, textarea, button {
                font-size: 12px;
            }
            
            .message {
                font-size: 0.8rem;
                padding: 10px;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-cogs logo"></i> Gerenciador Bling - Calory</h1>
            <p class="subtitle">Gerencie as credenciais de integração com o Bling</p>
        </header>

        <?php if($message): ?>
            <div class="message <?= $isError ? 'error' : 'success' ?>">
                <i class="<?= $isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="form-header">
                <h2 class="form-title"><i class="fas fa-key"></i> Credenciais Bling</h2>
                <span class="required">Campos obrigatórios</span>
            </div>
            
            <div class="link-help">
                <i class="fas fa-info-circle"></i> 
                <strong>Formato do Link:</strong> Cole o link completo de autorização do Bling que contém client_id e state.
                <br><code>https://www.bling.com.br/b/Api/v3/oauth/authorize?response_type=code&<br>client_id=SEU_CLIENT_ID&state=SEU_STATE</code>
            </div>
            
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="cnpj" class="required">CNPJ</label>
                    <div class="input-with-icon">
                        <i class="fas fa-building"></i>
                        <input type="text" name="cnpj" id="cnpj" placeholder="Somente números (ex: 12345678000195)" 
                               value="<?= $existing['cnpj'] ?? '' ?>" required 
                               pattern="\d{14}" title="Digite exatamente 14 dígitos"
                               maxlength="14">
                    </div>
                </div>

                <div class="form-group">
                    <label for="link" class="required">Link de Autorização Bling</label>
                    <div class="input-with-icon">
                        <i class="fas fa-link"></i>
                        <textarea name="link" id="link" placeholder="Cole o link completo de autorização do Bling..." required><?= $existing['link'] ?? '' ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="client_secret" class="required">Client Secret</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="text" name="client_secret" id="client_secret" placeholder="Client Secret do Bling" 
                               value="<?= $existing['client_secret'] ?? '' ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-full">
                    <i class="fas <?= $existing ? 'fa-sync-alt' : 'fa-save' ?>"></i>
                    <?= $existing ? 'Atualizar Credenciais' : 'Cadastrar Credenciais' ?>
                </button>
            </form>
        </div>

        <div class="cnpjs-list">
            <div class="data-card">
                <h3><i class="fas fa-list"></i> CNPJs Cadastrados (<?= count($allCredentials) ?>)</h3>
                
                <?php if (empty($allCredentials)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhum CNPJ cadastrado ainda.</p>
                        <small>Adicione seu primeiro CNPJ usando o formulário acima.</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($allCredentials as $cred): ?>
                        <div class="cnpj-item">
                            <div class="cnpj-header">
                                <span class="cnpj-number"><?= htmlspecialchars($cred['cnpj']) ?></span>
                                <div class="cnpj-actions">
                                    <a href="?delete=<?= $cred['cnpj'] ?>" class="btn-danger btn-sm" 
                                       onclick="return confirm('Tem certeza que deseja excluir o CNPJ <?= $cred['cnpj'] ?>? Esta ação não pode ser desfeita.')">
                                        <i class="fas fa-trash"></i> Excluir
                                    </a>
                                </div>
                            </div>
                            <div class="cnpj-details">
                                <div class="detail-row">
                                    <span class="detail-label">Client ID:</span>
                                    <span class="detail-value"><?= htmlspecialchars($cred['client_id']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">State:</span>
                                    <span class="detail-value"><?= htmlspecialchars($cred['state']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Link:</span>
                                    <a href="<?= htmlspecialchars($cred['link']) ?>" target="_blank" class="detail-value link-preview">
                                        <i class="fas fa-external-link-alt"></i> Ver link completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Formatação do CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 14) value = value.substring(0, 14);
            e.target.value = value;
        });

        // Validação do link em tempo real
        document.getElementById('link').addEventListener('blur', function() {
            const link = this.value.trim();
            if (link) {
                const urlParams = new URLSearchParams(link.split('?')[1]);
                const clientId = urlParams.get('client_id');
                const state = urlParams.get('state');
                
                if (!clientId || !state) {
                    this.style.borderColor = 'var(--danger)';
                    alert('⚠️ Link inválido! Certifique-se de que contém client_id e state.');
                } else {
                    this.style.borderColor = '#e1e5ee';
                }
            }
        });

        // Validação em tempo real
        const inputs = document.querySelectorAll('input[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = '#e1e5ee';
                }
            });
        });

        // Feedback visual ao enviar o formulário
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            const button = this.querySelector('button');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            button.disabled = true;
        });

        // Confirmação de exclusão
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Tem certeza que deseja excluir este CNPJ? Esta ação não pode ser desfeita.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>