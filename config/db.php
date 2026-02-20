<?php
$host = "localhost";
$db   = "paineluser";
$user = "postgres";
$pass = '$akata32HJ';

$conn = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>