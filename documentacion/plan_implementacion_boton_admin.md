# Plan de Implementación: Botón de Panel de Administración

Este plan detalla los cambios necesarios para añadir un acceso directo al Panel de Administración directamente en el menú de navegación principal para usuarios con permisos.

## Cambios Propuestos

### Componente: Interfaz de Usuario (Header)

#### [MODIFY] [header.php](file:///c:/mis%20app%20de%20noxertez%202/SahtoutCMS-main/Sahtout/includes/header.php)
Se añadirá una condición en el menú `<nav>` para mostrar el enlace al panel de administración si el usuario tiene el nivel de GM adecuado o el rol de administrador/moderador.

```php
<?php if (!empty($_SESSION['user_id']) && ($gmlevel > 0 || $role === 'admin' || $role === 'moderator')): ?>
    <a href="<?php echo $base_path; ?>admin/dashboard" class="admin-btn">
        <i class="fas fa-cogs"></i> <?php echo translate('admin_panel', 'Admin Panel'); ?>
    </a>
<?php endif; ?>
```

## Plan de Verificación

### Verificación Manual
1. **Acceso como Administrador:** Iniciar sesión con una cuenta que tenga permisos de administrador. Verificar que el botón "Panel de administración" aparece en el menú superior (al lado de "Cuenta").
2. **Acceso como Usuario Normal:** Iniciar sesión con una cuenta sin permisos (jugador). Verificar que el botón **NO** aparece en el menú superior.
3. **Cierre de Sesión:** Verificar que el botón desaparece al cerrar la sesión.
