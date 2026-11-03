# Student Attendance Tracker

A full-stack, responsive Student Attendance Tracker web application built with **PHP**, **MySQL**, **Vanilla JavaScript**, and **CSS3**.

---

## 🚀 Key Features

1. **Add & Manage Students**:
   - Add new students with Full Name and custom Roll Number / Student ID.
   - Automatically generates unique Student IDs if left blank.
   - Delete students with cascade deletion of attendance records.
   - Live search filter (by name or roll number) and status filter chips (All, Present, Absent, Unmarked).

2. **Mark Attendance (Present / Absent)**:
   - Modern segmented toggle buttons for every student.
   - Date selector with **Previous Day**, **Next Day**, and **Today** quick navigation.
   - Instant auto-save to MySQL via background AJAX without reloading the page.
   - Quick **"Mark All Present"** and **"Mark All Absent"** bulk actions.
   - "Force Save All" transaction commit button.

3. **Live Attendance Count & Percentage Tracking**:
   - Dynamic summary cards:
     - **Total Students** count
     - **Present Count** (live)
     - **Absent Count** (live)
     - **Live Attendance Percentage** ($\frac{\text{Present}}{\text{Total}} \times 100$)
   - Circular SVG gauge & animated progress bar with dynamic color indicators:
     - 🟢 **Green (>= 75%)**: Excellent attendance
     - 🟡 **Amber (50% – 74%)**: Average attendance
     - 🔴 **Red (< 50%)**: Low attendance
   - Individual student historical attendance percentages (e.g., 23/25 classes = 92%).

4. **MySQL Database Storage**:
   - Normalized relational schema with foreign key constraints.
   - Auto-creates database (`attendance_db`) and tables (`students`, `attendance`) automatically on first run.
   - Seeds sample demo students and records out of the box.

---

## 📁 File Structure

```
student-attendance-tracker/
├── db.php         # Database connection (PDO) & automatic table initialization
├── index.php      # Main UI dashboard and AJAX JSON API endpoints
├── script.js      # Real-time state management, live percentage calculations & AJAX
├── style.css       # Clean, modern, responsive UI design & animations
├── schema.sql     # Standalone MySQL schema (optional manual import)
└── README.md      # Setup documentation
```

---

## 🛠️ Setup & Running Instructions

### Option 1: Using XAMPP / WAMP / MAMP (Recommended)

1. **Copy or move** the `student-attendance-tracker` folder into your web server root:
   - For XAMPP on Windows: `C:\xampp\htdocs\student-attendance-tracker`
   - For WampServer: `C:\wamp64\www\student-attendance-tracker`
2. **Start Services**:
   - Open the **XAMPP Control Panel**.
   - Start **Apache** and **MySQL**.
3. **Database Setup**:
   - `db.php` is configured to connect to `localhost` with user `root` and empty password `""` (standard XAMPP default).
   - `db.php` **automatically creates** the database `attendance_db` and tables upon your first visit.
   *(Optional)* If you prefer manual SQL setup, import `schema.sql` into phpMyAdmin.
4. **Open in Browser**:
   - Navigate to: `http://localhost/student-attendance-tracker/`

---

### Option 2: Using PHP Built-In Web Server

If PHP and MySQL are installed in your system PATH:

1. Open your terminal / command prompt in this directory:
   ```bash
   cd "c:\Users\Admin\Downloads\student"
   ```
2. Start the built-in PHP server:
   ```bash
   C:\xampp\php\php.exe -S localhost:8000
   ```
3. Open your browser at:
   ```
   http://localhost:8000/
   ```

---

## ⚙️ Configuration (`db.php`)

If your MySQL credentials differ from the defaults, update these constants in [db.php](file:///c:/Users/Admin/Downloads/student/db.php):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // Enter your MySQL password if any
define('DB_NAME', 'attendance_db');
```
