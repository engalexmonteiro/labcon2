<?php
require_once __DIR__ . '/includes/auth.php';
clear_session();
header('Location: login.php');
exit;
