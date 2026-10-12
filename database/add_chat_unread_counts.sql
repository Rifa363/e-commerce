CREATE TABLE IF NOT EXISTS chat_read_status (
    conversation_id INT PRIMARY KEY,
    customer_last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    admin_last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
);
