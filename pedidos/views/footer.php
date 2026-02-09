<?php
// footer.php
?>
  </div> <!-- /.container-fluid -->
  <!-- Scripts al final -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script>
  function toggleTable(idTabla, idBtn, tableName) {
    const tabla = document.getElementById(idTabla);
    const boton = document.getElementById(idBtn);

    if (!tabla || !boton) {
      console.error("Tabla o botón no encontrado para:", idTabla, idBtn);
      return;
    }

    // Toggle the 'is-collapsed' class
    tabla.classList.toggle('is-collapsed');

    // Update button text
    if (tabla.classList.contains('is-collapsed')) {
      boton.innerHTML = '<i class="fas fa-eye me-1"></i> Mostrar ' + tableName;
    } else {
      boton.innerHTML = '<i class="fas fa-eye-slash me-1"></i> Ocultar ' + tableName;
    }
  }
  </script>
</body>
</html>
