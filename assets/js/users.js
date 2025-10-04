document.addEventListener('DOMContentLoaded', function() {
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
            usersTableBody.innerHTML = users.map(user => `
                <tr class="border-b border-slate-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">${user.username}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">${new Date(user.created_at).toLocaleString()}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        ${user.username !== 'admin' ? `<button class="delete-user-btn text-red-500 hover:text-red-400" data-id="${user.id}" title="Delete User"><i class="fas fa-trash"></i></button>` : ''}
                    </td>
                </tr>
            `).join('');
        } catch (error) {
            console.error('Failed to load users:', error);
            usersTableBody.innerHTML = '<tr><td colspan="3" class="text-center text-red-400 py-4">Failed to load users.</td></tr>';
        } finally {
            usersLoader.classList.add('hidden');
        }
    };

    createUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target.username.value;
        const password = e.target.password.value;
        const result = await api.post('create_user', { username, password });
        if (result.success) {
            createUserForm.reset();
            await loadUsers();
        } else {
            alert(`Error: ${result.error}`);
        }
    });

    usersTableBody.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            const userId = deleteBtn.dataset.id;
            if (confirm('Are you sure you want to delete this user? All their maps and devices will be permanently removed.')) {
                const result = await api.post('delete_user', { id: userId });
                if (result.success) {
                    await loadUsers();
                } else {
                    alert(`Error: ${result.error}`);
                }
            }
        }
    });

    loadUsers();
});