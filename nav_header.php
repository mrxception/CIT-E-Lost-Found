<?php

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
}
?>

<header class="header">
    <nav class="nav">
        <a href="index.php" class="logo">CIT E-Lost & Found</a>
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
