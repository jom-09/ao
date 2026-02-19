<!DOCTYPE html>
<html>
<head>
    <title>Import</title>
</head>
<body>

<h2>Import Excel File</h2>

<?php
// Show messages
if (isset($_GET['success'])) {
    echo "<p style='color:green;'>Import successful!</p>";
}

if (isset($_GET['error'])) {
    echo "<p style='color:red;'>" . htmlspecialchars($_GET['error']) . "</p>";
}
?>

<form action="import_logic.php" method="POST" enctype="multipart/form-data">

    <label>Select Barangay:</label><br><br>

    <select name="barangay" required>
        <option value="">-- Select Barangay --</option>
        <option value="alicia">Alicia</option>
        <option value="cabugao">Cabugao</option>
        <option value="dagupan">Dagupan</option>
        <option value="diodol">Diodol</option>
        <option value="dumabel">Dumabel</option>
        <option value="dungo">Dungo</option>
        <option value="guinalbin">Guinalbin</option>
        <option value="nagabgaban">Nagabgaban</option>
        <option value="palacian">Palacian</option>
        <option value="pinaripad_norte">Pinaripad Norte</option>
        <option value="pinaripad_sur">Pinaripad Sur</option>
        <option value="progreso">Progreso</option>
        <option value="ramos">Ramos</option>
        <option value="rangayan">Rang-Ayan</option>
        <option value="san_antonio">San Antonio</option>
        <option value="san_benigno">San Benigno</option>
        <option value="san_francisco">San Francisco</option>
        <option value="san_leonardo">San Leonardo</option>
        <option value="san_manuel">San Manuel</option>
        <option value="san_ramon">San Ramon</option>
        <option value="victoria">Victoria</option>
        <option value="villa_pagaduan">Villa Pagaduan</option>
        <option value="villa_santiago">Villa Santiago</option>
        <option value="villa_ventura">Villa Ventura</option>
    </select>

    <br><br>

    <label>Select Excel File:</label><br><br>

    <input type="file" name="excel" accept=".xlsx,.xls" required>

    <br><br>

    <button type="submit" name="import">Import Data</button>

</form>


</body>
</html>
