<?php

require_once "../config/auth.php";

if (!isset($_SESSION['admin_id'])) {
 header("Location: index.php");
    exit;
}