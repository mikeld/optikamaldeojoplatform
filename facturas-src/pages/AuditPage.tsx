
import React, { useState, useRef, useEffect } from 'react';
import { Upload, FileText, Loader2, CheckCircle2, XCircle, AlertCircle, RefreshCw, Save, ArrowLeft, PlusCircle, Database, Trash2, X, CheckSquare, Edit3, AlertTriangle, ArrowUpCircle, ArrowDownCircle, Layers } from 'lucide-react';
import { extractInvoiceFile, extractInvoiceText } from '../services/geminiService';
import { combineRenderedPagesAsJpeg, dataUrlToFile, extractPdfTextPages, renderPdfPageImages } from '../services/pdfPreview';
import { db } from '../db';
import { InvoiceData, AuditLine, LineStatus, Product, AuditRecord, AuditStatus, ProductFamily, InvoiceItem, InvoiceAiModel } from '../types';

type ProcessingStepKey = 'idle' | 'reading' | 'rendering' | 'extracting' | 'loading' | 'matching' | 'done';

interface ProcessingStep {
  key: ProcessingStepKey;
  label: string;
  detail: string;
  percent: number;
  usesAi: boolean;
}

const defaultProcessingStep: ProcessingStep = {
  key: 'idle',
  label: 'Pendiente',
  detail: 'Selecciona una factura para empezar.',
  percent: 0,
  usesAi: false,
};

const extractionModes = {
  disabled: {
    label: '0 páginas · Sin IA',
    description: 'Recomendado. Usa texto y reglas locales. No consume créditos de Gemini.',
    aiPages: 0,
  },
  economy: {
    label: '2 páginas · Respaldo IA',
    description: 'Solo se usa si el parser local no reconoce la factura.',
    aiPages: 2,
  },
  complete: {
    label: 'Hasta 8 páginas · Respaldo IA',
    description: 'Mayor coste. Solo para PDFs escaneados o facturas difíciles.',
    aiPages: 8,
  },
  all: {
    label: 'Todas las páginas · Respaldo IA',
    description: 'Usa IA en todas las páginas detectadas del documento. Recomendado para facturas largas.',
    aiPages: 999,
  },
};

const formatSeconds = (startedAt: number | null) => {
  if (!startedAt) return '0s';
  return `${Math.max(0, Math.round((Date.now() - startedAt) / 1000))}s`;
};

const modelLabel = (model: InvoiceAiModel) => model === 'gemini-2.5-flash-lite' ? 'Flash-Lite' : 'Normal';

// Misma lógica de validación que el backend: la suma de líneas (importe neto)
// debe coincidir con el total, o quedar por debajo dentro del margen del IVA.
const computeInvoiceValidation = (items: InvoiceData['items'], declaredTotal: number) => {
  const linesSum = Math.round(items.reduce((sum, it) => sum + Number(it.total || 0), 0) * 100) / 100;
  let ok = false;
  let message = '';
  if (declaredTotal <= 0) {
    message = 'La factura no declara total; no se puede validar la suma de líneas.';
  } else if (Math.abs(linesSum - declaredTotal) <= 0.05) {
    ok = true;
  } else if (linesSum < declaredTotal && linesSum >= declaredTotal * 0.75) {
    ok = true; // diferencia compatible con IVA
  } else {
    message = `La suma de líneas (${linesSum.toFixed(2)}€) no cuadra con el total declarado (${declaredTotal.toFixed(2)}€). Revisa si faltan líneas o hay importes mal leídos.`;
  }
  return { linesSum, declaredTotal, ok, message };
};

const mergeInvoicePages = (pages: InvoiceData[]): InvoiceData => {
  const firstWithHeader = pages.find(page => page.providerName || page.invoiceNumber || page.date) || pages[0];
  const fiscalSummary = [...pages].reverse().find(page => page.hasFiscalSummary)
    || [...pages].reverse().find(page => Number(page.total || 0) > 0);
  const withOfficial = pages.find(page => page.officialProvider?.id);

  const items = pages.flatMap(page => page.items || []);
  const total = fiscalSummary?.total || 0;

  return {
    providerName: firstWithHeader?.providerName || 'Proveedor sin detectar',
    date: firstWithHeader?.date || new Date().toISOString().split('T')[0],
    invoiceNumber: firstWithHeader?.invoiceNumber || 'S/N',
    subtotal: fiscalSummary?.subtotal || 0,
    taxTotal: fiscalSummary?.taxTotal || 0,
    taxes: fiscalSummary?.taxes || [],
    hasFiscalSummary: Boolean(fiscalSummary?.hasFiscalSummary),
    total,
    items,
    officialProvider: withOfficial?.officialProvider || null,
    // La validación por página no sirve (cada página solo ve sus líneas): se recalcula sobre el total combinado
    validation: computeInvoiceValidation(items, total),
  };
};

const invoiceSummary = (audit: AuditRecord) => {
  const activeLines = audit.lines.filter(line => line.status !== LineStatus.REJECTED);
  const units = activeLines.reduce((sum, line) => sum + Number(line.quantity || 0), 0);
  const detectedTotal = activeLines.reduce((sum, line) => {
    const lineTotal = line.invoiceLineTotal;
    return sum + (typeof lineTotal === 'number' ? lineTotal : Number(line.quantity || 0) * Number(line.invoiceUnitPrice || 0));
  }, 0);
  const invoiceSubtotal = Number(audit.invoiceSubtotal ?? 0);
  const compareTotal = invoiceSubtotal > 0 ? invoiceSubtotal : Number(audit.totalInvoice || 0);
  const unknown = activeLines.filter(line => line.status === LineStatus.NEW_PRODUCT).length;
  const discrepancies = activeLines.filter(line => line.status === LineStatus.DISCREPANCY).length;
  const matched = activeLines.filter(line => line.status === LineStatus.MATCHED || line.status === LineStatus.ACCEPTED).length;

  return {
    lines: activeLines.length,
    units,
    detectedTotal,
    invoiceSubtotal,
    compareTotal,
    unknown,
    discrepancies,
    matched,
    invoiceTotal: Number(audit.totalInvoice || 0)
  };
};

const normalizeLensName = (value: string) => value
  .toLowerCase()
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/[+-]?\d{1,2}([.,]\d{1,2})/g, ' ')
  .replace(/\b(od|oi|os|add|low|med|high|bc|dia|cyl|cil|axis|eje|sph|esf|pwr|power)\b/g, ' ')
  .replace(/\b\d{1,4}\b/g, ' ')
  .replace(/[^a-z0-9]+/g, ' ')
  .replace(/\s+/g, ' ')
  .trim();

const extractGraduation = (item: InvoiceItem) => {
  if (item.graduation) return item.graduation;
  const match = item.description.match(/[+-]\s?\d{1,2}([.,]\d{1,2})/);
  return match ? match[0].replace(/\s+/g, '').replace(',', '.') : null;
};

const regexEscape = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const isCatalogCandidate = (line: AuditLine) => {
  const name = (line.baseProductName || line.invoiceDescription || '').toLowerCase();
  return line.status === LineStatus.NEW_PRODUCT
    && line.invoiceUnitPrice > 0
    && !name.includes('bono')
    && !name.includes('envío')
    && !name.includes('envio');
};

const unknownCatalogGroups = (lines: AuditLine[]) => {
  const groups = new Map<string, {
    key: string;
    name: string;
    price: number;
    lineIndexes: number[];
    units: number;
    graduations: Set<string>;
  }>();

  lines.forEach((line, index) => {
    if (!isCatalogCandidate(line)) return;
    const name = (line.baseProductName || line.invoiceDescription).trim();
    const key = `${normalizeLensName(name)}::${line.invoiceUnitPrice.toFixed(2)}`;
    const current = groups.get(key) || {
      key,
      name,
      price: line.invoiceUnitPrice,
      lineIndexes: [],
      units: 0,
      graduations: new Set<string>(),
    };
    current.lineIndexes.push(index);
    current.units += Number(line.quantity || 0);
    if (line.graduation) current.graduations.add(line.graduation);
    groups.set(key, current);
  });

  return [...groups.values()]
    .sort((a, b) => b.lineIndexes.length - a.lineIndexes.length)
    .slice(0, 12);
};

const matchInvoiceItem = (item: InvoiceItem, products: Product[], families: ProductFamily[]) => {
  const itemBase = item.baseProductName || item.description;
  const normalizedDescription = normalizeLensName(item.description);
  const normalizedBase = normalizeLensName(itemBase);
  const candidateText = normalizedBase || normalizedDescription;

  const productMatch = products.find(product => {
    const sku = product.sku.toLowerCase().trim();
    const productName = normalizeLensName(product.name);
    return sku === item.description.toLowerCase().trim()
      || productName === normalizedDescription
      || productName === normalizedBase;
  });

  if (productMatch) {
    return {
      price: productMatch.familyBasePrice ?? productMatch.expectedPrice,
      productId: productMatch.id,
      sku: productMatch.sku,
      familyId: productMatch.familyId || undefined,
      familyName: productMatch.familyName || undefined,
    };
  }

  const familyMatch = families.find(family => {
    const familyName = normalizeLensName(family.familyName);
    if (family.regexPattern) {
      try {
        const regex = new RegExp(family.regexPattern, 'i');
        if (regex.test(item.description) || regex.test(itemBase)) return true;
      } catch {
        // Ignore invalid regex configured by the user.
      }
    }

    if (!familyName || !candidateText) return false;
    return candidateText.includes(familyName) || familyName.includes(candidateText);
  });

  if (familyMatch) {
    return {
      price: familyMatch.basePrice,
      familyId: familyMatch.id,
      familyName: familyMatch.familyName,
    };
  }

  return null;
};

const AuditPage: React.FC = () => {
  const [file, setFile] = useState<File | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [processingStep, setProcessingStep] = useState<ProcessingStep>(defaultProcessingStep);
  const [processingStartedAt, setProcessingStartedAt] = useState<number | null>(null);
  const [processingElapsedTick, setProcessingElapsedTick] = useState(0);
  const [selectedModel, setSelectedModel] = useState<InvoiceAiModel>('gemini-2.5-flash');
  const [extractionMode, setExtractionMode] = useState<'disabled' | 'economy' | 'complete' | 'all'>('disabled');
  const [saveVisualSources, setSaveVisualSources] = useState(true);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [selectedProviderId, setSelectedProviderId] = useState<number | null>(null);

  useEffect(() => {
    db.getProviders().then(list => {
      setProviders(list);
    }).catch(err => {
      console.error('Error cargando proveedores:', err);
    });
  }, []);

  const [auditResult, setAuditResult] = useState<AuditRecord | null>(null);
  const [extractionWarning, setExtractionWarning] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [modalMode, setModalMode] = useState<'NONE' | 'SKU' | 'PRICE_UPDATE' | 'FINALIZE' | 'SUCCESS' | 'EDIT_LINE'>('NONE');
  const [activeLineIdx, setActiveLineIdx] = useState<number | null>(null);
  const [tempSku, setTempSku] = useState('');
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);
  const [editLineData, setEditLineData] = useState<AuditLine | null>(null);
  const [lastSaveSummary, setLastSaveSummary] = useState<{ alertCount: number; criticalCount: number; status: AuditStatus } | null>(null);

  useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (auditResult) {
        e.preventDefault();
        e.returnValue = 'Tienes una auditoría en curso. Si sales ahora, perderás los cambios no guardados.';
        return e.returnValue;
      }
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [auditResult]);

  useEffect(() => {
    if (!isProcessing) return;
    const interval = window.setInterval(() => setProcessingElapsedTick(value => value + 1), 1000);
    return () => window.clearInterval(interval);
  }, [isProcessing]);

  const processInvoice = async () => {
    if (!file) return;
    setIsProcessing(true);
    const startedAt = Date.now();
    setProcessingStartedAt(startedAt);
    setProcessingElapsedTick(0);
    setProcessingStep({
      key: 'reading',
      label: 'Preparando factura',
      detail: 'Leyendo el archivo en el navegador. Esta fase no consume IA.',
      percent: 8,
      usesAi: false,
    });
    setError(null);

    try {
      const selectedExtractionMode = extractionModes[extractionMode];
      const textPageLimit = 60;
      const textPages = file.type === 'application/pdf'
        ? await extractPdfTextPages(file, textPageLimit, ({ pageNumber, pageCount, totalPages }) => {
          setProcessingStep({
            key: 'reading',
            label: `Leyendo texto página ${pageNumber}/${pageCount}`,
            detail: totalPages > pageCount
              ? `Se lee texto de ${pageCount} de ${totalPages} páginas. Esta fase no consume IA.`
              : 'Extrayendo texto seleccionable del PDF. Esta fase no consume IA.',
            percent: 10 + Math.round((pageNumber / pageCount) * 25),
            usesAi: false,
          });
        })
        : [];
      const usableTextPages = textPages.filter(page => page.text.length > 80);
      const shouldUseTextExtraction = usableTextPages.length > 0;
      if (file.type !== 'application/pdf' && selectedExtractionMode.aiPages === 0) {
        throw new Error('Las imágenes necesitan visión IA para poder leerlas. Selecciona “2 páginas · Respaldo IA” o sube un PDF con texto.');
      }
      if (file.type === 'application/pdf' && !shouldUseTextExtraction && selectedExtractionMode.aiPages === 0) {
        throw new Error('Este PDF no contiene texto seleccionable. Activa el respaldo IA para leerlo como imagen.');
      }
      const pagesForAiRender = !shouldUseTextExtraction && file.type === 'application/pdf'
        ? await renderPdfPageImages(file, selectedExtractionMode.aiPages, ({ pageNumber, pageCount, totalPages }) => {
          setProcessingStep({
            key: 'rendering',
            label: `Renderizando página ${pageNumber}/${pageCount}`,
            detail: totalPages > pageCount
              ? `No se detectó texto suficiente. Se preparan ${pageCount} de ${totalPages} páginas como imagen.`
              : 'No se detectó texto suficiente. Generando imágenes legibles del PDF.',
            percent: 10 + Math.round((pageNumber / pageCount) * 25),
            usesAi: false,
          });
        })
        : [];
      let extracted: InvoiceData;
      let extractionMethod: 'texto_pdf_sin_ia' | 'texto_pdf_gemini' | 'imagen_pdf' | 'imagen' = file.type === 'application/pdf' ? 'imagen_pdf' : 'imagen';

      if (file.type === 'application/pdf' && shouldUseTextExtraction) {
        setProcessingStep({
          key: 'matching',
          label: 'Analizando texto del PDF',
          detail: 'Detectando líneas tabulares sin usar IA. Si no basta, se usará Gemini como respaldo.',
          percent: 42,
          usesAi: false,
        });

        const parsedFromText = await Promise.all(usableTextPages.map(async (page, index) => {
          setProcessingStep({
            key: 'matching',
            label: `Analizando texto página ${index + 1}/${usableTextPages.length}`,
            detail: 'Extracción por reglas de factura. Esta fase no consume IA.',
            percent: 38 + Math.round(((index + 1) / usableTextPages.length) * 28),
            usesAi: false,
          });
          return extractInvoiceText(page.text, { model: selectedModel, pageNumber: page.pageNumber, parserOnly: true });
        })).then(mergeInvoicePages);

        if (parsedFromText.items.length > 0) {
          extracted = parsedFromText;
          extractionMethod = 'texto_pdf_sin_ia';
        } else {
          if (selectedExtractionMode.aiPages === 0) {
            throw new Error('No se han reconocido líneas con el parser local. Activa el respaldo IA para esta factura o revisa el PDF.');
          }
          setProcessingStep({
            key: 'extracting',
            label: 'Extrayendo datos con Gemini',
            detail: `Modelo ${modelLabel(selectedModel)}. No se detectaron líneas por reglas; se usa texto del PDF por página.`,
            percent: 42,
            usesAi: true,
          });
          extracted = await Promise.all(usableTextPages.slice(0, selectedExtractionMode.aiPages).map(async (page, index) => {
            setProcessingStep({
              key: 'extracting',
              label: `Extrayendo texto página ${index + 1}/${Math.min(usableTextPages.length, selectedExtractionMode.aiPages)}`,
              detail: `Modelo ${modelLabel(selectedModel)}. Llamada IA sobre texto, no imagen.`,
              percent: 38 + Math.round(((index + 1) / Math.min(usableTextPages.length, selectedExtractionMode.aiPages)) * 28),
              usesAi: true,
            });
            return extractInvoiceText(page.text, { model: selectedModel, pageNumber: page.pageNumber, providerId: selectedProviderId });
          })).then(mergeInvoicePages);
          extractionMethod = 'texto_pdf_gemini';
        }
      } else if (file.type === 'application/pdf') {
        setProcessingStep({
          key: 'extracting',
          label: 'Extrayendo datos con Gemini',
          detail: `Modelo ${modelLabel(selectedModel)}. Procesando ${pagesForAiRender.length} imagen(es), una por una.`,
          percent: 42,
          usesAi: true,
        });
        extracted = await Promise.all(pagesForAiRender.map(async (page, index) => {
          setProcessingStep({
            key: 'extracting',
            label: `Extrayendo página ${index + 1}/${pagesForAiRender.length}`,
            detail: `Modelo ${modelLabel(selectedModel)}. Esta llamada consume IA, pero devuelve un JSON pequeño.`,
            percent: 38 + Math.round(((index + 1) / pagesForAiRender.length) * 28),
            usesAi: true,
          });
          const { base64, mimeType } = await combineRenderedPagesAsJpeg([page]);
          const optimizedFile = await dataUrlToFile(base64, mimeType, `factura-pagina-${page.pageNumber}.jpg`);
          return extractInvoiceFile(optimizedFile, { model: selectedModel, providerId: selectedProviderId });
        })).then(mergeInvoicePages);
      } else {
        setProcessingStep({
          key: 'extracting',
          label: 'Extrayendo datos con Gemini',
          detail: `Modelo ${modelLabel(selectedModel)}. Enviando la imagen a Gemini. Esta es la única fase que consume IA.`,
          percent: 42,
          usesAi: true,
        });
        extracted = await extractInvoiceFile(file, { model: selectedModel, providerId: selectedProviderId });
      }
      setProcessingStep({
        key: 'loading',
        label: 'Guardando y cargando catálogo',
        detail: saveVisualSources
          ? 'Subiendo el PDF, preparando fuentes visuales y recuperando productos/familias. Esta fase no consume IA.'
          : 'Subiendo el PDF y recuperando productos/familias. No se guardarán imágenes de página.',
        percent: 72,
        usesAi: false,
      });
      const pagesForVisualSources = saveVisualSources && file.type === 'application/pdf'
        ? await renderPdfPageImages(file, 8)
        : [];
      const [masterProducts, families, uploadedFile, uploadedPages] = await Promise.all([
        db.getProducts(),
        db.getFamilies(),
        db.uploadInvoiceFile(file),
        db.uploadInvoicePages(pagesForVisualSources)
      ]);

      setProcessingStep({
        key: 'matching',
        label: 'Comparando con catálogo',
        detail: 'Detectando productos conocidos, familias de lentillas y diferencias de precio.',
        percent: 88,
        usesAi: false,
      });
      const auditLines: AuditLine[] = extracted.items.map((item, idx) => {
        const match = matchInvoiceItem(item, masterProducts, families);

        let status = LineStatus.DISCREPANCY;
        if (!match) status = LineStatus.NEW_PRODUCT;
        else if (Math.abs(match.price - item.unitPrice) < 0.01) status = LineStatus.MATCHED;

        return {
          id: `line-${idx}-${Date.now()}`,
          invoiceDescription: item.description,
          baseProductName: item.baseProductName,
          quantity: item.quantity,
          invoiceUnitPrice: item.unitPrice,
          invoiceLineTotal: item.total,
          masterProductPrice: match?.price,
          masterProductId: match?.productId,
          masterProductSku: match?.sku,
          matchedFamilyId: match?.familyId,
          matchedFamilyName: match?.familyName,
          graduation: extractGraduation(item),
          status: status,
          difference: match ? item.unitPrice - match.price : 0,
          orderNumber: item.orderNumber || null,
          orderDate: item.orderDate || null,
          clientRef: item.clientRef || null
        };
      });

      setExtractionWarning(extracted.validation && !extracted.validation.ok && extracted.validation.message ? extracted.validation.message : null);
      setAuditResult({
        id: `temp-${Date.now()}`,
        createdAt: new Date().toISOString(),
        invoiceDate: extracted.date || new Date().toISOString().split('T')[0],
        provider: extracted.officialProvider?.name || extracted.providerName,
        pedidosProviderId: selectedProviderId
          || extracted.officialProvider?.id
          || providers.find(p => p.name.toLowerCase() === (extracted.providerName || '').toLowerCase())?.pedidosProviderId
          || null,
        invoiceNumber: extracted.invoiceNumber || 'S/N',
        lines: auditLines,
        totalInvoice: extracted.total,
        invoiceSubtotal: extracted.subtotal || extracted.items.reduce((sum, item) => sum + Number(item.total || 0), 0),
        taxTotal: extracted.taxTotal || 0,
        globalStatus: 'in_review',
        pdfPath: uploadedFile.path,
        pages: uploadedPages,
        ocrText: usableTextPages.map(p => p.text).join('\n\n') || null,
        notes: `Archivo original: ${uploadedFile.filename}. Modelo: ${selectedModel}. Extracción: ${extractionMethod}. Páginas IA: ${extractionMethod === 'texto_pdf_gemini' ? Math.min(usableTextPages.length, selectedExtractionMode.aiPages) : extractionMethod === 'imagen_pdf' ? pagesForAiRender.length : extractionMethod === 'imagen' ? 1 : 0}. Páginas texto: ${usableTextPages.length}. Fuentes visuales: ${saveVisualSources ? uploadedPages.length : 0}.`
      });
      setProcessingStep({
        key: 'done',
        label: 'Auditoría preparada',
        detail: 'Revisa líneas, diferencias y productos nuevos antes de guardar.',
        percent: 100,
        usesAi: false,
      });
      setIsProcessing(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al procesar la factura. Verifica que la imagen sea clara.");
      setIsProcessing(false);
      setProcessingStartedAt(null);
    }
  };

  const handleLineAction = async (lineIdx: number, action: 'ACCEPT' | 'REJECT' | 'ADD_TO_CATALOG' | 'EDIT' | 'RESOLVE_PRICE') => {
    if (!auditResult) return;
    const lines = [...auditResult.lines];
    const line = lines[lineIdx];

    if (action === 'ACCEPT') {
      line.status = LineStatus.ACCEPTED;
    }
    else if (action === 'RESOLVE_PRICE') {
      setActiveLineIdx(lineIdx);
      setModalMode('PRICE_UPDATE');
      return;
    }
    else if (action === 'REJECT') {
      line.status = LineStatus.REJECTED;
    }
    else if (action === 'ADD_TO_CATALOG') {
      setActiveLineIdx(lineIdx);
      setTempSku(`SKU-${Math.floor(Math.random() * 10000)}`);
      setModalMode('SKU');
      return;
    }
    else if (action === 'EDIT') {
      setActiveLineIdx(lineIdx);
      setEditLineData({ ...line });
      setModalMode('EDIT_LINE');
      return;
    }

    setAuditResult({ ...auditResult, lines });
  };

  const confirmPriceResolution = async (shouldUpdateMaster: boolean) => {
    if (activeLineIdx === null || !auditResult) return;
    const lines = [...auditResult.lines];
    const line = lines[activeLineIdx];

    if (shouldUpdateMaster && line.masterProductId) {
      const products = await db.getProducts();
      const p = products.find(prod => prod.id === line.masterProductId);
      if (p) {
        await db.upsertProduct({ ...p, expectedPrice: line.invoiceUnitPrice });
      }
    }

    line.status = LineStatus.ACCEPTED;
    if (shouldUpdateMaster) {
      line.masterProductPrice = line.invoiceUnitPrice;
      line.difference = 0;
    }
    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setActiveLineIdx(null);
  };

  const confirmCreateProduct = async () => {
    if (activeLineIdx === null || !auditResult || !tempSku) return;
    const lines = [...auditResult.lines];
    const line = lines[activeLineIdx];

    const newProd: Product = {
      id: `prod-${Date.now()}`,
      sku: tempSku,
      name: line.invoiceDescription,
      expectedPrice: line.invoiceUnitPrice,
      vat: 21
    };

    await db.upsertProduct(newProd);
    line.status = LineStatus.ACCEPTED;
    line.masterProductId = newProd.id;
    line.masterProductSku = newProd.sku;
    line.masterProductPrice = newProd.expectedPrice;

    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setActiveLineIdx(null);
    setTempSku('');
  };

  const createSuggestedFamilies = async () => {
    if (!auditResult) return;
    const suggestions = unknownCatalogGroups(auditResult.lines).filter(group => group.lineIndexes.length >= 2);
    if (suggestions.length === 0) {
      setError('No hay grupos claros para crear en bloque. Usa Vincular en una línea concreta.');
      return;
    }

    setIsBulkProcessing(true);
    try {
      const lines = [...auditResult.lines];
      for (const group of suggestions) {
        const family: ProductFamily = {
          id: `fam-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
          familyName: group.name,
          basePrice: group.price,
          regexPattern: regexEscape(group.name),
          productType: group.graduations.size > 0 ? 'lens' : 'other',
          provider: auditResult.provider,
          notes: `Creada desde factura ${auditResult.invoiceNumber}. ${group.lineIndexes.length} líneas agrupadas.`,
        };
        const familyId = await db.upsertFamily(family);
        group.lineIndexes.forEach(index => {
          const diff = lines[index].invoiceUnitPrice - group.price;
          lines[index] = {
            ...lines[index],
            matchedFamilyId: familyId,
            matchedFamilyName: group.name,
            masterProductPrice: group.price,
            difference: diff,
            status: Math.abs(diff) < 0.01 ? LineStatus.ACCEPTED : LineStatus.DISCREPANCY,
          };
        });
      }
      setAuditResult({ ...auditResult, lines });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se han podido crear las familias sugeridas');
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const saveEditedLine = () => {
    if (activeLineIdx === null || !auditResult || !editLineData) return;
    const lines = [...auditResult.lines];

    const diff = editLineData.masterProductPrice ? editLineData.invoiceUnitPrice - editLineData.masterProductPrice : 0;
    let newStatus = editLineData.status;

    if (editLineData.masterProductPrice) {
      newStatus = Math.abs(diff) < 0.01 ? LineStatus.MATCHED : LineStatus.DISCREPANCY;
    }

    lines[activeLineIdx] = {
      ...editLineData,
      invoiceLineTotal: typeof editLineData.invoiceLineTotal === 'number'
        ? editLineData.invoiceLineTotal
        : editLineData.invoiceUnitPrice * editLineData.quantity,
      difference: diff,
      status: newStatus
    };
    setAuditResult({ ...auditResult, lines });
    setModalMode('NONE');
    setEditLineData(null);
  };

  const validateAllItems = async () => {
    if (!auditResult) return;
    setIsBulkProcessing(true);
    const updatedLines = [...auditResult.lines];

    for (let i = 0; i < updatedLines.length; i++) {
      const line = updatedLines[i];
      if (line.status === LineStatus.REJECTED || line.status === LineStatus.ACCEPTED) continue;

      if (line.status === LineStatus.MATCHED) {
        line.status = LineStatus.ACCEPTED;
      }
    }

    setAuditResult({ ...auditResult, lines: updatedLines });
    setIsBulkProcessing(false);
  };

  const executeFinalize = async () => {
    if (!auditResult) return;
    try {
      const auditId = await db.saveAudit({ ...auditResult, globalStatus: 'in_review' });
      const validation = await db.validateInvoice(auditId, auditResult.lines
        .filter(line => line.status !== LineStatus.REJECTED)
        .map(line => ({
          sku: line.masterProductSku || null,
          name: line.invoiceDescription,
          familyName: line.matchedFamilyName || null,
          expectedPrice: line.masterProductPrice ?? null,
          price: line.invoiceUnitPrice,
          status: line.status
        })));
      const finalStatus: AuditStatus = validation.criticalCount > 0
        ? 'in_review'
        : validation.alertCount > 0
          ? 'pending'
          : 'approved';
      await db.saveAudit({
        ...auditResult,
        id: auditId,
        globalStatus: finalStatus,
        alertCount: validation.alertCount,
        criticalAlertCount: validation.criticalCount
      });
      setLastSaveSummary({
        alertCount: validation.alertCount,
        criticalCount: validation.criticalCount,
        status: finalStatus
      });
      setModalMode('SUCCESS');
    } catch (e: any) {
      setError(`Error: ${e.message}`);
      setModalMode('NONE');
    }
  };

  if (auditResult) {
    const summary = invoiceSummary(auditResult);
    const catalogSuggestions = unknownCatalogGroups(auditResult.lines);

    return (
      <div className="space-y-6 max-w-6xl mx-auto pb-20">
        {/* Modal de guardado */}
        {modalMode === 'SUCCESS' && lastSaveSummary && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[90] flex items-center justify-center p-4">
            <div className="bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full p-9 text-center animate-in zoom-in duration-200">
              <div className={`w-16 h-16 rounded-3xl flex items-center justify-center mx-auto mb-5 ${
                lastSaveSummary.criticalCount > 0 ? 'bg-rose-50 text-rose-600' :
                lastSaveSummary.alertCount > 0 ? 'bg-amber-50 text-amber-600' :
                'bg-emerald-50 text-emerald-600'
              }`}>
                {lastSaveSummary.alertCount > 0 ? <AlertTriangle className="w-8 h-8" /> : <CheckCircle2 className="w-8 h-8" />}
              </div>
              <h3 className="text-2xl font-black text-slate-800 mb-2">Auditoría guardada</h3>
              <p className="text-slate-500 text-sm mb-6 leading-relaxed">
                {lastSaveSummary.alertCount > 0
                  ? `Se han creado ${lastSaveSummary.alertCount} alertas (${lastSaveSummary.criticalCount} críticas) para revisar.`
                  : 'No se han detectado diferencias pendientes.'}
              </p>
              <div className="grid grid-cols-3 gap-3 mb-7">
                <div className="bg-slate-50 rounded-2xl p-3">
                  <p className="text-[9px] uppercase font-black text-slate-400 mb-1">Estado</p>
                  <p className="text-xs font-black text-slate-700">{lastSaveSummary.status === 'approved' ? 'OK' : 'Revisión'}</p>
                </div>
                <div className="bg-amber-50 rounded-2xl p-3">
                  <p className="text-[9px] uppercase font-black text-amber-500 mb-1">Alertas</p>
                  <p className="text-lg font-black text-amber-700">{lastSaveSummary.alertCount}</p>
                </div>
                <div className="bg-rose-50 rounded-2xl p-3">
                  <p className="text-[9px] uppercase font-black text-rose-500 mb-1">Críticas</p>
                  <p className="text-lg font-black text-rose-700">{lastSaveSummary.criticalCount}</p>
                </div>
              </div>
              <div className="flex gap-3">
                <button
                  onClick={() => {
                    setAuditResult(null);
                    setLastSaveSummary(null);
                    setModalMode('NONE');
                  }}
                  className="flex-1 py-3 bg-slate-100 text-slate-700 rounded-2xl font-black hover:bg-slate-200 transition-all"
                >
                  Nueva factura
                </button>
                <a
                  href="#/history"
                  className="flex-1 py-3 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 transition-all"
                >
                  Historial
                </a>
              </div>
            </div>
          </div>
        )}

        {/* Modal Resolución de Precio */}
        {modalMode === 'PRICE_UPDATE' && activeLineIdx !== null && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[80] flex items-center justify-center p-4">
            <div className="bg-white rounded-[3rem] shadow-2xl max-w-lg w-full p-10 animate-in zoom-in duration-200">
              <div className="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center mx-auto mb-6 text-amber-500">
                <AlertTriangle className="w-10 h-10" />
              </div>
              <h3 className="text-2xl font-black text-slate-800 text-center mb-2">Cambio de Precio</h3>
              <p className="text-slate-500 text-center text-sm mb-8 leading-relaxed">
                El ítem <strong>{auditResult.lines[activeLineIdx].invoiceDescription}</strong> ha variado. ¿Cómo quieres proceder?
              </p>

              <div className="grid grid-cols-2 gap-4 mb-8">
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                  <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Precio Catálogo</p>
                  <p className="text-xl font-black text-slate-400 font-mono line-through">{auditResult.lines[activeLineIdx].masterProductPrice?.toFixed(2)}€</p>
                </div>
                <div className="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 text-center">
                  <p className="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Nuevo Precio</p>
                  <p className="text-xl font-black text-indigo-600 font-mono">{auditResult.lines[activeLineIdx].invoiceUnitPrice.toFixed(2)}€</p>
                </div>
              </div>

              <div className="space-y-3">
                <button
                  onClick={() => confirmPriceResolution(true)}
                  className="w-full py-4 bg-indigo-600 text-white font-black rounded-2xl hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-2"
                >
                  <RefreshCw className="w-5 h-5" /> ACTUALIZAR MAESTRO Y VALIDAR
                </button>
                <button
                  onClick={() => confirmPriceResolution(false)}
                  className="w-full py-4 bg-white text-slate-600 font-black rounded-2xl border-2 border-slate-100 hover:bg-slate-50 transition-all"
                >
                  Solo validar para esta factura
                </button>
                <button
                  onClick={() => setModalMode('NONE')}
                  className="w-full py-3 text-slate-400 font-bold hover:text-slate-600 transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        )}

        {modalMode === 'SKU' && activeLineIdx !== null && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[80] flex items-center justify-center p-4">
            <div className="bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full p-9 animate-in zoom-in duration-200">
              <div className="w-16 h-16 bg-slate-900 text-white rounded-3xl flex items-center justify-center mx-auto mb-5">
                <PlusCircle className="w-8 h-8" />
              </div>
              <h3 className="text-2xl font-black text-slate-800 text-center mb-2">Crear producto</h3>
              <p className="text-slate-500 text-sm text-center mb-6 leading-relaxed">
                Se añadirá <strong>{auditResult.lines[activeLineIdx].invoiceDescription}</strong> al catálogo maestro con el precio de esta factura.
              </p>
              <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SKU / Referencia</label>
              <input
                value={tempSku}
                onChange={(e) => setTempSku(e.target.value)}
                className="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-100 focus:border-indigo-500 outline-none font-mono font-bold mb-6"
              />
              <div className="flex gap-3">
                <button
                  onClick={() => {
                    setModalMode('NONE');
                    setActiveLineIdx(null);
                    setTempSku('');
                  }}
                  className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors"
                >
                  CANCELAR
                </button>
                <button
                  onClick={confirmCreateProduct}
                  disabled={!tempSku.trim()}
                  className="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-black hover:bg-indigo-600 disabled:bg-slate-200 transition-all shadow-xl shadow-slate-200"
                >
                  CREAR
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal Edición de Línea */}
        {modalMode === 'EDIT_LINE' && editLineData && (
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] flex items-center justify-center p-4">
            <div className="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full p-10 animate-in zoom-in duration-200">
              <h3 className="text-2xl font-black text-slate-800 mb-6">Corregir OCR</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Descripción</label>
                  <input
                    value={editLineData.invoiceDescription}
                    onChange={(e) => setEditLineData({ ...editLineData, invoiceDescription: e.target.value })}
                    className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold outline-none focus:border-indigo-500"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Cantidad</label>
                    <input
                      type="number"
                      value={editLineData.quantity}
                      onChange={(e) => setEditLineData({ ...editLineData, quantity: parseFloat(e.target.value) })}
                      className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold font-mono outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Precio (€)</label>
                    <input
                      type="number"
                      step="0.01"
                      value={editLineData.invoiceUnitPrice}
                      onChange={(e) => setEditLineData({ ...editLineData, invoiceUnitPrice: parseFloat(e.target.value) })}
                      className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold font-mono outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Importe línea (€)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={editLineData.invoiceLineTotal ?? editLineData.invoiceUnitPrice * editLineData.quantity}
                    onChange={(e) => setEditLineData({ ...editLineData, invoiceLineTotal: parseFloat(e.target.value) })}
                    className="w-full px-4 py-3 rounded-xl border-2 border-slate-100 font-bold font-mono outline-none focus:border-indigo-500"
                  />
                </div>
              </div>
              <div className="flex gap-3 pt-8">
                <button onClick={() => setModalMode('NONE')} className="flex-1 py-4 font-black text-slate-400 hover:bg-slate-50 rounded-2xl transition-colors">CANCELAR</button>
                <button onClick={saveEditedLine} className="flex-1 py-4 bg-slate-900 text-white font-black rounded-2xl hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200">GUARDAR</button>
              </div>
            </div>
          </div>
        )}

        <div className="flex flex-col md:flex-row md:items-center justify-between bg-white p-6 rounded-2xl border border-slate-100 shadow-sm gap-4">
          <div className="flex items-center gap-4">
            <button onClick={() => { if (confirm('¿Cancelar auditoría?')) setAuditResult(null); }} className="p-2 hover:bg-slate-100 rounded-full"><ArrowLeft /></button>
            <div>
              <h2 className="text-2xl font-black text-slate-800 leading-tight">{auditResult.provider}</h2>
              <div className="flex gap-3 text-xs text-slate-400 mt-1 uppercase font-black">
                <span>FAC: {auditResult.invoiceNumber}</span>
                <span>•</span>
                <span>FECHA: {auditResult.invoiceDate}</span>
                {auditResult.pdfPath && (
                  <>
                    <span>•</span>
                    <span className="text-indigo-500">PDF guardado</span>
                  </>
                )}
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button onClick={validateAllItems} disabled={isBulkProcessing} className="px-5 py-2.5 bg-emerald-50 text-emerald-700 font-bold rounded-xl hover:bg-emerald-100 transition-all flex items-center gap-2">
              {isBulkProcessing ? <Loader2 className="animate-spin w-4 h-4" /> : <CheckSquare className="w-4 h-4" />}
              Validar líneas OK
            </button>
            <button onClick={executeFinalize} className="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 flex items-center gap-2 shadow-lg shadow-indigo-100 transition-all">
              <Save className="w-5 h-5" /> Guardar Auditoría
            </button>
          </div>
        </div>

        <div className="grid grid-cols-2 lg:grid-cols-8 gap-4">
          <SummaryCard label="Total factura" value={`${summary.invoiceTotal.toFixed(2)}€`} tone="indigo" />
          <SummaryCard label="Subtotal líneas" value={`${summary.compareTotal.toFixed(2)}€`} tone="slate" />
          <SummaryCard label="IVA" value={`${Number(auditResult.taxTotal || 0).toFixed(2)}€`} tone="slate" />
          <SummaryCard label="Total líneas" value={`${summary.detectedTotal.toFixed(2)}€`} tone={Math.abs(summary.detectedTotal - summary.compareTotal) > 0.05 ? 'amber' : 'slate'} />
          <SummaryCard label="Líneas" value={summary.lines.toString()} tone="slate" />
          <SummaryCard label="Unidades" value={summary.units.toString()} tone="slate" />
          <SummaryCard label="Sin catálogo" value={summary.unknown.toString()} tone={summary.unknown > 0 ? 'amber' : 'emerald'} />
          <SummaryCard label="Diferencias" value={summary.discrepancies.toString()} tone={summary.discrepancies > 0 ? 'rose' : 'emerald'} />
        </div>

        {extractionWarning && (
          <div className="rounded-2xl border border-rose-100 bg-rose-50 px-5 py-4 text-rose-800 flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 mt-0.5 shrink-0" />
            <p className="text-sm font-bold leading-relaxed">
              Aviso de extracción IA: {extractionWarning}
            </p>
          </div>
        )}

        {(summary.unknown > 0 || summary.discrepancies > 0 || Math.abs(summary.detectedTotal - summary.compareTotal) > 0.05) && (
          <div className="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4 text-amber-800 flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 mt-0.5 shrink-0" />
            <p className="text-sm font-bold leading-relaxed">
              Revisa la factura antes de guardarla: hay {summary.unknown} productos sin catálogo, {summary.discrepancies} diferencias de precio
              {Math.abs(summary.detectedTotal - summary.compareTotal) > 0.05 ? ` y el total de líneas detectado no coincide con el subtotal de factura (${summary.detectedTotal.toFixed(2)}€ vs ${summary.compareTotal.toFixed(2)}€).` : '.'}
            </p>
          </div>
        )}

        {catalogSuggestions.length > 0 && (
          <div className="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4">
              <div className="flex items-start gap-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                  <Layers className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="text-sm font-black text-slate-800 uppercase tracking-wide">Familias sugeridas</h3>
                  <p className="text-xs font-semibold text-slate-500 mt-1">Agrupa productos repetidos por nombre base y precio para no vincular línea a línea.</p>
                </div>
              </div>
              <button
                onClick={createSuggestedFamilies}
                disabled={isBulkProcessing}
                className="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-black hover:bg-indigo-600 disabled:bg-slate-200 transition-all flex items-center justify-center gap-2"
              >
                {isBulkProcessing ? <Loader2 className="w-4 h-4 animate-spin" /> : <PlusCircle className="w-4 h-4" />}
                Crear grupos claros
              </button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              {catalogSuggestions.slice(0, 6).map(group => (
                <div key={group.key} className="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <p className="font-black text-slate-800 text-sm line-clamp-2">{group.name}</p>
                  <p className="mt-2 text-[10px] font-black uppercase text-slate-400">
                    {group.lineIndexes.length} líneas · {group.units} uds · {group.graduations.size} grad.
                  </p>
                  <p className="mt-1 font-mono font-black text-indigo-600">{group.price.toFixed(2)}€</p>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <table className="w-full text-left">
            <thead>
              <tr className="bg-slate-50/50 text-slate-400 text-[9px] font-black uppercase tracking-[0.2em] border-b border-slate-100">
                <th className="px-6 py-4">Descripción en Factura</th>
                <th className="px-6 py-4 text-center">Precio Catálogo</th>
                <th className="px-6 py-4 text-center">Precio Factura</th>
                <th className="px-6 py-4 text-center">Importe</th>
                <th className="px-6 py-4">Estado</th>
                <th className="px-6 py-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {auditResult.lines.map((line, idx) => {
                const isDiscrepancy = line.status === LineStatus.DISCREPANCY;
                return (
                  <tr key={line.id} className={`transition-all ${isDiscrepancy ? 'bg-rose-50/20' : 'hover:bg-slate-50/30'}`}>
                    <td className="px-6 py-4">
                      <p className="font-bold text-slate-800 leading-tight">{line.invoiceDescription}</p>
                      <p className="text-[10px] text-slate-400 font-bold uppercase mt-1">
                        Cantidad: {line.quantity}
                        {line.graduation ? ` · Grad: ${line.graduation}` : ''}
                        {line.matchedFamilyName ? ` · Familia: ${line.matchedFamilyName}` : ''}
                      </p>
                      {(line.orderNumber || line.clientRef) && (
                        <p className="text-[10px] text-indigo-400 font-bold uppercase mt-0.5">
                          {line.orderNumber ? `Pedido ${line.orderNumber}` : ''}
                          {line.orderNumber && line.orderDate ? ` (${line.orderDate})` : ''}
                          {line.clientRef ? `${line.orderNumber ? ' · ' : ''}Cliente: ${line.clientRef}` : ''}
                        </p>
                      )}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-mono text-indigo-400 font-bold">{line.masterProductPrice ? `${line.masterProductPrice.toFixed(2)}€` : '--'}</span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex flex-col items-center">
                        <span className={`text-lg font-black font-mono ${isDiscrepancy ? 'text-rose-600' : 'text-slate-800'}`}>
                          {line.invoiceUnitPrice.toFixed(2)}€
                        </span>
                        {typeof line.invoiceLineTotal === 'number' && Math.abs(line.invoiceLineTotal - (line.invoiceUnitPrice * line.quantity)) > 0.01 && (
                          <span className="text-[9px] font-black text-slate-400 uppercase mt-0.5">
                            antes dto.
                          </span>
                        )}
                        {isDiscrepancy && (
                          <span className="flex items-center gap-1 text-[9px] font-black text-rose-500 uppercase mt-0.5">
                            {line.difference > 0 ? <ArrowUpCircle className="w-2.5 h-2.5" /> : <ArrowDownCircle className="w-2.5 h-2.5" />}
                            Diferencia detectada
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className="font-black font-mono text-slate-700">
                        {(typeof line.invoiceLineTotal === 'number' ? line.invoiceLineTotal : line.invoiceUnitPrice * line.quantity).toFixed(2)}€
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <StatusBadge status={line.status} diff={line.difference} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex justify-end gap-1.5">
                        <button onClick={() => handleLineAction(idx, 'EDIT')} className="p-2 text-slate-400 hover:bg-slate-100 rounded-lg transition-all" title="Corregir OCR">
                          <Edit3 className="w-4 h-4" />
                        </button>

                        {line.status === LineStatus.NEW_PRODUCT && (
                          <button onClick={() => handleLineAction(idx, 'ADD_TO_CATALOG')} className="flex items-center gap-1.5 text-[9px] font-black bg-slate-900 text-white px-3 py-2 rounded-lg hover:bg-indigo-600 transition-all uppercase">
                            <PlusCircle className="w-3.5 h-3.5" /> Vincular
                          </button>
                        )}

                        {isDiscrepancy && (
                          <button
                            onClick={() => handleLineAction(idx, 'RESOLVE_PRICE')}
                            className="flex items-center gap-2 px-3 py-2 bg-rose-600 text-white rounded-lg text-[10px] font-black hover:bg-rose-700 transition-all shadow-lg shadow-rose-100 uppercase"
                          >
                            <RefreshCw className="w-3.5 h-3.5" /> Resolver
                          </button>
                        )}

                        {line.status === LineStatus.PENDING && (
                          <button onClick={() => handleLineAction(idx, 'ACCEPT')} className="p-2 text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all">
                            <CheckCircle2 className="w-6 h-6" />
                          </button>
                        )}

                        {(line.status === LineStatus.ACCEPTED || line.status === LineStatus.MATCHED) && (
                          <div className="flex items-center gap-1.5 text-emerald-600 font-black text-[9px] bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-100 uppercase">
                            <CheckCircle2 className="w-3.5 h-3.5" /> Validado
                          </div>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto py-20 space-y-8">
      <div className="text-center space-y-4">
        <div className="bg-indigo-600 w-24 h-24 rounded-[2.5rem] flex items-center justify-center mx-auto text-white shadow-2xl shadow-indigo-200">
          <Upload className="w-12 h-12" />
        </div>
        <h1 className="text-4xl font-black text-slate-800 tracking-tight">Cargar Factura</h1>
        <p className="text-slate-500 text-lg">Inicia una auditoría subiendo una foto o PDF.</p>
      </div>
      <div onClick={() => fileInputRef.current?.click()} className="group border-3 border-dashed rounded-[3rem] p-20 text-center cursor-pointer transition-all border-slate-200 hover:border-indigo-400 hover:bg-white">
        <input
          type="file"
          ref={fileInputRef}
          onChange={(e) => {
            if (!e.target.files) return;
            setFile(e.target.files[0]);
            setError(null);
            setProcessingStep(defaultProcessingStep);
            setProcessingStartedAt(null);
          }}
          hidden
          accept="image/*,application/pdf"
        />
        <PlusCircle className="w-12 h-12 text-slate-300 mx-auto mb-4 group-hover:text-indigo-500 transition-colors" />
        <p className="text-slate-500 font-bold">{file ? file.name : 'Haz clic para seleccionar archivo'}</p>
      </div>
      <div className="rounded-[2rem] border border-slate-100 bg-white p-5 shadow-sm space-y-5">
        <div className={extractionMode === 'disabled' ? 'opacity-50' : ''}>
          <p className="text-xs font-black uppercase tracking-widest text-slate-400 mb-3">Modelo de respaldo IA</p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <button
              type="button"
              onClick={() => setSelectedModel('gemini-2.5-flash')}
              disabled={isProcessing || extractionMode === 'disabled'}
              className={`rounded-2xl border p-4 text-left transition-all ${selectedModel === 'gemini-2.5-flash' ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-white'}`}
            >
              <span className="block text-sm font-black">Normal</span>
              <span className="mt-1 block text-xs font-semibold opacity-75">Más calidad para OCR y facturas difíciles.</span>
            </button>
            <button
              type="button"
              onClick={() => setSelectedModel('gemini-2.5-flash-lite')}
              disabled={isProcessing || extractionMode === 'disabled'}
              className={`rounded-2xl border p-4 text-left transition-all ${selectedModel === 'gemini-2.5-flash-lite' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-white'}`}
            >
              <span className="block text-sm font-black">Flash-Lite</span>
              <span className="mt-1 block text-xs font-semibold opacity-75">Más barato y rápido. Útil para facturas claras.</span>
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <label className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <span className="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Proveedor (Opcional)</span>
            <select
              value={selectedProviderId || ''}
              onChange={(event) => {
                const val = event.target.value;
                setSelectedProviderId(val ? Number(val) : null);
              }}
              disabled={isProcessing}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-700 outline-none focus:border-indigo-300"
            >
              <option value="">Autodetectar Proveedor</option>
              {providers.map(p => (
                <option key={p.pedidosProviderId || p.name} value={p.pedidosProviderId || ''}>
                  {p.name} {p.isOfficial ? '' : '(No registrado)'}
                </option>
              ))}
            </select>
            <span className="mt-2 block text-xs font-semibold text-slate-500">
              {selectedProviderId ? 'Forzar reglas del proveedor seleccionado.' : 'Intentar detectar automáticamente.'}
            </span>
          </label>

          <label className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <span className="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Páginas con IA</span>
            <select
              value={extractionMode}
              onChange={(event) => setExtractionMode(event.target.value as 'disabled' | 'economy' | 'complete' | 'all')}
              disabled={isProcessing}
              className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-700 outline-none focus:border-indigo-300"
            >
              <option value="disabled">{extractionModes.disabled.label}</option>
              <option value="economy">{extractionModes.economy.label}</option>
              <option value="complete">{extractionModes.complete.label}</option>
              <option value="all">{extractionModes.all.label}</option>
            </select>
            <span className="mt-2 block text-xs font-semibold text-slate-500">{extractionModes[extractionMode].description}</span>
          </label>

          <label className={`flex items-start gap-3 rounded-2xl border p-4 transition-all ${saveVisualSources ? 'border-indigo-100 bg-indigo-50' : 'border-slate-100 bg-slate-50'}`}>
            <input
              type="checkbox"
              checked={saveVisualSources}
              onChange={(event) => setSaveVisualSources(event.target.checked)}
              disabled={isProcessing}
              className="mt-1 h-5 w-5 rounded border-slate-300 text-indigo-600"
            />
            <span>
              <span className="block text-sm font-black text-slate-800">Guardar imágenes/fuentes visuales</span>
              <span className="mt-1 block text-xs font-semibold text-slate-500">No gasta IA. Guarda páginas renderizadas para ver evidencia en el asistente.</span>
            </span>
          </label>
        </div>
      </div>
      <button disabled={!file || isProcessing} onClick={processInvoice} className="w-full py-6 bg-slate-900 text-white rounded-[2rem] font-black text-xl hover:bg-indigo-600 disabled:bg-slate-100 transition-all flex items-center justify-center gap-4">
        {isProcessing ? <><Loader2 className="animate-spin w-6 h-6" /> PROCESANDO...</> : "EMPEZAR AUDITORÍA"}
      </button>
      {isProcessing && (
        <div className="rounded-[2rem] border border-slate-100 bg-white p-5 shadow-sm space-y-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-slate-400">Estado del procesado</p>
              <h3 className="mt-1 text-lg font-black text-slate-800">{processingStep.label}</h3>
              <p className="mt-1 text-sm font-semibold leading-relaxed text-slate-500">{processingStep.detail}</p>
            </div>
            <div className={`shrink-0 rounded-2xl px-3 py-2 text-xs font-black ${processingStep.usesAi ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}>
              {processingStep.usesAi ? 'Usando IA' : 'Sin IA'}
            </div>
          </div>

          <div className="h-3 overflow-hidden rounded-full bg-slate-100">
            <div
              className={`h-full rounded-full transition-all duration-500 ${processingStep.usesAi ? 'bg-amber-500' : 'bg-indigo-600'}`}
              style={{ width: `${Math.max(4, Math.min(100, processingStep.percent))}%` }}
            />
          </div>

          <div className="flex items-center justify-between text-xs font-black text-slate-400">
            <span>{processingStep.percent}%</span>
            <span>{formatSeconds(processingStartedAt)} transcurridos</span>
          </div>
        </div>
      )}
      {error && (
        <div className="rounded-2xl border border-rose-100 bg-rose-50 px-5 py-4 text-rose-700 flex items-start gap-3">
          <AlertCircle className="w-5 h-5 mt-0.5" />
          <p className="text-sm font-bold leading-relaxed">{error}</p>
        </div>
      )}
    </div>
  );
};

const StatusBadge: React.FC<{ status: LineStatus, diff: number }> = ({ status, diff }) => {
  const cfg = {
    [LineStatus.MATCHED]: { color: 'text-emerald-600 bg-emerald-50 border-emerald-100', label: 'PRECIO OK' },
    [LineStatus.DISCREPANCY]: { color: 'text-rose-600 bg-rose-50 border-rose-100', label: `DIF: ${diff > 0 ? '+' : ''}${diff.toFixed(2)}€` },
    [LineStatus.NEW_PRODUCT]: { color: 'text-amber-600 bg-amber-50 border-amber-100', label: 'NO EN CATÁLOGO' },
    [LineStatus.ACCEPTED]: { color: 'text-blue-600 bg-blue-50 border-blue-100', label: 'ACEPTADO' },
    [LineStatus.REJECTED]: { color: 'text-slate-400 bg-slate-50 border-slate-200', label: 'EXCLUIDO' },
    [LineStatus.PENDING]: { color: 'text-slate-400', label: 'PENDIENTE' },
  };
  const { color, label } = cfg[status] || cfg[LineStatus.PENDING];
  return <span className={`px-3 py-1.5 rounded-lg text-[9px] font-black border tracking-wider ${color}`}>{label}</span>;
};

const SummaryCard: React.FC<{ label: string; value: string; tone: 'slate' | 'indigo' | 'emerald' | 'amber' | 'rose' }> = ({ label, value, tone }) => {
  const tones = {
    slate: 'bg-white border-slate-100 text-slate-800',
    indigo: 'bg-indigo-50 border-indigo-100 text-indigo-700',
    emerald: 'bg-emerald-50 border-emerald-100 text-emerald-700',
    amber: 'bg-amber-50 border-amber-100 text-amber-700',
    rose: 'bg-rose-50 border-rose-100 text-rose-700',
  };

  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${tones[tone]}`}>
      <p className="text-[9px] uppercase font-black tracking-widest opacity-60 mb-1">{label}</p>
      <p className="text-xl font-black font-mono">{value}</p>
    </div>
  );
};

export default AuditPage;
