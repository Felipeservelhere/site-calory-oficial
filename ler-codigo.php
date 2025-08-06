<?php
// Define a URL de retorno, recebida via parâmetro GET ou usa padrão local
$paginaRetorno = isset($_GET['retorno']) ? $_GET['retorno'] : 'http://192.168.0.160/CaloryWebR4Hrestaurante/venda/pesquisa.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ler Código de Barras / QR Code</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            padding: 20px;
            background-color: #f9f9f9;
        }
        #leitor {
            width: 100%;
            max-width: 400px;
            margin: 40px auto;
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
        h1 {
            color: #333;
        }
    </style>
</head>
<body>
    <h1>Ler Código de Barras / QR Code</h1>
    <div id="leitor"></div>
    <button class="btn-cancelar" onclick="window.location.href='<?php echo $paginaRetorno; ?>'">Cancelar</button>

    <script>
        const html5QrCode = new Html5Qrcode("leitor");

        // Configura o leitor para QR Code e códigos de barras 1D
        const config = { 
            fps: 10, 
            qrbox: 250, 
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E
            ]
        };

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config,
                    qrCodeMessage => {
                        // Para a câmera e redireciona para a URL de retorno
                        html5QrCode.stop().then(() => {
                            const destino = "<?php echo $paginaRetorno; ?>?filtroMesa=" + encodeURIComponent(qrCodeMessage);
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
