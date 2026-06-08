
import React, { useEffect, useMemo, useState } from 'react';
import { db } from '../db';
import { Alert, AuditRecord, PriceHistory } from '../types';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { AlertTriangle, CheckCircle, Clock, Euro, FileText, Loader2, PackageSearch, TrendingUp, Activity } from 'lucide-react';

const currency = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });
const shortDate = new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short' });

const DashboardPage: React.FC = () => {
  const [audits, setAudits] = useState<AuditRecord[]>([]);
  const [pendingAlerts, setPendingAlerts] = useState<Alert[]>([]);
  const [priceHistory, setPriceHistory] = useState<PriceHistory[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadDashboard = async () => {
      setIsLoading(true);
      const [auditData, alertData, historyData] = await Promise.all([
        db.getAudits(),
        db.getAlerts(undefined, 'pending'),
        db.getPriceHistory()
      ]);
      setAudits(auditData);
      setPendingAlerts(alertData);
      setPriceHistory(historyData);
      setIsLoading(false);
    };
    loadDashboard();
  }, []);

  const actionableData = useMemo(() => {
    const pendingReviews = audits.filter(a => a.globalStatus === 'pending' || a.globalStatus === 'in_review');
    const criticalAlerts = pendingAlerts.filter(a => a.severity === 'critical');
    const unknownProducts = pendingAlerts.filter(a => a.alertType === 'unknown_product');

    const providerImpact = new Map<string, { provider: string; amount: number; alerts: number }>();
    audits.forEach(audit => {
      audit.lines.forEach(line => {
        if (line.difference <= 0) return;
        const current = providerImpact.get(audit.provider) || { provider: audit.provider, amount: 0, alerts: 0 };
        current.amount += line.difference * line.quantity;
        current.alerts += 1;
        providerImpact.set(audit.provider, current);
      });
    });

    pendingAlerts.forEach(alert => {
      if (!alert.difference || alert.difference <= 0) return;
      const audit = audits.find(a => a.id === alert.auditId);
      const provider = audit?.provider || 'Proveedor sin identificar';
      const current = providerImpact.get(provider) || { provider, amount: 0, alerts: 0 };
      current.amount += alert.difference;
      current.alerts += 1;
      providerImpact.set(provider, current);
    });

    const providers = Array.from(providerImpact.values())
      .sort((a, b) => b.amount - a.amount)
      .slice(0, 6)
      .map(item => ({
        name: item.provider,
        value: Number(item.amount.toFixed(2)),
        alerts: item.alerts
      }));

    const topAlerts = [...pendingAlerts]
      .sort((a, b) => {
        const severityOrder = { critical: 0, warning: 1, info: 2 };
        return severityOrder[a.severity] - severityOrder[b.severity];
      })
      .slice(0, 5);

    return {
      pendingReviews,
      criticalAlerts,
      unknownProducts,
      providers,
      topAlerts,
      recentChanges: priceHistory.slice(0, 5)
    };
  }, [audits, pendingAlerts, priceHistory]);

  const stats = {
    total: audits.length,
    correct: audits.filter(a => a.globalStatus === 'approved').length,
    rejected: audits.filter(a => a.globalStatus === 'rejected').length,
    pending: actionableData.pendingReviews.length,
    totalVolume: audits.reduce((acc, a) => acc + a.totalInvoice, 0)
  };

  const chartData = [
    { name: 'OK', value: stats.correct, color: '#10B981' },
    { name: 'Rechazadas', value: stats.rejected, color: '#EF4444' },
    { name: 'Pendientes', value: stats.pending, color: '#F59E0B' },
  ];

  return (
    <div className="space-y-8 pb-20">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight">Control de Facturas</h2>
          <p className="text-slate-500 font-medium">Alertas, proveedores y cambios de precio pendientes de revisar.</p>
        </div>
        <div className="bg-white px-6 py-3 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3">
          {isLoading ? <Loader2 className="text-indigo-600 w-5 h-5 animate-spin" /> : <Activity className="text-indigo-600 w-5 h-5" />}
          <span className="font-black text-slate-800 text-sm">{isLoading ? 'CARGANDO' : 'SISTEMA ONLINE'}</span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <StatCard title="Facturas" value={stats.total} icon={<FileText />} bgColor="bg-indigo-50 text-indigo-600" />
        <StatCard title="Por Revisar" value={stats.pending} icon={<Clock />} bgColor="bg-amber-50 text-amber-600" />
        <StatCard title="Alertas Criticas" value={actionableData.criticalAlerts.length} icon={<AlertTriangle />} bgColor="bg-rose-50 text-rose-600" />
        <StatCard title="Volumen" value={currency.format(stats.totalVolume)} icon={<Euro />} bgColor="bg-slate-900 text-white" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
          <h3 className="text-xl font-black mb-8 text-slate-800 flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-indigo-500" /> Proveedores con más impacto
          </h3>
          <div className="h-72">
            {actionableData.providers.length === 0 ? (
              <EmptyState icon={<CheckCircle />} text="Sin subidas detectadas por proveedor." />
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={actionableData.providers}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                  <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fill: '#64748b', fontSize: 12, fontWeight: 700}} />
                  <YAxis axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12, fontWeight: 700}} />
                  <Tooltip cursor={{fill: '#f8fafc'}} formatter={(value: number) => currency.format(value)} contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1)'}} />
                  <Bar dataKey="value" radius={[8, 8, 8, 8]} barSize={48}>
                    {actionableData.providers.map((_, index) => <Cell key={index} fill={index === 0 ? '#e11d48' : '#4f46e5'} />)}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>

        <div className="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
          <h3 className="text-xl font-black mb-6 text-slate-800 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-rose-500" /> Alertas pendientes
          </h3>
          <div className="space-y-4">
            {actionableData.topAlerts.length === 0 ? (
              <EmptyState icon={<CheckCircle />} text="No hay alertas pendientes." />
            ) : (
              actionableData.topAlerts.map((alert) => (
                <div key={alert.id} className="flex items-center justify-between gap-4 p-4 rounded-2xl bg-rose-50/50 border border-rose-100">
                  <div className="max-w-[70%]">
                    <p className="font-bold text-slate-800 text-sm truncate">{alert.productName || alert.productSku || 'Producto sin identificar'}</p>
                    <p className="text-[10px] text-rose-500 font-black uppercase tracking-widest">{alertLabel(alert.alertType)}</p>
                  </div>
                  <SeverityBadge severity={alert.severity} />
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <Panel title="Estado de auditorias" icon={<Activity className="w-5 h-5 text-indigo-500" />}>
          <div className="h-60">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fill: '#64748b', fontSize: 12, fontWeight: 700}} />
                <YAxis axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12, fontWeight: 700}} />
                <Tooltip cursor={{fill: '#f8fafc'}} contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1)'}} />
                <Bar dataKey="value" radius={[8, 8, 8, 8]} barSize={48}>
                  {chartData.map((entry, index) => <Cell key={index} fill={entry.color} />)}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panel>

        <Panel title="Productos desconocidos" icon={<PackageSearch className="w-5 h-5 text-amber-500" />}>
          {actionableData.unknownProducts.length === 0 ? (
            <EmptyState icon={<CheckCircle />} text="No hay productos nuevos pendientes." />
          ) : (
            <div className="space-y-3">
              {actionableData.unknownProducts.slice(0, 5).map(alert => (
                <div key={alert.id} className="p-4 rounded-2xl bg-amber-50 border border-amber-100">
                  <p className="font-black text-sm text-slate-800 truncate">{alert.productName || 'Linea sin nombre'}</p>
                  <p className="text-[10px] font-black uppercase tracking-widest text-amber-600">Factura {alert.auditId}</p>
                </div>
              ))}
            </div>
          )}
        </Panel>

        <Panel title="Ultimos cambios de precio" icon={<TrendingUp className="w-5 h-5 text-emerald-500" />}>
          {actionableData.recentChanges.length === 0 ? (
            <EmptyState icon={<CheckCircle />} text="Aun no hay cambios de precio registrados." />
          ) : (
            <div className="space-y-3">
              {actionableData.recentChanges.map(change => (
                <div key={change.id} className="flex items-center justify-between gap-4 p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                  <div className="min-w-0">
                    <p className="font-black text-sm text-slate-800 truncate">{change.productName || change.sku}</p>
                    <p className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{formatDate(change.changeDate)} · {change.changedBy}</p>
                  </div>
                  <p className="font-black text-emerald-700 whitespace-nowrap">{currency.format(change.newPrice)}</p>
                </div>
              ))}
            </div>
          )}
        </Panel>
      </div>
    </div>
  );
};

const StatCard: React.FC<{ title: string; value: any; icon: React.ReactElement<any>; bgColor: string }> = ({ title, value, icon, bgColor }) => (
  <div className="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5 min-h-[112px]">
    <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${bgColor}`}>
      {React.cloneElement(icon, { className: 'w-7 h-7' })}
    </div>
    <div className="min-w-0">
      <p className="text-[10px] text-slate-400 font-black uppercase tracking-widest">{title}</p>
      <p className="text-2xl font-black text-slate-800 tracking-tight break-words">{value}</p>
    </div>
  </div>
);

const Panel: React.FC<{ title: string; icon: React.ReactNode; children: React.ReactNode }> = ({ title, icon, children }) => (
  <section className="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
    <h3 className="text-xl font-black mb-6 text-slate-800 flex items-center gap-2">
      {icon} {title}
    </h3>
    {children}
  </section>
);

const EmptyState: React.FC<{ icon: React.ReactElement<any>; text: string }> = ({ icon, text }) => (
  <div className="h-full min-h-[140px] flex flex-col items-center justify-center text-center text-slate-400">
    {React.cloneElement(icon, { className: 'w-8 h-8 mb-3 text-emerald-500' })}
    <p className="font-bold">{text}</p>
  </div>
);

const SeverityBadge: React.FC<{ severity: Alert['severity'] }> = ({ severity }) => {
  const styles = {
    critical: 'bg-rose-100 text-rose-700',
    warning: 'bg-amber-100 text-amber-700',
    info: 'bg-indigo-100 text-indigo-700',
  };
  const labels = {
    critical: 'Critica',
    warning: 'Aviso',
    info: 'Info',
  };
  return (
    <span className={`px-3 py-1 rounded-full text-xs font-black whitespace-nowrap ${styles[severity]}`}>
      {labels[severity]}
    </span>
  );
};

const alertLabel = (type: Alert['alertType']) => ({
  unknown_product: 'Producto nuevo',
  price_change: 'Cambio de precio',
  price_error: 'Error de precio',
  vat_error: 'Error IVA'
}[type]);

const formatDate = (value?: string) => {
  if (!value) return 'Sin fecha';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : shortDate.format(date);
};

export default DashboardPage;
