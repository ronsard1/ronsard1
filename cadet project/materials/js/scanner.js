// Barcode scanner functionality
document.addEventListener('DOMContentLoaded', function() {
    const startScannerBtn = document.getElementById('startScanner');
    const stopScannerBtn = document.getElementById('stopScanner');
    const manualEntryBtn = document.getElementById('manualEntry');
    const barcodeVideo = document.getElementById('barcode-video');
    const barcodeInput = document.getElementById('barcode');
    const scannerStatus = document.getElementById('scanner-status');
    let scannerActive = false;
    
    if (startScannerBtn) {
        startScannerBtn.addEventListener('click', startBarcodeScanner);
        stopScannerBtn.addEventListener('click', stopBarcodeScanner);
        manualEntryBtn.addEventListener('click', focusBarcodeInput);
        
        // Auto-generate barcode based on material name
        document.getElementById('name')?.addEventListener('blur', function() {
            if (barcodeInput && !barcodeInput.value) {
                const name = this.value.replace(/\s+/g, '').toUpperCase();
                const timestamp = Date.now().toString().slice(-6);
                barcodeInput.value = name.slice(0, 8) + timestamp;
            }
        });
    }
    
    function startBarcodeScanner() {
        if (scannerActive) return;
        
        if (!navigator.mediaDevices) {
            scannerStatus.textContent = 'Camera not supported';
            return;
        }
        
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: barcodeVideo,
                constraints: {
                    width: 400,
                    height: 300,
                    facingMode: "environment"
                }
            },
            decoder: {
                readers: ["code_128_reader", "ean_reader", "upc_reader"]
            }
        }, function(err) {
            if (err) {
                console.error('Scanner error:', err);
                scannerStatus.textContent = 'Error starting scanner: ' + err.message;
                return;
            }
            
            Quagga.start();
            scannerActive = true;
            barcodeVideo.style.display = 'block';
            document.querySelector('.scanner-overlay').style.display = 'block';
            startScannerBtn.style.display = 'none';
            stopScannerBtn.style.display = 'inline-flex';
            scannerStatus.textContent = 'Scanner active - point camera at barcode';
        });
        
        Quagga.onDetected(function(result) {
            const code = result.codeResult.code;
            barcodeInput.value = code;
            scannerStatus.textContent = 'Barcode scanned: ' + code;
            stopBarcodeScanner();
            
            // Show success message
            setTimeout(() => {
                scannerStatus.textContent = 'Barcode captured successfully!';
            }, 1000);
        });
    }
    
    function stopBarcodeScanner() {
        if (scannerActive) {
            Quagga.stop();
            scannerActive = false;
            barcodeVideo.style.display = 'none';
            document.querySelector('.scanner-overlay').style.display = 'none';
            startScannerBtn.style.display = 'inline-flex';
            stopScannerBtn.style.display = 'none';
            scannerStatus.textContent = 'Scanner ready';
        }
    }
    
    function focusBarcodeInput() {
        stopBarcodeScanner();
        barcodeInput.focus();
        scannerStatus.textContent = 'Manual entry mode';
    }
});