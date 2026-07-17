-- V2 migration for ai_vault_keys — adds columns the V2.3 code already expects.
-- Run one ALTER at a time in phpMyAdmin. If a column already exists, that
-- single line will error "Duplicate column" — safe to ignore and move on.

ALTER TABLE ai_vault_keys ADD COLUMN auth_type VARCHAR(20) NOT NULL DEFAULT 'bearer' AFTER model_target;
ALTER TABLE ai_vault_keys ADD COLUMN auth_header VARCHAR(100) NOT NULL DEFAULT 'Authorization' AFTER auth_type;
ALTER TABLE ai_vault_keys ADD COLUMN auth_prefix VARCHAR(50) NOT NULL DEFAULT 'Bearer ' AFTER auth_header;
ALTER TABLE ai_vault_keys ADD COLUMN system_message TEXT NULL AFTER auth_prefix;
ALTER TABLE ai_vault_keys ADD COLUMN user_message_template TEXT NULL AFTER system_message;
ALTER TABLE ai_vault_keys ADD COLUMN extra_body_fields TEXT NULL AFTER user_message_template;
ALTER TABLE ai_vault_keys ADD COLUMN model_location VARCHAR(10) NOT NULL DEFAULT 'body' AFTER extra_body_fields;
ALTER TABLE ai_vault_keys ADD COLUMN model_key_name VARCHAR(50) NOT NULL DEFAULT 'model' AFTER model_location;
ALTER TABLE ai_vault_keys ADD COLUMN request_method VARCHAR(10) NOT NULL DEFAULT 'POST' AFTER model_key_name;
ALTER TABLE ai_vault_keys ADD COLUMN response_path VARCHAR(255) NULL AFTER request_method;

-- Backfill sensible V2 defaults for existing rows saved under the old V1 schema,
-- so old keys don't silently break once index.php reads these new columns.
UPDATE ai_vault_keys SET
  auth_type = 'bearer', auth_header = 'Authorization', auth_prefix = 'Bearer ',
  user_message_template = '{"role":"user","content":"{{PROMPT}}"}',
  model_location = 'body', model_key_name = 'model', request_method = 'POST',
  response_path = 'choices.0.message.content'
WHERE auth_type IS NULL OR auth_type = '';
