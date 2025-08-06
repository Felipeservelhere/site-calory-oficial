<?php
// Define a URL de retorno, recebida via parâmetro GET ou usa padrão local
$paginaRetorno = 'http://192.168.0.160/CaloryWebR4Hrestaurante/venda/cartao.php';
$filtroCodigo = isset($_GET['filtroCodigo']) ? $_GET['filtroCodigo'] : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ler Código de Barras / QR Code</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            background: linear-gradient(135deg, #00BFFF, #87CEFA);
            color: #333;
            margin: 0;
            padding: 0;
        }

        h1 {
            margin-top: 20px;
            font-size: 2em;
            color: #fff;
            text-shadow: 1px 1px 2px #000;
        }

        #leitor-container {
            position: relative;
            width: 90%;
            max-width: 500px;
            margin: 40px auto;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            background: #000;
        }

        #leitor {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        /* Máscara retangular */
        #mask {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 120px;
            border: 3px solid #00FF00;
            box-sizing: border-box;
            pointer-events: none;
            border-radius: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 10px #00FF00; }
            50% { box-shadow: 0 0 20px #00FF00; }
            100% { box-shadow: 0 0 10px #00FF00; }
        }

        .btn-group {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn {
            font-size: 18px;
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: 0.3s;
            color: #fff;
        }

        .btn-cancelar {
            background-color: #d9534f;
        }

        .btn-cancelar:hover {
            background-color: #c9302c;
        }

        .btn-flash {
            background-color: #28a745;
        }

        .btn-flash:hover {
            background-color: #218838;
        }

        @media(max-width:600px){
            #leitor {
                height: 300px;
            }
            #mask {
                height: 90px;
            }
            .btn {
                font-size: 16px;
                padding: 8px 20px;
            }
        }
    </style>
</head>
<body>
    <h1>Ler Código de Barras / QR Code</h1>
    <div id="leitor-container">
        <div id="leitor"></div>
        <div id="mask"></div>
    </div>

    <div class="btn-group">
        <button class="btn btn-flash" id="toggleFlash">Ligar Flash</button>
        <button class="btn btn-cancelar" onclick="window.location.href='<?php echo $paginaRetorno; ?>'">Cancelar</button>
    </div>

    <script>
        let flashOn = false;
        let html5QrCode;
        let currentCameraId;

        function startScanner(cameraId = { facingMode: "environment" }) {
            html5QrCode = new Html5Qrcode("leitor");
            const config = { 
                fps: 10, 
                qrbox: { width: 300, height: 120 }, // corresponde à máscara
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

            html5QrCode.start(
                cameraId,
                config,
                qrCodeMessage => {
                    html5QrCode.stop().then(() => {
                        let destino = "<?php echo $paginaRetorno; ?>?filtroCodigo=" + encodeURIComponent(qrCodeMessage);
                        window.location.href = destino;
                    });
                },
                errorMessage => {
                    console.warn("Erro na leitura:", errorMessage);
                }
            ).catch(err => {
                alert("Erro ao iniciar a câmera: " + err);
            });
        }

        Html5Qrcode.getCameras().then(cameras => {
            if(cameras && cameras.length){
                currentCameraId = cameras[0].id;
                startScanner({ facingMode: "environment" });
            } else {
                alert("Nenhuma câmera detectada.");
            }
        }).catch(err => alert("Erro ao acessar a câmera: " + err));

        document.getElementById("toggleFlash").addEventListener("click", function(){
            if(!html5QrCode) return;
            flashOn = !flashOn;
            html5QrCode.applyVideoConstraints({ advanced: [{ torch: flashOn }] })
                .then(() => this.innerText = flashOn ? "Desligar Flash" : "Ligar Flash")
                .catch(err => alert("Flash não suportado: " + err));
        });
    </script>
</body>
</html>