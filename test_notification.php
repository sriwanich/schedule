<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบ Push Notification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .btn-test {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .log-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .log-entry {
            padding: 5px;
            border-bottom: 1px solid #e9ecef;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="text-center mb-4">
            <i class="bi bi-bell-fill text-primary"></i>
            ทดสอบ Push Notification
        </h1>

        <div id="statusBox" class="status-box status-warning">
            <strong><i class="bi bi-info-circle"></i> สถานะ:</strong>
            <span id="statusText">กำลังตรวจสอบการรองรับ...</span>
        </div>

        <div class="mb-4">
            <h5><i class="bi bi-gear"></i> การตั้งค่า</h5>
            <div class="form-group mb-3">
                <label class="form-label">หัวข้อแจ้งเตือน</label>
                <input type="text" id="notifTitle" class="form-control" value="ทดสอบการแจ้งเตือน">
            </div>
            <div class="form-group mb-3">
                <label class="form-label">ข้อความ</label>
                <textarea id="notifBody" class="form-control" rows="3">นี่คือการทดสอบ Push Notification จากระบบของคุณ</textarea>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <button id="requestPermBtn" class="btn btn-primary btn-test">
                    <i class="bi bi-shield-check"></i> ขออนุญาตแจ้งเตือน
                </button>
            </div>
            <div class="col-md-6">
                <button id="subscribeBtn" class="btn btn-success btn-test" disabled>
                    <i class="bi bi-bell-fill"></i> Subscribe
                </button>
            </div>
            <div class="col-md-6">
                <button id="testLocalBtn" class="btn btn-info btn-test" disabled>
                    <i class="bi bi-send"></i> ทดสอบแจ้งเตือนทันที
                </button>
            </div>
            <div class="col-md-6">
                <button id="unsubscribeBtn" class="btn btn-danger btn-test" disabled>
                    <i class="bi bi-bell-slash"></i> Unsubscribe
                </button>
            </div>
        </div>

        <div class="mb-4">
            <h5><i class="bi bi-list-ul"></i> Log การทำงาน</h5>
            <div id="logBox" class="log-box">
                <div class="log-entry text-muted">รอการทำงาน...</div>
            </div>
        </div>

        <div class="alert alert-info">
            <strong><i class="bi bi-info-circle"></i> วิธีใช้งาน:</strong>
            <ol class="mb-0 mt-2">
                <li>คลิก "ขออนุญาตแจ้งเตือน" เพื่ขออนุญาตจากเบราว์เซอร์</li>
                <li>คลิก "Subscribe" เพื่อลงทะเบียนรับการแจ้งเตือน</li>
                <li>คลิก "ทดสอบแจ้งเตือนทันที" เพื่อส่งการแจ้งเตือนทดสอบ</li>
                <li>คลิก "Unsubscribe" เมื่อต้องการยกเลิก</li>
            </ol>
        </div>
    </div>

    <script>
        // ต้องแทนที่ด้วย VAPID Public Key ของคุณ
        const VAPID_PUBLIC_KEY = 'BAYl0GpGYm6ohG-SPMOviCjfYpTRs0CFQW-_okleCSD-tYUYBDbbNjSsCGkKHAsLoy3VP-14Ze-YyQByA8YpsCA';

        let registration = null;
        let subscription = null;

        // ฟังก์ชัน log
        function addLog(message, type = 'info') {
            const logBox = document.getElementById('logBox');
            const time = new Date().toLocaleTimeString('th-TH');
            const colors = {
                'info': '#17a2b8',
                'success': '#28a745',
                'error': '#dc3545',
                'warning': '#ffc107'
            };
            
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.innerHTML = `<strong style="color: ${colors[type]}">[${time}]</strong> ${message}`;
            
            logBox.appendChild(entry);
            logBox.scrollTop = logBox.scrollHeight;
        }

        // อัพเดทสถานะ
        function updateStatus(message, type = 'warning') {
            const statusBox = document.getElementById('statusBox');
            const statusText = document.getElementById('statusText');
            
            statusBox.className = `status-box status-${type}`;
            statusText.textContent = message;
        }

        // แปลง base64 เป็น Uint8Array
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        // ตรวจสอบการรองรับ
        async function checkSupport() {
            if (!('serviceWorker' in navigator)) {
                updateStatus('เบราว์เซอร์ไม่รองรับ Service Worker', 'error');
                addLog('เบราว์เซอร์ไม่รองรับ Service Worker', 'error');
                return false;
            }

            if (!('PushManager' in window)) {
                updateStatus('เบราว์เซอร์ไม่รองรับ Push Notification', 'error');
                addLog('เบราว์เซอร์ไม่รองรับ Push Notification', 'error');
                return false;
            }

            updateStatus('เบราว์เซอร์รองรับ Push Notification', 'success');
            addLog('เบราว์เซอร์รองรับ Push Notification ✓', 'success');
            return true;
        }

        // ลงทะเบียน Service Worker
        async function registerServiceWorker() {
            try {
                registration = await navigator.serviceWorker.register('/service-worker.js');
                addLog('ลงทะเบียน Service Worker สำเร็จ ✓', 'success');
                return registration;
            } catch (error) {
                addLog('ลงทะเบียน Service Worker ล้มเหลว: ' + error.message, 'error');
                throw error;
            }
        }

        // ขออนุญาต
        async function requestPermission() {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                updateStatus('ได้รับอนุญาตแล้ว กรุณา Subscribe', 'success');
                addLog('ได้รับอนุญาตแจ้งเตือนแล้ว ✓', 'success');
                document.getElementById('subscribeBtn').disabled = false;
                return true;
            } else {
                updateStatus('ไม่ได้รับอนุญาต', 'error');
                addLog('ผู้ใช้ปฏิเสธการขออนุญาต', 'error');
                return false;
            }
        }

        // Subscribe
        async function subscribe() {
            try {
                if (!registration) {
                    registration = await navigator.serviceWorker.ready;
                }

                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });

                addLog('Subscribe สำเร็จ ✓', 'success');

                // ส่งข้อมูลไปบันทึกที่ server
                const response = await fetch('/push_manager.php?action=subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(subscription)
                });

                const data = await response.json();
                
                if (data.success) {
                    updateStatus('Subscribe สำเร็จ - พร้อมรับการแจ้งเตือน', 'success');
                    addLog('บันทึกข้อมูล subscription ที่ server สำเร็จ ✓', 'success');
                    document.getElementById('testLocalBtn').disabled = false;
                    document.getElementById('unsubscribeBtn').disabled = false;
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                addLog('Subscribe ล้มเหลว: ' + error.message, 'error');
                updateStatus('Subscribe ล้มเหลว', 'error');
            }
        }

        // Unsubscribe
        async function unsubscribe() {
            try {
                if (!subscription) {
                    const reg = await navigator.serviceWorker.ready;
                    subscription = await reg.pushManager.getSubscription();
                }

                if (subscription) {
                    await fetch('/push_manager.php?action=unsubscribe', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(subscription)
                    });

                    await subscription.unsubscribe();
                    subscription = null;

                    updateStatus('Unsubscribe สำเร็จ', 'warning');
                    addLog('ยกเลิก subscription สำเร็จ ✓', 'warning');
                    document.getElementById('testLocalBtn').disabled = true;
                    document.getElementById('unsubscribeBtn').disabled = true;
                }
            } catch (error) {
                addLog('Unsubscribe ล้มเหลว: ' + error.message, 'error');
            }
        }

        // ทดสอบส่ง notification ทันที
        function testLocalNotification() {
            const title = document.getElementById('notifTitle').value;
            const body = document.getElementById('notifBody').value;

            if (Notification.permission === 'granted') {
                new Notification(title, {
                    body: body,
                    icon: '/images/logo.jpg',
                    badge: '/images/logo.jpg',
                    vibrate: [200, 100, 200]
                });
                addLog('ส่งการแจ้งเตือนทดสอบสำเร็จ ✓', 'success');
            } else {
                addLog('ไม่สามารถส่งการแจ้งเตือนได้ - ยังไม่ได้รับอนุญาต', 'error');
            }
        }

        // เริ่มต้น
        window.addEventListener('load', async () => {
            addLog('เริ่มต้นระบบทดสอบ...', 'info');
            
            const supported = await checkSupport();
            
            if (supported) {
                await registerServiceWorker();
                
                // ตรวจสอบสถานะปัจจุบัน
                if (Notification.permission === 'granted') {
                    addLog('มีสิทธิ์แจ้งเตือนอยู่แล้ว', 'success');
                    document.getElementById('subscribeBtn').disabled = false;
                    
                    // ตรวจสอบว่ามี subscription อยู่แล้วหรือไม่
                    const reg = await navigator.serviceWorker.ready;
                    subscription = await reg.pushManager.getSubscription();
                    
                    if (subscription) {
                        updateStatus('Subscribe อยู่แล้ว - พร้อมรับการแจ้งเตือน', 'success');
                        addLog('พบ subscription ที่มีอยู่แล้ว', 'info');
                        document.getElementById('testLocalBtn').disabled = false;
                        document.getElementById('unsubscribeBtn').disabled = false;
                    } else {
                        updateStatus('มีสิทธิ์แล้ว - กรุณา Subscribe', 'warning');
                    }
                } else if (Notification.permission === 'denied') {
                    updateStatus('ถูกปฏิเสธ - กรุณาเปิดอนุญาตใน Browser Settings', 'error');
                    addLog('การแจ้งเตือนถูกปฏิเสธโดยผู้ใช้', 'error');
                } else {
                    updateStatus('พร้อมใช้งาน - กรุณาขออนุญาต', 'warning');
                }
            }

            // Event Listeners
            document.getElementById('requestPermBtn').addEventListener('click', requestPermission);
            document.getElementById('subscribeBtn').addEventListener('click', subscribe);
            document.getElementById('unsubscribeBtn').addEventListener('click', unsubscribe);
            document.getElementById('testLocalBtn').addEventListener('click', testLocalNotification);
        });
    </script>
</body>
</html>