document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pdfUploadForm');
    const fileInput = document.getElementById('pdfFiles');
    const fileStatus = document.getElementById('fileStatus');
    const submitButton = document.getElementById('submitButton');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Configurar opciones de ToastrJS
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-bottom-right', // Esquina derecha inferior
        timeOut: 5000,
        onShown: function() {
            console.log('Toastr mostrado con opciones:', toastr.options);
        }
    };

    // Actualizar el estado del archivo seleccionado
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            fileStatus.textContent = `${fileInput.files.length} archivo(s) seleccionado(s)`;
        } else {
            fileStatus.textContent = 'Sin archivos seleccionados';
        }
    });

    // Manejar el envío del formulario
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const files = fileInput.files;
        if (files.length === 0) {
            toastr.error('Por favor, selecciona al menos un archivo PDF.', '', {
                backgroundColor: '#b71c1c', // Fondo rojo para error
                color: '#ffffff' // Letras blancas
            });
            return;
        }

        if (files.length > 10) {
            toastr.error('No puedes subir más de 10 archivos.', '', {
                backgroundColor: '#b71c1c',
                color: '#ffffff'
            });
            return;
        }

        // Validar que todos los archivos sean PDFs
        for (const file of files) {
            if (!file.type.includes('pdf')) {
                toastr.error('Solo se permiten archivos PDF.', '', {
                    backgroundColor: '#b71c1c',
                    color: '#ffffff'
                });
                return;
            }
        }

        // Mostrar indicador de carga
        submitButton.disabled = true;
        submitButton.textContent = 'Procesando...';

        // Crear FormData para enviar los archivos
        const formData = new FormData();
        for (const file of files) {
            formData.append('pdfFiles[]', file, file.name);
        }
        formData.append('_csrf', csrfToken);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                body: formData,
            });

            // Restaurar el botón
            submitButton.disabled = false;
            submitButton.textContent = 'Subir y Combinar';

            if (!response.ok) {
                const errorData = await response.json();
                console.error('Error en la respuesta del servidor:', errorData);
                toastr.error(errorData.message || 'Error al procesar la solicitud.', '', {
                    backgroundColor: '#b71c1c',
                    color: '#ffffff'
                });
                return;
            }

            // Verificar el tipo de contenido
            const contentType = response.headers.get('Content-Type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                console.error('Respuesta JSON inesperada:', errorData);
                toastr.error(errorData.message || 'Error inesperado.', '', {
                    backgroundColor: '#b71c1c',
                    color: '#ffffff'
                });
                return;
            }

            if (!contentType || !contentType.includes('application/pdf')) {
                const errorText = await response.text();
                console.error('Respuesta no es un PDF:', errorText);
                toastr.error('La respuesta del servidor no es un archivo PDF.', '', {
                    backgroundColor: '#b71c1c',
                    color: '#ffffff'
                });
                return;
            }

            // Manejar la respuesta del servidor
            const blob = await response.blob();
            if (blob.size === 0) {
                throw new Error('El archivo PDF recibido está vacío.');
            }

            const disposition = response.headers.get('Content-Disposition');
            let filename = 'combined.pdf';
            if (disposition && disposition.indexOf('attachment') !== -1) {
                const matches = disposition.match(/filename="(.+)"/);
                if (matches && matches[1]) {
                    filename = matches[1];
                }
            }

            // Crear un enlace para descargar el archivo
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);

            // Mostrar mensaje de éxito con estilo forzado
            toastr.success('Archivos combinados correctamente. PDF descargado.', '', {
                backgroundColor: '#2e7d32', // Fondo verde
                color: '#ffffff' // Letras blancas
            });
        } catch (error) {
            submitButton.disabled = false;
            submitButton.textContent = 'Subir y Combinar';
            toastr.error('Error al procesar los archivos: ' + error.message, '', {
                backgroundColor: '#b71c1c',
                color: '#ffffff'
            });
            console.error('Error:', error);
        }
    });
});