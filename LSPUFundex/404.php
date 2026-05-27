<?php
require_once 'config/app.php';
require_once 'includes/functions.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | LSPUFundex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family:'Segoe UI',sans-serif;
            background:linear-gradient(135deg,#0d1b2a,#1b4f72);
            min-height:100vh;
            display:flex; align-items:center; justify-content:center;
        }
        .error-card {
            background:white; border-radius:18px;
            padding:50px 60px; text-align:center;
            box-shadow:0 25px 60px rgba(0,0,0,0.3);
            max-width:480px; width:90%;
        }
        .error-code { font-size:90px; font-weight:900; color:#e0e6ed; line-height:1; }
        h2 { color:#1b4f72; font-size:22px; margin:10px 0 8px; }
        p  { color:#7f8c8d; font-size:14px; margin-bottom:28px; }
        .btn-home {
            background:linear-gradient(90deg,#1b4f72,#2980b9);
            color:white; border:none; border-radius:10px;
            padding:12px 28px; font-weight:600; text-decoration:none;
            display:inline-block; transition:all 0.2s;
        }
        .btn-home:hover { color:white; transform:translateY(-2px); box-shadow:0 6px 20px rgba(27,79,114,0.4); }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">404</div>
        <h2>Page Not Found</h2>
        <p>The page you are looking for doesn't exist or has been moved.</p>
        <a href="<?php echo BASE_URL; ?>" class="btn-home">
            <i class="bi bi-house me-2"></i>Go to Dashboard
        </a>
    </div>
</body>
</html>