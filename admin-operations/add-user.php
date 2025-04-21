<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

include '../db.php'; // Include MySQLi connection

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=' . urlencode('Please log in as admin.'));
    exit;
}

$errors = [];
$success = '';
$colleges = [];
$hostels = [];

// Check if database connection is successful
if (!isset($db) || $db->connect_error) {
    error_log("Database connection failed: " . ($db ? $db->connect_error : "DB object not set"));
    $errors[] = "Unable to connect to the database.";
} else {
    // Fetch colleges for dropdown
    try {
        $result = $db->query("SELECT college_id, name FROM colleges ORDER BY name");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $colleges[] = $row;
            }
            $result->free();
        } else {
            error_log("Fetch colleges failed: " . $db->error);
            $errors[] = "Failed to load colleges due to a server error.";
        }
    } catch (Exception $e) {
        error_log("Fetch colleges failed: " . $e->getMessage());
        $errors[] = "Failed to load colleges due to a server error.";
    }

    // Fetch hostels for dropdown (only for students)
    try {
        $result = $db->query("SELECT hostel_id, name FROM hostels ORDER BY name");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $hostels[] = $row;
            }
            $result->free();
        } else {
            error_log("Fetch hostels failed: " . $db->error);
            $errors[] = "Failed to load hostels due to a server error.";
        }
    } catch (Exception $e) {
        error_log("Fetch hostels failed: " . $e->getMessage());
        $errors[] = "Failed to load hostels due to a server error.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $mname = filter_input(INPUT_POST, 'mname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $lname = filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $official_id = filter_input(INPUT_POST, 'official_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $association = filter_input(INPUT_POST, 'association', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $college_id = filter_var($_POST['college_id'], FILTER_VALIDATE_INT);
    $hostel_id = isset($_POST['hostel_id']) && $_POST['hostel_id'] !== '' ? filter_var($_POST['hostel_id'], FILTER_VALIDATE_INT) : null;

    // Validation
    if (empty($fname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($official_id)) {
        $errors[] = "Official ID is required.";
    }
    if (empty($association)) {
        $errors[] = "Association is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (!in_array($role, ['student', 'teacher']) || empty($role)) {
        $errors[] = "Role must be student or teacher.";
    }
    if (!$college_id) {
        $errors[] = "College is required.";
    }
    if ($role === 'teacher' && $hostel_id !== null) {
        $errors[] = "Teachers cannot be assigned to hostels.";
    }

    // Check if email already exists
    try {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email is already registered.";
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Check email failed: " . $e->getMessage());
        $errors[] = "Failed to verify email.";
    }

    // Check if official_id already exists
    try {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE official_id = ?");
        $stmt->bind_param('s', $official_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Official ID is already registered.";
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Check official_id failed: " . $e->getMessage());
        $errors[] = "Failed to verify official ID.";
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                "INSERT INTO users (official_id, association, email, password, role, fname, mname, lname, college_id, hostel_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('sssssssiii', $official_id, $association, $email, $hashed_password, $role, $fname, $mname, $lname, $college_id, $hostel_id);
            if ($stmt->execute()) {
                $success = "User added successfully.";
            } else {
                error_log("Add user failed: " . $stmt->error);
                $errors[] = "Failed to add user due to a server error.";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Add user failed: " . $e->getMessage());
            $errors[] = "Failed to add user due to a server error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(26, 60, 52, 0.7), rgba(26, 60, 52, 0.7)), url('../images/cive.jpeg');
            background-size: cover;
            color: #2d3748;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
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
        .success {
            color: #2a9d8f;
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
            <a href="../index.php">Home</a> <i class="fas fa-chevron-right"></i>
            <a href="../admin-dashboard.php">Admin</a> <i class="fas fa-chevron-right"></i>
            <span>Add New User</span>
        </div>
        <h2>Add New User</h2>
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
            <div class="form-group">
                <label for="official_id">Official ID</label>
                <input type="text" name="official_id" id="official_id" placeholder="Enter official ID" value="<?php echo isset($_POST['official_id']) ? htmlspecialchars($_POST['official_id']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="association">Association</label>
                <input type="text" name="association" id="association" placeholder="Enter association" value="<?php echo isset($_POST['association']) ? htmlspecialchars($_POST['association']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="fname">First Name</label>
                <input type="text" name="fname" id="fname" placeholder="Enter first name" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="mname">Middle Name (Optional)</label>
                <input type="text" name="mname" id="mname" placeholder="Enter middle name" value="<?php echo isset($_POST['mname']) ? htmlspecialchars($_POST['mname']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" name="lname" id="lname" placeholder="Enter last name" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter password (min 8 characters)" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" required onchange="toggleHostelField()">
                    <option value="" disabled selected>Select role</option>
                    <option value="student" <?php echo isset($_POST['role']) && $_POST['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo isset($_POST['role']) && $_POST['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>
            <div class="form-group">
                <label for="college_id">College</label>
                <select name="college_id" id="college_id" required>
                    <option value="" disabled selected>Select college</option>
                    <?php foreach ($colleges as $college): ?>
                        <option value="<?php echo $college['college_id']; ?>" <?php echo isset($_POST['college_id']) && $_POST['college_id'] == $college['college_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($college['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="hostel-group" style="display: none;">
                <label for="hostel_id">Hostel (Optional for Students)</label>
                <select name="hostel_id" id="hostel_id">
                    <option value="">No hostel (off-campus)</option>
                    <?php foreach ($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['hostel_id']; ?>" <?php echo isset($_POST['hostel_id']) && $_POST['hostel_id'] == $hostel['hostel_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hostel['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Add User</button>
        </form>
    </div>
    <script>
        function toggleHostelField() {
            const role = document.getElementById('role').value;
            const hostelGroup = document.getElementById('hostel-group');
            hostelGroup.style.display = role === 'student' ? 'block' : 'none';
            if (role !== 'student') {
                document.getElementById('hostel_id').value = '';
            }
        }
    </script>
</body>
</html>