<?php
// db.php - Database connection & automatic table initialization

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_db');

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First connect without database name to ensure DB exists
        $dsnWithoutDb = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $tempPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
        
        // Create database if not exists
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect directly to the database
        $dsnWithDb = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, $options);

        // Ensure tables exist
        initializeTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        // If request is AJAX, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' 
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            || isset($_GET['action'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed: ' . $e->getMessage() . '. Please verify MySQL is running in XAMPP/WAMP.'
            ]);
            exit;
        }

        // Render friendly error message
        die("
            <div style='font-family: system-ui, sans-serif; max-width: 600px; margin: 40px auto; padding: 24px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 8px; color: #991b1b;'>
                <h3 style='margin-top: 0;'>Database Connection Error</h3>
                <p>Could not connect to MySQL server at <strong>" . htmlspecialchars(DB_HOST) . "</strong> with user <strong>" . htmlspecialchars(DB_USER) . "</strong>.</p>
                <p style='background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #fee2e2; font-family: monospace; font-size: 13px;'>" . htmlspecialchars($e->getMessage()) . "</p>
                <h4>Troubleshooting Steps:</h4>
                <ol style='line-height: 1.6; padding-left: 20px;'>
                    <li>Open <strong>XAMPP / WampServer</strong> control panel.</li>
                    <li>Start the <strong>Apache</strong> and <strong>MySQL</strong> services.</li>
                    <li>If you configured a MySQL password, update <code>DB_PASS</code> in <code>db.php</code>.</li>
                    <li>Refresh this page.</li>
                </ol>
            </div>
        ");
    }
}

function initializeTables($pdo) {
    // Create Students Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `students` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `roll_number` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create Attendance Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `attendance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` ENUM('Present', 'Absent') NOT NULL DEFAULT 'Present',
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_student_date` (`student_id`, `attendance_date`),
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed dummy data if empty so the user can test right away
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM `students`");
    if ($checkStmt->fetchColumn() == 0) {
        $dummyStudents = [
            ['STU-101', 'Alex Johnson'],
            ['STU-102', 'Sophia Davis'],
            ['STU-103', 'Liam Miller'],
            ['STU-104', 'Emma Wilson'],
            ['STU-105', 'Noah Brown']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO `students` (`roll_number`, `name`) VALUES (?, ?)");
        foreach ($dummyStudents as $stu) {
            $insertStmt->execute($stu);
        }

        // Add some sample attendance for yesterday and today
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $stuRows = $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
        $attStmt = $pdo->prepare("INSERT IGNORE INTO `attendance` (`student_id`, `attendance_date`, `status`) VALUES (?, ?, ?)");
        
        // Yesterday sample
        if (!empty($stuRows)) {
            $attStmt->execute([$stuRows[0], $yesterday, 'Present']);
            $attStmt->execute([$stuRows[1], $yesterday, 'Present']);
            $attStmt->execute([$stuRows[2], $yesterday, 'Absent']);
            $attStmt->execute([$stuRows[3], $yesterday, 'Present']);
            $attStmt->execute([$stuRows[4], $yesterday, 'Present']);

            // Today sample
            $attStmt->execute([$stuRows[0], $today, 'Present']);
            $attStmt->execute([$stuRows[1], $today, 'Present']);
            $attStmt->execute([$stuRows[2], $today, 'Present']);
            $attStmt->execute([$stuRows[3], $today, 'Absent']);
            $attStmt->execute([$stuRows[4], $today, 'Present']);
        }
    }
}
?>
