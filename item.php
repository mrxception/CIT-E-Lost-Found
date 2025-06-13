<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: search.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT i.*, u.name as user_name, u.email as user_email FROM items i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.id = ? AND i.status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: search.php");
    exit();
}

$message_sent = false;
if ($_POST && isset($_SESSION['user_id'])) {
    $content = $_POST['message'];
    $query = "INSERT INTO messages (sender_id, receiver_id, item_id, content) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$_SESSION['user_id'], $item['user_id'], $item['id'], $content])) {
        $message_sent = true;
    }
}

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['name']); ?> - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .unread-badge {
            background-color: var(--primary-blue);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .contact-info-section {
            background-color: var(--light-maroon);
            border: 2px solid var(--primary-maroon);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .contact-info-header {
            color: var(--primary-maroon);
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .contact-info-content {
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .contact-info-note {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-style: italic;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">CIT Lost & Found</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="search.php">Search Items</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="report.php">Report Item</a></li>
                    <li><a href="messages.php">Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo $unread_count; ?></span><?php endif; ?></a></li>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li><a href="admin.php">Admin Panel</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
        <div style="margin-bottom: 2rem;">
            <a href="search.php" style="color: var(--primary-blue); text-decoration: none;">← Back to Search</a>
        </div>

        <div class="grid grid-2" style="gap: 3rem;">
            <div class="fade-in">
                <?php if ($item['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image" style="width: 100%; border-radius: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div style="width: 100%; height: 300px; background-color: var(--gray-100); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: var(--gray-600); font-size: 1.2rem;">
                        No Image Available
                    </div>
                <?php endif; ?>
            </div>

            <div class="card slide-in">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <h1 style="color: var(--primary-blue); margin: 0;"><?php echo htmlspecialchars($item['name']); ?></h1>
                    <span class="status-badge status-<?php echo $item['type']; ?>">
                        <?php echo ucfirst($item['type']); ?>
                    </span>
                </div>

                <div style="margin-bottom: 2rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <strong style="color: var(--gray-800);">Category:</strong>
                            <p style="margin: 0.25rem 0; color: var(--gray-600);"><?php echo htmlspecialchars($item['category']); ?></p>
                        </div>
                        <div>
                            <strong style="color: var(--gray-800);">Date:</strong>
                            <p style="margin: 0.25rem 0; color: var(--gray-600);"><?php echo date('F j, Y', strtotime($item['date_lost_found'])); ?></p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--gray-800);">Location:</strong>
                        <p style="margin: 0.25rem 0; color: var(--gray-600);"><?php echo htmlspecialchars($item['location']); ?></p>
                    </div>
                    
                    <div>
                        <strong style="color: var(--gray-800);">Description:</strong>
                        <p style="margin: 0.25rem 0; color: var(--gray-600); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    </div>
                </div>

                
                <?php if (!empty($item['contact_info'])): ?>
                <div class="contact-info-section">
                    <div class="contact-info-header">
                        📞 Contact Information
                    </div>
                    <div class="contact-info-content">
                        <?php echo htmlspecialchars($item['contact_info']); ?>
                    </div>
                    <div class="contact-info-note">
                        You can contact the poster directly using the information above.
                    </div>
                </div>
                <?php endif; ?>

                <div style="border-top: 1px solid var(--gray-200); padding-top: 1.5rem;">
                    <p style="color: var(--gray-600); margin-bottom: 1rem;">
                        Posted by <strong><?php echo htmlspecialchars($item['user_name']); ?></strong> on <?php echo date('F j, Y', strtotime($item['created_at'])); ?>
                    </p>

                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $item['user_id']): ?>
                        <?php if ($message_sent): ?>
                            <div class="alert alert-success">Message sent successfully! You can view your messages in the <a href="messages.php" style="color: var(--primary-blue);">Messages</a> section.</div>
                        <?php endif; ?>
                        
                        <form method="POST" style="margin-top: 1rem;">
                            <div class="form-group">
                                <label for="message" class="form-label">Contact the owner:</label>
                                <textarea id="message" name="message" class="form-textarea" required placeholder="Hi, I think this might be my item..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-warning">
                            <a href="login.php" style="color: var(--primary-blue);">Login</a> to contact the owner of this item.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CIT E-Lost & Found. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
