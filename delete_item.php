<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

if ($_POST && isset($_POST['item_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    
    $query = "SELECT * FROM items WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_POST['item_id'], $_SESSION['user_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        
        $query = "DELETE FROM messages WHERE item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item['id']]);
        
        
        if ($item['image_path'] && file_exists($item['image_path'])) {
            unlink($item['image_path']);
        }
        
        
        $query = "DELETE FROM items WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item['id'], $_SESSION['user_id']]);
        
        $_SESSION['delete_success'] = "Item deleted successfully.";
    }
}

header("Location: dashboard.php");
exit();
?>
