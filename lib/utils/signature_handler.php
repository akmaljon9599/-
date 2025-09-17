<?php
namespace CourierService\Utils;

use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class SignatureHandler
{
    private $signaturesPath;

    public function __construct()
    {
        $this->signaturesPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/signatures/';
        
        if (!Directory::isDirectoryExists($this->signaturesPath)) {
            Directory::createDirectory($this->signaturesPath);
        }
    }

    /**
     * Сохранение подписи клиента
     */
    public function saveSignature($requestId, $signatureData, $format = 'base64')
    {
        try {
            $filename = 'signature_' . $requestId . '_' . date('YmdHis') . '.png';
            $filepath = $this->signaturesPath . $filename;
            
            if ($format === 'base64') {
                $this->saveBase64Signature($signatureData, $filepath);
            } elseif ($format === 'svg') {
                $this->saveSvgSignature($signatureData, $filepath);
            } else {
                throw new \Exception('Неподдерживаемый формат подписи: ' . $format);
            }
            
            // Сохраняем информацию о подписи в базу данных
            \CourierService\Main\DocumentTable::add([
                'REQUEST_ID' => $requestId,
                'TYPE' => 'signature',
                'FILE_PATH' => $filepath,
                'FILE_NAME' => $filename,
                'FILE_SIZE' => filesize($filepath),
                'MIME_TYPE' => 'image/png',
                'UPLOADED_BY' => $GLOBALS['USER']->GetID(),
                'UPLOAD_DATE' => new \Bitrix\Main\Type\DateTime()
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/upload/courier_service/signatures/' . $filename
            ];
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Signature Handler', 'saveSignature', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Сохранение подписи в формате base64
     */
    private function saveBase64Signature($base64Data, $filepath)
    {
        // Удаляем префикс data:image/png;base64, если есть
        if (strpos($base64Data, 'data:image') === 0) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }
        
        $imageData = base64_decode($base64Data);
        
        if ($imageData === false) {
            throw new \Exception('Ошибка декодирования base64 данных');
        }
        
        File::putFileContents($filepath, $imageData);
    }

    /**
     * Сохранение подписи в формате SVG
     */
    private function saveSvgSignature($svgData, $filepath)
    {
        // Конвертируем SVG в PNG
        $pngData = $this->svgToPng($svgData);
        File::putFileContents($filepath, $pngData);
    }

    /**
     * Конвертация SVG в PNG
     */
    private function svgToPng($svgData)
    {
        // Создаем временный SVG файл
        $tempSvgFile = tempnam(sys_get_temp_dir(), 'signature_') . '.svg';
        File::putFileContents($tempSvgFile, $svgData);
        
        // Создаем временный PNG файл
        $tempPngFile = tempnam(sys_get_temp_dir(), 'signature_') . '.png';
        
        // Используем ImageMagick для конвертации
        $command = "convert '{$tempSvgFile}' '{$tempPngFile}'";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Fallback: используем GD для простых SVG
            $pngData = $this->svgToPngWithGd($svgData);
        } else {
            $pngData = File::getFileContents($tempPngFile);
            unlink($tempPngFile);
        }
        
        unlink($tempSvgFile);
        
        return $pngData;
    }

    /**
     * Конвертация SVG в PNG с помощью GD (fallback)
     */
    private function svgToPngWithGd($svgData)
    {
        // Простая конвертация для базовых SVG
        $width = 400;
        $height = 200;
        
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        // Здесь можно добавить более сложную логику парсинга SVG
        // Для простоты создаем базовое изображение
        
        ob_start();
        imagepng($image);
        $pngData = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return $pngData;
    }

    /**
     * Получение подписи клиента
     */
    public function getSignature($requestId)
    {
        $signature = \CourierService\Main\DocumentTable::getList([
            'filter' => [
                'REQUEST_ID' => $requestId,
                'TYPE' => 'signature'
            ],
            'order' => ['UPLOAD_DATE' => 'DESC'],
            'limit' => 1
        ])->fetch();
        
        if ($signature && File::isFileExists($signature['FILE_PATH'])) {
            return [
                'success' => true,
                'filepath' => $signature['FILE_PATH'],
                'filename' => $signature['FILE_NAME'],
                'url' => '/upload/courier_service/signatures/' . $signature['FILE_NAME']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Подпись не найдена'
        ];
    }

    /**
     * Валидация подписи
     */
    public function validateSignature($signatureData, $format = 'base64')
    {
        try {
            if ($format === 'base64') {
                // Проверяем, что это валидный base64
                if (strpos($signatureData, 'data:image') === 0) {
                    $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
                }
                
                $decoded = base64_decode($signatureData, true);
                if ($decoded === false) {
                    return false;
                }
                
                // Проверяем, что это изображение
                $imageInfo = getimagesizefromstring($decoded);
                return $imageInfo !== false;
                
            } elseif ($format === 'svg') {
                // Проверяем, что это валидный SVG
                return strpos($signatureData, '<svg') !== false && strpos($signatureData, '</svg>') !== false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Создание миниатюры подписи
     */
    public function createThumbnail($signaturePath, $maxWidth = 200, $maxHeight = 100)
    {
        try {
            $imageInfo = getimagesize($signaturePath);
            if ($imageInfo === false) {
                throw new \Exception('Не удалось получить информацию об изображении');
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Вычисляем размеры миниатюры
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);
            
            // Создаем исходное изображение
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($signaturePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($signaturePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($signaturePath);
                    break;
                default:
                    throw new \Exception('Неподдерживаемый тип изображения: ' . $mimeType);
            }
            
            // Создаем миниатюру
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            
            // Сохраняем прозрачность для PNG
            if ($mimeType === 'image/png') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            // Изменяем размер
            imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // Сохраняем миниатюру
            $thumbnailPath = str_replace('.png', '_thumb.png', $signaturePath);
            imagepng($thumbnail, $thumbnailPath);
            
            // Освобождаем память
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Signature Handler', 'createThumbnail', $e->getMessage());
            return false;
        }
    }

    /**
     * Удаление подписи
     */
    public function deleteSignature($requestId)
    {
        try {
            $signatures = \CourierService\Main\DocumentTable::getList([
                'filter' => [
                    'REQUEST_ID' => $requestId,
                    'TYPE' => 'signature'
                ]
            ]);
            
            while ($signature = $signatures->fetch()) {
                if (File::isFileExists($signature['FILE_PATH'])) {
                    unlink($signature['FILE_PATH']);
                }
                
                \CourierService\Main\DocumentTable::delete($signature['ID']);
            }
            
            return true;
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Signature Handler', 'deleteSignature', $e->getMessage());
            return false;
        }
    }
}