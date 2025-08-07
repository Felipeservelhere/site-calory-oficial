<?php
// Define a URL de retorno, recebida via parâmetro GET ou usa padrão local
$paginaRetorno = 'http://192.168.0.160/CaloryWebR4Hrestaurante/venda/cartao.php';
$filtroCodigo = isset($_GET['filtroCodigo']) ? $_GET['filtroCodigo'] : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ler Código de Barras / QR Code</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root {
            --primary: #00BFFF;
            --primary-dark: #0080FF;
            --success: #28a745;
            --danger: #d9534f;
            --white: #ffffff;
            --black: #000000;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #333;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1 {
            margin: 15px 0;
            font-size: 1.8em;
            color: var(--white);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            padding: 0 15px;
        }

        #scanner-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 15px;
        }

        #leitor-container {
            position: relative;
            width: 100%;
            flex: 1;
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            background: var(--black);
            max-height: 70vh;
        }

        #leitor {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Máscara de foco melhorada */
        #mask {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 25%;
            max-height: 200px;
            border: 3px solid #00FF00;
            box-sizing: border-box;
            pointer-events: none;
            border-radius: 8px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 10px #00FF00; opacity: 0.8; }
            50% { box-shadow: 0 0 25px #00FF00; opacity: 1; }
            100% { box-shadow: 0 0 10px #00FF00; opacity: 0.8; }
        }

        .scan-line {
            position: absolute;
            top: 10%;
            left: 10%;
            width: 80%;
            height: 3px;
            background: rgba(0, 255, 0, 0.7);
            animation: scan 2s linear infinite;
            z-index: 10;
        }

        @keyframes scan {
            0% { top: 10%; }
            100% { top: 90%; }
        }

        .btn-group {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            font-size: 1.1em;
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--white);
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            min-width: 160px;
        }

        .btn:active {
            transform: scale(0.95);
        }

        .btn-cancelar {
            background-color: var(--danger);
        }

        .btn-cancelar:hover {
            background-color: #c9302c;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }

        .btn-flash {
            background-color: var(--success);
        }

        .btn-flash:hover {
            background-color: #218838;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }

        .btn-switch-camera {
            background-color: #6c757d;
        }

        .btn-switch-camera:hover {
            background-color: #5a6268;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }

        #status {
            margin: 10px 0;
            color: var(--white);
            font-size: 0.9em;
            min-height: 20px;
        }

        @media(max-width: 768px){
            h1 {
                font-size: 1.5em;
                margin: 10px 0;
            }
            
            .btn {
                font-size: 1em;
                padding: 10px 20px;
                min-width: 120px;
            }
            
            #leitor-container {
                max-height: 60vh;
            }
        }

        @media(max-width: 480px){
            .btn-group {
                gap: 10px;
            }
            
            .btn {
                font-size: 0.9em;
                padding: 8px 15px;
                min-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div id="scanner-container">
        <h1>Posicione o código na área de leitura</h1>
        <div id="status">Iniciando câmera...</div>
        
        <div id="leitor-container">
            <div id="leitor"></div>
            <div id="mask"></div>
            <div class="scan-line"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-flash" id="toggleFlash">Ligar Flash</button>
            <button class="btn btn-switch-camera" id="switchCamera">Trocar Câmera</button>
            <button class="btn btn-cancelar" onclick="window.location.href='<?php echo $paginaRetorno; ?>'">Cancelar</button>
        </div>
    </div>

    <script>
        // Configurações globais
        const config = {
            fps: 30, // Aumentamos a taxa de quadros para leitura mais rápida
            qrbox: { width: 250, height: 100 }, // Área de detecção otimizada
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODABAR,
                Html5QrcodeSupportedFormats.ITF
            ],
            rememberLastUsedCamera: true,
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
        };

        // Variáveis globais
        let html5QrCode;
        let cameras = [];
        let currentCameraIndex = 0;
        let flashOn = false;
        let isScanning = false;
        let lastScanTime = 0;
        const SCAN_COOLDOWN = 1000; // 1 segundo entre leituras

        // Elementos DOM
        const statusElement = document.getElementById('status');
        const toggleFlashBtn = document.getElementById('toggleFlash');
        const switchCameraBtn = document.getElementById('switchCamera');

        // Inicializa o scanner
        async function initScanner() {
            try {
                // Obter lista de câmeras
                cameras = await Html5Qrcode.getCameras();
                
                if (cameras && cameras.length > 0) {
                    updateStatus(`Câmera ${currentCameraIndex + 1}/${cameras.length} detectada`);
                    
                    // Criar instância do scanner
                    html5QrCode = new Html5Qrcode("leitor");
                    
                    // Iniciar com a câmera traseira (environment) ou a primeira disponível
                    const cameraId = cameras.find(c => c.label.includes('back'))?.id || cameras[0].id;
                    await startCamera(cameraId);
                    
                    // Habilitar botão de troca de câmera se houver mais de uma
                    if (cameras.length > 1) {
                        switchCameraBtn.style.display = 'inline-block';
                    }
                } else {
                    throw new Error("Nenhuma câmera detectada");
                }
            } catch (error) {
                console.error("Erro ao inicializar scanner:", error);
                updateStatus(`Erro: ${error.message}`, true);
                toggleFlashBtn.disabled = true;
                switchCameraBtn.disabled = true;
            }
        }

        // Inicia a câmera
        async function startCamera(cameraId) {
            if (isScanning) {
                await html5QrCode.stop();
                isScanning = false;
            }
            
            try {
                updateStatus("Iniciando câmera...");
                await html5QrCode.start(
                    cameraId,
                    config,
                    onScanSuccess,
                    onScanError
                );
                isScanning = true;
                updateStatus("Pronto para escanear");
                
                // Tentar ativar flash se já estava ligado
                if (flashOn) {
                    toggleFlash();
                }
            } catch (error) {
                console.error("Erro ao iniciar câmera:", error);
                updateStatus(`Erro: ${error.message}`, true);
            }
        }

        // Troca para a próxima câmera
        async function switchCamera() {
            if (cameras.length < 2) return;
            
            currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
            updateStatus(`Alternando para câmera ${currentCameraIndex + 1}/${cameras.length}`);
            
            try {
                await startCamera(cameras[currentCameraIndex].id);
            } catch (error) {
                console.error("Erro ao trocar câmera:", error);
                updateStatus(`Erro ao trocar câmera: ${error.message}`, true);
            }
        }

        // Liga/desliga o flash
        async function toggleFlash() {
            if (!html5QrCode || !isScanning) return;
            
            try {
                flashOn = !flashOn;
                await html5QrCode.applyVideoConstraints({
                    advanced: [{ torch: flashOn }]
                });
                toggleFlashBtn.textContent = flashOn ? "Desligar Flash" : "Ligar Flash";
                toggleFlashBtn.style.backgroundColor = flashOn ? "#ffc107" : "var(--success)";
                updateStatus(`Flash ${flashOn ? 'ligado' : 'desligado'}`);
            } catch (error) {
                console.warn("Flash não suportado:", error);
                updateStatus("Flash não disponível nesta câmera");
                toggleFlashBtn.disabled = true;
            }
        }

        // Callback de leitura bem-sucedida
        function onScanSuccess(decodedText) {
            const now = Date.now();
            
            // Evitar leituras múltiplas rápidas
            if (now - lastScanTime < SCAN_COOLDOWN) {
                return;
            }
            
            lastScanTime = now;
            
            // Feedback visual
            document.getElementById('mask').style.borderColor = '#00FF00';
            document.getElementById('mask').style.animation = 'none';
            document.querySelector('.scan-line').style.display = 'none';
            updateStatus("Código lido com sucesso!", false, 'success');
            
            // Parar scanner e redirecionar
            setTimeout(async () => {
                try {
                    await html5QrCode.stop();
                    const destino = `<?php echo $paginaRetorno; ?>?filtroCodigo=${encodeURIComponent(decodedText)}`;
                    window.location.href = destino;
                } catch (error) {
                    console.error("Erro ao parar scanner:", error);
                    updateStatus("Erro ao processar código", true);
                    await startCamera(cameras[currentCameraIndex].id);
                }
            }, 500);
        }

        // Callback de erro na leitura
        function onScanError(errorMessage) {
            // Ignorar erros comuns durante a leitura normal
            if (!errorMessage.includes('No MultiFormat Readers were able to detect')) {
                console.warn("Erro na leitura:", errorMessage);
                updateStatus(`Erro: ${errorMessage}`, true);
            }
        }

        // Atualiza o status na tela
        function updateStatus(message, isError = false, type = 'info') {
            statusElement.textContent = message;
            statusElement.style.color = isError ? '#ff4444' : 
                                      type === 'success' ? '#00C851' : '#ffffff';
        }

        // Event Listeners
        toggleFlashBtn.addEventListener('click', toggleFlash);
        switchCameraBtn.addEventListener('click', switchCamera);

        // Iniciar quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', initScanner);

        // Gerenciar visibilidade da página
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'hidden' && isScanning) {
                await html5QrCode.stop();
                isScanning = false;
            } else if (document.visibilityState === 'visible' && !isScanning && cameras.length > 0) {
                await startCamera(cameras[currentCameraIndex].id);
            }
        });
    </script>
</body>
</html>