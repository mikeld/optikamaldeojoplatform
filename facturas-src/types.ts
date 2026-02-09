
export interface Product {
  id: string;
  sku: string;
  name: string;
  familyId?: string | null;
  graduation?: string | null;
  expectedPrice: number;
  vat: number;
  provider?: string | null;
  lastUpdated?: string;
  // Datos calculados del JOIN con families
  familyName?: string;
  familyBasePrice?: number;
}

export interface ProductFamily {
  id: string;
  familyName: string;
  basePrice: number;
  regexPattern?: string | null;
  productType: 'lens' | 'frame' | 'accessory' | 'solution' | 'other';
  provider?: string | null;
  notes?: string | null;
  createdAt?: string;
  updatedAt?: string;
}

export interface PriceHistory {
  id: string;
  productId: string;
  oldPrice: number | null;
  newPrice: number;
  changeDate: string;
  reason: string;
  changedBy: string;
  invoiceId?: string | null;
  // Datos del JOIN
  productName?: string;
  sku?: string;
}

export interface Alert {
  id: string;
  auditId: string;
  lineNumber: number | null;
  alertType: 'unknown_product' | 'price_change' | 'price_error' | 'vat_error';
  severity: 'info' | 'warning' | 'critical';
  productSku?: string | null;
  productName?: string | null;
  expectedValue?: number | null;
  actualValue?: number | null;
  difference?: number | null;
  differencePercent?: number | null;
  status: 'pending' | 'resolved' | 'ignored';
  resolutionAction?: string | null;
  resolvedAt?: string | null;
  createdAt?: string;
}

export interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

export interface InvoiceData {
  providerName: string;
  date: string;
  invoiceNumber: string;
  items: InvoiceItem[];
  total: number;
}

export enum LineStatus {
  PENDING = 'PENDING',
  MATCHED = 'MATCHED',
  DISCREPANCY = 'DISCREPANCY',
  ACCEPTED = 'ACCEPTED',
  REJECTED = 'REJECTED',
  NEW_PRODUCT = 'NEW_PRODUCT'
}

export interface AuditLine {
  id: string;
  invoiceDescription: string;
  quantity: number;
  invoiceUnitPrice: number;
  masterProductPrice?: number;
  masterProductId?: string;
  status: LineStatus;
  difference: number;
}

export type AuditStatus = 'pending' | 'approved' | 'rejected' | 'in_review';

export interface AuditRecord {
  id: string;
  createdAt: string;
  invoiceDate: string;
  provider: string;
  invoiceNumber: string;
  lines: AuditLine[];
  totalInvoice: number;
  globalStatus: AuditStatus;
  pdfPath?: string | null;
  ocrText?: string | null;
  alertCount?: number;
  criticalAlertCount?: number;
  reviewedBy?: string | null;
  reviewedAt?: string | null;
  notes?: string | null;
}
