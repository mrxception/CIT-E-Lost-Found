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

$error = '';
$success = '';
$item = null;


$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($item_id <= 0) {
    header("Location: dashboard.php");
    exit();
}


$query = "SELECT * FROM items WHERE id = ? AND user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: dashboard.php");
    exit();
}

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
        $image_url = $item['image_path']; 
        $cloudinary_public_id = $item['cloudinary_public_id'];
        
        
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
                    
                    if ($item['cloudinary_public_id']) {
                        $cloudinary->deleteImage($item['cloudinary_public_id']);
                    }
                    
                    $image_url = $upload_result['url'];
                    $cloudinary_public_id = $upload_result['public_id'];
                } else {
                    $error = "Failed to upload image: " . $upload_result['error'];
                }
            }
        }
        
        
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            
            if ($item['cloudinary_public_id']) {
                $cloudinary->deleteImage($item['cloudinary_public_id']);
            }
            
            $image_url = null;
            $cloudinary_public_id = null;
        }
        
        if (empty($error)) {
            try {
                $query = "UPDATE items SET name = ?, description = ?, category = ?, type = ?, location = ?, date_lost_found = ?, contact_info = ?, image_path = ?, cloudinary_public_id = ?, status = 'pending' WHERE id = ? AND user_id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $description, $category, $type, $location, $date_lost_found, $contact_info, $image_url, $cloudinary_public_id, $item_id, $_SESSION['user_id']])) {
                    header("Location: dashboard.php?updated=success");
                    exit();
                } else {
                    $error = "Failed to update item. Please try again.";
                }
            } catch (Exception $e) {
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
}

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
    <title>Edit Item - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>
    <?php renderNavigation('dashboard'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 2rem; padding-bottom: 2rem;">
        <div style="max-width: 800px; margin: 0 auto;">
            <h1 style="color: var(--primary-maroon); margin-bottom: 2rem; text-align: center;">Edit Item</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid grid-2" style="gap: 2rem;">
                        <div>
                            <div class="form-group">
                                <label for="type" class="form-label">Type *</label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">Select type</option>
                                    <option value="lost" <?php echo $item['type'] == 'lost' ? 'selected' : ''; ?>>Lost Item</option>
                                    <option value="found" <?php echo $item['type'] == 'found' ? 'selected' : ''; ?>>Found Item</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="name" class="form-label">Item Name *</label>
                                <input type="text" id="name" name="name" class="form-input" required 
                                       value="<?php echo htmlspecialchars($item['name']); ?>"
                                       placeholder="e.g., iPhone 12, Blue Backpack, Student ID">
                            </div>
                            
                            <div class="form-group">
                                <label for="category" class="form-label">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select category</option>
                                    <option value="Electronics" <?php echo $item['category'] == 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Clothing" <?php echo $item['category'] == 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Accessories" <?php echo $item['category'] == 'Accessories' ? 'selected' : ''; ?>>Accessories</option>
                                    <option value="Books" <?php echo $item['category'] == 'Books' ? 'selected' : ''; ?>>Books</option>
                                    <option value="Documents" <?php echo $item['category'] == 'Documents' ? 'selected' : ''; ?>>Documents</option>
                                    <option value="Keys" <?php echo $item['category'] == 'Keys' ? 'selected' : ''; ?>>Keys</option>
                                    <option value="Bags" <?php echo $item['category'] == 'Bags' ? 'selected' : ''; ?>>Bags</option>
                                    <option value="Other" <?php echo $item['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" id="location" name="location" class="form-input" required 
                                       value="<?php echo htmlspecialchars($item['location']); ?>"
                                       placeholder="e.g., Library 2nd Floor, Engineering Building, Cafeteria">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="date_lost_found" class="form-label">Date Lost/Found *</label>
                                <input type="date" id="date_lost_found" name="date_lost_found" class="form-input" required 
                                       value="<?php echo $item['date_lost_found']; ?>"
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_info" class="form-label">Contact Information</label>
                                <input type="text" id="contact_info" name="contact_info" class="form-input" 
                                       value="<?php echo htmlspecialchars($item['contact_info']); ?>"
                                       placeholder="Phone number, email, or other contact details">
                                <small style="color: var(--gray-600); font-size: 0.8rem;">Optional: Others can contact you directly</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Current Image</label>
                                <?php if ($item['image_path']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Current item image" 
                                             style="max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 0.5rem; border: 1px solid var(--gray-200);">
                                        <div style="margin-top: 0.5rem;">
                                            <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--danger); cursor: pointer;">
                                                <input type="checkbox" name="remove_image" value="1" id="remove_image">
                                                Remove current image
                                            </label>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--gray-600); font-size: 0.9rem; margin-bottom: 1rem;">No image uploaded</p>
                                <?php endif; ?>
                                
                                <label for="image" class="form-label">Upload New Image</label>
                                <input type="file" id="image" name="image" class="form-input" accept="image/*">
                                <small style="color: var(--gray-600); font-size: 0.8rem;">
                                    Supported formats: JPEG, PNG, GIF, WebP (Max: 5MB)<br>
                                    Leave empty to keep current image
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" class="form-textarea" required 
                                  placeholder="Provide detailed description including color, size, brand, distinctive features, circumstances of loss/finding, etc."><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    
                    <div style="background-color: var(--light-gold); padding: 1rem; border-radius: 0.5rem; margin: 1.5rem 0;">
                        <p style="color: var(--primary-maroon); font-weight: 600; margin: 0;">
                            ⚠️ Note: After editing, your item will need to be reviewed again by administrators before being published.
                        </p>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Item</button>
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
            }
        });

        
        document.getElementById('remove_image')?.addEventListener('change', function() {
            const imageInput = document.getElementById('image');
            if (this.checked) {
                imageInput.disabled = true;
                imageInput.value = '';
            } else {
                imageInput.disabled = false;
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
