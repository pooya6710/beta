-- ایجاد جدول آمار مدیریت
CREATE TABLE IF NOT EXISTS admin_statistics (
    id SERIAL PRIMARY KEY,
    statistic_date DATE NOT NULL DEFAULT CURRENT_DATE,
    total_users INTEGER DEFAULT 0,
    blocked_users INTEGER DEFAULT 0,
    active_users_today INTEGER DEFAULT 0,
    active_users_week INTEGER DEFAULT 0,
    active_users_month INTEGER DEFAULT 0,
    active_users_year INTEGER DEFAULT 0,
    total_games INTEGER DEFAULT 0,
    active_games INTEGER DEFAULT 0,
    games_today INTEGER DEFAULT 0,
    games_week INTEGER DEFAULT 0,
    games_month INTEGER DEFAULT 0,
    games_year INTEGER DEFAULT 0,
    new_users_today INTEGER DEFAULT 0,
    new_users_week INTEGER DEFAULT 0,
    new_users_month INTEGER DEFAULT 0,
    new_users_year INTEGER DEFAULT 0,
    total_delta_coins DECIMAL(10, 2) DEFAULT 0,
    avg_delta_coins DECIMAL(10, 2) DEFAULT 0,
    total_trophies INTEGER DEFAULT 0,
    avg_trophies DECIMAL(10, 2) DEFAULT 0,
    collusion_count_today INTEGER DEFAULT 0,
    spam_banned_count INTEGER DEFAULT 0,
    messages_exchanged_today INTEGER DEFAULT 0,
    friendships_added_today INTEGER DEFAULT 0,
    friend_game_requests_today INTEGER DEFAULT 0,
    referrals_today INTEGER DEFAULT 0,
    avg_moves_today DECIMAL(10, 2) DEFAULT 0,
    abandoned_games_today INTEGER DEFAULT 0,
    total_delta_coins_earned_today DECIMAL(10, 2) DEFAULT 0,
    total_delta_coins_lost_today DECIMAL(10, 2) DEFAULT 0,
    total_trophies_earned_today INTEGER DEFAULT 0,
    total_trophies_lost_today INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ایجاد جدول تنظیمات ادمین‌ها
CREATE TABLE IF NOT EXISTS admin_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    can_manage_users BOOLEAN DEFAULT FALSE,
    can_send_broadcasts BOOLEAN DEFAULT FALSE,
    can_manage_games BOOLEAN DEFAULT FALSE,
    can_view_statistics BOOLEAN DEFAULT FALSE,
    can_manage_admins BOOLEAN DEFAULT FALSE,
    can_manage_settings BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد جدول پیام‌های همگانی ارسال شده
CREATE TABLE IF NOT EXISTS broadcast_messages (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL,
    message_type VARCHAR(20) NOT NULL DEFAULT 'text',
    message_text TEXT,
    media_id VARCHAR(100),
    sent_count INTEGER DEFAULT 0,
    failed_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ایجاد اندکس‌ها برای بهبود کارایی
CREATE INDEX IF NOT EXISTS idx_admin_statistics_date ON admin_statistics(statistic_date);
CREATE INDEX IF NOT EXISTS idx_admin_permissions_user_id ON admin_permissions(user_id);
CREATE INDEX IF NOT EXISTS idx_broadcast_messages_admin_id ON broadcast_messages(admin_id);