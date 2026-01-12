<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
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

// Fetch events with filters
$where_conditions = [];
$params = [];
$param_types = "";

if (isset($_GET['academic_year_id']) && !empty($_GET['academic_year_id'])) {
    $where_conditions[] = "e.academic_year_id = ?";
    $params[] = $_GET['academic_year_id'];
    $param_types .= "i";
}

if (isset($_GET['term_id']) && !empty($_GET['term_id'])) {
    $where_conditions[] = "e.term_id = ?";
    $params[] = $_GET['term_id'];
    $param_types .= "i";
}

if (isset($_GET['event_type']) && !empty($_GET['event_type'])) {
    $where_conditions[] = "e.event_type = ?";
    $params[] = $_GET['event_type'];
    $param_types .= "s";
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $where_conditions[] = "e.event_date >= ?";
    $params[] = $_GET['date_from'];
    $param_types .= "s";
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $where_conditions[] = "e.event_date <= ?";
    $params[] = $_GET['date_to'];
    $param_types .= "s";
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

$sql = "SELECT e.*, ay.year_name, t.term_name 
        FROM events e 
        LEFT JOIN academic_years ay ON e.academic_year_id = ay.id 
        LEFT JOIN terms t ON e.term_id = t.id 
        $where_sql 
        ORDER BY e.event_date DESC, e.start_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$event_result = $stmt->get_result();

while ($row = $event_result->fetch_assoc()) {
    $events[] = $row;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events List - GEBSCO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <link rel="stylesheet" type="text/css" href="css/events.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
    <style>
        .events-list-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .events-list-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .events-list-title {
            margin: 0;
            font-size: 24px;
        }

        .view-switcher {
            display: flex;
            gap: 10px;
        }

        .events-table {
            width: 100%;
            border-collapse: collapse;
        }

        .events-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .events-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
        }

        .events-table tr:hover {
            background: #f8f9fa;
        }

        .event-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .event-academic { background: #e3f2fd; color: #1976d2; }
        .event-sports { background: #e8f5e8; color: #388e3c; }
        .event-cultural { background: #fce4ec; color: #c2185b; }
        .event-holiday { background: #fff3e0; color: #f57c00; }
        .event-meeting { background: #f3e5f5; color: #7b1fa2; }
        .event-examination { background: #ffebee; color: #d32f2f; }
        .event-other { background: #f5f5f5; color: #616161; }

        .event-date {
            font-weight: 600;
            color: #2c3e50;
        }

        .event-time {
            font-size: 12px;
            color: #6c757d;
        }

        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .event-description {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.4;
        }

        .event-location {
            font-size: 14px;
            color: #6c757d;
        }

        .event-actions {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .no-events {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-events i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .filter-advanced {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }

        .filter-advanced.show {
            display: block;
        }

        .date-range-filters {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .toggle-advanced {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }

        .toggle-advanced:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .events-table {
                display: block;
                overflow-x: auto;
            }
            
            .events-table th,
            .events-table td {
                padding: 10px;
                font-size: 14px;
            }
            
            .event-actions {
                flex-direction: column;
            }
            
            .date-range-filters {
                grid-template-columns: 1fr;
            }
            
            .events-list-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
                <h1><i class="fas fa-list"></i> Events List View</h1>
                <nav class="breadcrumb">
                    <a href="index.php">Home</a> > <a href="event.php">Calendar</a> > List View
                </nav>
            </div>
            
            <!-- Filter Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Events</h3>
                </div>
                <div class="card-body">
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="academic_year_id">Academic Year</label>
                                <select name="academic_year_id" id="academic_year_id">
                                    <option value="">All Academic Years</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?= $year['id'] ?>" 
                                            <?= (isset($_GET['academic_year_id']) && $_GET['academic_year_id'] == $year['id']) || 
                                                (!isset($_GET['academic_year_id']) && isset($current_academic_year) && $current_academic_year['id'] == $year['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['year_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="term_id">Term</label>
                                <select name="term_id" id="term_id">
                                    <option value="">All Terms</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?= $term['id'] ?>" 
                                            <?= (isset($_GET['term_id']) && $_GET['term_id'] == $term['id']) || 
                                                (!isset($_GET['term_id']) && isset($current_term) && $current_term['id'] == $term['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($term['term_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="event_type">Event Type</label>
                                <select name="event_type" id="event_type">
                                    <option value="">All Event Types</option>
                                    <?php foreach ($event_types as $key => $value): ?>
                                        <option value="<?= $key ?>" <?= (isset($_GET['event_type']) && $_GET['event_type'] == $key) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($value) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Advanced Filters -->
                        <button type="button" class="toggle-advanced" onclick="toggleAdvancedFilters()">
                            <i class="fas fa-cog"></i> Advanced Filters
                        </button>
                        
                        <div class="filter-advanced" id="advancedFilters">
                            <div class="date-range-filters">
                                <div class="filter-group">
                                    <label for="date_from">From Date</label>
                                    <input type="date" id="date_from" name="date_from" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="date_to">To Date</label>
                                    <input type="date" id="date_to" name="date_to" value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="events_list.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Events List -->
            <div class="events-list-container">
                <div class="events-list-header">
                    <h2 class="events-list-title">
                        <i class="fas fa-calendar-list"></i> Events (<?= count($events) ?>)
                    </h2>
                    <div class="view-switcher">
                        <a href="event.php" class="btn btn-light">
                            <i class="fas fa-calendar-alt"></i> Calendar View
                        </a>
                        <button class="btn btn-success" onclick="openEventModal()">
                            <i class="fas fa-plus"></i> Add New Event
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (count($events) > 0): ?>
                        <div class="table-responsive">
                            <table class="events-table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Event</th>
                                        <th>Type</th>
                                        <th>Academic Year</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td>
                                                <div class="event-date">
                                                    <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                                </div>
                                                <div class="event-time">
                                                    <?= date('g:i A', strtotime($event['start_time'])) ?>
                                                    <?php if (!empty($event['end_time'])): ?>
                                                        - <?= date('g:i A', strtotime($event['end_time'])) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="event-title"><?= htmlspecialchars($event['event_title']) ?></div>
                                                <?php if (!empty($event['description'])): ?>
                                                    <div class="event-description">
                                                        <?= nl2br(htmlspecialchars(substr($event['description'], 0, 100))) ?>
                                                        <?= strlen($event['description']) > 100 ? '...' : '' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="event-type-badge event-<?= strtolower($event['event_type']) ?>">
                                                    <?= $event['event_type'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($event['year_name']) ?><br>
                                                <small class="event-time"><?= htmlspecialchars($event['term_name']) ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($event['location'])): ?>
                                                    <div class="event-location">
                                                        <i class="fas fa-map-marker-alt"></i> 
                                                        <?= htmlspecialchars($event['location']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="event-time">Not specified</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="event-actions">
                                                    <button class="btn btn-primary btn-sm" onclick="editEvent(<?= $event['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteEvent(<?= $event['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-events">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Events Found</h3>
                            <p>No events match your current filters. Try adjusting your filters or add new events.</p>
                            <button class="btn btn-success" onclick="openEventModal()" style="margin-top: 15px;">
                                <i class="fas fa-plus"></i> Add Your First Event
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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
        function toggleAdvancedFilters() {
            const advancedFilters = document.getElementById('advancedFilters');
            advancedFilters.classList.toggle('show');
        }

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

        // Auto-close advanced filters if date range is set
        <?php if (isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('advancedFilters').classList.add('show');
            });
        <?php endif; ?>
    </script>
</body>
</html>