<?php
namespace CourierService\Utils;

use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class ExcelExporter
{
    private $outputPath;

    public function __construct()
    {
        $this->outputPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/exports/';
        
        if (!Directory::isDirectoryExists($this->outputPath)) {
            Directory::createDirectory($this->outputPath);
        }
    }

    /**
     * Экспорт заявок в Excel
     */
    public function exportRequests($requests, $filters = [])
    {
        try {
            $filename = 'requests_export_' . date('YmdHis') . '.xlsx';
            $filepath = $this->outputPath . $filename;
            
            // Создаем Excel файл
            $this->createExcelFile($requests, $filepath, $filters);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/upload/courier_service/exports/' . $filename
            ];
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Excel Exporter', 'exportRequests', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Создание Excel файла
     */
    private function createExcelFile($requests, $filepath, $filters)
    {
        // Используем простой CSV формат для совместимости
        $csvContent = $this->generateCsvContent($requests, $filters);
        
        // Конвертируем CSV в Excel если возможно
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $this->createXlsxFile($csvContent, $filepath);
        } else {
            // Используем CSV как fallback
            $csvFile = str_replace('.xlsx', '.csv', $filepath);
            File::putFileContents($csvFile, $csvContent);
            
            // Переименовываем в .xlsx для совместимости
            rename($csvFile, $filepath);
        }
    }

    /**
     * Генерация CSV контента
     */
    private function generateCsvContent($requests, $filters)
    {
        $csv = '';
        
        // Заголовки
        $headers = [
            '№',
            'Номер заявки',
            'ID АБС',
            'ФИО клиента',
            'Телефон клиента',
            'Адрес доставки',
            'PAN',
            'Тип карты',
            'Статус',
            'Статус звонка',
            'Курьер',
            'Филиал',
            'Подразделение',
            'Оператор',
            'Дата регистрации',
            'Дата обработки',
            'Дата доставки',
            'Причина отказа'
        ];
        
        $csv .= $this->arrayToCsv($headers);
        
        // Данные
        foreach ($requests as $index => $request) {
            $row = [
                $index + 1,
                $request['REQUEST_NUMBER'],
                $request['ABS_ID'] ?? '',
                $request['CLIENT_NAME'],
                $request['CLIENT_PHONE'],
                $request['CLIENT_ADDRESS'],
                $request['PAN'],
                $request['CARD_TYPE'] ?? '',
                $this->getStatusText($request['STATUS']),
                $this->getCallStatusText($request['CALL_STATUS']),
                $request['COURIER_NAME'] ?? '',
                $request['BRANCH_NAME'] ?? '',
                $request['DEPARTMENT_NAME'] ?? '',
                $request['OPERATOR_NAME'] ?? '',
                $request['CREATED_DATE']->format('d.m.Y H:i'),
                $request['PROCESSED_DATE'] ? $request['PROCESSED_DATE']->format('d.m.Y H:i') : '',
                $request['DELIVERY_DATE'] ? $request['DELIVERY_DATE']->format('d.m.Y H:i') : '',
                $request['REJECTION_REASON'] ?? ''
            ];
            
            $csv .= $this->arrayToCsv($row);
        }
        
        // Добавляем информацию о фильтрах
        if (!empty($filters)) {
            $csv .= "\n\nФильтры:\n";
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    $csv .= $key . ': ' . $value . "\n";
                }
            }
        }
        
        return $csv;
    }

    /**
     * Конвертация массива в CSV строку
     */
    private function arrayToCsv($array)
    {
        $csv = '';
        foreach ($array as $value) {
            $csv .= '"' . str_replace('"', '""', $value) . '",';
        }
        return rtrim($csv, ',') . "\n";
    }

    /**
     * Создание XLSX файла с помощью PhpSpreadsheet
     */
    private function createXlsxFile($csvContent, $filepath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Парсим CSV контент
        $lines = explode("\n", trim($csvContent));
        $row = 1;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            $col = 1;
            
            foreach ($data as $cell) {
                $sheet->setCellValueByColumnAndRow($col, $row, $cell);
                $col++;
            }
            $row++;
        }
        
        // Форматирование
        $this->formatExcelSheet($sheet);
        
        // Сохранение
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
    }

    /**
     * Форматирование Excel листа
     */
    private function formatExcelSheet($sheet)
    {
        // Автоширина колонок
        foreach (range('A', 'Z') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Жирный шрифт для заголовков
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        
        // Цвет фона для заголовков
        $sheet->getStyle('A1:R1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        
        // Границы
        $sheet->getStyle('A1:R' . $sheet->getHighestRow())
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }

    /**
     * Экспорт статистики курьеров
     */
    public function exportCourierStats($couriers, $period = [])
    {
        try {
            $filename = 'courier_stats_' . date('YmdHis') . '.xlsx';
            $filepath = $this->outputPath . $filename;
            
            $csvContent = $this->generateCourierStatsCsv($couriers, $period);
            
            if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                $this->createCourierStatsXlsx($csvContent, $filepath);
            } else {
                $csvFile = str_replace('.xlsx', '.csv', $filepath);
                File::putFileContents($csvFile, $csvContent);
                rename($csvFile, $filepath);
            }
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/upload/courier_service/exports/' . $filename
            ];
            
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Excel Exporter', 'exportCourierStats', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateCourierStatsCsv($couriers, $period)
    {
        $csv = '';
        
        $headers = [
            'Курьер',
            'Филиал',
            'Статус',
            'Всего заявок',
            'Доставлено',
            'Отказано',
            'В процессе',
            'Процент успешности',
            'Последняя активность'
        ];
        
        $csv .= $this->arrayToCsv($headers);
        
        foreach ($couriers as $courier) {
            $successRate = $courier['total_requests'] > 0 
                ? round(($courier['delivered'] / $courier['total_requests']) * 100, 2) 
                : 0;
            
            $row = [
                $courier['NAME'],
                $courier['BRANCH_NAME'] ?? '',
                $this->getCourierStatusText($courier['STATUS']),
                $courier['total_requests'],
                $courier['delivered'],
                $courier['rejected'],
                $courier['in_progress'],
                $successRate . '%',
                $courier['LAST_ACTIVITY'] ? $courier['LAST_ACTIVITY']->format('d.m.Y H:i') : ''
            ];
            
            $csv .= $this->arrayToCsv($row);
        }
        
        return $csv;
    }

    private function createCourierStatsXlsx($csvContent, $filepath)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $lines = explode("\n", trim($csvContent));
        $row = 1;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            $col = 1;
            
            foreach ($data as $cell) {
                $sheet->setCellValueByColumnAndRow($col, $row, $cell);
                $col++;
            }
            $row++;
        }
        
        $this->formatExcelSheet($sheet);
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
    }

    private function getStatusText($status)
    {
        $statuses = [
            'new' => 'Новая',
            'waiting' => 'Ожидает доставки',
            'delivered' => 'Доставлено',
            'rejected' => 'Отказано',
            'cancelled' => 'Отменено'
        ];
        
        return $statuses[$status] ?? $status;
    }

    private function getCallStatusText($status)
    {
        $statuses = [
            'not_called' => 'Не звонили',
            'successful' => 'Успешный',
            'failed' => 'Не удался'
        ];
        
        return $statuses[$status] ?? $status;
    }

    private function getCourierStatusText($status)
    {
        $statuses = [
            'active' => 'Активен',
            'inactive' => 'Неактивен',
            'on_delivery' => 'На доставке'
        ];
        
        return $statuses[$status] ?? $status;
    }
}