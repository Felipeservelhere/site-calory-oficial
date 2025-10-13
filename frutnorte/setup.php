<?php
session_start();
require_once 'config/databaselogin.php';

$database = new DatabaseLogin();
$pdo = $database->getConnection();
$msg = '';
$msg_type = '';

$token = $_GET['token'] ?? '';
if (!$token) die("Token inválido ou ausente.");

// Buscar usuário pendente pelo token
$stmt = $pdo->prepare("SELECT id, token_expira, empresa_id, email FROM usuarios WHERE token_ativacao = ? AND status = 0");
$stmt->execute([$token]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) die("Token inválido ou expirado.");
if (strtotime($usuario['token_expira']) < time()) die("Token expirado. Solicite novamente o primeiro acesso.");

// Definir usuário fixo como "admin"
$login = "admin";

// Processar form de setup
if ($_POST && isset($_POST['setup'])) {
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $logo_path = '';

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $nova_senha)) {
        $msg = "Senha inválida. Precisa de 1 maiúscula, 1 número e 1 símbolo.";
        $msg_type = "danger";
    } elseif ($nova_senha !== $confirmar_senha) {
        $msg = "As senhas não coincidem.";
        $msg_type = "danger";
    } else {
        // Upload logo opcional
        if (!empty($_FILES['logo']['name'])) {
            $allowed = ['png','jpg','jpeg','gif'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $logo_dir = 'uploads/logos/';
                if (!is_dir($logo_dir)) mkdir($logo_dir, 0755, true);
                $logo_path = $logo_dir . 'logo_' . $usuario['empresa_id'] . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
                $stmt2 = $pdo->prepare("UPDATE empresas SET logo = ? WHERE id = ?");
                $stmt2->execute([$logo_path, $usuario['empresa_id']]);
            }
        }

        // Atualizar usuário com login "admin"
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, senha = ?, status = 1, token_ativacao = NULL, token_expira = NULL WHERE id = ?");
        if ($stmt->execute([$login, $senha_hash, $usuario['id']])) {
            // Criar sessão
            $_SESSION['loggedin'] = true;
            $_SESSION['empresa_id'] = $usuario['empresa_id'];
            $_SESSION['admin_id'] = $usuario['id'];
            $_SESSION['admin_email'] = $usuario['email'];
            $_SESSION['admin_usuario'] = $login;

            header("Location: index.php");
            exit;
        } else {
            $msg = "Erro ao ativar conta.";
            $msg_type = "danger";
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
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background: linear-gradient(135deg,#f6f8fb 0%,#e9eef7 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; padding:20px;}
.setup-card { width:100%; max-width:480px; background:#fff; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.08); border:1px solid #eef2f7; padding:36px;}
.page-title { text-align:center; font-size:28px; font-weight:600; color:#1f2937; margin-bottom:8px;}
.setup-header { text-align:center; font-size:18px; font-weight:500; color:#4b5563; margin-bottom:12px;}
.setup-subtitle { text-align:center; font-size:14px; color:#6b7280; margin-bottom:32px;}
.form-label { font-weight:500; color:#374151; margin-bottom:8px; font-size:14px;}
.form-control { border-radius:8px; border:1px solid #d1d5db; padding:12px 16px; font-size:14px; transition: all 0.2s;}
.form-control:focus { border-color:#3B82F6; box-shadow:0 0 0 3px rgba(59,130,246,0.1); outline:none;}
.form-text { font-size:12px; color:#6b7280; margin-top:4px;}
.btn-primary { background:#3B82F6; border:none; border-radius:10px; padding:12px 24px; font-size:15px; font-weight:600; color:#fff; width:100%; transition: all 0.2s; box-shadow:0 8px 20px rgba(59,130,246,0.35);}
.btn-primary:hover:not(:disabled) { background:#2563EB; transform: translateY(-1px); box-shadow:0 10px 25px rgba(59,130,246,0.4);}
.btn-primary:active:not(:disabled) { background:#1D4ED8; transform: translateY(0);}
.link-discrete { text-align:center; margin-top:20px;}
.link-discrete a { color:#6b7280; text-decoration:none; font-size:14px; transition: color 0.2s;}
.link-discrete a:hover { color:#3B82F6;}
.alert { border-radius:10px; font-size:14px; margin-bottom:24px;}
#password-strength { font-size:13px; color:#6b7280; margin-top:8px; line-height:1.5;}
.strength-ok { color:#10b981;}
.strength-missing { color:#ef4444;}
</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-auto">
            <div class="page-title">Calory Sistemas</div>
            <div class="setup-card">
                <div class="setup-header">Configuração Inicial</div>
                <div class="setup-subtitle">Seu login será <strong>admin</strong>. Defina uma senha segura e envie sua logo.</div>

                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="setup" value="1">

                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="8" autocomplete="new-password">
                        <div class="form-text">8+ caracteres, 1 maiúscula, 1 número e 1 símbolo (@, !, %, *, ?, &).</div>
                        <div id="password-strength"></div>
                    </div>

                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label for="logo" class="form-label">Logo da empresa (opcional)</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn">Salvar e acessar</button>
                </form>

                <div class="link-discrete">
                    <a href="login.php">Voltar ao login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const novaSenhaInput = document.getElementById('nova_senha');
const confirmarSenhaInput = document.getElementById('confirmar_senha');
const strengthDiv = document.getElementById('password-strength');
const submitBtn = document.getElementById('submit-btn');

function getPasswordChecks(pwd) {
    return {
        length: pwd.length >= 8,
        upper: /[A-Z]/.test(pwd),
        number: /\d/.test(pwd),
        symbol: /[@$!%*?&]/.test(pwd)
    };
}

function renderStrength(pwd) {
    if (!pwd) { strengthDiv.innerHTML = ''; return; }
    const c = getPasswordChecks(pwd);
    const items = [
        { t: '8+ caracteres', ok: c.length },
        { t: '1 maiúscula', ok: c.upper },
        { t: '1 número', ok: c.number },
        { t: '1 símbolo', ok: c.symbol },
    ];
    strengthDiv.innerHTML = items.map(i => `<span class="${i.ok?'strength-ok':'strength-missing'}">${i.ok?'✓':'•'} ${i.t}</span>`).join(' · ');
}

[novaSenhaInput, confirmarSenhaInput].forEach(el => {
    el.addEventListener('input', () => {
        renderStrength(novaSenhaInput.value);
    });
});
</script>
</body>
</html>

