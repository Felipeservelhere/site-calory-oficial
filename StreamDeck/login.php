<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    if ($usuario === 'admin' && $senha === 'FiatBCR-1230') {
        $_SESSION['usuario'] = $usuario;
        header("Location: regimar.php");
        exit();
    } else {
        $erro = "Usuário ou senha inválidos";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Calory Sistemas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.css" />
    <style>
        body {
            background-color: #f8f8f8;
        }
        .login-container {
            margin-top: 120px;
        }
        .login-box {
            max-width: 400px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            width: 120px;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-box">
            <div class="logo">
                <img src="calory png.png" alt="Calory Sistemas">
            </div>
            <h4 class="text-center">Acesso ao Sistema</h4>
            <?php if (isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>
            <form method="post">
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" name="usuario" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
