<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_POST) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Email already registered";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $database = new Database();
    $db = $database->getConnection();
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
    <title>Register - CIT E-Lost & Found</title>
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
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="active">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container auth-container" style="margin-top: 2rem; margin-bottom: 2rem; padding-top: 1rem; padding-bottom: 2rem;">
        <div style="max-width: 400px; width: 100%; margin: 0 auto;">
            <div class="card fade-in">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="color: var(--primary-maroon); margin-bottom: 0.5rem;">Join CIT E-Lost & Found</h2>
                    <p style="color: var(--gray-600);">Create your account to start reporting items</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                </form>
                
                <p style="text-align: center; margin-top: 2rem; color: var(--gray-600);">
                    Already have an account? <a href="login.php" style="color: var(--primary-maroon); font-weight: 600;">Login here</a>
                </p>
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
