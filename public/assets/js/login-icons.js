// Login Background Icons - Mouse Repel Effect
document.addEventListener('DOMContentLoaded', function () {
    const icons = document.querySelectorAll('.bg-icon');
    const loginPage = document.querySelector('.login-page');

    if (!loginPage || icons.length === 0) return;

    loginPage.addEventListener('mousemove', function (e) {
        const mouseX = e.clientX;
        const mouseY = e.clientY;

        icons.forEach(icon => {
            const rect = icon.getBoundingClientRect();
            const iconCenterX = rect.left + rect.width / 2;
            const iconCenterY = rect.top + rect.height / 2;

            // Calculate distance from mouse to icon center
            const deltaX = iconCenterX - mouseX;
            const deltaY = iconCenterY - mouseY;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

            // Repel radius (in pixels)
            const repelRadius = 150;

            if (distance < repelRadius) {
                // Calculate repel strength (stronger when closer)
                const strength = (repelRadius - distance) / repelRadius;
                const moveX = (deltaX / distance) * strength * 40;
                const moveY = (deltaY / distance) * strength * 40;

                // Apply transform
                icon.style.transform = `translate(${moveX}px, ${moveY}px) rotate(${strength * 10}deg)`;
                icon.style.opacity = 0.3 + (strength * 0.3);
            } else {
                // Reset to default animation state
                icon.style.transform = '';
                icon.style.opacity = '';
            }
        });
    });

    // Reset icons when mouse leaves the page
    loginPage.addEventListener('mouseleave', function () {
        icons.forEach(icon => {
            icon.style.transform = '';
            icon.style.opacity = '';
        });
    });
});
