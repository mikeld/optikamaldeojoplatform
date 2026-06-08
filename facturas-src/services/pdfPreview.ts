import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

export interface RenderedPdfPage {
  pageNumber: number;
  base64: string;
  mimeType: 'image/jpeg';
  width: number;
  height: number;
}

export const renderPdfPageImages = async (file: File, maxPages = 4): Promise<RenderedPdfPage[]> => {
  const data = new Uint8Array(await file.arrayBuffer());
  const pdf = await pdfjsLib.getDocument({ data }).promise;
  const pageCount = Math.min(pdf.numPages, maxPages);
  const pages: RenderedPdfPage[] = [];

  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber++) {
    const page = await pdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 1 });
    const targetWidth = 1500;
    const scale = Math.min(1.8, Math.max(1.1, targetWidth / viewport.width));
    const scaledViewport = page.getViewport({ scale });

    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(scaledViewport.width);
    canvas.height = Math.floor(scaledViewport.height);

    const context = canvas.getContext('2d');
    if (!context) {
      throw new Error('No se ha podido preparar la vista previa del PDF');
    }

    await page.render({ canvas, canvasContext: context, viewport: scaledViewport }).promise;
    const dataUrl = canvas.toDataURL('image/jpeg', 0.84);
    pages.push({
      pageNumber,
      base64: dataUrl.split(',')[1],
      mimeType: 'image/jpeg',
      width: canvas.width,
      height: canvas.height,
    });
  }

  return pages;
};

export const combineRenderedPagesAsJpeg = async (renderedPages: RenderedPdfPage[]): Promise<{ base64: string; mimeType: string }> => {
  const pageImages = await Promise.all(renderedPages.map(page => {
    const image = new Image();
    image.src = `data:${page.mimeType};base64,${page.base64}`;
    return new Promise<HTMLImageElement>((resolve, reject) => {
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error('No se ha podido preparar la vista previa del PDF'));
    });
  }));

  const canvas = document.createElement('canvas');
  canvas.width = Math.max(...pageImages.map(page => page.width));
  canvas.height = pageImages.reduce((sum, page) => sum + page.height, 0);

  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('No se ha podido preparar la vista previa del PDF');
  }

  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, canvas.width, canvas.height);

  let y = 0;
  pageImages.forEach(page => {
    context.drawImage(page, 0, y);
    y += page.height;
  });

  const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
  return {
    base64: dataUrl.split(',')[1],
    mimeType: 'image/jpeg'
  };
};

export const renderPdfPagesAsJpeg = async (file: File, maxPages = 3): Promise<{ base64: string; mimeType: string }> => {
  const renderedPages = await renderPdfPageImages(file, maxPages);
  return combineRenderedPagesAsJpeg(renderedPages);
};
