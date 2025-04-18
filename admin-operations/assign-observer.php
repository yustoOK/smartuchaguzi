<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

include '../db.php'; // Includes MySQLi connection

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=' . urlencode('Please log in as admin.'));
    exit;
}

$errors = [];
$success = '';
$users = [];
$elections = [];

// Fetch users (non-observers)
try {
    $result = $db->query("SELECT id, name, email FROM users WHERE role != 'observer' ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
    } else {
        $errors[] = "Failed to load users.";
    }
} catch (Exception $e) {
    error_log("Fetch users failed: " . $e->getMessage());
    $errors[] = "Failed to load users.";
}

// Fetch elections
try {
    $result = $db->query("SELECT id, title FROM elections ORDER BY start_date DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $elections[] = $row;
        }
        $result->free();
    } else {
        $errors[] = "Failed to load elections.";
    }
} catch (Exception $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);

    // Validation
    if (!$user_id) {
        $errors[] = "Invalid user selected.";
    }
    if (!$election_id) {
        $errors[] = "Invalid election selected.";
    }

    if (empty($errors)) {
        try {
            // Start transaction
            $db->begin_transaction();

            // Update user to observer (remove college/hostel)
            $stmt = $db->prepare("UPDATE users SET role = 'observer', college_id = NULL, hostel_id = NULL WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update user role: " . $stmt->error);
            }
            $stmt->close();

            // Assign observer to election (assuming observers table or similar)
            $stmt = $db->prepare("INSERT INTO observers (user_id, election_id, assigned_at) VALUES (?, ?, NOW())");
            $stmt->bind_param('ii', $user_id, $election_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to assign observer: " . $stmt->error);
            }
            $stmt->close();

            $db->commit();
            $success = "Observer assigned successfully.";
        } catch (Exception $e) {
            $db->rollback();
            error_log("Assign observer failed: " . $e->getMessage());
            $errors[] = "Failed to assign observer due to a server error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Observer</title>
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
        select {
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
            <span>Assign Observer</span>
        </div>
        <h2>Assign Observer</h2>
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
                <label for="user_id">Select User</label>
                <select name="user_id" id="user_id" required>
                    <option value="" disabled selected>Select user</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="election_id">Select Election</label>
                <select name="election_id" id="election_id" required>
                    <option value="" disabled selected>Select election</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?php echo $election['id']; ?>">
                            <?php echo htmlspecialchars($election['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Assign Observer</button>
        </form>
    </div>
</body>
</html>