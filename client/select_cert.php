<?php
session_start();
require_once "../config/database.php";

// Save client info from previous page
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $address = trim($_POST['address']);
    $purpose = trim($_POST['purpose']);

    if(!$firstname || !$lastname || !$address || !$purpose){
        header("Location: index.php?error=Please fill all required fields");
        exit();
    }

    $_SESSION['client_info'] = [
        'firstname'=>$firstname,
        'middlename'=>$middlename,
        'lastname'=>$lastname,
        'address'=>$address,
        'purpose'=>$purpose
    ];
}else{
    if(!isset($_SESSION['client_info'])){
        header("Location: index.php");
        exit();
    }
}

// Fetch all active certificates
$certificates = [];
$sql = "SELECT * FROM certificates WHERE status='active'";
$result = $conn->query($sql);
while($row=$result->fetch_assoc()){
    $certificates[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Certificates | Certificate System</title>
<link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<script>
document.addEventListener('DOMContentLoaded', function(){
    const checkboxes = document.querySelectorAll('.cert-checkbox');
    const totalSpan = document.getElementById('totalAmount');

    function updateTotal(){
        let total = 0;
        checkboxes.forEach(cb => {
            if(cb.checked) total += parseFloat(cb.dataset.price);
        });
        totalSpan.textContent = total.toFixed(2);
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateTotal));
});
</script>
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="row">
<div class="col-md-12">

<h4 class="mb-4">Select Certificates</h4>

<form action="submit_request.php" method="POST" onsubmit="return confirm('Are you sure you want to submit?');">
<div class="row">
<?php foreach($certificates as $cert): ?>
<div class="col-md-4 mb-3">
<div class="card h-100 shadow-sm p-3">
<h5><?php echo htmlspecialchars($cert['certificate_name']); ?></h5>
<p class="text-muted"><?php echo htmlspecialchars($cert['description']); ?></p>
<p>₱<?php echo number_format($cert['price'],2); ?></p>
<input type="checkbox" name="certificates[]" value="<?php echo $cert['id']; ?>" class="cert-checkbox" data-price="<?php echo $cert['price']; ?>">
</div>
</div>
<?php endforeach; ?>
</div>

<div class="mb-3 text-end">
<strong>Total: ₱<span id="totalAmount">0.00</span></strong>
</div>

<div class="d-grid">
<button type="submit" class="btn btn-success">Submit Request</button>
</div>
</form>

</div>
</div>
</div>

</body>
</html>
