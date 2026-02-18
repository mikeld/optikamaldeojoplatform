
import { GoogleGenAI, Type } from "@google/genai";
import { InvoiceData } from "../types";

export const extractInvoiceData = async (base64Image: string, mimeType: string): Promise<InvoiceData> => {
  // Usamos (process.env as any) para evitar errores de tipos en entornos que no tienen definidos los tipos de Node/Vite
  // pero manteniendo la variable API_KEY que es donde el sistema inyecta la clave.
  const apiKey = import.meta.env.VITE_API_KEY || (process.env as any).API_KEY;
  if (!apiKey) {
    throw new Error("No se ha configurado la clave de API de Gemini (VITE_API_KEY)");
  }
  const ai = new GoogleGenAI({ apiKey });

  const response = await ai.models.generateContent({
    model: 'gemini-3-flash-preview',
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
