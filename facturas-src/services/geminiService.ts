
import { GoogleGenAI, Type } from "@google/genai";
import { AssistantContext, InvoiceData } from "../types";

export const extractInvoiceData = async (base64Image: string, mimeType: string): Promise<InvoiceData> => {
  // Usamos (process.env as any) para evitar errores de tipos en entornos que no tienen definidos los tipos de Node/Vite
  // pero manteniendo la variable API_KEY que es donde el sistema inyecta la clave.
  const apiKey = (import.meta as any).env?.VITE_API_KEY || (process.env as any).API_KEY;
  if (!apiKey) {
    throw new Error("No se ha configurado la clave de API de Gemini (VITE_API_KEY)");
  }
  const ai = new GoogleGenAI({ apiKey });

  const response = await ai.models.generateContent({
    model: 'gemini-3-flash-preview', //model: "gemini-1.5-flash",
    contents: {
      parts: [
        {
          inlineData: {
            data: base64Image,
            mimeType: mimeType,
          },
        },
        {
          text: `Extract invoice data. 
          IMPORTANT Rules for Item Extraction:
          1. Clean Descriptions: Remove technical noise from product names such as internal references (e.g. 100040256), graduation/powers (e.g. -02.75, +1.25), and other numeric codes (e.g. 850 141). 
             Example: "(1) 100040256 DAILIES TOTAL 1 90P 850 141 -02.75" should be extracted as "DAILIES TOTAL 1 90P".
          2. Grouping: If multiple lines refer to the same product (same cleaned description) AND have the same unit price, group them into a single item summing their quantities.
          3. Date Format: MUST be in YYYY-MM-DD.
          4. Return JSON following the provided schema.`,
        },
      ],
    },
    config: {
      responseMimeType: "application/json",
      responseSchema: {
        type: Type.OBJECT,
        properties: {
          providerName: { type: Type.STRING },
          date: { type: Type.STRING, description: "Date in YYYY-MM-DD format only" },
          invoiceNumber: { type: Type.STRING },
          items: {
            type: Type.ARRAY,
            items: {
              type: Type.OBJECT,
              properties: {
                description: { type: Type.STRING },
                quantity: { type: Type.NUMBER },
                unitPrice: { type: Type.NUMBER },
                total: { type: Type.NUMBER },
              },
              required: ["description", "quantity", "unitPrice", "total"]
            }
          },
          total: { type: Type.NUMBER },
        },
        required: ["providerName", "items", "total", "date"]
      },
    },
  });

  return JSON.parse(response.text || '{}');
};

export const askInvoiceAssistant = async (question: string, context: AssistantContext): Promise<string> => {
  const apiKey = (import.meta as any).env?.VITE_API_KEY || (process.env as any).API_KEY;
  if (!apiKey) {
    throw new Error("No se ha configurado la clave de API de Gemini (VITE_API_KEY)");
  }

  const ai = new GoogleGenAI({ apiKey });
  const compactContext = {
    generatedAt: context.generatedAt,
    audits: context.audits,
    pendingAlerts: context.pendingAlerts,
    priceHistory: context.priceHistory
  };

  const response = await ai.models.generateContent({
    model: 'gemini-3-flash-preview',
    contents: {
      parts: [
        {
          text: `Eres el asistente interno de Facturas Check para una óptica.

Responde en castellano, de forma breve y operativa.
Usa SOLO el contexto JSON proporcionado. No inventes importes, fechas, proveedores ni facturas.
Cuando menciones una factura, cita proveedor, número y fecha si están disponibles.
Si la pregunta no se puede responder con el contexto, dilo claramente y sugiere qué dato falta.

Contexto JSON:
${JSON.stringify(compactContext)}

Pregunta:
${question}`
        }
      ]
    }
  });

  return response.text || 'No he podido generar una respuesta con los datos disponibles.';
};
