<?php
function getOrCreateChatConversation(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        'INSERT INTO chat_conversations (user_id) VALUES (:user_id)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmt->execute([':user_id' => $userId]);
    return (int)$db->lastInsertId();
}

function saveChatMessage(PDO $db, int $conversationId, int $senderId, string $senderRole, string $message, ?string $attachmentPath = null): int
{
    $stmt = $db->prepare(
        'INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message, attachment_path)
         VALUES (:conversation_id, :sender_id, :sender_role, :message, :attachment_path)'
    );
    $stmt->execute([
        ':conversation_id' => $conversationId,
        ':sender_id' => $senderId,
        ':sender_role' => $senderRole,
        ':message' => $message,
        ':attachment_path' => $attachmentPath
    ]);
    $messageId = (int)$db->lastInsertId();
    $db->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([':id' => $conversationId]);
    return $messageId;
}
