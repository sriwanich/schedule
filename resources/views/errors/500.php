<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 - เกิดข้อผิดพลาด | RDSIS</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background: #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
    .error-card { background: #fff; border-radius: 20px; padding: 56px 48px; text-align: center; max-width: 520px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,.08); }
    .error-code { font-size: 96px; font-weight: 800; color: #fee2e2; line-height: 1; margin-bottom: 16px; }
    h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
    p  { font-size: 14px; color: #64748b; line-height: 1.7; margin-bottom: 24px; }
    a  { display: inline-flex; align-items: center; gap: 8px; background: #1d4ed8; color: #fff; padding: 10px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; }
    a:hover { background: #1e40af; }
    .debug-box { margin-top: 24px; background: #1e293b; color: #94a3b8; padding: 20px; border-radius: 12px; text-align: left; font-family: monospace; font-size: 12px; line-height: 1.6; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="error-code">⚠️</div>
    <h1>เกิดข้อผิดพลาดในระบบ</h1>
    <p>ขออภัย ระบบเกิดข้อผิดพลาดที่ไม่คาดคิด<br>กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ</p>
    <a href="/">กลับหน้าหลัก</a>

    <?php if (isset($debug) && $debug && isset($error)): ?>
    <div class="debug-box">
<strong style="color:#f87171"><?= get_class($error) ?></strong>
<strong style="color:#fbbf24"><?= htmlspecialchars($error->getMessage()) ?></strong>
<span style="color:#64748b"><?= htmlspecialchars($error->getFile()) ?>:<?= $error->getLine() ?></span>

<?= htmlspecialchars(implode("\n", array_slice(explode("\n", $error->getTraceAsString()), 0, 15))) ?>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
