-- ============================================================
-- BugTracker – Database Schema v1.0.0
-- ============================================================
-- Tham chiếu: TDD Backend v1.0.0 – Phần 2.2
-- Charset:    utf8mb4 (hỗ trợ tiếng Việt + emoji reaction)
-- Engine:     InnoDB (hỗ trợ Foreign Key + Transaction)
-- Collation:  utf8mb4_unicode_ci
--
-- HƯỚNG DẪN IMPORT:
--   InfinityFree Control Panel → MySQL Databases → phpMyAdmin
--   → Import file này. Chạy toàn bộ, không chia nhỏ.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';


-- ============================================================
-- PHẦN 1: AUTHENTICATION & USER
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: users
-- Lưu thông tin tài khoản cá nhân. Tách biệt với Workspace.
-- Một Account có thể thuộc nhiều Workspace (multi-tenant).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(150)        NOT NULL                    COMMENT 'Họ tên đầy đủ',
    `email`                 VARCHAR(255)        NOT NULL                    COMMENT 'Email đăng nhập — UNIQUE',
    `password`              VARCHAR(255)        NOT NULL                    COMMENT 'bcrypt hash, cost=12. KHÔNG lưu plain text',
    `avatar_path`           VARCHAR(500)        NULL     DEFAULT NULL       COMMENT 'Đường dẫn tương đối file avatar trong /storage/',
    `is_verified`           TINYINT(1)          NOT NULL DEFAULT 0          COMMENT '0=chưa xác minh email, 1=đã xác minh',
    `onboarding_completed`  TINYINT(1)          NOT NULL DEFAULT 0          COMMENT '1=đã hoàn thành onboarding (chọn/tạo Workspace)',
    `email_notif_settings`  JSON                NULL     DEFAULT NULL       COMMENT 'Cài đặt bật/tắt từng loại email notification',
    `created_at`            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            TIMESTAMP           NULL     DEFAULT NULL       COMMENT 'Soft delete — NULL = đang hoạt động',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tài khoản người dùng — tách biệt với Workspace (multi-tenant)';


-- ------------------------------------------------------------
-- Bảng: email_verifications
-- Token xác minh email sau đăng ký. Single-use, TTL 24 giờ.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED     NOT NULL,
    `token`         VARCHAR(64)         NOT NULL COMMENT 'bin2hex(random_bytes(32)) — CSPRNG',
    `expires_at`    TIMESTAMP           NOT NULL COMMENT 'NOW() + 24 giờ',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_verifications_token` (`token`),
    KEY `idx_email_verifications_user_id` (`user_id`),
    KEY `idx_email_verifications_expires_at` (`expires_at`),
    CONSTRAINT `fk_email_verifications_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Token xác minh email — single-use, xóa sau khi dùng';


-- ------------------------------------------------------------
-- Bảng: password_resets
-- Token đặt lại mật khẩu. Single-use, TTL 1 giờ.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED     NOT NULL,
    `token`         VARCHAR(64)         NOT NULL COMMENT 'bin2hex(random_bytes(32))',
    `expires_at`    TIMESTAMP           NOT NULL COMMENT 'NOW() + 1 giờ',
    `used_at`       TIMESTAMP           NULL     DEFAULT NULL COMMENT 'NULL=chưa dùng; ghi timestamp khi dùng xong',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_token` (`token`),
    KEY `idx_password_resets_user_id` (`user_id`),
    CONSTRAINT `fk_password_resets_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Token reset mật khẩu — single-use, TTL 1 giờ';


-- ------------------------------------------------------------
-- Bảng: user_tokens
-- Remember Me token. Lưu hash của cookie, không lưu raw token.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_tokens` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED     NOT NULL,
    `token_hash`    VARCHAR(64)         NOT NULL COMMENT 'Hash của raw token trong cookie — KHÔNG lưu raw',
    `expires_at`    TIMESTAMP           NOT NULL COMMENT 'NOW() + 30 ngày',
    `ip_address`    VARCHAR(45)         NULL     DEFAULT NULL COMMENT 'IPv4 hoặc IPv6',
    `user_agent`    VARCHAR(500)        NULL     DEFAULT NULL COMMENT 'Browser fingerprint',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_tokens_token_hash` (`token_hash`),
    KEY `idx_user_tokens_user_id` (`user_id`),
    KEY `idx_user_tokens_expires_at` (`expires_at`),
    CONSTRAINT `fk_user_tokens_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Remember Me token — lưu hash, không lưu raw token';


-- ------------------------------------------------------------
-- Bảng: login_attempts
-- Rate limiting đăng nhập — 5 lần sai/15 phút → lock IP.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `ip_address`        VARCHAR(45)         NOT NULL COMMENT 'IP đăng nhập sai',
    `email_attempted`   VARCHAR(255)        NULL     DEFAULT NULL COMMENT 'Email được thử — nullable để tránh lộ thông tin',
    `attempted_at`      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
    COMMENT 'Composite index cho query: đếm lần sai của IP trong 15 phút'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rate limiting đăng nhập theo IP';


-- ============================================================
-- PHẦN 2: WORKSPACE & MULTI-TENANT
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: workspaces
-- Không gian làm việc độc lập. Mọi dữ liệu nghiệp vụ
-- đều gắn với workspace_id để đảm bảo data isolation.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workspaces` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(150)        NOT NULL                    COMMENT 'Tên hiển thị',
    `slug`          VARCHAR(150)        NOT NULL                    COMMENT 'URL-friendly — VD: cong-ty-abc',
    `description`   TEXT                NULL     DEFAULT NULL,
    `avatar_path`   VARCHAR(500)        NULL     DEFAULT NULL,
    `owner_user_id` BIGINT UNSIGNED     NOT NULL                    COMMENT 'Owner hiện tại — chỉ 1 người tại mỗi thời điểm',
    `settings`      JSON                NULL     DEFAULT NULL       COMMENT 'Cài đặt: timezone, password policy...',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP           NULL     DEFAULT NULL       COMMENT 'Soft delete',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_workspaces_slug` (`slug`),
    KEY `idx_workspaces_owner` (`owner_user_id`),
    KEY `idx_workspaces_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_workspaces_owner_user_id`
        FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Workspace — đơn vị tổ chức cấp cao nhất (multi-tenant)';


-- ------------------------------------------------------------
-- Bảng: workspace_members
-- Quan hệ nhiều-nhiều: User ↔ Workspace, kèm Role.
-- UNIQUE(workspace_id, user_id): 1 user chỉ có 1 role/workspace.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workspace_members` (
    `id`            BIGINT UNSIGNED                                 NOT NULL AUTO_INCREMENT,
    `workspace_id`  BIGINT UNSIGNED                                 NOT NULL,
    `user_id`       BIGINT UNSIGNED                                 NOT NULL,
    `role`          ENUM('owner','admin','member','guest')          NOT NULL DEFAULT 'member',
    `joined_at`     TIMESTAMP                                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `invited_by`    BIGINT UNSIGNED                                 NULL     DEFAULT NULL COMMENT 'Ai đã mời — NULL nếu là Owner tự tạo',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_workspace_members_ws_user` (`workspace_id`, `user_id`)
        COMMENT 'Mỗi user chỉ có 1 role trong 1 workspace',
    KEY `idx_workspace_members_user_id` (`user_id`),
    KEY `idx_workspace_members_role` (`role`),
    CONSTRAINT `fk_wm_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_wm_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_wm_invited_by`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Thành viên workspace kèm role — WorkspaceMiddleware query bảng này mỗi request';


-- ------------------------------------------------------------
-- Bảng: workspace_invitations
-- Lời mời tham gia workspace. Token 64 chars, TTL 7 ngày.
-- is_pre_registered: email chưa có account khi được mời.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workspace_invitations` (
    `id`                BIGINT UNSIGNED                             NOT NULL AUTO_INCREMENT,
    `workspace_id`      BIGINT UNSIGNED                             NOT NULL,
    `email`             VARCHAR(255)                                NOT NULL COMMENT 'Email người được mời',
    `role`              ENUM('admin','member','guest')              NOT NULL DEFAULT 'member' COMMENT 'Owner không thể bị mời — chỉ chuyển giao',
    `token`             VARCHAR(64)                                 NOT NULL COMMENT 'bin2hex(random_bytes(32))',
    `invited_by`        BIGINT UNSIGNED                             NOT NULL,
    `status`            ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
    `is_pre_registered` TINYINT(1)                                  NOT NULL DEFAULT 0 COMMENT '1=email chưa có account khi mời',
    `expires_at`        TIMESTAMP                                   NOT NULL COMMENT 'NOW() + 7 ngày',
    `used_at`           TIMESTAMP                                   NULL     DEFAULT NULL,
    `created_at`        TIMESTAMP                                   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_workspace_invitations_token` (`token`),
    KEY `idx_wi_email_ws_status` (`email`, `workspace_id`, `status`)
        COMMENT 'TDD Phần 2.3: kiểm tra invitation pending khi mời lại',
    KEY `idx_wi_expires_at` (`expires_at`),
    KEY `idx_wi_workspace_id` (`workspace_id`),
    CONSTRAINT `fk_wi_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_wi_invited_by`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lời mời workspace — 4 kịch bản xử lý theo TDD Phần 1.5';


-- ============================================================
-- PHẦN 3: PROJECT & MILESTONE
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: projects
-- Mỗi workspace có nhiều project. Project Key (VD: BT)
-- là prefix cho Issue ID (BT-001). last_issue_number tăng
-- trong transaction để tránh race condition.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
    `id`                BIGINT UNSIGNED                 NOT NULL AUTO_INCREMENT,
    `workspace_id`      BIGINT UNSIGNED                 NOT NULL,
    `name`              VARCHAR(150)                    NOT NULL,
    `description`       TEXT                            NULL     DEFAULT NULL,
    `key`               VARCHAR(6)                      NOT NULL COMMENT '2-6 ký tự in hoa A-Z — VD: BT, SHOP — UNIQUE per workspace',
    `color`             VARCHAR(7)                      NOT NULL DEFAULT '#2563EB' COMMENT 'Hex color — xem ViewLayer Guide Phần 2.2',
    `status`            ENUM('active','archived')       NOT NULL DEFAULT 'active',
    `last_issue_number` INT UNSIGNED                    NOT NULL DEFAULT 0 COMMENT 'Bộ đếm Issue per-project. Tăng trong transaction (TDD Phần 2.2.3)',
    `created_by`        BIGINT UNSIGNED                 NOT NULL,
    `created_at`        TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP                       NULL     DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_projects_ws_key` (`workspace_id`, `key`)
        COMMENT 'Project Key unique PER workspace, không phải global',
    KEY `idx_projects_workspace_status` (`workspace_id`, `status`),
    KEY `idx_projects_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_projects_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_projects_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Project trong workspace — Key là prefix Issue ID (BT-001)';


-- ------------------------------------------------------------
-- Bảng: project_members
-- Thành viên được phép truy cập project cụ thể.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_members` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `project_id`    BIGINT UNSIGNED     NOT NULL,
    `user_id`       BIGINT UNSIGNED     NOT NULL,
    `added_at`      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_project_members_proj_user` (`project_id`, `user_id`),
    KEY `idx_project_members_user_id` (`user_id`),
    CONSTRAINT `fk_pm_project_id`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pm_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Bảng: milestones
-- Giai đoạn/phiên bản trong project. Issue có thể gán vào milestone.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `milestones` (
    `id`            BIGINT UNSIGNED             NOT NULL AUTO_INCREMENT,
    `project_id`    BIGINT UNSIGNED             NOT NULL,
    `workspace_id`  BIGINT UNSIGNED             NOT NULL COMMENT 'Denormalized — tránh JOIN khi filter isolation',
    `name`          VARCHAR(150)                NOT NULL,
    `description`   TEXT                        NULL     DEFAULT NULL,
    `start_date`    DATE                        NULL     DEFAULT NULL,
    `due_date`      DATE                        NULL     DEFAULT NULL,
    `status`        ENUM('open','closed')       NOT NULL DEFAULT 'open',
    `created_at`    TIMESTAMP                   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP                   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_milestones_project_id` (`project_id`),
    KEY `idx_milestones_workspace_id` (`workspace_id`),
    CONSTRAINT `fk_milestones_project_id`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_milestones_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Milestone/Version trong project';


-- ============================================================
-- PHẦN 4: ISSUE (CORE)
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: issues
-- Bảng trung tâm của hệ thống. Mọi query PHẢI có
-- WHERE workspace_id = ? để đảm bảo data isolation.
-- issue_key = {project.key}-{issue_number} (VD: BT-001)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `issues` (
    `id`                    BIGINT UNSIGNED                                                                     NOT NULL AUTO_INCREMENT,
    `workspace_id`          BIGINT UNSIGNED                                                                     NOT NULL COMMENT 'Data isolation — bắt buộc trong mọi query',
    `project_id`            BIGINT UNSIGNED                                                                     NOT NULL,
    `issue_number`          INT UNSIGNED                                                                        NOT NULL COMMENT 'Số thứ tự per-project (1,2,3...) — tăng trong transaction',
    `issue_key`             VARCHAR(20)                                                                         NOT NULL COMMENT 'VD: BT-001 — computed từ project.key + issue_number',
    `title`                 VARCHAR(500)                                                                        NOT NULL,
    `description`           LONGTEXT                                                                            NULL     DEFAULT NULL COMMENT 'Markdown raw text — sanitize khi render',
    `type`                  ENUM('bug','task','enhancement','question')                                         NOT NULL DEFAULT 'bug',
    `status`                ENUM('open','in_triage','in_progress','resolved','closed','reopened','wont_fix','duplicate') NOT NULL DEFAULT 'open',
    `severity`              ENUM('critical','major','minor','trivial')                                          NOT NULL DEFAULT 'major',
    `priority`              ENUM('urgent','high','medium','low')                                                NOT NULL DEFAULT 'medium',
    `reporter_id`           BIGINT UNSIGNED                                                                     NOT NULL,
    `assignee_id`           BIGINT UNSIGNED                                                                     NULL     DEFAULT NULL,
    `milestone_id`          BIGINT UNSIGNED                                                                     NULL     DEFAULT NULL,
    `duplicate_of_issue_id` BIGINT UNSIGNED                                                                     NULL     DEFAULT NULL COMMENT 'Self-ref — dùng khi status=duplicate',
    `status_changed_by`     BIGINT UNSIGNED                                                                     NULL     DEFAULT NULL COMMENT 'User đổi status lần cuối',
    `resolved_at`           TIMESTAMP                                                                           NULL     DEFAULT NULL COMMENT 'Ghi khi status→closed. Dùng tính TTR (Time To Resolve)',
    `created_at`            TIMESTAMP                                                                           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP                                                                           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            TIMESTAMP                                                                           NULL     DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_issues_project_number` (`project_id`, `issue_number`)
        COMMENT 'Đảm bảo không có 2 Issue cùng số trong 1 project',
    KEY `idx_issues_key` (`issue_key`),
    KEY `idx_issues_ws_status` (`workspace_id`, `status`)
        COMMENT 'TDD Phần 2.3: query phổ biến nhất — filter theo ws + status',
    KEY `idx_issues_ws_assignee` (`workspace_id`, `assignee_id`)
        COMMENT 'Dashboard: Issue của tôi',
    KEY `idx_issues_project_status` (`project_id`, `status`),
    KEY `idx_issues_reporter` (`reporter_id`),
    KEY `idx_issues_created_at` (`created_at`),
    KEY `idx_issues_updated_at` (`updated_at`),
    KEY `idx_issues_deleted_at` (`deleted_at`),
    FULLTEXT KEY `ft_issues_title` (`title`)
        COMMENT 'Global search theo từ khóa — TDD Phần 2.3',
    CONSTRAINT `fk_issues_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_project_id`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_reporter_id`
        FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_assignee_id`
        FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_milestone_id`
        FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_duplicate_of`
        FOREIGN KEY (`duplicate_of_issue_id`) REFERENCES `issues` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_issues_status_changed_by`
        FOREIGN KEY (`status_changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Issue — bảng trung tâm. Mọi query phải có WHERE workspace_id';


-- ------------------------------------------------------------
-- Bảng: tags
-- Label màu sắc do Admin tạo cho workspace.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `workspace_id`  BIGINT UNSIGNED     NOT NULL,
    `name`          VARCHAR(50)         NOT NULL,
    `color`         VARCHAR(7)          NOT NULL DEFAULT '#5D6D7E' COMMENT 'Hex color cho badge',
    `description`   VARCHAR(255)        NULL     DEFAULT NULL,
    `created_by`    BIGINT UNSIGNED     NOT NULL,
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_ws_name` (`workspace_id`, `name`)
        COMMENT 'Tag name unique per workspace',
    KEY `idx_tags_workspace_id` (`workspace_id`),
    CONSTRAINT `fk_tags_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tags_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Bảng: issue_tags
-- Quan hệ nhiều-nhiều: Issue ↔ Tag.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `issue_tags` (
    `issue_id`      BIGINT UNSIGNED     NOT NULL,
    `tag_id`        BIGINT UNSIGNED     NOT NULL,

    PRIMARY KEY (`issue_id`, `tag_id`),
    KEY `idx_issue_tags_tag_id` (`tag_id`),
    CONSTRAINT `fk_it_issue_id`
        FOREIGN KEY (`issue_id`) REFERENCES `issues` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_it_tag_id`
        FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Bảng: issue_links
-- Liên kết giữa 2 Issue. Lưu CẢ 2 CHIỀU để query đơn giản.
-- VD: A blocks B → (A→B, blocks) + (B→A, is_blocked_by)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `issue_links` (
    `id`                BIGINT UNSIGNED                                                                     NOT NULL AUTO_INCREMENT,
    `source_issue_id`   BIGINT UNSIGNED                                                                     NOT NULL,
    `target_issue_id`   BIGINT UNSIGNED                                                                     NOT NULL,
    `link_type`         ENUM('blocks','is_blocked_by','duplicates','relates_to','is_parent_of','is_child_of') NOT NULL,
    `created_by`        BIGINT UNSIGNED                                                                     NOT NULL,
    `created_at`        TIMESTAMP                                                                           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_issue_links` (`source_issue_id`, `target_issue_id`, `link_type`),
    KEY `idx_issue_links_source` (`source_issue_id`),
    KEY `idx_issue_links_target` (`target_issue_id`),
    CONSTRAINT `fk_il_source_issue_id`
        FOREIGN KEY (`source_issue_id`) REFERENCES `issues` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_il_target_issue_id`
        FOREIGN KEY (`target_issue_id`) REFERENCES `issues` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_il_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Issue links — lưu 2 chiều để tránh UNION query (TDD Phần 4 UC-033)';


-- ============================================================
-- PHẦN 5: COMMENTS & REACTIONS
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: comments
-- Thread bình luận của Issue. Hỗ trợ 1 cấp reply (parent_comment_id).
-- Soft delete: giữ lại placeholder "bình luận đã bị xóa".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id`                BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `issue_id`          BIGINT UNSIGNED     NOT NULL,
    `workspace_id`      BIGINT UNSIGNED     NOT NULL COMMENT 'Denormalized — data isolation filter',
    `user_id`           BIGINT UNSIGNED     NOT NULL,
    `parent_comment_id` BIGINT UNSIGNED     NULL     DEFAULT NULL COMMENT 'Self-ref — NULL = top-level comment',
    `content`           TEXT                NOT NULL COMMENT 'Raw markdown — sanitize bằng htmlspecialchars khi render',
    `is_edited`         TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '1=đã chỉnh sửa ít nhất 1 lần',
    `edited_at`         TIMESTAMP           NULL     DEFAULT NULL,
    `created_at`        TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP           NULL     DEFAULT NULL COMMENT 'Soft delete — giữ placeholder trong thread',

    PRIMARY KEY (`id`),
    KEY `idx_comments_issue_created` (`issue_id`, `created_at`)
        COMMENT 'TDD Phần 2.3: load comments của issue, sort theo thời gian',
    KEY `idx_comments_workspace_id` (`workspace_id`),
    KEY `idx_comments_user_id` (`user_id`),
    KEY `idx_comments_parent_id` (`parent_comment_id`),
    CONSTRAINT `fk_comments_issue_id`
        FOREIGN KEY (`issue_id`) REFERENCES `issues` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_parent_id`
        FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comment thread — 1 cấp reply, soft delete';


-- ------------------------------------------------------------
-- Bảng: comment_reactions
-- Emoji reaction cho comment. 1 user chỉ react 1 emoji/comment.
-- Toggle: click lần 2 → xóa reaction (UNIQUE constraint).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comment_reactions` (
    `id`            BIGINT UNSIGNED                                     NOT NULL AUTO_INCREMENT,
    `comment_id`    BIGINT UNSIGNED                                     NOT NULL,
    `user_id`       BIGINT UNSIGNED                                     NOT NULL,
    `emoji`         ENUM('thumbs_up','check','fire','question')         NOT NULL,
    `created_at`    TIMESTAMP                                           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_comment_reactions_user_emoji` (`comment_id`, `user_id`, `emoji`)
        COMMENT '1 user chỉ react 1 emoji/comment — toggle bằng INSERT/DELETE',
    KEY `idx_comment_reactions_comment_id` (`comment_id`),
    CONSTRAINT `fk_cr_comment_id`
        FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cr_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PHẦN 6: ATTACHMENTS
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: attachments
-- File đính kèm cho Issue hoặc Comment.
-- File lưu NGOÀI public_html tại /storage/attachments/{ws_id}/{issue_id}/
-- Serve qua PHP script trung gian (TDD Phần 3.2 + D1-027).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attachments` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `workspace_id`  BIGINT UNSIGNED     NOT NULL COMMENT 'Data isolation',
    `issue_id`      BIGINT UNSIGNED     NULL     DEFAULT NULL COMMENT 'NULL nếu gắn vào comment',
    `comment_id`    BIGINT UNSIGNED     NULL     DEFAULT NULL COMMENT 'NULL nếu gắn vào issue',
    `uploader_id`   BIGINT UNSIGNED     NOT NULL,
    `original_name` VARCHAR(255)        NOT NULL COMMENT 'Tên file gốc người dùng upload',
    `stored_name`   VARCHAR(255)        NOT NULL COMMENT 'Tên file sau rename (UUID + ext) — tránh path traversal',
    `file_path`     VARCHAR(500)        NOT NULL COMMENT 'Đường dẫn tương đối trong /storage/',
    `mime_type`     VARCHAR(100)        NOT NULL COMMENT 'Validate bằng finfo_file() — KHÔNG tin extension',
    `file_size`     INT UNSIGNED        NOT NULL COMMENT 'Bytes — max 2MB (SRS Phần 3.3.2)',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP           NULL     DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_attachments_workspace_id` (`workspace_id`),
    KEY `idx_attachments_issue_id` (`issue_id`),
    KEY `idx_attachments_comment_id` (`comment_id`),
    CONSTRAINT `fk_att_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_att_issue_id`
        FOREIGN KEY (`issue_id`) REFERENCES `issues` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_att_comment_id`
        FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_att_uploader_id`
        FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='File đính kèm — serve qua PHP script, không direct URL';


-- ============================================================
-- PHẦN 7: NOTIFICATIONS & ACTIVITY
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: notifications
-- In-app notification. JS poll /api/notifications/count mỗi 60s.
-- Index (user_id, is_read) tối ưu cho query đếm chưa đọc.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED     NOT NULL COMMENT 'Người nhận notification',
    `workspace_id`  BIGINT UNSIGNED     NOT NULL,
    `type`          VARCHAR(50)         NOT NULL COMMENT 'VD: issue_assigned, mentioned, status_changed',
    `entity_type`   VARCHAR(30)         NOT NULL COMMENT 'VD: issue, comment, workspace',
    `entity_id`     BIGINT UNSIGNED     NOT NULL COMMENT 'ID của entity liên quan',
    `message`       VARCHAR(500)        NOT NULL COMMENT 'Text thông báo pre-rendered',
    `url`           VARCHAR(500)        NULL     DEFAULT NULL COMMENT 'Deep link đến trang liên quan',
    `is_read`       TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_read` (`user_id`, `is_read`)
        COMMENT 'TDD Phần 2.3: query đếm notif chưa đọc — gọi mỗi 60s',
    KEY `idx_notifications_workspace_id` (`workspace_id`),
    KEY `idx_notifications_created_at` (`created_at`),
    CONSTRAINT `fk_notif_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notif_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='In-app notification — JS polling 60s (InfinityFree không có WebSocket)';


-- ------------------------------------------------------------
-- Bảng: activity_logs
-- Nhật ký hành động nghiệp vụ (KHÁC system_logs).
-- Ghi mọi thao tác quan trọng: tạo issue, đổi status, comment...
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `workspace_id`  BIGINT UNSIGNED     NOT NULL COMMENT 'Bắt buộc cho mọi query log',
    `user_id`       BIGINT UNSIGNED     NULL     DEFAULT NULL COMMENT 'NULL = system action',
    `action_type`   VARCHAR(50)         NOT NULL COMMENT 'VD: issue_created, status_changed, member_invited',
    `entity_type`   VARCHAR(30)         NOT NULL COMMENT 'VD: issue, project, workspace, comment',
    `entity_id`     BIGINT UNSIGNED     NOT NULL COMMENT 'ID của entity bị tác động',
    `metadata`      JSON                NULL     DEFAULT NULL COMMENT 'Chi tiết: {"from":"open","to":"closed","issue_key":"BT-012"}',
    `ip_address`    VARCHAR(45)         NULL     DEFAULT NULL,
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_ws_created` (`workspace_id`, `created_at`)
        COMMENT 'TDD Phần 2.3: query log workspace mới nhất',
    KEY `idx_activity_logs_entity` (`entity_type`, `entity_id`)
        COMMENT 'TDD Phần 2.3: query log của 1 issue/project cụ thể',
    KEY `idx_activity_logs_user_id` (`user_id`),
    CONSTRAINT `fk_al_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_al_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Activity log nghiệp vụ — khác system_logs (kỹ thuật)';


-- ============================================================
-- PHẦN 8: BẢNG PHỤ TRỢ
-- ============================================================

-- ------------------------------------------------------------
-- Bảng: saved_filters
-- Bộ lọc Issue đã lưu của user. Lưu dạng JSON.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_filters` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED     NOT NULL,
    `workspace_id`  BIGINT UNSIGNED     NOT NULL,
    `name`          VARCHAR(100)        NOT NULL COMMENT 'Tên bộ lọc do user đặt',
    `filter_config` JSON                NOT NULL COMMENT 'Toàn bộ tham số filter dạng JSON',
    `created_at`    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_saved_filters_user_ws` (`user_id`, `workspace_id`),
    CONSTRAINT `fk_sf_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sf_workspace_id`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Bảng: email_queue
-- Hàng đợi email thất bại. Thay thế Cronjob bằng manual retry
-- từ trang Admin (TDD Phần 1.3 — Lớp 3).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`            BIGINT UNSIGNED                         NOT NULL AUTO_INCREMENT,
    `to_email`      VARCHAR(255)                            NOT NULL,
    `to_name`       VARCHAR(150)                            NULL     DEFAULT NULL,
    `subject`       VARCHAR(255)                            NOT NULL,
    `body_html`     LONGTEXT                                NOT NULL COMMENT 'Nội dung email đã render — đủ để retry không cần lookup thêm',
    `status`        ENUM('pending','sent','failed')         NOT NULL DEFAULT 'pending',
    `attempts`      TINYINT UNSIGNED                        NOT NULL DEFAULT 0 COMMENT 'Số lần đã thử gửi',
    `last_error`    TEXT                                    NULL     DEFAULT NULL COMMENT 'Error message từ PHPMailer exception',
    `created_at`    TIMESTAMP                               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`       TIMESTAMP                               NULL     DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_email_queue_status` (`status`),
    KEY `idx_email_queue_to_email` (`to_email`),
    KEY `idx_email_queue_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Email queue — manual retry thay Cronjob (InfinityFree)';


-- ------------------------------------------------------------
-- Bảng: system_logs
-- Log kỹ thuật (lỗi, warning). KHÁC activity_logs (nghiệp vụ).
-- Admin xem qua /admin/system-logs. Dọn dẹp thủ công.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id`            BIGINT UNSIGNED                                         NOT NULL AUTO_INCREMENT,
    `level`         ENUM('DEBUG','INFO','WARNING','ERROR','CRITICAL')       NOT NULL DEFAULT 'INFO',
    `context`       VARCHAR(100)                                            NULL     DEFAULT NULL COMMENT 'VD: EmailService, AuthController',
    `message`       TEXT                                                    NOT NULL,
    `trace`         TEXT                                                    NULL     DEFAULT NULL COMMENT 'Stack trace rút gọn — max 2000 chars (TDD Phần 4.2)',
    `created_at`    TIMESTAMP                                               NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_system_logs_level_created` (`level`, `created_at`)
        COMMENT 'Filter theo level + thời gian trong Admin panel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System log kỹ thuật — Admin xem qua /admin/system-logs';


-- ============================================================
-- KHÔI PHỤC FOREIGN KEY CHECK
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- KIỂM TRA SAU IMPORT
-- Chạy query này để verify tất cả 20 bảng đã được tạo:
-- SELECT TABLE_NAME FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
-- ORDER BY TABLE_NAME;
-- ============================================================