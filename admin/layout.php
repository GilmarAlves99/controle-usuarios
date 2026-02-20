<?php
if (!isset($titulo)) $titulo = "Painel Admin";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title><?= $titulo ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">

<div class="flex min-h-screen">

    <aside class="w-64 bg-blue-900 text-white p-6">
        <h2 class="text-2xl font-bold mb-8">Admin</h2>

        <nav class="space-y-3">
            <a href="dashboard.php">Dashboard</a>
            <a href="clientes.php">Clientes</a>
            <a href="planos.php">Planos</a>
            <a href="pagamentos.php">Pagamentos</a>
            <a href="relatorios.php">Relatórios</a>
            <hr class="my-4 border-gray-600">
            <a href="logout.php" class="text-red-400">Sair</a>
        </nav>
    </aside>

    <main class="flex-1 p-10">
        <?= $conteudo ?>
    </main>

</div>

</body>
</html>