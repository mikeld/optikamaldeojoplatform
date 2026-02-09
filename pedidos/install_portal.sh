#!/bin/bash

# Script de Instalación Automatizada - Portal Unificado Optikamaldeojo
# Este script crea la estructura del portal unificado y mueve los archivos necesarios

echo "=============================================="
echo "Portal Unificado Optikamaldeojo - Instalación"
echo "=============================================="
echo ""

# Variables
BASE_DIR="/Users/mikel/Desktop/Desarrollador/ProyectosPersonales"
PLATFORM_DIR="$BASE_DIR/OptikamaldeojoPlatform"
PEDIDOS_SRC="$BASE_DIR/PedidosMaldeojo"
FACTURAS_SRC="$BASE_DIR/AuditoriasCarretero"

# Paso 1: Compilar proyecto React
echo "[1/6] Compilando proyecto React (AuditoriasCarretero)..."
cd "$FACTURAS_SRC"
npm run build

if [ ! -d "dist" ]; then
    echo "❌ Error: No se pudo compilar el proyecto React"
    exit 1
fi
echo "✅ Proyecto React compilado"

# Paso 2: Crear estructura de directorios
echo ""
echo "[2/6] Creando estructura de directorios..."
mkdir -p "$PLATFORM_DIR"/{pedidos,facturas,includes,assets/{css,images,js}}
echo "✅ Estructura creada"

# Paso 3: Copiar proyecto Pedidos
echo ""
echo "[3/6] Copiando proyecto PedidosMaldeojo..."
cp -r "$PEDIDOS_SRC"/* "$PLATFORM_DIR/pedidos/"
echo "✅ Proyecto Pedidos copiado"

# Paso 4: Copiar build de Facturas
echo ""
echo "[4/6] Copiando proyecto AuditoriasCarretero (compilado)..."
cp -r "$FACTURAS_SRC/dist"/* "$PLATFORM_DIR/facturas/"
echo "✅ Proyecto Facturas copiado"

# Paso 5: Mover archivos del sistema unificado a la raíz
echo ""
echo "[5/6] Configurando archivos del portal unificado..."

# Mover archivos principales
mv "$PLATFORM_DIR/pedidos/index_unified.php" "$PLATFORM_DIR/index.php"
mv "$PLATFORM_DIR/pedidos/home.php" "$PLATFORM_DIR/home.php"
mv "$PLATFORM_DIR/pedidos/logout.php" "$PLATFORM_DIR/logout.php"

# Copiar includes y assets a la raíz
cp -r "$PLATFORM_DIR/pedidos/includes"/* "$PLATFORM_DIR/includes/"
cp -r "$PLATFORM_DIR/pedidos/assets"/* "$PLATFORM_DIR/assets/"

echo "✅ Archivos configurados"

# Paso 6: Crear archivo .htaccess
echo ""
echo "[6/6] Creando archivo .htaccess..."
cat > "$PLATFORM_DIR/.htaccess" << 'EOF'
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirigir a index.php si el archivo no existe
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>

# Evitar listado de directorios
Options -Indexes

# Proteger archivos sensibles
<FilesMatch "\.(md|env|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF

echo "✅ Archivo .htaccess creado"

# Resumen
echo ""
echo "=============================================="
echo "✅ Instalación completada exitosamente!"
echo "=============================================="
echo ""
echo "📁 Portal instalado en:"
echo "   $PLATFORM_DIR"
echo ""
echo "📝 Próximos pasos:"
echo "   1. Configura tu servidor web para apuntar a:"
echo "      $PLATFORM_DIR"
echo ""
echo "   2. Accede al portal en tu navegador"
echo ""
echo "   3. Inicia sesión con tus credenciales"
echo ""
echo "🎨 Para personalizar:"
echo "   - Logo: $PLATFORM_DIR/assets/images/logo_placeholder.png"
echo "   - Estilos: $PLATFORM_DIR/index.php y home.php (inline styles)"
echo ""
echo "📖 Más información en:"
echo "   $PLATFORM_DIR/pedidos/GUIA_INSTALACION.md"
echo ""
