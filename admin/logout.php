<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
sessionStart();
session_destroy();
header('Location: /admin/login.php');
exit;
