<?php
// Root index redirect
require_once __DIR__ . '/config.php';
header('Location: ' . BASE_URL . '/portal/index.php');
exit;
