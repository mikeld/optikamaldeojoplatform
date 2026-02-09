
import React, { useState, useEffect } from 'react';
import { db } from '../db';
import { ProductFamily } from '../types';
import { Plus, Search, Edit2, Trash2, Package, X, Tag } from 'lucide-react';

const FamiliesPage: React.FC = () => {
    const [families, setFamilies] = useState<ProductFamily[]>([]);
    const [filter, setFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState<string>('all');
    const [isFormVisible, setIsFormVisible] = useState(false);
    const [editingFamily, setEditingFamily] = useState<ProductFamily | null>(null);
    const [deleteId, setDeleteId] = useState<string | null>(null);

    useEffect(() => {
        loadFamilies();
    }, []);

    const loadFamilies = async () => {
        const data = await db.getFamilies();
        setFamilies(data);
    };

    const handleSave = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = e.currentTarget;
        const formData = new FormData(form);

        const family: ProductFamily = {
            id: editingFamily?.id || `fam_${Date.now()}`,
            familyName: formData.get('familyName') as string,
            basePrice: parseFloat(formData.get('basePrice') as string),
            regexPattern: formData.get('regexPattern') as string || null,
            productType: formData.get('productType') as any,
            provider: formData.get('provider') as string || null,
            notes: formData.get('notes') as string || null
        };

        await db.upsertFamily(family);
        await loadFamilies();
        setIsFormVisible(false);
        setEditingFamily(null);
    };

    const confirmDelete = async () => {
        if (deleteId) {
            try {
                await db.deleteFamily(deleteId);
                await loadFamilies();
            } catch (error: any) {
                alert(error.message || 'Error al eliminar familia');
            }
            setDeleteId(null);
        }
    };

    const filtered = families.filter(f => {
        const matchesSearch = f.familyName.toLowerCase().includes(filter.toLowerCase()) ||
            f.provider?.toLowerCase().includes(filter.toLowerCase());
        const matchesType = typeFilter === 'all' || f.productType === typeFilter;
        return matchesSearch && matchesType;
    });

    const stats = {
        total: families.length,
        lenses: families.filter(f => f.productType === 'lens').length,
        frames: families.filter(f => f.productType === 'frame').length,
        others: families.filter(f => !['lens', 'frame'].includes(f.productType)).length
    };

    return (
        <div className="min-h-screen space-y-8">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-4xl font-black text-slate-800 mb-2">Familias de Productos</h1>
                    <p className="text-slate-500">Gestiona grupos de productos con el mismo precio base</p>
                </div>
                <button
                    onClick={() => { setEditingFamily(null); setIsFormVisible(true); }}
                    className="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-2xl font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200"
                >
                    <Plus className="w-5 h-5" />
                    Nueva Familia
                </button>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div className="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total</p>
                            <p className="text-3xl font-black text-slate-800">{stats.total}</p>
                        </div>
                        <Package className="w-12 h-12 text-indigo-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Lentillas</p>
                            <p className="text-3xl font-black text-blue-600">{stats.lenses}</p>
                        </div>
                        <Tag className="w-12 h-12 text-blue-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Monturas</p>
                            <p className="text-3xl font-black text-emerald-600">{stats.frames}</p>
                        </div>
                        <Tag className="w-12 h-12 text-emerald-200" />
                    </div>
                </div>
                <div className="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Otros</p>
                            <p className="text-3xl font-black text-amber-600">{stats.others}</p>
                        </div>
                        <Tag className="w-12 h-12 text-amber-200" />
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <div className="flex gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Buscar familias..."
                            value={filter}
                            onChange={(e) => setFilter(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none"
                        />
                    </div>
                    <select
                        value={typeFilter}
                        onChange={(e) => setTypeFilter(e.target.value)}
                        className="px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none bg-white"
                    >
                        <option value="all">Todos los tipos</option>
                        <option value="lens">Lentillas</option>
                        <option value="frame">Monturas</option>
                        <option value="accessory">Accesorios</option>
                        <option value="solution">Soluciones</option>
                        <option value="other">Otros</option>
                    </select>
                </div>
            </div>

            {/* Table */}
            <div className="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <table className="w-full">
                    <thead className="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th className="px-8 py-5 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Nombre Familia</th>
                            <th className="px-8 py-5 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Tipo</th>
                            <th className="px-8 py-5 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Proveedor</th>
                            <th className="px-8 py-5 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Precio Base</th>
                            <th className="px-8 py-5 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filtered.map(family => (
                            <tr key={family.id} className="hover:bg-slate-50/80 transition-colors group">
                                <td className="px-8 py-6">
                                    <div className="font-bold text-slate-800">{family.familyName}</div>
                                    {family.notes && <div className="text-xs text-slate-400 mt-1">{family.notes}</div>}
                                </td>
                                <td className="px-8 py-6">
                                    <span className={`px-3 py-1 rounded-full text-xs font-bold ${family.productType === 'lens' ? 'bg-blue-100 text-blue-700' :
                                            family.productType === 'frame' ? 'bg-emerald-100 text-emerald-700' :
                                                family.productType === 'accessory' ? 'bg-purple-100 text-purple-700' :
                                                    family.productType === 'solution' ? 'bg-cyan-100 text-cyan-700' :
                                                        'bg-slate-100 text-slate-700'
                                        }`}>
                                        {family.productType}
                                    </span>
                                </td>
                                <td className="px-8 py-6 text-slate-600">{family.provider || '-'}</td>
                                <td className="px-8 py-6 text-right">
                                    <span className="text-lg font-black text-slate-800">{family.basePrice.toFixed(2)}€</span>
                                </td>
                                <td className="px-8 py-6 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button
                                            onClick={() => { setEditingFamily(family); setIsFormVisible(true); }}
                                            className="p-2.5 bg-slate-50 text-slate-400 hover:bg-slate-900 hover:text-white rounded-xl transition-all shadow-sm"
                                            title="Editar"
                                        >
                                            <Edit2 className="w-4 h-4" />
                                        </button>
                                        <button
                                            onClick={() => setDeleteId(family.id)}
                                            className="p-2.5 bg-rose-50 text-rose-400 hover:bg-rose-600 hover:text-white rounded-xl transition-all shadow-sm"
                                            title="Borrar"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {filtered.length === 0 && (
                    <div className="text-center py-12">
                        <Package className="w-16 h-16 text-slate-200 mx-auto mb-4" />
                        <p className="text-slate-400 font-medium">No se encontraron familias</p>
                    </div>
                )}
            </div>

            {/* Delete Confirmation Modal */}
            {deleteId && (
                <div className="fixed inset-0 bg-slate-900/60 flex items-center justify-center z-50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 animate-in zoom-in duration-200">
                        <h3 className="text-2xl font-black text-slate-800 mb-4">¿Eliminar Familia?</h3>
                        <p className="text-slate-600 mb-6">Esta acción no se puede deshacer. Los productos de esta familia quedarán sin familia asignada.</p>
                        <div className="flex gap-3">
                            <button
                                onClick={() => setDeleteId(null)}
                                className="flex-1 py-4 bg-slate-100 text-slate-700 rounded-2xl font-black hover:bg-slate-200 transition-all"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={confirmDelete}
                                className="flex-1 py-4 bg-rose-600 text-white rounded-2xl font-black hover:bg-rose-700 transition-all shadow-xl shadow-rose-200"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Form Modal */}
            {isFormVisible && (
                <div className="fixed inset-0 bg-slate-900/60 flex items-center justify-center z-50 backdrop-blur-sm p-4 overflow-y-auto">
                    <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl p-8 my-8 animate-in zoom-in duration-200">
                        <div className="flex items-center justify-between mb-6">
                            <h3 className="text-2xl font-black text-slate-800">
                                {editingFamily ? 'Editar Familia' : 'Nueva Familia'}
                            </h3>
                            <button
                                onClick={() => { setIsFormVisible(false); setEditingFamily(null); }}
                                className="p-2 hover:bg-slate-100 rounded-xl transition-all"
                            >
                                <X className="w-6 h-6 text-slate-400" />
                            </button>
                        </div>
                        <form onSubmit={handleSave} className="space-y-5">
                            <div>
                                <label className="block text-sm font-bold text-slate-700 mb-2">Nombre de la Familia *</label>
                                <input
                                    type="text"
                                    name="familyName"
                                    required
                                    defaultValue={editingFamily?.familyName}
                                    placeholder="ej: DAILIES TOTAL 1 90P 850 141"
                                    className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-bold text-slate-700 mb-2">Precio Base *</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        name="basePrice"
                                        required
                                        defaultValue={editingFamily?.basePrice}
                                        placeholder="0.00"
                                        className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-bold text-slate-700 mb-2">Tipo de Producto *</label>
                                    <select
                                        name="productType"
                                        required
                                        defaultValue={editingFamily?.productType || 'lens'}
                                        className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none bg-white"
                                    >
                                        <option value="lens">Lentillas</option>
                                        <option value="frame">Monturas</option>
                                        <option value="accessory">Accesorios</option>
                                        <option value="solution">Soluciones</option>
                                        <option value="other">Otros</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-bold text-slate-700 mb-2">Proveedor</label>
                                <input
                                    type="text"
                                    name="provider"
                                    defaultValue={editingFamily?.provider || ''}
                                    placeholder="ej: Alcon, Johnson & Johnson"
                                    className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-bold text-slate-700 mb-2">Patrón Regex (Opcional)</label>
                                <input
                                    type="text"
                                    name="regexPattern"
                                    defaultValue={editingFamily?.regexPattern || ''}
                                    placeholder="Para auto-identificar variantes"
                                    className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none font-mono text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-bold text-slate-700 mb-2">Notas</label>
                                <textarea
                                    name="notes"
                                    defaultValue={editingFamily?.notes || ''}
                                    rows={3}
                                    placeholder="Información adicional sobre esta familia..."
                                    className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 transition-all outline-none resize-none"
                                />
                            </div>
                            <div className="flex gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => { setIsFormVisible(false); setEditingFamily(null); }}
                                    className="flex-1 py-4 bg-slate-100 text-slate-700 rounded-2xl font-black hover:bg-slate-200 transition-all"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200"
                                >
                                    GUARDAR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default FamiliesPage;
