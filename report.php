<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'config/cloudinary.php';
require_once 'includes/navigation.php';

$database = new Database();
$db = $database->getConnection();
$cloudinary = new CloudinaryUploader();


$cloudinary->enableLocalFallback('uploads/');


$user_check_query = "SELECT id, name FROM users WHERE id = ?";
$user_check_stmt = $db->prepare($user_check_query);
$user_check_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $type = $_POST['type'];
    $location = trim($_POST['location']);
    $date_lost_found = $_POST['date_lost_found'];
    $contact_info = trim($_POST['contact_info']);
    
    
    if (empty($name) || empty($description) || empty($category) || empty($type) || empty($location) || empty($date_lost_found)) {
        $error = "Please fill in all required fields.";
    } else {
        $image_url = null;
        $cloudinary_public_id = null;
        
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; 
            
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                $error = "Please upload a valid image file (JPEG, PNG, GIF, or WebP).";
            } elseif ($_FILES['image']['size'] > $max_size) {
                $error = "Image file size must be less than 5MB.";
            } else {
                
                $upload_result = $cloudinary->uploadImage($_FILES['image']['tmp_name']);
                
                if ($upload_result['success']) {
                    $image_url = $upload_result['url'];
                    $cloudinary_public_id = $upload_result['public_id'];
                } else {
                    $error = "Failed to upload image: " . $upload_result['error'];
                }
            }
        }
        
        if (empty($error)) {
            try {
                
                $user_exists_query = "SELECT id FROM users WHERE id = ?";
                $user_exists_stmt = $db->prepare($user_exists_query);
                $user_exists_stmt->execute([$_SESSION['user_id']]);
                
                if (!$user_exists_stmt->fetch()) {
                    throw new Exception("User account not found. Please log in again.");
                }
                
                
                $query = "INSERT INTO items (user_id, name, description, category, type, location, date_lost_found, contact_info, image_path, cloudinary_public_id, status, date_reported) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURDATE())";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$_SESSION['user_id'], $name, $description, $category, $type, $location, $date_lost_found, $contact_info, $image_url, $cloudinary_public_id])) {
                    $success = "Item reported successfully! It will be reviewed by administrators before being published.";
                    
                    
                    $_POST = array();
                } else {
                    $error = "Failed to save item. Please try again.";
                    
                    
                    if ($cloudinary_public_id) {
                        $cloudinary->deleteImage($cloudinary_public_id);
                    }
                }
            } catch (Exception $e) {
                $error = "An error occurred: " . $e->getMessage();
                
                
                if ($cloudinary_public_id) {
                    $cloudinary->deleteImage($cloudinary_public_id);
                }
                
                
                if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
                    strpos($e->getMessage(), 'User account not found') !== false) {
                    session_destroy();
                    header("Location: login.php?error=session_expired");
                    exit();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Item - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    <?php renderNavigation('report'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
        <div style="max-width: 800px; margin: 0 auto;">
            <h1 style="color: var(--primary-maroon); margin-bottom: 2rem; text-align: center;">Report Lost or Found Item</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 2rem; margin-bottom: 2rem; background-color: var(--light-maroon);">
                <h3 style="color: var(--primary-maroon); margin-bottom: 1rem;">📋 Reporting Guidelines</h3>
                <ul style="color: var(--gray-600); line-height: 1.8; list-style-type: none;">
                    <li><strong>• Be Detailed:</strong> Include as much information as possible about the item</li>
                    <li><strong>• Upload Photos:</strong> Clear images help others identify items quickly</li>
                    <li><strong>• Accurate Location:</strong> Specify exactly where the item was lost or found</li>
                    <li><strong>• Contact Info:</strong> Provide reliable contact information for quick communication</li>
                    <li><strong>• Admin Review:</strong> All reports are reviewed before being published</li>
                    <li><strong>• Privacy:</strong> Your personal information is kept secure and private</li>
                </ul>
            </div>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-2" style="gap: 2rem;">
                        <div>
                            <div class="form-group">
                                <label for="type" class="form-label">Type *</label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">Select type</option>
                                    <option value="lost" <?php echo (isset($_POST['type']) && $_POST['type'] == 'lost') ? 'selected' : ''; ?>>Lost Item</option>
                                    <option value="found" <?php echo (isset($_POST['type']) && $_POST['type'] == 'found') ? 'selected' : ''; ?>>Found Item</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="name" class="form-label">Item Name *</label>
                                <input type="text" id="name" name="name" class="form-input" required 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                       placeholder="e.g., iPhone 12, Blue Backpack, Student ID">
                            </div>
                            
                            <div class="form-group">
                                <label for="category" class="form-label">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select category</option>
                                    <option value="Electronics" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Clothing" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Clothing') ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="Books" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Books') ? 'selected' : ''; ?>>Books</option>
                                    <option value="Documents" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Documents') ? 'selected' : ''; ?>>Documents</option>
                                    <option value="Keys" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Keys') ? 'selected' : ''; ?>>Keys</option>
                                    <option value="Bags" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Bags') ? 'selected' : ''; ?>>Bags</option>
                                    <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" id="location" name="location" class="form-input" required 
                                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                                       placeholder="e.g., Library 2nd Floor, Engineering Building, Cafeteria">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="date_lost_found" class="form-label">Date Lost/Found *</label>
                                <input type="date" id="date_lost_found" name="date_lost_found" class="form-input" required 
                                       value="<?php echo isset($_POST['date_lost_found']) ? $_POST['date_lost_found'] : ''; ?>"
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" id="contact_info" name="contact_info" class="form-input" 
                                       value="<?php echo isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : ''; ?>"
                                       placeholder="Phone number, email, or other contact details">
                                <small style="color: var(--gray-600); font-size: 0.8rem;">Optional: Others can contact you directly</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="image" class="form-label">Upload Image</label>
                                <input type="file" id="image" name="image" class="form-input" accept="image/*">
                                <small style="color: var(--gray-600); font-size: 0.8rem;">
                                    Supported formats: JPEG, PNG, GIF, WebP (Max: 5MB)<br>
                                    Images will be stored locally if Cloudinary is unavailable
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" class="form-textarea" required 
                                  placeholder="Provide detailed description including color, size, brand, distinctive features, circumstances of loss/finding, etc."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CIT E-Lost & Found. All rights reserved.</p>
        </div>
    </footer>

    <script>
        
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                    return;
                }
                
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF, or WebP)');
                    this.value = '';
                    return;
                }
                
                
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                console.log(`Selected: ${fileName} (${fileSize} MB)`);
            }
        });

        
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['type', 'name', 'category', 'location', 'date_lost_found', 'description'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                if (!element.value.trim()) {
                    isValid = false;
                    element.style.borderColor = 'var(--danger)';
                } else {
                    element.style.borderColor = 'var(--gray-200)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>
