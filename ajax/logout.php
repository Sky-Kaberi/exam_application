<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

logoutApplicantSession();
session_destroy();

header('Location: ../public/login.php');
exit;
