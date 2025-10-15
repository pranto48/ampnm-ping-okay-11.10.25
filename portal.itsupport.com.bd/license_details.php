<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$license_id = $_GET['license_id'] ?? null;

if (!$license_id) {
    header('Location: dashboard.php');
    exit;
}

// Fetch license details for the current user
$stmt = $pdo->prepare("
    SELECT l.*, p.name as product_name, p.description as product_description
    FROM `licenses` l
    JOIN `products` p ON l.product_id = p.id
    WHERE l.id = ? AND l.customer_id = ?
");
$stmt->execute([$license_id, $customer_id]);
$license = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$license) {
    header('Location: dashboard.php');
    exit;
}

portal_header("License Details - IT Support BD Portal");
?>

<h1 class="text-4xl font-bold text-gray-900 mb-8 text-center">License Details & Setup</h1>

<div class="max-w-3xl mx-auto card">
    <h2 class="text-2xl font-semibold text-gray-900 mb-4"><?= htmlspecialchars($license['product_name']) ?></h2>
    <p class="text-gray-600 mb-6"><?= htmlspecialchars($license['product_description']) ?></p>

    <div class="bg-gray-100 p-4 rounded-md mb-6">
        <strong class="block text-lg text-gray-800 mb-2">Your License Key:</strong>
        <div class="flex items-center justify-between bg-white border border-gray-300 rounded-md p-3 font-mono text-lg break-all">
            <span id="license-key-display"><?= htmlspecialchars($license['license_key']) ?></span>
            <button class="ml-4 text-blue-600 hover:text-blue-800" onclick="copyToClipboard('license-key-display')">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>
        <p class="text-sm text-gray-500 mt-2">Use this key when setting up your AMPNM Docker application.</p>
    </div>

    <h3 class="text-xl font-semibold text-gray-900 mb-4">Download AMPNM Docker Setup Files</h3>
    <p class="text-gray-700 mb-4">Download these files to quickly set up your AMPNM application using Docker. Your license key will be pre-filled in the `docker-compose.yml` file.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <a href="generate_docker_compose.php?license_key=<?= urlencode($license['license_key']) ?>" class="btn-primary flex items-center justify-center">
            <i class="fas fa-file-code mr-2"></i>Download docker-compose.yml
        </a>
        <a href="download_dockerfile.php" class="btn-secondary flex items-center justify-center">
            <i class="fas fa-file-alt mr-2"></i>Download Dockerfile
        </a>
    </div>

    <h3 class="text-xl font-semibold text-gray-900 mb-4">Installation Instructions</h3>
    <div class="space-y-4 text-gray-700">
        <p>Follow these steps to get your AMPNM application running with Docker:</p>
        <ol class="list-decimal list-inside space-y-2 pl-4">
            <li>
                <strong>Download Files:</strong> Download both `docker-compose.yml` and `Dockerfile` using the buttons above.
                Place them in a new, empty directory on your server.
            </li>
            <li>
                <strong>Ensure Docker is Installed:</strong> Make sure Docker and Docker Compose are installed on your server.
                If not, follow the official Docker installation guides.
            </li>
            <li>
                <strong>Open Terminal:</strong> Navigate to the directory where you saved the files using your terminal/command prompt.
            </li>
            <li>
                <strong>Run Docker Compose:</strong> Execute the following command:
                <pre class="bg-gray-800 text-white p-3 rounded-md text-sm mt-2 overflow-x-auto"><code>docker-compose up --build -d</code></pre>
                This command will build the application image, set up the database, and start the services in the background.
            </li>
            <li>
                <strong>Access AMPNM:</strong> Once the services are up (this might take a few minutes for the first build),
                open your web browser and go to: <code class="bg-gray-200 p-1 rounded">http://localhost:2266</code>
            </li>
            <li>
                <strong>Login:</strong> The default admin credentials are `admin` / `password`. You can change this in the `docker-compose.yml` file before running `docker-compose up`.
            </li>
        </ol>
        <p class="text-sm text-gray-500">
            <strong>Note:</strong> The `LICENSE_API_URL` and `APP_LICENSE_KEY` environment variables in `docker-compose.yml` are pre-filled for you.
            The AMPNM application will automatically verify your license with our portal.
        </p>
    </div>
</div>

<script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent || element.innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('License key copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            alert('Failed to copy license key.');
        });
    }
</script>

<?php portal_footer(); ?>