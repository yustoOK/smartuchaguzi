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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $association = filter_input(INPUT_POST, 'association', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $positions = $_POST['positions'] ?? [];

        // Validate election inputs
        if (empty($title)) {
            $errors[] = "Election title is required.";
        }
        if (empty($association) || !in_array($association, ['UDOSO', 'UDOMASA'])) {
            $errors[] = "Invalid association. Choose UDOSO or UDOMASA.";
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

        // Validate positions
        foreach ($positions as $index => $position) {
            $name = trim(filter_var($position['name'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $scope = $position['scope'] ?? '';
            $college_id = !empty($position['college_id']) ? filter_var($position['college_id'], FILTER_VALIDATE_INT) : null;
            $hostel_id = !empty($position['hostel_id']) ? filter_var($position['hostel_id'], FILTER_VALIDATE_INT) : null;

            if (empty($name)) {
                $errors[] = "Position name for position " . ($index + 1) . " is required.";
            }
            if (!in_array($scope, ['university', 'college', 'hostel'])) {
                $errors[] = "Invalid scope for position " . ($index + 1) . ". Choose university, college, or hostel.";
            }

            // Scope-based validation
            if ($scope === 'university') {
                $college_id = null;
                $hostel_id = null;
            } elseif ($scope === 'college') {
                if (!$college_id) {
                    $errors[] = "College is required for college-scoped position " . ($index + 1) . ".";
                }
                $hostel_id = null; // Ensure hostel_id is NULL for college scope
            } elseif ($scope === 'hostel') {
                if (!$college_id) {
                    $errors[] = "College is required for hostel-scoped position " . ($index + 1) . ".";
                }
                if (!$hostel_id) {
                    $errors[] = "Hostel is required for hostel-scoped position " . ($index + 1) . ".";
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Insert into elections table
                $stmt = $pdo->prepare(
                    "INSERT INTO elections (title, association, start_time, end_time, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$title, $association, $start_time, $end_time, $description]);
                $election_id = $pdo->lastInsertId();

                // Insert into electionpositions table
                foreach ($positions as $position) {
                    $name = trim(filter_var($position['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                    $scope = $position['scope'];
                    $college_id = !empty($position['college_id']) ? filter_var($position['college_id'], FILTER_VALIDATE_INT) : null;
                    $hostel_id = !empty($position['hostel_id']) ? filter_var($position['hostel_id'], FILTER_VALIDATE_INT) : null;
                    $pos_description = !empty($position['description']) ? filter_var($position['description'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;

                    // Set college_id and hostel_id to NULL based on scope
                    if ($scope === 'university') {
                        $college_id = null;
                        $hostel_id = null;
                    } elseif ($scope === 'college') {
                        $hostel_id = null;
                    }

                    $stmt = $pdo->prepare(
                        "INSERT INTO electionpositions (election_id, college_id, name, description, scope, hostel_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([$election_id, $college_id, $name, $pos_description, $scope, $hostel_id]);
                }

                $pdo->commit();
                $success = "Election and positions added successfully. Redirecting...";
                echo "<script>setTimeout(() => { window.location.href = 'admin-dashboard.php'; }, 2000);</script>";
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Add election failed: " . $e->getMessage());
                $errors[] = "Failed to add election: " . $e->getMessage(); // Temporary for debugging
            }
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
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            max-width: 900px;
            width: 100%;
            background: #ffffff;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            color: #1a3c34;
            margin-bottom: 10px;
        }

        .breadcrumb {
            font-size: 14px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
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
            font-size: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-grid.full-width {
            grid-column: 1 / -1;
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
            padding: 10px 12px;
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
            min-height: 80px;
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

        .position-group .form-grid {
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .position-group label {
            margin-bottom: 8px;
        }

        .position-group input,
        .position-group select,
        .position-group textarea {
            margin-bottom: 10px;
        }

        .hidden {
            display: none;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        button {
            padding: 10px 25px;
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

        button.remove-position {
            background: #dc2626;
            color: #ffffff;
            margin-top: 10px;
        }

        button.remove-position:hover {
            background: #b91c1c;
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
            grid-column: 1 / -1;
        }

        .success {
            background: #ecfdf5;
            color: #059669;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #059669;
            grid-column: 1 / -1;
        }

        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .position-group .form-grid {
                grid-template-columns: 1fr;
            }

            h2 {
                font-size: 20px;
            }

            .breadcrumb {
                font-size: 12px;
            }

            button {
                padding: 8px 20px;
                font-size: 14px;
            }

            .position-group {
                padding: 15px;
            }
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

        <?php if (!empty($success)): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-grid">
                <div>
                    <label for="title">Election Title</label>
                    <input type="text" name="title" id="title" placeholder="Enter election title" required>
                </div>
                <div>
                    <label for="association">Association</label>
                    <select name="association" id="association" required>
                        <option value="">Select Association</option>
                        <option value="UDOSO">UDOSO (Students)</option>
                        <option value="UDOMASA">UDOMASA (Teachers)</option>
                    </select>
                </div>
                <div>
                    <label for="start_time">Start Time</label>
                    <input type="datetime-local" name="start_time" id="start_time" required>
                </div>
                <div>
                    <label for="end_time">End Time</label>
                    <input type="datetime-local" name="end_time" id="end_time" required>
                </div>
                <div class="form-grid full-width">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3" required></textarea>
                </div>
            </div>

            <div id="positions">
                <div class="position-group">
                    <div class="form-grid">
                        <div>
                            <label>Position 1</label>
                            <input type="text" name="positions[0][name]" placeholder="Position Name" required>
                        </div>
                        <div>
                            <label>Scope</label>
                            <select name="positions[0][scope]" class="scope-select" required onchange="toggleScopeFields(this)">
                                <option value="university">University</option>
                                <option value="college">College</option>
                                <option value="hostel">Hostel</option>
                            </select>
                        </div>
                        <div class="college-field">
                            <label>College (Required for college/hostel scope)</label>
                            <select name="positions[0][college_id]">
                                <option value="">None</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?php echo $college['college_id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="hostel-field hidden">
                            <label>Hostel (Required for hostel scope)</label>
                            <select name="positions[0][hostel_id]">
                                <option value="">None</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?php echo $hostel['hostel_id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grid full-width">
                            <label>Description (Optional)</label>
                            <textarea name="positions[0][description]" rows="2"></textarea>
                        </div>
                    </div>
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

        function toggleScopeFields(selectElement) {
            const positionGroup = selectElement.closest('.position-group');
            const collegeField = positionGroup.querySelector('.college-field');
            const hostelField = positionGroup.querySelector('.hostel-field');
            const scope = selectElement.value;

            collegeField.classList.add('hidden');
            hostelField.classList.add('hidden');

            if (scope === 'college' || scope === 'hostel') {
                collegeField.classList.remove('hidden');
            }
            if (scope === 'hostel') {
                hostelField.classList.remove('hidden');
            }
        }

        function addPosition() {
            const container = document.getElementById('positions');
            const div = document.createElement('div');
            div.className = 'position-group';
            div.innerHTML = `
                <div class="form-grid">
                    <div>
                        <label>Position ${positionCount + 1}</label>
                        <input type="text" name="positions[${positionCount}][name]" placeholder="Position Name" required>
                    </div>
                    <div>
                        <label>Scope</label>
                        <select name="positions[${positionCount}][scope]" class="scope-select" required onchange="toggleScopeFields(this)">
                            <option value="university">University</option>
                            <option value="college">College</option>
                            <option value="hostel">Hostel</option>
                        </select>
                    </div>
                    <div class="college-field">
                        <label>College (Required for college/hostel scope)</label>
                        <select name="positions[${positionCount}][college_id]">
                            <option value="">None</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?php echo $college['college_id']; ?>"><?php echo htmlspecialchars($college['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hostel-field hidden">
                        <label>Hostel (Required for hostel scope)</label>
                        <select name="positions[${positionCount}][hostel_id]">
                            <option value="">None</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo $hostel['hostel_id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grid full-width">
                        <label>Description (Optional)</label>
                        <textarea name="positions[${positionCount}][description]" rows="2"></textarea>
                    </div>
                </div>
                <button type="button" class="remove-position" onclick="removePosition(this)">Remove Position</button>
            `;
            container.appendChild(div);
            positionCount++;
        }

        function removePosition(button) {
            if (confirm('Are you sure you want to remove this position?')) {
                button.parentElement.remove();
                const positions = document.querySelectorAll('#positions .position-group');
                positions.forEach((pos, index) => {
                    pos.querySelector('label').textContent = `Position ${index + 1}`;
                });
            }
        }

        // Initialize scope field visibility on page load
        document.querySelectorAll('.scope-select').forEach(select => {
            toggleScopeFields(select);
        });
    </script>
</body>
</html>