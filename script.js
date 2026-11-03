/**
 * script.js - Student Attendance Tracker Frontend Logic
 * Live calculations, AJAX data sync, filtering & UI interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const attendanceDateInput = document.getElementById('attendanceDate');
    const prevDateBtn = document.getElementById('prevDateBtn');
    const nextDateBtn = document.getElementById('nextDateBtn');
    const todayDateBtn = document.getElementById('todayDateBtn');

    // Stats Elements
    const statTotalStudents = document.getElementById('statTotalStudents');
    const statPresentCount = document.getElementById('statPresentCount');
    const statAbsentCount = document.getElementById('statAbsentCount');
    const statPercentage = document.getElementById('statPercentage');
    const statProgressBar = document.getElementById('statProgressBar');
    const rateStatusBadge = document.getElementById('rateStatusBadge');
    const radialCircle = document.getElementById('radialCircle');
    const statRateDescription = document.getElementById('statRateDescription');

    // Filter & Search Elements
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const filterChips = document.querySelectorAll('.filter-chip');
    const countFilterAll = document.getElementById('countFilterAll');
    const countFilterPresent = document.getElementById('countFilterPresent');
    const countFilterAbsent = document.getElementById('countFilterAbsent');
    const countFilterUnmarked = document.getElementById('countFilterUnmarked');

    // Table & States
    const studentTableBody = document.getElementById('studentTableBody');
    const emptyState = document.getElementById('emptyState');
    const loadingState = document.getElementById('loadingState');
    const showingCountText = document.getElementById('showingCountText');

    // Bulk & Save Actions
    const markAllPresentBtn = document.getElementById('markAllPresentBtn');
    const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
    const saveAllBtn = document.getElementById('saveAllBtn');

    // Modal Elements
    const openAddModalBtn = document.getElementById('openAddModalBtn');
    const closeAddModalBtn = document.getElementById('closeAddModalBtn');
    const cancelAddBtn = document.getElementById('cancelAddBtn');
    const addStudentModal = document.getElementById('addStudentModal');
    const addStudentForm = document.getElementById('addStudentForm');
    const studentNameInput = document.getElementById('studentNameInput');
    const studentRollInput = document.getElementById('studentRollInput');

    // Local State
    let students = [];
    let activeFilter = 'all';
    let searchQuery = '';
    const circumference = 2 * Math.PI * 23; // r = 23 -> 144.51

    // Initialize
    init();

    function init() {
        // Setup initial date if empty
        if (!attendanceDateInput.value) {
            attendanceDateInput.value = getFormattedDate(new Date());
        }

        // Attach event listeners
        bindEvents();

        // Fetch initial attendance data
        fetchAttendanceData();
    }

    function bindEvents() {
        // Date Navigation
        attendanceDateInput.addEventListener('change', () => fetchAttendanceData());
        
        prevDateBtn.addEventListener('click', () => changeDate(-1));
        nextDateBtn.addEventListener('click', () => changeDate(1));
        todayDateBtn.addEventListener('click', () => {
            attendanceDateInput.value = getFormattedDate(new Date());
            fetchAttendanceData();
        });

        // Search Input
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toLowerCase();
            clearSearchBtn.style.display = searchQuery ? 'block' : 'none';
            renderTable();
        });

        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchQuery = '';
            clearSearchBtn.style.display = 'none';
            renderTable();
        });

        // Filter Chips
        filterChips.forEach(chip => {
            chip.addEventListener('click', () => {
                filterChips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                activeFilter = chip.getAttribute('data-filter');
                renderTable();
            });
        });

        // Bulk Actions
        markAllPresentBtn.addEventListener('click', () => bulkMarkAttendance('Present'));
        markAllAbsentBtn.addEventListener('click', () => bulkMarkAttendance('Absent'));
        saveAllBtn.addEventListener('click', () => saveAllAttendance());

        // Modal triggers
        openAddModalBtn.addEventListener('click', openModal);
        closeAddModalBtn.addEventListener('click', closeModal);
        cancelAddBtn.addEventListener('click', closeModal);

        // Close modal on click outside
        addStudentModal.addEventListener('click', (e) => {
            if (e.target === addStudentModal) closeModal();
        });

        // Form Submit
        addStudentForm.addEventListener('submit', handleAddStudent);
    }

    // Date Helpers
    function getFormattedDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function changeDate(deltaDays) {
        const current = new Date(attendanceDateInput.value);
        if (isNaN(current.getTime())) return;
        current.setDate(current.getDate() + deltaDays);
        attendanceDateInput.value = getFormattedDate(current);
        fetchAttendanceData();
    }

    // Modal Control
    function openModal() {
        addStudentModal.classList.add('active');
        studentNameInput.focus();
    }

    function closeModal() {
        addStudentModal.classList.remove('active');
        addStudentForm.reset();
    }

    // Global shortcut for opening modal
    window.openAddStudentModal = openModal;

    // Fetch Attendance Data via AJAX
    function fetchAttendanceData() {
        const date = attendanceDateInput.value;
        loadingState.style.display = 'flex';

        fetch(`index.php?action=fetch&date=${encodeURIComponent(date)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            loadingState.style.display = 'none';
            if (data.success) {
                students = data.students || [];
                updateLiveStats();
                renderTable();
            } else {
                showToast(data.error || 'Failed to fetch attendance data', 'error');
            }
        })
        .catch(err => {
            loadingState.style.display = 'none';
            showToast('Unable to connect to server: ' + err.message, 'error');
        });
    }

    // Real-Time Live Attendance Stats Calculation
    function updateLiveStats() {
        const total = students.length;
        let present = 0;
        let absent = 0;
        let unmarked = 0;

        students.forEach(s => {
            if (s.status === 'Present') present++;
            else if (s.status === 'Absent') absent++;
            else unmarked++;
        });

        // Calculate live attendance percentage
        const percentage = total > 0 ? ((present / total) * 100) : 0;
        const formattedPct = percentage.toFixed(1);

        // Update DOM stats numbers
        statTotalStudents.textContent = total;
        statPresentCount.textContent = present;
        statAbsentCount.textContent = absent;
        statPercentage.textContent = `${formattedPct}%`;

        // Update Filter Badge Counts
        countFilterAll.textContent = total;
        countFilterPresent.textContent = present;
        countFilterAbsent.textContent = absent;
        countFilterUnmarked.textContent = unmarked;

        // Visual Gauge styling & thresholds
        let strokeColor = '#10b981'; // Green >= 75%
        let badgeText = 'Excellent';
        let badgeClass = 'badge-excellent';

        if (percentage < 50) {
            strokeColor = '#ef4444'; // Red < 50%
            badgeText = 'Low';
            badgeClass = 'badge-danger';
        } else if (percentage < 75) {
            strokeColor = '#f59e0b'; // Amber 50-74%
            badgeText = 'Average';
            badgeClass = 'badge-warning';
        }

        // Update Circular SVG Ring
        const offset = circumference - (percentage / 100) * circumference;
        radialCircle.style.strokeDashoffset = offset;
        radialCircle.style.stroke = strokeColor;

        // Update Progress Bar
        statProgressBar.style.width = `${percentage}%`;
        statProgressBar.style.backgroundColor = strokeColor;

        // Update Badge
        rateStatusBadge.className = `badge ${badgeClass}`;
        rateStatusBadge.textContent = badgeText;

        // Context description
        if (total === 0) {
            statRateDescription.textContent = 'No students registered yet';
        } else if (unmarked > 0) {
            statRateDescription.textContent = `${unmarked} student(s) unmarked`;
        } else {
            statRateDescription.textContent = 'All students accounted for';
        }
    }

    // Render Student Table
    function renderTable() {
        studentTableBody.innerHTML = '';

        // Filter students by query and status chip
        const filtered = students.filter(student => {
            const matchesQuery = student.name.toLowerCase().includes(searchQuery) || 
                                 student.roll_number.toLowerCase().includes(searchQuery);

            if (!matchesQuery) return false;

            if (activeFilter === 'all') return true;
            return student.status === activeFilter;
        });

        showingCountText.textContent = `Showing ${filtered.length} of ${students.length} students`;

        if (filtered.length === 0) {
            emptyState.style.display = 'block';
            return;
        } else {
            emptyState.style.display = 'none';
        }

        filtered.forEach(student => {
            const tr = document.createElement('tr');
            
            // Historical percentage visual indicator
            const histPct = student.historical_percentage || 0;
            let histColor = '#10b981';
            if (histPct < 50) histColor = '#ef4444';
            else if (histPct < 75) histColor = '#f59e0b';

            tr.innerHTML = `
                <td>
                    <span class="roll-tag">${escapeHtml(student.roll_number)}</span>
                </td>
                <td>
                    <div class="student-meta">
                        <span class="student-name">${escapeHtml(student.name)}</span>
                        <span class="student-id-sub">Student #${student.id}</span>
                    </div>
                </td>
                <td>
                    <div class="status-switch-group" role="group" aria-label="Attendance for ${escapeHtml(student.name)}">
                        <button type="button" class="status-btn ${student.status === 'Present' ? 'active-present' : ''}" 
                                data-id="${student.id}" data-status="Present" title="Mark Present">
                            <i class="fa-solid fa-check"></i> Present
                        </button>
                        <button type="button" class="status-btn ${student.status === 'Absent' ? 'active-absent' : ''}" 
                                data-id="${student.id}" data-status="Absent" title="Mark Absent">
                            <i class="fa-solid fa-xmark"></i> Absent
                        </button>
                    </div>
                </td>
                <td>
                    <div class="history-wrap">
                        <div class="history-header">
                            <span>${student.present_days || 0}/${student.total_days || 0} classes</span>
                            <span class="history-pct" style="color: ${histColor};">${histPct}%</span>
                        </div>
                        <div class="history-bar-bg">
                            <div class="history-bar-fill" style="width: ${histPct}%; background-color: ${histColor};"></div>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn-delete-row" data-id="${student.id}" data-name="${escapeHtml(student.name)}" title="Delete student">
                        <i class="fa-regular fa-trash-can"></i>
                    </button>
                </td>
            `;

            // Bind status switch click
            const statusBtns = tr.querySelectorAll('.status-btn');
            statusBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const statusToSet = btn.getAttribute('data-status');
                    handleStatusToggle(student.id, statusToSet);
                });
            });

            // Bind delete click
            const delBtn = tr.querySelector('.btn-delete-row');
            delBtn.addEventListener('click', () => {
                const sId = delBtn.getAttribute('data-id');
                const sName = delBtn.getAttribute('data-name');
                handleDeleteStudent(sId, sName);
            });

            studentTableBody.appendChild(tr);
        });
    }

    // Toggle Attendance Status Live & Save to DB
    function handleStatusToggle(studentId, newStatus) {
        const student = students.find(s => s.id === studentId);
        if (!student) return;

        // If clicking the already selected status, we keep it or can let it be
        const oldStatus = student.status;
        student.status = newStatus;

        // Optimistically update live stats & recalculate live attendance percentage
        updateLiveStats();
        renderTable();

        // Send AJAX update to MySQL
        const date = attendanceDateInput.value;
        const formData = new FormData();
        formData.append('action', 'mark_single');
        formData.append('student_id', studentId);
        formData.append('date', date);
        formData.append('status', newStatus);

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update student history counters locally
                if (oldStatus !== newStatus) {
                    if (oldStatus === 'Unmarked') {
                        student.total_days = (student.total_days || 0) + 1;
                        if (newStatus === 'Present') student.present_days = (student.present_days || 0) + 1;
                    } else if (oldStatus === 'Present' && newStatus === 'Absent') {
                        student.present_days = Math.max(0, (student.present_days || 1) - 1);
                    } else if (oldStatus === 'Absent' && newStatus === 'Present') {
                        student.present_days = (student.present_days || 0) + 1;
                    }

                    if (student.total_days > 0) {
                        student.historical_percentage = Math.round((student.present_days / student.total_days) * 1000) / 10;
                    }
                    renderTable();
                }
            } else {
                // Rollback on failure
                student.status = oldStatus;
                updateLiveStats();
                renderTable();
                showToast(data.error || 'Failed to save attendance', 'error');
            }
        })
        .catch(err => {
            student.status = oldStatus;
            updateLiveStats();
            renderTable();
            showToast('Network error: ' + err.message, 'error');
        });
    }

    // Bulk Mark All Students
    function bulkMarkAttendance(status) {
        if (students.length === 0) {
            showToast('No students to mark!', 'warning');
            return;
        }

        // Optimistically mark all in memory
        students.forEach(s => s.status = status);
        updateLiveStats();
        renderTable();

        const date = attendanceDateInput.value;
        const formData = new FormData();
        formData.append('action', 'bulk_mark');
        formData.append('date', date);
        formData.append('status', status);

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(`Marked all ${students.length} students as ${status}`, 'success');
                // Refresh data to keep historical stats synced
                fetchAttendanceData();
            } else {
                showToast(data.error || 'Bulk update failed', 'error');
            }
        })
        .catch(err => {
            showToast('Network error: ' + err.message, 'error');
        });
    }

    // Force Save All Records in Single Transaction
    function saveAllAttendance() {
        if (students.length === 0) {
            showToast('No attendance records to save.', 'warning');
            return;
        }

        const date = attendanceDateInput.value;
        const records = students.map(s => ({
            student_id: s.id,
            status: s.status === 'Absent' ? 'Absent' : 'Present' // default unmarked to present on force save
        }));

        loadingState.style.display = 'flex';

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'save_all',
                date: date,
                records: records
            })
        })
        .then(res => res.json())
        .then(data => {
            loadingState.style.display = 'none';
            if (data.success) {
                showToast('All attendance records saved successfully!', 'success');
                fetchAttendanceData();
            } else {
                showToast(data.error || 'Failed to save attendance', 'error');
            }
        })
        .catch(err => {
            loadingState.style.display = 'none';
            showToast('Save failed: ' + err.message, 'error');
        });
    }

    // Add New Student
    function handleAddStudent(e) {
        e.preventDefault();
        const name = studentNameInput.value.trim();
        const roll = studentRollInput.value.trim();

        if (!name) {
            showToast('Student name is required', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_student');
        formData.append('name', name);
        if (roll) formData.append('roll_number', roll);

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Student added successfully!', 'success');
                closeModal();
                fetchAttendanceData();
            } else {
                showToast(data.error || 'Could not add student', 'error');
            }
        })
        .catch(err => {
            showToast('Error: ' + err.message, 'error');
        });
    }

    // Delete Student
    function handleDeleteStudent(studentId, studentName) {
        if (!confirm(`Are you sure you want to delete student "${studentName}"? All their attendance records will be removed.`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_student');
        formData.append('id', studentId);

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Student deleted', 'success');
                students = students.filter(s => s.id !== parseInt(studentId));
                updateLiveStats();
                renderTable();
            } else {
                showToast(data.error || 'Could not delete student', 'error');
            }
        })
        .catch(err => {
            showToast('Network error: ' + err.message, 'error');
        });
    }

    // Toast Notifications
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let iconClass = 'fa-check-circle';
        if (type === 'error') iconClass = 'fa-circle-exclamation';
        if (type === 'warning') iconClass = 'fa-triangle-exclamation';

        toast.innerHTML = `
            <i class="fa-solid ${iconClass} toast-icon"></i>
            <span>${escapeHtml(message)}</span>
        `;

        toastContainer.appendChild(toast);

        // Auto remove after 3.5s
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(15px)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toastContainer.removeChild(toast);
                }
            }, 300);
        }, 3500);
    }

    // HTML Escape Helper
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
