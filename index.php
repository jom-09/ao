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
            <div class="text-center mb-4">
                <div class="brand-icon mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-shield-check" viewBox="0 0 16 16">
                        <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
                        <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                    </svg>
                </div>
                <h4 class="fw-bold mb-1">T.R.A.C.S</h4>
                <p class="text-muted small">Tracking & Records Administration Certification System</p>
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
                    &copy; <?php echo date("Y"); ?> T.R.A.C.S
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