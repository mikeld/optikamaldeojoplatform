<?php
// footer.php
?>
  </div> <!-- /.container-fluid -->

  <!-- Modal Stock Disponible -->
  <div class="modal fade" id="modalStockDisponible" tabindex="-1" aria-labelledby="modalStockDisponibleLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
              <div class="modal-header bg-success text-white py-3">
                  <h5 class="modal-title fw-bold" id="modalStockDisponibleLabel">
                      <i class="fas fa-boxes me-2"></i>¡Stock Disponible!
                  </h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body p-4 text-center">
                  <div class="mb-3 text-success">
                      <i class="fas fa-check-circle fa-4x animate__animated animate__bounceIn"></i>
                  </div>
                  <h5 class="fw-bold mb-3" id="stock-modal-title">Existe stock para este producto y graduación.</h5>
                  <div class="alert alert-success border-0 bg-success bg-opacity-10 text-start py-3 px-4 mb-0" id="stock-modal-details" style="border-radius: 10px;">
                      <!-- Detalles del stock -->
                  </div>
              </div>
              <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-between">
                  <button type="button" class="btn btn-secondary px-3" style="border-radius: 8px;" data-bs-dismiss="modal">Cerrar</button>
                  <a href="#" id="stock-modal-link" target="_blank" class="btn btn-success text-white px-4 fw-semibold" style="border-radius: 8px;">
                      <i class="fas fa-external-link-alt me-2"></i>Ver Stock Completo
                  </a>
              </div>
          </div>
      </div>
  </div>

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

  /* ── Modal Stock Disponible ───────────────────────────────────── */
  let modalStockDispInstance = null;
  function showStockDisponibleModal(productCode, esfValue, matchesData) {
    const modalEl = document.getElementById('modalStockDisponible');
    if (!modalEl) return;
    
    if (!modalStockDispInstance) {
      modalStockDispInstance = new bootstrap.Modal(modalEl);
    }
    
    document.getElementById('stock-modal-title').innerHTML = `¡Hay stock para el producto <strong class="text-success">${escapeHtml(productCode)}</strong> y esfera <strong class="text-success">${escapeHtml(esfValue)}</strong>!`;
    
    let detailsHtml = '<ul class="mb-0 ps-3 fw-medium">';
    matchesData.forEach(m => {
      const format = m.tipo === 'caja' ? 'caja(s)' : 'blister(s)';
      const eye = m.ojo === 'ninguno' ? 'Genérico' : m.ojo;
      
      let rxText = [];
      if (m.esf) rxText.push(`Esf: ${m.esf}`);
      if (m.cil) rxText.push(`Cil: ${m.cil}`);
      if (m.eje) rxText.push(`Eje: ${m.eje}`);
      if (m.add) rxText.push(`Add: ${m.add}`);
      if (m.rad) rxText.push(`Rad: ${m.rad}`);
      if (m.dia) rxText.push(`Dia: ${m.dia}`);
      const rxStr = rxText.length > 0 ? ` [${rxText.join(', ')}]` : '';
      
      detailsHtml += `<li class="mb-1"><strong>${m.cantidad}</strong> ${format} para <strong>ojo ${eye}</strong>${rxStr}</li>`;
    });
    detailsHtml += '</ul>';
    
    document.getElementById('stock-modal-details').innerHTML = detailsHtml;
    
    let listadoStockUrl = 'listado_stock.php';
    if (window.location.pathname.includes('/controllers/')) {
      listadoStockUrl = '../views/listado_stock.php';
    }
    
    document.getElementById('stock-modal-link').href = `${listadoStockUrl}?filtro=${encodeURIComponent(productCode)}`;
    
    modalStockDispInstance.show();
  }
  
  function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
  </script>

  <style>
  @keyframes toastIn  { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:none; } }
  @keyframes toastOut { from { opacity:1; transform:none; } to { opacity:0; transform:translateX(20px); } }
  </style>
</body>
</html>
