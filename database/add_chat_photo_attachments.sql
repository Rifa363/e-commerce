ALTER TABLE chat_messages
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message;
