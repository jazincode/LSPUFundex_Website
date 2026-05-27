<?php
// ============================================
// LSPUFundex - Modern Login Page
// File: login.php
// ============================================

require_once 'config/app.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {

    if (isAdmin()) {

        redirect(BASE_URL . 'admin/dashboard.php');

    } elseif (isCouncil()) {

        redirect(BASE_URL . 'council/dashboard.php');

    } else {

        redirect(BASE_URL . 'officer/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {

        $error = 'Please enter both username and password.';

    } else {

        $stmt = $conn->prepare("
            SELECT 
                id,
                full_name,
                username,
                password,
                role,
                is_active,
                section_id,
                department_id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$user) {

            $error = 'Invalid username or password.';

        } elseif ($user['is_active'] != 1) {

            $error = 'Your account has been deactivated.';

        } elseif (!password_verify($password, $user['password'])) {

            $error = 'Invalid username or password.';

        } else {

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['section_id']    = $user['section_id'] ?? null;
            $_SESSION['department_id'] = $user['department_id'] ?? null;

            $upd = $conn->prepare("
                UPDATE users
                SET last_login = NOW()
                WHERE id = ?
            ");

            $upd->bind_param("i", $user['id']);
            $upd->execute();
            $upd->close();

            logAction(
                $conn,
                $user['id'],
                'LOGIN',
                'Authentication',
                $user['full_name'] . ' logged in successfully.'
            );

            if ($user['role'] === 'admin') {

                redirect(BASE_URL . 'admin/dashboard.php');

            } elseif ($user['role'] === 'council') {

                redirect(BASE_URL . 'council/dashboard.php');

            } else {

                redirect(BASE_URL . 'officer/dashboard.php');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login | LSPUFundex</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{

            min-height:100vh;

            display:flex;
            justify-content:center;
            align-items:center;

            padding:20px;

            font-family:'Inter', sans-serif;

            background:

                linear-gradient(
                    135deg,
                    rgba(0, 32, 63, 0.82),
                    rgba(20, 74, 116, 0.72),
                    rgba(44, 137, 200, 0.58),
                    rgba(89, 168, 166, 0.45)
                ),

                url('assets/images/campus bg.png')
                no-repeat center center / cover;

            overflow:hidden;
            position:relative;
        }

        /* BACKGROUND GLOW EFFECTS */
        body::before,
        body::after{

            content:'';
            position:absolute;
            border-radius:50%;
            filter:blur(90px);
            z-index:0;
        }

        body::before{

            width:320px;
            height:320px;

            background:
                rgba(44, 137, 200, 0.35);

            top:-80px;
            left:-80px;
        }

        body::after{

            width:260px;
            height:260px;

            background:
                rgba(89, 168, 166, 0.28);

            bottom:-70px;
            right:-70px;
        }

        /* LOGIN CONTAINER */
        .login-container{

            width:100%;
            max-width:420px;

            position:relative;
            z-index:2;
        }

        /* LOGIN CARD */
        .login-card{

            background:
                rgba(255,255,255,0.92);

            backdrop-filter:blur(14px);

            border-radius:28px;

            padding:35px 32px;

            box-shadow:
                0 20px 50px rgba(0,0,0,0.28);

            border:
                1px solid rgba(255,255,255,0.25);
        }

        /* LOGO */
        .logo-wrapper{

            display:flex;
            justify-content:center;

            margin-bottom:18px;
        }

        .logo-box{

            width:92px;
            height:92px;

            border-radius:50%;

            background:white;

            display:flex;
            justify-content:center;
            align-items:center;

            overflow:hidden;

            box-shadow:
                0 8px 24px rgba(0,0,0,0.15);
        }

        .logo-box img{

            width:78px;
            height:78px;

            object-fit:contain;
        }

        /* TITLE */
        .welcome-title{

            text-align:center;

            font-size:34px;
            font-weight:800;

            color:#003B73;

            margin-bottom:4px;
        }

        .welcome-subtitle{

            text-align:center;

            color:#64748b;

            font-size:14px;

            margin-bottom:28px;
        }

        /* LABEL */
        .form-label{

            font-size:14px;
            font-weight:600;

            color:#1e293b;

            margin-bottom:8px;
        }

        /* INPUT GROUP */
        .input-group{

            border:1px solid #dbe2ea;

            border-radius:14px;

            overflow:hidden;

            background:white;

            margin-bottom:18px;
        }

        .input-group-text{

            width:54px;

            border:none;

            background:#f8fafc;

            color:#003B73;

            display:flex;
            align-items:center;
            justify-content:center;

            font-size:17px;
        }

        .form-control{

            border:none !important;

            box-shadow:none !important;

            height:54px;

            font-size:14px;
        }

        .form-control::placeholder{

            color:#9ca3af;
        }

        /* PASSWORD TOGGLE */
        .toggle-password{

            width:55px;

            border:none;

            background:#f8fafc;

            color:#64748b;

            cursor:pointer;

            transition:0.2s;
        }

        .toggle-password:hover{

            color:#003B73;
        }

        /* LOGIN BUTTON */
        .btn-login{

            width:100%;
            height:54px;

            border:none;

            border-radius:14px;

            background:
                linear-gradient(
                    90deg,
                    #144A74,
                    #2C89C8
                );

            color:white;

            font-size:16px;
            font-weight:700;

            transition:0.25s;

            margin-top:8px;

            box-shadow:
                0 10px 24px rgba(44, 137, 200, 0.35);
        }

        .btn-login:hover{

            transform:translateY(-2px);

            opacity:0.96;
        }

        /* ALERT */
        .alert-custom{

            border-radius:12px;

            font-size:13px;

            margin-bottom:18px;
        }

        /* BACK LINK */
        .back-link{

            text-align:center;

            margin-top:18px;
        }

        .back-link a{

            color:white;

            text-decoration:none;

            font-size:14px;
            font-weight:500;
        }

        .back-link a:hover{

            text-decoration:underline;
        }

        /* MOBILE */
        @media(max-width:480px){

            .login-card{

                padding:30px 24px;
            }

            .welcome-title{

                font-size:30px;
            }

            .logo-box{

                width:80px;
                height:80px;
            }

            .logo-box img{

                width:66px;
                height:66px;
            }
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <!-- LOGO -->
        <div class="logo-wrapper">

            <div class="logo-box">

                <?php
                    $logoPath = 'assets/images/logo.png';
                ?>

                <?php if(file_exists($logoPath)): ?>

                    <img
                        src="<?php echo BASE_URL; ?>assets/images/logo.png"
                        alt="LSPU Logo"
                    >

                <?php else: ?>

                    <img
                        src="https://via.placeholder.com/80x80.png?text=LOGO"
                        alt="Placeholder Logo"
                    >

                <?php endif; ?>

            </div>

        </div>

        <!-- TITLE -->
        <div class="welcome-title">
            Welcome back!
        </div>

        <div class="welcome-subtitle">
            Sign in to your account
        </div>

        <!-- ERROR -->
        <?php if(!empty($error)): ?>

            <div class="alert alert-danger alert-custom">

                <i class="bi bi-exclamation-circle me-1"></i>

                <?php echo clean($error); ?>

            </div>

        <?php endif; ?>

        <!-- FORM -->
        <form method="POST"
              action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo csrf_token(); ?>"
            >

            <!-- USERNAME -->
            <label class="form-label">
                Username
            </label>

            <div class="input-group">

                <span class="input-group-text">

                    <i class="bi bi-person"></i>

                </span>

                <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Enter your username"
                    value="<?php echo clean($_POST['username'] ?? ''); ?>"
                    autocomplete="username"
                    required
                >

            </div>

            <!-- PASSWORD -->
            <label class="form-label">
                Password
            </label>

            <div class="input-group">

                <span class="input-group-text">

                    <i class="bi bi-lock"></i>

                </span>

                <input
                    type="password"
                    name="password"
                    id="passwordField"
                    class="form-control"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword()"
                >

                    <i class="bi bi-eye"
                       id="eyeIcon"></i>

                </button>

            </div>

            <!-- BUTTON -->
            <button type="submit"
                    class="btn-login">

                <i class="bi bi-box-arrow-in-right me-2"></i>

                Sign In

            </button>

        </form>

    </div>

    <!-- BACK LINK -->
    <div class="back-link">

        <a href="<?php echo BASE_URL; ?>public/dashboard.php">

            <i class="bi bi-arrow-left"></i>

            Back to Public Dashboard

        </a>

    </div>

</div>

<script>

    function togglePassword(){

        const passwordField =
            document.getElementById('passwordField');

        const eyeIcon =
            document.getElementById('eyeIcon');

        if(passwordField.type === 'password'){

            passwordField.type = 'text';

            eyeIcon.className =
                'bi bi-eye-slash';

        }else{

            passwordField.type = 'password';

            eyeIcon.className =
                'bi bi-eye';
        }
    }

</script>

</body>
</html>