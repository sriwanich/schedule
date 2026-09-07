<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - ไม่พบหน้า | RDSIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Sarabun', sans-serif;
      background: #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 24px;
    }
    .error-card {
      background: #fff;
      border-radius: 20px;
      padding: 56px 48px;
      text-align: center;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 20px 40px rgba(0,0,0,.08);
    }
    .error-code {
      font-size: 96px;
      font-weight: 800;
      color: #e2e8f0;
      line-height: 1;
      margin-bottom: 16px;
      letter-spacing: -4px;
    }
    .error-icon { font-size: 48px; margin-bottom: 16px; }
    h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
    p  { font-size: 14px; color: #64748b; line-height: 1.7; margin-bottom: 32px; }
    a  {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #1d4ed8;
      color: #fff;
      padding: 10px 24px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: background .15s;
    }
    a:hover { background: #1e40af; }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="error-icon">🔍</div>
    <div class="error-code">404</div>
    <h1>ไม่พบหน้าที่ต้องการ</h1>
    <p>ขออภัย ไม่พบหน้าที่คุณกำลังค้นหา<br>อาจถูกย้ายหรือลบออกไปแล้ว</p>
    <a href="/">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
      </svg>
      กลับหน้าหลัก
    </a>
  </div>
</body>
</html>
