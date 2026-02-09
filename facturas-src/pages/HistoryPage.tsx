
import React, { useEffect, useState } from 'react';
import { db } from '../db';
import { AuditRecord, LineStatus } from '../types';
import { FileText, Calendar, Eye, ArrowLeft, Clock, Building2, Package, X, Download } from 'lucide-react';

const HistoryPage: React.FC = () => {
  const [history, setHistory] = useState<AuditRecord[]>([]);
  const [selectedAudit, setSelectedAudit] = useState<AuditRecord | null>(null);

  useEffect(() => {
    fetchHistory();
  }, []);

  const fetchHistory = async () => {
    const data = await db.getAudits();
    setHistory(data);
  };

  const exportToCSV = (audit: AuditRecord) => {
    const headers = ["Descripcion", "Cantidad", "Precio Factura", "Precio Maestro", "Diferencia"];
    const rows = audit.lines.map(l => [
      l.invoiceDescription,
      l.quantity,
      l.invoiceUnitPrice,
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

  const formatDate = (isoString: string) => {
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return 'Fecha Inválida';
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-black text-slate-800 tracking-tight">Historial de Auditorías</h2>
          <p className="text-slate-500 font-medium">Control total sobre los registros históricos.</p>
        </div>
      </div>

      <div className="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        {history.length === 0 ? (
          <div className="p-20 text-center text-slate-300 font-black uppercase tracking-widest text-xs italic">
            Sin registros históricos
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
                  <th className="px-8 py-5 text-right">Detalle</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {history.map(record => (
                  <tr key={record.id} className="hover:bg-slate-50/50 transition-colors group">
                    <td className="px-8 py-6 text-sm text-slate-700 font-bold">{formatDate(record.createdAt)}</td>
                    <td className="px-8 py-6 text-sm text-slate-500 font-medium italic">{record.invoiceDate}</td>
                    <td className="px-8 py-6 font-black text-slate-800 uppercase text-xs tracking-tight">{record.provider}</td>
                    <td className="px-8 py-6"><span className="bg-slate-100 px-2 py-1 rounded text-[10px] font-black">{record.invoiceNumber}</span></td>
                    <td className="px-8 py-6 font-black font-mono text-indigo-600">{record.totalInvoice.toFixed(2)}€</td>
                    <td className="px-8 py-6"><StatusBadge status={record.globalStatus} /></td>
                    <td className="px-8 py-6 text-right">
                      <button onClick={() => setSelectedAudit(record)} className="p-2.5 bg-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white rounded-xl transition-all shadow-sm">
                        <Eye className="w-5 h-5" />
                      </button>
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
                <button onClick={() => exportToCSV(selectedAudit)} className="flex items-center gap-2 px-5 py-2.5 bg-emerald-50 text-emerald-700 font-black rounded-xl hover:bg-emerald-100 transition-all text-sm shadow-sm border border-emerald-100">
                  <Download className="w-4 h-4" /> EXPORTAR CSV
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
                        <td className="px-6 py-4 text-center font-black font-mono">{line.invoiceUnitPrice.toFixed(2)}€</td>
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
    COMPLETED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-rose-100 text-rose-700',
    PENDING: 'bg-amber-100 text-amber-700',
  };
  return <span className={`px-3 py-1.5 rounded-lg text-[9px] font-black tracking-widest uppercase border ${styles[status as keyof typeof styles]}`}>{status}</span>;
};

export default HistoryPage;
