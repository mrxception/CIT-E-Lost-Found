<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/navigation.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

if ($_POST && isset($_POST['reply_content'])) {
    $reply_content = trim($_POST['reply_content']);
    $item_id = (int)$_POST['item_id'];
    $receiver_id = (int)$_POST['receiver_id'];
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (!empty($reply_content) && $item_id > 0 && $receiver_id > 0) {
        try {
            $query = "INSERT INTO messages (sender_id, receiver_id, item_id, parent_id, content, timestamp, is_read) 
                     VALUES (?, ?, ?, ?, ?, NOW(), 0)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$_SESSION['user_id'], $receiver_id, $item_id, $parent_id, $reply_content])) {
                $success_message = "Reply sent successfully!";
            } else {
                $error_message = "Failed to send reply. Please try again.";
            }
        } catch (Exception $e) {
            $error_message = "Error sending reply: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

if (isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    $mark_read_query = "UPDATE messages SET is_read = 1 
                       WHERE receiver_id = ? AND item_id = ? AND is_read = 0";
    $mark_read_stmt = $db->prepare($mark_read_query);
    $mark_read_stmt->execute([$_SESSION['user_id'], $item_id]);
}

$conversations_query = "SELECT 
    m.item_id,
    i.name as item_name,
    i.type as item_type,
    COUNT(m.id) as message_count,
    MAX(m.timestamp) as last_message_time,
    SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM messages m
    JOIN items i ON m.item_id = i.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY m.item_id, i.name, i.type
    ORDER BY last_message_time DESC";

$conversations_stmt = $db->prepare($conversations_query);
$conversations_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$conversations = $conversations_stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$messages = [];
$item_details = null;

if ($selected_item_id) {
    $item_query = "SELECT i.*, u.name as owner_name 
                  FROM items i 
                  JOIN users u ON i.user_id = u.id 
                  WHERE i.id = ?";
    $item_stmt = $db->prepare($item_query);
    $item_stmt->execute([$selected_item_id]);
    $item_details = $item_stmt->fetch(PDO::FETCH_ASSOC);
    
    $messages_query = "SELECT 
        m.*,
        sender.name as sender_name,
        receiver.name as receiver_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE m.item_id = ?
        ORDER BY m.timestamp ASC";
    
    $messages_stmt = $db->prepare($messages_query);
    $messages_stmt->execute([$selected_item_id]);
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    <?php renderNavigation('messages'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
        <h1 style="color: var(--primary-maroon); margin-bottom: 2rem;">Messages</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="conversations-container">
            <div class="conversation-list">
                <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">Conversations</h3>
                
                <?php if (empty($conversations)): ?>
                    <div class="card" style="text-align: center; padding: 2rem;">
                        <p style="color: var(--gray-600);">No conversations yet.</p>
                        <p style="color: var(--gray-600); font-size: 0.9rem;">Start by messaging someone about an item!</p>
                        <a href="search.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Items</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-item <?php echo ($selected_item_id == $conversation['item_id']) ? 'active' : ''; ?>">
                            <a href="messages.php?item_id=<?php echo $conversation['item_id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                                <div class="conversation-header">
                                    <span style="font-weight: 600; color: var(--primary-maroon);">
                                        <?php echo htmlspecialchars($conversation['item_name']); ?>
                                    </span>
                                    <?php if ($conversation['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-meta">
                                    <span class="status-badge status-<?php echo $conversation['item_type']; ?>">
                                        <?php echo ucfirst($conversation['item_type']); ?>
                                    </span>
                                    <span><?php echo $conversation['message_count']; ?> message<?php echo $conversation['message_count'] != 1 ? 's' : ''; ?></span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.5rem;">
                                    Last activity: <?php echo date('M j, Y g:i A', strtotime($conversation['last_message_time'])); ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="conversation-messages">
                <?php if ($selected_item_id && $item_details): ?>
                    <div class="card" style="margin-bottom: 2rem; background-color: var(--light-maroon);">
                        <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">
                            <?php echo htmlspecialchars($item_details['name']); ?>
                        </h3>
                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                            <span class="status-badge status-<?php echo $item_details['type']; ?>">
                                <?php echo ucfirst($item_details['type']); ?>
                            </span>
                            <span style="color: var(--gray-600);">
                                Posted by: <strong><?php echo htmlspecialchars($item_details['owner_name']); ?></strong>
                            </span>
                        </div>
                        <a href="item.php?id=<?php echo $item_details['id']; ?>" class="btn btn-secondary btn-sm">
                            View Item Details
                        </a>
                    </div>

                    <div class="message-list">
                        <?php if (empty($messages)): ?>
                            <div class="card" style="text-align: center; padding: 2rem;">
                                <p style="color: var(--gray-600);">No messages in this conversation yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'outgoing' : 'incoming'; ?>">
                                    <div class="message-header">
                                        <strong style="color: var(--primary-maroon);">
                                            <?php echo htmlspecialchars($message['sender_name']); ?>
                                        </strong>
                                        <span class="message-meta">
                                            <?php echo date('M j, Y g:i A', strtotime($message['timestamp'])); ?>
                                        </span>
                                    </div>
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $reply_to_id = null;
                    if (!empty($messages)) {
                        $last_message = end($messages);
                        $reply_to_id = ($last_message['sender_id'] == $_SESSION['user_id']) ? $last_message['receiver_id'] : $last_message['sender_id'];
                    } else {
                        $reply_to_id = $item_details['user_id'];
                    }
                    ?>
                    
                    <?php if ($reply_to_id && $reply_to_id != $_SESSION['user_id']): ?>
                        <div class="reply-form">
                            <h4 style="color: var(--primary-maroon); margin-bottom: 1rem;">Send Reply</h4>
                            <form method="POST">
                                <input type="hidden" name="item_id" value="<?php echo $selected_item_id; ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $reply_to_id; ?>">
                                <?php if (!empty($messages)): ?>
                                    <input type="hidden" name="parent_id" value="<?php echo end($messages)['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <textarea name="reply_content" class="reply-textarea" required 
                                              placeholder="Type your message here..."></textarea>
                                </div>
                                
                                <div class="reply-actions">
                                    <button type="submit" class="btn btn-primary">Send Reply</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="conversation-empty">
                        <h3 style="color: var(--gray-600); margin-bottom: 1rem;">Select a Conversation</h3>
                        <p style="color: var(--gray-600);">Choose a conversation from the list to view and reply to messages.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CIT E-Lost & Found. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messageList = document.querySelector('.message-list');
            if (messageList) {
                messageList.scrollTop = messageList.scrollHeight;
            }
            
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });
    </script>
</body>
</html>
