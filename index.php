<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN type = 'lost' THEN 1 ELSE 0 END) as lost_items,
    SUM(CASE WHEN type = 'found' THEN 1 ELSE 0 END) as found_items,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_items
    FROM items WHERE status IN ('approved', 'returned')";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT i.*, u.name as user_name FROM items i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.status = 'approved' 
          ORDER BY i.created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $result ? $result['unread'] : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIT E-Lost & Found - Digital Lost and Found Portal</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

    
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">CIT E-Lost & Found</a>
        
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        
            
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php" class="active">Home</a></li>
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

    <main>
        
        <section class="hero">
            <div class="container">
                <h1>CIT E-Lost & Found</h1>
                <p>A Digital Lost and Found Portal for the <strong>Cebu Institute of Technology – University</strong></p>
                <p style="color: var(--primary-maroon); font-weight: 600; margin-bottom: 2rem;">Connecting our CIT-U community through lost and found items</p>
                <div style="margin-top: 2rem;">
                    <a href="search.php" class="btn btn-primary">Search Items</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="report.php" class="btn btn-secondary">Report Item</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-secondary">Get Started</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
            
            <div class="stats">
                <div class="stat-item fade-in">
                    <span class="stat-number"><?php echo $stats['total_items']; ?></span>
                    <span class="stat-label">Total Items</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-number"><?php echo $stats['lost_items']; ?></span>
                    <span class="stat-label">Lost Items</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-number"><?php echo $stats['found_items']; ?></span>
                    <span class="stat-label">Found Items</span>
                </div>
                <div class="stat-item fade-in">
                    <span class="stat-number"><?php echo $stats['returned_items']; ?></span>
                    <span class="stat-label">Items Returned</span>
                </div>
            </div>

            
            <?php if (!empty($recent_items)): ?>
            <section style="margin: 4rem 0;">
                <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-maroon);">Recent Posts</h2>
                <div class="grid grid-3">
                    <?php foreach ($recent_items as $item): ?>
                    <div class="item-card slide-in">
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
                                <span><?php echo date('M j, Y', strtotime($item['date_lost_found'])); ?></span>
                            </div>
                            <p style="color: var(--gray-600); margin-bottom: 0.5rem;">
                                <strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?>
                            </p>
                            <p style="color: var(--gray-600); margin-bottom: 1rem;">
                                <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <a href="item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="width: 100%;">View Details</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                
                <div style="text-align: center; margin-top: 3rem;">
                    <a href="search.php" class="btn btn-secondary">View All Items</a>
                </div>
            </section>
            <?php else: ?>
            
            <section style="margin: 4rem 0;">
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3 style="color: var(--gray-600); margin-bottom: 1rem;">No Items Posted Yet</h3>
                    <p style="color: var(--gray-600); margin-bottom: 2rem;">Be the first to help the CIT-U community by reporting a lost or found item!</p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="report.php" class="btn btn-primary">Report Your First Item</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-primary">Join CIT E-Lost & Found</a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            
            <section style="margin: 4rem 0;">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-maroon);">How It Works</h2>
                <div class="grid grid-3">
                    <div class="card fade-in" style="text-align: center;">
                        <div style="width: 60px; height: 60px; background-color: var(--light-maroon); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary-maroon); font-size: 1.5rem; font-weight: bold;">1</div>
                        <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">Report Item</h3>
                        <p style="color: var(--gray-600);">Found something or lost an item? Create an account and report it with details and photos.</p>
                    </div>
                    <div class="card fade-in" style="text-align: center;">
                        <div style="width: 60px; height: 60px; background-color: var(--light-maroon); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary-maroon); font-size: 1.5rem; font-weight: bold;">2</div>
                        <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">Search & Connect</h3>
                        <p style="color: var(--gray-600);">Browse through posted items and connect with other CIT-U community members.</p>
                    </div>
                    <div class="card fade-in" style="text-align: center;">
                        <div style="width: 60px; height: 60px; background-color: var(--light-maroon); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--primary-maroon); font-size: 1.5rem; font-weight: bold;">3</div>
                        <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">Reunite Items</h3>
                        <p style="color: var(--gray-600);">Message item owners to arrange safe return and help reunite items with their owners.</p>
                    </div>
                </div>
            </section>

            <?php if (!isset($_SESSION['user_id'])): ?>
            <section style="margin: 4rem 0;">
                <div class="card" style="background: linear-gradient(135deg, var(--light-maroon), var(--light-gold)); text-align: center; padding: 3rem;">
                    <h2 style="color: var(--primary-maroon); margin-bottom: 1rem;">Join the CIT-U Community</h2>
                    <p style="color: var(--gray-600); margin-bottom: 2rem; font-size: 1.1rem;">Help your fellow students and staff by joining our digital lost and found community.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="register.php" class="btn btn-primary">Create Account</a>
                        <a href="login.php" class="btn btn-secondary">Login</a>
                    </div>
                </div>
            </section>
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
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navLinks = document.getElementById('navLinks');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

            function toggleMobileMenu() {
                navLinks.classList.toggle('mobile-menu-open');
                mobileMenuToggle.classList.toggle('active');
                mobileMenuOverlay.classList.toggle('active');
            }

            function closeMobileMenu() {
                navLinks.classList.remove('mobile-menu-open');
                mobileMenuToggle.classList.remove('active');
                mobileMenuOverlay.classList.remove('active');
            }

            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            mobileMenuOverlay.addEventListener('click', closeMobileMenu);

            const navLinksItems = navLinks.querySelectorAll('a');
            navLinksItems.forEach(link => {
                link.addEventListener('click', closeMobileMenu);
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                }
            });

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

            document.querySelectorAll('.stat-item, .item-card, .card.fade-in').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
