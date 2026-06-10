import React, { useEffect, useState, useMemo } from 'react';
import { db } from '../db';
import { Provider, AuditRecord } from '../types';
import { 
  Building2, 
  Sparkles, 
  BookOpen, 
  Edit3, 
  Loader2, 
  Play, 
  Check, 
  X, 
  RefreshCw, 
  FileText, 
  Terminal, 
  AlertTriangle,
  History,
  CheckCircle2
} from 'lucide-react';

const ProvidersPage: React.FC = () => {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [audits, setAudits] = useState<AuditRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Modals state
  const [selectedProvider, setSelectedProvider] = useState<Provider | null>(null);
  const [showStudyModal, setShowStudyModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  
  // Layout Study state
  const [selectedAuditId, setSelectedAuditId] = useState('');
  const [isStudying, setIsStudying] = useState(false);
  const [studyStepMsg, setStudyStepMsg] = useState('');

  // Manual Edit state
  const [tempDescription, setTempDescription] = useState('');
  const [tempRules, setTempRules] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    setError('');
    try {
      const [providersList, auditsList] = await Promise.all([
        db.getProviders(),
        db.getAudits()
      ]);
      setProviders(providersList);
      setAudits(auditsList);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error al cargar proveedores');
    } finally {
      setLoading(false);
    }
  };

  // Filtrar facturas asociadas al proveedor seleccionado
  const referenceAudits = useMemo(() => {
    if (!selectedProvider) return [];
    return audits.filter(a => a.provider.trim().toLowerCase() === selectedProvider.name.trim().toLowerCase());
  }, [selectedProvider, audits]);

  // Iniciar estudio de layout
  const handleOpenStudy = (provider: Provider) => {
    setSelectedProvider(provider);
    const providerInvoices = audits.filter(a => a.provider.trim().toLowerCase() === provider.name.trim().toLowerCase());
    if (providerInvoices.length > 0) {
      setSelectedAuditId(providerInvoices[0].id);
    } else {
      setSelectedAuditId('');
    }
    setShowStudyModal(true);
  };

  const executeStudy = async () => {
    if (!selectedAuditId || !selectedProvider) return;
    setIsStudying(true);
    setStudyStepMsg('Gemini está analizando la estructura del documento...');
    
    // Rotar mensajes de carga para dar sensación Premium
    const messages = [
      'Identificando la tabla de artículos...',
      'Buscando el formato de códigos y SKUs...',
      'Analizando el patrón de nombres de paciente y pedidos...',
      'Buscando subtotal, IVA y fecha de factura...',
      'Generando reglas específicas de extracción...',
      'Guardando análisis del proveedor...'
    ];
    let msgIdx = 0;
    const interval = setInterval(() => {
      if (msgIdx < messages.length) {
        setStudyStepMsg(messages[msgIdx]);
        msgIdx++;
      }
    }, 4500);

    try {
      await db.studyProviderLayout(selectedAuditId);
      clearInterval(interval);
      setShowStudyModal(false);
      setSelectedProvider(null);
      await loadData();
    } catch (err) {
      clearInterval(interval);
      setError(err instanceof Error ? err.message : 'Error al estudiar el formato de la factura');
    } finally {
      setIsStudying(false);
    }
  };

  // Iniciar edición manual
  const handleOpenEdit = (provider: Provider) => {
    setSelectedProvider(provider);
    setTempDescription(provider.systemDescription || '');
    setTempRules(provider.extractionRules || '');
    setShowEditModal(true);
  };

  const executeSaveEdit = async () => {
    if (!selectedProvider) return;
    setIsSaving(true);
    try {
      await db.saveProviderConfig({
        id: selectedProvider.id,
        name: selectedProvider.name,
        systemDescription: tempDescription,
        extractionRules: tempRules
      });
      setShowEditModal(false);
      setSelectedProvider(null);
      await loadData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error al guardar configuración');
    } finally {
      setIsSaving(false);
    }
  };

  const stats = useMemo(() => {
    return {
      total: providers.length,
      studied: providers.filter(p => p.systemDescription !== null).length,
      pending: providers.filter(p => p.systemDescription === null).length
    };
  }, [providers]);

  return (
    <div className="space-y-8 pb-20 max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between gap-6">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
            <Building2 className="w-8 h-8 text-indigo-600" />
            Proveedores
          </h2>
          <p className="text-slate-500 font-medium">Estudia y gestiona cómo Gemini interpreta las facturas de cada proveedor.</p>
        </div>
        <button
          onClick={loadData}
          disabled={loading}
          className="flex items-center gap-2 px-5 py-3 rounded-2xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-60 transition-all"
        >
          {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
          Actualizar
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-indigo-50 text-indigo-600">
            <Building2 className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Proveedores</p>
            <p className="text-2xl font-black text-slate-800">{stats.total}</p>
          </div>
        </div>
        <div className="bg-white p-6 rounded-[2rem] border border-emerald-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-emerald-50 text-emerald-600">
            <CheckCircle2 className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Estudiados (IA)</p>
            <p className="text-2xl font-black text-slate-800">{stats.studied}</p>
          </div>
        </div>
        <div className="bg-white p-6 rounded-[2rem] border border-amber-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-amber-50 text-amber-600">
            <Sparkles className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Sin Estudiar</p>
            <p className="text-2xl font-black text-slate-800">{stats.pending}</p>
          </div>
        </div>
      </div>

      {error && (
        <div className="rounded-[2rem] p-6 border border-rose-100 bg-rose-50 text-rose-700 flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 mt-0.5 shrink-0" />
          <p className="font-bold text-sm leading-relaxed">{error}</p>
        </div>
      )}

      {/* Grid de Proveedores */}
      {loading ? (
        <div className="h-64 flex items-center justify-center text-slate-400 gap-3">
          <Loader2 className="w-8 h-8 animate-spin text-indigo-600" />
          <span className="font-bold">Cargando proveedores y reglas...</span>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {providers.map(provider => {
            const hasConfig = provider.systemDescription !== null;
            return (
              <div 
                key={provider.name} 
                className={`bg-white rounded-[2rem] p-6 border transition-all hover:shadow-md flex flex-col justify-between ${
                  hasConfig ? 'border-slate-100 bg-white' : 'border-slate-200 border-dashed bg-slate-50/50'
                }`}
              >
                <div>
                  <div className="flex items-start justify-between gap-4 mb-4">
                    <div>
                      <h3 className="text-lg font-black text-slate-800 leading-tight">{provider.name}</h3>
                      <div className="flex items-center gap-2 mt-1.5 text-xs text-slate-400 font-bold uppercase">
                        <History className="w-3.5 h-3.5" />
                        <span>{provider.invoiceCount} facturas subidas</span>
                      </div>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${
                      hasConfig ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100'
                    }`}>
                      {hasConfig ? 'Estudiado' : 'Sin Estudiar'}
                    </span>
                  </div>

                  {hasConfig ? (
                    <div className="space-y-4">
                      {/* Descripción */}
                      <div className="bg-slate-50 rounded-2xl p-4 border border-slate-100/50">
                        <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 mb-2">
                          <BookOpen className="w-3.5 h-3.5 text-indigo-500" />
                          Formato de su Factura
                        </h4>
                        <p className="text-xs font-semibold text-slate-600 leading-relaxed">
                          {provider.systemDescription}
                        </p>
                      </div>

                      {/* Reglas Técnicas */}
                      {provider.extractionRules && (
                        <div className="bg-indigo-50/20 rounded-2xl p-4 border border-indigo-50/50">
                          <h4 className="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-1.5 mb-2">
                            <Terminal className="w-3.5 h-3.5" />
                            Reglas Técnicas Inyectadas
                          </h4>
                          <p className="text-xs font-bold text-indigo-950 font-mono leading-relaxed">
                            {provider.extractionRules}
                          </p>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="h-28 flex items-center justify-center text-center p-6 border border-slate-200/50 rounded-2xl bg-white/50">
                      <p className="text-xs font-semibold text-slate-400 leading-relaxed">
                        Este proveedor es nuevo o no se ha analizado su formato de facturas.
                        Inicia el estudio para que la IA extraiga los datos con mayor precisión.
                      </p>
                    </div>
                  )}
                </div>

                {/* Acciones */}
                <div className="mt-6 pt-4 border-t border-slate-100 flex gap-2 justify-end">
                  <button
                    onClick={() => handleOpenEdit(provider)}
                    className="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 font-bold text-xs flex items-center gap-1.5 transition-colors bg-white"
                  >
                    <Edit3 className="w-3.5 h-3.5" />
                    Editar Reglas
                  </button>
                  <button
                    onClick={() => handleOpenStudy(provider)}
                    disabled={provider.invoiceCount === 0}
                    className="px-4 py-2.5 rounded-xl bg-slate-900 text-white hover:bg-indigo-600 disabled:bg-slate-200 disabled:text-slate-400 font-bold text-xs flex items-center gap-1.5 transition-colors shadow-lg shadow-slate-100"
                    title={provider.invoiceCount === 0 ? "Sube al menos una factura de este proveedor antes para usarla de muestra" : "Estudiar formato"}
                  >
                    <Sparkles className="w-3.5 h-3.5" />
                    {hasConfig ? 'Volver a Estudiar' : 'Estudiar Formato'}
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {providers.length === 0 && !loading && (
        <div className="bg-white rounded-[2rem] p-12 text-center border border-slate-100">
          <Building2 className="w-16 h-16 text-slate-200 mx-auto mb-4" />
          <p className="text-slate-400 font-bold">No hay proveedores en la base de datos.</p>
          <p className="text-slate-400 text-xs mt-1">Sube una factura en "Auditar Factura" para que se registren automáticamente.</p>
        </div>
      )}

      {/* ============================================================ */}
      {/* MODAL DE ESTUDIO DE FORMATO */}
      {/* ============================================================ */}
      {showStudyModal && selectedProvider && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[80] flex items-center justify-center p-4">
          <div className="bg-white rounded-[3rem] shadow-2xl max-w-lg w-full p-10 animate-in zoom-in duration-200">
            <div className="w-20 h-20 bg-indigo-50 rounded-3xl flex items-center justify-center mx-auto mb-6 text-indigo-600">
              <Sparkles className="w-10 h-10" />
            </div>
            
            <h3 className="text-2xl font-black text-slate-800 text-center mb-2">Estudiar Formato de Facturas</h3>
            <p className="text-slate-500 text-center text-sm mb-6 leading-relaxed">
              Gemini analizará un documento de muestra de <strong>{selectedProvider.name}</strong> para aprender a extraer columnas, precios y códigos.
            </p>

            {isStudying ? (
              <div className="space-y-4 py-6 text-center">
                <Loader2 className="w-10 h-10 animate-spin text-indigo-600 mx-auto" />
                <p className="text-slate-800 font-black text-sm">{studyStepMsg}</p>
                <p className="text-slate-400 text-xs font-semibold">Esto tardará unos 15 segundos y no consume tokens recurrentes.</p>
              </div>
            ) : (
              <div className="space-y-6">
                {referenceAudits.length > 0 ? (
                  <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Factura de muestra de referencia</label>
                    <select
                      value={selectedAuditId}
                      onChange={(e) => setSelectedAuditId(e.target.value)}
                      className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-bold bg-white"
                    >
                      {referenceAudits.map(a => (
                        <option key={a.id} value={a.id}>
                          FAC: {a.invoiceNumber} ({a.invoiceDate}) - {a.totalInvoice.toFixed(2)}€
                        </option>
                      ))}
                    </select>
                  </div>
                ) : (
                  <div className="bg-amber-50 text-amber-800 rounded-2xl p-4 border border-amber-100 text-xs font-semibold leading-relaxed">
                    No hay facturas previas en el historial para este proveedor que sirvan de referencia. Sube primero una factura de este proveedor.
                  </div>
                )}

                <div className="flex gap-3 pt-4">
                  <button
                    onClick={() => { setShowStudyModal(false); setSelectedProvider(null); }}
                    className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors"
                  >
                    CANCELAR
                  </button>
                  <button
                    onClick={executeStudy}
                    disabled={!selectedAuditId}
                    className="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 disabled:bg-slate-100 disabled:text-slate-400 transition-all shadow-xl shadow-indigo-100 flex items-center justify-center gap-2"
                  >
                    <Play className="w-4 h-4 fill-current" /> COMENZAR ESTUDIO
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ============================================================ */}
      {/* MODAL DE EDICIÓN MANUAL */}
      {/* ============================================================ */}
      {showEditModal && selectedProvider && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[80] flex items-center justify-center p-4">
          <div className="bg-white rounded-[3rem] shadow-2xl max-w-xl w-full p-10 animate-in zoom-in duration-200">
            <h3 className="text-2xl font-black text-slate-800 mb-2">Editar Reglas de Proveedor</h3>
            <p className="text-slate-400 text-sm mb-6 font-medium">Refina de forma manual lo aprendido por Gemini para el proveedor <strong>{selectedProvider.name}</strong>.</p>

            <div className="space-y-5">
              <div>
                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Descripción del Sistema (En Español)</label>
                <textarea
                  value={tempDescription}
                  onChange={(e) => setTempDescription(e.target.value)}
                  className="w-full h-24 px-4 py-3 rounded-2xl border-2 border-slate-100 outline-none focus:border-indigo-500 font-semibold text-xs text-slate-700 leading-relaxed resize-none"
                  placeholder="Ej: Las facturas constan de artículos ópticos. Las descripciones contienen el SKU en corchetes al principio [XYZ] y al final incorporan el nombre de paciente y número de albarán."
                />
              </div>

              <div>
                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Instrucciones Técnicas para Gemini (En Inglés)</label>
                <textarea
                  value={tempRules}
                  onChange={(e) => setTempRules(e.target.value)}
                  className="w-full h-28 px-4 py-3 rounded-2xl border-2 border-slate-100 outline-none focus:border-indigo-500 font-mono font-bold text-xs text-indigo-950 leading-relaxed resize-none"
                  placeholder="Ej: Descriptions start with SKU code in brackets, e.g. [SKU001]. Extract SKU value without brackets as 'sku'. Patient names are listed at the end of description after a space, remove them from 'baseProductName'."
                />
                <p className="text-[10px] text-slate-400 font-medium mt-1">Estas instrucciones se inyectan en el System Prompt de Gemini cuando procesa facturas de este proveedor.</p>
              </div>
            </div>

            <div className="flex gap-3 pt-8">
              <button
                onClick={() => { setShowEditModal(false); setSelectedProvider(null); }}
                disabled={isSaving}
                className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors"
              >
                CANCELAR
              </button>
              <button
                onClick={executeSaveEdit}
                disabled={isSaving}
                className="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-2"
              >
                {isSaving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
                GUARDAR CAMBIOS
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProvidersPage;
