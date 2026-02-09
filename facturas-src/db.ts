
import { Product, AuditRecord, ProductFamily, PriceHistory, Alert } from './types';

const API_URL = '/pedidos/api/facturas.php';

export const db = {
  isCloud(): boolean {
    return true;
  },

  // ============================================================
  // PRODUCTOS
  // ============================================================

  async getProducts(): Promise<Product[]> {
    try {
      const response = await fetch(`${API_URL}?action=getProducts`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((p: any) => ({
        id: p.id.toString(),
        sku: p.sku,
        name: p.name,
        familyId: p.family_id || null,
        graduation: p.graduation || null,
        expectedPrice: parseFloat(p.expected_price),
        vat: parseFloat(p.vat),
        provider: p.provider || null,
        lastUpdated: p.last_updated,
        familyName: p.family_name,
        familyBasePrice: p.family_base_price ? parseFloat(p.family_base_price) : undefined
      }));
    } catch (error) {
      console.error("Error fetching products:", error);
      return [];
    }
  },

  async upsertProduct(product: Product): Promise<void> {
    const response = await fetch(`${API_URL}?action=upsertProduct`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(product)
    });
    if (!response.ok) throw new Error('Error saving product');
  },

  async deleteProduct(id: string): Promise<void> {
    const response = await fetch(`${API_URL}?action=deleteProduct&id=${id}`, {
      method: 'DELETE'
    });
    if (!response.ok) throw new Error('Error deleting product');
  },

  // ============================================================
  // FAMILIAS DE PRODUCTOS
  // ============================================================

  async getFamilies(): Promise<ProductFamily[]> {
    try {
      const response = await fetch(`${API_URL}?action=getFamilies`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((f: any) => ({
        id: f.id,
        familyName: f.family_name,
        basePrice: parseFloat(f.base_price),
        regexPattern: f.regex_pattern || null,
        productType: f.product_type,
        provider: f.provider || null,
        notes: f.notes || null,
        createdAt: f.created_at,
        updatedAt: f.updated_at
      }));
    } catch (error) {
      console.error("Error fetching families:", error);
      return [];
    }
  },

  async getFamily(id: string): Promise<ProductFamily | null> {
    try {
      const response = await fetch(`${API_URL}?action=getFamily&id=${id}`);
      if (!response.ok) throw new Error('Network response was not ok');
      const f = await response.json();
      if (!f) return null;
      return {
        id: f.id,
        familyName: f.family_name,
        basePrice: parseFloat(f.base_price),
        regexPattern: f.regex_pattern || null,
        productType: f.product_type,
        provider: f.provider || null,
        notes: f.notes || null,
        createdAt: f.created_at,
        updatedAt: f.updated_at
      };
    } catch (error) {
      console.error("Error fetching family:", error);
      return null;
    }
  },

  async getFamilyProducts(familyId: string): Promise<Product[]> {
    try {
      const response = await fetch(`${API_URL}?action=getFamilyProducts&id=${familyId}`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((p: any) => ({
        id: p.id.toString(),
        sku: p.sku,
        name: p.name,
        familyId: p.family_id || null,
        graduation: p.graduation || null,
        expectedPrice: parseFloat(p.expected_price),
        vat: parseFloat(p.vat),
        provider: p.provider || null,
        lastUpdated: p.last_updated
      }));
    } catch (error) {
      console.error("Error fetching family products:", error);
      return [];
    }
  },

  async upsertFamily(family: ProductFamily): Promise<string> {
    const response = await fetch(`${API_URL}?action=upsertFamily`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(family)
    });
    if (!response.ok) throw new Error('Error saving family');
    const result = await response.json();
    return result.id;
  },

  async deleteFamily(id: string): Promise<void> {
    const response = await fetch(`${API_URL}?action=deleteFamily`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Error deleting family');
    }
  },

  // ============================================================
  // AUDITORÍAS / FACTURAS
  // ============================================================

  async getAudits(): Promise<AuditRecord[]> {
    try {
      const response = await fetch(`${API_URL}?action=getAudits`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((a: any) => ({
        id: a.id.toString(),
        createdAt: a.created_at,
        invoiceDate: a.invoice_date,
        provider: a.provider,
        invoiceNumber: a.invoice_number,
        totalInvoice: parseFloat(a.total_invoice),
        globalStatus: a.global_status as any,
        lines: a.lines,
        pdfPath: a.pdf_path || null,
        ocrText: a.ocr_text || null,
        alertCount: a.alert_count || 0,
        criticalAlertCount: a.critical_alert_count || 0,
        reviewedBy: a.reviewed_by || null,
        reviewedAt: a.reviewed_at || null,
        notes: a.notes || null
      }));
    } catch (error) {
      console.error("Error fetching audits:", error);
      return [];
    }
  },

  async saveAudit(audit: AuditRecord): Promise<string> {
    const response = await fetch(`${API_URL}?action=saveAudit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(audit)
    });
    if (!response.ok) throw new Error('Error saving audit');
    const result = await response.json();
    return result.id;
  },

  // ============================================================
  // ALERTAS
  // ============================================================

  async getAlerts(auditId?: string, status?: 'pending' | 'resolved' | 'ignored'): Promise<Alert[]> {
    try {
      let url = `${API_URL}?action=getAlerts`;
      if (auditId) url += `&audit_id=${auditId}`;
      if (status) url += `&status=${status}`;

      const response = await fetch(url);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((a: any) => ({
        id: a.id,
        auditId: a.audit_id,
        lineNumber: a.line_number,
        alertType: a.alert_type,
        severity: a.severity,
        productSku: a.product_sku || null,
        productName: a.product_name || null,
        expectedValue: a.expected_value ? parseFloat(a.expected_value) : null,
        actualValue: a.actual_value ? parseFloat(a.actual_value) : null,
        difference: a.difference ? parseFloat(a.difference) : null,
        differencePercent: a.difference_percent ? parseFloat(a.difference_percent) : null,
        status: a.status,
        resolutionAction: a.resolution_action || null,
        resolvedAt: a.resolved_at || null,
        createdAt: a.created_at
      }));
    } catch (error) {
      console.error("Error fetching alerts:", error);
      return [];
    }
  },

  async createAlert(alert: Omit<Alert, 'id' | 'createdAt'>): Promise<string> {
    const response = await fetch(`${API_URL}?action=createAlert`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(alert)
    });
    if (!response.ok) throw new Error('Error creating alert');
    const result = await response.json();
    return result.id;
  },

  async resolveAlert(alertId: string, action: string): Promise<void> {
    const response = await fetch(`${API_URL}?action=resolveAlert`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ alertId, action })
    });
    if (!response.ok) throw new Error('Error resolving alert');
  },

  // ============================================================
  // HISTORIAL DE PRECIOS
  // ============================================================

  async getPriceHistory(productId: string): Promise<PriceHistory[]> {
    try {
      const response = await fetch(`${API_URL}?action=getPriceHistory&product_id=${productId}`);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      return data.map((h: any) => ({
        id: h.id,
        productId: h.product_id,
        oldPrice: h.old_price ? parseFloat(h.old_price) : null,
        newPrice: parseFloat(h.new_price),
        changeDate: h.change_date,
        reason: h.reason,
        changedBy: h.changed_by,
        invoiceId: h.invoice_id || null,
        productName: h.product_name,
        sku: h.sku
      }));
    } catch (error) {
      console.error("Error fetching price history:", error);
      return [];
    }
  },

  async recordPriceChange(change: Omit<PriceHistory, 'id' | 'productName' | 'sku'>): Promise<void> {
    const response = await fetch(`${API_URL}?action=recordPriceChange`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(change)
    });
    if (!response.ok) throw new Error('Error recording price change');
  },

  // ============================================================
  // VALIDACIÓN DE FACTURAS
  // ============================================================

  async validateInvoice(auditId: string, lines: any[]): Promise<{
    alertCount: number;
    criticalCount: number;
    alerts: Alert[];
  }> {
    const response = await fetch(`${API_URL}?action=validateInvoice`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ auditId, lines })
    });
    if (!response.ok) throw new Error('Error validating invoice');
    const result = await response.json();
    return {
      alertCount: result.alertCount,
      criticalCount: result.criticalCount,
      alerts: result.alerts.map((a: any) => ({
        id: a.id,
        auditId: a.audit_id,
        lineNumber: a.line_number,
        alertType: a.alert_type,
        severity: a.severity,
        productSku: a.product_sku || null,
        productName: a.product_name || null,
        expectedValue: a.expected_value ? parseFloat(a.expected_value) : null,
        actualValue: a.actual_value ? parseFloat(a.actual_value) : null,
        difference: a.difference ? parseFloat(a.difference) : null,
        differencePercent: a.difference_percent ? parseFloat(a.difference_percent) : null,
        status: 'pending',
        resolutionAction: null,
        resolvedAt: null,
        createdAt: new Date().toISOString()
      }))
    };
  }
};
