import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

export const renderPdfPagesAsJpeg = async (file: File, maxPages = 3): Promise<{ base64: string; mimeType: string }> => {
  const data = new Uint8Array(await file.arrayBuffer());
  const pdf = await pdfjsLib.getDocument({ data }).promise;
  const pageCount = Math.min(pdf.numPages, maxPages);
  const renderedPages = [];

  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber++) {
    const page = await pdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 1 });
    const targetWidth = 1500;
    const scale = Math.min(1.8, Math.max(1.1, targetWidth / viewport.width));
    const scaledViewport = page.getViewport({ scale });

    const pageCanvas = document.createElement('canvas');
    pageCanvas.width = Math.floor(scaledViewport.width);
    pageCanvas.height = Math.floor(scaledViewport.height);

    const pageContext = pageCanvas.getContext('2d');
    if (!pageContext) {
      throw new Error('No se ha podido preparar la vista previa del PDF');
    }

    await page.render({ canvas: pageCanvas, canvasContext: pageContext, viewport: scaledViewport }).promise;
    renderedPages.push(pageCanvas);
  }

  const canvas = document.createElement('canvas');
  canvas.width = Math.max(...renderedPages.map(page => page.width));
  canvas.height = renderedPages.reduce((sum, page) => sum + page.height, 0);

  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('No se ha podido preparar la vista previa del PDF');
  }

  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, canvas.width, canvas.height);

  let y = 0;
  renderedPages.forEach(page => {
    context.drawImage(page, 0, y);
    y += page.height;
  });

  const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
  return {
    base64: dataUrl.split(',')[1],
    mimeType: 'image/jpeg'
  };
};
