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

// ====================== LOGIN NORMAL ======================
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
                $senha_aleatoria = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $senha_hash = password_hash($senha_aleatoria, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha, cargo, status, data_cadastro, usuario) VALUES (?, 'Administrador', ?, ?, 'Admin', 1, NOW(), NULL)");

                if ($stmt->execute([$empresa_id, $email, $senha_hash])) {
                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'felipeservelhere.calory@gmail.com';
                        $mail->Password   = 'llal vitv sami oqfr';
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;


                        $mail->setFrom('seuemail@gmail.com', 'Calory Sistemas');
                        $mail->addAddress($email, 'Administrador');

                        $mail->isHTML(false);
                        $mail->Subject = 'Senha de Primeiro Acesso - Calory Sistemas';
                        $mail->Body    = "Olá,\n\nBem-vindo ao sistema Calory Sistemas!\n\nEmpresa: {$empresa['nome_empresa']}\nCNPJ: {$cnpj}\n\nSua senha temporária de administrador é: {$senha_aleatoria}\n\nApós login, defina seu usuário e nova senha.\n\nE-mail: {$email}\n\nAtenciosamente,\nEquipe Calory Sistemas";

                        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Mostra todo o processo de conexão SMTP
                        $mail->Debugoutput = 'html';
                        
                        $mail->send();
                        $msg = "Conta criada com sucesso! Verifique seu e-mail para senha temporária.";
                        $msg_type = "success";
                    } catch (Exception $e) {
                        $msg = "Erro ao enviar e-mail: {$mail->ErrorInfo}. Conta criada, mas senha não enviada.";
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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    body {
        background: linear-gradient(135deg, #e0eafc, #cfdef3);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-card {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        overflow: hidden;
        transition: all 0.3s;
    }
    .login-card:hover { transform: translateY(-3px); }
    .login-header { background: #6B46C1; color: white; text-align: center; padding: 20px; font-size: 1.25rem; }
    .login-header i { margin-right: 10px; }
    .btn-login { background: #6B46C1; color: white; border-radius: 8px; font-weight: 600; }
    .btn-login:hover { background: #5b21b6; }
    .btn-collapse { border-radius: 8px; font-weight: 500; }
</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card login-card">
                <div class="login-header"><i class="fas fa-lock"></i>Login Calory Sistemas</div>
                <div class="card-body p-4">

                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST">
                        <input type="hidden" name="login" value="1">
                        <div class="mb-3">
                            <label for="email_login" class="form-label">E-mail ou Usuário</label>
                            <input type="text" class="form-control" id="email_login" name="email_login" required>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        <button type="submit" class="btn btn-login w-100 mb-3"><i class="fas fa-sign-in-alt"></i> Entrar</button>
                    </form>

                    <!-- Primeiro Acesso -->
                    <button class="btn btn-outline-secondary w-100 mb-3 btn-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#primeiroAcessoForm">
                        <i class="fas fa-key"></i> Primeiro Acesso
                    </button>
                    <div class="collapse" id="primeiroAcessoForm">
                        <form method="POST">
                            <input type="hidden" name="primeiro_acesso" value="1">
                            <div class="mb-3">
                                <label for="cnpj" class="form-label">CNPJ *</label>
                                <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="exemplo@empresa.com" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-envelope"></i> Solicitar Senha</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
