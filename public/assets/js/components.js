/**
 * Dropdown Component JavaScript
 * Handles dropdown menu interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all dropdowns
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.dropdown-trigger');
        const menu = dropdown.querySelector('.dropdown-menu');

        if (!trigger || !menu) return;

        // Toggle dropdown on trigger click
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();

            // Close other dropdowns
            dropdowns.forEach(other => {
                if (other !== dropdown) {
                    other.classList.remove('active');
                }
            });

            // Toggle current dropdown
            dropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Close dropdown when clicking menu item
        const menuItems = menu.querySelectorAll('.dropdown-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function () {
                dropdown.classList.remove('active');
            });
        });

        // Keyboard navigation
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                dropdown.classList.toggle('active');
            }
        });
    });
});

/**
 * Scroll to Top Button
 */
document.addEventListener('DOMContentLoaded', function () {
    // Create scroll to top button if it doesn't exist
    let scrollBtn = document.querySelector('.scroll-to-top');

    if (!scrollBtn) {
        scrollBtn = document.createElement('button');
        scrollBtn.className = 'scroll-to-top';
        scrollBtn.setAttribute('aria-label', 'Scroll to top');
        scrollBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>';
        document.body.appendChild(scrollBtn);
    }

    // Show/hide button based on scroll position
    window.addEventListener('scroll', function () {
        if (window.pageYOffset > 300) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // Scroll to top on click
    scrollBtn.addEventListener('click', function () {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});

/**
 * Search Input Clear Button
 */
document.addEventListener('DOMContentLoaded', function () {
    const searchWrappers = document.querySelectorAll('.search-input-wrapper');

    searchWrappers.forEach(wrapper => {
        const input = wrapper.querySelector('.search-input');
        const clearBtn = wrapper.querySelector('.search-clear');

        if (!input || !clearBtn) return;

        clearBtn.addEventListener('click', function () {
            input.value = '';
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
});

/**
 * Table Sorting
 */
document.addEventListener('DOMContentLoaded', function () {
    const sortables = document.querySelectorAll('.sortable');

    sortables.forEach(th => {
        th.addEventListener('click', function () {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(th.parentElement.children).indexOf(th);
            const isAsc = th.classList.contains('sort-asc');

            // Remove sorting from other columns
            sortables.forEach(other => {
                if (other !== th) {
                    other.classList.remove('sort-asc', 'sort-desc');
                }
            });

            // Toggle sort direction
            if (isAsc) {
                th.classList.remove('sort-asc');
                th.classList.add('sort-desc');
            } else {
                th.classList.remove('sort-desc');
                th.classList.add('sort-asc');
            }

            // Sort rows
            rows.sort((a, b) => {
                const aValue = a.children[index].textContent.trim();
                const bValue = b.children[index].textContent.trim();

                // Try to parse as number
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAsc ? bNum - aNum : aNum - bNum;
                }

                // String comparison
                return isAsc
                    ? bValue.localeCompare(aValue)
                    : aValue.localeCompare(bValue);
            });

            // Reorder rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});
