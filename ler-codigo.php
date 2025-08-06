<?php
$paginaRetorno = 'http://192.168.0.160/CaloryWebR4Hrestaurante/venda/pesquisa.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ler Código de Barras</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        #leitor {
            width: 100%;
            max-width: 400px;
            margin: 40px auto;
        }
        body {
            font-family: sans-serif;
            text-align: center;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .btn-cancelar {
            margin-top: 20px;
            font-size: 18px;
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-cancelar:hover {
            background-color: #c9302c;
        }
    </style>
</head>
<body>
    <h1>Ler Código de Barras</h1>
    <div id="leitor"></div>
    <button class="btn-cancelar" onclick="window.location.href='<?php echo $paginaRetorno; ?>'">Cancelar</button>

    <script>
        const html5QrCode = new Html5Qrcode("leitor");

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: 250 },
                    qrCodeMessage => {
                        html5QrCode.stop().then(() => {
                            const destino = "<?php echo $paginaRetorno; ?>?qrcode=" + encodeURIComponent(qrCodeMessage);
                            window.location.href = destino;
                        });
                    },
                    errorMessage => {
                        console.warn("Erro na leitura:", errorMessage);
                    }
                ).catch(err => {
                    alert("Erro ao iniciar a câmera: " + err);
                });
            } else {
                alert("Nenhuma câmera detectada.");
            }
        }).catch(err => {
            alert("Erro ao acessar a câmera: " + err);
        });
    </script>
</body>
</html>
