<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";

/* =========================
   PADRÃO PARA EVITAR ERRO
========================= */
$editarPlano = [
    'id' => '',
    'nome' => '',
    'valor' => ''
];

/* =========================
   EDITAR PLANO
========================= */
if (isset($_GET['editar'])) {

    $id = (int) $_GET['editar'];

    $stmt = $conn->prepare("SELECT * FROM planos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($plano) {
        $editarPlano = $plano;
    }
}

/* =========================
   SALVAR (CRIAR OU EDITAR)
========================= */
if (isset($_POST['salvar'])) {

    $id = $_POST['id'];
    $nome = trim($_POST['nome']);
    $valor = str_replace(",", ".", $_POST['valor']);

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE planos 
            SET nome = :nome, valor = :valor
            WHERE id = :id
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':valor' => $valor,
            ':id' => $id
        ]);

        $mensagem = "Plano atualizado com sucesso!";
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO planos (nome, valor)
            VALUES (:nome, :valor)
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':valor' => $valor
        ]);

        $mensagem = "Plano criado com sucesso!";
    }

    // Limpa formulário após salvar
    $editarPlano = [
        'id' => '',
        'nome' => '',
        'valor' => ''
    ];
}

/* =========================
   EXCLUIR PLANO
========================= */
if (isset($_GET['excluir'])) {

    $id = (int) $_GET['excluir'];

    $stmt = $conn->prepare("DELETE FROM planos WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $mensagem = "Plano excluído com sucesso!";
}

/* =========================
   LISTAR PLANOS
========================= */
$stmt = $conn->query("SELECT * FROM planos ORDER BY id DESC");
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Planos</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
<div class="bg-white shadow p-4 flex justify-between items-center">
   <h1 class="text-2xl font-bold mb-4">Gerenciar Planos</h1>
    <div class="space-x-4">
        <a href="clientes.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Clientes</a>
        <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Dashboard</a>
        <a href="pagamentos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Pagamentos</a>
        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
    </div>
</div>





<!-- FORMULÁRIO -->
<form method="POST" class="bg-white p-6 rounded shadow mb-6">
<div class="grid grid-cols-3 gap-4">

<input type="hidden" name="id" value="<?= $editarPlano['id'] ?>">

<input type="text" name="nome" placeholder="Nome do Plano"
required class="border p-2 rounded"
value="<?= $editarPlano['nome'] ?>">

<input type="text" name="valor" placeholder="Valor (ex: 29.90)"
required class="border p-2 rounded"
value="<?= $editarPlano['valor'] ?>">

</div>

<button type="submit" name="salvar"
class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">
<?= $editarPlano['id'] ? 'Atualizar Plano' : 'Criar Plano' ?>
</button>

</form>
<?php if ($mensagem): ?>
<div class="bg-green-100 text-green-700 p-3 mb-4 rounded">
    <?= $mensagem ?>
</div>
<?php endif; ?>
<!-- LISTAGEM -->
<div class="bg-white p-6 rounded shadow">
<table class="w-full border">
<thead>
<tr class="bg-gray-200">
<th class="p-2">ID</th>
<th class="p-2">Nome</th>
<th class="p-2">Valor</th>
<th class="p-2">Ações</th>
</tr>
</thead>
<tbody>

<?php foreach ($planos as $plano): ?>

<tr class="border-t">
<td class="p-2"><?= $plano['id'] ?></td>
<td class="p-2"><?= $plano['nome'] ?></td>
<td class="p-2 font-semibold">
R$ <?= number_format($plano['valor'], 2, ",", ".") ?>
</td>
<td class="p-2">

<a href="?editar=<?= $plano['id'] ?>"
class="text-yellow-600 mr-3">Editar</a>

<a href="?excluir=<?= $plano['id'] ?>"
class="text-red-600"
onclick="return confirm('Tem certeza que deseja excluir este plano?')">
Excluir
</a>

</td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

</body>
</html>