
import React, { useState, useEffect } from 'react';
import { db } from '../db';
import { Alert } from '../types';
import { AlertTriangle, CheckCircle, XCircle, Info, Filter, Calendar } from 'lucide-react';

const AlertsPage: React.FC = () => {
    const [alerts, setAlerts] = useState<Alert[]>([]);
    const [statusFilter, setStatusFilter] = useState<'all' | 'pending' | 'resolved' | 'ignored'>('all');
    const [severityFilter, setSeverityFilter] = useState<'all' | 'info' | 'warning' | 'critical'>('all');

    useEffect(() => {
        loadAlerts();
    }, []);

    const loadAlerts = async () => {
        const data = await db.getAlerts();
        setAlerts(data);
    };

    const handleResolve = async (alertId: string, action: string) => {
        await db.resolveAlert(alertId, action);
        await loadAlerts();
    };

    const filtered = alerts.filter(alert => {
        const matchesStatus = statusFilter === 'all' || alert.status === statusFilter;
        const matchesSeverity = severityFilter === 'all' || alert.severity === severityFilter;
        return matchesStatus && matchesSeverity;
    });

    const stats = {
        total: alerts.length,
        pending: alerts.filter(a => a.status === 'pending').length,
        critical: alerts.filter(a => a.severity === 'critical' && a.status === 'pending').length,
        resolved: alerts.filter(a => a.status === 'resolved').length
    };

    const getSeverityColor = (severity: string) => {
        switch (severity) {
            case 'critical': return 'bg-rose-100 text-rose-700 border-rose-200';
            case 'warning': return 'bg-amber-100 text-amber-700 border-amber-200';
            case 'info': return 'bg-blue-100 text-blue-700 border-blue-200';
            default: return 'bg-slate-100 text-slate-700 border-slate-200';
        }
    };

    const getSeverityIcon = (severity: string) => {
        switch (severity) {
            case 'critical': return <XCircle className="w-5 h-5" />;
            case 'warning': return <AlertTriangle className="w-5 h-5" />;
            case 'info': return <Info className="w-5 h-5" />;
            default: return <Info className="w-5 h-5" />;
        }
    };

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'unknown_product': return 'Producto Desconocido';
            case 'price_change': return 'Cambio de Precio';
            case 'price_error': return 'Error de Precio';
            case 'vat_error': return 'Error de IVA';
            default: return type;
        }
    };

    return (
        <div className="min-h-screen space-y-8">
            {/* Header */}
            <div>
                <h1 className="text-4xl font-black text-slate-800 mb-2">Centro de Alertas</h1>
                <p className="text-slate-500">Gestiona las alertas detectadas en la validación de facturas</p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div className="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Alertas</p>
                            <p className="text-3xl font-black text-slate-800">{stats.total}</p>
                        </div>
                        <AlertTriangle className="w-12 h-12 text-slate-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-amber-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1">Pendientes</p>
                            <p className="text-3xl font-black text-amber-600">{stats.pending}</p>
                        </div>
                        <AlertTriangle className="w-12 h-12 text-amber-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-rose-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-rose-600 uppercase tracking-wider mb-1">Críticas</p>
                            <p className="text-3xl font-black text-rose-600">{stats.critical}</p>
                        </div>
                        <XCircle className="w-12 h-12 text-rose-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-emerald-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Resueltas</p>
                            <p className="text-3xl font-black text-emerald-600">{stats.resolved}</p>
                        </div>
                        <CheckCircle className="w-12 h-12 text-emerald-200" />
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <div className="flex items-center gap-4">
                    <Filter className="w-5 h-5 text-slate-400" />
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value as any)}
                        className="px-4 py-2 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none bg-white"
                    >
                        <option value="all">Todos los estados</option>
                        <option value="pending">Pendientes</option>
                        <option value="resolved">Resueltas</option>
                        <option value="ignored">Ignoradas</option>
                    </select>
                    <select
                        value={severityFilter}
                        onChange={(e) => setSeverityFilter(e.target.value as any)}
                        className="px-4 py-2 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none bg-white"
                    >
                        <option value="all">Todas las severidades</option>
                        <option value="critical">Críticas</option>
                        <option value="warning">Advertencias</option>
                        <option value="info">Informativas</option>
                    </select>
                </div>
            </div>

            {/* Alerts List */}
            <div className="space-y-4">
                {filtered.map(alert => (
                    <div
                        key={alert.id}
                        className={`bg-white rounded-3xl p-6 shadow-sm border transition-all hover:shadow-md ${alert.status === 'pending' ? 'border-slate-200' : 'border-slate-100 opacity-60'
                            }`}
                    >
                        <div className="flex items-start gap-4">
                            <div className={`p-3 rounded-2xl border ${getSeverityColor(alert.severity)}`}>
                                {getSeverityIcon(alert.severity)}
                            </div>
                            <div className="flex-1">
                                <div className="flex items-start justify-between mb-2">
                                    <div>
                                        <h3 className="font-black text-slate-800 text-lg">{getTypeLabel(alert.alertType)}</h3>
                                        <p className="text-sm text-slate-500">
                                            {alert.productName || alert.productSku || 'Producto no identificado'}
                                        </p>
                                    </div>
                                    <span className={`px-3 py-1 rounded-full text-xs font-bold ${alert.status === 'pending' ? 'bg-amber-100 text-amber-700' :
                                            alert.status === 'resolved' ? 'bg-emerald-100 text-emerald-700' :
                                                'bg-slate-100 text-slate-700'
                                        }`}>
                                        {alert.status === 'pending' ? 'Pendiente' :
                                            alert.status === 'resolved' ? 'Resuelta' : 'Ignorada'}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    {alert.expectedValue !== null && (
                                        <div>
                                            <p className="text-xs text-slate-400 mb-1">Valor Esperado</p>
                                            <p className="font-bold text-slate-700">{alert.expectedValue.toFixed(2)}€</p>
                                        </div>
                                    )}
                                    {alert.actualValue !== null && (
                                        <div>
                                            <p className="text-xs text-slate-400 mb-1">Valor Real</p>
                                            <p className="font-bold text-slate-700">{alert.actualValue.toFixed(2)}€</p>
                                        </div>
                                    )}
                                    {alert.difference !== null && (
                                        <div>
                                            <p className="text-xs text-slate-400 mb-1">Diferencia</p>
                                            <p className={`font-bold ${alert.difference > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                {alert.difference > 0 ? '+' : ''}{alert.difference.toFixed(2)}€
                                            </p>
                                        </div>
                                    )}
                                    {alert.differencePercent !== null && (
                                        <div>
                                            <p className="text-xs text-slate-400 mb-1">Variación</p>
                                            <p className={`font-bold ${alert.differencePercent > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                {alert.differencePercent > 0 ? '+' : ''}{alert.differencePercent.toFixed(1)}%
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center gap-2 text-xs text-slate-400">
                                    <Calendar className="w-3 h-3" />
                                    {new Date(alert.createdAt).toLocaleString('es-ES')}
                                    {alert.resolvedAt && (
                                        <>
                                            <span className="mx-2">→</span>
                                            Resuelta: {new Date(alert.resolvedAt).toLocaleString('es-ES')}
                                        </>
                                    )}
                                </div>

                                {alert.status === 'pending' && (
                                    <div className="flex gap-2 mt-4">
                                        <button
                                            onClick={() => handleResolve(alert.id, 'price_updated')}
                                            className="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition-all"
                                        >
                                            Actualizar Precio
                                        </button>
                                        <button
                                            onClick={() => handleResolve(alert.id, 'approved')}
                                            className="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 transition-all"
                                        >
                                            Aprobar
                                        </button>
                                        <button
                                            onClick={() => handleResolve(alert.id, 'ignored')}
                                            className="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-200 transition-all"
                                        >
                                            Ignorar
                                        </button>
                                    </div>
                                )}

                                {alert.resolutionAction && alert.status !== 'pending' && (
                                    <div className="mt-4 p-3 bg-slate-50 rounded-xl">
                                        <p className="text-xs text-slate-500">
                                            <span className="font-bold">Acción tomada:</span> {alert.resolutionAction}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {filtered.length === 0 && (
                <div className="bg-white rounded-3xl p-12 text-center">
                    <CheckCircle className="w-16 h-16 text-emerald-200 mx-auto mb-4" />
                    <p className="text-slate-400 font-medium">No hay alertas que mostrar</p>
                </div>
            )}
        </div>
    );
};

export default AlertsPage;
