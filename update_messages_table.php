<?php

function getUnreadMessageCount($user_id) {
    if (!$user_id) return 0;
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['unread'] : 0;
}


function renderNavigation($current_page = '') {
    $unread_count = 0;
    if (isset($_SESSION['user_id'])) {
        $unread_count = getUnreadMessageCount($_SESSION['user_id']);
    }
    ?>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">CIT Lost & Found</a>
            
            
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            
            
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php" <?php echo $current_page == 'home' ? 'class="active"' : ''; ?>>Home</a></li>
                <li><a href="search.php" <?php echo $current_page == 'search' ? 'class="active"' : ''; ?>>Search</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="dashboard.php" <?php echo $current_page == 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                    <li><a href="report.php" <?php echo $current_page == 'report' ? 'class="active"' : ''; ?>>Report</a></li>
                    <li><a href="messages.php" <?php echo $current_page == 'messages' ? 'class="active"' : ''; ?>>Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo $unread_count; ?></span><?php endif; ?></a></li>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li><a href="admin.php" <?php echo $current_page == 'admin' ? 'class="active"' : ''; ?>>Admin</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" <?php echo $current_page == 'login' ? 'class="active"' : ''; ?>>Login</a></li>
                    <li><a href="register.php" <?php echo $current_page == 'register' ? 'class="active"' : ''; ?>>Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <script>
    function toggleMobileMenu() {
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.querySelector('.mobile-menu-toggle');
        
        navLinks.classList.toggle('mobile-menu-open');
        menuToggle.classList.toggle('active');
    }

    
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.nav-links a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                const navLinksContainer = document.getElementById('navLinks');
                const menuToggle = document.querySelector('.mobile-menu-toggle');
                navLinksContainer.classList.remove('mobile-menu-open');
                menuToggle.classList.remove('active');
            });
        });

        
        document.addEventListener('click', function(event) {
            const nav = document.querySelector('.nav');
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            
            if (!nav.contains(event.target)) {
                navLinks.classList.remove('mobile-menu-open');
                menuToggle.classList.remove('active');
            }
        });
    });
    </script>
    <?php
}
?>
