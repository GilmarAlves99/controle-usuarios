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
   BUSCAR PLANOS
========================= */
$stmtPlanos = $conn->query("SELECT id, nome, valor FROM planos ORDER BY nome ASC");
$planos = $stmtPlanos->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   PADRÃO PARA EVITAR ERRO
========================= */
$editarCliente = [
    'id' => '',
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'lugar' => '',
    'ativo' => 1,
    'plano_id' => '',
    'data_vencimento' => ''
];

/* =========================
   EDITAR CLIENTE
========================= */
if (isset($_GET['editar'])) {

    $id = (int) $_GET['editar'];

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $editarCliente = array_merge($editarCliente, $cliente);
    }

    $stmtPg = $conn->prepare("
        SELECT plano_id, data_vencimento 
        FROM pagamentos 
        WHERE cliente_id = :cliente_id AND status = 'Ativo' 
        LIMIT 1
    ");
    $stmtPg->execute([':cliente_id' => $id]);
    $pg = $stmtPg->fetch(PDO::FETCH_ASSOC);

    if ($pg) {
        $editarCliente['plano_id'] = $pg['plano_id'] ?? '';
        $editarCliente['data_vencimento'] = $pg['data_vencimento'] ?? '';
    }
}

/* =========================
   ATUALIZAR CLIENTE
========================= */
if (isset($_POST['atualizar'])) {

    $id = (int) $_POST['id'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $lugar = trim($_POST['lugar']);
    $ativo = $_POST['ativo'];
    $plano_id = $_POST['plano_id'];
    $data_vencimento = $_POST['data_vencimento'];

    $stmt = $conn->prepare("
        UPDATE clientes 
        SET nome = :nome, email = :email, telefone = :telefone, ativo = :ativo, lugar = :lugar
        WHERE id = :id
    ");
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone,
        ':ativo' => $ativo,
        ':lugar' => $lugar,
        ':id' => $id
    ]);

    if ($plano_id) {

        $stmtCheck = $conn->prepare("
            SELECT id FROM pagamentos 
            WHERE cliente_id = :cliente_id AND status = 'Ativo' LIMIT 1
        ");
        $stmtCheck->execute([':cliente_id' => $id]);
        $pgExist = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($pgExist) {

            $stmtUpdate = $conn->prepare("
                UPDATE pagamentos 
                SET plano_id = :plano_id,
                    data_vencimento = :data_vencimento
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':plano_id' => $plano_id,
                ':data_vencimento' => $data_vencimento,
                ':id' => $pgExist['id']
            ]);
        } else {

            $stmtInsert = $conn->prepare("
                INSERT INTO pagamentos (cliente_id, plano_id, data_vencimento, status)
                VALUES (:cliente_id, :plano_id, :data_vencimento, 'Ativo')
            ");
            $stmtInsert->execute([
                ':cliente_id' => $id,
                ':plano_id' => $plano_id,
                ':data_vencimento' => $data_vencimento
            ]);
        }
    }

    $mensagem = "Cliente atualizado com sucesso!";
}

/* =========================
   LISTAR CLIENTES
========================= */
$stmt = $conn->query("
    SELECT 
        c.id, c.nome, c.email, c.telefone, c.ativo, c.lugar,
        p.nome AS plano_nome,
        pg.data_vencimento
    FROM clientes c
    LEFT JOIN pagamentos pg ON c.id = pg.cliente_id AND pg.status = 'Ativo'
    LEFT JOIN planos p ON pg.plano_id = p.id
    ORDER BY c.nome ASC
");

$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Clientes</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
<div class="bg-white shadow p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold">
        Editar Clientes - <?= htmlspecialchars($_SESSION['admin_nome']); ?>
    </h1>
    <div class="space-x-4">
        <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">DashBoard</a>
        <a href="planos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Planos</a>
        <a href="pagamentos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Pagamentos</a>
        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
    </div>
</div>


<!-- FORMULÁRIO -->
<form method="POST" class="bg-white p-6 rounded shadow mb-6">
<div class="grid grid-cols-3 gap-4">

<input type="hidden" name="id" value="<?= $editarCliente['id'] ?>">

<input type="text" name="nome" placeholder="Nome" required class="border p-2 rounded"
value="<?= $editarCliente['nome'] ?>">

<input type="email" name="email" placeholder="Email" required class="border p-2 rounded"
value="<?= $editarCliente['email'] ?>">

<input type="text" name="telefone" placeholder="Telefone" class="border p-2 rounded"
value="<?= $editarCliente['telefone'] ?>">

<input type="text" name="lugar" placeholder="Lugar" class="border p-2 rounded"
value="<?= $editarCliente['lugar'] ?>">

<select name="ativo" class="border p-2 rounded">
<option value="1" <?= ($editarCliente['ativo'] == 1) ? 'selected' : '' ?>>Ativo</option>
<option value="0" <?= ($editarCliente['ativo'] == 0) ? 'selected' : '' ?>>Inativo</option>
</select>

<select name="plano_id" class="border p-2 rounded">
<option value="">Selecione o plano</option>
<?php foreach ($planos as $plano): ?>
<option value="<?= $plano['id'] ?>"
<?= ($editarCliente['plano_id'] == $plano['id']) ? 'selected' : '' ?>>
<?= $plano['nome'] ?> - R$ <?= number_format($plano['valor'],2,",",".") ?>
</option>
<?php endforeach; ?>
</select>

<input type="date" name="data_vencimento"
class="border p-2 rounded"
value="<?= $editarCliente['data_vencimento'] ? date('Y-m-d', strtotime($editarCliente['data_vencimento'])) : '' ?>">

</div>

<button type="submit" name="atualizar"
class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded">
Atualizar Cliente
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
<th class="p-2">Nome</th>
<th class="p-2">Email</th>
<th class="p-2">Plano</th>
<th class="p-2">Vencimento</th>
<th class="p-2">Ações</th>
</tr>
</thead>
<tbody>

<?php foreach ($clientes as $cliente):

$class = '';
if ($cliente['data_vencimento']) {

    $hoje = new DateTime();
    $dataV = new DateTime($cliente['data_vencimento']);
    $diff = (int)$hoje->diff($dataV)->format("%r%a");

    if ($diff <= 2) $class = 'bg-red-500 text-white';
    elseif ($diff <= 5) $class = 'bg-purple-500 text-white';
    elseif ($diff <= 10) $class = 'bg-yellow-400';
}
?>

<tr class="border-t">
<td class="p-2"><?= $cliente['nome'] ?></td>
<td class="p-2"><?= $cliente['email'] ?></td>
<td class="p-2"><?= $cliente['plano_nome'] ?? 'Sem plano' ?></td>
<td class="p-2 font-semibold <?= $class ?>">
<?= $cliente['data_vencimento'] ? date('d/m/Y', strtotime($cliente['data_vencimento'])) : '-' ?>
</td>
<td class="p-2">
<a href="?editar=<?= $cliente['id'] ?>" class="text-yellow-600">Editar</a>
</td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

</body>
</html>