<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// Get current month and year for calendar display
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch data from database
$academic_years = [];
$terms = [];
$events = [];

// Fetch academic years
$year_result = $conn->query("SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC");
while ($row = $year_result->fetch_assoc()) {
    $academic_years[] = $row;
}

// Fetch terms
$term_result = $conn->query("SELECT id, term_name, term_order FROM terms ORDER BY term_order");
while ($row = $term_result->fetch_assoc()) {
    $terms[] = $row;
}

// Fetch events for the current month
$first_day_of_month = date('Y-m-01', strtotime("$current_year-$current_month-01"));
$last_day_of_month = date('Y-m-t', strtotime("$current_year-$current_month-01"));

$sql = "SELECT e.*, ay.year_name, t.term_name 
        FROM events e 
        LEFT JOIN academic_years ay ON e.academic_year_id = ay.id 
        LEFT JOIN terms t ON e.term_id = t.id 
        WHERE e.event_date BETWEEN ? AND ?
        ORDER BY e.event_date, e.start_time";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $first_day_of_month, $last_day_of_month);
$stmt->execute();
$event_result = $stmt->get_result();

$events_by_date = [];
while ($row = $event_result->fetch_assoc()) {
    $event_date = $row['event_date'];
    if (!isset($events_by_date[$event_date])) {
        $events_by_date[$event_date] = [];
    }
    $events_by_date[$event_date][] = $row;
}

// Event types for filter dropdown
$event_types = [
    'Academic' => 'Academic',
    'Sports' => 'Sports',
    'Cultural' => 'Cultural',
    'Holiday' => 'Holiday',
    'Meeting' => 'Meeting',
    'Examination' => 'Examination',
    'Other' => 'Other'
];

// Get current academic year and term for default filter values
$current_academic_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_term = $conn->query("SELECT id FROM terms WHERE id = 1 LIMIT 1")->fetch_assoc();

// Calendar calculations
$first_day_timestamp = strtotime("$current_year-$current_month-01");
$days_in_month = date('t', $first_day_timestamp);
$first_day_of_week = date('N', $first_day_timestamp); // 1=Monday, 7=Sunday

// Previous and next month navigation
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month == 0) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month == 13) {
    $next_month = 1;
    $next_year++;
}

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
     <link rel="stylesheet" type="text/css" href="css/events.css">
    <style>
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-nav button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .calendar-nav button:hover {
            background: rgba(255,255,255,0.3);
        }

        .calendar-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .calendar-year {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
        }

        .calendar-day-header {
            background: #f5f5f5;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            border-bottom: 2px solid #ddd;
        }

        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 10px;
            border: 1px solid #e0e0e0;
            position: relative;
            transition: background 0.3s;
        }

        .calendar-day:hover {
            background: #f9f9f9;
        }

        .calendar-day.other-month {
            background: #f8f8f8;
            color: #999;
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .event-badge {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 3px;
        }

        .event-item {
            font-size: 11px;
            margin: 2px 0;
            padding: 3px 5px;
            border-radius: 3px;
            background: #f0f0f0;
            cursor: pointer;
            transition: all 0.3s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-item:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .event-academic { background: #e3f2fd; border-left: 3px solid #2196f3; }
        .event-sports { background: #e8f5e8; border-left: 3px solid #4caf50; }
        .event-cultural { background: #fce4ec; border-left: 3px solid #e91e63; }
        .event-holiday { background: #fff3e0; border-left: 3px solid #ff9800; }
        .event-meeting { background: #f3e5f5; border-left: 3px solid #9c27b0; }
        .event-examination { background: #ffebee; border-left: 3px solid #f44336; }
        .event-other { background: #f5f5f5; border-left: 3px solid #757575; }

        .event-time {
            font-size: 9px;
            color: #666;
            margin-right: 3px;
        }

        .event-popup {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            min-width: 250px;
            display: none;
        }

        .event-popup h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .event-popup .event-details {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .event-popup .event-description {
            font-size: 12px;
            color: #333;
        }

        @media (max-width: 768px) {
            .calendar-day {
                min-height: 80px;
                padding: 5px;
            }
            
            .event-item {
                font-size: 9px;
                padding: 2px 3px;
            }
            
            .day-number {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-calendar-alt"></i> Academic Calendar & Event Management</h1>
                <nav class="breadcrumb">
                    <a href="index.php">Home</a> > <a href="#">Academic</a> > Calendar & Events
                </nav>
            </div>
            
            <!-- Calendar Navigation -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-light">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <div>
                            <h2 class="calendar-title"><?= $month_names[$current_month] ?></h2>
                            <div class="calendar-year"><?= $current_year ?></div>
                        </div>
                        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-light">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <!-- Day headers -->
                    <div class="calendar-day-header">MONDAY</div>
                    <div class="calendar-day-header">TUESDAY</div>
                    <div class="calendar-day-header">WEDNESDAY</div>
                    <div class="calendar-day-header">THURSDAY</div>
                    <div class="calendar-day-header">FRIDAY</div>
                    <div class="calendar-day-header">SATURDAY</div>
                    <div class="calendar-day-header">SUNDAY</div>
                    
                    <!-- Empty days for the first week -->
                    <?php for ($i = 1; $i < $first_day_of_week; $i++): ?>
                        <div class="calendar-day other-month"></div>
                    <?php endfor; ?>
                    
                    <!-- Days of the month -->
                    <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                        <?php
                        $current_date = date('Y-m-d', strtotime("$current_year-$current_month-$day"));
                        $is_today = ($current_date == date('Y-m-d'));
                        $day_events = isset($events_by_date[$current_date]) ? $events_by_date[$current_date] : [];
                        ?>
                        
                        <div class="calendar-day <?= $is_today ? 'today' : '' ?>">
                            <div class="day-number"><?= $day ?></div>
                            
                            <?php foreach ($day_events as $event): ?>
                                <div class="event-item event-<?= strtolower($event['event_type']) ?>" 
                                     onclick="showEventPopup(<?= htmlspecialchars(json_encode($event)) ?>, this)">
                                    <span class="event-time">
                                        <?= date('g:i', strtotime($event['start_time'])) ?>
                                    </span>
                                    <?= htmlspecialchars($event['event_title']) ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($day_events) > 2): ?>
                                <div class="event-item event-more" onclick="showAllEvents('<?= $current_date ?>', this)">
                                    +<?= count($day_events) - 2 ?> more events
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Start new row after Sunday -->
                        <?php if (($first_day_of_week + $day - 1) % 7 == 0 && $day != $days_in_month): ?>
                            </div><div class="calendar-grid">
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- Empty days for the last week -->
                    <?php
                    $last_day_of_week = date('N', strtotime("$current_year-$current_month-$days_in_month"));
                    $empty_days_end = 7 - $last_day_of_week;
                    for ($i = 0; $i < $empty_days_end; $i++): ?>
                        <div class="calendar-day other-month"></div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons" style="margin: 20px 0; text-align: center;">
                <button class="btn btn-success" onclick="openEventModal()">
                    <i class="fas fa-plus"></i> Add New Event
                </button>
                <a href="events_list.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> List View
                </a>
            </div>
        </div>
    </div>

    <!-- Event Popup Modal -->
    <div id="eventPopup" class="event-popup"></div>

    <!-- Add/Edit Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Event</h2>
                <span class="close" onclick="closeEventModal()">&times;</span>
            </div>
            <form id="eventForm" method="POST" action="save_event.php">
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="event_id">
                    
                    <div class="form-group">
                        <label for="event_title">Event Title *</label>
                        <input type="text" id="event_title" name="event_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_type">Event Type *</label>
                        <select id="event_type" name="event_type" required>
                            <option value="">Select Event Type</option>
                            <?php foreach ($event_types as $key => $value): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date">Event Date *</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="e.g., School Hall, Football Field">
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year_id">Academic Year *</label>
                        <select id="academic_year_id" name="academic_year_id" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= $year['is_current'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['year_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="term_id">Term *</label>
                        <select id="term_id" name="term_id" required>
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?= $term['id'] ?>"><?= htmlspecialchars($term['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Provide details about the event..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/darkmode.js"></script>
    <script>
        let currentPopup = null;

        function showEventPopup(event, element) {
            // Close any existing popup
            if (currentPopup) {
                currentPopup.remove();
            }

            const popup = document.createElement('div');
            popup.className = 'event-popup';
            popup.style.display = 'block';
            
            // Position the popup
            const rect = element.getBoundingClientRect();
            popup.style.left = (rect.left + window.scrollX) + 'px';
            popup.style.top = (rect.bottom + window.scrollY + 5) + 'px';

            // Popup content
            popup.innerHTML = `
                <h4>${event.event_title}</h4>
                <div class="event-details">
                    <div><i class="fas fa-calendar"></i> ${event.event_date}</div>
                    <div><i class="fas fa-clock"></i> ${event.start_time} ${event.end_time ? ' - ' + event.end_time : ''}</div>
                    ${event.location ? `<div><i class="fas fa-map-marker-alt"></i> ${event.location}</div>` : ''}
                    <div><i class="fas fa-tag"></i> ${event.event_type}</div>
                </div>
                ${event.description ? `<div class="event-description">${event.description}</div>` : ''}
                <div style="margin-top: 10px;">
                    <button class="btn btn-primary btn-sm" onclick="editEvent(${event.id})">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteEvent(${event.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            `;

            document.body.appendChild(popup);
            currentPopup = popup;

            // Close popup when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function closePopup(e) {
                    if (!popup.contains(e.target) && e.target !== element) {
                        popup.remove();
                        document.removeEventListener('click', closePopup);
                        currentPopup = null;
                    }
                });
            }, 100);
        }

        function showAllEvents(date, element) {
            // Implement showing all events for a specific date
            alert('Showing all events for ' + date);
            // You can implement a modal or redirect to a detailed view
        }

        // Existing modal functions
        function openEventModal() {
            document.getElementById('eventModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Add New Event';
            document.getElementById('eventForm').reset();
            document.getElementById('event_id').value = '';
        }
        
        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        function editEvent(eventId) {
            // Close popup if open
            if (currentPopup) {
                currentPopup.remove();
                currentPopup = null;
            }

            // Fetch event data and populate form
            fetch('get_event.php?id=' + eventId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const event = data.event;
                        document.getElementById('modalTitle').textContent = 'Edit Event';
                        document.getElementById('event_id').value = event.id;
                        document.getElementById('event_title').value = event.event_title;
                        document.getElementById('event_type').value = event.event_type;
                        document.getElementById('event_date').value = event.event_date;
                        document.getElementById('start_time').value = event.start_time;
                        document.getElementById('end_time').value = event.end_time;
                        document.getElementById('location').value = event.location;
                        document.getElementById('academic_year_id').value = event.academic_year_id;
                        document.getElementById('term_id').value = event.term_id;
                        document.getElementById('description').value = event.description;
                        
                        document.getElementById('eventModal').style.display = 'block';
                    } else {
                        alert('Error loading event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading event data');
                });
        }
        
        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                fetch('delete_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + eventId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Event deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting event');
                });
            }
        }

        // Initialize date picker
        flatpickr("#event_date", {
            dateFormat: "Y-m-d",
            minDate: "today"
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('eventModal');
            if (event.target === modal) {
                closeEventModal();
            }
        }

        // Form submission
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('save_event.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Event saved successfully');
                    closeEventModal();
                    location.reload();
                } else {
                    alert('Error saving event: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving event');
            });
        });
    </script>
    <script src="js/pwa.js"></script>
</body>
</html>