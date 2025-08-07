<?php
$retorno = isset($_GET['ret']) ? $_GET['ret'] : 'http://localhost';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ler Código com ZXing</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script type="module">
        import { BrowserMultiFormatReader } from 'https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.1/+esm';

        const codeReader = new BrowserMultiFormatReader();
        const video = document.getElementById('video');

        let retornoUrl = "<?php echo $retorno; ?>";

        function redirecionarComCodigo(code) {
            const destino = retornoUrl.replace("{CODE}", encodeURIComponent(code));
            window.location.href = destino;
        }

        async function iniciarLeitura() {
            try {
                const devices = await codeReader.listVideoInputDevices();
                const cam = devices.find(d => d.label.toLowerCase().includes('back')) || devices[0];

                await codeReader.decodeFromVideoDevice(cam.deviceId, video, (result, err) => {
                    if (result) {
                        console.log("Código detectado:", result.text);
                        codeReader.reset(); // Para leitura única
                        redirecionarComCodigo(result.text);
                    }
                    if (err && !(err instanceof NotFoundException)) {
                        console.warn("Erro:", err);
                    }
                });
            } catch (e) {
                console.error("Erro ao iniciar câmera:", e);
                document.getElementById('status').innerText = "Erro ao acessar a câmera";
            }
        }

        window.addEventListener('DOMContentLoaded', iniciarLeitura);
    </script>
    <style>
        body {
            margin: 0;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
        }
        video {
            width: 100%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 0 10px #00ff00;
        }
        #status {
            margin-top: 15px;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <video id="video" autoplay muted playsinline></video>
    <div id="status">Aguardando leitura do código...</div>
</body>
</html>
