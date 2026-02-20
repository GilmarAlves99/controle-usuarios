<?php
require_once "../config/db.php";

try {
    $conn->query("SELECT 1");
    echo "Conectado com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}