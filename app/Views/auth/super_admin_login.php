<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login - Rosmon SMS</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: #1f2937;
        }

        /* Soft radial glow in the background */
        body::before {
            content: '';
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(179,188,225,0.4) 0%, rgba(248,250,252,0) 70%);
            top: -200px;
            left: -200px;
            z-index: 0;
            border-radius: 50%;
        }

        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(19,25,143,0.08) 0%, rgba(248,250,252,0) 70%);
            bottom: -150px;
            right: -150px;
            z-index: 0;
            border-radius: 50%;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 20px;
            padding: 40px 32px 32px 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
            margin: 20px;
            margin-top: 60px; /* offset for logo */
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -85px;
            margin-bottom: 24px;
        }

        .logo-circle {
            width: 84px;
            height: 84px;
            background-color: #13198f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(19, 25, 143, 0.2);
            border: 4px solid #ffffff;
            color: #ffffff;
            font-size: 32px;
            margin-bottom: 12px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            color: #13198f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .brand-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
            text-align: center;
        }

        .iam-text {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #13198f;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Form Fields */
        .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            color: #1f2937;
            background: transparent;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #1d4ed8;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
        }

        .password-toggle:hover {
            color: #4b5563;
        }

        .forgot-password {
            display: block;
            text-align: right;
            font-size: 13px;
            color: #1d4ed8;
            font-weight: 500;
            text-decoration: none;
            margin-top: -6px;
            margin-bottom: 24px;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            height: 48px;
            background-color: #13198f;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s, box-shadow 0.2s;
        }

        .btn-login:hover {
            background-color: #0e1373;
            box-shadow: 0 4px 12px rgba(19, 25, 143, 0.2);
        }

        .btn-login:active {
            transform: translateY(1px);
        }

        .back-to-main {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
        }
        
        .back-to-main a {
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .back-to-main a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo-wrapper">
            <div class="logo-circle">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="brand-name">ROSMON SMS</div>
            <div class="brand-subtitle">Super Admin Access</div>
        </div>

        <div class="iam-text">
            <span>Secure Login Portal</span>
        </div>

        <form action="<?= $appBase ?? '' ?>/api/login" method="POST" id="loginForm">
            <!-- Hidden role fixed to super_admin -->
            <input type="hidden" name="role" value="super_admin">

            <div class="form-group">
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>

            <div class="form-group">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <i class="fa-regular fa-eye-slash password-toggle" id="togglePassword"></i>
            </div>

            <a href="#" class="forgot-password">Forgot Password?</a>

            <button type="submit" class="btn-login">Login as Admin</button>
        </form>

        <div class="back-to-main">
            <a href="<?= $appBase ?? '' ?>/"><i class="fa-solid fa-arrow-left"></i> Back to Main Login</a>
        </div>
    </div>

    <script>
        // Password Visibility Toggle
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
    </script>
</body>
</html>
