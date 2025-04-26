<?php
session_start();
date_default_timezone_set('Africa/Dar_es_Salaam');

include '../db.php'; // Database connection (assumes MySQLi)

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=' . urlencode('Please log in as admin.'));
    exit;
}

$errors = [];
$success = '';
$notifications = [];

try {
    $stmt = $db->query(
        "SELECT id, title, content, sent_at 
         FROM notifications 
         WHERE type = 'upcoming_election' 
         ORDER BY sent_at DESC"
    );
    $notifications = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Fetch notifications failed: " . $e->getMessage());
    $errors[] = "Failed to load upcoming elections.";
}

// Handle adding new notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notification'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validation
    if (empty($title)) {
        $errors[] = "Election title is required.";
    }
    if (empty($date)) {
        $errors[] = "Election date is required.";
    } elseif (!strtotime($date)) {
        $errors[] = "Invalid election date format.";
    } elseif (strtotime($date) < strtotime('today')) {
        $errors[] = "Election date must be in the future.";
    }
    if (empty($description)) {
        $errors[] = "Election description is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare(
                "INSERT INTO notifications (user_id, title, content, type, sent_at, created_at) 
                 VALUES (NULL, ?, ?, 'upcoming_election', ?, NOW())"
            );
            $stmt->bind_param('sss', $title, $description, $date);
            $stmt->execute();
            $stmt->close();
            header('Location: update-upcoming.php?success=' . urlencode('Upcoming election added successfully.'));
            exit;
        } catch (Exception $e) {
            error_log("Add notification failed: " . $e->getMessage());
            $errors[] = "Failed to add upcoming election due to a server error.";
        }
    }
}

// Handle updating existing notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notification'])) {
    $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validation
    if ($notification_id === false || $notification_id <= 0) {
        $errors[] = "Invalid notification ID.";
    } else {
        $stmt = $db->prepare("SELECT 1 FROM notifications WHERE id = ? AND type = 'upcoming_election'");
        $stmt->bind_param('i', $notification_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $errors[] = "Notification not found.";
        }
        $stmt->close();
    }
    if (empty($title)) {
        $errors[] = "Election title is required.";
    }
    if (empty($date)) {
        $errors[] = "Election date is required.";
    } elseif (!strtotime($date)) {
        $errors[] = "Invalid election date format.";
    } elseif (strtotime($date) < strtotime('today')) {
        $errors[] = "Election date must be in the future.";
    }
    if (empty($description)) {
        $errors[] = "Election description is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare(
                "UPDATE notifications 
                 SET title = ?, content = ?, sent_at = ? 
                 WHERE id = ? AND type = 'upcoming_election'"
            );
            $stmt->bind_param('sssi', $title, $description, $date, $notification_id);
            $stmt->execute();
            $stmt->close();
            header('Location: update-upcoming.php?success=' . urlencode('Upcoming election updated successfully.'));
            exit;
        } catch (Exception $e) {
            error_log("Update notification failed: " . $e->getMessage());
            $errors[] = "Failed to update upcoming election due to a server error.";
        }
    }
}

// Handle deleting notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);

    if ($notification_id === false || $notification_id <= 0) {
        $errors[] = "Invalid notification ID.";
    } else {
        $stmt = $db->prepare("SELECT 1 FROM notifications WHERE id = ? AND type = 'upcoming_election'");
        $stmt->bind_param('i', $notification_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $errors[] = "Notification not found.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND type = 'upcoming_election'");
            $stmt->bind_param('i', $notification_id);
            $stmt->execute();
            $stmt->close();
            header('Location: update-upcoming.php?success=' . urlencode('Upcoming election removed successfully.'));
            exit;
        } catch (Exception $e) {
            error_log("Delete notification failed: " . $e->getMessage());
            $errors[] = "Failed to remove upcoming election due to a server error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Upcoming Elections</title>
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
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e8ecef;
            border-radius: 6px;
            font-size: 16px;
        }
        textarea {
            resize: vertical;
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
            <span>Manage Upcoming Elections</span>
        </div>
        <h2>Manage Upcoming Elections</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="success">
                <p><?php echo htmlspecialchars($_GET['success']); ?></p>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="add_notification" value="1">
            <div class="form-group">
                <label for="title">Election Title</label>
                <input type="text" name="title" id="title" placeholder="Election Title" required>
            </div>
            <div class="form-group">
                <label for="date">Election Date</label>
                <input type="date" name="date" id="date" required>
            </div>
            <div class="form-group">
                <label for="description">Description (e.g., Positions)</label>
                <textarea name="description" id="description" placeholder="Include positions like President, College Governors" rows="4" required></textarea>
            </div>
            <button type="submit">Add to Upcoming Elections</button>
        </form>
        <h3>Existing Upcoming Elections</h3>
        <?php if (empty($notifications)): ?>
            <p>No upcoming and/or ongoing elections announced.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($notification['title']); ?></td>
                        <td><?php echo htmlspecialchars($notification['sent_at']); ?></td>
                        <td><?php echo htmlspecialchars($notification['content']); ?></td>
                        <td>
                            <button onclick="editNotification(<?php echo $notification['id']; ?>, '<?php echo htmlspecialchars(addslashes($notification['title']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $notification['sent_at']; ?>', '<?php echo htmlspecialchars(addslashes($notification['content']), ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <input type="hidden" name="delete_notification" value="1">
                                <button type="submit" onclick="return confirm('Are you sure you want to delete this announcement?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <h3>Edit Upcoming Election</h3>
            <form method="POST" action="">
                <input type="hidden" name="notification_id" id="edit-notification-id">
                <input type="hidden" name="update_notification" value="1">
                <div class="form-group">
                    <label for="edit-title">Election Title</label>
                    <input type="text" name="title" id="edit-title" required>
                </div>
                <div class="form-group">
                    <label for="edit-date">Election Date</label>
                    <input type="date" name="date" id="edit-date" required>
                </div>
                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea name="description" id="edit-description" rows="4" required></textarea>
                </div>
                <button type="submit">Update</button>
                <button type="button" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>
    <script>
        function editNotification(id, title, date, description) {
            document.getElementById('edit-notification-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-date').value = date;
            document.getElementById('edit-description').value = description;
            document.getElementById('edit-modal').style.display = 'block';
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