let ouResetList = [];
let ouGroupsList = [];

document.addEventListener('DOMContentLoaded', function () {
    // AD Configuration
    const form = document.getElementById('ad-config-form');
    const testBtn = document.getElementById('test-connection');
    const addResetBtn = document.getElementById('add-ou-reset');
    const addGroupsBtn = document.getElementById('add-ou-groups');

    if (form) {
        form.addEventListener('submit', saveConfig);
        testBtn.addEventListener('click', testConnection);
        addResetBtn.addEventListener('click', () => addOU('reset'));
        addGroupsBtn.addEventListener('click', () => addOU('groups'));

        // Load existing config
        loadConfig();
    }

    // API Configuration
    const apiForm = document.getElementById('api-config-form');
    if (apiForm) {
        apiForm.addEventListener('submit', saveApiConfig);

        // API Key visibility toggle
        const toggleApiKeyBtn = document.getElementById('toggle-api-key');
        if (toggleApiKeyBtn) {
            toggleApiKeyBtn.addEventListener('click', function () {
                const input = document.getElementById('dhcp-api-key');
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        // API test buttons
        document.getElementById('test-dhcp-api').addEventListener('click', () => testApi('dhcp'));
        document.getElementById('test-share-api').addEventListener('click', () => testApi('share'));
        document.getElementById('test-all-apis').addEventListener('click', testAllApis);

        // Load API config
        loadApiConfig();
    }

    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');

            // Update active tab button
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Update active tab content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabId) {
                    content.classList.add('active');
                }
            });
        });
    });
});

async function loadConfig() {
    try {
        const response = await App.fetch('/settings/ad');

        if (response.success) {
            const config = response.data;

            document.getElementById('host').value = config.host || '';
            document.getElementById('port').value = config.port || 389;
            document.getElementById('protocol').value = config.protocol || 'ldap';
            document.getElementById('use-tls').checked = config.use_tls || false;
            document.getElementById('base-dn').value = config.base_dn || '';
            document.getElementById('bind-dn').value = config.bind_dn || '';
            document.getElementById('admin-ou').value = config.admin_ou || '';
            document.getElementById('connection-timeout').value = config.connection_timeout || 10;

            ouResetList = config.ou_reset_password || [];
            ouGroupsList = config.ou_manage_groups || [];

            renderOULists();
        }
    } catch (error) {
        console.log('No existing config found');
    }
}

async function saveConfig(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    data.csrf_token = document.getElementById('csrf-token').value;
    data.ou_reset_password = ouResetList;
    data.ou_manage_groups = ouGroupsList;
    data.use_tls = document.getElementById('use-tls').checked ? 'true' : 'false';

    const messageDiv = document.getElementById('config-message');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    App.setLoading(submitBtn, true);

    try {
        const response = await App.fetch('/settings/ad', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    } finally {
        App.setLoading(submitBtn, false);
    }
}

async function testConnection() {
    const formData = new FormData(document.getElementById('ad-config-form'));
    const data = Object.fromEntries(formData);

    data.csrf_token = document.getElementById('csrf-token').value;
    data.use_tls = document.getElementById('use-tls').checked ? 'true' : 'false';

    const messageDiv = document.getElementById('config-message');
    const testBtn = document.getElementById('test-connection');

    App.setLoading(testBtn, true);

    try {
        const response = await App.fetch('/settings/ad/test', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(messageDiv, 'Conexão testada com sucesso!', 'success');
        } else {
            App.showAlert(messageDiv, response.message, 'error');
        }
    } catch (error) {
        App.showAlert(messageDiv, 'Erro ao testar conexão: ' + error.message, 'error');
    } finally {
        App.setLoading(testBtn, false);
    }
}

function addOU(type) {
    const input = document.getElementById(`ou-${type}-input`);
    const value = input.value.trim();

    if (!value) {
        alert('Digite um DN válido');
        return;
    }

    if (type === 'reset') {
        if (!ouResetList.includes(value)) {
            ouResetList.push(value);
        }
    } else {
        if (!ouGroupsList.includes(value)) {
            ouGroupsList.push(value);
        }
    }

    input.value = '';
    renderOULists();
}

function removeOU(type, index) {
    if (type === 'reset') {
        ouResetList.splice(index, 1);
    } else {
        ouGroupsList.splice(index, 1);
    }

    renderOULists();
}

function renderOULists() {
    // Reset OU list
    const resetContainer = document.getElementById('ou-reset-list');
    let resetHtml = '';
    ouResetList.forEach((ou, index) => {
        resetHtml += `
            <span class="ou-tag">
                ${ou}
                <button type="button" onclick="removeOU('reset', ${index})">&times;</button>
            </span>
        `;
    });
    resetContainer.innerHTML = resetHtml || '<p class="text-secondary">Nenhuma OU adicionada</p>';

    // Groups OU list
    const groupsContainer = document.getElementById('ou-groups-list');
    let groupsHtml = '';
    ouGroupsList.forEach((ou, index) => {
        groupsHtml += `
            <span class="ou-tag">
                ${ou}
                <button type="button" onclick="removeOU('groups', ${index})">&times;</button>
            </span>
        `;
    });
    groupsContainer.innerHTML = groupsHtml || '<p class="text-secondary">Nenhuma OU adicionada</p>';
}

// API Configuration Functions
async function loadApiConfig() {
    try {
        const response = await App.fetch('/settings/api');

        if (response.success) {
            const config = response.data;

            document.getElementById('dhcp-api-url').value = config.dhcp_api_url || 'http://10.168.11.80:5001';
            document.getElementById('dhcp-api-key').value = config.dhcp_api_key || '';
            document.getElementById('share-api-url').value = config.share_api_url || 'http://10.168.11.80:5002';
            document.getElementById('dhcp-api-enabled').checked = config.dhcp_api_enabled || false;
            document.getElementById('share-api-enabled').checked = config.share_api_enabled || false;
            document.getElementById('api-timeout').value = config.api_timeout || 30;
            document.getElementById('api-retry-attempts').value = config.api_retry_attempts || 3;
        }
    } catch (error) {
        console.log('No existing API config found');
    }
}

async function saveApiConfig(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    data.csrf_token = document.getElementById('api-csrf-token').value;
    data.dhcp_api_url = document.getElementById('dhcp-api-url').value.trim();
    data.dhcp_api_key = document.getElementById('dhcp-api-key').value.trim();
    data.share_api_url = document.getElementById('share-api-url').value.trim();
    data.dhcp_api_enabled = document.getElementById('dhcp-api-enabled').checked;
    data.share_api_enabled = document.getElementById('share-api-enabled').checked;

    const messageDiv = document.getElementById('api-config-message');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    App.setLoading(submitBtn, true);

    try {
        const response = await App.fetch('/settings/api', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(messageDiv, response.message, 'success');
        } else {
            App.showAlert(messageDiv, response.message, 'error');
        }
    } catch (error) {
        App.showAlert(messageDiv, error.message, 'error');
    } finally {
        App.setLoading(submitBtn, false);
    }
}

async function testApi(apiType) {
    const urlField = document.getElementById(`${apiType}-api-url`);
    const statusSpan = document.getElementById(`${apiType}-api-status`);
    const testBtn = document.getElementById(`test-${apiType}-api`);

    const url = urlField.value.trim();
    if (!url) {
        statusSpan.innerHTML = '<span class="text-error">URL não informada</span>';
        return;
    }

    App.setLoading(testBtn, true);
    statusSpan.innerHTML = '<span class="text-info">Testando...</span>';

    try {
        const response = await App.fetch('/settings/api/test', {
            method: 'POST',
            body: JSON.stringify({
                api_type: apiType,
                url: url,
                api_key: document.getElementById('dhcp-api-key').value.trim()
            })
        });

        if (response.success) {
            statusSpan.innerHTML = '<span class="text-success">✓ Conectado</span>';
        } else {
            statusSpan.innerHTML = `<span class="text-error">✗ ${response.message}</span>`;
        }
    } catch (error) {
        statusSpan.innerHTML = `<span class="text-error">✗ ${error.message}</span>`;
    } finally {
        App.setLoading(testBtn, false);
    }
}

async function testAllApis() {
    const testBtn = document.getElementById('test-all-apis');
    App.setLoading(testBtn, true);

    try {
        await Promise.all([
            testApi('dhcp'),
            testApi('share')
        ]);
    } finally {
        App.setLoading(testBtn, false);
    }
}
