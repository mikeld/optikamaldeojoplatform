
import React, { useEffect, useState } from 'react';
import { db } from '../db';
import { AuditRecord, AuditStatus } from '../types';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { AlertTriangle, CheckCircle, FileText, TrendingUp, ArrowUpRight, ArrowDownRight, Activity } from 'lucide-react';

const DashboardPage: React.FC = () => {
  const [audits, setAudits] = useState<AuditRecord[]>([]);
  const [priceHikes, setPriceHikes] = useState<any[]>([]);

  useEffect(() => {
    const fetchAudits = async () => {
      const data = await db.getAudits();
      setAudits(data);
      calculatePriceTrends(data);
    };
    fetchAudits();
  }, []);

  const calculatePriceTrends = (allAudits: AuditRecord[]) => {
    const hikes: any[] = [];
    allAudits.forEach(a => {
      a.lines.forEach(l => {
        if (l.difference > 0) {
          hikes.push({
            name: l.invoiceDescription,
            diff: l.difference,
            date: a.invoiceDate,
            provider: a.provider
          });
        }
      });
    });
    setPriceHikes(hikes.sort((a, b) => b.diff - a.diff).slice(0, 5));
  };

  const stats = {
    total: audits.length,
    correct: audits.filter(a => a.globalStatus === 'COMPLETED').length,
    rejected: audits.filter(a => a.globalStatus === 'REJECTED').length,
    pending: audits.filter(a => a.globalStatus === 'PENDING').length,
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
          <h2 className="text-3xl font-black text-slate-800 tracking-tight">Análisis Inteligente</h2>
          <p className="text-slate-500 font-medium">Visualización de costos y rendimiento de auditoría.</p>
        </div>
        <div className="bg-white px-6 py-3 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3">
          <Activity className="text-indigo-600 w-5 h-5" />
          <span className="font-black text-slate-800 text-sm">SISTEMA ONLINE</span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <StatCard title="Facturas" value={stats.total} icon={<FileText />} bgColor="bg-indigo-50 text-indigo-600" />
        <StatCard title="Auditadas OK" value={stats.correct} icon={<CheckCircle />} bgColor="bg-emerald-50 text-emerald-600" />
        <StatCard title="Alertas Precio" value={priceHikes.length} icon={<AlertTriangle />} bgColor="bg-rose-50 text-rose-600" />
        <StatCard title="Volumen Total" value={`${stats.totalVolume.toLocaleString()}€`} icon={<TrendingUp />} bgColor="bg-slate-900 text-white" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Gráfico principal */}
        <div className="lg:col-span-2 bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
          <h3 className="text-xl font-black mb-8 text-slate-800 flex items-center gap-2">
            <Activity className="w-5 h-5 text-indigo-500" /> Distribución de Resultados
          </h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12, fontWeight: 700}} />
                <YAxis axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12, fontWeight: 700}} />
                <Tooltip cursor={{fill: '#f8fafc'}} contentStyle={{borderRadius: '16px', border: 'none', boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1)'}} />
                <Bar dataKey="value" radius={[8, 8, 8, 8]} barSize={60}>
                  {chartData.map((entry, index) => <Cell key={index} fill={entry.color} />)}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Alertas de Precio */}
        <div className="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
          <h3 className="text-xl font-black mb-6 text-slate-800 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-rose-500" /> Top Incrementos
          </h3>
          <div className="space-y-4">
            {priceHikes.length === 0 ? (
              <p className="text-slate-400 text-center py-8 italic font-medium">No se detectan sobrecostos.</p>
            ) : (
              priceHikes.map((hike, i) => (
                <div key={i} className="flex items-center justify-between p-4 rounded-2xl bg-rose-50/50 border border-rose-100">
                  <div className="max-w-[70%]">
                    <p className="font-bold text-slate-800 text-sm truncate">{hike.name}</p>
                    <p className="text-[10px] text-rose-500 font-black uppercase tracking-widest">{hike.provider}</p>
                  </div>
                  <div className="flex flex-col items-end">
                    <span className="text-rose-600 font-black flex items-center gap-0.5">
                      <ArrowUpRight className="w-4 h-4" /> +{hike.diff.toFixed(2)}€
                    </span>
                    <span className="text-[9px] text-slate-400 font-bold">{hike.date}</span>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

// Fixed: Corrected type of 'icon' from ReactNode to ReactElement<any> to allow React.cloneElement to accept 'className'
const StatCard: React.FC<{ title: string; value: any; icon: React.ReactElement<any>; bgColor: string }> = ({ title, value, icon, bgColor }) => (
  <div className="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-5">
    <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${bgColor}`}>
      {/* Fixed: Directly cloning the icon element which is now properly typed to avoid unknown property errors */}
      {React.cloneElement(icon, { className: 'w-7 h-7' })}
    </div>
    <div>
      <p className="text-[10px] text-slate-400 font-black uppercase tracking-widest">{title}</p>
      <p className="text-2xl font-black text-slate-800 tracking-tight">{value}</p>
    </div>
  </div>
);

const StatusBadge: React.FC<{ status: string }> = ({ status }) => {
  const styles = {
    COMPLETED: 'bg-emerald-100 text-emerald-700',
    REJECTED: 'bg-rose-100 text-rose-700',
    PENDING: 'bg-amber-100 text-amber-700',
  };
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-semibold ${styles[status as keyof typeof styles]}`}>
      {status === 'COMPLETED' ? 'Correcta' : status === 'REJECTED' ? 'Rechazada' : 'Pendiente'}
    </span>
  );
};

export default DashboardPage;
