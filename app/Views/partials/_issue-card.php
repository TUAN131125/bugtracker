<?php
/**
 * @var array $issue Dữ liệu tóm tắt của một Issue
 */
?>

<div class="card issue-card mb-2 shadow-sm border-left-<?= Sanitizer::escape($issue['priority'] ?? 'medium') ?>">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="issue-title-wrapper text-truncate pr-3">
                <a href="/issues/<?= Sanitizer::escape($issue['issue_key']) ?>" class="font-weight-bold text-dark text-decoration-none">
                    <span class="text-primary mr-1">[<?= Sanitizer::escape($issue['issue_key']) ?>]</span>
                    <span title="<?= Sanitizer::escape($issue['title']) ?>"><?= Sanitizer::escape($issue['title']) ?></span>
                </a>
            </div>
            
            <span class="badge status-<?= Sanitizer::escape($issue['status']) ?> text-uppercase text-small" style="white-space: nowrap;">
                <?= Sanitizer::escape(str_replace('_', ' ', $issue['status'])) ?>
            </span>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="issue-meta text-small text-muted d-flex align-items-center">
                <span class="badge badge-light border mr-2" title="Loại: <?= Sanitizer::escape(ucfirst($issue['type'])) ?>">
                    <?= Sanitizer::escape(ucfirst($issue['type'])) ?>
                </span>
                
                <?php if (($issue['attachment_count'] ?? 0) > 0): ?>
                    <span class="mr-2" title="Có tệp đính kèm">📎 <?= Sanitizer::escape($issue['attachment_count']) ?></span>
                <?php endif; ?>
                
                <?php if (($issue['comment_count'] ?? 0) > 0): ?>
                    <span