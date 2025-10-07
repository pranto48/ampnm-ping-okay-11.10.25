document.addEventListener('DOMContentLoaded', function() {
    // Initialize Notyf for toast notifications
    window.notyf = new Notyf({
        duration: 4000,
        position: { x: 'right', y: 'top' },
        types: [
            { type: 'success', backgroundColor: '#22c55e', icon: { className: 'fas fa-check-circle', tagName: 'i', color: 'white' } },
            { type: 'error', backgroundColor: '#ef4444', icon: { className: 'fas fa-times-circle', tagName: 'i', color: 'white' } },
            { type: 'info', backgroundColor: '#3b82f6', icon: { className: 'fas fa-info-circle', tagName: 'i', color: 'white' } }
        ]
    });

    const appContainer = document.getElementById('app');
    const navLinks = document.querySelectorAll('#main-nav a[data-navigo]');
    const router = new Navigo('/', { hash: false });

    // Page loading logic
    const loadPage = async (page, initFunction) => {
        appContainer.innerHTML = '<div class="container mx-auto px-4 py-8 text-center"><div class="loader mx-auto"></div></div>';
        try {
            // In a real SPA, you might want to clear intervals or event listeners from the previous page.
            // For this app, re-initializing on each page load is simple and effective.
            if (window.cleanup) {
                window.cleanup();
            }

            const response = await fetch(`pages/${page}.html`);
            if (!response.ok) throw new Error(`Page not found: ${page}.html`);
            const html = await response.text();
            appContainer.innerHTML = html;
            
            if (initFunction && typeof initFunction === 'function') {
                initFunction();
            }
        } catch (error) {
            console.error(`Failed to load page: ${page}`, error);
            appContainer.innerHTML = `<div class="text-center text-red-400 py-16">Error loading page.</div>`;
        }
    };

    // Define routes
    router.on({
        '/': () => loadPage('dashboard', initDashboard),
        '/devices': () => loadPage('devices', initDevices),
        '/history': () => loadPage('history', initHistory),
        '/map': () => loadPage('map', initMap),
        '/users': () => loadPage('users', initUsers),
    }).resolve();

    // Handle active nav link styling
    router.hooks({
        after: (match) => {
            navLinks.forEach(link => {
                const linkPath = new URL(link.href).pathname;
                if (linkPath === match.route.path) {
                    link.classList.add('bg-slate-700', 'text-white');
                } else {
                    link.classList.remove('bg-slate-700', 'text-white');
                }
            });
        }
    });

    // Handle 404
    router.notFound(() => {
        appContainer.innerHTML = '<div class="container mx-auto px-4 py-8 text-center"><h2>404 - Page Not Found</h2></div>';
    });

    // Make router instance globally available if needed
    window.router = router;
});