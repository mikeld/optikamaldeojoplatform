
import { AssistantContext, InvoiceData, InvoiceExtractionOptions } from "../types";

const API_URL = '../pedidos/api/facturas.php';

const readJsonResponse = async (response: Response, fallbackMessage: string) => {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch {
    const plain = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    const detail = plain ? ` Respuesta del servidor: ${plain.slice(0, 180)}` : '';
    throw new Error(`${fallbackMessage}. El servidor no ha devuelto JSON.${detail}`);
  }
};

export const extractInvoiceData = async (base64Image: string, mimeType: string, options?: InvoiceExtractionOptions): Promise<InvoiceData> => {
  const response = await fetch(`${API_URL}?action=extractInvoiceData`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ base64Image, mimeType, model: options?.model })
  });

  const data = await readJsonResponse(response, 'No se ha podido extraer la factura con Gemini');
  if (!response.ok) {
    throw new Error(data.error || 'No se ha podido extraer la factura con Gemini');
  }

  return data;
};

export const extractInvoiceFile = async (file: File, options?: InvoiceExtractionOptions): Promise<InvoiceData> => {
  const formData = new FormData();
  formData.append('file', file);
  if (options?.model) {
    formData.append('model', options.model);
  }

  const response = await fetch(`${API_URL}?action=extractInvoiceData`, {
    method: 'POST',
    body: formData
  });

  const data = await readJsonResponse(response, 'No se ha podido extraer la factura con Gemini');
  if (!response.ok) {
    throw new Error(data.error || 'No se ha podido extraer la factura con Gemini');
  }

  return data;
};

export const askInvoiceAssistant = async (question: string, context: AssistantContext): Promise<string> => {
  const response = await fetch(`${API_URL}?action=askInvoiceAssistant`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ question, context })
  });

  const data = await readJsonResponse(response, 'El asistente no ha podido responder ahora mismo');
  if (!response.ok) {
    throw new Error(data.error || 'El asistente no ha podido responder ahora mismo');
  }

  return data.answer || 'No he podido generar una respuesta con los datos disponibles.';
};
