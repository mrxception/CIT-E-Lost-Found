<?php
session_start();
require_once 'config/database.php';
require_once 'includes/navigation.php';

$database = new Database();
$db = $database->getConnection();


$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';

$query = "SELECT i.*, u.name as user_name FROM items i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.status = 'approved'";
$params = [];

if ($search_query) {
    $query .= " AND (i.name LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($category_filter) {
    $query .= " AND i.category = ?";
    $params[] = $category_filter;
}

if ($type_filter) {
    $query .= " AND i.type = ?";
    $params[] = $type_filter;
}

if ($location_filter) {
    $query .= " AND i.location LIKE ?";
    $params[] = "%$location_filter%";
}

$query .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


$cat_query = "SELECT DISTINCT category FROM items WHERE status = 'approved' ORDER BY category";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);


$loc_query = "SELECT DISTINCT location FROM items WHERE status = 'approved' AND location IS NOT NULL ORDER BY location";
$loc_stmt = $db->prepare($loc_query);
$loc_stmt->execute();
$locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Items - CIT E-Lost & Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php renderNavigation('search'); ?>

    <main class="container" style="margin-top: 2rem; margin-bottom: 4rem;">
        <h1 style="color: var(--primary-maroon); margin-bottom: 2rem;">Search Lost & Found Items</h1>
        
        
        <div class="card" style="margin-bottom: 2rem;">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" id="search" name="search" class="form-input" 
                        placeholder="Search items..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type" class="form-label">Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type_filter == 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="found" <?php echo $type_filter == 'found' ? 'selected' : ''; ?>>Found</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <select id="location" name="location" class="form-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" 
                                    <?php echo $location_filter == $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="search.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        
        <div class="search-results">
            <?php if (empty($items)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <h3 style="color: var(--gray-600); margin-bottom: 1rem;">No items found</h3>
                    <p style="color: var(--gray-600);">Try adjusting your search criteria or <a href="report.php" style="color: var(--primary-maroon);">report a new item</a>.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-3" style="gap: 2rem;">
                    <?php foreach ($items as $item): ?>
                        <div class="card item-card fade-in" style="cursor: pointer;" onclick="window.location.href='item.php?id=<?php echo $item['id']; ?>'">
                            <?php if ($item['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="Item Image" class="item-image">
                            <?php else: ?>
                                <div class="item-image-placeholder">No Image</div>
                            <?php endif; ?>
                            
                            <div class="item-content">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <h3 style="margin: 0; color: var(--gray-800);"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <span class="status-badge status-<?php echo $item['type']; ?>">
                                        <?php echo ucfirst($item['type']); ?>
                                    </span>
                                </div>
                                
                                <p style="color: var(--gray-600); margin: 0.5rem 0; font-size: 0.9rem;">
                                    <strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?>
                                </p>
                                
                                <p style="color: var(--gray-600); margin: 0.5rem 0; font-size: 0.9rem;">
                                    <strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?>
                                </p>
                                
                                <p style="color: var(--gray-600); margin: 0.5rem 0; font-size: 0.9rem;">
                                    <strong>Date:</strong> <?php echo date('M j, Y', strtotime($item['date_lost_found'])); ?>
                                </p>
                                
                                <p style="color: var(--gray-600); margin: 1rem 0 0 0; font-size: 0.85rem;">
                                    Posted by <?php echo htmlspecialchars($item['user_name']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <p style="color: var(--gray-600);">Found <?php echo count($items); ?> item(s)</p>
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
        
        document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.item-card');
        
        cards.forEach(card => {
            card.classList.add('fade-in');
        });

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.animated) {
                    entry.target.classList.add('fade-in');
                    entry.target.dataset.animated = 'true'; 
                    observer.unobserve(entry.target); 
                }
            });
        }, observerOptions);

        cards.forEach(card => {
            if (!card.dataset.animated) {
                observer.observe(card);
            }
        });
    });
    </script>
</body>
</html>
