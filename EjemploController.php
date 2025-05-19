<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;
use yii\web\Response;

class EjemploController extends Controller
{
    public function actionIndex()
    {
        \app\assets\CompresorAsset::register($this->view);
        return $this->render('../ejemplo/index');
    }

    public function actionUpload()
    {
        Yii::info("Solicitud recibida en actionUpload", __METHOD__);

        if (Yii::$app->request->isPost) {
            $response = ['success' => false, 'message' => 'Error desconocido'];

            try {
                // Obtener los archivos subidos
                $files = UploadedFile::getInstancesByName('pdfFiles');

                // Verificar que se hayan enviado archivos
                if (empty($files)) {
                    $response['message'] = 'No se recibieron archivos.';
                    return $this->asJson($response);
                }

                // Limitar a 10 archivos
                if (count($files) > 10) {
                    $response['message'] = 'No puedes subir más de 10 archivos.';
                    return $this->asJson($response);
                }

                // Verificar que todos los archivos sean PDFs
                $validFiles = [];
                foreach ($files as $file) {
                    if ($file && $file->extension === 'pdf') {
                        $validFiles[] = $file;
                    }
                }

                if (empty($validFiles)) {
                    $response['message'] = 'No se subieron archivos válidos (solo se aceptan PDFs).';
                    return $this->asJson($response);
                }

                // Crear directorio temporal y asegurarse de que esté vacío
                $uploadDir = Yii::getAlias('@runtime/uploads');
                if (file_exists($uploadDir)) {
                    array_map('unlink', glob("$uploadDir/*.*"));
                    Yii::info("Directorio temporal limpiado: $uploadDir", __METHOD__);
                } else {
                    mkdir($uploadDir, 0777, true);
                }

                // Guardar archivos temporalmente
                foreach ($validFiles as $file) {
                    $file->saveAs($uploadDir . '/' . $file->name);
                    Yii::info("Archivo guardado: " . $file->name, __METHOD__);
                }

                // Ruta del archivo combinado
                $outputPath = Yii::getAlias('@runtime/combined.pdf');

                // Usar la ruta completa del ejecutable de Python dentro del entorno virtual
                $pythonPath = 'C:\\wamp64\\www\\Yii2\\pdf_env\\Scripts\\python.exe';
                $command = escapeshellarg($pythonPath) . " " .
                           escapeshellarg(Yii::getAlias('@app') . '/combine_pdfs.py') . " " .
                           escapeshellarg($uploadDir) . " " .
                           escapeshellarg($outputPath);
                $output = [];
                $returnVar = 0;
                exec($command . ' 2>&1', $output, $returnVar);

                // Limpiar el directorio temporal inmediatamente después de ejecutar el script
                array_map('unlink', glob("$uploadDir/*.*"));
                if (file_exists($uploadDir)) {
                    rmdir($uploadDir);
                    Yii::info("Directorio temporal eliminado: $uploadDir", __METHOD__);
                }

                // Registrar el resultado del script Python
                Yii::info("Resultado de combine_pdfs.py - Código de retorno: $returnVar", __METHOD__);
                Yii::info("Salida del script: " . implode("\n", $output), __METHOD__);

                if ($returnVar !== 0) {
                    $errorMessage = !empty($output) ? implode("\n", $output) : 'Error desconocido al ejecutar el script Python';
                    $response['message'] = 'Error al combinar los PDFs: ' . $errorMessage;
                    return $this->asJson($response);
                }

                if (!file_exists($outputPath)) {
                    $response['message'] = 'El archivo combinado no se generó correctamente.';
                    return $this->asJson($response);
                }

                // Verificar el tamaño del archivo
                $fileSize = filesize($outputPath);
                Yii::info("Archivo combinado generado: $outputPath, Tamaño: $fileSize bytes", __METHOD__);
                if ($fileSize === 0) {
                    $response['message'] = 'El archivo PDF combinado está vacío.';
                    return $this->asJson($response);
                }

                // Ajustar el formato de la respuesta para que sea un archivo binario
                Yii::$app->response->format = Response::FORMAT_RAW;
                Yii::$app->response->headers->add('Content-Type', 'application/pdf');
                Yii::$app->response->headers->add('Content-Disposition', 'attachment; filename="combined.pdf"');

                // Enviar el archivo al cliente
                $content = file_get_contents($outputPath);
                if ($content === false) {
                    $response['message'] = 'No se pudo leer el archivo PDF combinado.';
                    return $this->asJson($response);
                }

                // Eliminar el archivo combinado después de leerlo
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                    Yii::info("Archivo combinado eliminado: $outputPath", __METHOD__);
                }

                return $content;
            } catch (Exception $e) {
                $response['message'] = 'Error interno: ' . $e->getMessage();
                return $this->asJson($response);
            }
        }

        $response['message'] = 'Método no permitido.';
        return $this->asJson($response);
    }
}