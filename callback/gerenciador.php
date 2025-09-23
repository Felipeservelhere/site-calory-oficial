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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnpj = preg_replace('/\D/', '', $_POST['cnpj']);
    $client_id = trim($_POST['client_id']);
    $client_secret = trim($_POST['client_secret']);
    $state = trim($_POST['state']);

    if ($cnpj && $client_id && $client_secret && $state) {
        // Verifica se o CNPJ já existe
        $stmt = $pdo->prepare("SELECT * FROM credenciais WHERE cnpj = :cnpj");
        $stmt->execute([':cnpj' => $cnpj]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $message = "⚠️ CNPJ já cadastrado!";
        } else {
            // Inserir em credenciais com state informado
            $stmt = $pdo->prepare("INSERT INTO credenciais (cnpj, client_id, client_secret, state) 
                                   VALUES (:cnpj, :client_id, :client_secret, :state)");
            $stmt->execute([
                ':cnpj' => $cnpj,
                ':client_id' => $client_id,
                ':client_secret' => $client_secret,
                ':state' => $state
            ]);

            // Inserir em bling_tokens
            $stmt = $pdo->prepare("INSERT INTO bling_tokens (cnpj) VALUES (:cnpj)");
            $stmt->execute([':cnpj' => $cnpj]);

            $existing = [
                'cnpj' => $cnpj,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'state' => $state
            ];

            $message = "✅ CNPJ cadastrado com sucesso!";
        }
    } else {
        $message = "❌ Preencha todos os campos!";
    }
}
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
            padding: 20px;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
        }

        h1 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 2.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo {
            color: var(--primary);
            font-size: 2.5rem;
        }

        .subtitle {
            color: var(--gray);
            font-size: 1.1rem;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 25px;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .input-with-icon input {
            padding-left: 45px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(39, 139, 130, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
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
            gap: 10px;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid var(--success);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid var(--danger);
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border-left: 5px solid var(--warning);
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 5px solid var(--info);
        }

        .data-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-top: 20px;
        }

        .data-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5ee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .cnpj-display {
            font-weight: bold;
            color: var(--primary);
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .card {
                padding: 20px;
            }
            
            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }
            
            .card {
                padding: 15px;
            }
            
            input, button {
                font-size: 14px;
            }
        }

        .form-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e5ee;
        }

        .form-title {
            color: var(--primary);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .required::after {
            content: " *";
            color: var(--danger);
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
            <div class="message <?= $existing && $message === "⚠️ CNPJ já cadastrado!" ? 'warning' : ($message[0] === '✅' ? 'success' : 'error') ?>">
                <i class="<?= $existing && $message === "⚠️ CNPJ já cadastrado!" ? 'fas fa-exclamation-triangle' : ($message[0] === '✅' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="form-header">
                <h2 class="form-title"><i class="fas fa-key"></i> Credenciais Bling</h2>
                <span class="required">Campos obrigatórios</span>
            </div>
            
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="cnpj" class="required">CNPJ</label>
                    <div class="input-with-icon">
                        <i class="fas fa-building"></i>
                        <input type="text" name="cnpj" id="cnpj" placeholder="Somente números (ex: 12345678000195)" 
                               value="<?= $existing['cnpj'] ?? '' ?>" required 
                               pattern="\d{14}" title="Digite exatamente 14 dígitos">
                    </div>
                </div>

                <div class="form-group">
                    <label for="client_id" class="required">Client ID</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="client_id" id="client_id" placeholder="Client ID do Bling" 
                               value="<?= $existing['client_id'] ?? '' ?>" required>
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

                <div class="form-group">
                    <label for="state" class="required">State</label>
                    <div class="input-with-icon">
                        <i class="fas fa-code"></i>
                        <input type="text" name="state" id="state" placeholder="Defina o state manualmente" 
                               value="<?= $existing['state'] ?? '' ?>" required>
                    </div>
                </div>

                <button type="submit">
                    <i class="fas <?= $existing ? 'fa-sync-alt' : 'fa-save' ?>"></i>
                    <?= $existing ? 'Atualizar / Visualizar' : 'Cadastrar Credenciais' ?>
                </button>
            </form>
        </div>

        <?php if($existing): ?>
            <div class="data-card">
                <h3><i class="fas fa-database"></i> Dados Cadastrados</h3>
                <p class="cnpj-display">CNPJ: <?= htmlspecialchars($existing['cnpj']) ?></p>
                <table>
                    <tr>
                        <th><i class="fas fa-id-card"></i> Client ID</th>
                        <td><?= htmlspecialchars($existing['client_id']) ?></td>
                    </tr>
                    <tr>
                        <th><i class="fas fa-lock"></i> Client Secret</th>
                        <td><?= htmlspecialchars($existing['client_secret']) ?></td>
                    </tr>
                    <tr>
                        <th><i class="fas fa-code"></i> State</th>
                        <td><?= htmlspecialchars($existing['state']) ?></td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Formatação do CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 14) value = value.substring(0, 14);
            e.target.value = value;
        });

        // Validação em tempo real
        const inputs = document.querySelectorAll('input[required]');
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
    </script>
</body>
</html>