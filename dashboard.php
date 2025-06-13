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

$query = "SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_query = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN type = 'lost' THEN 1 ELSE 0 END) as lost_items,
    SUM(CASE WHEN type = 'found' THEN 1 ELSE 0 END) as found_items,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_items,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_items
    FROM items WHERE user_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$success_message = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == 'success') {
    $success_message = 'Item deleted successfully!';
}
if (isset($_GET['updated']) && $_GET['updated'] == 'success') {
    $success_message = 'Item updated successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    <?php renderNavigation('dashboard'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
        <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="color: var(--primary-maroon); margin: 0;">Dashboard</h1>
            <a href="report.php" class="btn btn-primary">Report New Item</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success" style="margin-bottom: 2rem;">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        
        <div class="grid grid-4" style="gap: 1.5rem; margin-bottom: 3rem;">
            <div class="stat-card fade-in">
                <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-number"><?php echo $stats['lost_items']; ?></div>
                <div class="stat-label">Lost Items</div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-number"><?php echo $stats['found_items']; ?></div>
                <div class="stat-label">Found Items</div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-number"><?php echo $stats['returned_items']; ?></div>
                <div class="stat-label">Items Returned</div>
            </div>
        </div>

        
        <div class="card">
            <h2 style="color: var(--primary-maroon); margin-bottom: 1.5rem;">Your Items</h2>
            
            <?php if (empty($user_items)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <h3 style="color: var(--gray-600); margin-bottom: 1rem;">No items reported yet</h3>
                    <p style="color: var(--gray-600); margin-bottom: 2rem;">Start by reporting a lost or found item.</p>
                    <a href="report.php" class="btn btn-primary">Report Your First Item</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_items as $item): ?>
                                <tr class="fade-in">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <?php if ($item['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item" style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.5rem;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background-color: var(--gray-200); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: var(--gray-600);">No Image</div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                <br>
                                                <small style="color: var(--gray-600);"><?php echo htmlspecialchars($item['location']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $item['type']; ?>">
                                            <?php echo ucfirst($item['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $item['status']; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <?php if ($item['status'] == 'approved'): ?>
                                                <a href="item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <?php endif; ?>
                                            <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CIT E-Lost & Found. All rights reserved.</p>
        </div>
    </footer>

    <script>
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>
