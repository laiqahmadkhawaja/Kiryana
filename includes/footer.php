<?php
// includes/footer.php
?>
</main>
</div> <!-- End app-wrapper -->

<!-- Bootstrap 5.3 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js for reports -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- App JS -->
<script src="assets/js/app.js"></script>
<!-- Print Utilities: opens clean print-only window (no navbar/sidebar) -->
<script src="assets/js/print-utils.js"></script>

<script>
// Sidebar Toggle for Mobile
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        const sidebar = document.getElementById('appSidebar');
        sidebar.classList.toggle('d-block');
        sidebar.classList.toggle('position-fixed');
        sidebar.classList.toggle('h-100');
        sidebar.style.zIndex = '1050';
    });
}

// Live Clock Updater
function updateClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        const now = new Date();
        el.innerText = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + 
                       ', ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
}
setInterval(updateClock, 1000);
</script>
</body>
</html>
