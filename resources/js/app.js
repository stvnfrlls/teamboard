import './bootstrap';
import './task-board';

const shell = document.querySelector('[data-app-shell]');
const sidebarToggle = shell?.querySelector('[data-sidebar-toggle]');

if (shell && sidebarToggle) {
    const storageKey = 'teamboard.sidebar.collapsed';
    const toggleLabel = sidebarToggle.querySelector('[data-sidebar-toggle-label]');
    let collapsed = false;

    try {
        collapsed = localStorage.getItem(storageKey) === 'true';
    } catch {
        // The toggle still works when browser storage is unavailable.
    }

    const renderSidebar = () => {
        const label = collapsed ? 'Show sidebar' : 'Hide sidebar';

        shell.dataset.sidebarCollapsed = String(collapsed);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', label);
        toggleLabel.textContent = label;
    };

    renderSidebar();
    sidebarToggle.hidden = false;

    sidebarToggle.addEventListener('click', () => {
        collapsed = !collapsed;
        renderSidebar();

        try {
            localStorage.setItem(storageKey, String(collapsed));
        } catch {
            // Keep the current page state without saving the preference.
        }
    });
}
