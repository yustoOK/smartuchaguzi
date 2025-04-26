<?php
date_default_timezone_set('Africa/Dar_es_Salaam');
include 'db.php'; 

$notifications = [];
$error_message = '';

if (isset($db) && !$db->connect_error) {
    $result = $db->query(
        "SELECT title, content, sent_at 
         FROM notifications 
         WHERE type = 'upcoming_election' 
         AND user_id IS NULL 
         AND sent_at >= NOW() 
         ORDER BY sent_at DESC"
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
} else {
    error_log("Database connection not available in fetch-upcoming.php");
    $error_message = "Unable to connect to the database.";
}
?>

<style>
    .election-list {
        margin: 20px 0;
    }
    .election-item {
        background: rgba(255, 255, 255, 0.9);
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .election-item h3 {
        margin: 0 0 10px;
        color: #1a3c34;
        font-size: 1.2em;
    }
    .election-item p {
        margin: 0;
        color: #2d3748;
        font-size: 0.95em;
    }
</style>

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
                <p>Date: <?php echo htmlspecialchars(date('F j, Y', strtotime($notification['sent_at']))); ?> | <?php echo htmlspecialchars($notification['content']); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="election-item">
            <h3>No Upcoming Elections</h3>
            <p>Check back later for updates on upcoming elections.</p>
        </div>
    <?php endif; ?>
</div>