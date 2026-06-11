
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
  baseProductName?: string;
  graduation?: string | null;
  quantity: number;
  unitPrice: number;
  total: number;
}

export interface InvoiceData {
  providerName: string;
  date: string;
  invoiceNumber: string;
  items: InvoiceItem[];
  subtotal?: number;
  taxTotal?: number;
  taxes?: Array<{ rate: number; base: number; amount: number }>;
  hasFiscalSummary?: boolean;
  total: number;
}

export type InvoiceAiModel = 'gemini-2.5-flash' | 'gemini-2.5-flash-lite';

export interface InvoiceExtractionOptions {
  model: InvoiceAiModel;
  providerId?: number | null;
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
  baseProductName?: string;
  quantity: number;
  invoiceUnitPrice: number;
  invoiceLineTotal?: number;
  masterProductPrice?: number;
  masterProductId?: string;
  masterProductSku?: string;
  matchedFamilyId?: string;
  matchedFamilyName?: string;
  graduation?: string | null;
  status: LineStatus;
  difference: number;
}

export type AuditStatus = 'pending' | 'approved' | 'rejected' | 'in_review';

export interface AuditRecord {
  id: string;
  createdAt: string;
  invoiceDate: string;
  provider: string;
  pedidosProviderId?: number | null;
  invoiceNumber: string;
  lines: AuditLine[];
  totalInvoice: number;
  invoiceSubtotal?: number;
  taxTotal?: number;
  globalStatus: AuditStatus;
  pdfPath?: string | null;
  ocrText?: string | null;
  alertCount?: number;
  criticalAlertCount?: number;
  reviewedBy?: string | null;
  reviewedAt?: string | null;
  notes?: string | null;
  pages?: InvoicePagePreview[];
}

export interface AssistantContext {
  generatedAt: string;
  audits: any[];
  pendingAlerts: any[];
  priceHistory: any[];
  retrieval?: {
    mode: string;
    question: string;
    terms: string[];
  };
}

export interface UploadedInvoiceFile {
  path: string;
  filename: string;
  mimeType: string;
  size: number;
}

export interface UploadedInvoicePage {
  pageNumber: number;
  path: string;
  mimeType: string;
  width: number;
  height: number;
}

export interface InvoicePagePreview extends UploadedInvoicePage {
  id?: string;
}

export interface SchemaStatus {
  ok: boolean;
  database: string;
  checkedAt: string;
  tables: Record<string, {
    exists: boolean;
    missingColumns: string[];
  }>;
  missingTables: string[];
  missingColumns: Record<string, string[]>;
  schemaFile: string;
  geminiConfigured: boolean;
}

export interface Provider {
  id: string | null;
  pedidosProviderId?: number | null;
  name: string;
  systemDescription: string | null;
  extractionRules: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  invoiceCount: number;
  active?: boolean;
  isOfficial?: boolean;
  importance?: 'principal' | 'puntual';
  expectedMonthlyInvoices?: number;
}

