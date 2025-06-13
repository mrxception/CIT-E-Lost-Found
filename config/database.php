<?php
class Database {
    private $host = '';
    private $db_name = '';
    private $username = '';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        return $this->conn;
    }
}

function createTables() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user','admin') DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('lost','found') NOT NULL,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        location VARCHAR(200),
        status ENUM('pending','approved','returned') DEFAULT 'pending',
        image_path VARCHAR(255),
        cloudinary_public_id VARCHAR(255),
        date_reported DATE NOT NULL,
        date_lost_found DATE NOT NULL,
        contact_info VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        item_id INT NOT NULL,
        parent_id INT NULL,
        content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id),
        FOREIGN KEY (item_id) REFERENCES items(id),
        FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE
    )";
    $db->exec($query);
    
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM messages LIKE 'is_read'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
        }
    } catch(PDOException $e) {
    }
    
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM messages LIKE 'parent_id'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE messages ADD COLUMN parent_id INT NULL AFTER item_id");
            $db->exec("ALTER TABLE messages ADD CONSTRAINT fk_messages_parent FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE");
        }
    } catch(PDOException $e) {
    }
    
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM items LIKE 'cloudinary_public_id'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE items ADD COLUMN cloudinary_public_id VARCHAR(255) NULL AFTER image_path");
        }
    } catch(PDOException $e) {
    }
    
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM items LIKE 'contact_info'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE items ADD COLUMN contact_info VARCHAR(255) NULL");
        }
    } catch(PDOException $e) {
    }
    
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_parent_id ON messages(parent_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_item_id ON messages(item_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_receiver_id ON messages(receiver_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_items_cloudinary_public_id ON items(cloudinary_public_id)");
    } catch(PDOException $e) {
    }
    
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $query = "INSERT IGNORE INTO users (name, email, password, role) VALUES ('Admin', 'admin@cit.edu', ?, 'admin')";
    $stmt = $db->prepare($query);
    $stmt->execute([$admin_password]);
}
?>