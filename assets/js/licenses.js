function initLicenses() {
    const API_URL = 'api.php';

    const els = {
        licenseFormTitle: document.getElementById('licenseFormTitle'),
        licenseForm: document.getElementById('licenseForm'),
        licenseId: document.getElementById('licenseId'),
        licenseUserId: document.getElementById('licenseUserId'),
        licenseKey: document.getElementById('licenseKey'),
        licenseStatus: document.getElementById('licenseStatus'),
        licenseIssuedAt: document.getElementById('licenseIssuedAt'),
        licenseExpiresAt: document.getElementById('licenseExpiresAt'),
        licenseMaxDevices: document.getElementById('licenseMaxDevices'),
        saveLicenseBtn: document.getElementById('saveLicenseBtn'),
        cancelLicenseEditBtn: document.getElementById('cancelLicenseEditBtn'),
        licensesTableBody: document.getElementById('licensesTableBody'),
        licensesLoader: document.getElementById('licensesLoader'),
        noLicensesMessage: document.getElementById('noLicensesMessage'),
    };

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const loadLicenses = async () => {
        els.licensesLoader.classList.remove('hidden');
        els.licensesTableBody.innerHTML = '';
        els.noLicensesMessage.classList.add('hidden');

        try {
            const licenses = await api.get('get_licenses');
            if (licenses.length > 0) {
                els.licensesTableBody.innerHTML = licenses.map(license => {
                    const statusClass = {
                        'active': 'bg-green-500/20 text-green-400',
                        'free': 'bg-blue-500/20 text-blue-400',
                        'expired': 'bg-red-500/20 text-red-400',
                        'revoked': 'bg-slate-500/20 text-slate-400',
                    }[license.status] || 'bg-slate-500/20 text-slate-400';

                    const issuedAt = license.issued_at ? new Date(license.issued_at).toLocaleDateString() : 'N/A';
                    const expiresAt = license.expires_at ? new Date(license.expires_at).toLocaleDateString() : 'Never';

                    return `
                        <tr class="border-b border-slate-700">
                            <td class="px-6 py-4 whitespace-nowrap text-white">${license.user_email || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-300">${license.license_key}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                    ${license.status.charAt(0).toUpperCase() + license.status.slice(1)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-300">${license.current_devices}/${license.max_devices}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-400">${expiresAt}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button class="edit-license-btn text-yellow-400 hover:text-yellow-300 mr-3" data-id="${license.id}"><i class="fas fa-edit"></i> Edit</button>
                                <button class="delete-license-btn text-red-500 hover:text-red-400" data-id="${license.id}"><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                els.noLicensesMessage.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to load licenses:', error);
            window.notyf.error('Failed to load licenses.');
        } finally {
            els.licensesLoader.classList.add('hidden');
        }
    };

    const populateUserSelect = async () => {
        try {
            // Fetch users from Supabase via the new API endpoint
            const users = await api.get('get_supabase_users'); 
            els.licenseUserId.innerHTML = '<option value="">-- Select User --</option>' + 
                users.map(user => `<option value="${user.id}">${user.email}</option>`).join('');
        } catch (error) {
            console.error('Failed to load users for license assignment:', error);
            window.notyf.error('Failed to load users for license assignment.');
        }
    };

    const resetForm = () => {
        els.licenseForm.reset();
        els.licenseId.value = '';
        els.licenseFormTitle.textContent = 'Create New License';
        els.saveLicenseBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save License';
        els.cancelLicenseEditBtn.classList.add('hidden');
        // Set default values for new license
        els.licenseStatus.value = 'active';
        els.licenseMaxDevices.value = 1;
        els.licenseIssuedAt.value = '';
        els.licenseExpiresAt.value = '';
        // Re-populate user select to clear any previous selection
        populateUserSelect(); 
    };

    els.licenseForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        els.saveLicenseBtn.disabled = true;
        els.saveLicenseBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        const id = els.licenseId.value;
        const data = {
            user_id: els.licenseUserId.value,
            license_key: els.licenseKey.value,
            status: els.licenseStatus.value,
            issued_at: els.licenseIssuedAt.value || null,
            expires_at: els.licenseExpiresAt.value || null,
            max_devices: parseInt(els.licenseMaxDevices.value),
        };

        try {
            let result;
            if (id) {
                result = await api.post('update_license', { id, updates: data });
            } else {
                result = await api.post('create_license', data);
            }

            if (result.success) {
                window.notyf.success(result.message);
                resetForm();
                loadLicenses();
            } else {
                window.notyf.error(`Error: ${result.error}`);
            }
        } catch (error) {
            window.notyf.error('An unexpected error occurred while saving license.');
            console.error(error);
        } finally {
            els.saveLicenseBtn.disabled = false;
            els.saveLicenseBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save License';
        }
    });

    els.cancelLicenseEditBtn.addEventListener('click', resetForm);

    els.licensesTableBody.addEventListener('click', async (e) => {
        const editButton = e.target.closest('.edit-license-btn');
        const deleteButton = e.target.closest('.delete-license-btn');

        if (editButton) {
            const licenseId = editButton.dataset.id;
            const licenses = await api.get('get_licenses'); // Re-fetch to get full data
            const licenseToEdit = licenses.find(l => l.id == licenseId);

            if (licenseToEdit) {
                els.licenseFormTitle.textContent = 'Edit License';
                els.licenseId.value = licenseToEdit.id;
                els.licenseUserId.value = licenseToEdit.user_id; // This is now a Supabase user ID (UUID)
                els.licenseKey.value = licenseToEdit.license_key;
                els.licenseStatus.value = licenseToEdit.status;
                els.licenseIssuedAt.value = licenseToEdit.issued_at ? new Date(licenseToEdit.issued_at).toISOString().slice(0, 16) : '';
                els.licenseExpiresAt.value = licenseToEdit.expires_at ? new Date(licenseToEdit.expires_at).toISOString().slice(0, 16) : '';
                els.licenseMaxDevices.value = licenseToEdit.max_devices;
                els.saveLicenseBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update License';
                els.cancelLicenseEditBtn.classList.remove('hidden');
            }
        } else if (deleteButton) {
            const licenseId = deleteButton.dataset.id;
            if (confirm('Are you sure you want to delete this license?')) {
                try {
                    const result = await api.post('delete_license', { id: licenseId });
                    if (result.success) {
                        window.notyf.success(result.message);
                        loadLicenses();
                    } else {
                        window.notyf.error(`Error: ${result.error}`);
                    }
                } catch (error) {
                    window.notyf.error('An unexpected error occurred while deleting license.');
                    console.error(error);
                }
            }
        }
    });

    // Initial load
    populateUserSelect();
    loadLicenses();
}