<?php
$servername = "localhost";
$username = "root";
$password = "@@rOOt@cAlOry@1967@@";
$dbname = "meubanco";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>