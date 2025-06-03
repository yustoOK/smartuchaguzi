<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

$host = 'localhost';
$dbname = 'smartuchaguzi_db';
$username = 'root';
$password = 'Leonida1972@@@@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=' . urlencode('Please log in as admin.'));
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$elections = [];
$colleges = [];
$hostels = [];

try {
    $stmt = $pdo->query("
        SELECT 
            e.election_id, 
            e.title, 
            e.association, 
            CASE 
                WHEN COUNT(DISTINCT ep.college_id) > 1 THEN 'Multiple Colleges'
                WHEN COUNT(ep.college_id) = 0 THEN 'N/A'
                ELSE MAX(c.name)
            END AS college_name, 
            e.start_time, 
            e.end_time, 
            e.description, 
            e.status,
            COALESCE((SELECT COUNT(*) FROM candidates ca WHERE ca.election_id = e.election_id), 0) AS candidate_count
        FROM elections e 
        LEFT JOIN electionpositions ep ON e.election_id = ep.election_id 
        LEFT JOIN colleges c ON ep.college_id = c.college_id 
        GROUP BY e.election_id, e.title, e.association, e.start_time, e.end_time, e.description, e.status 
        ORDER BY e.created_at DESC"
    );
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections.";
}

try {
    $colleges = $pdo->query("SELECT college_id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $hostels = $pdo->query("SELECT hostel_id, name, college_id FROM hostels ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch colleges/hostels failed: " . $e->getMessage());
    $errors[] = "Failed to load colleges or hostels.";
}

// Handle delete election
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_election']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);

    if ($election_id) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM electionpositions WHERE election_id = ?");
            $stmt->execute([$election_id]);
            $stmt = $pdo->prepare("DELETE FROM elections WHERE election_id = ?");
            if ($stmt->execute([$election_id])) {
                $pdo->commit();
                $success = "Election deleted successfully.";
                $stmt = $pdo->query("
                    SELECT 
                        e.election_id, 
                        e.title, 
                        e.association, 
                        CASE 
                            WHEN COUNT(DISTINCT ep.college_id) > 1 THEN 'Multiple Colleges'
                            WHEN COUNT(ep.college_id) = 0 THEN 'N/A'
                            ELSE MAX(c.name)
                        END AS college_name, 
                        e.start_time, 
                        e.end_time, 
                        e.description, 
                        e.status,
                        COALESCE((SELECT COUNT(*) FROM candidates ca WHERE ca.election_id = e.election_id), 0) AS candidate_count
                    FROM elections e 
                    LEFT JOIN electionpositions ep ON e.election_id = ep.election_id 
                    LEFT JOIN colleges c ON ep.college_id = c.college_id 
                    GROUP BY e.election_id, e.title, e.association, e.start_time, e.end_time, e.description, e.status 
                    ORDER BY e.created_at DESC"
                );
                $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $pdo->rollBack();
                error_log("Delete election failed: " . $stmt->errorInfo()[2]);
                $errors[] = "Failed to delete election due to a server error.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete election failed: " . $e->getMessage());
            $errors[] = "Failed to delete election due to a server error.";
        }
    } else {
        $errors[] = "Invalid election selected for deletion.";
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_election']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $positions = $_POST['positions'] ?? [];
    $delete_positions = $_POST['delete_positions'] ?? [];

    if (!$election_id) {
        $errors[] = "Invalid election selected.";
    }
    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start and end times are required.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = "End time must be after start time.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE elections SET start_time = ?, end_time = ?, description = ? WHERE election_id = ?"
            );
            $stmt->execute([$start_time, $end_time, $description, $election_id]);

            if (!empty($delete_positions)) {
                $placeholders = implode(',', array_fill(0, count($delete_positions), '?'));
                $stmt = $pdo->prepare("DELETE FROM electionpositions WHERE position_id IN ($placeholders)");
                $stmt->execute($delete_positions);
            }

            foreach ($positions as $index => $position) {
                $name = filter_var($position['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $college_id = !empty($position['college_id']) ? filter_var($position['college_id'], FILTER_VALIDATE_INT) : null;
                $hostel_id = !empty($position['hostel_id']) ? filter_var($position['hostel_id'], FILTER_VALIDATE_INT) : null;
                $scope = $position['scope'] ?? 'university';
                $position_id = isset($position['id']) ? filter_var($position['id'], FILTER_VALIDATE_INT) : null;

                if (empty($name)) {
                    $errors[] = "Position name is required.";
                    break;
                }

                if ($scope === 'university') {
                    $college_id = null;
                    $hostel_id = null;
                } elseif ($scope === 'college') {
                    if (!$college_id) {
                        $errors[] = "College is required for college-scoped position.";
                        break;
                    }
                    $hostel_id = null;
                } elseif ($scope === 'hostel') {
                    if (!$college_id || !$hostel_id) {
                        $errors[] = "College and hostel are required for hostel-scoped position.";
                        break;
                    }
                    // Validate hostel belongs to the selected college
                    $stmt = $pdo->prepare("SELECT college_id FROM hostels WHERE hostel_id = ?");
                    $stmt->execute([$hostel_id]);
                    $hostel_college_id = $stmt->fetchColumn();
                    if ($hostel_college_id != $college_id) {
                        $errors[] = "Selected hostel does not belong to the selected college.";
                        break;
                    }
                }

                if ($position_id) {
                    $stmt = $pdo->prepare(
                        "UPDATE electionpositions SET name = ?, college_id = ?, hostel_id = ?, scope = ? WHERE position_id = ?"
                    );
                    $stmt->execute([$name, $college_id, $hostel_id, $scope, $position_id]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO electionpositions (election_id, name, college_id, hostel_id, scope, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([$election_id, $name, $college_id, $hostel_id, $scope]);
                }
            }

            if (empty($errors)) {
                $pdo->commit();
                $success = "Election updated successfully.";
                $stmt = $pdo->query("
                    SELECT 
                        e.election_id, 
                        e.title, 
                        e.association, 
                        CASE 
                            WHEN COUNT(DISTINCT ep.college_id) > 1 THEN 'Multiple Colleges'
                            WHEN COUNT(ep.college_id) = 0 THEN 'N/A'
                            ELSE MAX(c.name)
                        END AS college_name, 
                        e.start_time, 
                        e.end_time, 
                        e.description, 
                        e.status,
                        COALESCE((SELECT COUNT(*) FROM candidates ca WHERE ca.election_id = e.election_id), 0) AS candidate_count
                    FROM elections e 
                    LEFT JOIN electionpositions ep ON e.election_id = ep.election_id 
                    LEFT JOIN colleges c ON ep.college_id = c.college_id 
                    GROUP BY e.election_id, e.title, e.association, e.start_time, e.end_time, e.description, e.status 
                    ORDER BY e.created_at DESC"
                );
                $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update election failed: " . $e->getMessage());
            $errors[] = "Failed to update election due to a server error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections: Edit & Delete</title>
    <link rel="icon" href="../images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, rgba(26, 60, 52, 0.8), rgba(34, 78, 68, 0.8)), url('images/cive.jpeg');
            background-size: cover;
            background-attachment: fixed;
            color: #2d3748;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 15px;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
            position: relative;
        }
        .container:hover {
            transform: translateY(-5px);
        }
        h2 {
            text-align: center;
            color: #1a3c34;
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: 600;
        }
        .search-bar {
            position: absolute;
            top: 25px;
            right: 15px;
            width: 250px;
            display: flex;
            align-items: center;
        }
        .search-bar input {
            width: 100%;
            padding: 8px 12px 8px 30px;
            border: 1px solid #e0e7ea;
            border-radius: 20px;
            font-size: 14px;
            background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>') no-repeat 10px center;
            background-size: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .search-bar input:focus {
            outline: none;
            border-color: #2a9d8f;
            box-shadow: 0 0 6px rgba(42, 157, 143, 0.4);
        }
        .breadcrumb {
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        .breadcrumb a {
            color: #2a9d8f;
            text-decoration: none;
            margin-right: 6px;
            transition: color 0.3s ease;
        }
        .breadcrumb a:hover {
            color: #207b6e;
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #2d3748;
            margin-right: 6px;
        }
        .breadcrumb i {
            margin: 0 6px;
            color: #2d3748;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #e0e7ea;
            text-align: left;
            font-size: 13px;
        }
        th {
            background: #2a9d8f;
            color: #fff;
            font-weight: 600;
        }
        td:nth-child(2) { /* Title column */
            max-width: 200px;
            word-wrap: break-word;
            word-break: break-all;
        }
        tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.5);
        }
        tr:hover {
            background: rgba(42, 157, 143, 0.1);
        }
        .edit-btn {
            background: #2a9d8f;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-right: 5px;
        }
        .edit-btn:hover {
            background: #207b6e;
            transform: translateY(-2px);
        }
        .delete-btn {
            background: #d00000;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .delete-btn:hover {
            background: #a00000;
            transform: translateY(-2px);
        }
        .error {
            color: #d00000;
            margin-bottom: 15px;
            font-size: 14px;
            padding: 8px;
            background: #fee2e2;
            border-radius: 6px;
        }
        .success {
            color: #2a9d8f;
            margin-bottom: 15px;
            font-size: 14px;
            padding: 8px;
            background: #d1fae5;
            border-radius: 6px;
        }
        .no-elections {
            text-align: center;
            font-size: 16px;
            color: #6b7280;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e0e7ea;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            margin: 40px auto;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
        }
        .modal-content h3 {
            margin-top: 0;
            color: #1a3c34;
            font-size: 18px;
        }
        .modal-content .form-group {
            margin-bottom: 15px;
        }
        .modal-content input, .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #e0e7ea;
            border-radius: 6px;
            font-size: 14px;
        }
        .modal-content input:focus, .modal-content select:focus, .modal-content textarea:focus {
            outline: none;
            border-color: #2a9d8f;
            box-shadow: 0 0 6px rgba(42, 157, 143, 0.4);
        }
        .modal-content button {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-right: 10px;
        }
        .modal-content .submit-btn {
            background: #2a9d8f;
            color: #fff;
        }
        .modal-content .submit-btn:hover {
            background: #207b6e;
        }
        .modal-content .cancel-btn {
            background: #e2e8f0;
            color: #1a3c34;
        }
        .modal-content .cancel-btn:hover {
            background: #d1d5db;
        }
        .modal-content .delete-checkbox {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #d00000;
        }
        @media (max-width: 768px) {
            .container {
                padding: 15px 10px;
                margin: 15px 5px;
            }
            .search-bar {
                top: 15px;
                right: 10px;
                width: 200px;
            }
            .search-bar input {
                padding: 6px 10px 6px 25px;
                background-size: 14px;
                font-size: 12px;
            }
            h2 {
                font-size: 20px;
            }
            table, th, td {
                font-size: 12px;
                padding: 6px;
            }
            td:nth-child(2) { /* Title column */
                max-width: 150px;
            }
            .edit-btn, .delete-btn {
                font-size: 10px;
                padding: 4px 10px;
            }
            .modal-content {
                margin: 20px;
                padding: 15px;
            }
        }
        @media (max-width: 480px) {
            .container {
                margin: 10px 3px;
                padding: 10px 5px;
            }
            .search-bar {
                top: 10px;
                right: 5px;
                width: 150px;
            }
            .search-bar input {
                padding: 5px 8px 5px 20px;
                background-size: 12px;
                font-size: 10px;
            }
            h2 {
                font-size: 18px;
            }
            table, th, td {
                font-size: 10px;
                padding: 4px;
            }
            td:nth-child(2) { /* Title column */
                max-width: 100px;
            }
            .edit-btn, .delete-btn {
                font-size: 8px;
                padding: 3px 8px;
            }
            .modal-content h3 {
                font-size: 16px;
            }
            .modal-content button {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="../index.html">Home</a> <i class="fas fa-chevron-right"></i>
            <a href="../admin-dashboard.php">Admin</a> <i class="fas fa-chevron-right"></i>
            <span>Manage Elections</span>
        </div>
        <h2>Manage Elections</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search elections..." onkeyup="filterTable()">
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        <?php if (empty($elections)): ?>
            <p class="no-elections">No elections available to manage. Please add an election using the <a href="add-election.php" style="color: #2a9d8f;">Add Election</a> page.</p>
        <?php else: ?>
            <table id="electionTable">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Association</th>
                    <th>College</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Candidates</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($elections as $election): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($election['election_id']); ?></td>
                        <td><?php echo htmlspecialchars($election['title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($election['association']); ?></td>
                        <td><?php echo htmlspecialchars($election['college_name']); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['start_time']))); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($election['end_time']))); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($election['status'])); ?></td>
                        <td><?php echo htmlspecialchars($election['candidate_count']); ?></td>
                        <td>
                            <button class="edit-btn" onclick="editElection(<?php echo $election['election_id']; ?>, '<?php echo addslashes($election['title'] ?? ''); ?>', '<?php echo addslashes($election['association']); ?>', '<?php echo addslashes($election['college_name']); ?>', '<?php echo addslashes($election['start_time']); ?>', '<?php echo addslashes($election['end_time']); ?>', '<?php echo addslashes($election['description'] ?? ''); ?>')">Edit</button>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="delete_election" value="1">
                                <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this election? This action cannot be undone.');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <h3>Edit Election</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="edit_election" value="1">
                <input type="hidden" name="election_id" id="edit-election-id">
                <div class="form-group">
                    <label for="edit-title">Title</label>
                    <input type="text" name="title" id="edit-title" disabled>
                </div>
                <div class="form-group">
                    <label for="edit-association">Association</label>
                    <input type="text" name="association" id="edit-association" disabled>
                </div>
                <div class="form-group">
                    <label for="edit-college">College</label>
                    <input type="text" name="college" id="edit-college" disabled>
                </div>
                <div class="form-group">
                    <label for="edit-start_time">Start Time</label>
                    <input type="datetime-local" name="start_time" id="edit-start_time" required>
                </div>
                <div class="form-group">
                    <label for="edit-end_time">End Time</label>
                    <input type="datetime-local" name="end_time" id="edit-end_time" required>
                </div>
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea name="description" id="edit-description" rows="3" required></textarea>
                </div>
                <div id="edit-positions"></div>
                <div class="form-group">
                    <button type="button" onclick="addPosition()">Add New Position</button>
                </div>
                <button type="submit" class="submit-btn">Update Election</button>
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
    <script>
        let positionCount = 0;
        const hostelsByCollege = <?php echo json_encode(array_reduce($hostels, function($carry, $hostel) {
            $carry[$hostel['college_id']][] = $hostel;
            return $carry;
        }, [])); ?>;

        function editElection(id, title, association, college, start_time, end_time, description) {
            document.getElementById('edit-election-id').value = id;
            document.getElementById('edit-title').value = title || 'N/A';
            document.getElementById('edit-association').value = association;
            document.getElementById('edit-college').value = college;
            document.getElementById('edit-start_time').value = start_time.replace(' ', 'T');
            document.getElementById('edit-end_time').value = end_time.replace(' ', 'T');
            document.getElementById('edit-description').value = description || '';

            const positionsDiv = document.getElementById('edit-positions');
            positionsDiv.innerHTML = '';
            fetchPositions(id);

            document.getElementById('edit-modal').style.display = 'block';
        }

        function fetchPositions(electionId) {
            fetch(`get_positions.php?election_id=${electionId}`)
                .then(response => response.json())
                .then(positions => {
                    const positionsDiv = document.getElementById('edit-positions');
                    positions.forEach((position, index) => {
                        const div = document.createElement('div');
                        div.className = 'form-group';
                        div.innerHTML = `
                            <label>Position ${index + 1}</label>
                            <input type="hidden" name="positions[${index}][id]" value="${position.position_id}">
                            <input type="text" name="positions[${index}][name]" value="${position.name}" required>
                            <select name="positions[${index}][scope]" onchange="togglePositionFields(this)">
                                <option value="university" ${position.scope === 'university' ? 'selected' : ''}>University</option>
                                <option value="college" ${position.scope === 'college' ? 'selected' : ''}>College</option>
                                <option value="hostel" ${position.scope === 'hostel' ? 'selected' : ''}>Hostel</option>
                            </select>
                            <select name="positions[${index}][college_id]" class="college-field" ${position.scope === 'university' ? 'disabled' : ''} onchange="updateHostelOptions(this)">
                                <option value="">Select College</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?php echo $college['college_id']; ?>" ${position.college_id == <?php echo $college['college_id']; ?> ? 'selected' : ''}>
                                        <?php echo htmlspecialchars($college['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="positions[${index}][hostel_id]" class="hostel-field" ${position.scope !== 'hostel' ? 'disabled' : ''}>
                                <option value="">Select Hostel</option>
                                ${position.scope === 'hostel' && position.college_id ? getHostelOptions(position.college_id, position.hostel_id) : ''}
                            </select>
                            <div class="delete-checkbox">
                                <input type="checkbox" name="delete_positions[]" value="${position.position_id}">
                                <span>Delete</span>
                            </div>
                        `;
                        positionsDiv.appendChild(div);
                        togglePositionFields(div.querySelector('select[name="positions[' + index + '][scope]"]'));
                    });
                    positionCount = positions.length;
                })
                .catch(error => console.error('Error fetching positions:', error));
        }

        function getHostelOptions(collegeId, selectedHostelId) {
            const hostels = hostelsByCollege[collegeId] || [];
            return hostels.map(hostel => `
                <option value="${hostel.hostel_id}" ${selectedHostelId == hostel.hostel_id ? 'selected' : ''}>
                    ${hostel.name}
                </option>
            `).join('');
        }

        function togglePositionFields(selectElement) {
            const positionGroup = selectElement.closest('.form-group');
            const collegeField = positionGroup.querySelector('.college-field');
            const hostelField = positionGroup.querySelector('.hostel-field');
            const scope = selectElement.value;

            collegeField.disabled = scope === 'university';
            hostelField.disabled = scope !== 'hostel';
            if (scope === 'university') {
                collegeField.value = '';
                hostelField.innerHTML = '<option value="">Select Hostel</option>';
            } else if (scope === 'college') {
                hostelField.innerHTML = '<option value="">Select Hostel</option>';
            } else if (scope === 'hostel') {
                updateHostelOptions(collegeField);
            }
        }

        function updateHostelOptions(collegeField) {
            const positionGroup = collegeField.closest('.form-group');
            const hostelField = positionGroup.querySelector('.hostel-field');
            const collegeId = collegeField.value;
            hostelField.innerHTML = '<option value="">Select Hostel</option>';
            if (collegeId) {
                hostelField.innerHTML += getHostelOptions(collegeId, '');
            }
        }

        function addPosition() {
            const positionsDiv = document.getElementById('edit-positions');
            const div = document.createElement('div');
            div.className = 'form-group';
            div.innerHTML = `
                <label>Position ${positionCount + 1}</label>
                <input type="text" name="positions[${positionCount}][name]" placeholder="Position Name" required>
                <select name="positions[${positionCount}][scope]" onchange="togglePositionFields(this)">
                    <option value="university">University</option>
                    <option value="college">College</option>
                    <option value="hostel">Hostel</option>
                </select>
                <select name="positions[${positionCount}][college_id]" class="college-field" disabled onchange="updateHostelOptions(this)">
                    <option value="">Select College</option>
                    <?php foreach ($colleges as $college): ?>
                        <option value="<?php echo $college['college_id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="positions[${positionCount}][hostel_id]" class="hostel-field" disabled>
                    <option value="">Select Hostel</option>
                </select>
            `;
            positionsDiv.appendChild(div);
            positionCount++;
        }

        function closeModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }

        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('electionTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                for (let j = 0; j < td.length - 1; j++) {
                    if (td[j]) {
                        const text = td[j].textContent || td[j].innerText;
                        if (text.toLowerCase().indexOf(input) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('edit-modal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };
    </script>
</body>
</html>