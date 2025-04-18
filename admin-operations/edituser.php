<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

include '../db.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=' . urlencode('Please log in as admin.'));
    exit;
}

$errors = [];
$success = '';
$users = [];
$colleges = [];
$hostels = [];

// Fetch users
try {
    $result = $db->query("SELECT id, name, email, role, college_id, hostel_id FROM users WHERE role IN ('student', 'teacher') ORDER BY name");
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

// Fetch colleges
try {
    $result = $db->query("SELECT id, name FROM colleges ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $colleges[] = $row;
        }
        $result->free();
    } else {
        $errors[] = "Failed to load colleges.";
    }
} catch (Exception $e) {
    error_log("Fetch colleges failed: " . $e->getMessage());
    $errors[] = "Failed to load colleges.";
}

// Fetch hostels
try {
    $result = $db->query("SELECT id, name FROM hostels ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $hostels[] = $row;
        }
        $result->free();
    } else {
        $errors[] = "Failed to load hostels.";
    }
} catch (Exception $e) {
    error_log("Fetch hostels failed: " . $e->getMessage());
    $errors[] = "Failed to load hostels.";
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'] ?? '';
    $college_id = filter_var($_POST['college_id'], FILTER_VALIDATE_INT);
    $hostel_id = isset($_POST['hostel_id']) ? filter_var($_POST['hostel_id'], FILTER_VALIDATE_INT) : null;

    // Validation
    if (!$user_id) {
        $errors[] = "Invalid user selected.";
    }
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (!in_array($role, ['student', 'teacher'])) {
        $errors[] = "Role must be student or teacher.";
    }
    if (!$college_id) {
        $errors[] = "College is required.";
    }
    if ($role === 'student' && $hostel_id === false) {
        $errors[] = "Invalid hostel selection.";
    }
    if ($role === 'teacher' && $hostel_id !== null) {
        $errors[] = "Teachers cannot be assigned to hostels.";
    }

    // Check email uniqueness (exclude current user)
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $user_id);
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

    if (empty($errors)) {
        try {
            $stmt = $db->prepare(
                "UPDATE users 
                SET name = ?, email = ?, role = ?, college_id = ?, hostel_id = ? 
                WHERE id = ?"
            );
            $stmt->bind_param('sssiii', $name, $email, $role, $college_id, $hostel_id, $user_id);
            if ($stmt->execute()) {
                $success = "User updated successfully.";
            } else {
                error_log("Update user failed: " . $stmt->error);
                $errors[] = "Failed to update user due to a server error.";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Update user failed: " . $e->getMessage());
            $errors[] = "Failed to update user due to a server error.";
        }
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

    if (!$user_id) {
        $errors[] = "Invalid user selected for deletion.";
    } else {
        try {
            // Check for dependencies (e.g., candidates, observers)
            $stmt = $db->prepare("SELECT id FROM candidates WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Cannot delete user: They are a candidate in an election.";
                $stmt->close();
            } else {
                $stmt->close();
                // Check observers table (if exists)
                $stmt = $db->prepare("SELECT id FROM observers WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Cannot delete user: They are an observer for an election.";
                    $stmt->close();
                } else {
                    $stmt->close();
                    // Delete user
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role IN ('student', 'teacher')");
                    $stmt->bind_param('i', $user_id);
                    if ($stmt->execute()) {
                        $success = "User deleted successfully.";
                    } else {
                        error_log("Delete user failed: " . $stmt->error);
                        $errors[] = "Failed to delete user due to a server error.";
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Delete user failed: " . $e->getMessage());
            $errors[] = "Failed to delete user due to a server error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
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
        button {
            background: #f4a261;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        button:hover {
            background: #e76f51;
        }
        button.delete {
            background: #e76f51;
        }
        button.delete:hover {
            background: #d00000;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #e8ecef;
            text-align: left;
        }
        th {
            background: #f4a261;
            color: #fff;
        }
        tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            max-width: 500px;
            margin: 100px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="../index.php">Home</a> <i class="fas fa-chevron-right"></i>
            <a href="../admin-dashboard.php">Admin</a> <i class="fas fa-chevron-right"></i>
            <span>Edit User</span>
        </div>
        <h2>Edit User</h2>
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
        <?php if (empty($users)): ?>
            <p>No users available to edit or delete.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>College</th>
                    <th>Hostel</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <?php
                            foreach ($colleges as $college) {
                                if ($college['id'] == $user['college_id']) {
                                    echo htmlspecialchars($college['name']);
                                    break;
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($user['hostel_id']) {
                                foreach ($hostels as $hostel) {
                                    if ($hostel['id'] == $user['hostel_id']) {
                                        echo htmlspecialchars($hostel['name']);
                                        break;
                                    }
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['college_id']; ?>, <?php echo $user['hostel_id'] ?: 'null'; ?>)">Edit</button>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="delete_user" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="delete" onclick="return confirm('Are you sure you want to delete <?php echo addslashes($user['name']); ?>? This action cannot be undone.');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <h3>Edit User</h3>
            <form method="POST" action="">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="form-group">
                    <label for="edit-name">Full Name</label>
                    <input type="text" name="name" id="edit-name" required>
                </div>
                <div class="form-group">
                    <label for="edit-email">Email</label>
                    <input type="email" name="email" id="edit-email" required>
                </div>
                <div class="form-group">
                    <label for="edit-role">Role</label>
                    <select name="role" id="edit-role" required onchange="toggleHostelField()">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-college_id">College</label>
                    <select name="college_id" id="edit-college_id" required>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo $college['id']; ?>">
                                <?php echo htmlspecialchars($college['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="edit-hostel-group" style="display: none;">
                    <label for="edit-hostel_id">Hostel (Optional for Students)</label>
                    <select name="hostel_id" id="edit-hostel_id">
                        <option value="">No hostel (off-campus)</option>
                        <?php foreach ($hostels as $hostel): ?>
                            <option value="<?php echo $hostel['id']; ?>">
                                <?php echo htmlspecialchars($hostel['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Update User</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
    <script>
        function editUser(id, name, email, role, college_id, hostel_id) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-college_id').value = college_id;
            document.getElementById('edit-hostel_id').value = hostel_id || '';
            document.getElementById('edit-hostel-group').style.display = role === 'student' ? 'block' : 'none';
            document.getElementById('edit-modal').style.display = 'block';
        }
        function toggleHostelField() {
            const role = document.getElementById('edit-role').value;
            const hostelGroup = document.getElementById('edit-hostel-group');
            hostelGroup.style.display = role === 'student' ? 'block' : 'none';
            if (role !== 'student') {
                document.getElementById('edit-hostel_id').value = '';
            }
        }
        function closeModal() {
            document.getElementById('edit-modal').style.display = 'none';
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