<!-- Botón menú móvil -->
<button class="mobile-menu-toggle" onclick="toggleMobileMenu()" style="display: none; position: fixed; top: 10px; right: 10px; z-index: 1001;">
    ☰
</button>

<!-- Overlay para cerrar menú en móvil -->
<div class="mobile-overlay" onclick="toggleMobileMenu()" style="display: none;"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>Sistema HC</h3>
        <div class="user-info">
            <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?><br>
            <small><?php echo ucfirst($_SESSION['rol']); ?></small>
        </div>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i>📊</i> Dashboard
            </a>
        </li>
        
        <li>
            <a href="pacientes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'pacientes.php' ? 'active' : ''; ?>">
                <i>👤</i> Pacientes
            </a>
        </li>
        
        <li>
            <a href="historias_clinicas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'historias_clinicas.php' ? 'active' : ''; ?>">
                <i>📋</i> Historias Clínicas
            </a>
        </li>
        
        <li>
            <a href="movimientos.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'movimientos.php' ? 'active' : ''; ?>">
                <i>🔄</i> Movimientos
            </a>
        </li>
        
        <li>
            <a href="busqueda.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'busqueda.php' ? 'active' : ''; ?>">
                <i>🔍</i> Búsqueda Avanzada
            </a>
        </li>
        
        <li>
            <a href="reportes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                <i>📈</i> Reportes
            </a>
        </li>
        
        <?php if (tienePermiso('administrador')): ?>
        <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <a href="usuarios.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
                <i>👥</i> Usuarios
            </a>
        </li>
        
        <li>
            <a href="fuentes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'fuentes.php' ? 'active' : ''; ?>">
                <i>🏥</i> Fuentes
            </a>
        </li>
        
        <li>
            <a href="auditoria.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'auditoria.php' ? 'active' : ''; ?>">
                <i>🔐</i> Auditoría
            </a>
        </li>
        <?php endif; ?>
        
        <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <a href="logout.php">
                <i>🚪</i> Cerrar Sesión
            </a>
        </li>
    </ul>
</aside>

<script>
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none';
    } else {
        sidebar.classList.add('active');
        overlay.style.display = 'block';
    }
}

// Cerrar menú al hacer clic en un enlace (solo en móvil)
if (window.innerWidth <= 768) {
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => {
            toggleMobileMenu();
        });
    });
}

// Mostrar botón hamburguesa solo en móvil
window.addEventListener('resize', () => {
    const menuButton = document.querySelector('.mobile-menu-toggle');
    const overlay = document.querySelector('.mobile-overlay');
    
    if (window.innerWidth <= 768) {
        menuButton.style.display = 'block';
    } else {
        menuButton.style.display = 'none';
        document.getElementById('sidebar').classList.remove('active');
        overlay.style.display = 'none';
    }
});

// Ejecutar al cargar
window.dispatchEvent(new Event('resize'));
</script>

<style>
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}
</style>
