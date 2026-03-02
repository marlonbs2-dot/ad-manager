document.addEventListener('DOMContentLoaded', function() {
    const exportPdfBtn = document.getElementById('export-pdf');
    const exportExcelBtn = document.getElementById('export-excel');
    
    exportPdfBtn.addEventListener('click', () => exportReport('pdf'));
    exportExcelBtn.addEventListener('click', () => exportReport('excel'));
});

async function exportReport(type) {
    const filters = getFilters();
    const messageDiv = document.getElementById('export-message');
    
    const params = new URLSearchParams({
        type: type,
        ...filters
    });
    
    const btn = type === 'pdf' ? document.getElementById('export-pdf') : document.getElementById('export-excel');
    App.setLoading(btn, true);
    
    try {
        // Use regular fetch for file download
        const response = await fetch(`/reports/export?${params}`);
        
        if (!response.ok) {
            throw new Error('Erro ao gerar relatório');
        }
        
        // Get filename from header or generate one
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `relatorio_${new Date().getTime()}.${type === 'pdf' ? 'pdf' : 'xlsx'}`;
        
        if (contentDisposition) {
            const matches = /filename="?([^"]+)"?/.exec(contentDisposition);
            if (matches && matches[1]) {
                filename = matches[1];
            }
        }
        
        // Download file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        App.showAlert(messageDiv, 'Relatório gerado com sucesso!', 'success');
        
    } catch (error) {
        App.showAlert(messageDiv, 'Erro ao gerar relatório: ' + error.message, 'error');
    } finally {
        App.setLoading(btn, false);
    }
}

function getFilters() {
    return {
        username: document.getElementById('report-username').value,
        action: document.getElementById('report-action').value,
        result: document.getElementById('report-result').value,
        target_ou: document.getElementById('report-ou').value,
        date_from: document.getElementById('report-date-from').value,
        date_to: document.getElementById('report-date-to').value
    };
}
