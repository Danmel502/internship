<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand text-success" href="index.php">Media <span class="text-dark">Track</span></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= (isset($current_page) && $current_page === 'home') ? 'active' : '' ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (isset($current_page) && $current_page === 'about') ? 'active' : '' ?>" href="about.php">About</a>
                </li>
                <li class="nav-item dropdown" id="settingsDropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" id="settingsMenu" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" id="settingsDropdownMenu">
                        <li><a class="dropdown-item" href="settings/system-names.php">System Names</a></li>
                        <li><a class="dropdown-item" href="settings/modules.php">Modules</a></li>
                        <li><a class="dropdown-item" href="settings/clients.php">Clients</a></li>
                        <li><a class="dropdown-item" href="settings/sources.php">Sources</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.getElementById('settingsDropdown');
    const dropdownMenu = document.getElementById('settingsDropdownMenu');
    const dropdownToggle = document.getElementById('settingsMenu');
    
    if (!dropdown || !dropdownMenu || !dropdownToggle) return;
    
    let isClicked = false;
    let hoverTimeout;

    // Show dropdown on hover
    dropdown.addEventListener('mouseenter', function() {
        clearTimeout(hoverTimeout);
        if (!isClicked) {
            dropdownMenu.classList.add('show');
            dropdown.classList.add('show');
        }
    });

    // Hide dropdown on mouse leave (only if not clicked)
    dropdown.addEventListener('mouseleave', function() {
        if (!isClicked) {
            hoverTimeout = setTimeout(function() {
                dropdownMenu.classList.remove('show');
                dropdown.classList.remove('show');
            }, 100);
        }
    });

    // Toggle dropdown on click
    dropdownToggle.addEventListener('click', function(e) {
        e.preventDefault();
        isClicked = !isClicked;
        
        if (isClicked) {
            dropdownMenu.classList.add('show');
            dropdown.classList.add('show');
        } else {
            dropdownMenu.classList.remove('show');
            dropdown.classList.remove('show');
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            isClicked = false;
            dropdownMenu.classList.remove('show');
            dropdown.classList.remove('show');
        }
    });

    // Handle dropdown item clicks
    dropdownMenu.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            // Close dropdown after clicking an item
            isClicked = false;
            dropdownMenu.classList.remove('show');
            dropdown.classList.remove('show');
        }
    });
});
</script>