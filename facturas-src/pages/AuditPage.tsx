
import React, { useState, useRef, useEffect } from 'react';
import { Upload, FileText, Loader2, CheckCircle2, XCircle, AlertCircle, RefreshCw, Save, ArrowLeft, PlusCircle, Database, Trash2, X, CheckSquare, Edit3, AlertTriangle, ArrowUpCircle, ArrowDownCircle } from 'lucide-react';
import { extractInvoiceData } from '../services/geminiService';
import { db } from '../db';
import { InvoiceData, AuditLine, LineStatus, Product, AuditRecord } from '../types';

const AuditPage: React.FC = () => {
  const [file, setFile] = useState<File | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [auditResult, setAuditResult] = useState<AuditRecord | null>(null);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [modalMode, setModalMode] = useState<'NONE' | 'SKU' | 'PRICE_UPDATE' | 'FINALIZE' | 'SUCCESS' | 'EDIT_LINE'>('NONE');
  const [activeLineIdx, setActiveLineIdx] = useState<number | null>(null);
  const [tempSku, setTempSku] = useState('');
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);
  const [editLineData, setEditLineData] = useState<AuditLine | null>(null);

  useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (auditResult) {
        e.preventDefault();
        e.returnValue = 'Tienes una auditoría en curso. Si sales ahora, perderás los cambios no guardados.';
        return e.returnValue;
      }
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [auditResult]);

  const processInvoice = async () => {
    if (!file) return;
    setIsProcessing(true);
    setError(null);
    
    try {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = async () => {
        const base64 = (reader.result as string).split(',')[1];
        const extracted = await extractInvoiceData(base64, file.type);
        const masterProducts = await db.getProducts();

        const auditLines: AuditLine[] = extracted.items.map((item, idx) => {
          const match = masterProducts.find(p => 
            p.name.toLowerCase().trim() === item.description.toLowerCase().trim() ||
            p.sku.toLowerCase().trim() === item.description.toLowerCase().trim()
          );

          let status = LineStatus.DISCREPANCY;
          if (!match) status = LineStatus.NEW_PRODUCT;
          else if (Math.abs(match.expectedPrice - item.unitPrice) < 0.01) status = LineStatus.MATCHED;

          return {
            id: `line-${idx}-${Date.now()}`,
            invoiceDescription: item.description,
            quantity: item.quantity,
            invoiceUnitPrice: item.unitPrice,
            masterProductPrice: match?.expectedPrice,
            masterProductId: match?.id,
            status: status,
            difference: match ? item.unitPrice - match.expectedPrice : 0
          };
        });

        setAuditResult({
          id: `temp-${Date.now()}`,
          createdAt: new Date().toISOString(),
          invoiceDate: extracted.date || new Date().toISOString().split('T')[0],
          provider: extracted.providerName,
          invoiceNumber: extracted.invoiceNumber || 'S/N',
          lines: auditLines,
          totalInvoice: extracted.total,
          globalStatus: 'PENDING'
        });
        setIsProcessing(false);
      };
    } catch (err) {
      setError("Error al procesar la factura. Verifica que la imagen sea clara.");
      setIsProcessing(false);
    }
  };

  const handleLineAction = async (lineIdx: number, action: 'ACCEPT' | 'REJECT' | 'ADD_TO_CATALOG' | 'EDIT' | 'RESOLVE_PRICE') => {
    if (!auditResult) return;
    const lines = [...auditResult.lines];
    const line = lines[lineIdx];

    if (action === 'ACCEPT') {
      line.status = LineStatus.ACCEPTED;
    } 
    else if (action === 'RESOLVE_PRICE') {
      setActiveLineIdx(lineIdx);
      setModalMode('PRICE_UPDATE');
      return;
    }
    else if (action === 'REJECT') {
      line.status = LineStatus.REJECTED;
    } 
    else if (action === 'ADD_TO_CATALOG') {
      setActiveLineIdx(lineIdx);
      setTempSku(`SKU-${Math.floor(Math.random() * 10000)}`);
      setModalMode('SKU');
      return;
    }
    else if (action === 'EDIT') {
      setActiveLineIdx(lineIdx);
      setEditLineData({ ...line });
      setModalMode('EDIT_LINE');
      return;
    }

    setAuditResult({ ...auditResult, lines });
  };

  const confirmPriceResolution = async (shouldUpdateMaster: boolean) => {
    if (activeLineIdx === null || !auditResult) return;
    const lines = [...auditResult.lines];
    const line = lines[activeLineIdx];

    if (shouldUpdateMaster && line.masterProductId) {
      const products = await db.getProducts();
      const p = products.find(prod => prod.id === line.masterProductId);
      if (p) {
        await db.upsertProduct({ ...p, expectedPrice: line.invoiceUnitPrice });
      }
    }
    
    line.status = LineStatus.ACCEPTED;
    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setActiveLineIdx(null);
  };

  const confirmCreateProduct = async () => {
    if (activeLineIdx === null || !auditResult || !tempSku) return;
    const lines = [...auditResult.lines];
    const line = lines[activeLineIdx];

    const newProd: Product = {
      id: `prod-${Date.now()}`,
      sku: tempSku,
      name: line.invoiceDescription,
      expectedPrice: line.invoiceUnitPrice,
      vat: 21
    };
    
    await db.upsertProduct(newProd);
    line.status = LineStatus.ACCEPTED;
    line.masterProductId = newProd.id;
    line.masterProductPrice = newProd.expectedPrice;
    
    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setActiveLineIdx(null);
    setTempSku('');
  };

  const saveEditedLine = () => {
    if (activeLineIdx === null || !auditResult || !editLineData) return;
    const lines = [...auditResult.lines];
    
    const diff = editLineData.masterProductPrice ? editLineData.invoiceUnitPrice - editLineData.masterProductPrice : 0;
    let newStatus = editLineData.status;
    
    if (editLineData.masterProductPrice) {
      newStatus = Math.abs(diff) < 0.01 ? LineStatus.MATCHED : LineStatus.DISCREPANCY;
    }

    lines[activeLineIdx] = { ...editLineData, difference: diff, status: newStatus };
    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setEditLineData(null);
  };

  const validateAllItems = async () => {
    if (!auditResult) return;
    setIsBulkProcessing(true);
    const updatedLines = [...auditResult.lines];
    const products = await db.getProducts();

    for (let i = 0; i < updatedLines.length; i++) {
      const line = updatedLines[i];
      if (line.status === LineStatus.REJECTED || line.status === LineStatus.ACCEPTED) continue;
      
      if (line.status === LineStatus.NEW_PRODUCT) {
        const newSku = `AUTO-${Math.floor(Math.random() * 99999)}`;
        const newProd: Product = {
          id: `prod-${Date.now()}-${i}`,
          sku: newSku,
          name: line.invoiceDescription,
          expectedPrice: line.invoiceUnitPrice,
          vat: 21
        };
        await db.upsertProduct(newProd);
        line.status = LineStatus.ACCEPTED;
        line.masterProductId = newProd.id;
        line.masterProductPrice = newProd.expectedPrice;
      } 
      else if (line.status === LineStatus.DISCREPANCY && line.masterProductId) {
        const p = products.find(prod => prod.id === line.masterProductId);
        if (p) {
          await db.upsertProduct({ ...p, expectedPrice: line.invoiceUnitPrice });
        }
        line.status = LineStatus.ACCEPTED;
      }
      else {
        line.status = LineStatus.ACCEPTED;
      }
    }

    setAuditResult({ ...auditResult, lines: updatedLines });
    setIsBulkProcessing(false);
  };

  const executeFinalize = async (status: 'COMPLETED' | 'REJECTED') => {
    if (!auditResult) return;
    try {
      await db.saveAudit({ ...auditResult, globalStatus: status });
      setModalMode('SUCCESS');
    } catch (e: any) {
      setError(`Error: ${e.message}`);
      setModalMode('NONE');
    }
  };

  if (auditResult) {
    return (
      <div className="space-y-6 max-w-6xl mx-auto pb-20">
        {/* Modal Resolución de Precio */}
        {modalMode === 'PRICE_UPDATE' && activeLineIdx !== null && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[80] flex items-center justify-center p-4">
            <div className="bg-white rounded-[3rem] shadow-2xl max-w-lg w-full p-10 animate-in zoom-in duration-200">
              <div className="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center mx-auto mb-6 text-amber-500">
                <AlertTriangle className="w-10 h-10" />
              </div>
              <h3 className="text-2xl font-black text-slate-800 text-center mb-2">Cambio de Precio</h3>
              <p className="text-slate-500 text-center text-sm mb-8 leading-relaxed">
                El ítem <strong>{auditResult.lines[activeLineIdx].invoiceDescription}</strong> ha variado. ¿Cómo quieres proceder?
              </p>
              
              <div className="grid grid-cols-2 gap-4 mb-8">
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                  <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Precio Catálogo</p>
                  <p className="text-xl font-black text-slate-400 font-mono line-through">{auditResult.lines[activeLineIdx].masterProductPrice?.toFixed(2)}€</p>
                </div>
                <div className="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 text-center">
                  <p className="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Nuevo Precio</p>
                  <p className="text-xl font-black text-indigo-600 font-mono">{auditResult.lines[activeLineIdx].invoiceUnitPrice.toFixed(2)}€</p>
                </div>
              </div>

              <div className="space-y-3">
                <button 
                  onClick={() => confirmPriceResolution(true)} 
                  className="w-full py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-2"
                >
                  <RefreshCw className="w-5 h-5" /> ACTUALIZAR MAESTRO Y VALIDAR
                </button>
                <button 
                  onClick={() => confirmPriceResolution(false)} 
                  className="w-full py-4 bg-white text-slate-600 font-black rounded-2xl border-2 border-slate-100 hover:bg-slate-50 transition-all"
                >
                  Solo validar para esta factura
                </button>
                <button 
                  onClick={() => setModalMode('NONE')} 
                  className="w-full py-3 text-slate-400 font-bold hover:text-slate-600 transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal Edición de Línea */}
        {modalMode === 'EDIT_LINE' && editLineData && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
            <div className="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full p-10 animate-in zoom-in duration-200">
              <h3 className="text-2xl font-black text-slate-800 mb-6">Corregir OCR</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Descripción</label>
                  <input 
                    value={editLineData.invoiceDescription}
                    onChange={(e) => setEditLineData({...editLineData, invoiceDescription: e.target.value})}
                    className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold outline-none focus:border-indigo-500"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Cantidad</label>
                    <input 
                      type="number"
                      value={editLineData.quantity}
                      onChange={(e) => setEditLineData({...editLineData, quantity: parseFloat(e.target.value)})}
                      className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold font-mono outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Precio (€)</label>
                    <input 
                      type="number"
                      step="0.01"
                      value={editLineData.invoiceUnitPrice}
                      onChange={(e) => setEditLineData({...editLineData, invoiceUnitPrice: parseFloat(e.target.value)})}
                      className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold font-mono outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>
              </div>
              <div className="flex gap-3 pt-8">
                <button onClick={() => setModalMode('NONE')} className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors">CANCELAR</button>
                <button onClick={saveEditedLine} className="flex-1 py-4 bg-slate-900 text-white font-black rounded-2xl hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200">GUARDAR</button>
              </div>
            </div>
          </div>
        )}

        <div className="flex flex-col md:flex-row md:items-center justify-between bg-white p-6 rounded-2xl border border-slate-100 shadow-sm gap-4">
          <div className="flex items-center gap-4">
            <button onClick={() => { if (confirm('¿Cancelar auditoría?')) setAuditResult(null); }} className="p-2 hover:bg-slate-100 rounded-full"><ArrowLeft /></button>
            <div>
              <h2 className="text-2xl font-black text-slate-800 leading-tight">{auditResult.provider}</h2>
              <div className="flex gap-3 text-xs text-slate-400 mt-1 uppercase font-black">
                <span>FAC: {auditResult.invoiceNumber}</span>
                <span>•</span>
                <span>FECHA: {auditResult.invoiceDate}</span>
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button onClick={validateAllItems} disabled={isBulkProcessing} className="px-5 py-2.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl hover:bg-emerald-100 transition-all flex items-center gap-2">
              {isBulkProcessing ? <Loader2 className="animate-spin w-4 h-4" /> : <CheckSquare className="w-4 h-4" />}
              Validar Todo
            </button>
            <button onClick={() => executeFinalize('COMPLETED')} className="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 flex items-center gap-2 shadow-lg shadow-indigo-100 transition-all">
              <Save className="w-5 h-5" /> Finalizar Auditoría
            </button>
          </div>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <table className="w-full text-left">
            <thead>
              <tr className="bg-slate-50/50 text-slate-400 text-[9px] font-black uppercase tracking-[0.2em] border-b border-slate-100">
                <th className="px-6 py-4">Descripción en Factura</th>
                <th className="px-6 py-4 text-center">Precio Catálogo</th>
                <th className="px-6 py-4 text-center">Precio Factura</th>
                <th className="px-6 py-4">Estado</th>
                <th className="px-6 py-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {auditResult.lines.map((line, idx) => {
                const isDiscrepancy = line.status === LineStatus.DISCREPANCY;
                return (
                  <tr key={line.id} className={`transition-all ${isDiscrepancy ? 'bg-rose-50/20' : 'hover:bg-slate-50/30'}`}>
                    <td className="px-6 py-4">
                      <p className="font-bold text-slate-800 leading-tight">{line.invoiceDescription}</p>
                      <p className="text-[10px] text-slate-400 font-bold uppercase mt-1">Cantidad: {line.quantity}</p>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-mono text-indigo-400 font-bold">{line.masterProductPrice ? `${line.masterProductPrice.toFixed(2)}€` : '--'}</span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex flex-col items-center">
                        <span className={`text-lg font-black font-mono ${isDiscrepancy ? 'text-rose-600' : 'text-slate-800'}`}>
                          {line.invoiceUnitPrice.toFixed(2)}€
                        </span>
                        {isDiscrepancy && (
                          <span className="flex items-center gap-1 text-[9px] font-black text-rose-500 uppercase mt-0.5">
                            {line.difference > 0 ? <ArrowUpCircle className="w-2.5 h-2.5" /> : <ArrowDownCircle className="w-2.5 h-2.5" />}
                            Diferencia detectada
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <StatusBadge status={line.status} diff={line.difference} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex justify-end gap-1.5">
                        <button onClick={() => handleLineAction(idx, 'EDIT')} className="p-2 text-slate-400 hover:bg-slate-100 rounded-lg transition-all" title="Corregir OCR">
                          <Edit3 className="w-4 h-4" />
                        </button>
                        
                        {line.status === LineStatus.NEW_PRODUCT && (
                          <button onClick={() => handleLineAction(idx, 'ADD_TO_CATALOG')} className="flex items-center gap-1.5 text-[9px] font-black bg-slate-900 text-white px-3 py-2 rounded-lg hover:bg-indigo-600 transition-all uppercase">
                            <PlusCircle className="w-3.5 h-3.5" /> Vincular
                          </button>
                        )}

                        {isDiscrepancy && (
                          <button 
                            onClick={() => handleLineAction(idx, 'RESOLVE_PRICE')} 
                            className="flex items-center gap-2 px-3 py-2 bg-rose-600 text-white rounded-lg text-[10px] font-black hover:bg-rose-700 transition-all shadow-lg shadow-rose-100 uppercase"
                          >
                            <RefreshCw className="w-3.5 h-3.5" /> Resolver
                          </button>
                        )}

                        {line.status === LineStatus.PENDING && (
                           <button onClick={() => handleLineAction(idx, 'ACCEPT')} className="p-2 text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all">
                            <CheckCircle2 className="w-6 h-6" />
                          </button>
                        )}

                        {(line.status === LineStatus.ACCEPTED || line.status === LineStatus.MATCHED) && (
                          <div className="flex items-center gap-1.5 text-emerald-600 font-black text-[9px] bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-100 uppercase">
                            <CheckCircle2 className="w-3.5 h-3.5" /> Validado
                          </div>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto py-20 space-y-8">
      <div className="text-center space-y-4">
        <div className="bg-indigo-600 w-24 h-24 rounded-[2.5rem] flex items-center justify-center mx-auto text-white shadow-2xl shadow-indigo-200">
          <Upload className="w-12 h-12" />
        </div>
        <h1 className="text-4xl font-black text-slate-800 tracking-tight">Cargar Factura</h1>
        <p className="text-slate-500 text-lg">Inicia una auditoría subiendo una foto o PDF.</p>
      </div>
      <div onClick={() => fileInputRef.current?.click()} className="group border-3 border-dashed rounded-[3rem] p-20 text-center cursor-pointer transition-all border-slate-200 hover:border-indigo-400 hover:bg-white">
        <input type="file" ref={fileInputRef} onChange={(e) => e.target.files && setFile(e.target.files[0])} hidden accept="image/*,application/pdf" />
        <PlusCircle className="w-12 h-12 text-slate-300 mx-auto mb-4 group-hover:text-indigo-500 transition-colors" />
        <p className="text-slate-500 font-bold">{file ? file.name : 'Haz clic para seleccionar archivo'}</p>
      </div>
      <button disabled={!file || isProcessing} onClick={processInvoice} className="w-full py-6 bg-slate-900 text-white rounded-[2rem] font-black text-xl hover:bg-indigo-600 disabled:bg-slate-100 transition-all flex items-center justify-center gap-4">
        {isProcessing ? <><Loader2 className="animate-spin w-6 h-6" /> PROCESANDO...</> : "EMPEZAR AUDITORÍA"}
      </button>
    </div>
  );
};

const StatusBadge: React.FC<{ status: LineStatus, diff: number }> = ({ status, diff }) => {
  const cfg = {
    [LineStatus.MATCHED]: { color: 'text-emerald-600 bg-emerald-50 border-emerald-100', label: 'PRECIO OK' },
    [LineStatus.DISCREPANCY]: { color: 'text-rose-600 bg-rose-50 border-rose-100', label: `DIF: ${diff > 0 ? '+' : ''}${diff.toFixed(2)}€` },
    [LineStatus.NEW_PRODUCT]: { color: 'text-amber-600 bg-amber-50 border-amber-100', label: 'NO EN CATÁLOGO' },
    [LineStatus.ACCEPTED]: { color: 'text-blue-600 bg-blue-50 border-blue-100', label: 'ACEPTADO' },
    [LineStatus.REJECTED]: { color: 'text-slate-400 bg-slate-50 border-slate-200', label: 'EXCLUIDO' },
    [LineStatus.PENDING]: { color: 'text-slate-400', label: 'PENDIENTE' },
  };
  const { color, label } = cfg[status] || cfg[LineStatus.PENDING];
  return <span className={`px-3 py-1.5 rounded-lg text-[9px] font-black border tracking-wider ${color}`}>{label}</span>;
};

export default AuditPage;
