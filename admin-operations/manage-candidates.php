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
$candidates = [];

// Fetch all candidates with associated details
try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.official_id, c.election_id, c.firstname, c.midname, c.lastname, c.association, c.college, c.position_id,
                c.passport, c.hostel_id, e.association AS election_association, cl.name AS college_name, ep.name AS position_name, h.name AS hostel_name
         FROM candidates c
         LEFT JOIN elections e ON c.election_id = e.election_id
         LEFT JOIN electionpositions ep ON c.position_id = ep.position_id
         LEFT JOIN colleges cl ON ep.college_id = cl.college_id
         LEFT JOIN hostels h ON ep.hostel_id = h.hostel_id"
    );
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch candidates failed: " . $e->getMessage());
    $errors[] = "Failed to load candidates.";
}

// Add candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $official_id = filter_var($_POST['official_id'], FILTER_SANITIZE_STRING);
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $midname = filter_input(INPUT_POST, 'midname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $association = filter_var($_POST['association'], FILTER_SANITIZE_STRING);
    $college = filter_var($_POST['college'], FILTER_SANITIZE_STRING);
    $position_id = filter_var($_POST['position_id'], FILTER_VALIDATE_INT);
    $passport = filter_var($_POST['passport'], FILTER_SANITIZE_STRING);
    $hostel_id = filter_var($_POST['hostel_id'], FILTER_VALIDATE_INT);

    if (empty($firstname) || empty($lastname)) {
        $errors[] = "First name and last name are required.";
    }
    if ($election_id === false || $election_id <= 0) {
        $errors[] = "Please enter a valid election ID.";
    }
    if ($position_id === false || $position_id <= 0) {
        $errors[] = "Please enter a valid position ID.";
    }
    if ($hostel_id === false) {
        $hostel_id = null; // Allow NULL for hostel_id
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO candidates (official_id, election_id, firstname, midname, lastname, association, college, position_id, passport, hostel_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$official_id, $election_id, $firstname, $midname, $lastname, $association, $college, $position_id, $passport ?: 'images/general.png', $hostel_id]);
            header('Location: manage-candidates.php?success=' . urlencode('Candidate added successfully.'));
            exit;
        } catch (PDOException $e) {
            error_log("Add candidate failed: " . $e->getMessage());
            $errors[] = "Failed to add candidate due to a server error. Ensure IDs are valid.";
        }
    }
}

// Edit candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_candidate'])) {
    $candidate_id = filter_var($_POST['candidate_id'], FILTER_VALIDATE_INT);
    $official_id = filter_var($_POST['official_id'], FILTER_SANITIZE_STRING);
    $election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $midname = filter_input(INPUT_POST, 'midname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $association = filter_var($_POST['association'], FILTER_SANITIZE_STRING);
    $college = filter_var($_POST['college'], FILTER_SANITIZE_STRING);
    $position_id = filter_var($_POST['position_id'], FILTER_VALIDATE_INT);
    $passport = filter_var($_POST['passport'], FILTER_SANITIZE_STRING);
    $hostel_id = filter_var($_POST['hostel_id'], FILTER_VALIDATE_INT);

    if (empty($firstname) || empty($lastname)) {
        $errors[] = "First name and last name are required.";
    }
    if ($election_id === false || $election_id <= 0) {
        $errors[] = "Please enter a valid election ID.";
    }
    if ($position_id === false || $position_id <= 0) {
        $errors[] = "Please enter a valid position ID.";
    }
    if ($hostel_id === false) {
        $hostel_id = null; // Allow NULL for hostel_id
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE candidates SET official_id = ?, election_id = ?, firstname = ?, midname = ?, lastname = ?, association = ?, college = ?, position_id = ?, passport = ?, hostel_id = ? WHERE id = ?"
            );
            $stmt->execute([$official_id, $election_id, $firstname, $midname, $lastname, $association, $college, $position_id, $passport ?: 'images/general.png', $hostel_id, $candidate_id]);
            header('Location: manage-candidates.php?success=' . urlencode('Candidate updated successfully.'));
            exit;
        } catch (PDOException $e) {
            error_log("Edit candidate failed: " . $e->getMessage());
            $errors[] = "Failed to update candidate due to a server error. Ensure IDs are valid.";
        }
    }
}

// Delete candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
    $candidate_id = filter_var($_POST['candidate_id'], FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        header('Location: manage-candidates.php?success=' . urlencode('Candidate removed successfully.'));
        exit;
    } catch (PDOException $e) {
        error_log("Delete candidate failed: " . $e->getMessage());
        $errors[] = "Failed to remove candidate due to a server error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates</title>
    <link rel="icon" href=".././images/System Logo.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, rgba(26, 60, 52, 0.8), rgba(34, 78, 68, 0.8)), url('images/cive.jpeg');
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
        h2, h3 {
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
        .action-bar {
            margin-bottom: 15px;
            text-align: right;
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
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e7ea;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #f9fafb;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input:focus {
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
        .new-btn, .edit-btn {
            background: #2a9d8f;
            color: #fff;
        }
        .new-btn:hover, .edit-btn:hover {
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
            h2, h3 {
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
            h2, h3 {
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
            <a href="../index.html">Home</a> <i class="fas fa-chevron-right"></i>
            <a href="../admin-dashboard.php">Admin</a> <i class="fas fa-chevron-right"></i>
            <span>Manage Candidates</span>
        </div>
        <h2>Manage Candidates</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search candidates..." onkeyup="filterTable()">
        </div>
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
        <div class="action-bar">
            <button class="new-btn" onclick="openNewModal()">New</button>
        </div>
        <h3>All Candidates</h3>
        <?php if (empty($candidates)): ?>
            <p>No candidates found.</p>
        <?php else: ?>
            <table id="candidateTable">
                <tr>
                    <th>Name</th>
                    <th>Official ID</th>
                    <th>Election ID</th>
                    <th>College</th>
                    <th>Position ID</th>
                    <th>Passport</th>
                    <th>Hostel ID</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($candidates as $candidate): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($candidate['midname'] ? $candidate['firstname'] . ' ' . $candidate['midname'] . ' ' : $candidate['firstname'] . ' ') . $candidate['lastname']); ?></td>
                        <td><?php echo htmlspecialchars($candidate['official_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($candidate['election_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($candidate['college'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($candidate['position_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($candidate['passport'] ?? 'images/general.png'); ?></td>
                        <td><?php echo htmlspecialchars($candidate['hostel_id'] ?? 'N/A'); ?></td>
                        <td>
                            <button class="edit-btn" onclick="editCandidate(<?php echo $candidate['id']; ?>, '<?php echo addslashes($candidate['official_id'] ?? ''); ?>', '<?php echo addslashes($candidate['election_id'] ?? ''); ?>', '<?php echo addslashes($candidate['firstname'] ?? ''); ?>', '<?php echo addslashes($candidate['midname'] ?? ''); ?>', '<?php echo addslashes($candidate['lastname'] ?? ''); ?>', '<?php echo addslashes($candidate['association'] ?? ''); ?>', '<?php echo addslashes($candidate['college'] ?? ''); ?>', '<?php echo addslashes($candidate['position_id'] ?? ''); ?>', '<?php echo addslashes($candidate['passport'] ?? 'images/general.png'); ?>', '<?php echo addslashes($candidate['hostel_id'] ?? ''); ?>')">Edit</button>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                <button type="submit" class="delete-btn" name="delete_candidate" onclick="return confirm('Are you sure you want to delete this candidate? This action cannot be undone.');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Modal for adding new candidate -->
    <div class="modal" id="new-modal">
        <div class="modal-content">
            <h3>Add New Candidate</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="official_id">Official ID</label>
                    <input type="text" name="official_id" id="official_id" maxlength="30">
                </div>
                <div class="form-group">
                    <label for="election_id">Election ID</label>
                    <input type="number" name="election_id" id="election_id" required>
                </div>
                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" name="firstname" id="firstname" required maxlength="30">
                </div>
                <div class="form-group">
                    <label for="midname">Middle Name</label>
                    <input type="text" name="midname" id="midname" maxlength="30">
                </div>
                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" name="lastname" id="lastname" required maxlength="30">
                </div>
                <div class="form-group">
                    <label for="association">Association</label>
                    <input type="text" name="association" id="association" maxlength="40">
                </div>
                <div class="form-group">
                    <label for="college">College</label>
                    <input type="text" name="college" id="college" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="position_id">Position ID</label>
                    <input type="number" name="position_id" id="position_id" required>
                </div>
                <div class="form-group">
                    <label for="passport">Passport Image Path</label>
                    <input type="text" name="passport" id="passport" maxlength="400" placeholder="e.g., images/candidate1.png">
                </div>
                <div class="form-group">
                    <label for="hostel_id">Hostel ID</label>
                    <input type="number" name="hostel_id" id="hostel_id">
                </div>
                <button type="submit" name="add_candidate">Add Candidate</button>
                <button type="button" onclick="closeNewModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Modal for editing candidate -->
    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <h3>Edit Candidate</h3>
            <form method="POST" action="">
                <input type="hidden" name="candidate_id" id="edit-candidate-id">
                <div class="form-group">
                    <label for="edit-official_id">Official ID</label>
                    <input type="text" name="official_id" id="edit-official_id" maxlength="30">
                </div>
                <div class="form-group">
                    <label for="edit-election_id">Election ID</label>
                    <input type="number" name="election_id" id="edit-election_id" required>
                </div>
                <div class="form-group">
                    <label for="edit-firstname">First Name</label>
                    <input type="text" name="firstname" id="edit-firstname" required maxlength="30">
                </div>
                <div class="form-group">
                    <label for="edit-midname">Middle Name</label>
                    <input type="text" name="midname" id="edit-midname" maxlength="30">
                </div>
                <div class="form-group">
                    <label for="edit-lastname">Last Name</label>
                    <input type="text" name="lastname" id="edit-lastname" required maxlength="30">
                </div>
                <div class="form-group">
                    <label for="edit-association">Association</label>
                    <input type="text" name="association" id="edit-association" maxlength="40">
                </div>
                <div class="form-group">
                    <label for="edit-college">College</label>
                    <input type="text" name="college" id="edit-college" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="edit-position_id">Position ID</label>
                    <input type="number" name="position_id" id="edit-position_id" required>
                </div>
                <div class="form-group">
                    <label for="edit-passport">Passport Image Path</label>
                    <input type="text" name="passport" id="edit-passport" maxlength="400" placeholder="e.g., images/candidate1.png">
                </div>
                <div class="form-group">
                    <label for="edit-hostel_id">Hostel ID</label>
                    <input type="number" name="hostel_id" id="edit-hostel_id">
                </div>
                <button type="submit" name="edit_candidate">Update Candidate</button>
                <button type="button" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openNewModal() {
            document.getElementById('new-modal').style.display = 'block';
            document.getElementById('official_id').value = '';
            document.getElementById('election_id').value = '';
            document.getElementById('firstname').value = '';
            document.getElementById('midname').value = '';
            document.getElementById('lastname').value = '';
            document.getElementById('association').value = '';
            document.getElementById('college').value = '';
            document.getElementById('position_id').value = '';
            document.getElementById('passport').value = '';
            document.getElementById('hostel_id').value = '';
        }

        function closeNewModal() {
            document.getElementById('new-modal').style.display = 'none';
        }

        function editCandidate(id, official_id, election_id, firstname, midname, lastname, association, college, position_id, passport, hostel_id) {
            document.getElementById('edit-candidate-id').value = id;
            document.getElementById('edit-official_id').value = official_id;
            document.getElementById('edit-election_id').value = election_id;
            document.getElementById('edit-firstname').value = firstname;
            document.getElementById('edit-midname').value = midname;
            document.getElementById('edit-lastname').value = lastname;
            document.getElementById('edit-association').value = association;
            document.getElementById('edit-college').value = college;
            document.getElementById('edit-position_id').value = position_id;
            document.getElementById('edit-passport').value = passport;
            document.getElementById('edit-hostel_id').value = hostel_id;
            document.getElementById('edit-modal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }

        function filterTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('candidateTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                for (let j = 0; j < td.length - 1; j++) {
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
            const newModal = document.getElementById('new-modal');
            const editModal = document.getElementById('edit-modal');
            if (event.target == newModal) {
                newModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        };
    </script>
</body>
</html>