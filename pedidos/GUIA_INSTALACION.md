# Guía de Instalación - Portal Unificado Optikamaldeojo

## 📋 Archivos Creados

Se han creado los siguientes archivos nuevos en el proyecto PedidosMaldeojo:

### Archivos Principales
1. **`home.php`** - Pantalla de selección de proyectos (después del login)
2. **`index_unified.php`** - Nuevo sistema de login unificado
3. **`includes/auth_class.php`** - Clase de autenticación compartida

### Archivos Modificados
1. **`logout.php`** - Actualizado para usar la nueva clase Auth
2. **`views/header.php`** - Añadido botón "Volver al Inicio"

### En el proyecto AuditoriasCarretero
1. **`App.tsx`** - Añadido botón "Volver al Inicio" en el sidebar
2. **`index.html`** - Añadido Font Awesome para iconos

## 🚀 Pasos de Instalación

### 1. Preparar el Proyecto React (AuditoriasCarretero)

Primero, compila el proyecto React para producción:

```bash
cd /Users/mikel/Desktop/Desarrollador/ProyectosPersonales/AuditoriasCarretero
npm run build
```

Esto creará una carpeta `dist/` con los archivos compilados.

### 2. Crear la Estructura de Directorios

Crea un nuevo directorio para el portal unificado y organiza los proyectos:

```bash
cd /Users/mikel/Desktop/Desarrollador/ProyectosPersonales

# Crear directorio principal
mkdir OptikamaldeojoPlatform
cd OptikamaldeojoPlatform

# Crear subdirectorios
mkdir pedidos facturas
```

### 3. Mover los Proyectos

```bash
# Copiar el proyecto de Pedidos (manteniendo el original como respaldo)
cp -r ../PedidosMaldeojo/* pedidos/

# Copiar el build del proyecto de Facturas
cp -r ../AuditoriasCarretero/dist/* facturas/
```

### 4. Mover Archivos del Sistema Unificado a la Raíz

Los archivos de login y home deben estar en la raíz del portal:

```bash
# Desde dentro de OptikamaldeojoPlatform
mv pedidos/index_unified.php index.php
mv pedidos/home.php .
mv pedidos/logout.php .

# Copiar recursos necesarios
cp -r pedidos/includes .
cp -r pedidos/assets .
```

### 5. Actualizar Rutas en home.php

Edita `home.php` y actualiza las rutas de los proyectos:

```html
<a href="pedidos/views/listado_pedidos.php?orden_columna=fecha_llegada&orden_direccion=ASC" class="project-card pedidos">
```

y

```html
<a href="facturas/index.html" class="project-card facturas">
```

### 6. Actualizar App.tsx en el proyecto React

El botón "Volver al Inicio" ya está configurado para apuntar a `../home.php`, lo cual funcionará correctamente.

### 7. Configurar el Servidor Web

Asegúrate de que tu servidor web (Apache/Nginx) apunte al directorio `OptikamaldeojoPlatform`.

**Para Apache** (archivo `.htaccess` en la raíz):
```apache
DirectoryIndex index.php
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L,QSA]
```

## 📁 Estructura Final

```
OptikamaldeojoPlatform/
├── index.php                    # Login unificado
├── home.php                     # Selección de proyectos
├── logout.php                   # Cerrar sesión
├── .htaccess                   # Configuración Apache
├── includes/
│   ├── auth_class.php          # Clase de autenticación
│   ├── auth.php                # Verificación de sesión
│   └── conexion.php            # Conexión a BD
├── assets/
│   ├── css/
│   └── images/
├── pedidos/                     # Proyecto PedidosMaldeojo
│   ├── controllers/
│   ├── views/
│   │   ├── header.php          # Con botón "Volver al Inicio"
│   │   └── ...
│   └── ...
└── facturas/                    # Proyecto AuditoriasCarretero (compilado)
    ├── index.html
    ├── assets/
    └── ...
```

## 🔐 Flujo de Autenticación

1. **Usuario accede** → `index.php` (login)
2. **Login exitoso** → `home.php` (selección de proyectos)
3. **Selecciona proyecto** → Redirige a `pedidos/` o `facturas/`
4. **Dentro del proyecto** → Botón "Volver al Inicio" lleva a `home.php`
5. **Logout** → `logout.php` → destruye sesión → vuelve a `index.php`

## ✅ Verificación

1. Accede a `http://tu-servidor/` → Deberías ver el login
2. Inicia sesión con tus credenciales
3. Deberías ver la pantalla de selección con dos tarjetas
4. Haz clic en cualquier proyecto para acceder
5. Verifica que el botón "Volver al Inicio" funciona
6. Prueba el logout

## 🎨 Personalización

### Cambiar Logo

Reemplaza el archivo:
```
assets/images/logo_placeholder.png
```

Con tu logo real de Optikamaldeojo.

### Colores

Los colores principales se pueden ajustar en:
- **Login/Home**: Estilos inline en `index.php` y `home.php`
- **Gradientes**: Busca `linear-gradient` en los archivos

## ⚠️ Notas Importantes

1. **Base de Datos**: Ambos proyectos comparten la misma base de datos de usuarios
2. **Sesiones**: La sesión PHP es compartida entre ambos proyectos
3. **Rutas**: Todas las rutas son relativas desde la raíz del portal
4. **Build React**: Cada vez que actualices el proyecto React, debes:
   - Ejecutar `npm run build`
   - Copiar el nuevo `dist/` a `facturas/`

## 🔧 Troubleshooting

### "Class 'Auth' not found"
- Verifica que `includes/auth_class.php` existe
- Asegúrate de que el `require` apunta correctamente

### "Headers already sent"
- Revisa que no haya espacios antes de `<?php`
- Verifica que no haya `echo` antes de `header()`

### El proyecto React no carga
- Verifica que copiaste todo el contenido de `dist/`
- Asegúrate de que `index.html` está en `facturas/`
- Revisa las rutas en el navegador (F12 → Network)
