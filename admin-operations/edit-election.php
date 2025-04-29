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

$errors = [];
$success = '';
$election = null;
$positions = [];

try {
    $elections = $pdo->query(
        "SELECT e.id, e.association, c.name AS college, e.start_time, e.end_time, e.description 
        FROM elections e JOIN colleges c ON e.college_id = c.college_id ORDER BY e.created_at DESC"
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
            FROM elections e JOIN colleges c ON e.college_id = c.college_id 
            WHERE e.id = ?"
        );
        $stmt->execute([$election_id]);
        $election = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT position_id, name, hostel_id FROM electionpositions WHERE election_id = ?");
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
                $stmt = $pdo->prepare("DELETE FROM electionpositions WHERE position_id IN (" . implode(',', array_fill(0, count($delete_positions), '?')) . ")");
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
                        "UPDATE electionpositions SET name = ?, hostel_id = ? WHERE position_id = ?"
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

try {
    $colleges = $pdo->query("SELECT college_id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $hostels = $pdo->query("SELECT hostel_id, name FROM hostels ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch colleges/hostels failed: " . $e->getMessage());
    $errors[] = "Failed to load colleges or hostels.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, rgba(26, 60, 52, 0.85), rgba(26, 60, 52, 0.85)), url('images/cive.jpeg');
            background-size: cover;
            background-attachment: fixed;
            color: #1a3c34;
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            width: 90%;
            background: #ffffff;
            padding: 50px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        h2 {
            font-size: 28px;
            font-weight: 600;
            color: #1a3c34;
            margin-bottom: 10px;
        }

        .breadcrumb {
            font-size: 16px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            position: absolute;
            top: -10px;
            left: 0;
        }

        .breadcrumb a {
            color: #f4a261;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #e76f51;
            text-decoration: underline;
        }

        .breadcrumb i {
            color: #6b7280;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1a3c34;
            margin-bottom: 8px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #1a3c34;
            background: #f9fafb;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #f4a261;
            box-shadow: 0 0 0 3px rgba(244, 162, 97, 0.1);
        }

        input[disabled] {
            background: #e2e8f0;
            cursor: not-allowed;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .position-group {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            transition: box-shadow 0.3s ease;
        }

        .position-group:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .delete-checkbox {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #dc2626;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }

        button[type="button"] {
            background: #e2e8f0;
            color: #1a3c34;
        }

        button[type="button"]:hover {
            background: #d1d5db;
            transform: translateY(-1px);
        }

        button[type="submit"] {
            background: #f4a261;
            color: #ffffff;
        }

        button[type="submit"]:hover {
            background: #e76f51;
            transform: translateY(-1px);
        }

        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }

        .no-elections {
            text-align: center;
            font-size: 16px;
            color: #6b7280;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="../index.html">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="../admin-dashboard.php">Admin</a>
                <i class="fas fa-chevron-right"></i>
                <span>Edit Election</span>
            </div>
            <h2>Edit Election</h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($elections)): ?>
            <p class="no-elections">No elections available. Please add an election first using the <a href="add-election.php" style="color: #f4a261;">Add Election</a> page.</p>
        <?php else: ?>
            <div class="form-group">
                <label for="election_id">Select Election</label>
                <select name="election_id" onchange="window.location.href='edit-election.php?id=' + this.value">
                    <option value="">Select an Election</option>
                    <?php foreach ($errors as $e): ?>
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
                            <input type="hidden" name="positions[<?php echo $index; ?>][id]" value="<?php echo $position['position_id']; ?>">
                            <input type="text" name="positions[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($position['name']); ?>" required>
                            <label>Hostel (Optional, for student positions)</label>
                            <select name="positions[<?php echo $index; ?>][hostel_id]">
                                <option value="">None</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?php echo $hostel['hostel_id']; ?>" <?php echo $position['hostel_id'] == $hostel['hostel_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hostel['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="delete-checkbox">
                                <input type="checkbox" name="delete_positions[]" value="<?php echo $position['position_id']; ?>">
                                <span>Delete</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="button-group">
                    <button type="button" onclick="addPosition()">Add New Position</button>
                    <button type="submit" name="update_election">Update Election</button>
                </div>
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
                <label>Hostel (Optional, for student positions)</label>
                <select name="positions[${positionCount}][hostel_id]">
                    <option value="">None</option>
                    <?php foreach ($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['hostel_id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            `;
            container.appendChild(div);
            positionCount++;
        }
    </script>
</body>
</html>