import React, { useEffect, useMemo, useState } from 'react';
import { askInvoiceAssistant } from '../services/geminiService';
import { db } from '../db';
import { AssistantContext } from '../types';
import { AlertTriangle, Bot, Clock, FileText, History, Loader2, Send, Sparkles, TrendingUp } from 'lucide-react';

const suggestedQuestions = [
  '¿Qué facturas tienen alertas críticas pendientes?',
  '¿Qué proveedores aparecen con más cambios de precio?',
  'Resume las últimas facturas auditadas.',
  '¿Qué productos han cambiado de precio recientemente?'
];

const AssistantPage: React.FC = () => {
  const [context, setContext] = useState<AssistantContext | null>(null);
  const [question, setQuestion] = useState(suggestedQuestions[0]);
  const [answer, setAnswer] = useState('');
  const [loadingContext, setLoadingContext] = useState(true);
  const [asking, setAsking] = useState(false);
  const [error, setError] = useState('');
  const [retrievalTerms, setRetrievalTerms] = useState<string[]>([]);

  useEffect(() => {
    loadContext();
  }, []);

  const loadContext = async () => {
    setLoadingContext(true);
    setError('');
    try {
      const data = await db.getAssistantContext();
      setContext(data);
      setRetrievalTerms([]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se ha podido cargar el contexto del asistente');
    } finally {
      setLoadingContext(false);
    }
  };

  const stats = useMemo(() => {
    if (!context) {
      return {
        audits: 0,
        pendingAlerts: 0,
        criticalAlerts: 0,
        priceChanges: 0
      };
    }

    return {
      audits: context.audits.length,
      pendingAlerts: context.pendingAlerts.length,
      criticalAlerts: context.pendingAlerts.filter(alert => alert.severity === 'critical').length,
      priceChanges: context.priceHistory.length
    };
  }, [context]);

  const handleAsk = async () => {
    const trimmedQuestion = question.trim();
    if (!trimmedQuestion || !context || asking) return;

    setAsking(true);
    setAnswer('');
    setError('');

    try {
      const focusedContext = await db.searchAssistantContext(trimmedQuestion);
      setContext(focusedContext);
      setRetrievalTerms(focusedContext.retrieval?.terms || []);
      const response = await askInvoiceAssistant(trimmedQuestion, focusedContext);
      setAnswer(response);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'El asistente no ha podido responder ahora mismo');
    } finally {
      setAsking(false);
    }
  };

  return (
    <div className="space-y-8 pb-20">
      <div className="flex items-center justify-between gap-6">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
            <Bot className="w-8 h-8 text-indigo-600" />
            Asistente IA
          </h2>
          <p className="text-slate-500 font-medium">Consulta facturas, alertas e historial de precios con recuperación de contexto.</p>
        </div>
        <button
          onClick={loadContext}
          disabled={loadingContext}
          className="flex items-center gap-2 px-5 py-3 rounded-2xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 disabled:opacity-60 transition-all"
        >
          {loadingContext ? <Loader2 className="w-4 h-4 animate-spin" /> : <Clock className="w-4 h-4" />}
          Actualizar contexto
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <StatCard title="Facturas en contexto" value={stats.audits} icon={<FileText />} color="bg-indigo-50 text-indigo-600" />
        <StatCard title="Alertas pendientes" value={stats.pendingAlerts} icon={<AlertTriangle />} color="bg-amber-50 text-amber-600" />
        <StatCard title="Críticas pendientes" value={stats.criticalAlerts} icon={<Sparkles />} color="bg-rose-50 text-rose-600" />
        <StatCard title="Cambios de precio" value={stats.priceChanges} icon={<TrendingUp />} color="bg-emerald-50 text-emerald-600" />
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-8">
        <section className="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm">
          <div className="space-y-4">
            <label className="block text-xs font-black text-slate-400 uppercase tracking-widest">
              Pregunta
            </label>
            <textarea
              value={question}
              onChange={(event) => setQuestion(event.target.value)}
              rows={4}
              className="w-full rounded-2xl border border-slate-200 p-4 text-slate-800 font-medium outline-none focus:border-indigo-300 focus:ring-4 focus:ring-indigo-50 transition-all resize-none"
              placeholder="Pregunta por proveedores, facturas, alertas o cambios de precio..."
            />

            <div className="flex flex-wrap gap-2">
              {suggestedQuestions.map(item => (
                <button
                  key={item}
                  onClick={() => setQuestion(item)}
                  className="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                >
                  {item}
                </button>
              ))}
            </div>

            <button
              onClick={handleAsk}
              disabled={loadingContext || asking || !context || question.trim().length === 0}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-indigo-600 text-white font-black hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-indigo-100"
            >
              {asking ? <Loader2 className="w-5 h-5 animate-spin" /> : <Send className="w-5 h-5" />}
              Preguntar
            </button>
          </div>

          <div className="mt-8 min-h-80 rounded-2xl bg-slate-50 border border-slate-100 p-6">
            {loadingContext && (
              <div className="h-64 flex flex-col items-center justify-center text-slate-400 gap-3">
                <Loader2 className="w-8 h-8 animate-spin" />
                <p className="font-bold">Cargando contexto de facturas...</p>
              </div>
            )}

            {!loadingContext && !answer && !asking && !error && (
              <div className="h-64 flex flex-col items-center justify-center text-center text-slate-400 gap-3">
                <Bot className="w-12 h-12 text-slate-300" />
                <p className="max-w-md font-bold">El asistente buscará primero las facturas, alertas y cambios de precio más relevantes para tu pregunta.</p>
              </div>
            )}

            {asking && (
              <div className="h-64 flex flex-col items-center justify-center text-indigo-500 gap-3">
                <Loader2 className="w-8 h-8 animate-spin" />
                <p className="font-black">Buscando contexto relevante...</p>
              </div>
            )}

            {answer && (
              <div className="prose prose-slate max-w-none">
                <p className="whitespace-pre-line text-slate-700 leading-relaxed font-medium">{answer}</p>
              </div>
            )}
          </div>
        </section>

        <aside className="space-y-6">
          <div className="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm">
            <h3 className="text-lg font-black text-slate-800 mb-4 flex items-center gap-2">
              <History className="w-5 h-5 text-indigo-500" />
              Contexto usado
            </h3>
            <div className="space-y-3 text-sm">
              <ContextRow label="Generado" value={context?.generatedAt ? new Date(context.generatedAt).toLocaleString('es-ES') : 'Pendiente'} />
              <ContextRow label="Facturas" value={`${stats.audits} últimas`} />
              <ContextRow label="Alertas" value={`${stats.pendingAlerts} recuperadas`} />
              <ContextRow label="Precios" value={`${stats.priceChanges} movimientos`} />
              <ContextRow label="Modo" value={context?.retrieval?.mode === 'keyword_rag' ? 'RAG por términos' : 'Resumen inicial'} />
            </div>
            {retrievalTerms.length > 0 && (
              <div className="mt-5 flex flex-wrap gap-2">
                {retrievalTerms.map(term => (
                  <span key={term} className="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-black">
                    {term}
                  </span>
                ))}
              </div>
            )}
          </div>

          {error && (
            <div className="rounded-[2rem] p-6 border border-rose-100 bg-rose-50 text-rose-700">
              <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 mt-0.5" />
                <p className="font-bold text-sm leading-relaxed">{error}</p>
              </div>
            </div>
          )}
        </aside>
      </div>
    </div>
  );
};

const StatCard: React.FC<{ title: string; value: number; icon: React.ReactElement<any>; color: string }> = ({ title, value, icon, color }) => (
  <div className="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
    <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${color}`}>
      {React.cloneElement(icon, { className: 'w-7 h-7' })}
    </div>
    <div>
      <p className="text-[10px] text-slate-400 font-black uppercase tracking-widest">{title}</p>
      <p className="text-2xl font-black text-slate-800 tracking-tight">{value}</p>
    </div>
  </div>
);

const ContextRow: React.FC<{ label: string; value: string }> = ({ label, value }) => (
  <div className="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0 last:pb-0">
    <span className="font-bold text-slate-400">{label}</span>
    <span className="font-black text-slate-700 text-right">{value}</span>
  </div>
);

export default AssistantPage;
