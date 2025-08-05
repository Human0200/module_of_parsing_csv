<?php
namespace ParserCSV;
use Bitrix\Main\Loader;

class ParserCSV
{
    protected $skipFirstLine;
    
    /**
     * @param bool $skipFirstLine Пропускать ли первую строку (заголовки) по умолчанию
     */
    public function __construct(bool $skipFirstLine = false)
    {
        Loader::includeModule('leadspace.parsercsv');
        $this->skipFirstLine = $skipFirstLine;
    }

    /**
     * Парсит CSV файл с разделителем ";" и возвращает данные в формате JSON
     *
     * @param string $filePath Путь к CSV файлу
     * @param bool|null $skipFirstLine Пропускать ли первую строку (null - использовать значение из конструктора)
     * @return string JSON строка с массивом объектов
     * @throws \Exception Если файл не существует или не может быть прочитан
     */
    public function parseToJson(string $filePath, ?bool $skipFirstLine = null): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Файл не существует: " . $filePath);
        }

        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = explode("\n", $content);

        $data = [];
        $headers = ['id', 'date', 'amount', 'client', 'status'];

        // Используем переданное значение или значение из конструктора
        $skip = $skipFirstLine ?? $this->skipFirstLine;

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if ($skip && $index === 0) continue;
            
            $parts = str_getcsv($line, ';');
            $parts = array_pad($parts, 5, '');
            
            $data[] = [
                'id' => trim($parts[0]),
                'date' => trim($parts[1]),
                'amount' => trim($parts[2]),
                'client' => trim($parts[3]),
                'status' => trim($parts[4])
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Парсит CSV файл и сохраняет результат в JSON файл
     *
     * @param string $csvFilePath Путь к CSV файлу
     * @param string $jsonFilePath Путь для сохранения JSON файла
     * @param bool|null $skipFirstLine Пропускать ли первую строку
     * @return bool Успешность операции
     * @throws \Exception
     */
    public function parseToJsonFile(string $csvFilePath, string $jsonFilePath, ?bool $skipFirstLine = null): bool
    {
        $json = $this->parseToJson($csvFilePath, $skipFirstLine);
        return file_put_contents($jsonFilePath, $json) !== false;
    }

    /**
     * Парсит CSV файл и возвращает данные в виде массива
     *
     * @param string $filePath Путь к CSV файлу
     * @param bool|null $skipFirstLine Пропускать ли первую строку
     * @return array Массив с данными
     * @throws \Exception
     */
    public function parseToArray(string $filePath, ?bool $skipFirstLine = null): array
    {
        $json = $this->parseToJson($filePath, $skipFirstLine);
        return json_decode($json, true);
    }

    /**
     * Удаляет файл
     *
     * @param string $filePath Путь к файлу
     * @return bool Успешность операции
     */
        public function DeleteFile(string $filePath): bool
    {
        return unlink($filePath);
    }


    /**
     * Логирует данные в файл
     * @param mixed $data Данные для логирования
     * @param string $name Имя файла
     * @return bool
     */
        public static function FileLog($data, $name)
    {
        $logDir = __DIR__.'/../logs';

        if (! file_exists($logDir) && ! mkdir($logDir, 0755, true)) {
            throw new \RuntimeException("Не удалось создать директорию для логов: {$logDir}");
        }

        if (preg_match('/[\/\\\\]/', $name)) {
            throw new \InvalidArgumentException('Имя файла содержит недопустимые символы');
        }

        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            $jsonData = 'Не удалось преобразовать данные в JSON: '.json_last_error_msg();
        }

        $filePath = $logDir.'/'.$name.'.txt';
        $result = file_put_contents($filePath, $jsonData.PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Не удалось записать в файл лога: {$filePath}");
        }

        return true;
    }
}