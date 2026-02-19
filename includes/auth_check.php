<?php
// includes/auth_check.php

session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=Unauthorized access.");
    exit();
}
    