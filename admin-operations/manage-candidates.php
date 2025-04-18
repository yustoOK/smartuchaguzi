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
$candidates = [];

try {
    $elections = $pdo->query(
        "SELECT e.id, e.association, c.name AS college 
        FROM elections e JOIN colleges c ON e.college_id = c.id ORDER BY e.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch elections failed: " . $e->getMessage());
    $errors[] = "Failed to load elections.";
}

if (isset($_GET['election_id'])) {
    $election_id = filter_var($_GET['election_id'], FILTER_VALIDATE_INT);
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

        $stmt = $pdo->prepare(
            "SELECT c.id, c.user_id, u.fname, u.lname, ep.name AS position_name 
            FROM candidates c 
            JOIN users u ON c.user_id = u.id 
            JOIN electionpositions ep ON c.position_id = ep.id 
            WHERE c.election_id = ?"
        );
        $stmt->execute([$election_id]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch election details failed: " . $e->getMessage());
        $errors[] = "Failed to load election details.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);
    $position_id = filter_var($_POST['position_id'], FILTER_VALIDATE_INT);
    $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

    try {
        $stmt = $pdo->prepare("SELECT hostel_id, election_id FROM electionpositions WHERE id = ?");
        $stmt->execute([$position_id]);
        $position = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$position || $position['election_id'] != $election_id) {
            $errors[] = "Invalid position selected.";
        } else {
            $stmt = $pdo->prepare("SELECT role, college_id, hostel_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = "Selected user does not exist.";
            } else {
                $stmt = $pdo->prepare("SELECT college_id FROM elections WHERE id = ?");
                $stmt->execute([$election_id]);
                $election_college = $stmt->fetchColumn();

                if ($user['college_id'] != $election_college) {
                    $errors[] = "User must belong to the election's college.";
                }
                if ($user['role'] === 'observer') {
                    $errors[] = "Observers cannot be candidates.";
                }
                if ($position['hostel_id'] && ($user['hostel_id'] != $position['hostel_id'] || $user['role'] !== 'voter')) {
                    $errors[] = "Only students from the specified hostel can run for this position.";
                }
                if ($user['role'] === 'teacher-voter' && $position['hostel_id']) {
                    $errors[] = "Teachers cannot run for hostel-specific positions.";
                }

                $stmt = $pdo->prepare("SELECT id FROM candidates WHERE election_id = ? AND user_id = ?");
                $stmt->execute([$election_id, $user_id]);
                if ($stmt->fetch()) {
                    $errors[] = "User is already a candidate in this election.";
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "INSERT INTO candidates (election_id, position_id, user_id, created_at) 
                VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$election_id, $position_id, $user_id]);
            header('Location: manage-candidates.php?election_id=' . $election_id . '&success=' . urlencode('Candidate added successfully.'));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Add candidate failed: " . $e->getMessage());
        $errors[] = "Failed to add candidate due to a server error.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
    $candidate_id = filter_var($_POST['candidate_id'], FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        header('Location: manage-candidates.php?election_id=' . $election_id . '&success=' . urlencode('Candidate removed successfully.'));
        exit;
    } catch (PDOException $e) {
        error_log("Delete candidate failed: " . $e->getMessage());
        $errors[] = "Failed to remove candidate due to a server error.";
    }
}

$eligible_users = [];
if ($election) {
    try {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.fname, u.lname, u.role 
            FROM users u 
            WHERE u.college_id = ? AND u.role IN ('voter', 'teacher-voter')"
        );
        $stmt->execute([$election['college_id']]);
        $eligible_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch eligible users failed: " . $e->getMessage());
        $errors[] = "Failed to load eligible users.";
    }
}
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates</title>
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
        input, select {
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
            <span>Manage Candidates</span>
        </div>
        <h2>Manage Candidates</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>
        <?php if (empty($elections)): ?>
            <p>No elections available.</p>
        <?php else: ?>
            <div class="form-group">
                <label for="election_id">Select Election</label>
                <select name="election_id" onchange="window.location.href='manage-candidates.php?election_id=' + this.value">
                    <option value="">Select an Election</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $election && $election['id'] == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['association'] . ' - ' . $e['college']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($election): ?>
            <form method="POST" action="">
                <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                <div class="form-group">
                    <label for="position_id">Position</label>
                    <select name="position_id" id="position_id" required>
                        <option value="">Select Position</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?php echo $position['id']; ?>">
                                <?php echo htmlspecialchars($position['name']) . ($position['hostel_id'] ? ' (Hostel-specific)' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_id">User</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($eligible_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname'] . ' (' . $user['role'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_candidate">Add Candidate</button>
            </form>
            <h3>Current Candidates</h3>
            <?php if (empty($candidates)): ?>
                <p>No candidates assigned.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']); ?></td>
                            <td><?php echo htmlspecialchars($candidate['position_name']); ?></td>
                            <td>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                    <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                                    <button type="submit" name="delete_candidate" onclick="return confirm('Are you sure?')">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>