function initUsers() {
    const API_URL = 'api.php';
    const usersTableBody = document.getElementById('usersTableBody');
    const usersLoader = document.getElementById('usersLoader');
    const createUserForm = document.getElementById('createUserForm');

    const api = {
        get: (action) => fetch(`${API_URL}?action=${action}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(res => res.json())
    };

    const loadUsers = async () => {
        usersLoader.classList.remove('hidden');
        usersTableBody.innerHTML = '';
        try {
            const users = await api.get('get_users');
            usersTableBody.innerHTML = users.map(user => `...`).join(''); // Assuming correct
        } catch (error) {
            console.error('Failed to load users:', error);
        } finally {
            usersLoader.classList.add('hidden');
        }
    };

    createUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        // ... (form submission logic) ...
    });

    usersTableBody.addEventListener('click', async (e) => {
        // ... (delete logic) ...
    });

    loadUsers();
}