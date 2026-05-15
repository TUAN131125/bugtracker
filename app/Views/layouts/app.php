<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['pageTitle'] ?? 'Dashboard') ?> - BugTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php require __DIR__ . '/../partials/_sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php require __DIR__ . '/../partials/_header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <?php 
                    if (isset($viewPath) && file_exists(__DIR__ . '/../' . $viewPath . '.php')) {
                        require __DIR__ . '/../' . $viewPath . '.php';
                    }
                ?>
            </div>
        </main>
    </div>

    <?php require __DIR__ . '/../partials/_toast-container.php'; ?>

    <script src="/assets/js/core/toast.js"></script>
    <script src="/assets/js/core/api.js"></script>
</body>
</html>