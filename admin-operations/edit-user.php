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
    $result = $db->query("SELECT user_id, fname, mname, lname, email, role, college_id, hostel_id, active 
                          FROM users 
                          WHERE role IN ('voter', 'observer') 
                          ORDER BY fname, lname");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
            $users[] = $row;
        }
        $result->free();
    } else {
        error_log("Fetch users failed: " . $db->error);
        $errors[] = "Failed to load users.";
    }
} catch (Exception $e) {
    error_log("Fetch users failed: " . $e->getMessage());
    $errors[] = "Failed to load users.";
}

// Fetch colleges
try {
    $result = $db->query("SELECT college_id, name FROM colleges ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $colleges[] = $row;
        }
        $result->free();
    } else {
        error_log("Fetch colleges failed: " . $db->error);
        $errors[] = "Failed to load colleges.";
    }
} catch (Exception $e) {
    error_log("Fetch colleges failed: " . $e->getMessage());
    $errors[] = "Failed to load colleges.";
}

// Fetch hostels
try {
    $result = $db->query("SELECT hostel_id, name FROM hostels ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $hostels[] = $row;
        }
        $result->free();
    } else {
        error_log("Fetch hostels failed: " . $db->error);
        $errors[] = "Failed to load hostels.";
    }
} catch (Exception $e) {
    error_log("Fetch hostels failed: " . $e->getMessage());
    $errors[] = "Failed to load hostels.";
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $fname = filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $mname = filter_input(INPUT_POST, 'mname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $lname = filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'] ?? '';
    $college_id = filter_var($_POST['college_id'], FILTER_VALIDATE_INT);
    $hostel_id = isset($_POST['hostel_id']) && $_POST['hostel_id'] !== '' ? filter_var($_POST['hostel_id'], FILTER_VALIDATE_INT) : null;

    // Map form roles to ENUM values
    $role_map = ['student' => 'voter', 'teacher' => 'observer'];
    $role = isset($role_map[$role]) ? $role_map[$role] : 'voter';

    // Validation
    if (!$user_id) {
        $errors[] = "Invalid user selected.";
    }
    if (empty($fname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (!in_array($role, ['voter', 'observer'])) {
        $errors[] = "Role must be valid.";
    }
    if (!$college_id) {
        $errors[] = "College is required.";
    }
    if ($role === 'observer' && $hostel_id !== null) {
        $errors[] = "Observers cannot be assigned to hostels.";
    }

    // Check email uniqueness (exclude current user)
    try {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
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
                SET fname = ?, mname = ?, lname = ?, email = ?, role = ?, college_id = ?, hostel_id = ? 
                WHERE user_id = ?"
            );
            $stmt->bind_param('sssssiii', $fname, $mname, $lname, $email, $role, $college_id, $hostel_id, $user_id);
            if ($stmt->execute()) {
                $success = "User updated successfully.";
                // Refresh user list
                $users = [];
                $result = $db->query("SELECT user_id, fname, mname, lname, email, role, college_id, hostel_id, active 
                                      FROM users 
                                      WHERE role IN ('voter', 'observer') 
                                      ORDER BY fname, lname");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
                        $users[] = $row;
                    }
                    $result->free();
                }
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
            // Check for dependencies (e.g., candidates)
            $stmt = $db->prepare("SELECT official_id FROM candidates WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Cannot delete user: They are a candidate in an election.";
                $stmt->close();
            } else {
                $stmt->close();
                // Delete user
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role IN ('voter', 'observer')");
                $stmt->bind_param('i', $user_id);
                if ($stmt->execute()) {
                    $success = "User deleted successfully.";
                    // Refresh user list
                    $users = [];
                    $result = $db->query("SELECT user_id, fname, mname, lname, email, role, college_id, hostel_id, active 
                                          FROM users 
                                          WHERE role IN ('voter', 'observer') 
                                          ORDER BY fname, lname");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? $row['mname'] . ' ' : '') . $row['lname']);
                            $users[] = $row;
                        }
                        $result->free();
                    }
                } else {
                    error_log("Delete user failed: " . $db->error);
                    $errors[] = "Failed to delete user due to a server error: " . $db->error;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Delete user failed: " . $e->getMessage() . " - MySQL Error: " . $db->error);
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
    <title>Manage Users: Edit & Delete</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, rgba(26, 60, 52, 0.8), rgba(34, 78, 68, 0.8)), url('../images/cive.jpeg');
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #1a3c34;
            font-size: 15px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e7ea;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #f9fafb;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #2a9d8f;
            box-shadow: 0 0 6px rgba(42, 157, 143, 0.4);
            background: #fff;
        }
        button {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-right: 15px;
        }
        .edit-btn {
            background: #2a9d8f;
            color: #fff;
        }
        .edit-btn:hover {
            background: #207b6e;
            transform: translateY(-2px);
        }
        .delete-btn {
            background: #d00000;
            color: #fff;
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
        tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.5);
        }
        tr:hover {
            background: rgba(42, 157, 143, 0.1);
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
            max-width: 450px;
            width: 90%;
            margin: 40px auto;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
        }
        .modal-content h3 {
            margin-top: 0;
            color: #1a3c34;
            font-size: 18px;
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
            button {
                font-size: 12px;
                padding: 6px 14px;
                margin-right: 10px;
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
            button {
                padding: 5px 10px;
                margin-right: 8px;
            }
            .modal-content h3 {
                font-size: 16px;
            }
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
        <h2>Manage Users</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search users..." onkeyup="filterTable()">
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
        <?php if (empty($users)): ?>
            <p>No users available to edit or delete.</p>
        <?php else: ?>
            <table id="userTable">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>College</th>
                    <th>Hostel</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <?php
                            foreach ($colleges as $college) {
                                if ($college['college_id'] == $user['college_id']) {
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
                                    if ($hostel['hostel_id'] == $user['hostel_id']) {
                                        echo htmlspecialchars($hostel['name']);
                                        break;
                                    }
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo $user['active'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <button class="edit-btn" onclick="editUser(<?php echo $user['user_id']; ?>, '<?php echo addslashes($user['fname']); ?>', '<?php echo addslashes($user['mname']); ?>', '<?php echo addslashes($user['lname']); ?>', '<?php echo addslashes($user['email']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['college_id']; ?>, <?php echo $user['hostel_id'] ?: 'null'; ?>, <?php echo $user['active']; ?>)">Edit</button>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="delete_user" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete <?php echo addslashes($user['full_name']); ?>? This action cannot be undone.');">Delete</button>
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
                    <label for="edit-fname">First Name</label>
                    <input type="text" name="fname" id="edit-fname" required>
                </div>
                <div class="form-group">
                    <label for="edit-mname">Middle Name (Optional)</label>
                    <input type="text" name="mname" id="edit-mname">
                </div>
                <div class="form-group">
                    <label for="edit-lname">Last Name</label>
                    <input type="text" name="lname" id="edit-lname" required>
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
                            <option value="<?php echo $college['college_id']; ?>">
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
                            <option value="<?php echo $hostel['hostel_id']; ?>">
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
        function editUser(id, fname, mname, lname, email, role, college_id, hostel_id, active) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-fname').value = fname;
            document.getElementById('edit-mname').value = mname || '';
            document.getElementById('edit-lname').value = lname;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = (role === 'voter' ? 'student' : 'teacher');
            document.getElementById('edit-college_id').value = college_id;
            document.getElementById('edit-hostel_id').value = hostel_id || '';
            document.getElementById('edit-hostel-group').style.display = (role === 'voter' ? 'block' : 'none');
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
        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('userTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                for (let j = 0; j < td.length - 1; j++) { // Exclude Actions column
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