import React, { useEffect, useState, useMemo } from 'react';
import { db } from '../db';
import { Provider, AuditRecord } from '../types';
import {
  Calendar,
  Building2,
  CheckCircle,
  AlertTriangle,
  Clock,
  Euro,
  FileText,
  Loader2,
  RefreshCw,
  X,
  FileCheck,
  ChevronDown,
  ChevronUp,
  ExternalLink
} from 'lucide-react';

const currency = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });
const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const fullMonthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

interface CellModalData {
  providerName: string;
  monthName: string;
  year: number;
  audits: AuditRecord[];
}

const MonthlyControlPage: React.FC = () => {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [audits, setAudits] = useState<AuditRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedYear, setSelectedYear] = useState<number>(new Date().getFullYear());
  
  // Cell details modal
  const [modalData, setModalData] = useState<CellModalData | null>(null);
  const [expandedAuditId, setExpandedAuditId] = useState<string | null>(null);

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
      setError(err instanceof Error ? err.message : 'Error al cargar los datos');
    } finally {
      setLoading(false);
    }
  };

  // Extraer todos los años en los que hay facturas registradas
  const availableYears = useMemo(() => {
    const years = new Set<number>([new Date().getFullYear()]);
    audits.forEach(audit => {
      if (audit.invoiceDate) {
        const year = new Date(audit.invoiceDate).getFullYear();
        if (!isNaN(year)) {
          years.add(year);
        }
      }
    });
    return Array.from(years).sort((a, b) => b - a);
  }, [audits]);

  // Agrupar proveedores por importancia
  const groupedProviders = useMemo(() => {
    const principal = providers.filter(p => p.importance === 'principal');
    const puntual = providers.filter(p => p.importance !== 'principal');
    return { principal, puntual };
  }, [providers]);

  // Filtrar facturas por el año seleccionado
  const auditsInSelectedYear = useMemo(() => {
    return audits.filter(audit => {
      if (!audit.invoiceDate) return false;
      const year = new Date(audit.invoiceDate).getFullYear();
      return year === selectedYear;
    });
  }, [audits, selectedYear]);

  // Obtener facturas en una celda específica (Proveedor + Mes)
  const getCellAudits = (provider: Provider, monthIdx: number) => {
    return auditsInSelectedYear.filter(audit => {
      // 1. Validar mes (0-11)
      if (!audit.invoiceDate) return false;
      const dateObj = new Date(audit.invoiceDate);
      if (dateObj.getMonth() !== monthIdx) return false;

      // 2. Cruzar por ID de proveedor o por nombre (insensible a mayúsculas y espacios)
      if (provider.pedidosProviderId && audit.pedidosProviderId) {
        return Number(provider.pedidosProviderId) === Number(audit.pedidosProviderId);
      }
      return audit.provider.trim().toLowerCase() === provider.name.trim().toLowerCase();
    });
  };

  // Estadísticas del año seleccionado
  const stats = useMemo(() => {
    let expectedInvoicesTotal = 0;
    let uploadedPrincipalCount = 0;
    let totalInvoicesYearCount = auditsInSelectedYear.length;
    let totalVolume = 0;

    // Calcular facturas esperadas y subidas para principales
    groupedProviders.principal.forEach(prov => {
      const expectedPerMonth = prov.expectedMonthlyInvoices || 1;
      expectedInvoicesTotal += expectedPerMonth * 12;

      for (let m = 0; m < 12; m++) {
        const cellAudits = getCellAudits(prov, m);
        // Contar como máximo el número esperado (no sumar de más si sube extras)
        uploadedPrincipalCount += Math.min(cellAudits.length, expectedPerMonth);
      }
    });

    auditsInSelectedYear.forEach(audit => {
      totalVolume += audit.totalInvoice || 0;
    });

    const coveragePercent = expectedInvoicesTotal > 0 
      ? Math.round((uploadedPrincipalCount / expectedInvoicesTotal) * 100)
      : 100;

    return {
      expectedInvoicesTotal,
      uploadedPrincipalCount,
      totalInvoicesYearCount,
      totalVolume,
      coveragePercent
    };
  }, [groupedProviders, auditsInSelectedYear]);

  const toggleAuditExpander = (auditId: string) => {
    setExpandedAuditId(expandedAuditId === auditId ? null : auditId);
  };

  return (
    <div className="space-y-8 pb-20 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
            <Calendar className="w-8 h-8 text-indigo-600" />
            Control Mensual de Facturas
          </h2>
          <p className="text-slate-500 font-medium">Revisa las entregas mensuales de facturas según la importancia del proveedor.</p>
        </div>
        <div className="flex items-center gap-3">
          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(Number(e.target.value))}
            disabled={loading}
            className="rounded-2xl border-2 border-slate-100 bg-white px-5 py-3 text-sm font-black text-slate-700 outline-none focus:border-indigo-500 transition-all shadow-sm"
          >
            {availableYears.map(year => (
              <option key={year} value={year}>Año {year}</option>
            ))}
          </select>

          <button
            onClick={loadData}
            disabled={loading}
            className="flex items-center gap-2 px-5 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-60 transition-all shadow-sm"
          >
            {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
            Actualizar
          </button>
        </div>
      </div>

      {error && (
        <div className="rounded-[2rem] p-6 border border-rose-100 bg-rose-50 text-rose-700 flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 mt-0.5 shrink-0" />
          <p className="font-bold text-sm leading-relaxed">{error}</p>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-indigo-50 text-indigo-600">
            <FileText className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Subidas ({selectedYear})</p>
            <p className="text-2xl font-black text-slate-800">{stats.totalInvoicesYearCount}</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-rose-50 text-rose-600">
            <FileCheck className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Principales Entregadas</p>
            <p className="text-2xl font-black text-slate-800">{stats.uploadedPrincipalCount} <span className="text-sm text-slate-400 font-bold">/ {stats.expectedInvoicesTotal}</span></p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
          <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${stats.coveragePercent >= 90 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}>
            <CheckCircle className="w-7 h-7" />
          </div>
          <div>
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Cobertura Obligatoria</p>
            <p className="text-2xl font-black text-slate-800">{stats.coveragePercent}%</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-slate-900 text-white">
            <Euro className="w-7 h-7" />
          </div>
          <div className="min-w-0">
            <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Importe Acumulado</p>
            <p className="text-xl font-black text-slate-800 truncate">{currency.format(stats.totalVolume)}</p>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="h-96 flex items-center justify-center text-slate-400 gap-3">
          <Loader2 className="w-8 h-8 animate-spin text-indigo-600" />
          <span className="font-bold">Procesando cuadrícula de facturas...</span>
        </div>
      ) : (
        <div className="space-y-8">
          {/* TABLA PRINCIPALES */}
          <div className="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div className="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <div>
                <h3 className="text-lg font-black text-slate-800">Proveedores Principales</h3>
                <p className="text-xs font-semibold text-slate-400">Facturación regular obligatoria (1 o 2 facturas mensuales previstas).</p>
              </div>
              <span className="px-3 py-1 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-100">
                Regular Mensual
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100">
                    <th className="p-5 text-xs font-black text-slate-400 uppercase tracking-widest w-64 bg-slate-50/20">Proveedor</th>
                    {monthNames.map(m => (
                      <th key={m} className="p-3 text-center text-xs font-black text-slate-400 uppercase tracking-widest">{m}</th>
                    ))}
                    <th className="p-5 text-center text-xs font-black text-slate-500 uppercase tracking-widest w-32 bg-slate-50/20">Total Año</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {groupedProviders.principal.map(prov => {
                    const expectedPerMonth = prov.expectedMonthlyInvoices || 1;
                    let yearlyTotalCount = 0;
                    return (
                      <tr key={prov.name} className="hover:bg-slate-50/30 transition-colors">
                        <td className="p-5 font-black text-slate-800 text-sm bg-slate-50/10">
                          {prov.name}
                          <span className="block text-[9px] font-black text-rose-500 uppercase mt-0.5 tracking-wider">
                            Previsto: {expectedPerMonth} / mes
                          </span>
                        </td>
                        {monthNames.map((_, mIdx) => {
                          const cellAudits = getCellAudits(prov, mIdx);
                          const count = cellAudits.length;
                          yearlyTotalCount += count;
                          
                          // Determinar colores según cumplimiento
                          let pillClass = '';
                          if (count >= expectedPerMonth) {
                            pillClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200/80 hover:bg-emerald-100';
                          } else if (count > 0) {
                            pillClass = 'bg-amber-50 text-amber-700 border border-amber-200/80 hover:bg-amber-100';
                          } else {
                            pillClass = 'bg-slate-50 text-slate-300 border border-slate-100 hover:bg-slate-100/50 hover:text-slate-400';
                          }

                          return (
                            <td key={mIdx} className="p-3 text-center">
                              <button
                                onClick={() => count > 0 && setModalData({ providerName: prov.name, monthName: fullMonthNames[mIdx], year: selectedYear, audits: cellAudits })}
                                disabled={count === 0}
                                className={`w-12 py-1.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center mx-auto ${pillClass}`}
                              >
                                {count}/{expectedPerMonth}
                              </button>
                            </td>
                          );
                        })}
                        <td className="p-5 text-center font-black text-sm bg-slate-50/10 text-slate-700">
                          {yearlyTotalCount}
                        </td>
                      </tr>
                    );
                  })}
                  {groupedProviders.principal.length === 0 && (
                    <tr>
                      <td colSpan={14} className="p-8 text-center text-slate-400 font-semibold text-sm">
                        No hay proveedores clasificados como Principales en la base de datos.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* TABLA PUNTUALES */}
          <div className="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
            <div className="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <div>
                <h3 className="text-lg font-black text-slate-800">Proveedores Puntuales</h3>
                <p className="text-xs font-semibold text-slate-400">Facturación esporádica (marcas de monturas o servicios puntuales, sin objetivo fijo).</p>
              </div>
              <span className="px-3 py-1 rounded-full text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                Puntuales / Ocasional
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-100">
                    <th className="p-5 text-xs font-black text-slate-400 uppercase tracking-widest w-64 bg-slate-50/20">Proveedor</th>
                    {monthNames.map(m => (
                      <th key={m} className="p-3 text-center text-xs font-black text-slate-400 uppercase tracking-widest">{m}</th>
                    ))}
                    <th className="p-5 text-center text-xs font-black text-slate-500 uppercase tracking-widest w-32 bg-slate-50/20">Total Año</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {groupedProviders.puntual.map(prov => {
                    let yearlyTotalCount = 0;
                    return (
                      <tr key={prov.name} className="hover:bg-slate-50/30 transition-colors">
                        <td className="p-5 font-black text-slate-800 text-sm bg-slate-50/10">
                          {prov.name}
                        </td>
                        {monthNames.map((_, mIdx) => {
                          const cellAudits = getCellAudits(prov, mIdx);
                          const count = cellAudits.length;
                          yearlyTotalCount += count;
                          
                          let pillClass = '';
                          if (count > 0) {
                            pillClass = 'bg-indigo-50 text-indigo-700 border border-indigo-200/50 hover:bg-indigo-100';
                          } else {
                            pillClass = 'text-slate-300 font-normal hover:bg-slate-50/50';
                          }

                          return (
                            <td key={mIdx} className="p-3 text-center">
                              {count > 0 ? (
                                <button
                                  onClick={() => setModalData({ providerName: prov.name, monthName: fullMonthNames[mIdx], year: selectedYear, audits: cellAudits })}
                                  className={`w-10 py-1.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center mx-auto ${pillClass}`}
                                >
                                  {count}
                                </button>
                              ) : (
                                <span className={`text-xs block ${pillClass}`}>-</span>
                              )}
                            </td>
                          );
                        })}
                        <td className="p-5 text-center font-black text-sm bg-slate-50/10 text-slate-700">
                          {yearlyTotalCount}
                        </td>
                      </tr>
                    );
                  })}
                  {groupedProviders.puntual.length === 0 && (
                    <tr>
                      <td colSpan={14} className="p-8 text-center text-slate-400 font-semibold text-sm">
                        No hay proveedores clasificados como Puntuales.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ============================================================ */}
      {/* MODAL DE DETALLE DE CELDA */}
      {/* ============================================================ */}
      {modalData && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[80] flex items-center justify-center p-4">
          <div className="bg-white rounded-[3rem] shadow-2xl max-w-2xl w-full p-8 animate-in zoom-in duration-200 max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
              <div>
                <h3 className="text-xl font-black text-slate-800">Facturas Subidas</h3>
                <p className="text-slate-500 font-semibold text-xs mt-0.5">
                  {modalData.providerName} — {modalData.monthName} de {modalData.year}
                </p>
              </div>
              <button
                onClick={() => { setModalData(null); setExpandedAuditId(null); }}
                className="w-10 h-10 rounded-full bg-slate-50 hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Listado de Facturas */}
            <div className="flex-1 overflow-y-auto py-6 space-y-4 pr-1">
              {modalData.audits.map(audit => {
                const isExpanded = expandedAuditId === audit.id;
                return (
                  <div key={audit.id} className="border border-slate-100 rounded-3xl bg-slate-50/30 overflow-hidden transition-all">
                    {/* Fila Cabecera Factura */}
                    <div 
                      onClick={() => toggleAuditExpander(audit.id)}
                      className="p-5 flex items-center justify-between gap-4 cursor-pointer hover:bg-slate-50 transition-colors select-none"
                    >
                      <div className="min-w-0">
                        <p className="font-black text-slate-800 text-sm">FAC: {audit.invoiceNumber || 'S/N'}</p>
                        <p className="text-[10px] text-slate-400 font-bold uppercase mt-0.5">Fecha: {audit.invoiceDate}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="font-black text-slate-900 text-sm">{currency.format(audit.totalInvoice)}</span>
                        {isExpanded ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
                      </div>
                    </div>

                    {/* Desplegable de líneas */}
                    {isExpanded && (
                      <div className="px-5 pb-5 pt-2 border-t border-slate-100/50 bg-white/70 space-y-4">
                        <div>
                          <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2.5">Líneas de la Factura</p>
                          <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                            {audit.lines.map((line, lIdx) => (
                              <div key={line.id || lIdx} className="flex justify-between items-start gap-4 p-2.5 rounded-xl bg-slate-50/50 border border-slate-100 text-xs font-semibold">
                                <div className="min-w-0">
                                  <p className="text-slate-800 font-bold truncate">{line.invoiceDescription}</p>
                                  <p className="text-[10px] text-slate-400 font-bold uppercase mt-0.5">
                                    Cant: {line.quantity} · P. Unitario Neto: {currency.format(line.invoiceUnitPrice)}
                                  </p>
                                </div>
                                <span className="font-bold text-slate-700 whitespace-nowrap">
                                  {currency.format(line.invoiceLineTotal || (line.invoiceUnitPrice * line.quantity))}
                                </span>
                              </div>
                            ))}
                          </div>
                        </div>

                        <div className="flex items-center justify-between pt-2 border-t border-slate-100/50 text-[10px] font-black text-slate-400">
                          <span>Estado: <span className="uppercase text-indigo-600">{audit.globalStatus}</span></span>
                          {audit.pdfPath && (
                            <a
                              href={`../${audit.pdfPath}`}
                              target="_blank"
                              rel="noreferrer"
                              className="flex items-center gap-1 text-indigo-600 hover:text-indigo-800 transition-colors"
                            >
                              <ExternalLink className="w-3.5 h-3.5" /> VER PDF ORIGINAL
                            </a>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

            <div className="pt-4 border-t border-slate-100 shrink-0">
              <button
                onClick={() => { setModalData(null); setExpandedAuditId(null); }}
                className="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white rounded-2xl font-black text-sm transition-colors shadow-xl shadow-slate-100"
              >
                CERRAR
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MonthlyControlPage;
