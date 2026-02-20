<?php
require_once "../config/db.php";

$email = "gilmaralves914@email.com";

$stmt = $conn->prepare("SELECT * FROM admin WHERE email = :email");
$stmt->bindParam(":email", $email);
$stmt->execute();

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

var_dump($admin);