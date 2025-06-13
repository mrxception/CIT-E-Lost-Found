<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db === null) {
            throw new Exception("Failed to connect to the database.");
        }
        
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        error_log("Login error: " . $e->getMessage());
    }
}

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_count = $result ? $result['unread'] : 0;
    } catch (Exception $e) {
        error_log("Unread messages error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CIT E-Lost & Found</title>
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
                    <li><a href="login.php" class="active">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container auth-container" style="margin-top: 2rem; margin-bottom: 2rem; padding-top: 1rem; padding-bottom: 2rem;">
        <div style="max-width: 400px; width: 100%; margin: 0 auto;">
            <div class="card fade-in">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-maroon); margin-bottom: 0.5rem;">Welcome Back</h2>
                    <p style="color: var(--gray-600);">Login to your CIT E-Lost & Found account</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                
                <p style="text-align: center; margin-top: 2rem; color: var(--gray-600);">
                    Don't have an account? <a href="register.php" style="color: var(--primary-maroon); font-weight: 600;">Register here</a>
                </p>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>© 2025 CIT E-Lost & Found. All rights reserved.</p>
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
        });
    </script>
</body>
</html>