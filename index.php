<?php
require_once __DIR__ . '/db.php';

// Handle API requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = getDBConnection();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Helper: Read JSON payload if sent as application/json
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true) ?? [];
    if (!empty($jsonInput)) {
        $_POST = array_merge($_POST, $jsonInput);
        if (isset($jsonInput['action'])) {
            $action = $jsonInput['action'];
        }
    }

    try {
        switch ($action) {
            case 'fetch':
                $date = $_GET['date'] ?? date('Y-m-d');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $date = date('Y-m-d');
                }

                // Fetch all students along with their status for the chosen date
                // and their total historical attendance stats
                $query = "
                    SELECT 
                        s.id,
                        s.roll_number,
                        s.name,
                        s.created_at,
                        a.status AS current_status,
                        COUNT(hist.id) AS total_marked_days,
                        SUM(CASE WHEN hist.status = 'Present' THEN 1 ELSE 0 END) AS total_present_days
                    FROM students s
                    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ?
                    LEFT JOIN attendance hist ON s.id = hist.student_id
                    GROUP BY s.id, a.status
                    ORDER BY s.roll_number ASC, s.name ASC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$date]);
                $students = $stmt->fetchAll();

                $totalStudents = count($students);
                $presentCount = 0;
                $absentCount = 0;
                $unmarkedCount = 0;

                $formattedStudents = [];
                foreach ($students as $stu) {
                    $status = $stu['current_status'] ?? 'Unmarked';
                    if ($status === 'Present') {
                        $presentCount++;
                    } elseif ($status === 'Absent') {
                        $absentCount++;
                    } else {
                        $unmarkedCount++;
                    }

                    $totalDays = (int)$stu['total_marked_days'];
                    $presentDays = (int)$stu['total_present_days'];
                    $historicalPct = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 100.0;

                    $formattedStudents[] = [
                        'id' => (int)$stu['id'],
                        'roll_number' => $stu['roll_number'],
                        'name' => $stu['name'],
                        'status' => $status,
                        'total_days' => $totalDays,
                        'present_days' => $presentDays,
                        'historical_percentage' => $historicalPct
                    ];
                }

                // Calculate Live Daily Attendance Percentage
                // Based on total students (if unmarked, they aren't counted as present)
                $livePercentage = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0.0;

                echo json_encode([
                    'success' => true,
                    'date' => $date,
                    'summary' => [
                        'total_students' => $totalStudents,
                        'present_count' => $presentCount,
                        'absent_count' => $absentCount,
                        'unmarked_count' => $unmarkedCount,
                        'live_percentage' => $livePercentage
                    ],
                    'students' => $formattedStudents
                ]);
                exit;

            case 'add_student':
                $name = trim($_POST['name'] ?? '');
                $roll = trim($_POST['roll_number'] ?? '');

                if ($name === '') {
                    echo json_encode(['success' => false, 'error' => 'Student name is required.']);
                    exit;
                }

                if ($roll === '') {
                    // Auto generate a roll number
                    $countStmt = $pdo->query("SELECT MAX(id) FROM students");
                    $nextId = ((int)$countStmt->fetchColumn()) + 101;
                    $roll = 'STU-' . $nextId;
                }

                // Check duplicate roll number
                $chk = $pdo->prepare("SELECT id FROM students WHERE roll_number = ?");
                $chk->execute([$roll]);
                if ($chk->fetch()) {
                    echo json_encode(['success' => false, 'error' => "Roll number '$roll' already exists."]);
                    exit;
                }

                $ins = $pdo->prepare("INSERT INTO students (roll_number, name) VALUES (?, ?)");
                $ins->execute([$roll, $name]);
                $newId = $pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => "Student '$name' ($roll) added successfully.",
                    'student' => [
                        'id' => (int)$newId,
                        'roll_number' => $roll,
                        'name' => $name
                    ]
                ]);
                exit;

            case 'delete_student':
                $studentId = (int)($_POST['id'] ?? 0);
                if ($studentId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid student ID.']);
                    exit;
                }

                $del = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $del->execute([$studentId]);

                echo json_encode(['success' => true, 'message' => 'Student removed successfully.']);
                exit;

            case 'mark_single':
                $studentId = (int)($_POST['student_id'] ?? 0);
                $date = $_POST['date'] ?? date('Y-m-d');
                $status = $_POST['status'] ?? 'Present';

                if ($studentId <= 0 || !in_array($status, ['Present', 'Absent'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid student ID or status.']);
                    exit;
                }

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $date = date('Y-m-d');
                }

                $upsert = $pdo->prepare("
                    INSERT INTO attendance (student_id, attendance_date, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $upsert->execute([$studentId, $date, $status]);

                echo json_encode(['success' => true, 'message' => "Attendance updated to $status."]);
                exit;

            case 'bulk_mark':
                $date = $_POST['date'] ?? date('Y-m-d');
                $status = $_POST['status'] ?? 'Present';

                if (!in_array($status, ['Present', 'Absent'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
                    exit;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $date = date('Y-m-d');
                }

                $students = $pdo->query("SELECT id FROM students")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($students)) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (student_id, attendance_date, status)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status)
                    ");
                    foreach ($students as $sId) {
                        $stmt->execute([$sId, $date, $status]);
                    }
                    $pdo->commit();
                }

                echo json_encode([
                    'success' => true, 
                    'message' => "Marked all students as $status."
                ]);
                exit;

            case 'save_all':
                $date = $_POST['date'] ?? date('Y-m-d');
                $records = $_POST['records'] ?? [];

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $date = date('Y-m-d');
                }

                if (is_array($records) && !empty($records)) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (student_id, attendance_date, status)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE status = VALUES(status)
                    ");
                    foreach ($records as $item) {
                        $sId = (int)($item['student_id'] ?? 0);
                        $status = $item['status'] ?? 'Present';
                        if ($sId > 0 && in_array($status, ['Present', 'Absent'])) {
                            $stmt->execute([$sId, $date, $status]);
                        }
                    }
                    $pdo->commit();
                }

                echo json_encode(['success' => true, 'message' => 'All attendance records saved successfully.']);
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action.']);
                exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Ensure database connection is initialized for rendering
$pdo = getDBConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Tracker</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for clean icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Project Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <!-- Top App Header -->
        <header class="app-header">
            <div class="header-brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-clipboard-user"></i>
                </div>
                <div>
                    <h1>Student Attendance Tracker</h1>
                    <p class="subtitle">Daily Classroom Attendance & Live Performance Tracking</p>
                </div>
            </div>

            <!-- Date Selector Control -->
            <div class="date-control-card">
                <button id="prevDateBtn" class="btn btn-icon" title="Previous Day">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="date-input-wrapper">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="date" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button id="nextDateBtn" class="btn btn-icon" title="Next Day">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
                <button id="todayDateBtn" class="btn btn-sm btn-outline">Today</button>
            </div>
        </header>

        <!-- Live Analytics Summary Dashboard -->
        <section class="stats-grid">
            <!-- Total Students Card -->
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Total Students</span>
                    <h2 class="stat-value" id="statTotalStudents">0</h2>
                    <span class="stat-subtext">Registered in class</span>
                </div>
            </div>

            <!-- Present Today Card -->
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Present Today</span>
                    <h2 class="stat-value text-green" id="statPresentCount">0</h2>
                    <span class="stat-subtext">Attending class</span>
                </div>
            </div>

            <!-- Absent Today Card -->
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Absent Today</span>
                    <h2 class="stat-value text-red" id="statAbsentCount">0</h2>
                    <span class="stat-subtext">Missing or on leave</span>
                </div>
            </div>

            <!-- Live Attendance Percentage Card -->
            <div class="stat-card stat-card-featured">
                <div class="stat-featured-header">
                    <div>
                        <span class="stat-label">Live Attendance Rate</span>
                        <div class="percentage-display">
                            <h2 class="stat-value" id="statPercentage">0.0%</h2>
                            <span class="badge" id="rateStatusBadge">Good</span>
                        </div>
                    </div>
                    <div class="radial-ring-wrap">
                        <svg class="progress-ring" width="56" height="56">
                            <circle class="progress-ring__circle-bg" stroke="#e2e8f0" stroke-width="5" fill="transparent" r="23" cx="28" cy="28"/>
                            <circle class="progress-ring__circle" id="radialCircle" stroke="#10b981" stroke-width="5" stroke-dasharray="144.5" stroke-dashoffset="144.5" stroke-linecap="round" fill="transparent" r="23" cx="28" cy="28"/>
                        </svg>
                    </div>
                </div>
                <!-- Mini Progress Bar -->
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="statProgressBar" style="width: 0%;"></div>
                </div>
                <span class="stat-subtext" id="statRateDescription">Calculated in real-time</span>
            </div>
        </section>

        <!-- Main Workspace Card -->
        <main class="main-card">
            <!-- Toolbar & Controls -->
            <div class="toolbar">
                <!-- Search & Filter -->
                <div class="toolbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search by name or roll number...">
                        <button id="clearSearchBtn" class="clear-btn" style="display: none;">&times;</button>
                    </div>
                    
                    <div class="filter-group">
                        <button class="filter-chip active" data-filter="all">All (<span id="countFilterAll">0</span>)</button>
                        <button class="filter-chip" data-filter="Present">Present (<span id="countFilterPresent">0</span>)</button>
                        <button class="filter-chip" data-filter="Absent">Absent (<span id="countFilterAbsent">0</span>)</button>
                        <button class="filter-chip" data-filter="Unmarked">Unmarked (<span id="countFilterUnmarked">0</span>)</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="toolbar-right">
                    <div class="btn-group">
                        <button id="markAllPresentBtn" class="btn btn-sm btn-outline-success" title="Mark all students present">
                            <i class="fa-solid fa-check-double"></i> Mark All Present
                        </button>
                        <button id="markAllAbsentBtn" class="btn btn-sm btn-outline-danger" title="Mark all students absent">
                            <i class="fa-solid fa-xmark"></i> Mark All Absent
                        </button>
                    </div>

                    <button id="openAddModalBtn" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i> Add Student
                    </button>
                </div>
            </div>

            <!-- Attendance Table Container -->
            <div class="table-responsive">
                <table class="attendance-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th width="12%">Roll No</th>
                            <th width="30%">Student Name</th>
                            <th width="28%">Attendance Status (Live)</th>
                            <th width="20%">Historical Record</th>
                            <th width="10%" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <!-- Loaded dynamically via script.js -->
                    </tbody>
                </table>

                <!-- Empty State Placeholder -->
                <div id="emptyState" class="empty-state" style="display: none;">
                    <div class="empty-icon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <h3>No Students Found</h3>
                    <p>There are no students matching your query. Add your first student to get started!</p>
                    <button class="btn btn-primary" onclick="openAddStudentModal()">
                        <i class="fa-solid fa-user-plus"></i> Add Student
                    </button>
                </div>

                <!-- Loading State Skeleton -->
                <div id="loadingState" class="loading-state">
                    <div class="spinner"></div>
                    <p>Loading attendance data...</p>
                </div>
            </div>

            <!-- Table Footer -->
            <div class="table-footer">
                <div class="footer-info">
                    <span id="showingCountText">Showing 0 students</span>
                    <span class="live-indicator"><span class="dot"></span> Changes auto-save in real-time to MySQL</span>
                </div>
                <div class="footer-actions">
                    <button id="saveAllBtn" class="btn btn-success">
                        <i class="fa-solid fa-floppy-disk"></i> Force Save All
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Add New Student -->
    <div class="modal-overlay" id="addStudentModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title-wrap">
                    <div class="modal-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h3>Add New Student</h3>
                </div>
                <button class="modal-close-btn" id="closeAddModalBtn">&times;</button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="studentNameInput">Full Name <span class="required">*</span></label>
                        <input type="text" id="studentNameInput" placeholder="e.g. John Doe" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="studentRollInput">Roll Number / Student ID</label>
                        <input type="text" id="studentRollInput" placeholder="e.g. STU-106 (optional, auto-generated if blank)">
                        <small class="form-hint">Unique identifier for the student.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelAddBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveStudentBtn">
                        <i class="fa-solid fa-check"></i> Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Scripts -->
    <script src="script.js"></script>
</body>
</html>
