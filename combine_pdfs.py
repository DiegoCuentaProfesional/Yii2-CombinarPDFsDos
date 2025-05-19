import sys
import fitz  # PyMuPDF
import os

def optimize_pdf(pdf_document):
    """
    Optimiza un documento PDF eliminando metadatos y reduciendo el tamaño si es posible.
    """
    try:
        # Eliminar metadatos innecesarios
        pdf_document.del_xml_metadata()
        pdf_document.set_metadata({})

        # Opcional: Reducir resolución de imágenes (si las hay)
        for page in pdf_document:
            try:
                for img in page.get_images(full=True):
                    xref = img[0]
                    image = pdf_document.extract_image(xref)
                    if image:
                        # Extraer la imagen como un Pixmap
                        pix = fitz.Pixmap(pdf_document, xref)
                        if pix.n < 5:  # Si no es un canal alfa
                            pix = fitz.Pixmap(fitz.csRGB, pix)  # Convertir a RGB
                            # Reducir resolución manualmente (al 50%)
                            pix_new = fitz.Pixmap(pix, pix.irect.scale(0.5, 0.5))
                            pdf_document._set_image(xref, pix_new)
                            pix_new = None
                        pix = None
            except Exception as e:
                print(f"Error al optimizar imágenes en página: {str(e)}", file=sys.stderr)
                continue
    except Exception as e:
        print(f"Error al optimizar PDF: {str(e)}", file=sys.stderr)

def combine_pdfs(input_dir, output_path):
    try:
        # Verificar que el directorio de entrada exista
        if not os.path.exists(input_dir):
            raise Exception("El directorio de entrada no existe")

        # Listar todos los archivos PDF en el directorio
        pdf_files = [f for f in os.listdir(input_dir) if f.lower().endswith('.pdf')]
        if not pdf_files:
            raise Exception("No se encontraron archivos PDF en el directorio")

        # Combinar PDFs
        output_pdf = fitz.open()
        for pdf_file in pdf_files:
            pdf_path = os.path.join(input_dir, pdf_file)
            pdf = fitz.open(pdf_path)
            output_pdf.insert_pdf(pdf)
            pdf.close()

        # Optimizar el PDF
        optimize_pdf(output_pdf)

        # Guardar el PDF combinado con máxima compresión
        output_pdf.save(output_path, garbage=4, deflate=True, clean=True, linear=True)
        output_pdf.close()

        return True
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        return False

if __name__ == "__main__":
    # Si se ejecuta desde la línea de comandos, se puede pasar el directorio y el archivo de salida
    if len(sys.argv) != 3:
        print("Uso: python combine_pdfs.py <input_dir> <output_path>", file=sys.stderr)
        sys.exit(1)
    
    input_dir = sys.argv[1]
    output_path = sys.argv[2]
    if combine_pdfs(input_dir, output_path):
        sys.exit(0)
    else:
        sys.exit(1)