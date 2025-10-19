<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;
    const body = document.body;
    const themeIcon = themeToggle.querySelector('i');
    function applyTheme(theme) {
        if (theme === 'light') {
            body.classList.add('light-mode');
            if(themeIcon) {
                themeIcon.classList.remove('bi-moon-stars-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        } else {
            body.classList.remove('light-mode');
            if(themeIcon) {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-stars-fill');
            }
        }
    }
    const savedTheme = localStorage.getItem('theme') || 'dark';
    applyTheme(savedTheme);
    themeToggle.addEventListener('click', function() {
        let newTheme = body.classList.contains('light-mode') ? 'dark' : 'light';
        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
});
</script>
</body>
</html>