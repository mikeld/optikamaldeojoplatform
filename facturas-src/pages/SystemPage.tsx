import React, { useEffect, useMemo, useState } from 'react';
import { db } from '../db';
import { SchemaStatus } from '../types';
import { AlertTriangle, CheckCircle, Database, KeyRound, Loader2, RefreshCw, ServerCog, XCircle } from 'lucide-react';

const SystemPage: React.FC = () => {
  const [schemaStatus, setSchemaStatus] = useState<SchemaStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const geminiConfigured = Boolean((import.meta as any).env?.VITE_API_KEY || (process.env as any).API_KEY);

  useEffect(() => {
    loadStatus();
  }, []);

  const loadStatus = async () => {
    setLoading(true);
    setError('');
    try {
      const status = await db.getSchemaStatus();
      setSchemaStatus(status);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se ha podido comprobar el sistema');
    } finally {
      setLoading(false);
    }
  };

  const missingCount = useMemo(() => {
    if (!schemaStatus) return 0;
    return schemaStatus.missingTables.length + Object.values(schemaStatus.missingColumns).reduce((sum, cols) => sum + cols.length, 0);
  }, [schemaStatus]);

  return (
    <div className="space-y-8 pb-20">
      <div className="flex items-center justify-between gap-6">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
            <ServerCog className="w-8 h-8 text-indigo-600" />
            Sistema
          </h2>
          <p className="text-slate-500 font-medium">Estado técnico de Facturas Check, base de datos e IA.</p>
        </div>
        <button
          onClick={loadStatus}
          disabled={loading}
          className="flex items-center gap-2 px-5 py-3 rounded-2xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-60 transition-all"
        >
          {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
          Actualizar
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <StatusCard
          title="Base de datos"
          value={schemaStatus?.database || 'Comprobando'}
          detail={schemaStatus?.checkedAt ? new Date(schemaStatus.checkedAt).toLocaleString('es-ES') : 'Estado de MySQL'}
          tone={schemaStatus?.ok ? 'success' : schemaStatus ? 'danger' : 'warning'}
          icon={<Database />}
        />
        <StatusCard
          title="Esquema"
          value={schemaStatus?.ok ? 'Correcto' : schemaStatus ? 'Revisar' : 'Pendiente'}
          detail={schemaStatus ? `${missingCount} faltas detectadas` : 'Tablas y columnas'}
          tone={schemaStatus?.ok ? 'success' : schemaStatus ? 'danger' : 'warning'}
          icon={schemaStatus?.ok ? <CheckCircle /> : <AlertTriangle />}
        />
        <StatusCard
          title="Gemini"
          value={geminiConfigured ? 'Configurado' : 'Pendiente'}
          detail="Extractor y asistente IA"
          tone={geminiConfigured ? 'success' : 'warning'}
          icon={<KeyRound />}
        />
      </div>

      {error && (
        <div className="rounded-[2rem] p-6 border border-rose-100 bg-rose-50 text-rose-700">
          <div className="flex items-start gap-3">
            <XCircle className="w-5 h-5 mt-0.5" />
            <p className="font-bold text-sm leading-relaxed">{error}</p>
          </div>
        </div>
      )}

      <section className="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm">
        <div className="flex items-start justify-between gap-4 mb-6">
          <div>
            <h3 className="text-xl font-black text-slate-800 flex items-center gap-2">
              <Database className="w-5 h-5 text-indigo-500" />
              Esquema Facturas Check
            </h3>
            <p className="text-sm text-slate-500 mt-1">Archivo esperado: {schemaStatus?.schemaFile || 'pedidos/sql/facturas_schema.sql'}</p>
          </div>
          {schemaStatus?.ok && (
            <span className="px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-700">OK</span>
          )}
        </div>

        {loading && (
          <div className="h-48 flex items-center justify-center text-slate-400 gap-3">
            <Loader2 className="w-6 h-6 animate-spin" />
            <span className="font-bold">Comprobando esquema...</span>
          </div>
        )}

        {!loading && schemaStatus && (
          <div className="space-y-3">
            {Object.entries(schemaStatus.tables).map(([table, status]) => (
              <div key={table} className={`rounded-2xl border p-4 ${status.exists && status.missingColumns.length === 0 ? 'border-emerald-100 bg-emerald-50/40' : 'border-rose-100 bg-rose-50/40'}`}>
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    {status.exists && status.missingColumns.length === 0 ? (
                      <CheckCircle className="w-5 h-5 text-emerald-600" />
                    ) : (
                      <AlertTriangle className="w-5 h-5 text-rose-600" />
                    )}
                    <span className="font-black text-slate-800">{table}</span>
                  </div>
                  <span className={`px-2.5 py-1 rounded-lg text-xs font-black ${status.exists ? 'bg-white text-slate-600 border border-slate-100' : 'bg-rose-100 text-rose-700'}`}>
                    {status.exists ? 'Existe' : 'Falta tabla'}
                  </span>
                </div>
                {status.missingColumns.length > 0 && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {status.missingColumns.map(column => (
                      <span key={column} className="px-2.5 py-1 rounded-lg bg-white border border-rose-100 text-rose-700 text-xs font-bold">
                        {column}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
};

const StatusCard: React.FC<{
  title: string;
  value: string;
  detail: string;
  tone: 'success' | 'warning' | 'danger';
  icon: React.ReactElement<any>;
}> = ({ title, value, detail, tone, icon }) => {
  const styles = {
    success: 'bg-emerald-50 text-emerald-600 border-emerald-100',
    warning: 'bg-amber-50 text-amber-600 border-amber-100',
    danger: 'bg-rose-50 text-rose-600 border-rose-100'
  };

  return (
    <div className={`bg-white p-6 rounded-[2rem] shadow-sm border flex items-center gap-5 ${styles[tone]}`}>
      <div className="w-14 h-14 rounded-2xl flex items-center justify-center bg-white/70">
        {React.cloneElement(icon, { className: 'w-7 h-7' })}
      </div>
      <div className="min-w-0">
        <p className="text-[10px] font-black uppercase tracking-widest opacity-70">{title}</p>
        <p className="text-xl font-black tracking-tight truncate">{value}</p>
        <p className="text-xs font-bold opacity-70 truncate">{detail}</p>
      </div>
    </div>
  );
};

export default SystemPage;
