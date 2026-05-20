<?php
/**
 * @var string $view_content Nội dung HTML lõi của view chức năng
 */
?>
<div class="app-layout">
    <aside class="app-sidebar-wrapper">
        <?php include __DIR__ . '/../partials/_sidebar.php'; ?>
    </aside>

    <div class="app-main-container">
        <header class="app-header-wrapper">
            <?php include __DIR__ . '/../partials/_header.php'; ?>
        </header>

        <main class="app-content-body">
            <div class="container-fluid">
                <?= $view_content ?>
            </div>
        </main>
    </div>
</div>