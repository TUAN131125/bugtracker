<?php
/**
 * @var int $current_page Trang hiện tại
 * @var int $total_pages  Tổng số trang
 * @var string $base_url  URL gốc (đã bao gồm các query string lọc, ngoại trừ 'page')
 */

// Chỉ hiển thị phân trang nếu có nhiều hơn 1 trang
if ($total_pages > 1): 
?>
<nav aria-label="Điều hướng trang" class="d-flex justify-content-center mt-4">
    <ul class="pagination">
        <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $base_url ?>&page=<?= $current_page - 1 ?>" <?= ($current_page <= 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                &laquo; Trước
            </a>
        </li>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)): ?>
                <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $base_url ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
        <?php endfor; ?>

        <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $base_url ?>&page=<?= $current_page + 1 ?>" <?= ($current_page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                Sau &raquo;
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>