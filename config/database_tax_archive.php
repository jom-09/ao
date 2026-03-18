<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$taxHost = "localhost";
$taxUser = "root";
$taxPass = "";
$taxDb   = "db_tax_archive";

$taxConn = new mysqli($taxHost, $taxUser, $taxPass, $taxDb);
$taxConn->set_charset("utf8mb4");