<?php
$mysqli = new mysqli("localhost", "root", "@@rOOt@cAlOry@1967@@", "meubanco");

$result = $mysqli->query("SELECT id, nome, tipo_imagem, imagem FROM banners");

while ($row = $result->fetch_assoc()) {
    echo "<h3>" . htmlspecialchars($row['nome']) . "</h3>";
    $img_data = base64_encode($row['imagem']);
    $mime = $row['tipo_imagem'];
    echo "<img src='data:$mime;base64,$img_data' width='300'><hr>";
}
?>
