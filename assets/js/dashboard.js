document.addEventListener('DOMContentLoaded', function() {
    const pingForm = document.getElementById('pingForm');
    const pingHostInput = document.getElementById('pingHostInput');
    const pingButton = document.getElementById('pingButton');
    const pingResultContainer = document.getElementById('pingResultContainer');
    const pingResultPre = document.getElementById('pingResultPre');

    pingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const host = pingHostInput.value.trim();
        if (!host) {
            alert('Please enter a host or IP address.');
            return;
        }

        pingButton.disabled = true;
        pingButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Pinging...';
        pingResultContainer.classList.remove('hidden');
        pingResultPre.textContent = `Pinging ${host}...`;

        try {
            const response = await fetch('api.php?action=manual_ping', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ host: host })
            });
            const result = await response.json();

            if (response.ok) {
                pingResultPre.textContent = result.output;
            } else {
                pingResultPre.textContent = `Error: ${result.error || 'An unknown error occurred.'}`;
            }
        } catch (error) {
            pingResultPre.textContent = `Failed to perform ping. Check API connection. Error: ${error.message}`;
        } finally {
            pingButton.disabled = false;
            pingButton.innerHTML = '<i class="fas fa-bolt mr-2"></i>Ping';
        }
    });
});