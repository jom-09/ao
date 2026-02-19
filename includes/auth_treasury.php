<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role']!=='treasury'){
    header("Location: ../index.php?error=Unauthorized access.");
    exit();
}
