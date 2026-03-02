document.addEventListener('DOMContentLoaded', async function() {
    await loadDashboardData();
});

async function loadDashboardData() {
    try {
        const response = await App.fetch('/api/dashboard/stats');
        
        if (response.success) {
            const { statistics, recent_logs } = response.data;
            
            // Update stats
            document.getElementById('stat-actions-today').textContent = statistics.actions_today || 0;
            document.getElementById('stat-success-rate').textContent = (statistics.success_rate || 0) + '%';
            document.getElementById('stat-active-users').textContent = statistics.most_active_users?.length || 0;
            document.getElementById('stat-recent-actions').textContent = recent_logs?.length || 0;
            
            // Render actions today by type
            renderActionsTodayByType(statistics.actions_today_by_type || []);
            
            // Render actions by type (7 days)
            renderActionsByType(statistics.actions_by_type || []);
            
            // Render most active users
            renderMostActiveUsers(statistics.most_active_users || []);
            
            // Render recent logs
            renderRecentLogs(recent_logs || []);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function renderActionsTodayByType(actions) {
    const container = document.getElementById('actions-today-by-type');
    
    if (actions.length === 0) {
        container.innerHTML = '<p class="text-center">Nenhuma ação registrada hoje</p>';
        return;
    }
    
    renderDonutChart(container, actions);
}

function renderActionsByType(actions) {
    const container = document.getElementById('actions-by-type');
    
    if (actions.length === 0) {
        container.innerHTML = '<p class="text-center">Nenhuma ação registrada nos últimos 7 dias</p>';
        return;
    }
    
    renderDonutChart(container, actions);
}

function renderDonutChart(container, data) {
    const colors = [
        '#3b82f6', // Azul vibrante
        '#8b5cf6', // Roxo
        '#ec4899', // Rosa
        '#f59e0b', // Laranja
        '#10b981', // Verde
        '#06b6d4', // Ciano
        '#6366f1', // Índigo
        '#f43f5e'  // Vermelho
    ];
    
    const total = data.reduce((sum, item) => sum + item.count, 0);
    const size = 200;
    const strokeWidth = 40;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    
    let html = '<div class="donut-chart-container">';
    
    // SVG Donut Chart
    html += `<svg class="donut-chart" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">`;
    
    let currentOffset = 0;
    data.forEach((item, index) => {
        const percentage = item.count / total;
        const strokeDasharray = circumference * percentage;
        const strokeDashoffset = -currentOffset;
        const color = colors[index % colors.length];
        
        html += `
            <circle
                class="donut-segment"
                cx="${size / 2}"
                cy="${size / 2}"
                r="${radius}"
                fill="transparent"
                stroke="${color}"
                stroke-width="${strokeWidth}"
                stroke-dasharray="${strokeDasharray} ${circumference}"
                stroke-dashoffset="${strokeDashoffset}"
                transform="rotate(-90 ${size / 2} ${size / 2})"
                data-label="${formatActionName(item.action)}"
                data-value="${item.count}"
                data-percentage="${(percentage * 100).toFixed(1)}%"
            />
        `;
        
        currentOffset += strokeDasharray;
    });
    
    // Center text
    html += `
        <text x="${size / 2}" y="${size / 2 - 10}" text-anchor="middle" class="donut-total">${total}</text>
        <text x="${size / 2}" y="${size / 2 + 15}" text-anchor="middle" class="donut-label">Total</text>
    `;
    
    html += '</svg>';
    
    // Legend
    html += '<div class="donut-legend">';
    data.forEach((item, index) => {
        const percentage = ((item.count / total) * 100).toFixed(1);
        const color = colors[index % colors.length];
        html += `
            <div class="legend-item">
                <span class="legend-color" style="background-color: ${color}"></span>
                <span class="legend-label">${formatActionName(item.action)}</span>
                <span class="legend-value">${item.count} (${percentage}%)</span>
            </div>
        `;
    });
    html += '</div>';
    
    html += '</div>';
    container.innerHTML = html;
}

function renderMostActiveUsers(users) {
    const container = document.getElementById('most-active-users');
    
    if (users.length === 0) {
        container.innerHTML = '<p class="text-center">Nenhum usuário ativo nos últimos 7 dias</p>';
        return;
    }
    
    let html = '<div class="user-list">';
    
    users.forEach((user, index) => {
        html += `
            <div class="user-list-item">
                <span class="user-rank">#${index + 1}</span>
                <span class="user-name">${user.username}</span>
                <span class="user-actions badge">${user.actions}</span>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function renderRecentLogs(logs) {
    const tbody = document.querySelector('#recent-logs tbody');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhuma atividade recente</td></tr>';
        return;
    }
    
    let html = '';
    
    logs.forEach(log => {
        const resultClass = log.result === 'success' ? 'badge-success' : 
                           log.result === 'failure' ? 'badge-error' : 'badge-warning';
        
        html += `
            <tr>
                <td>${App.formatDate(log.created_at)}</td>
                <td>${formatActionName(log.action)}</td>
                <td>${App.formatDN(log.target_dn)}</td>
                <td>${log.ip_address}</td>
                <td><span class="badge ${resultClass}">${log.result}</span></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function formatActionName(action) {
    const names = {
        'login_success': 'Login Sucesso',
        'login_failed': 'Login Falhou',
        'reset_password': 'Reset de Senha',
        'enable_user': 'Habilitar Usuário',
        'disable_user': 'Desabilitar Usuário',
        'add_group_member': 'Adicionar a Grupo',
        'remove_group_member': 'Remover de Grupo'
    };
    
    return names[action] || action;
}
