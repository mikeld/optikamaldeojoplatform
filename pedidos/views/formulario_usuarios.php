<?php
require '../includes/auth.php';
require '../includes/conexion.php';

// Definir las acciones del navbar
$acciones_navbar = [
    [
        'nombre' => 'Listado Clientes',
        'url' => 'listado_usuarios.php',
        'icono' => 'bi-people'
    ]
];

include('header.php');

// Variables para los datos del formulario
$id = null;
$referencia = '';
$telefono = '';
$email = '';
$direccion = '';

// Detectar si se está editando un cliente
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $id = (int) $_GET['id'];
        $conexion = new Conexion();
        $sql = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $conexion->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente) {
            $referencia = $cliente['referencia'];
            $telefono = $cliente['telefono'];
            $email = $cliente['email'];
            $direccion = $cliente['direccion'];
        }
    } catch (Exception $e) {
        die("Error al cargar el cliente: " . $e->getMessage());
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="text-center mb-5 section-title justify-content-center">
                <i class="fas <?= $id ? 'fa-user-edit' : 'fa-user-plus' ?>"></i> <?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?>
            </h1>
            <div class="modern-card">
                <form action="../controllers/guardar_usuario.php" method="POST" onsubmit="return validarFormulario()" class="modern-form">
                    <!-- ID oculto solo para editar -->
                    <?php if ($id): ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                    <?php endif; ?>

                    <!-- Referencia -->
                    <div class="mb-4">
                        <label for="referencia" class="form-label">Referencia del Cliente</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-2 border-end-0"><i class="fas fa-tag text-muted"></i></span>
                            <input type="text" id="referencia" name="referencia" class="form-control border-start-0" value="<?= htmlspecialchars($referencia) ?>" placeholder="Ej: REF123" required>
                        </div>
                    </div>

                    <!-- Teléfono -->
                    <div class="mb-4">
                        <label for="telefono" class="form-label">Teléfono de Contacto</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-2 border-end-0"><i class="fas fa-phone text-muted"></i></span>
                            <input type="text" id="telefono" name="telefono" 
                                   class="form-control border-start-0" 
                                   value="<?= htmlspecialchars($telefono) ?>" 
                                   placeholder="Ej: +34 600000000"
                                   required 
                                   pattern="^(\+?\d{1,3}|00\d{1,3})?\d{9}$" 
                                   title="El teléfono debe tener 9 dígitos o incluir el prefijo internacional (+34 o 0034).">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-2 border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" id="email" name="email" class="form-control border-start-0" value="<?= htmlspecialchars($email) ?>" placeholder="ejemplo@email.com">
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="mb-5">
                        <label for="direccion" class="form-label">Dirección Postal</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-2 border-end-0"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <textarea id="direccion" name="direccion" class="form-control border-start-0" rows="2" placeholder="Dirección completa..."><?= htmlspecialchars($direccion) ?></textarea>
                        </div>
                    </div>

                    <!-- Botón Enviar -->
                    <button type="submit" class="btn btn-login w-100 py-3">
                        <i class="fas fa-save me-2"></i> <?= $id ? 'Actualizar Cliente' : 'Guardar Cliente' ?>
                    </button>
                </form>               
            </div>
        </div>
    </div>
</div>

    <script>
        function validarFormulario() {
            const referencia = document.getElementById('referencia').value.trim();
            const telefono = document.getElementById('telefono').value.trim();
            //const email = document.getElementById('email').value.trim();

            if (referencia === '' || telefono === '' ) {
                alert('Los campos Referencia y Teléfono son obligatorios.');
                return false;
            }

            if (!/^(\+?\d{1,3}|00\d{1,3})?\d{9}$/.test(telefono)) {
                alert('El teléfono debe tener 9 dígitos o incluir el prefijo internacional (+34 o 0034).');
                return false;
            }

            return true;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
