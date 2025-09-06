<header class="top-nav">
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search...">
    </div>
    
    <div class="nav-right">
        <div class="notifications">
            <i class="fas fa-bell"></i>
            <span class="badge">3</span>
        </div>
        <div class="user-menu">
            <img src="./img/founder.jpg" alt="Admin Avatar">
            <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
</header>