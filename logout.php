<?php
session_start();

session_unset();
session_destroy();

header('Location: login.php?success=' . urlencode('Success'));
exit;
?>