<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/navigation.php';

$database = new Database();
$db = $database->getConnection();

if ($_POST) {
    $action = $_POST['action'];
    $item_id = $_POST['item_id'];
    
    if ($action == 'approve') {
        $query = "UPDATE items SET status = 'approved' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
    } elseif ($action == 'reject') {
        $query = "DELETE FROM items WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
    } elseif ($action == 'mark_returned') {
        $query = "UPDATE items SET status = 'returned' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
    }
}

$query = "SELECT i.*, u.name as user_name FROM items i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.status = 'pending' 
          ORDER BY i.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT i.*, u.name as user_name FROM items i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.status = 'approved' 
          ORDER BY i.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$approved_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_items,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_items
    FROM items";
$stmt = $db->prepare($query);
$stmt->execute();
$admin_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    <?php renderNavigation('admin'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
        <h1 style="color: var(--primary-maroon); margin-bottom: 2rem;">Admin Panel</h1>
        
        <div class="stats" style="margin-bottom: 3rem;">
            <div class="stat-item">
                <span class="stat-number"><?php echo $admin_stats['total_items']; ?></span>
                <span class="stat-label">Total Items</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $admin_stats['pending_items']; ?></span>
                <span class="stat-label">Pending Review</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $admin_stats['approved_items']; ?></span>
                <span class="stat-label">Approved Items</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $admin_stats['returned_items']; ?></span>
                <span class="stat-label">Items Returned</span>
            </div>
        </div>

        <section style="margin-bottom: 4rem;">
            <h2 style="color: var(--primary-maroon); margin-bottom: 2rem;">Pending Items (<?php echo count($pending_items); ?>)</h2>
            
            <?php if (empty($pending_items)): ?>
                <div class="card" style="text-align: center; padding: 2rem;">
                    <p style="color: var(--gray-600);">No pending items to review.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-2">
                    <?php foreach ($pending_items as $item): ?>
                    <div class="item-card">
                        <?php if ($item['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image" class="item-image">
                        <?php else: ?>
                            <div class="item-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="item-content">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="item-meta">
                                <span class="status-badge status-<?php echo $item['type']; ?>">
                                    <?php echo ucfirst($item['type']); ?>
                                </span>
                                <span class="status-badge status-pending">Pending</span>
                            </div>
                            <p style="color: var(--gray-600); margin-bottom: 0.5rem;">
                                <strong>Posted by:</strong> <?php echo htmlspecialchars($item['user_name']); ?>
                            </p>
                            <p style="color: var(--gray-600); margin-bottom: 0.5rem;">
                                <strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?>
                            </p>
                            <p style="color: var(--gray-600); margin-bottom: 1rem;">
                                <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . '...'; ?>
                            </p>
                            <div class="btn-group" style="display: flex; gap: 0.5rem;">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-success" style="width: 100%;">Approve</button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Are you sure you want to reject this item?')">Reject</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <h2 style="color: var(--primary-maroon); margin-bottom: 2rem;">Approved Items (<?php echo count($approved_items); ?>)</h2>
            
            <?php if (empty($approved_items)): ?>
                <div class="card" style="text-align: center; padding: 2rem;">
                    <p style="color: var(--gray-600);">No approved items.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($approved_items as $item): ?>
                    <div class="item-card">
                        <?php if ($item['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image" class="item-image">
                        <?php else: ?>
                            <div class="item-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="item-content">
                            <h3 class="item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="item-meta">
                                <span class="status-badge status-<?php echo $item['type']; ?>">
                                    <?php echo ucfirst($item['type']); ?>
                                </span>
                                <span><?php echo date('M j', strtotime($item['date_lost_found'])); ?></span>
                            </div>
                            <p style="color: var(--gray-600); margin-bottom: 1rem; font-size: 0.9rem;">
                                By <?php echo htmlspecialchars($item['user_name']); ?>
                            </p>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_returned">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn btn-warning" style="width: 100%;" onclick="return confirm('Mark this item as returned?')">Mark as Returned</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CIT E-Lost & Found. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                location.reload();
            }, 30000);
        });
    </script>
</body>
</html>
