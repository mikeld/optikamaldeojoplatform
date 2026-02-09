
import React, { useState, useEffect } from 'react';
import { db } from '../db';
import { Product, AuditRecord } from '../types';
import { Plus, Search, Edit2, Trash2, Calendar, TrendingUp, X, LineChart as ChartIcon, AlertTriangle } from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

const InventoryPage: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [families, setFamilies] = useState<ProductFamily[]>([]);
  const [audits, setAudits] = useState<AuditRecord[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);

  // Borrado
  const [deleteId, setDeleteId] = useState<string | null>(null);

  // Evolución de precios
  const [evolutionProduct, setEvolutionProduct] = useState<Product | null>(null);
  const [evolutionData, setEvolutionData] = useState<any[]>([]);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    const [pData, fData, aData] = await Promise.all([
      db.getProducts(),
      db.getFamilies(),
      db.getAudits()
    ]);
    setProducts(pData);
    setFamilies(fData);
    setAudits(aData);
  };

  const handleSave = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    const newProduct: Product = {
      id: editingProduct?.id || `prod-${Date.now()}`,
      sku: formData.get('sku') as string,
      name: formData.get('name') as string,
      familyId: formData.get('familyId') as string || null,
      graduation: formData.get('graduation') as string || null,
      expectedPrice: parseFloat(formData.get('price') as string),
      vat: parseFloat(formData.get('vat') as string),
      provider: formData.get('provider') as string || null,
    };

    await db.upsertProduct(newProduct);
    await loadData();
    setIsModalOpen(false);
    setEditingProduct(null);
  };

  const confirmDelete = async () => {
    if (!deleteId) return;
    await db.deleteProduct(deleteId);
    await loadData();
    setDeleteId(null);
  };

  const showEvolution = async (product: Product) => {
    // Intentar obtener historial real de la base de datos
    const historyData = await db.getPriceHistory(product.id);

    if (historyData.length > 0) {
      setEvolutionData(historyData.map(h => ({
        date: new Date(h.changeDate).toLocaleDateString(),
        price: h.newPrice,
        reason: h.reason
      })));
    } else {
      // Fallback a auditorías si no hay historial explícito
      const history = audits
        .filter(a => a.globalStatus === 'approved' || a.globalStatus === 'in_review')
        .flatMap(a => (a.lines || [])
          .filter(l => l.masterProductId === product.id)
          .map(l => ({
            date: a.invoiceDate,
            price: l.invoiceUnitPrice,
            provider: a.provider
          }))
        )
        .sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

      if (history.length === 0) {
        history.push({ date: 'Actual', price: product.expectedPrice, provider: 'Maestro' });
      }
      setEvolutionData(history);
    }

    setEvolutionProduct(product);
  };

  const filtered = products.filter(p =>
    p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    p.sku.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-end">
        <div>
          <h2 className="text-3xl font-black text-slate-800 tracking-tight">Catálogo Maestro</h2>
          <p className="text-slate-500 font-medium">Gestiona tus productos y sus precios esperados.</p>
        </div>
        <button
          onClick={() => { setEditingProduct(null); setIsModalOpen(true); }}
          className="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-100 flex items-center gap-2"
        >
          <Plus className="w-5 h-5" /> NUEVO PRODUCTO
        </button>
      </div>

      <div className="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div className="p-4 bg-slate-50/50 flex items-center border-b border-slate-100">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
            <input
              type="text"
              placeholder="Buscar por nombre o SKU..."
              className="w-full pl-11 pr-4 py-3 rounded-2xl border-none focus:ring-2 focus:ring-indigo-500/20 bg-white shadow-sm font-medium outline-none"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] bg-slate-50/30">
                <th className="px-8 py-5">Referencia & Producto</th>
                <th className="px-8 py-5">Familia / Graduación</th>
                <th className="px-8 py-5 text-center">Precio Maestro</th>
                <th className="px-8 py-5">Actualización</th>
                <th className="px-8 py-5 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {filtered.map(product => (
                <tr key={product.id} className="hover:bg-slate-50/80 transition-colors group">
                  <td className="px-8 py-6">
                    <p className="font-mono text-[10px] text-indigo-500 font-black mb-1 tracking-wider">{product.sku}</p>
                    <p className="font-bold text-slate-800 text-lg leading-tight">{product.name}</p>
                  </td>
                  <td className="px-8 py-6">
                    {product.familyName ? (
                      <div className="space-y-1">
                        <span className="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[10px] font-bold uppercase">{product.familyName}</span>
                        {product.graduation && <p className="text-xs text-slate-500 font-medium">Grad: {product.graduation}</p>}
                      </div>
                    ) : (
                      <span className="text-slate-300 text-[10px] font-bold uppercase italic">Sin familia</span>
                    )}
                  </td>
                  <td className="px-8 py-6 text-center">
                    <div className="flex flex-col items-center">
                      <span className="text-xl font-black text-slate-800 font-mono">{product.expectedPrice.toFixed(2)}€</span>
                      <span className="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">S/ IVA: {product.vat}%</span>
                    </div>
                  </td>
                  <td className="px-8 py-6">
                    <div className="flex items-center gap-2 text-xs text-slate-500 font-bold bg-slate-100/50 w-fit px-3 py-1 rounded-full uppercase tracking-tighter">
                      <Calendar className="w-3.5 h-3.5 text-slate-400" />
                      {product.lastUpdated ? new Date(product.lastUpdated).toLocaleDateString() : 'Original'}
                    </div>
                  </td>
                  <td className="px-8 py-6 text-right">
                    <div className="flex justify-end gap-2">
                      <button
                        onClick={() => showEvolution(product)}
                        className="p-2.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-xl transition-all shadow-sm"
                        title="Ver evolución"
                      >
                        <TrendingUp className="w-5 h-5" />
                      </button>
                      <button
                        onClick={() => { setEditingProduct(product); setIsModalOpen(true); }}
                        className="p-2.5 bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white rounded-xl transition-all shadow-sm"
                        title="Editar"
                      >
                        <Edit2 className="w-5 h-5" />
                      </button>
                      <button
                        onClick={() => setDeleteId(product.id)}
                        className="p-2.5 bg-rose-50 text-rose-400 hover:bg-rose-600 hover:text-white rounded-xl transition-all shadow-sm"
                        title="Borrar"
                      >
                        <Trash2 className="w-5 h-5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal Borrado */}
      {deleteId && (
        <div className="fixed inset-0 bg-slate-900/60 flex items-center justify-center z-[60] backdrop-blur-sm p-4">
          <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-sm p-10 animate-in zoom-in duration-200 text-center">
            <div className="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6">
              <AlertTriangle className="w-10 h-10" />
            </div>
            <h3 className="text-2xl font-black text-slate-800 mb-2">¿Estás seguro?</h3>
            <p className="text-slate-500 text-sm mb-8 leading-relaxed">
              Esta acción es irreversible y eliminará este producto del catálogo maestro.
            </p>
            <div className="flex gap-3">
              <button onClick={() => setDeleteId(null)} className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors">CANCELAR</button>
              <button onClick={confirmDelete} className="flex-1 py-4 bg-rose-600 text-white rounded-2xl font-black hover:bg-rose-700 transition-all shadow-xl shadow-rose-100">ELIMINAR</button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Edición (Existente) */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-slate-900/60 flex items-center justify-center z-50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 animate-in zoom-in duration-200">
            <h3 className="text-2xl font-black text-slate-800 mb-6">{editingProduct ? 'Editar Producto' : 'Nuevo en Catálogo'}</h3>
            <form onSubmit={handleSave} className="space-y-5">
              <div>
                <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nombre Completo</label>
                <input required name="name" defaultValue={editingProduct?.name} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-bold text-slate-800" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">SKU / Referencia</label>
                  <input required name="sku" defaultValue={editingProduct?.sku} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-mono font-bold" />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Precio Maestro (€)</label>
                  <input required name="price" step="0.01" type="number" defaultValue={editingProduct?.expectedPrice} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-mono font-bold" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Graduación (Lentillas)</label>
                  <input name="graduation" defaultValue={editingProduct?.graduation || ''} placeholder="ej: -2.75" className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-bold" />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">IVA Aplicable (%)</label>
                  <input required name="vat" type="number" defaultValue={editingProduct?.vat || 21} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-bold" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Proveedor</label>
                  <input name="provider" defaultValue={editingProduct?.provider || ''} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-bold" />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Familia</label>
                  <select name="familyId" defaultValue={editingProduct?.familyId || ''} className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none bg-white font-bold">
                    <option value="">Ninguna</option>
                    {families.map(f => (
                      <option key={f.id} value={f.id}>{f.familyName}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="flex gap-3 pt-4">
                <button type="button" onClick={() => setIsModalOpen(false)} className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors">CANCELAR</button>
                <button type="submit" className="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200">GUARDAR</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Evolución (Existente mejorado) */}
      {evolutionProduct && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-[3rem] shadow-2xl w-full max-w-4xl p-10 animate-in zoom-in duration-300">
            <div className="flex justify-between items-start mb-10">
              <div className="flex items-center gap-5">
                <div className="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center">
                  <ChartIcon className="w-8 h-8" />
                </div>
                <div>
                  <h3 className="text-3xl font-black text-slate-800 leading-tight">Evolución de Precios</h3>
                  <p className="text-slate-500 font-bold text-lg">{evolutionProduct.name}</p>
                </div>
              </div>
              <button onClick={() => setEvolutionProduct(null)} className="p-3 bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded-2xl transition-all"><X className="w-6 h-6" /></button>
            </div>
            <div className="h-80 w-full mb-8">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={evolutionData}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                  <XAxis dataKey="date" axisLine={false} tickLine={false} tick={{ fill: '#94a3b8', fontSize: 10, fontWeight: 700 }} />
                  <YAxis axisLine={false} tickLine={false} tick={{ fill: '#94a3b8', fontSize: 10, fontWeight: 700 }} tickFormatter={(val) => `${val}€`} />
                  <Tooltip contentStyle={{ borderRadius: '16px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)', fontWeight: 800 }} />
                  <Line type="monotone" dataKey="price" stroke="#6366f1" strokeWidth={4} dot={{ fill: '#6366f1', r: 6, strokeWidth: 4, stroke: '#fff' }} activeDot={{ r: 8 }} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default InventoryPage;
