<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
logoutUser();
header('Location: login.php');
exit;
