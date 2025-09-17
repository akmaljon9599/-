<?php
namespace CourierService\Utils;

use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class PdfGenerator
{
    private $templatePath;
    private $outputPath;

    public function __construct()
    {
        $this->templatePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/templates/';
        $this->outputPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/contracts/';
        
        // Создаем директории если их нет
        if (!Directory::isDirectoryExists($this->templatePath)) {
            Directory::createDirectory($this->templatePath);
        }
        
        if (!Directory::isDirectoryExists($this->outputPath)) {
            Directory::createDirectory($this->outputPath);
        }
    }

    /**
     * Генерация договора оферты
     */
    public function generateContract($requestData, $signatureData = null)
    {
        try {
            $template = $this->getContractTemplate();
            $contractContent = $this->processTemplate($template, $requestData);
            
            // Добавляем подпись если есть
            if ($signatureData) {
                $contractContent = $this->addSignatureToContract($contractContent, $signatureData);
            }
            
            $filename = 'contract_' . $requestData['REQUEST_NUMBER'] . '_' . date('YmdHis') . '.pdf';
            $filepath = $this->outputPath . $filename;
            
            // Генерируем PDF
            $this->htmlToPdf($contractContent, $filepath);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/upload/courier_service/contracts/' . $filename
            ];
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('PDF Generator', 'generateContract', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение шаблона договора
     */
    private function getContractTemplate()
    {
        $templateFile = $this->templatePath . 'contract_template.html';
        
        if (!File::isFileExists($templateFile)) {
            $this->createDefaultTemplate($templateFile);
        }
        
        return File::getFileContents($templateFile);
    }

    /**
     * Создание шаблона по умолчанию
     */
    private function createDefaultTemplate($templateFile)
    {
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Договор оферты</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .contract-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; }
                .section { margin-bottom: 20px; }
                .section-title { font-weight: bold; margin-bottom: 10px; }
                .signature-section { margin-top: 50px; }
                .signature-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; margin-left: 10px; }
                .footer { margin-top: 50px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ДОГОВОР ОФЕРТЫ</h1>
                <p>на доставку банковской карты</p>
            </div>
            
            <div class="section">
                <div class="section-title">1. СТОРОНЫ ДОГОВОРА</div>
                <p><strong>Исполнитель:</strong> Банк</p>
                <p><strong>Заказчик:</strong> {CLIENT_NAME}</p>
                <p><strong>Телефон:</strong> {CLIENT_PHONE}</p>
                <p><strong>Адрес доставки:</strong> {CLIENT_ADDRESS}</p>
            </div>
            
            <div class="section">
                <div class="section-title">2. ПРЕДМЕТ ДОГОВОРА</div>
                <p>Исполнитель обязуется доставить банковскую карту по указанному адресу, а Заказчик обязуется принять и оплатить услуги по доставке.</p>
                <p><strong>Номер заявки:</strong> {REQUEST_NUMBER}</p>
                <p><strong>PAN карты:</strong> {PAN}</p>
                <p><strong>Тип карты:</strong> {CARD_TYPE}</p>
            </div>
            
            <div class="section">
                <div class="section-title">3. УСЛОВИЯ ДОСТАВКИ</div>
                <p>• Доставка осуществляется в рабочие дни с 9:00 до 18:00</p>
                <p>• При доставке необходимо предъявить документ, удостоверяющий личность</p>
                <p>• В случае отсутствия получателя, доставка переносится на следующий рабочий день</p>
                <p>• Курьер имеет право отказать в доставке при отсутствии документов</p>
            </div>
            
            <div class="section">
                <div class="section-title">4. ОТВЕТСТВЕННОСТЬ</div>
                <p>Стороны несут ответственность в соответствии с действующим законодательством РФ.</p>
            </div>
            
            <div class="signature-section">
                <p><strong>Подпись Заказчика:</strong> <span class="signature-line"></span></p>
                <p><strong>Дата:</strong> {SIGNATURE_DATE}</p>
            </div>
            
            <div class="footer">
                <p>Договор составлен в двух экземплярах, имеющих одинаковую юридическую силу.</p>
                <p>Дата составления: {CONTRACT_DATE}</p>
            </div>
        </body>
        </html>';
        
        File::putFileContents($templateFile, $template);
    }

    /**
     * Обработка шаблона с подстановкой данных
     */
    private function processTemplate($template, $requestData)
    {
        $replacements = [
            '{CLIENT_NAME}' => $requestData['CLIENT_NAME'],
            '{CLIENT_PHONE}' => $requestData['CLIENT_PHONE'],
            '{CLIENT_ADDRESS}' => $requestData['CLIENT_ADDRESS'],
            '{REQUEST_NUMBER}' => $requestData['REQUEST_NUMBER'],
            '{PAN}' => $requestData['PAN'],
            '{CARD_TYPE}' => $requestData['CARD_TYPE'] ?? 'Не указан',
            '{SIGNATURE_DATE}' => date('d.m.Y'),
            '{CONTRACT_DATE}' => date('d.m.Y H:i')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Добавление подписи в договор
     */
    private function addSignatureToContract($contractContent, $signatureData)
    {
        if (is_string($signatureData)) {
            // Если подпись в виде base64 изображения
            $signatureHtml = '<div style="margin-top: 20px;"><img src="data:image/png;base64,' . $signatureData . '" style="max-width: 200px; max-height: 100px;" /></div>';
        } else {
            // Если подпись в виде SVG
            $signatureHtml = '<div style="margin-top: 20px;">' . $signatureData . '</div>';
        }
        
        // Заменяем плейсхолдер подписи
        $contractContent = str_replace('<span class="signature-line"></span>', $signatureHtml, $contractContent);
        
        return $contractContent;
    }

    /**
     * Конвертация HTML в PDF
     */
    private function htmlToPdf($htmlContent, $outputPath)
    {
        // Используем wkhtmltopdf для конвертации HTML в PDF
        $tempHtmlFile = tempnam(sys_get_temp_dir(), 'contract_') . '.html';
        File::putFileContents($tempHtmlFile, $htmlContent);
        
        $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm --margin-left 20mm --margin-right 20mm '{$tempHtmlFile}' '{$outputPath}'";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        // Удаляем временный файл
        unlink($tempHtmlFile);
        
        if ($returnCode !== 0) {
            throw new \Exception('Ошибка генерации PDF: ' . implode("\n", $output));
        }
        
        return true;
    }

    /**
     * Генерация отчета в PDF
     */
    public function generateReport($data, $reportType = 'requests')
    {
        try {
            $template = $this->getReportTemplate($reportType);
            $reportContent = $this->processReportTemplate($template, $data);
            
            $filename = 'report_' . $reportType . '_' . date('YmdHis') . '.pdf';
            $filepath = $this->outputPath . $filename;
            
            $this->htmlToPdf($reportContent, $filepath);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/upload/courier_service/contracts/' . $filename
            ];
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('PDF Generator', 'generateReport', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getReportTemplate($reportType)
    {
        $templateFile = $this->templatePath . 'report_' . $reportType . '_template.html';
        
        if (!File::isFileExists($templateFile)) {
            $this->createDefaultReportTemplate($templateFile, $reportType);
        }
        
        return File::getFileContents($templateFile);
    }

    private function createDefaultReportTemplate($templateFile, $reportType)
    {
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Отчет по заявкам</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ОТЧЕТ ПО ЗАЯВКАМ</h1>
                <p>Период: {REPORT_PERIOD}</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Номер заявки</th>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                    </tr>
                </thead>
                <tbody>
                    {REPORT_DATA}
                </tbody>
            </table>
            
            <div class="footer">
                <p>Отчет сформирован: {REPORT_DATE}</p>
            </div>
        </body>
        </html>';
        
        File::putFileContents($templateFile, $template);
    }

    private function processReportTemplate($template, $data)
    {
        $replacements = [
            '{REPORT_PERIOD}' => $data['period'] ?? 'Не указан',
            '{REPORT_DATE}' => date('d.m.Y H:i'),
            '{REPORT_DATA}' => $this->generateReportTable($data['requests'] ?? [])
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function generateReportTable($requests)
    {
        $html = '';
        foreach ($requests as $index => $request) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $request['REQUEST_NUMBER'] . '</td>';
            $html .= '<td>' . $request['CLIENT_NAME'] . '</td>';
            $html .= '<td>' . $request['CLIENT_PHONE'] . '</td>';
            $html .= '<td>' . $request['STATUS'] . '</td>';
            $html .= '<td>' . $request['CREATED_DATE'] . '</td>';
            $html .= '</tr>';
        }
        
        return $html;
    }
}