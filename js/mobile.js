/**
 * Funciones para mejorar la experiencia móvil
 * Sistema de Gestión de Historias Clínicas
 */

// Detectar si es dispositivo móvil
function isMobile() {
    return window.innerWidth <= 768;
}

// Detectar si es touch device
function isTouchDevice() {
    return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
}

// Prevenir zoom doble-tap en iOS (mantener zoom pinch)
document.addEventListener('dblclick', function(e) {
    if (isMobile()) {
        e.preventDefault();
    }
}, { passive: false });

// Mejorar scroll en tablas en móvil
document.addEventListener('DOMContentLoaded', function() {
    if (isMobile()) {
        // Agregar indicador de scroll a tablas
        const tables = document.querySelectorAll('.table-responsive');
        tables.forEach(table => {
            if (table.scrollWidth > table.clientWidth) {
                table.classList.add('has-scroll');
                
                // Agregar hint visual
                const hint = document.createElement('div');
                hint.className = 'scroll-hint';
                hint.innerHTML = '← Desliza para ver más →';
                hint.style.cssText = 'text-align: center; padding: 5px; font-size: 11px; color: #666; background: #f8f9fa;';
                table.parentNode.insertBefore(hint, table);
                
                // Ocultar hint después del primer scroll
                table.addEventListener('scroll', function() {
                    hint.style.display = 'none';
                }, { once: true });
            }
        });
        
        // Agregar clase mobile a body
        document.body.classList.add('is-mobile');
    }
});

// Mejorar UX de formularios en móvil
if (isMobile()) {
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-scroll al input enfocado (evitar que quede detrás del teclado)
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
        
        // Cerrar select al hacer scroll (iOS bug fix)
        let activeSelect = null;
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('focus', function() {
                activeSelect = this;
            });
            select.addEventListener('blur', function() {
                activeSelect = null;
            });
        });
        
        window.addEventListener('scroll', function() {
            if (activeSelect) {
                activeSelect.blur();
            }
        });
    });
}

// Mejorar performance de scroll
let ticking = false;
function optimizeScroll(callback) {
    if (!ticking) {
        window.requestAnimationFrame(function() {
            callback();
            ticking = false;
        });
        ticking = true;
    }
}

// Detectar orientación del dispositivo
function getOrientation() {
    if (window.innerWidth > window.innerHeight) {
        return 'landscape';
    }
    return 'portrait';
}

// Evento de cambio de orientación
window.addEventListener('orientationchange', function() {
    // Reload algunas funcionalidades si es necesario
    setTimeout(() => {
        const orientation = getOrientation();
        document.body.setAttribute('data-orientation', orientation);
        
        // Ajustar tablas si es necesario
        if (orientation === 'landscape') {
            document.querySelectorAll('.table-responsive').forEach(table => {
                table.style.maxHeight = 'none';
            });
        }
    }, 100);
});

// Prevenir scroll del body cuando modal está abierto
function lockBodyScroll() {
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
}

function unlockBodyScroll() {
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

// Mejorar clicks en móvil (eliminar delay de 300ms)
if (isTouchDevice()) {
    document.addEventListener('DOMContentLoaded', function() {
        // Fast click implementation
        let touchStartX = 0;
        let touchStartY = 0;
        
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            
            // Si el movimiento fue mínimo, considerar como tap
            const deltaX = Math.abs(touchEndX - touchStartX);
            const deltaY = Math.abs(touchEndY - touchStartY);
            
            if (deltaX < 10 && deltaY < 10) {
                const target = e.target;
                if (target.tagName === 'A' || target.tagName === 'BUTTON' || target.classList.contains('btn')) {
                    // Es un tap válido, el navegador manejará el click
                }
            }
        }, { passive: true });
    });
}

// Vibración táctil para acciones importantes (si está disponible)
function hapticFeedback(type = 'light') {
    if ('vibrate' in navigator) {
        switch(type) {
            case 'light':
                navigator.vibrate(10);
                break;
            case 'medium':
                navigator.vibrate(20);
                break;
            case 'heavy':
                navigator.vibrate(50);
                break;
            case 'success':
                navigator.vibrate([10, 50, 10]);
                break;
            case 'error':
                navigator.vibrate([50, 100, 50]);
                break;
        }
    }
}

// Agregar haptic feedback a botones importantes
document.addEventListener('DOMContentLoaded', function() {
    if (isTouchDevice()) {
        // Feedback en botones de guardar/crear
        document.querySelectorAll('.btn-success, .btn-primary').forEach(btn => {
            btn.addEventListener('touchstart', function() {
                hapticFeedback('light');
            }, { passive: true });
        });
        
        // Feedback en botones de eliminar/cancelar
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('touchstart', function() {
                hapticFeedback('medium');
            }, { passive: true });
        });
    }
});

// Detectar si el usuario está en modo standalone (PWA)
function isStandalone() {
    return (window.matchMedia('(display-mode: standalone)').matches) || 
           (window.navigator.standalone) || 
           document.referrer.includes('android-app://');
}

// Log de modo de visualización
if (isMobile()) {
    console.log('📱 Modo móvil activo');
    console.log('Touch device:', isTouchDevice());
    console.log('Orientación:', getOrientation());
    console.log('Standalone:', isStandalone());
}

// Exportar funciones para uso global
window.mobileUtils = {
    isMobile,
    isTouchDevice,
    getOrientation,
    lockBodyScroll,
    unlockBodyScroll,
    hapticFeedback,
    isStandalone
};
