<?php
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user;
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $error = "Login error. Please try again.";
            error_log("Login Error: " . $e->getMessage());
        }
    } else {
        $error = "Please enter both email and password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Project Manager</title>

<style>
:root {
    --bg: #05070f;
    --panel: rgba(255,255,255,0.06);
    --border: rgba(255,255,255,0.08);
    --text: #e5e7eb;
    --muted: #9ca3af;
    --primary: #3b82f6;
    --cyan: #06b6d4;
    --red: #ef4444;
}

* { 
    box-sizing: border-box; 
    font-family: system-ui, Segoe UI, sans-serif; 
}

body {
    margin: 0;
    background: radial-gradient(circle at top, #0f172a, var(--bg));
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 440px;
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-header h1 {
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 10px 0;
    background: linear-gradient(135deg, var(--primary), var(--cyan));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-header p {
    color: var(--muted);
    margin: 0;
    font-size: 15px;
}

.login-box {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
}

.login-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--cyan));
}

.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--red);
    color: var(--red);
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: shake 0.4s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.form-group {
    margin-bottom: 24px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text);
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-size: 15px;
    transition: all 0.3s ease;
    outline: none;
}

input[type="email"]:focus,
input[type="password"]:focus {
    border-color: var(--primary);
    background: rgba(255,255,255,0.05);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

input[type="email"]::placeholder,
input[type="password"]::placeholder {
    color: var(--muted);
}

.btn {
    width: 100%;
    padding: 14px 20px;
    background: linear-gradient(135deg, var(--primary), var(--cyan));
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.btn:active {
    transform: translateY(0);
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.login-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid var(--border);
}

.login-footer p {
    color: var(--muted);
    font-size: 14px;
    margin: 0;
}

.demo-credentials {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 16px;
    margin-top: 24px;
    font-size: 13px;
}

.demo-credentials strong {
    color: var(--primary);
    display: block;
    margin-bottom: 8px;
}

.demo-credentials div {
    color: var(--muted);
    margin: 4px 0;
}

.icon {
    font-size: 20px;
    margin-right: 2px;
}

@media (max-width: 480px) {
    .login-box {
        padding: 30px 24px;
    }
    
    .login-header h1 {
        font-size: 28px;
    }
}
</style>
</head>

<body>

<div class="login-container">
    <div class="login-header">
        <h1>Project Manager</h1>
        <p>Sign in to your account to continue</p>
    </div>

    <div class="login-box">
        <?php if ($error): ?>
            <div class="error" role="alert">
                <span>⚠️</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">
                    <span class="icon">📧</span> Email Address
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your email"
                    required
                    autocomplete="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">
                    <span class="icon">🔒</span> Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="demo-credentials">
            <strong>💡 Demo Credentials</strong>
            <div><strong>Manager:</strong> manager@example.com / password</div>
            <div><strong>Employee:</strong> employee@example.com / password</div>
        </div>

        <div class="login-footer">
            <p>© <?= date('Y') ?> • Khalil Nafeti</p>
        </div>
    </div>
</div>

</body>
</html>