
import React, { useEffect, useMemo, useState } from 'react';
import { db } from '../db';
import { AuditRecord } from '../types';
import { FileText, Eye, X, Download, Trash2, ExternalLink, Loader2, Search, SlidersHorizontal } from 'lucide-react';

type SortMode = 'invoice_desc' | 'invoice_asc' | 'created_desc' | 'created_asc' | 'provider_asc';

const HistoryPage: React.FC = () => {
  const [history, setHistory] = useState<AuditRecord[]>([]);
  const [selectedAudit, setSelectedAudit] = useState<AuditRecord | null>(null);
  const [deletingPdfId, setDeletingPdfId] = useState<string | null>(null);
  const [deletingAuditId, setDeletingAuditId] = useState<string | null>(null);
  const [query, setQuery] = useState('');
  const [providerFilter, setProviderFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sortMode, setSortMode] = useState<SortMode>('invoice_desc');

  useEffect(() => {
    fetchHistory();
    // Parse provider parameter from URL hash (e.g. #/history?provider=Alcon)
    const hash = window.location.hash;
    const match = hash.match(/[?&]provider=([^&]+)/);
    if (match) {
      setProviderFilter(decodeURIComponent(match[1]));
    }
  }, []);

  const fetchHistory = async () => {
    const data = await db.getAudits();
    setHistory(data);
  };

  const exportToCSV = (audit: AuditRecord) => {
    const headers = ["Descripcion", "Cantidad", "Precio Factura", "Importe Linea", "Precio Maestro", "Diferencia"];
    const rows = audit.lines.map(l => [
      l.invoiceDescription,
      l.quantity,
      l.invoiceUnitPrice,
      l.invoiceLineTotal ?? l.invoiceUnitPrice * l.quantity,
      l.masterProductPrice || 0,
      l.difference
    ]);
    
    let csvContent = "data:text/csv;charset=utf-8," 
      + headers.join(",") + "\n"
      + rows.map(e => e.join(",")).join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `auditoria_${audit.invoiceNumber}_${audit.provider}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const openInvoiceFile = (audit: AuditRecord) => {
    window.open(db.getInvoiceFileUrl(audit.id), '_blank', 'noopener,noreferrer');
  };

  const deleteInvoiceFile = async (audit: AuditRecord) => {
    if (!audit.pdfPath || !confirm(`¿Borrar el PDF guardado de la factura ${audit.invoiceNumber}?`)) return;

    setDeletingPdfId(audit.id);
    try {
      await db.deleteInvoiceFile(audit.id);
      const updatedHistory = history.map(item => item.id === audit.id ? { ...item, pdfPath: null } : item);
      setHistory(updatedHistory);
      if (selectedAudit?.id === audit.id) {
        setSelectedAudit({ ...selectedAudit, pdfPath: null });
      }
    } finally {
      setDeletingPdfId(null);
    }
  };

  const deleteAudit = async (audit: AuditRecord) => {
    const confirmed = confirm(`¿Borrar la auditoría completa de ${audit.provider} - ${audit.invoiceNumber}?\n\nSe eliminará el registro, sus alertas y el PDF asociado si existe.`);
    if (!confirmed) return;

    setDeletingAuditId(audit.id);
    try {
      await db.deleteAudit(audit.id);
      setHistory(history.filter(item => item.id !== audit.id));
      if (selectedAudit?.id === audit.id) {
        setSelectedAudit(null);
      }
    } finally {
      setDeletingAuditId(null);
    }
  };

  const formatDate = (isoString: string) => {
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return 'Fecha Inválida';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const formatDateTime = (isoString: string) => {
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return 'Fecha Inválida';
    return d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const providers = useMemo(() => {
    return Array.from(new Set(history.map(record => record.provider).filter(Boolean))).sort((a, b) => a.localeCompare(b));
  }, [history]);

  const filteredHistory = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    const filtered = history.filter(record => {
      if (providerFilter !== 'all' && record.provider !== providerFilter) return false;
      if (statusFilter !== 'all' && record.globalStatus !== statusFilter) return false;
      if (dateFrom && record.invoiceDate < dateFrom) return false;
      if (dateTo && record.invoiceDate > dateTo) return false;

      if (normalizedQuery) {
        const searchable = [
          record.provider,
          record.invoiceNumber,
          record.invoiceDate,
          record.totalInvoice?.toString(),
          ...record.lines.map(line => line.invoiceDescription)
        ].join(' ').toLowerCase();
        if (!searchable.includes(normalizedQuery)) return false;
      }

      return true;
    });

    return [...filtered].sort((a, b) => {
      if (sortMode === 'invoice_asc') return a.invoiceDate.localeCompare(b.invoiceDate);
      if (sortMode === 'created_desc') return b.createdAt.localeCompare(a.createdAt);
      if (sortMode === 'created_asc') return a.createdAt.localeCompare(b.createdAt);
      if (sortMode === 'provider_asc') return a.provider.localeCompare(b.provider) || b.invoiceDate.localeCompare(a.invoiceDate);
      return b.invoiceDate.localeCompare(a.invoiceDate);
    });
  }, [dateFrom, dateTo, history, providerFilter, query, sortMode, statusFilter]);

  const resetFilters = () => {
    setQuery('');
    setProviderFilter('all');
    setStatusFilter('all');
    setDateFrom('');
    setDateTo('');
    setSortMode('invoice_desc');
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-black text-slate-800 tracking-tight">Historial de Auditorías</h2>
          <p className="text-slate-500 font-medium">Control total sobre los registros históricos.</p>
        </div>
      </div>

      <div className="bg-white rounded-[2rem] border border-slate-100 p-5 shadow-sm space-y-4">
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-slate-700">
            <SlidersHorizontal className="w-5 h-5 text-indigo-500" />
            <h3 className="font-black">Filtros por fecha y proveedor</h3>
          </div>
          <button onClick={resetFilters} className="text-xs font-black text-slate-400 hover:text-indigo-600 transition-colors">
            Limpiar filtros
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(0,1fr))] gap-3">
          <label className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Buscar factura, producto, proveedor..."
              className="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white"
            />
          </label>

          <select value={providerFilter} onChange={(event) => setProviderFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300">
            <option value="all">Todos proveedores</option>
            {providers.map(provider => <option key={provider} value={provider}>{provider}</option>)}
          </select>

          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300">
            <option value="all">Todos estados</option>
            <option value="approved">Correcta</option>
            <option value="in_review">En revisión</option>
            <option value="pending">Pendiente</option>
            <option value="rejected">Rechazada</option>
          </select>

          <input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300" />
          <input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300" />

          <select value={sortMode} onChange={(event) => setSortMode(event.target.value as SortMode)} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-300">
            <option value="invoice_desc">Factura reciente</option>
            <option value="invoice_asc">Factura antigua</option>
            <option value="created_desc">Subida reciente</option>
            <option value="created_asc">Subida antigua</option>
            <option value="provider_asc">Proveedor A-Z</option>
          </select>
        </div>

        <div className="flex items-center justify-between text-xs font-black text-slate-400">
          <span>{filteredHistory.length} de {history.length} facturas</span>
          <span>Orden principal por fecha real de factura</span>
        </div>
      </div>

      <div className="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        {filteredHistory.length === 0 ? (
          <div className="p-20 text-center text-slate-300 font-black uppercase tracking-widest text-xs italic">
            Sin facturas para los filtros actuales
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-[9px] font-black uppercase tracking-[0.2em]">
                  <th className="px-8 py-5">Auditada</th>
                  <th className="px-8 py-5">Factura</th>
                  <th className="px-8 py-5">Proveedor</th>
                  <th className="px-8 py-5">Nº Factura</th>
                  <th className="px-8 py-5">Total</th>
                  <th className="px-8 py-5">Estado</th>
                  <th className="px-8 py-5 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredHistory.map(record => (
                  <tr key={record.id} className="hover:bg-slate-50/50 transition-colors group">
                    <td className="px-8 py-6 text-sm text-slate-700 font-bold">{formatDateTime(record.createdAt)}</td>
                    <td className="px-8 py-6 text-sm text-slate-500 font-medium italic">{record.invoiceDate}</td>
                    <td className="px-8 py-6 font-black text-slate-800 uppercase text-xs tracking-tight">{record.provider}</td>
                    <td className="px-8 py-6"><span className="bg-slate-100 px-2 py-1 rounded text-[10px] font-black">{record.invoiceNumber}</span></td>
                    <td className="px-8 py-6 font-black font-mono text-indigo-600">{record.totalInvoice.toFixed(2)}€</td>
                    <td className="px-8 py-6"><StatusBadge status={record.globalStatus} /></td>
                    <td className="px-8 py-6 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {record.pdfPath && (
                          <>
                            <button onClick={() => openInvoiceFile(record)} className="p-2.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-xl transition-all shadow-sm" title="Ver factura original">
                              <ExternalLink className="w-5 h-5" />
                            </button>
                            <button onClick={() => deleteInvoiceFile(record)} disabled={deletingPdfId === record.id} className="p-2.5 bg-rose-50 text-rose-500 hover:bg-rose-600 hover:text-white rounded-xl transition-all shadow-sm disabled:opacity-60" title="Borrar PDF">
                              {deletingPdfId === record.id ? <Loader2 className="w-5 h-5 animate-spin" /> : <Trash2 className="w-5 h-5" />}
                            </button>
                          </>
                        )}
                        <button onClick={() => setSelectedAudit(record)} className="p-2.5 bg-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white rounded-xl transition-all shadow-sm" title="Ver detalle">
                          <Eye className="w-5 h-5" />
                        </button>
                        <button onClick={() => deleteAudit(record)} disabled={deletingAuditId === record.id} className="p-2.5 bg-slate-100 text-slate-400 hover:bg-rose-600 hover:text-white rounded-xl transition-all shadow-sm disabled:opacity-60" title="Borrar auditoría">
                          {deletingAuditId === record.id ? <Loader2 className="w-5 h-5 animate-spin" /> : <Trash2 className="w-5 h-5" />}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Detalle con Exportación */}
      {selectedAudit && (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-[3rem] shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col animate-in slide-in-from-bottom-8">
            <div className="p-8 border-b border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-indigo-600 text-white rounded-2xl flex items-center justify-center"><FileText className="w-7 h-7" /></div>
                <div>
                  <h3 className="text-2xl font-black text-slate-800">{selectedAudit.provider}</h3>
                  <p className="text-slate-400 font-bold uppercase text-[10px] tracking-widest">Auditoría del {formatDate(selectedAudit.createdAt)}</p>
                </div>
              </div>
              <div className="flex gap-3">
                {selectedAudit.pdfPath && (
                  <>
                    <button onClick={() => openInvoiceFile(selectedAudit)} className="flex items-center gap-2 px-5 py-2.5 bg-indigo-50 text-indigo-700 font-black rounded-xl hover:bg-indigo-100 transition-all text-sm shadow-sm border border-indigo-100">
                      <ExternalLink className="w-4 h-4" /> VER PDF
                    </button>
                    <button onClick={() => deleteInvoiceFile(selectedAudit)} disabled={deletingPdfId === selectedAudit.id} className="flex items-center gap-2 px-5 py-2.5 bg-rose-50 text-rose-700 font-black rounded-xl hover:bg-rose-100 transition-all text-sm shadow-sm border border-rose-100 disabled:opacity-60">
                      {deletingPdfId === selectedAudit.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />} BORRAR PDF
                    </button>
                  </>
                )}
                <button onClick={() => exportToCSV(selectedAudit)} className="flex items-center gap-2 px-5 py-2.5 bg-emerald-50 text-emerald-700 font-black rounded-xl hover:bg-emerald-100 transition-all text-sm shadow-sm border border-emerald-100">
                  <Download className="w-4 h-4" /> EXPORTAR CSV
                </button>
                <button onClick={() => deleteAudit(selectedAudit)} disabled={deletingAuditId === selectedAudit.id} className="flex items-center gap-2 px-5 py-2.5 bg-slate-100 text-slate-600 font-black rounded-xl hover:bg-rose-600 hover:text-white transition-all text-sm shadow-sm disabled:opacity-60">
                  {deletingAuditId === selectedAudit.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />} BORRAR AUDITORÍA
                </button>
                <button onClick={() => setSelectedAudit(null)} className="p-3 bg-slate-100 text-slate-400 hover:text-rose-500 rounded-xl"><X className="w-6 h-6" /></button>
              </div>
            </div>
            
            <div className="flex-1 overflow-y-auto p-8">
              <div className="border border-slate-100 rounded-3xl overflow-hidden shadow-inner">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50/50">
                    <tr className="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                      <th className="px-6 py-4">Descripción</th>
                      <th className="px-6 py-4 text-center">Cant</th>
                      <th className="px-6 py-4 text-center">Facturado</th>
                      <th className="px-6 py-4 text-center">Maestro</th>
                      <th className="px-6 py-4 text-right">Estado</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {selectedAudit.lines.map((line, idx) => (
                      <tr key={idx}>
                        <td className="px-6 py-4 font-bold text-slate-800">{line.invoiceDescription}</td>
                        <td className="px-6 py-4 text-center font-bold text-slate-400">{line.quantity}</td>
                        <td className="px-6 py-4 text-center font-black font-mono">
                          {(typeof line.invoiceLineTotal === 'number' ? line.invoiceLineTotal : line.invoiceUnitPrice * line.quantity).toFixed(2)}€
                          {typeof line.invoiceLineTotal === 'number' && Math.abs(line.invoiceLineTotal - (line.invoiceUnitPrice * line.quantity)) > 0.01 && (
                            <span className="block text-[9px] text-slate-400 font-black uppercase">precio {line.invoiceUnitPrice.toFixed(2)}€</span>
                          )}
                        </td>
                        <td className="px-6 py-4 text-center font-bold text-indigo-500">{line.masterProductPrice ? `${line.masterProductPrice.toFixed(2)}€` : '-'}</td>
                        <td className="px-6 py-4 text-right">
                           <span className={`px-2 py-1 rounded-lg text-[10px] font-black ${line.difference > 0 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'}`}>
                             {line.difference > 0 ? `+${line.difference.toFixed(2)}` : 'OK'}
                           </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const StatusBadge: React.FC<{ status: string }> = ({ status }) => {
  const styles = {
    approved: 'bg-emerald-100 text-emerald-700 border-emerald-100',
    rejected: 'bg-rose-100 text-rose-700 border-rose-100',
    pending: 'bg-amber-100 text-amber-700 border-amber-100',
    in_review: 'bg-indigo-100 text-indigo-700 border-indigo-100',
  };
  const labels = {
    approved: 'Correcta',
    rejected: 'Rechazada',
    pending: 'Pendiente',
    in_review: 'En revision',
  };
  return <span className={`px-3 py-1.5 rounded-lg text-[9px] font-black tracking-widest uppercase border ${styles[status as keyof typeof styles] || 'bg-slate-100 text-slate-600 border-slate-100'}`}>{labels[status as keyof typeof labels] || status}</span>;
};

export default HistoryPage;
