<?php
/*
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

$errors = [];
$success = '';
$election = null;
$positions = [];

try {
    $elections = $pdo->query(
        "SELECT e.id, e.association, c.name AS college, e.start_time, e.end_time, e.description 
        FROM elections e JOIN colleges c ON e.college_id = c.id ORDER BY e.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections.";
}

if (isset($_GET['id'])) {
    $election_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare(
            "SELECT e.*, c.name AS college_name 
            FROM elections e JOIN colleges c ON e.college_id = c.id 
            WHERE e.id = ?"
        );
        $stmt->execute([$election_id]);
        $election = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, name, hostel_id FROM electionpositions WHERE election_id = ?");
        $stmt->execute([$election_id]);
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch election details failed: " . $e->getMessage());
        $errors[] = "Failed to load election details.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_election'])) {
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $positions = $_POST['positions'] ?? [];
    $delete_positions = $_POST['delete_positions'] ?? [];

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
                "UPDATE elections SET start_time = ?, end_time = ?, description = ? WHERE id = ?"
            );
            $stmt->execute([$start_time, $end_time, $description, $election_id]);

            if (!empty($delete_positions)) {
                $stmt = $pdo->prepare("DELETE FROM electionpositions WHERE id IN (" . implode(',', array_fill(0, count($delete_positions), '?')) . ")");
                $stmt->execute($delete_positions);
            }

            foreach ($positions as $index => $position) {
                $name = filter_var($position['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $hostel_id = isset($position['hostel_id']) ? filter_var($position['hostel_id'], FILTER_VALIDATE_INT) : null;
                $position_id = isset($position['id']) ? filter_var($position['id'], FILTER_VALIDATE_INT) : null;

                if (empty($name)) {
                    $errors[] = "Position name is required.";
                    break;
                }

                if ($position_id) {
                    $stmt = $pdo->prepare(
                        "UPDATE electionpositions SET name = ?, hostel_id = ? WHERE id = ?"
                    );
                    $stmt->execute([$name, $hostel_id, $position_id]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO electionpositions (election_id, name, hostel_id, created_at) 
                        VALUES (?, ?, ?, NOW())"
                    );
                    $stmt->execute([$election_id, $name, $hostel_id]);
                }
            }

            if (empty($errors)) {
                $pdo->commit();
                header('Location: admin-dashboard.php?success=' . urlencode('Election updated successfully.'));
                exit;
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

$colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$hostels = $pdo->query("SELECT id, name FROM hostels ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('images/cive.jpeg');
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 80px auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #1a3c34;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e8ecef;
            border-radius: 6px;
            font-size: 16px;
        }
        .position-group {
            border: 1px solid #e8ecef;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #e76f51;
        }
        .error {
            color: #e76f51;
            margin-bottom: 15px;
        }
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #f4a261;
            text-decoration: none;
            margin-right: 5px;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #2d3748;
            margin-right: 5px;
        }
        .breadcrumb i {
            margin: 0 5px;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="../index.html">Home</a> <i class="fas fa-chevron-right"></i>
            <a href="../admin-dashboard.php">Admin</a> <i class="fas fa-chevron-right"></i>
            <span>Edit Election</span>
        </div>
        <h2>Edit Election</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (empty($elections)): ?>
            <p>No elections available.</p>
        <?php else: ?>
            <div class="form-group">
                <label for="election_id">Select Election</label>
                <select name="election_id" onchange="window.location.href='edit-election.php?id=' + this.value">
                    <option value="">Select an Election</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election && $election['id'] == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['association'] . ' - ' . $e['college'] . ' (' . $e['start_time'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($election): ?>
            <form method="POST" action="">
                <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                <div class="form-group">
                    <label>Association</label>
                    <input type="text" value="<?php echo htmlspecialchars($election['association']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>College</label>
                    <input type="text" value="<?php echo htmlspecialchars($election['college_name']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="datetime-local" name="start_time" id="start_time" value="<?php echo htmlspecialchars($election['start_time']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="datetime-local" name="end_time" id="end_time" value="<?php echo htmlspecialchars($election['end_time']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" required><?php echo htmlspecialchars($election['description']); ?></textarea>
                </div>
                <div id="positions">
                    <?php foreach ($positions as $index => $position): ?>
                        <div class="position-group">
                            <label>Position <?php echo $index + 1; ?></label>
                            <input type="hidden" name="positions[<?php echo $index; ?>][id]" value="<?php echo $position['id']; ?>">
                            <input type="text" name="positions[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($position['name']); ?>" required>
                            <label>Hostel (Optional)</label>
                            <select name="positions[<?php echo $index; ?>][hostel_id]">
                                <option value="">None</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?php echo $hostel['id']; ?>" <?php echo $position['hostel_id'] == $hostel['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hostel['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label><input type="checkbox" name="delete_positions[]" value="<?php echo $position['id']; ?>"> Delete</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="addPosition()">Add New Position</button>
                <button type="submit" name="update_election">Update Election</button>
            </form>
        <?php endif; ?>
    </div>
    <script>
        let positionCount = <?php echo count($positions); ?>;
        function addPosition() {
            const container = document.getElementById('positions');
            const div = document.createElement('div');
            div.className = 'position-group';
            div.innerHTML = `
                <label>Position ${positionCount + 1}</label>
                <input type="text" name="positions[${positionCount}][name]" placeholder="Position Name" required>
                <label>Hostel (Optional)</label>
                <select name="positions[${positionCount}][hostel_id]">
                    <option value="">None</option>
                    <?php foreach ($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            `;
            container.appendChild(div);
            positionCount++;
        }
    </script>
</body>
</html>