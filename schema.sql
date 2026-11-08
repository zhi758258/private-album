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
-- 好友关系与私信
-- ============================================================

CREATE TABLE IF NOT EXISTS friend_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_friend_requests_pair (from_user_id, to_user_id),
    KEY idx_friend_requests_to (to_user_id),
    CONSTRAINT fk_friend_requests_from FOREIGN KEY (from_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_friend_requests_to FOREIGN KEY (to_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS friends (
    user_id INT UNSIGNED NOT NULL,
    friend_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, friend_id),
    KEY idx_friends_friend (friend_id),
    CONSTRAINT fk_friends_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_friends_friend FOREIGN KEY (friend_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS private_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_private_messages_pair (sender_id, receiver_id),
    KEY idx_private_messages_receiver (receiver_id, is_read),
    CONSTRAINT fk_private_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_private_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 消息提醒（留言、回复、好友请求、好友私信）
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    type VARCHAR(20) NOT NULL,
    ref_id INT UNSIGNED NULL,
    content VARCHAR(255) NOT NULL DEFAULT '',
    link VARCHAR(255) NOT NULL DEFAULT '',
    is_read TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read),
    KEY idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 用户空间：心情说说 + 日志（默认好友可见）
-- ============================================================

CREATE TABLE IF NOT EXISTS shuoshuo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(20) NOT NULL DEFAULT '',
    visibility VARCHAR(12) NOT NULL DEFAULT 'friends',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_shuoshuo_user (user_id),
    CONSTRAINT fk_shuoshuo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diaries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    content TEXT NOT NULL,
    visibility VARCHAR(12) NOT NULL DEFAULT 'friends',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_diaries_user (user_id),
    CONSTRAINT fk_diaries_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 斗地主
-- ============================================================

CREATE TABLE IF NOT EXISTS ddz_rooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(12) NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'waiting',
    state MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ddz_rooms_code (code),
    KEY idx_ddz_rooms_owner (owner_id),
    CONSTRAINT fk_ddz_rooms_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ddz_games (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    room_code VARCHAR(12) NOT NULL,
    delta INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ddz_games_user (user_id),
    CONSTRAINT fk_ddz_games_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
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
    ('nav_space', '空间'),
    ('nav_friends', '好友'),
    ('nav_album', '我的相册'),
    ('nav_memo', '备忘录'),
    ('nav_ddz', '斗地主'),
    ('nav_settings', '设置'),
    ('nav_admin', '用户管理'),
    ('nav_admin_cards', '卡密管理'),
    ('nav_admin_photos', '全部相册'),
    ('nav_admin_memos', '全部备忘录'),
    ('nav_files', '文件管理'),
    ('nav_admin_settings', '后台设置');
