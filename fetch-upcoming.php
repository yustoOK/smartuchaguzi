<?php
include 'db.php'; // MySQLi connection

$notifications = [];
$error_message = '';

if (isset($db) && !$db->connect_error) {
    try {
        $result = $db->query(
            "SELECT title, content, sent_at 
            FROM notifications 
            WHERE type = 'upcoming_election' 
            ORDER BY sent_at ASC"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $result->free();
        } else {
            error_log("Query failed: " . $db->error);
            $error_message = "Unable to load upcoming elections due to a database error.";
        }
    } catch (Exception $e) {
        error_log("Fetch upcoming elections failed: " . $e->getMessage());
        $error_message = "Unable to load upcoming elections due to a server error.";
    }
} else {
    error_log("Database connection not available in fetch-upcoming.php");
    $error_message = "Unable to connect to the database.";
}
?>

<div class="election-list">
    <?php if (!empty($error_message)): ?>
        <div class="election-item">
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error_message); ?> Please try again later.</p>
        </div>
    <?php elseif (!empty($notifications)): ?>
        <?php foreach ($notifications as $notification): ?>
            <div class="election-item">
                <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                <p>Date: <?php echo htmlspecialchars($notification['sent_at']); ?> | <?php echo htmlspecialchars($notification['content']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="election-item">
            <h3>No Upcoming Elections</h3>
            <p>Check back later for updates on upcoming elections.</p>
        </div>
    <?php endif; ?>
</div>