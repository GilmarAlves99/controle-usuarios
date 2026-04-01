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
   PADRÃO
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
   EXCLUIR CLIENTE
========================= */
if (isset($_GET['excluir'])) {

    $id = (int) $_GET['excluir'];

    if ($id > 0) {
        $conn->prepare("DELETE FROM pagamentos WHERE cliente_id = :id")
            ->execute([':id' => $id]);

        $conn->prepare("DELETE FROM clientes WHERE id = :id")
            ->execute([':id' => $id]);

        $mensagem = "Cliente excluído com sucesso!";
    }
}

/* =========================
   EDITAR CLIENTE
========================= */
if (isset($_GET['editar'])) {

    $id = (int) $_GET['editar'];

    if ($id > 0) {

        $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cliente) {
            $editarCliente = array_merge($editarCliente, $cliente);
        }

        $stmtPg = $conn->prepare("
    SELECT plano_id, data_vencimento 
    FROM pagamentos 
    WHERE cliente_id = :cliente_id
    ORDER BY id DESC
    LIMIT 1
");
        $stmtPg->execute([':cliente_id' => $id]);
        $pg = $stmtPg->fetch(PDO::FETCH_ASSOC);

        if ($pg) {
            $editarCliente['plano_id'] = $pg['plano_id'] ?? '';
            $editarCliente['data_vencimento'] = $pg['data_vencimento'] ?? '';
        }
    }
}

/* =========================
   ATUALIZAR CLIENTE
========================= */
if (isset($_POST['atualizar'])) {

    $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;

    $nome = trim($_POST['nome']);
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $telefone = trim($_POST['telefone']);
    $lugar = trim($_POST['lugar']);
    $ativo = $_POST['ativo'];
    $plano_id = $_POST['plano_id'];
    $data_vencimento = $_POST['data_vencimento'];

    /* =========================
       CRIAR OU ATUALIZAR CLIENTE
    ========================== */

    if ($id > 0) {
        // UPDATE
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
    } else {
        // INSERT (CRIAR CLIENTE)
        $stmt = $conn->prepare("
            INSERT INTO clientes (nome, email, telefone, lugar, ativo)
            VALUES (:nome, :email, :telefone, :lugar, :ativo)
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefone,
            ':lugar' => $lugar,
            ':ativo' => $ativo
        ]);

        // 🔥 PEGAR ID DO CLIENTE CRIADO
        $id = $conn->lastInsertId();
    }

    /* =========================
       PAGAMENTO
    ========================== */
    if (!empty($plano_id) && $id > 0) {

        $stmtPlano = $conn->prepare("SELECT valor FROM planos WHERE id = :id");
        $stmtPlano->execute([':id' => $plano_id]);
        $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

        $valor_pago = $plano ? $plano['valor'] : 0;

        $stmtInsert = $conn->prepare("
    INSERT INTO pagamentos 
    (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
    VALUES 
    (:cliente_id, :plano_id, :valor_pago, NOW(), :data_vencimento, 'Pago')
");

        $stmtInsert->execute([
            ':cliente_id' => $id,
            ':plano_id' => $plano_id,
            ':valor_pago' => $valor_pago,
            ':data_vencimento' => $data_vencimento
        ]);

        // VERIFICAR SE EXISTE PAGAMENTO ATIVO PARA O CLIENTE  
        $stmtInsert = $conn->prepare("
    INSERT INTO pagamentos 
    (cliente_id, plano_id, valor_pago, data_compra, data_vencimento, status)
    VALUES 
    (:cliente_id, :plano_id, :valor_pago, NOW(), :data_vencimento, 'Pago')
");

        $stmtInsert->execute([
            ':cliente_id' => $id,
            ':plano_id' => $plano_id,
            ':valor_pago' => $valor_pago,
            ':data_vencimento' => $data_vencimento
        ]);
    }

    $mensagem = $id ? "Cliente salvo com sucesso!" : "Erro ao salvar cliente!";
}

/* =========================
       PAGAMENTO
    ========================== */




/* =========================
   LISTAR CLIENTES
========================= */
$stmt = $conn->query("
    SELECT 
        c.id, 
        c.nome, 
        c.email,
        c.telefone, 
        c.ativo, 
        c.lugar,
        p.nome AS plano_nome,
        p.valor AS plano_valor,
        pg.data_vencimento,
        pg.status
    FROM clientes c

    LEFT JOIN pagamentos pg 
        ON pg.id = (
            SELECT id 
            FROM pagamentos 
            WHERE cliente_id = c.id 
            ORDER BY id DESC 
            LIMIT 1
        )

    LEFT JOIN planos p 
        ON pg.plano_id = p.id

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
            Clientes
        </h1>
        <div class="space-x-4">
            <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Dashboard</a>
            <a href="planos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Planos</a>
          
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
        </div>
    </div>

    <!-- FORM -->
    <form method="POST" class="bg-white p-6 rounded shadow mb-6">
        <div class="grid grid-cols-3 gap-4">

            <input type="hidden" name="id" value="<?= $editarCliente['id'] ?>">

            <input type="text" name="nome" required class="border p-2 rounded"
                value="<?= htmlspecialchars($editarCliente['nome']) ?>" placeholder="Nome">

            <input type="email" name="email" placeholder="Email (opcional)" class="border p-2 rounded"
                value="<?= htmlspecialchars($editarCliente['email']) ?>" placeholder="Email (opcional)">

            <input type="text" name="telefone" class="border p-2 rounded"
                value="<?= htmlspecialchars($editarCliente['telefone']) ?>" placeholder="Telefone (opcional)">

            <input type="text" name="lugar" class="border p-2 rounded"
                value="<?= htmlspecialchars($editarCliente['lugar']) ?>" placeholder="Lugar (opcional)">

            <select name="ativo" class="border p-2 rounded">
                <option value="1" <?= ($editarCliente['ativo'] == 1) ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($editarCliente['ativo'] == 0) ? 'selected' : '' ?>>Inativo</option>
            </select>

            <select name="plano_id" class="border p-2 rounded">
                <option value="">Selecione o plano</option>
                <?php foreach ($planos as $plano): ?>
                    <option value="<?= $plano['id'] ?>" <?= ($editarCliente['plano_id'] == $plano['id']) ? 'selected' : '' ?>>
                        <?= $plano['nome'] ?> - R$ <?= number_format($plano['valor'], 2, ",", ".") ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="data_vencimento" class="border p-2 rounded"
                value="<?= $editarCliente['data_vencimento'] ? date('Y-m-d', strtotime($editarCliente['data_vencimento'])) : '' ?>">

        </div>

        <button type="submit" name="atualizar"
            class="mt-4 bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
            Atualizar Cliente
        </button>

    </form>

    <?php if ($mensagem): ?>
        <div class="bg-green-100 text-green-700 p-3 mb-4 rounded">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>

    <!-- LISTA -->
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

                <?php foreach ($clientes as $cliente): ?>
                    <tr class="border-t">
                        <td class="p-2"><?= htmlspecialchars($cliente['nome']) ?></td>
                        <td class="p-2"><?= $cliente['email'] ? htmlspecialchars($cliente['email']) : '-' ?></td>
                        <td class="p-2"><?= $cliente['plano_nome'] ?? 'Sem plano' ?></td>
                        <td class="p-2">
                            <?= $cliente['data_vencimento'] ? date('d/m/Y', strtotime($cliente['data_vencimento'])) : '-' ?>
                        </td>
                        <td class="p-2 space-x-2">
                            <a href="?editar=<?= $cliente['id'] ?>" class="text-yellow-600">✏️</a>
                            <a href="?excluir=<?= $cliente['id'] ?>" class="text-red-600"
                                onclick="return confirm('Excluir cliente?')">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

</body>

</html>