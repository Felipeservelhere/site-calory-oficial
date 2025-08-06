<?php
$mysqli = new mysqli("localhost", "root", "@@rOOt@cAlOry@1967@@", "meubanco");

if ($mysqli->connect_error) {
    die("Falha na conexão: " . $mysqli->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tipoSelecionado = $_POST['tipo'];

    // Define o nome do banner e o tipo de dispositivo com base no tipo selecionado
    $nome = '';
    $tipo_dispositivo = '';

    switch ($tipoSelecionado) {
        case 'principal-1':
            $nome = 'banner-1';
            $tipo_dispositivo = 'desktop';
            break;
        case 'principal-1-mobile':
            $nome = 'banner-1';
            $tipo_dispositivo = 'mobile';
            break;
        case 'principal-2':
            $nome = 'banner-2';
            $tipo_dispositivo = 'desktop';
            break;
        case 'principal-2-mobile':
            $nome = 'banner-2';
            $tipo_dispositivo = 'mobile';
            break;
        case 'principal-3':
            $nome = 'banner-3';
            $tipo_dispositivo = 'desktop';
            break;
        case 'principal-3-mobile':
            $nome = 'banner-3';
            $tipo_dispositivo = 'mobile';
            break;
        default:
            die("Tipo inválido.");
    }

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $tmp_nome = $_FILES['imagem']['tmp_name'];
        $tipo_imagem = $_FILES['imagem']['type'];
        $conteudo_imagem = file_get_contents($tmp_nome);

        // Verifica se já existe um banner com esse nome e tipo de dispositivo
        $stmt = $mysqli->prepare("SELECT id FROM banners WHERE nome = ? AND tipo_dispositivo = ?");
        if (!$stmt) {
            die("Erro na preparação da query SELECT: " . $mysqli->error);
        }
        $stmt->bind_param("ss", $nome, $tipo_dispositivo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Atualiza se já existir
            $stmt = $mysqli->prepare("UPDATE banners SET imagem = ?, tipo_imagem = ?, criado_em = NOW() WHERE nome = ? AND tipo_dispositivo = ?");
            if (!$stmt) {
                die("Erro na preparação da query UPDATE: " . $mysqli->error);
            }
            $stmt->bind_param("ssss", $conteudo_imagem, $tipo_imagem, $nome, $tipo_dispositivo);
        } else {
            // Insere se for novo
            $stmt = $mysqli->prepare("INSERT INTO banners (nome, imagem, tipo_imagem, tipo_dispositivo) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                die("Erro na preparação da query INSERT: " . $mysqli->error);
            }
            $stmt->bind_param("ssss", $nome, $conteudo_imagem, $tipo_imagem, $tipo_dispositivo);
        }

        if ($stmt->execute()) {
            echo "✅ $nome ($tipo_dispositivo) enviado com sucesso!";
        } else {
            echo "❌ Erro ao salvar no banco: " . $stmt->error;
        }
    } else {
        echo "❌ Erro no upload da imagem.";
    }
}
?>