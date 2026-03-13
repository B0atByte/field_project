<?php
/**
 * ตรวจสอบสถานะ Session
 */
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ตรวจสอบ Session</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; } .error { color: red; } .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <h1>🔍 ตรวจสอบสถานะ Session</h1>

    <div class="card">
        <h2>Session Status</h2>
        <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
            <p class="success">✅ Session Active</p>
            <p class="info">Session ID: <?= session_id() ?></p>
        <?php else: ?>
            <p class="error">❌ Session Not Active</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>User Login Status</h2>
        <?php if (isset($_SESSION['user'])): ?>
            <p class="success">✅ User Logged In</p>
            <pre><?php print_r($_SESSION['user']); ?></pre>
        <?php else: ?>
            <p class="error">❌ User Not Logged In</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Session Data</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="card">
        <h2>Session Configuration</h2>
        <?php
        $config = [
            'session.save_path' => session_save_path(),
            'session.name' => session_name(),
            'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
            'session.cookie_path' => ini_get('session.cookie_path'),
            'session.cookie_domain' => ini_get('session.cookie_domain'),
            'session.cookie_secure' => ini_get('session.cookie_secure'),
            'session.cookie_httponly' => ini_get('session.cookie_httponly'),
        ];
        ?>
        <pre><?php print_r($config); ?></pre>
    </div>

    <div class="card">
        <h2>Actions</h2>
        <p>
            <a href="index.php" style="background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">← Login Page</a>
            <a href="dashboard/admin.php" style="background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">Admin Dashboard</a>
            <a href="admin/jobs.php" style="background: #f59e0b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-left: 10px;">Jobs Page</a>
        </p>
    </div>
</body>
</html>
```php
