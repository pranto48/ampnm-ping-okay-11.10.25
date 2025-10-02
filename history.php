<?php
require_once 'config.php';

$pdo = getDbConnection();

// Get filter parameters
$host = $_GET['host'] ?? '';
$limit = min(max($_GET['limit'] ?? 50, 1), 100);
$page = max($_GET['page'] ?? 1, 1);
$offset = ($page - 1) * $limit;

// Get unique hosts for filter dropdown
$stmt = $pdo->prepare("SELECT DISTINCT host FROM ping_results ORDER BY host");
$stmt->execute();
$hosts = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM ping_results";
$countParams = [];
if ($host) {
    $countSql .= " WHERE host = ?";
    $countParams[] = $host;
}
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$totalResults = $stmt->fetchColumn();
$totalPages = ceil($totalResults / $limit);

// Get history data
$sql = "SELECT * FROM ping_results";
$params = [];
if ($host) {
    $sql .= " WHERE host = ?";
    $params[] = $host;
}
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ping History - Network Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <i class="fas fa-history text-blue-600 text-3xl"></i>
                <h1 class="text-3xl font-bold text-gray-800">Ping History</h1>
            </div>
            <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Filter Results</h2>
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <select name="host" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Hosts</option>
                    <?php foreach ($hosts as $h): ?>
                        <option value="<?php echo htmlspecialchars($h); ?>" <?php echo $host === $h ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($h); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="limit" class="w-32 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <a href="export.php?host=<?php echo urlencode($host); ?>" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">
                    Ping Results 
                    <?php if ($host): ?>
                        <span class="text-gray-600">for <?php echo htmlspecialchars($host); ?></span>
                    <?php endif; ?>
                </h2>
                <div class="text-sm text-gray-500">
                    Showing <?php echo count($history); ?> of <?php echo $totalResults; ?> results
                </div>
            </div>
            
            <?php if (!empty($history)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Packet Loss</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Time (ms)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Time (ms)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max Time (ms)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($history as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 font-mono"><?php echo htmlspecialchars($item['host']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y H:i:s', strtotime($item['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    if ($item['success']) {
                                        echo 'bg-green-100 text-green-800';
                                    } else {
                                        echo 'bg-red-100 text-red-800';
                                    }
                                    ?>">
                                    <?php echo $item['success'] ? 'Success' : 'Failed'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="<?php echo $item['packet_loss'] > 0 ? 'text-orange-600' : 'text-green-600'; ?> font-medium">
                                    <?php echo $item['packet_loss']; ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $item['avg_time']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $item['min_time']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $item['max_time']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-6">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing page <span class="font-medium"><?php echo $page; ?></span> of <span class="font-medium"><?php echo $totalPages; ?></span>
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=1" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">First</span>
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Previous</span>
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $i; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i == $page ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Next</span>
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?host=<?php echo urlencode($host); ?>&limit=<?php echo $limit; ?>&page=<?php echo $totalPages; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Last</span>
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">No ping history found. Perform some pings to see results here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>