<?php
session_start();
require_once "../config/auth.php";
require_once "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

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
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// TOTAL FATURADO
// =========================
$stmtTotal = $conn->query("SELECT SUM(valor_pago) as total FROM pagamentos WHERE status = 'Pago'");
$totalValor = (float)$stmtTotal->fetchColumn();

// =========================
// CONTAGEM CLIENTES
// =========================
$queryAtivos = $conn->query("SELECT COUNT(*) FROM clientes WHERE ativo = true");
$clientesAtivos = (int)$queryAtivos->fetchColumn();

$queryInativos = $conn->query("SELECT COUNT(*) FROM clientes WHERE ativo = false");
$clientesInativos = (int)$queryInativos->fetchColumn();

// =========================
// FATURAMENTO POR MÊS
// =========================
$stmtGrafico = $conn->query("
    SELECT TO_CHAR(data_compra, 'YYYY-MM') AS mes, SUM(valor_pago) AS total
    FROM pagamentos
    WHERE status = 'Pago'
    GROUP BY mes
    ORDER BY mes ASC
");
$dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$valores = [];

$mesesFormatados = [
    '01' => 'Jan',
    '02' => 'Fev',
    '03' => 'Mar',
    '04' => 'Abr',
    '05' => 'Mai',
    '06' => 'Jun',
    '07' => 'Jul',
    '08' => 'Ago',
    '09' => 'Set',
    '10' => 'Out',
    '11' => 'Nov',
    '12' => 'Dez'
];

foreach ($dadosGrafico as $d) {
    $mesNumero = substr($d['mes'], 5, 2);
    $labels[] = $mesesFormatados[$mesNumero] . '/' . substr($d['mes'], 0, 4);
    $valores[] = (float)$d['total'];
}

// =========================
// PREVISÃO MÊS SEGUINTE
// =========================
$stmtPrevisto = $conn->query("
    SELECT SUM(p.valor) as total_previsto
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
    WHERE c.ativo = true
");
$totalPrevisto = (float)$stmtPrevisto->fetchColumn();

// Valor médio por cliente ativo
$valorMedio = $clientesAtivos > 0 ? $totalPrevisto / $clientesAtivos : 0;


// =========================
// PROJEÇÃO 6 MESES
// =========================
$totais = array_column($dadosGrafico, 'total'); // valores reais por mês
$valoresPrevisto = $totais;
$labelsPrevisto = $labels;

// Calcula taxa média de crescimento dos últimos meses
$taxas = [];
for ($i = 1; $i < count($totais); $i++) {
    if ($totais[$i - 1] > 0) {
        $taxas[] = $totais[$i] / $totais[$i - 1];
    }
}
$taxaMedia = count($taxas) ? array_sum($taxas) / count($taxas) : 1;

// Última data disponível
$mesesArray = array_column($dadosGrafico, 'mes'); // ✅ variável intermediária
$ultimaDataStr = end($mesesArray) . '-01';
$ultimaData = new DateTime($ultimaDataStr);

// Projeta próximos 6 meses
for ($i = 1; $i <= 6; $i++) {
    $ultimaData->modify('+1 month');
    $mesNumero = $ultimaData->format('m');
    $ano = $ultimaData->format('Y');

    // Pega o último valor projetado de forma segura
    $ultimoValor = end($valoresPrevisto);
    $projetado = round($ultimoValor * $taxaMedia, 2);
    $valoresPrevisto[] = $projetado;

    // Adiciona label
    $labelsPrevisto[] = $mesesFormatados[$mesNumero] . '/' . $ano;
}
// =========================
// CLIENTES PRÓXIMOS DO VENCIMENTO
// =========================
$hoje = new DateTime();
$clientesProxVencer = [];

foreach ($usuarios as $u) {
    if (!empty($u['data_vencimento'])) {
        $dataVenc = new DateTime($u['data_vencimento']);
        $diff = (int)$hoje->diff($dataVenc)->format("%r%a");

        if ($diff >= -10 && $diff <= 10) {
            if ($diff < 0) {
                $status = 'Vencido';
                $cor = 'bg-red-500 text-white';
            } elseif ($diff == 0) {
                $status = 'Hoje';
                $cor = 'bg-yellow-400';
            } else {
                $status = 'A vencer';
                $cor = 'bg-green-500 text-white';
            }
            $clientesProxVencer[] = [
                'nome' => $u['nome'],
                'vencimento' => $dataVenc->format('d/m/Y'),
                'status' => $status,
                'cor' => $cor
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="bg-gray-100 min-h-screen">

    <div class="bg-white shadow p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold">
            Bem-vindo, <?= htmlspecialchars($_SESSION['admin_nome']); ?>
        </h1>
        <div class="space-x-4">
            <a href="clientes.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Clientes</a>
            <a href="planos.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Planos</a>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Sair</a>
        </div>
    </div>

    <div class="p-8">

        <!-- CARD TOTAL -->


        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- FATURAMENTO TOTAL -->
            <div class="bg-green-500 text-white p-6 rounded-2xl shadow">
                <h2 class="text-lg">Faturamento Total</h2>
                <p class="text-3xl font-bold">
                    R$ <?= number_format($totalValor, 2, ',', '.') ?>
                </p>
            </div>

            <!-- PREVISTO DO MÊS -->
            <div class="bg-blue-500 text-white p-6 rounded-2xl shadow">
                <h2 class="text-lg">Previsto do Mês</h2>
                <p class="text-3xl font-bold">
                    R$ <?= number_format($totalPrevisto, 2, ',', '.') ?>
                </p>
            </div>
            <!-- CLIENTES ATIVOS -->
            <div class="bg-green-500 text-white p-6 rounded-2xl shadow flex-1">
                <h2 class="text-lg">Clientes Ativos</h2>
                <p class="text-3xl font-bold"><?= $clientesAtivos ?></p>
            </div>

            <!-- CLIENTES INATIVOS -->
            <div class="bg-red-500 text-white p-6 rounded-2xl shadow flex-1">
                <h2 class="text-lg">Clientes Inativos</h2>
                <p class="text-3xl font-bold"><?= $clientesInativos ?></p>
            </div>
            <!-- GRÁFICO FATURAMENTO MÊS -->
            <div class="bg-white p-6 rounded-2xl shadow mt-6">
                <h2 class="text-lg font-semibold mb-4">Faturamento Mensal</h2>
                <canvas id="graficoMensal" height="100"></canvas>
            </div>
            <!-- GRÁFICO DE PROJEÇÃO MENSAL -->
            <div class="bg-white p-6 rounded-2xl shadow mt-6">
                <h2 class="text-lg font-semibold mb-4">Projeção Mensal Prevista</h2>
                <canvas id="graficoPrevisto" height="100"></canvas>
            </div>
        </div>


        <br>
        <!-- TABELA -->
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

                        $vencimento = $user['data_vencimento'] ?? null;
                        $classVenc = '';
                        $textoVenc = '-';
                        $status = '-'; // ✅ CORREÇÃO

                        if ($vencimento) {

                            $hoje = new DateTime();
                            $dataV = new DateTime($vencimento);
                            $diff = (int)$hoje->diff($dataV)->format("%r%a");

                            if ($diff < 0) {
                                $status = 'Vencido';
                                $classVenc = 'bg-red-600 text-white';
                            } elseif ($diff <= 2) {
                                $status = 'Urgente';
                                $classVenc = 'bg-red-400 text-white';
                            } elseif ($diff <= 5) {
                                $status = 'Atenção';
                                $classVenc = 'bg-purple-500 text-white';
                            } elseif ($diff <= 15) {
                                $status = 'Próximo';
                                $classVenc = 'bg-yellow-400';
                            } else {
                                $status = 'Em dia';
                                $classVenc = 'bg-green-500 text-white';
                            }

                            $textoVenc = date("d/m/Y", strtotime($vencimento));
                        }
                    ?>

                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4"><?= $user['id']; ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($user['nome']); ?></td>
                            <td class="py-2 px-4">
                                <?php
                                $telefone = preg_replace('/\D/', '', $user['telefone']); // remove tudo que não é número
                                ?>
                                <a href="https://wa.me/55<?= $telefone ?>" target="_blank" class="text-green-600 hover:underline">
                                    <?= htmlspecialchars($user['telefone']); ?>
                                </a>
                            </td>

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
                                <?= $textoVenc ?> <br>
                                <span class="text-xs"><?= $status ?></span>
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
            btn.addEventListener('click', function() {

                const clienteId = this.dataset.id;

                if (!confirm("Confirmar pagamento?")) return;

                // 🔥 AQUI (logo após clicar)
                this.disabled = true;
                this.innerText = "Processando...";

                fetch('pagar_ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'cliente_id=' + clienteId
                    })
                    .then(res => res.json())
                    .then(data => {

                        if (data.success) {
                            alert(data.msg);

                            // 🔄 recarrega a página
                            location.reload();
                        } else {
                            alert("Erro: " + data.msg);

                            // 🔙 reativa botão se der erro
                            this.disabled = false;
                            this.innerText = "Pagar";
                        }

                    })
                    .catch(err => {
                        alert("Erro na requisição");

                        // 🔙 reativa botão se der erro
                        this.disabled = false;
                        this.innerText = "Pagar";

                        console.error(err);
                    });

            });
        });
    </script>
    <!-- SCRIPT PAGAMENTO -->
    <script>
        const labels = <?= json_encode($labels) ?>;
        const valores = <?= json_encode($valores) ?>;

        const ctx = document.getElementById('graficoMensal');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Faturamento Acumulado (R$)',
                    data: valores
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <!-- GRÁFICO -->
    <script>
        const total = <?= $totalValor ?>;

        const ctx = document.getElementById('graficoTotal');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Faturamento'],
                datasets: [{
                    label: 'R$',
                    data: [total],
                    tension: 0.4, // curva suave
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <script>
        const labelsPrevisto = <?= json_encode($labelsPrevisto) ?>;
        const valoresPrevisto = <?= json_encode($valoresPrevisto) ?>;

        const ctxPrevisto = document.getElementById('graficoPrevisto');

        new Chart(ctxPrevisto, {
            type: 'line',
            data: {
                labels: labelsPrevisto,
                datasets: [{
                    label: 'Projeção Faturamento (R$)',
                    data: valoresPrevisto,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <script>
        const labels = <?= json_encode($labels) ?>;
        const valores = <?= json_encode($valores) ?>;

        new Chart(document.getElementById('graficoMensal'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'R$',
                    data: valores,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>

</html>