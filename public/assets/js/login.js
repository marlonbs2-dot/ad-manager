document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const errorMessage = document.getElementById('error-message');
    let is2FAStep = false;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Get form data
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        // Ensure CSRF token is included
        if (!data.csrf_token) {
            const csrfField = document.getElementById('csrf-token-field');
            if (csrfField && csrfField.value) {
                data.csrf_token = csrfField.value;
            }
        }
        
        // Hide previous errors
        errorMessage.style.display = 'none';
        
        // Show loading
        App.setLoading(submitBtn, true);
        
        try {
            const endpoint = is2FAStep ? '/verify-2fa' : '/login';
            const response = await App.fetch(endpoint, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            if (response.success) {
                // Check if 2FA is required
                if (response.requires_2fa) {
                    show2FAForm();
                } else {
                    window.location.href = response.redirect || '/dashboard';
                }
            } else {
                errorMessage.textContent = response.message;
                errorMessage.style.display = 'block';
            }
        } catch (error) {
            errorMessage.textContent = error.message || 'Erro ao fazer login';
            errorMessage.style.display = 'block';
        } finally {
            App.setLoading(submitBtn, false);
        }
    });
    
    function show2FAForm() {
        is2FAStep = true;
        
        // Hide username and password fields
        const usernameGroup = document.querySelector('#username').closest('.form-group');
        const passwordGroup = document.querySelector('#password').closest('.form-group');
        usernameGroup.style.display = 'none';
        passwordGroup.style.display = 'none';
        
        // Create 2FA input field
        const twoFAGroup = document.createElement('div');
        twoFAGroup.className = 'form-group';
        twoFAGroup.id = '2fa-group';
        twoFAGroup.innerHTML = `
            <label for="code">Código de Autenticação</label>
            <input type="text" id="code" name="code" placeholder="Digite o código de 6 dígitos" required autofocus maxlength="6" pattern="[0-9]{6}">
            <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                Digite o código do seu aplicativo autenticador ou use um código de backup.
            </small>
        `;
        
        // Create trust device checkbox
        const trustDeviceGroup = document.createElement('div');
        trustDeviceGroup.className = 'form-group';
        trustDeviceGroup.innerHTML = `
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="trust_device" value="1" style="width: auto;">
                <span>Confiar neste dispositivo por 30 dias</span>
            </label>
        `;
        
        // Insert before the error message
        errorMessage.parentNode.insertBefore(twoFAGroup, errorMessage);
        errorMessage.parentNode.insertBefore(trustDeviceGroup, errorMessage);
        
        // Update button text
        const btnText = form.querySelector('.btn-text');
        btnText.textContent = 'Verificar Código';
        
        // Add back button
        const backBtn = document.createElement('button');
        backBtn.type = 'button';
        backBtn.className = 'btn btn-secondary btn-block';
        backBtn.textContent = 'Voltar';
        backBtn.style.marginTop = '0.5rem';
        backBtn.addEventListener('click', function() {
            location.reload();
        });
        form.querySelector('button[type="submit"]').parentNode.insertBefore(backBtn, form.querySelector('button[type="submit"]').nextSibling);
        
        // Focus on code input
        document.getElementById('code').focus();
    }
});
