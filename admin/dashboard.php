<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";

// Buscar clientes com plano ativo
$stmt = $conn->query("
    SELECT 
        c.id, 
        c.nome, 
        c.telefone, 
        c.ativo, 
        c.created_at, 
        c.lugar,
        p.nome AS plano_nome,
        p.valor AS plano_valor,
        pg.data_vencimento
    FROM clientes c
    LEFT JOIN pagamentos pg 
        ON c.id = pg.cliente_id AND pg.status = 'Ativo'
    LEFT JOIN planos p 
        ON pg.plano_id = p.id
    ORDER BY c.nome ASC
");

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="bg-white shadow p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold">
        Bem-vindo, <?= htmlspecialchars($_SESSION['admin_nome']); ?>
    </h1>
    <div class="space-x-4">
        <a href="clientes.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Clientes</a>
        <a href="planos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Planos</a>
        <a href="pagamentos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Pagamentos</a>
        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
    </div>
</div>

<div class="p-8">
    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-lg font-semibold mb-4">Todos os Usuários</h2>

        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2">ID</th>
                    <th class="p-2">Nome</th>
                    <th class="p-2">Telefone</th>
                    <th class="p-2">Status</th>
                    <th class="p-2">Criado em</th>
                    <th class="p-2">Lugar</th>
                    <th class="p-2">Plano</th>
                    <th class="p-2">Valor</th>
                    <th class="p-2">Vencimento</th>
                    <th class="p-2">Ações</th>
                </tr>
            </thead>
            <tbody>

<?php foreach ($usuarios as $user): 

    $vencimento = $user['data_vencimento'] ?? null;
    $classVenc = '';
    $textoVenc = '-';

    if ($vencimento) {

        $hoje = new DateTime();
        $dataV = new DateTime($vencimento);
        $diff = (int)$hoje->diff($dataV)->format("%r%a");

        // 🔥 REGRA DE CORES
        if ($diff <= 2) {
            $classVenc = 'bg-red-500 text-white';
        } elseif ($diff <= 5) {
            $classVenc = 'bg-purple-500 text-white';
        } elseif ($diff <= 10) {
            $classVenc = 'bg-yellow-400';
        }

        $textoVenc = date("d/m/Y", strtotime($vencimento));
    }
?>

<tr class="border-b hover:bg-gray-50">
    <td class="py-2 px-4"><?= $user['id']; ?></td>
    <td class="py-2 px-4"><?= htmlspecialchars($user['nome']); ?></td>
    <td class="py-2 px-4"><?= htmlspecialchars($user['telefone']); ?></td>

    <td class="py-2 px-4">
        <span class="px-2 py-1 rounded text-white text-sm <?= $user['ativo'] ? 'bg-green-500' : 'bg-red-500'; ?>">
            <?= $user['ativo'] ? "Ativo" : "Inativo"; ?>
        </span>
    </td>

    <td class="py-2 px-4">
        <?= date("d/m/Y H:i", strtotime($user['created_at'])); ?>
    </td>

    <td class="py-2 px-4"><?= htmlspecialchars($user['lugar']); ?></td>
    <td class="py-2 px-4"><?= htmlspecialchars($user['plano_nome'] ?? 'Sem plano'); ?></td>

    <td class="py-2 px-4">
        <?= isset($user['plano_valor']) ? 'R$ ' . number_format($user['plano_valor'], 2, ',', '.') : '-'; ?>
    </td>

    <td class="py-2 px-4 text-center font-semibold <?= $classVenc ?>">
        <?= $textoVenc ?>
    </td>

    <td class="py-2 px-4">
        <?php if ($user['plano_nome']): ?>
            <button 
                class="pagar-btn bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded"
                data-id="<?= $user['id'] ?>">
                Pagar
            </button>
        <?php endif; ?>
    </td>
</tr>

<?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.pagar-btn').forEach(btn => {
    btn.addEventListener('click', () => {

        if (!confirm("Confirmar pagamento?")) return;

        const clienteId = btn.dataset.id;

        fetch('pagar_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'cliente_id=' + clienteId
        })
        .then(res => res.json())
        .then(data => {
            alert(data.msg);
            if (data.success) {
                location.reload();
            }
        })
        .catch(err => console.error(err));
    });
});
</script>

</body>
</html>