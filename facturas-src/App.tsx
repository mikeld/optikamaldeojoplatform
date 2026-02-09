
import React from 'react';
import { HashRouter, Routes, Route, NavLink } from 'react-router-dom';
import { LayoutDashboard, ShoppingCart, ShieldCheck, History, Database, Cloud, HardDrive, Package, AlertTriangle } from 'lucide-react';
import DashboardPage from './pages/DashboardPage';
import InventoryPage from './pages/InventoryPage';
import AuditPage from './pages/AuditPage';
import HistoryPage from './pages/HistoryPage';
import FamiliesPage from './pages/FamiliesPage';
import AlertsPage from './pages/AlertsPage';
import { db } from './db';

const App: React.FC = () => {
  const isCloud = db.isCloud();

  return (
    <HashRouter>
      <div className="flex min-h-screen bg-slate-50">
        {/* Sidebar */}
        <aside className="w-64 bg-white border-r border-slate-200 flex flex-col fixed inset-y-0">
          <div className="p-6 border-b border-slate-100">
            <h1 className="text-xl font-bold text-indigo-600 flex items-center gap-2">
              <ShieldCheck className="w-8 h-8" />
              AuditoriaAI
            </h1>
          </div>

          <nav className="flex-1 p-4 space-y-2 overflow-y-auto">
            <NavLink
              to="/"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <LayoutDashboard className="w-5 h-5" />
              Panel de Control
            </NavLink>
            <NavLink
              to="/inventory"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <ShoppingCart className="w-5 h-5" />
              Catálogo de Precios
            </NavLink>
            <NavLink
              to="/audit"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <ShieldCheck className="w-5 h-5" />
              Auditar Factura
            </NavLink>
            <NavLink
              to="/history"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <History className="w-5 h-5" />
              Historial
            </NavLink>
            <NavLink
              to="/families"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <Package className="w-5 h-5" />
              Familias
            </NavLink>
            <NavLink
              to="/alerts"
              className={({ isActive }) => `flex items-center gap-3 p-3 rounded-lg transition-colors ${isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-600 hover:bg-slate-50'}`}
            >
              <AlertTriangle className="w-5 h-5" />
              Alertas
            </NavLink>
          </nav>

          {/* Database Status */}
          <div className="p-4 mx-4 mb-4 rounded-xl bg-slate-50 border border-slate-100">
            <div className="flex items-center justify-between mb-2">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Base de Datos</span>
              {isCloud ? <Cloud className="w-3 h-3 text-emerald-500" /> : <HardDrive className="w-3 h-3 text-amber-500" />}
            </div>
            <div className="flex items-center gap-2">
              <div className={`w-2 h-2 rounded-full ${isCloud ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`}></div>
              <span className="text-xs font-bold text-slate-700">{isCloud ? 'MySQL Cloud' : 'Local Storage'}</span>
            </div>
            {!isCloud && (
              <p className="text-[9px] text-slate-400 mt-2 leading-tight">Conectado a la base de datos central de Optikamaldeojo.</p>
            )}
          </div>

          {/* Back to Home Button */}
          <div className="p-4 border-t border-slate-100">
            <a
              href="../home.php"
              className="flex items-center justify-center gap-2 p-3 rounded-lg transition-colors bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium"
            >
              <i className="fas fa-home"></i>
              Volver al Inicio
            </a>
          </div>

          <div className="p-4 text-xs text-slate-400 text-center">
            &copy; 2024 AuditoriaAI Pro
          </div>
        </aside>

        {/* Main Content */}
        <main className="flex-1 ml-64 p-8">
          <Routes>
            <Route path="/" element={<DashboardPage />} />
            <Route path="/inventory" element={<InventoryPage />} />
            <Route path="/audit" element={<AuditPage />} />
            <Route path="/history" element={<HistoryPage />} />
            <Route path="/families" element={<FamiliesPage />} />
            <Route path="/alerts" element={<AlertsPage />} />
          </Routes>
        </main>
      </div>
    </HashRouter>
  );
};

export default App;
