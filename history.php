<?php
require_once 'config.php';
include 'header.php';

$pdo = getDbConnection();
$host = $_GET['host'] ?? '';
$limit = 50;
$stmt = $pdo->prepare("SELECT DISTINCT host FROM ping_results ORDER BY host");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_COLUMN);
$sql = "SELECT * FROM ping_results" . ($host ? " WHERE host = ?" : "") . " ORDER BY created_at DESC LIMIT ?";
$params = $host ? [$host, $limit] : [$limit];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-white">Ping History</h1>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 mb-8">
        <form method="GET" class="flex flex-col sm:flex-row gap-4">
            <select name="host" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                <option value="">All Hosts</option>
                <?php foreach ($hosts as $h): ?>
                    <option value="<?= htmlspecialchars($h) ?>" <?= $host === $h ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">Filter</button>
            <a href="export.php?host=<?= urlencode($host) ?>" class="px-6 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 text-center">Export CSV</a>
        </form>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Host</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Packet Loss</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Avg Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                    <tr class="border-b border-slate-700 hover:bg-slate-800/50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-white"><?= htmlspecialchars($item['host']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400"><?= date('M j, Y H:i:s', strtotime($item['created_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $item['success'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' ?>">
                                <?= $item['success'] ? 'Success' : 'Failed' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm <?= $item['packet_loss'] > 0 ? 'text-orange-400' : 'text-green-400' ?>"><?= $item['packet_loss'] ?>%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400"><?= $item['avg_time'] ?>ms</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>