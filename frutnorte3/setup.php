<?php
session_start();
require_once 'config/databaselogin.php';

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: login.php");
    exit;
}

$database = new DatabaseLogin();
$pdo = $database->getConnection();
$msg = '';
$msg_type = '';

// Verificar se é admin e precisa de setup
$admin_id = $_SESSION['admin_id'] ?? 0;
if (!$admin_id) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ? AND cargo = 'Admin'");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($admin['usuario'])) {
    header("Location: index.php");  // Já configurado, vai para dashboard
    exit;
}

// Processar form de setup
if ($_POST && isset($_POST['setup'])) {
    $usuario = trim($_POST['usuario']);
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validações
    if (empty($usuario) || strlen($usuario) < 3) {
        $msg = "Usuário deve ter pelo menos 3 caracteres.";
        $msg_type = "error";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $nova_senha)) {
        $msg = "Senha deve ter pelo menos 8 caracteres, incluindo 1 maiúscula, 1 número e 1 símbolo (@, !, %, *, ?, &).";
        $msg_type = "error";
    } elseif ($nova_senha !== $confirmar_senha) {
        $msg = "As senhas não coincidem.";
        $msg_type = "error";
    } else {
        // Verificar se usuario já existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $stmt->execute([$usuario, $admin_id]);
        if ($stmt->fetch()) {
            $msg = "Usuário já existe. Escolha outro.";
            $msg_type = "error";
        } else {
            // Atualizar banco
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, senha = ? WHERE id = ?");
            if ($stmt->execute([$usuario, $senha_hash, $admin_id])) {
                $msg = "Configuração concluída! Agora você pode acessar o dashboard.";
                $msg_type = "success";
                // Opcional: Limpar senha temporária ou log
                // Redirecionar após 2s ou botão
                echo "<script>setTimeout(function(){ window.location.href = 'index.php.php'; }, 2000);</script>";
            } else {
                $msg = "Erro ao salvar configurações.";
                $msg_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração Inicial - Calory Sistemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .setup-card { box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 15px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card setup-card">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4"><i class="fas fa-cog me-2"></i>Configuração Inicial</h>
                                                <p class="text-center text-muted mb-4">Defina seu usuário e uma senha segura para continuar.</p>

                        <?php if ($msg): ?>
                            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Formulário de Setup -->
                        <form method="POST">
                            <input type="hidden" name="setup" value="1">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuário (Username) *</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" required minlength="3" placeholder="Ex: admin123">
                                <div class="form-text">Mínimo 3 caracteres. Deve ser único.</div>
                            </div>
                            <div class="mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha *</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="8">
                                <div class="form-text">Mínimo 8 caracteres: 1 maiúscula, 1 número, 1 símbolo (@, !, %, *, ?, &).</div>
                                <div id="password-strength" class="mt-1 small text-muted"></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha *</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="submit-btn">Salvar Configurações</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-link">Voltar ao Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validação JS para senha (feedback em tempo real)
const novaSenhaInput = document.getElementById('nova_senha');
const confirmarSenhaInput = document.getElementById('confirmar_senha');
const strengthDiv = document.getElementById('password-strength');
const submitBtn = document.getElementById('submit-btn');

function validatePassword(password) {
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSymbol = /[@$!%*?&]/.test(password);
    const length = password.length >= 8;

    let score = 0;
    if (length) score++;
    if (hasUpper) score++;
    if (hasNumber) score++;
    if (hasSymbol) score++;

    // Feedback visual
    strengthDiv.innerHTML = '';
    if (password.length === 0) return;

    const requirements = [
        { text: 'Mínimo 8 caracteres', check: length },
        { text: '1 letra maiúscula (A-Z)', check: hasUpper },
        { text: '1 número (0-9)', check: hasNumber },
        { text: '1 símbolo (@, !, %, *, ?, &)', check: hasSymbol }
    ];

    requirements.forEach(req => {
        const span = document.createElement('span');
        span.className = req.check ? 'text-success' : 'text-danger';
        span.innerHTML = `• ${req.text} ${req.check ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'}`;
        strengthDiv.appendChild(span);
    });

    // Habilitar/desabilitar botão
    submitBtn.disabled = !length || !hasUpper || !hasNumber || !hasSymbol || (confirmarSenhaInput.value && confirmarSenhaInput.value !== password);
}

novaSenhaInput.addEventListener('input', function() {
    validatePassword(this.value);
});

confirmarSenhaInput.addEventListener('input', function() {
    validatePassword(novaSenhaInput.value);
    if (this.value !== novaSenhaInput.value) {
        this.setCustomValidity('Senhas não coincidem');
    } else {
        this.setCustomValidity('');
    }
});

// Validação no submit
document.querySelector('form').addEventListener('submit', function(e) {
    const password = novaSenhaInput.value;
    if (!/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/.test(password)) {
        e.preventDefault();
        alert('Senha não atende aos requisitos de segurança.');
        return false;
    }
    if (novaSenhaInput.value !== confirmarSenhaInput.value) {
        e.preventDefault();
        alert('As senhas não coincidem.');
        return false;
    }
});
</script>
</body>
</html>