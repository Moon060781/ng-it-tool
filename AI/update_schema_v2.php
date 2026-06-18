<?php
require_once(__DIR__ . "/../cred/config.php");

try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
        AI_DB_USER, AI_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "ALTER TABLE ai_vault_keys 
            ADD COLUMN auth_type VARCHAR(20) DEFAULT 'bearer' COMMENT 'bearer, api-key, query, basic',
            ADD COLUMN auth_header VARCHAR(50) DEFAULT 'Authorization' COMMENT 'e.g., x-api-key, x-goog-api-key',
            ADD COLUMN auth_prefix VARCHAR(50) DEFAULT 'Bearer ' COMMENT 'Prefix before key, e.g., \"Bearer \" or empty',
            ADD COLUMN system_message TEXT DEFAULT NULL COMMENT 'Optional system prompt',
            ADD COLUMN user_message_template TEXT DEFAULT '{\"role\":\"user\",\"content\":\"{{PROMPT}}\"}' COMMENT 'Template for each user message',
            ADD COLUMN extra_body_fields TEXT DEFAULT NULL COMMENT 'JSON object like {\"temperature\":0.7}',
            ADD COLUMN model_location VARCHAR(10) DEFAULT 'body' COMMENT 'Where to put model ID: body or query',
            ADD COLUMN model_key_name VARCHAR(20) DEFAULT 'model' COMMENT 'Key name for model in body, e.g., model, modelId';";

    $pdo->exec($sql);
    echo "Schema updated successfully to V2.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
