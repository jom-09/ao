<?php
session_start();
require_once "config/database.php";

// Only allow POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

// Validate inputs
if (empty($_POST['username']) || empty($_POST['password'])) {
    header("Location: index.php?error=Please fill in all fields.");
    exit();
}

$username = trim($_POST['username']);
$password = trim($_POST['password']);

// Prepare statement (Prevents SQL Injection)
$stmt = $conn->prepare("SELECT id, fullname, username, password, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    // Verify hashed password
    if (password_verify($password, $user['password'])) {

        // Regenerate session ID (Security best practice)
        session_regenerate_id(true);

        // Store session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: admin/home.php");
            exit();
        } elseif ($user['role'] === 'treasury') {
            header("Location: treasury/home.php");
            exit();
        } else {
            header("Location: index.php?error=Unauthorized role.");
            exit();
        }

    } else {
        header("Location: index.php?error=Invalid username or password.");
        exit();
    }

} else {
    header("Location: index.php?error=Invalid username or password.");
    exit();
}

$stmt->close();
$conn->close();
