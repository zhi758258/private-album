-- ============================================================
-- 私藏（private-album）MySQL 数据库结构
-- 要求：MySQL 5.7+ / MariaDB 10.2+，InnoDB，utf8mb4
-- 幂等：可重复执行（IF NOT EXISTS / INSERT IGNORE）
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(32) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(40) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'user',
    avatar VARCHAR(100) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    filename VARCHAR(100) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    caption VARCHAR(240) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    on_wall TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_photos_user (user_id),
    KEY idx_photos_wall (on_wall),
    CONSTRAINT fk_photos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_messages_user (user_id),
    KEY idx_messages_parent (parent_id),
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_parent FOREIGN KEY (parent_id) REFERENCES messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(80) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_memos_user (user_id),
    CONSTRAINT fk_memos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(64) NOT NULL,
    `value` TEXT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_likes (
    photo_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (photo_id, user_id),
    KEY idx_likes_user (user_id),
    CONSTRAINT fk_likes_photo FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    used_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cards_code (code),
    KEY idx_cards_used_by (used_by),
    CONSTRAINT fk_cards_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cards_user FOREIGN KEY (used_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 默认设置（INSERT IGNORE：已存在则跳过，不覆盖现有值）
-- ============================================================

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('site_name', '私藏'),
    ('notice_title', ''),
    ('notice_content', ''),
    ('notice_updated', ''),
    ('notice_enabled', '0'),
    ('notice_once', '0'),
    ('register_open', '0'),
    ('register_auto_card', '0');

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('nav_home', '首页'),
    ('nav_board', '留言板'),
    ('nav_album', '我的相册'),
    ('nav_memo', '备忘录'),
    ('nav_settings', '设置'),
    ('nav_admin', '用户管理'),
    ('nav_admin_cards', '卡密管理'),
    ('nav_admin_photos', '全部相册'),
    ('nav_admin_memos', '全部备忘录'),
    ('nav_files', '文件管理'),
    ('nav_admin_settings', '后台设置');
