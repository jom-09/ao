<?php
session_start();

// Clear any previous session data
unset($_SESSION['client_info']);

$error = "";
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Client Info | Certificate System</title>
<link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-6">

<div class="card shadow-sm p-4">
<h4 class="text-center mb-4">Client Information</h4>

<?php if($error): ?>
<div class="alert alert-danger"><?php echo $error;?></div>
<?php endif; ?>

<form action="select_cert.php" method="POST">
<div class="mb-3">
<label class="form-label">First Name</label>
<input type="text" name="firstname" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Middle Name</label>
<input type="text" name="middlename" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Last Name</label>
<input type="text" name="lastname" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Address</label>
<textarea name="address" class="form-control" rows="2" required></textarea>
</div>

<div class="mb-3">
<label class="form-label">Purpose</label>
<select name="purpose" class="form-select" required>
<option value="">Select Purpose</option>
<option value="Testing">Testing</option>
<option value="Business">Business</option>
<option value="Personal">Personal</option>
</select>
</div>

<div class="d-grid">
<button type="submit" class="btn btn-primary">Next</button>
</div>

</form>
</div>
</div>
</div>
</div>

</body>
</html>
