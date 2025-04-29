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
    <title>Add New Election</title>
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

        textarea {
            resize: vertical;
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
                <span>Add Election</span>
            </div>
            <h2>Add New Election</h2>
        </div>

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
                        <option value="<?php echo $college['college_id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
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
                            <option value="<?php echo $hostel['hostel_id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="button-group">
                <button type="button" onclick="addPosition()">Add Another Position</button>
                <button type="submit">Create Election</button>
            </div>
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