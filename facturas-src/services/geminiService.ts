
import { AssistantContext, InvoiceData } from "../types";

const API_URL = '../pedidos/api/facturas.php';

export const extractInvoiceData = async (base64Image: string, mimeType: string): Promise<InvoiceData> => {
  const response = await fetch(`${API_URL}?action=extractInvoiceData`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ base64Image, mimeType })
  });

  const data = await response.json();
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

  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || 'El asistente no ha podido responder ahora mismo');
  }

  return data.answer || 'No he podido generar una respuesta con los datos disponibles.';
};
