<?php
session_start();

// If already logged in, redirect based on role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/home.php");
        exit();
    } elseif ($_SESSION['role'] === 'treasury') {
        header("Location: treasury/home.php");
        exit();
    }
}

$error = "";

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Certificate System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS (LOCAL) -->
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-md-5">

            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <h4 class="text-center mb-4">Certificate System Login</h4>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form action="login_process.php" method="POST">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Login
                            </button>
                        </div>

                    </form>

                </div>
            </div>

            <p class="text-center mt-3 text-muted small">
                &copy; <?php echo date("Y"); ?> Certificate System
            </p>

        </div>
    </div>
</div>

</body>
</html>
