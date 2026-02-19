<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_ao";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // Validate role
    if ($role !== 'admin' && $role !== 'treasury') {
        die("Invalid role selected.");
    }

    // Check if username already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Username already exists!');</script>";
    } else {

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $username, $hashed_password, $role);

        if ($stmt->execute()) {
            echo "<script>alert('User created successfully!');</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $check->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>
</head>
<body>

<h2>Create User</h2>

<form method="POST">
    <label>Full Name:</label><br>
    <input type="text" name="fullname" required><br><br>

    <label>Username:</label><br>
    <input type="text" name="username" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <label>Role:</label><br>
    <select name="role" required>
        <option value="admin">Admin</option>
        <option value="treasury">Treasury</option>
    </select><br><br>

    <button type="submit">Create User</button>
</form>

</body>
</html>
