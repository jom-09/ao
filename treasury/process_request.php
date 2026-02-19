<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

// Accept via POST form (control number)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_id'], $_POST['control_number'])){
    $request_id = intval($_POST['accept_id']);
    $control_number = trim($_POST['control_number']);

    if(!$control_number){
        header("Location: home.php?error=Control Number is required");
        exit();
    }

    $stmt = $conn->prepare("UPDATE requests SET status='PAID', control_number=?, paid_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $control_number, $request_id);
    $stmt->execute();
    $stmt->close();

    header("Location: home.php?success=Request marked as PAID");
    exit();
}

// Decline via GET param
if(isset($_GET['decline'])){
    $request_id = intval($_GET['decline']);
    $stmt = $conn->prepare("UPDATE requests SET status='DECLINED', paid_at=NOW() WHERE id=?");
    $stmt->bind_param("i",$request_id);
    $stmt->execute();
    $stmt->close();

    header("Location: home.php?success=Request Declined");
    exit();
}

// Fetch request details if 'id' is set
if(isset($_GET['id'])){
    $request_id = intval($_GET['id']);

    $sql = "SELECT r.id,
                   CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
                   c.purpose,
                   r.total_amount
            FROM requests r
            JOIN clients c ON r.client_id=c.id
            WHERE r.id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i",$request_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows !== 1){
        header("Location: home.php?error=Request not found");
        exit();
    }
    $request = $res->fetch_assoc();
    $stmt->close();

    // Fetch certificates for this request
    $cert_sql = "SELECT ri.id, c.certificate_name, ri.price_at_time
                 FROM request_items ri
                 JOIN certificates c ON ri.certificate_id = c.id
                 WHERE ri.request_id=?";
    $stmt2 = $conn->prepare($cert_sql);
    $stmt2->bind_param("i",$request_id);
    $stmt2->execute();
    $cert_result = $stmt2->get_result();
    $certificates = [];
    while($row = $cert_result->fetch_assoc()){
        $certificates[] = $row;
    }
    $stmt2->close();
} else {
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Process Request | Treasury</title>
<link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-6">
<div class="card shadow-sm p-4">
<h4 class="mb-4">Process Request #<?php echo $request['id']; ?></h4>
<p><strong>Client:</strong> <?php echo htmlspecialchars($request['fullname']); ?></p>
<p><strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?></p>

<h5 class="mt-3">Certificates</h5>
<ul class="list-group mb-3">
<?php foreach($certificates as $cert): ?>
<li class="list-group-item d-flex justify-content-between align-items-center">
<?php echo htmlspecialchars($cert['certificate_name']); ?>
<span>₱<?php echo number_format($cert['price_at_time'],2); ?></span>
</li>
<?php endforeach; ?>
<li class="list-group-item d-flex justify-content-between align-items-center">
<strong>Total</strong>
<strong>₱<?php echo number_format($request['total_amount'],2); ?></strong>
</li>
</ul>

<!-- Accept Form -->
<form method="POST" onsubmit="return confirm('Are you sure you want to accept this request?');">
<div class="mb-3">
<label class="form-label">Control Number</label>
<input type="text" name="control_number" class="form-control" required>
</div>
<input type="hidden" name="accept_id" value="<?php echo $request['id']; ?>">
<div class="d-grid mb-2">
<button type="submit" class="btn btn-success">Accept & Save</button>
</div>
</form>

<!-- Decline Button -->
<a href="process_request.php?decline=<?php echo $request['id'];?>" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to decline this request?');">Decline</a>

<a href="home.php" class="btn btn-secondary w-100 mt-3">Back to Dashboard</a>

</div>
</div>
</div>

</body>
</html>
