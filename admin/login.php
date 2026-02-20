<?php
session_start();
require_once "../config/db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (!empty($email) && !empty($senha)) {

        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($senha, $admin['senha'])) {

            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];

            header("Location: dashboard.php");
            exit;

        } else {
            $erro = "Email ou senha inválidos.";
        }

    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Login Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

<div class="bg-white p-8 rounded-2xl shadow-xl w-96">

    <h1 class="text-2xl font-bold text-center mb-6">
        Painel Administrativo
    </h1>

    <?php if (!empty($erro)) : ?>
        <div class="bg-red-100 text-red-600 p-3 rounded mb-4 text-sm">
            <?= $erro ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input 
            type="email" 
            name="email" 
            placeholder="Email"
            class="w-full border p-2 mb-4 rounded-lg"
            required
        >

        <input 
            type="password" 
            name="senha" 
            placeholder="Senha"
            class="w-full border p-2 mb-4 rounded-lg"
            required
        >

        <button 
            type="submit"
            class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700"
        >
            Entrar
        </button>

    </form>

</div>

</body>
</html>