<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;

require_once dirname(__DIR__) . '/inc/config.php';

echo "Running HerMap v2 Database Migration...\n";

// Connect with admin/setup privileges (like setup-database.php)
try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=hermap;charset=utf8mb4', 'root', getenv('HERMAP_SETUP_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    // Fallback to runtime connection if root is not available
    $db = ps_db();
}

// 1. Create hm_contact_messages table
$db->exec("
CREATE TABLE IF NOT EXISTS hm_contact_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_id CHAR(16) NOT NULL UNIQUE,
    category ENUM('general', 'privacy_request', 'content_concern', 'legal_grievance', 'bug_report') NOT NULL DEFAULT 'general',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved', 'spam') NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    KEY idx_status_created (status, created_at),
    KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ hm_contact_messages table verified/created.\n";

// 2. Create hm_admins table
$db->exec("
CREATE TABLE IF NOT EXISTS hm_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret VARCHAR(64) NOT NULL,
    role ENUM('superadmin', 'moderator') NOT NULL DEFAULT 'moderator',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ hm_admins table verified/created.\n";

// 3. Create hm_settings table
$db->exec("
CREATE TABLE IF NOT EXISTS hm_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✓ hm_settings table verified/created.\n";

// 4. Update hm_moderation_actions with reason and notes columns if they don't exist
$cols = $db->query("SHOW COLUMNS FROM hm_moderation_actions")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('reason', $cols, true)) {
    $db->exec("ALTER TABLE hm_moderation_actions ADD COLUMN reason VARCHAR(60) NULL AFTER action");
    echo "✓ Added column 'reason' to hm_moderation_actions.\n";
}
if (!in_array('notes', $cols, true)) {
    $db->exec("ALTER TABLE hm_moderation_actions ADD COLUMN notes TEXT NULL AFTER reason");
    echo "✓ Added column 'notes' to hm_moderation_actions.\n";
}

// 4b. Update hm_incidents with message_encrypted column if missing
$incCols = $db->query("SHOW COLUMNS FROM hm_incidents")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('message_encrypted', $incCols, true)) {
    $db->exec("ALTER TABLE hm_incidents ADD COLUMN message_encrypted TEXT NULL AFTER venue_encrypted");
    echo "✓ Added column 'message_encrypted' to hm_incidents.\n";
}

// 5. Grant permissions to hermaph_app on all hm_ tables
try {
    foreach ($db->query("SHOW TABLES LIKE 'hm_%'")->fetchAll(PDO::FETCH_COLUMN) as $ownedTable) {
        $db->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON hermap.`$ownedTable` TO 'hermap_app'@'127.0.0.1'");
    }
    echo "✓ Permissions granted to hermaph_app for all hm_* tables.\n";
} catch (Throwable $e) {
    echo "Note: table grant skipped or already assigned.\n";
}

// 6. Seed default admin from .env into hm_admins if table is empty and .env has credentials
$adminUser = getenv('ADMIN_USERNAME') ?: 'amit';
$adminPassHash = getenv('ADMIN_PASSWORD_HASH') ?: '';
$adminTotp = getenv('ADMIN_TOTP_SECRET') ?: '';

if ($adminPassHash !== '' && $adminTotp !== '') {
    $stmt = $db->prepare("SELECT id FROM hm_admins WHERE username = ?");
    $stmt->execute([$adminUser]);
    if (!$stmt->fetch()) {
        $insert = $db->prepare("INSERT INTO hm_admins (username, password_hash, totp_secret, role, is_active) VALUES (?, ?, ?, 'superadmin', 1)");
        $insert->execute([$adminUser, $adminPassHash, $adminTotp]);
        echo "✓ Seeded admin user '{$adminUser}' into hm_admins table.\n";
    }
}

// 7. Seed initial settings if missing
$settings = [
    'report_intake_enabled' => getenv('REPORT_INTAKE_ENABLED') ?: '1',
    'public_min_reports' => (string)max(5, (int)(getenv('PUBLIC_MIN_REPORTS') ?: 5)),
    'publication_delay_hours' => (string)max(24, (int)(getenv('PUBLICATION_DELAY_HOURS') ?: 48)),
];

foreach ($settings as $key => $val) {
    $stmt = $db->prepare("INSERT IGNORE INTO hm_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $val]);
}
echo "✓ Verified initial settings in hm_settings.\n";

echo "Migration v2 completed successfully.\n";
