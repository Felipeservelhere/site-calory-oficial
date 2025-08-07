<?php
// Recebe o parâmetro ret via GET
$retorno = isset($_GET['ret']) ? $_GET['ret'] : '';
if (empty($retorno)) {
    die("Parâmetro 'ret' não fornecido.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Leitor de QR Code - Calory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #000;
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    h1 {
      margin: 20px;
      font-size: 1.5rem;
    }
    #reader {
      width: 100%;
      max-width: 600px;
      margin-bottom: 10px;
    }
    .controls {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-bottom: 20px;
    }
    button {
      padding: 10px;
      font-size: 14px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    .flash-btn {
      background-color: #ff9900;
      color: #000;
    }
    .switch-btn {
      background-color: #00ccff;
      color: #000;
    }
  </style>
</head>
<body>

  <h1>Leitor de QR Code</h1>

  <div id="reader"></div>

  <div class="controls">
    <button id="toggle-flash" class="flash-btn" style="display: none;">Ligar Flash</button>
    <button id="switch-camera" class="switch-btn">Trocar Câmera</button>
  </div>

  <script>
    const urlBase = <?php echo json_encode($retorno); ?>;
    let currentCameraId = null;
    let isFlashOn = false;
    let cameras = [];
    let html5QrCode;

    function startScanner(cameraId) {
      const config = {
        fps: 30,
        qrbox: { width: 300, height: 300 },
        aspectRatio: 1.77778,
        experimentalFeatures: {
          useBarCodeDetectorIfSupported: true
        },
        rememberLastUsedCamera: true
      };

      html5QrCode = new Html5Qrcode("reader");
      html5QrCode.start(
        cameraId,
        config,
        decodedText => {
          if (!window.qrProcessed) {
            window.qrProcessed = true;
            const destino = urlBase.replace('{CODE}', encodeURIComponent(decodedText));
            html5QrCode.stop().then(() => {
              window.location.href = destino;
            });
          }
        },
        error => {
          // Ignorar erros pequenos
        }
      ).then(() => {
        currentCameraId = cameraId;
        html5QrCode.getRunningTrackSettings().then(settings => {
          const capabilities = html5QrCode.getRunningTrackCapabilities();
          if (capabilities && capabilities.torch) {
            document.getElementById('toggle-flash').style.display = 'inline-block';
          }
        });
      });
    }

    async function setupScanner() {
      cameras = await Html5Qrcode.getCameras();
      if (cameras && cameras.length) {
        const backCamera = cameras.find(cam => /back|traseira/i.test(cam.label)) || cameras[0];
        startScanner(backCamera.id);
      } else {
        alert("Nenhuma câmera disponível.");
      }
    }

    document.getElementById("switch-camera").addEventListener("click", () => {
      if (!cameras.length) return;
      const currentIndex = cameras.findIndex(cam => cam.id === currentCameraId);
      const nextIndex = (currentIndex + 1) % cameras.length;
      html5QrCode.stop().then(() => {
        startScanner(cameras[nextIndex].id);
      });
    });

    document.getElementById("toggle-flash").addEventListener("click", () => {
      if (!html5QrCode) return;
      isFlashOn = !isFlashOn;
      html5QrCode.applyVideoConstraints({
        advanced: [{ torch: isFlashOn }]
      }).then(() => {
        document.getElementById("toggle-flash").innerText = isFlashOn ? "Desligar Flash" : "Ligar Flash";
      }).catch(e => {
        alert("Flash não suportado neste dispositivo.");
      });
    });

    setupScanner();
  </script>

</body>
</html>
