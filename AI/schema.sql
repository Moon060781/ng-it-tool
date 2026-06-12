CREATE TABLE IF NOT EXISTS ai_vault_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    platform_name VARCHAR(100) NOT NULL,
    api_endpoint TEXT,
    model_target VARCHAR(100) NOT NULL,
    api_key TEXT NOT NULL,
    custom_notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
