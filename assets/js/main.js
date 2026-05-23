const sidebar      = document.getElementById('sidebar');
const mainWrapper  = document.querySelector('.main-wrapper');
const toggleBtn    = document.getElementById('sidebarToggle');

if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            mainWrapper.classList.toggle('expanded');
        }
    });
}

// Close sidebar on mobile when clicking outside
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 &&
        sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        e.target !== toggleBtn) {
        sidebar.classList.remove('open');
    }
});
