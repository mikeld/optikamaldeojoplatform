<?php
// footer.php
?>
  </div> <!-- /.container-fluid -->

  <!-- Toast container -->
  <div id="toast-container" aria-live="polite" aria-atomic="true"
       style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;min-width:280px;max-width:380px;"></div>

  <!-- Scripts al final -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script>
  /* ── Toast system ────────────────────────────────────────────── */
  function showToast(msg, type = 'success', duration = 3500) {
    const icons = {
      success: '<i class="fas fa-check-circle"></i>',
      error:   '<i class="fas fa-times-circle"></i>',
      warning: '<i class="fas fa-exclamation-triangle"></i>',
      info:    '<i class="fas fa-info-circle"></i>',
    };
    const colors = {
      success: '#059669',
      error:   '#dc2626',
      warning: '#d97706',
      info:    '#2563eb',
    };
    const bg = { success:'#f0fdf4', error:'#fef2f2', warning:'#fffbeb', info:'#eff6ff' };

    const container = document.getElementById('toast-container');
    const el = document.createElement('div');
    el.style.cssText = `
      display:flex; align-items:flex-start; gap:10px;
      background:${bg[type]||'#fff'};
      border:1.5px solid ${colors[type]||'#e2e8f0'};
      border-left:4px solid ${colors[type]||'#e2e8f0'};
      border-radius:10px; padding:13px 16px;
      box-shadow:0 4px 20px rgba(0,0,0,.12);
      font-size:.86rem; font-family:inherit; color:#0f172a;
      animation:toastIn .25s ease-out both;
    `;
    el.innerHTML = `
      <span style="color:${colors[type]||'#475569'};font-size:1rem;flex-shrink:0;padding-top:1px">${icons[type]||icons.info}</span>
      <span style="flex:1;line-height:1.45">${msg}</span>
      <button onclick="this.closest('div').remove()" style="background:none;border:none;cursor:pointer;padding:0;color:#94a3b8;font-size:.95rem;flex-shrink:0">&#x2715;</button>
    `;
    container.appendChild(el);
    setTimeout(() => {
      el.style.animation = 'toastOut .25s ease-in forwards';
      setTimeout(() => el.remove(), 250);
    }, duration);
  }

  /* ── Toggle table sections ───────────────────────────────────── */
  function toggleTable(idTabla, idBtn, tableName) {
    const tabla = document.getElementById(idTabla);
    const boton = document.getElementById(idBtn);
    if (!tabla || !boton) return;
    tabla.classList.toggle('is-collapsed');
    const label = tableName ? ' ' + tableName : '';
    if (tabla.classList.contains('is-collapsed')) {
      boton.innerHTML = '<i class="fas fa-eye me-1"></i> Mostrar' + label;
    } else {
      boton.innerHTML = '<i class="fas fa-eye-slash me-1"></i> Ocultar' + label;
    }
  }
  </script>

  <style>
  @keyframes toastIn  { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:none; } }
  @keyframes toastOut { from { opacity:1; transform:none; } to { opacity:0; transform:translateX(20px); } }
  </style>
</body>
</html>
