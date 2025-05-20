<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Upload</title>
    
    <!-- tailwindcss -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- custom css -->
    <link rel="stylesheet" href="style.css">

</head>
 
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <header class="bg-green-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold"><?= APP_NAME ?></h1>
            
            <?php if (isLoggedIn()): ?>
                <div class="flex items-center">
                    <span class="mr-4">Halo, <?= $_SESSION['user_nama'] ?? 'User' ?></span>
                    <a href="<?= APP_URL ?>/logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-md">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php
    $flash = getFlashMessage();
    if ($flash): 
        $bgColor = $flash['type'] === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
    ?>
    <div class="container mx-auto mt-4">
        <div class="border-l-4 <?= $bgColor ?> p-4 mb-4">
            <p><?= $flash['message'] ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Content -->
    <main class="container mx-auto p-4">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-4 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</htmlL


