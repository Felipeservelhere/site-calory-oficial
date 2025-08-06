<?php
include 'conexao.php';

if (isset($_GET['nome'])) {
    $nome = $_GET['nome'];

    $sql = "SELECT imagem, tipo_imagem FROM banners WHERE nome = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $nome);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $imagem = $row['imagem'];
            $tipo_imagem = $row['tipo_imagem'];

            header("Content-type: " . $tipo_imagem);
            echo $imagem;
        } else {
            // Imagem não encontrada
            header("HTTP/1.0 404 Not Found");
            echo "Imagem não encontrada.";
        }
        $stmt->close();
    } else {
        // Erro na preparação da query
        header("HTTP/1.0 500 Internal Server Error");
        echo "Erro ao preparar a consulta.";
    }
    $conn->close();
} else {
    // Nome da imagem não fornecido
    header("HTTP/1.0 400 Bad Request");
    echo "Nome da imagem não fornecido.";
}
?>