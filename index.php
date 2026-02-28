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

    <!-- Framework CSS (Your specified paths) -->
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/bootstrap/css/datatables.min.css">
    <link href="assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            
            <!-- Header -->
            <div class="text-center mb-4 login-header">
    <div class="brand-icon">
        <img src="assets/img/logo3.png" alt="iRPTAS Logo" class="brand-logo-img">
    </div>

    <h4 class="fw-bold mb-1">iRPTAS</h4>
    <p class="text-muted small">
        Integrated Real Property Tax Assessment and Collection System
    </p>
</div>

            <!-- Error Alert -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login_process.php" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" name="username" class="form-control" id="username" placeholder="Username" required>
                    <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-login btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                    
                    <!-- Client Button -->
                    <a href="client/index.php" class="btn btn-client btn-lg">
                        <i class="bi bi-person-badge me-2"></i>Client
                    </a>
                </div>
            </form>

            <!-- Footer -->
            <div class="text-center mt-4 pt-3 border-top">
                <p class="text-muted small mb-0">
                    &copy; <?php echo date("Y"); ?> iRPTAS
                </p>
            </div>

        </div>
        
        <!-- Decorative Bottom Bar -->
        <div class="card-footer-custom"></div>
    </div>
    
    <!-- Background Decoration -->
    <div class="bg-decoration"></div>
</div>

<!-- Framework JS -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>