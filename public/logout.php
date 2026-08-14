<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

deconnecter();
header('Location: login.php');
exit;
