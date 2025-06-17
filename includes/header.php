<?php
// includes/header.php
// Pastikan session sudah dimulai sebelum memanggil header di halaman lain
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/db.php'; // Menggunakan __DIR__ untuk path yang absolut
include_once __DIR__ . '/functions.php';

// Cek apakah user sudah login, jika belum redirect ke halaman login
if (!isLoggedIn()) {
    redirectToLogin();
}

// Assign session variables with null coalescing operator for safety
$currentUsername = $_SESSION['username'] ?? 'Guest'; // Default ke 'Guest' jika tidak diatur
$currentRole = $_SESSION['role'] ?? 'warga';       // Default ke 'warga' jika tidak diatur
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sistem Informasi Qurban RT 001">
    <meta name="author" content="Your Name">

    <title>Sistem Qurban RT 001 - <?php echo htmlspecialchars(ucfirst($currentRole)); ?> Dashboard</title>

    <link href="/sistem_qurban/public/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="/sistem_qurban/public/css/sb-admin-2.min.css" rel="stylesheet">

    <link href="/sistem_qurban/public/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        /* Override beberapa gaya SB Admin 2 jika diperlukan */
        .sidebar-brand-text {
            white-space: normal;
            /* Allow brand text to wrap */
            font-size: 0.8rem;
            line-height: 1.2;
        }

        .sidebar-brand-icon {
            font-size: 1.5rem;
        }

        .sidebar-heading {
            color: #ccc;
            /* Lighter heading color */
        }

        .nav-item .nav-link {
            padding: 0.75rem 1rem;
            /* Adjust padding */
        }

        .logout-link-sidebar {
            background-color: #dc3545 !important;
            border-radius: 0.35rem;
            margin: 0 1rem;
            padding: 0.5rem 1rem !important;
            text-align: center;
        }

        .logout-link-sidebar:hover {
            background-color: #c82333 !important;
        }

        /* Adjust .container-fluid for content */
        .content-container-fluid {
            padding-left: 1rem;
            /* Add some padding on left */
            padding-right: 1rem;
            /* Add some padding on right */
        }

        /* Custom styles for messages */
        .message {
            padding: .75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: .35rem;
        }

        .message.success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .message.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .message.info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        /* Adjustments for login page (if applies) - only for login.php, register.php etc */
        body.bg-gradient-primary {
            background-color: #4e73df;
            /* Primary color */
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: cover;
        }

        .card.o-hidden.border-0.shadow-lg.my-5 {
            margin-top: 5rem !important;
            margin-bottom: 5rem !important;
        }

        /* Adjustments for the custom sidebar we built before */
        /* These styles might clash with SB Admin 2's native sidebar, consider removing or adapting */
        /* If you want to use SB Admin 2's native sidebar collapse, these can be simplified/removed */

        /* #sidebar, #content and #sidebarCollapse from previous custom implementation */
        /* #sidebar { display: none; } /* Or adjust to fit SB Admin 2's fixed sidebar */
        /* #content { margin-left: 0 !important; } */
        /* #sidebarCollapse { display: none !important; } */
    </style>
</head>

<body id="page-top">
    <div id="wrapper">

        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/sistem_qurban/public/dashboard.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-sheep"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Qurban RT <sup>001</sup></div>
            </a>

            <hr class="sidebar-divider my-0">

            <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard.php') !== false) ? 'active' : ''; ?>">
                <a class="nav-link" href="/sistem_qurban/public/dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Manajemen
            </div>

            <?php if (isAdmin()): ?>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/users.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/admin/users.php">
                        <i class="fas fa-fw fa-user-cog"></i>
                        <span>Manajemen User</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (isAdmin() || isPanitia()): ?>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/warga/index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/warga/add.php') !== false || strpos($_SERVER['REQUEST_URI'], '/warga/edit.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/warga/index.php">
                        <i class="fas fa-fw fa-users"></i>
                        <span>Data Warga</span>
                    </a>
                </li>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/qurban/index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/qurban/add_kambing.php') !== false || strpos($_SERVER['REQUEST_URI'], '/qurban/add_sapi.php') !== false || strpos($_SERVER['REQUEST_URI'], '/qurban/edit.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/qurban/index.php">
                        <i class="fas fa-fw fa-cut"></i>
                        <span>Data Qurban</span>
                    </a>
                </li>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/keuangan/index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/keuangan/add.php') !== false || strpos($_SERVER['REQUEST_URI'], '/keuangan/edit.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/keuangan/index.php">
                        <i class="fas fa-fw fa-coins"></i>
                        <span>Keuangan</span>
                    </a>
                </li>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/qurban/pembagian.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/qurban/pembagian.php">
                        <i class="fas fa-fw fa-boxes"></i>
                        <span>Pembagian Daging</span>
                    </a>
  </li>
                      <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/perlengkapan/index.php') !== false || strpos($_SERVER['REQUEST_URI'], '/perlengkapan/add.php') !== false || strpos($_SERVER['REQUEST_URI'], '/perlengkapan/edit.php') !== false) ? 'active' : ''; ?>">
                        <a class="nav-link" href="/sistem_qurban/public/perlengkapan/index.php">
                            <i class="fas fa-fw fa-tools"></i>
                            <span>Perlengkapan</span>
                        </a>
                    </li>
            <?php endif; ?>

            <?php if (isBerqurban()): ?>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/qurban/my_qurban.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/qurban/my_qurban.php">
                        <i class="fas fa-fw fa-hand-holding-heart"></i>
                        <span>Qurban Saya</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (isWarga() || isBerqurban() || isPanitia()): ?>
                <li class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/warga/qrcode.php') !== false) ? 'active' : ''; ?>">
                    <a class="nav-link" href="/sistem_qurban/public/warga/qrcode.php">
                        <i class="fas fa-fw fa-qrcode"></i>
                        <span>Kartu Qurban</span>
                    </a>
                </li>
            <?php endif; ?>

            <hr class="sidebar-divider d-none d-md-block">

            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

        </ul>
        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div class="d-none d-sm-inline-block mr-auto ml-md-3 my-2 my-md-0 mw-100 font-weight-bold text-gray-800">
                        Sistem Qurban RT 001
                    </div>

                    <ul class="navbar-nav ml-auto">

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($currentUsername); ?></span>
                                <img class="img-profile rounded-circle" src="/sistem_qurban/public/assets/profile.png">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="/sistem_qurban/public/auth.php?logout=true" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid content-container-fluid">
