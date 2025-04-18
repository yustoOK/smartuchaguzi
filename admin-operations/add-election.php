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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $association = filter_input(INPUT_POST, 'association', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $college_id = filter_input(INPUT_POST, 'college_id', FILTER_VALIDATE_INT);
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $positions = $_POST['positions'] ?? [];

    if (empty($association) || !in_array($association, ['UDOSO', 'UDOMASA'])) {
        $errors[] = "Invalid association. Choose UDOSO or UDOMASA.";
    }
    if (!$college_id) {
        $errors[] = "Please select a college.";
    }
    if (empty($start_time) || empty($end_time)) {
        $errors[] = "Start and end times are required.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = "End time must be after start time.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    if (empty($positions)) {
        $errors[] = "At least one position is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO elections (association, college_id, start_time, end_time, description, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$association, $college_id, $start_time, $end_time, $description]);
            $election_id = $pdo->lastInsertId();

            foreach ($positions as $position) {
                $name = filter_var($position['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $hostel_id = isset($position['hostel_id']) ? filter_var($position['hostel_id'], FILTER_VALIDATE_INT) : null;

                if (empty($name)) {
                    $errors[] = "Position name is required.";
                    break;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO electionpositions (election_id, name, hostel_id, created_at) 
                    VALUES (?, ?, ?, NOW())"
                );
                $stmt->execute([$election_id, $name, $hostel_id]);
            }

            if (empty($errors)) {
                $pdo->commit();
                header('Location: admin-dashboard.php?success=' . urlencode('Election added successfully.'));
                exit;
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add election failed: " . $e->getMessage());
            $errors[] = "Failed to add election due to a server error.";
        }
    }
}

try {
    $colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $hostels = $pdo->query("SELECT id, name FROM hostels ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Add New Election</title>
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
            <span>Add Election</span>
        </div>
        <h2>Add New Election</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="association">Association</label>
                <select name="association" id="association" required>
                    <option value="">Select Association</option>
                    <option value="UDOSO">UDOSO (Students)</option>
                    <option value="UDOMASA">UDOMASA (Teachers)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="college_id">College</label>
                <select name="college_id" id="college_id" required>
                    <option value="">Select College</option>
                    <?php foreach ($colleges as $college): ?>
                        <option value="<?php echo $college['id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_time">Start Time</label>
                <input type="datetime-local" name="start_time" id="start_time" required>
            </div>
            <div class="form-group">
                <label for="end_time">End Time</label>
                <input type="datetime-local" name="end_time" id="end_time" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="4" required></textarea>
            </div>
            <div id="positions">
                <div class="position-group">
                    <label>Position 1</label>
                    <input type="text" name="positions[0][name]" placeholder="Position Name" required>
                    <label>Hostel (Optional, for student positions)</label>
                    <select name="positions[0][hostel_id]">
                        <option value="">None</option>
                        <?php foreach ($hostels as $hostel): ?>
                            <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" onclick="addPosition()">Add Another Position</button>
            <button type="submit">Create Election</button>
        </form>
    </div>
    <script>
        let positionCount = 1;
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