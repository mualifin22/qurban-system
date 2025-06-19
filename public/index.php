<?php
include '../includes/db.php';
include '../includes/functions.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, username, password, role, nik_warga FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nik_warga'] = $user['nik_warga'];

            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Username atau password salah.";
        }
    } else {
        $error_message = "Username atau password salah.";
    }
    $_SESSION['error_message'] = $error_message;
    header("Location: index.php"); 
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sistem Informasi Qurban RT 001">
    <meta name="author" content="Your Name">

    <title>Login - Sistem Qurban RT 001</title>

    <link href="/sistem_qurban/public/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="/sistem_qurban/public/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        html, body {
            height: 100%; 
        }
        
        body.bg-gradient-primary {
            background-color: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: contain;

            display: flex;
            align-items: center;     
            justify-content: center; 
        }

        .bg-login-image-custom {
            background: url('/sistem_qurban/public/assets/qurban_bg.jpg');
            background-position: center;
            background-size: contain;
        }

    </style>
    </head>

<body class="bg-gradient-primary">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-xl-9 col-lg-10 col-md-11">

                <div class="card o-hidden border-0 shadow-lg">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block bg-login-image-custom"></div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="h4 text-gray-900 mb-4">Selamat Datang!</h1>
                                        <p class="text-muted mb-4">Sistem Informasi Manajemen Qurban</p>
                                    </div>

                                    <form class="user" action="index.php" method="POST">
                                        <?php if (!empty($error_message)): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?php echo htmlspecialchars($error_message); ?>
                                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <input type="text" class="form-control form-control-user"
                                                id="username" name="username"
                                                placeholder="Masukkan Username..." required>
                                        </div>
                                        <div class="form-group">
                                            <input type="password" class="form-control form-control-user"
                                                id="password" name="password" placeholder="Password" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-user btn-block">
                                            Login
                                        </button>
                                        <hr>
                                    </form>
                                    
                                    <div class="text-center">
                                        <a class="small" href="#">Lupa Password? (Hubungi Admin)</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script src="/sistem_qurban/public/vendor/jquery/jquery.min.js"></script>
    <script src="/sistem_qurban/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="/sistem_qurban/public/vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="/sistem_qurban/public/js/sb-admin-2.min.js"></script>

</body>

</html>