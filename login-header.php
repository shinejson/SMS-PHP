<?php
// login-header.php
$school_short_name = getSchoolSetting('school_short_name', 'School Dashboard');
$logo = getSchoolSetting('logo', 'img/logo.png');
$motto = getSchoolSetting('motto', 'Administrator Portal');
?>
<div class="login-header">
    <?php if (!empty($logo)): ?>
        <img src="<?php echo htmlspecialchars($logo); ?>" 
             alt="<?php echo htmlspecialchars($school_short_name); ?> Logo" 
             width="100"
             onerror="this.style.display='none'; document.querySelector('.logo-fallback').style.display='flex';">
    <?php endif; ?>
    
    <div class="logo-fallback" style="display: <?php echo empty($logo) ? 'flex' : 'none'; ?>;">
        <i class="fas fa-school"></i>
    </div>
    
    <h1><?php echo htmlspecialchars($school_short_name); ?></h1>
    <p><?php echo htmlspecialchars($motto); ?></p>
</div>