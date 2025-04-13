<?php
session_start();

session_unset();
session_destroy();

header('Location: login.php?success=' . urlencode('You have been logged out.'));
exit;
?>