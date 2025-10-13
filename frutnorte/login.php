<?php
session_start();
require_once 'config/databaselogin.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$databaseLogin = new DatabaseLogin();
$pdo = $databaseLogin->getConnection();
$msg = '';
$msg_type = '';

if ($_POST && isset($_POST['login'])) {
        $login_input = trim($_POST['email_login']);
        $senha = $_POST['senha'];

        if (empty($login_input) || empty($senha)) {
            $msg = "Preencha login e senha.";
            $msg_type = "danger";
        } else {
            $stmt = $pdo->prepare("SELECT id, empresa_id, usuario, email, senha FROM usuarios WHERE (email = ? OR usuario = ?) AND cargo = 'Admin' AND status = 1");
            $stmt->execute([$login_input, $login_input]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['empresa_id'] = $usuario['empresa_id'];
                $_SESSION['admin_id'] = $usuario['id'];
                $_SESSION['admin_email'] = $usuario['email'];

                if (empty($usuario['usuario'])) {
                    header("Location: setup.php");
                    exit;
                } else {
                    header("Location: index.php");
                    exit;
                }
            } else {
                $msg = "Login ou senha inválidos.";
                $msg_type = "danger";
            }
        }
    }

    // ====================== PRIMEIRO ACESSO ======================
    // ====================== PRIMEIRO ACESSO ======================
    if ($_POST && isset($_POST['primeiro_acesso'])) {
        $cnpj = trim(preg_replace('/\D/', '', $_POST['cnpj']));
        $email = trim($_POST['email']);

        if (empty($cnpj) || empty($email) || strlen($cnpj) !== 14) {
            $msg = "CNPJ e e-mail são obrigatórios e válidos.";
            $msg_type = "danger";
        } else {
            $stmt = $pdo->prepare("SELECT id, nome_empresa, email FROM empresas WHERE cnpj = ?");
            $stmt->execute([$cnpj]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empresa || $empresa['email'] !== $email) {
                $msg = "CNPJ ou e-mail não correspondem a uma empresa cadastrada.";
                $msg_type = "danger";
            } else {
                $empresa_id = $empresa['id'];
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE empresa_id = ? AND cargo = 'Admin'");
                $stmt->execute([$empresa_id]);

                if ($stmt->fetch()) {
                    $msg = "Já existe um administrador para esta empresa. Use o login normal.";
                    $msg_type = "warning";
                } else {
                    // Gerar token
                    $token = bin2hex(random_bytes(32));
                    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Criar conta pendente com token
                    $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, cargo, status, data_cadastro, token_ativacao, token_expira) VALUES (?, 'Administrador', ?, 'Admin', 0, NOW(), ?, ?)");
                    
                    if ($stmt->execute([$empresa_id, $email, $token, $expira])) {
                        $link_ativacao = "http://localhost/frutnorte/setup.php?token=$token";

                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'felipeservelhere.calory@gmail.com';
                            $mail->Password   = 'dtej ghad jcum lojf';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            $mail->setFrom('felipeservelhere.calory@gmail.com', 'Calory Sistemas');
                            $mail->addAddress($email, 'Administrador');
                            $mail->isHTML(true);
                            $mail->Subject = 'Ative sua conta - Calory Sistemas';

                            $mail->Body = "
                            <div style='font-family: Arial, sans-serif; padding: 20px;'>
                                <h2>Bem-vindo ao Calory Sistemas!</h2>
                                <p>Para ativar sua conta de administrador da empresa <strong>{$empresa['nome_empresa']}</strong>, clique no botão abaixo:</p>
                                <a href='$link_ativacao' style='display:inline-block;padding:12px 20px;background:#6B46C1;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Acessar Sistema Agora</a>
                                <p style='margin-top:20px;font-size:12px;color:#555;'>Se o botão não funcionar, copie e cole este link no navegador:</p>
                                <p style='font-size:12px;color:#555;'>$link_ativacao</p>
                                <hr>
                                <p style='font-size:11px;color:#888;'>Link válido por 1 hora por motivos de segurança.</p>
                            </div>";

                            $mail->send();
                            $msg = "Link de ativação enviado! Verifique seu e-mail.";
                            $msg_type = "success";
                        } catch (Exception $e) {
                            $msg = "Conta criada, mas houve erro ao enviar e-mail: {$mail->ErrorInfo}";
                            $msg_type = "warning";
                        }
                    } else {
                        $msg = "Erro ao criar conta de administrador.";
                        $msg_type = "danger";
                    }
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
<title>Login - Calory Sistemas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: linear-gradient(135deg, #f6f8fb 0%, #e9eef7 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    .login-card {
        width: 100%;
        max-width: 420px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #eef2f7;
        padding: 32px;
    }
    .page-title {
        text-align: center;
        font-size: 28px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 8px;
    }
    .login-header {
        text-align: center;
        font-size: 18px;
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 32px;
    }
    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
        font-size: 14px;
    }
    .form-control {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 12px 16px;
        font-size: 14px;
        transition: all 0.2s;
    }
    .form-control:focus {
        border-color: #3B82F6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    .btn-primary {
        background: #3B82F6;
        border: none;
        border-radius: 10px;
        padding: 12px 24px;
        font-size: 15px;
        font-weight: 600;
        color: #ffffff;
        width: 100%;
        transition: all 0.2s;
        box-shadow: 0 8px 20px rgba(59,130,246,0.35);
    }
    .btn-primary:hover {
        background: #2563EB;
        transform: translateY(-1px);
        box-shadow: 0 10px 25px rgba(59,130,246,0.4);
    }
    .btn-primary:active {
        background: #1D4ED8;
        transform: translateY(0);
    }
    .help-text {
        text-align: center;
        font-size: 13px;
        color: #6b7280;
        margin-top: 16px;
    }
    .link-discrete {
        text-align: center;
        margin-top: 20px;
    }
    .link-discrete a {
        color: #6b7280;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }
    .link-discrete a:hover {
        color: #3B82F6;
    }
    .alert {
        border-radius: 10px;
        font-size: 14px;
        margin-bottom: 20px;
    }
    #firstAccessForm {
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid #e5e7eb;
    }
    .form-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">
            <div class="page-title">Calory Sistemas</div>
            <div class="login-card">
                <div class="login-header">Acesso ao sistema</div>

                <?php if ($msg): ?>
                    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                        <?= htmlspecialchars($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <div id="loginForm">
                    <form method="POST">
                        <input type="hidden" name="login" value="1">
                        <div class="mb-3">
                            <label for="email_login" class="form-label">E-mail ou usuário</label>
                            <input type="text" class="form-control" id="email_login" name="email_login" placeholder="seuemail@empresa.com ou usuário" required>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </form>

                    <div class="help-text">Esqueceu a senha? Fale com o suporte.</div>

                    <div class="link-discrete">
                        <a href="javascript:void(0)" id="showFirstAccess">Primeiro acesso?</a>
                    </div>
                </div>

                <!-- Primeiro Acesso Form (hidden by default) -->
                <div id="firstAccessForm" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="primeiro_acesso" value="1">
                        <div class="mb-3">
                            <label for="cnpj" class="form-label">CNPJ</label>
                            <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
                            <div class="form-text">Digite o CNPJ cadastrado da sua empresa</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="exemplo@empresa.com" required>
                            <div class="form-text">Digite o e-mail cadastrado da empresa</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Solicitar senha</button>
                    </form>

                    <div class="link-discrete">
                        <a href="javascript:void(0)" id="backToLogin">Voltar ao login</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle entre Login e Primeiro Acesso
document.getElementById('showFirstAccess').addEventListener('click', function() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('firstAccessForm').style.display = 'block';
});

document.getElementById('backToLogin').addEventListener('click', function() {
    document.getElementById('firstAccessForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'block';
});

// Formatar CNPJ
document.getElementById('cnpj').addEventListener('input', function(e){
    let v = e.target.value.replace(/\D/g,'');
    v = v.replace(/^(\d{2})(\d)/,'$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
    v = v.replace(/(\d{4})(\d{2})$/,'$1-$2');
    e.target.value = v;
});
</script>
</body>
</html>