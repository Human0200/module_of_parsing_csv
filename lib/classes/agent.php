<?php

namespace AgentFunctions;

use Bitrix\Main\Loader;
use Exception;
use ParserCSV\ParserCSV;

class Agent
{
    public static function run(int $callback = 0)
    {
        if (!Loader::includeModule('leadspace.parsercsv') || !Loader::includeModule('crm')) {
            return __METHOD__ . '(1);';
        }

        date_default_timezone_set('Europe/Moscow');
        $currentHour = (int)date('G');

        if ($currentHour < 6 || $currentHour >= 7 && $callback !== 8) {
            return __METHOD__ . "(8);";
        } else {
            if ($currentHour < 8 || $currentHour >= 19) {
                return __METHOD__ . "(2);";
            }
        }

        $filePath = __DIR__ . '/../../bitrix.csv';

        try {
            $parser = new ParserCSV(false);
            $csvData = $parser->parseToArray($filePath);

            if (empty($csvData)) {
                self::logError('CSV файл пуст или не содержит данных');
                return __METHOD__ . '(3);';
            }

            // Создаем массив для сопоставления всех данных из CSV
            $csvDataMapped = [];
            foreach ($csvData as $item) {
                if (!empty($item['id'])) {
                    $id = ltrim(explode('-', $item['id'])[1], '0');
                    $csvDataMapped[$id] = [
                        'amount' => (float)$item['amount'],
                        'status_1c' => $item['status_1c'] ?? '',
                        'date' => $item['date'] ?? '',
                        'client' => $item['client'] ?? '',
                        'status' => $item['status'] ?? ''
                    ];
                }
            }

            if (empty($csvDataMapped)) {
                self::logError('Не найдено ID и данных для обновления');
                return __METHOD__ . '(4);';
            }

            $batchSize = 500;
            $updatedCount = 0;
            $idBatches = array_chunk(array_keys($csvDataMapped), $batchSize);

            foreach ($idBatches as $batchIds) {
                $dbResult = \CCrmDeal::GetListEx(
                    ['ID' => 'DESC'],
                    [
                        '@UF_CRM_1730208607985' => $batchIds,
                        'CHECK_PERMISSIONS' => 'N'
                    ],
                    false,
                    false,
                    ['ID', 'UF_CRM_1730208607985', 'OPPORTUNITY']
                );
                $foundDeals = 0;


                while ($deal = $dbResult->Fetch()) {
                    $dealId = $deal['UF_CRM_1730208607985'];
                    $foundDeals++;
                    // Проверяем, есть ли данные для этого ID в CSV
                    if (isset($csvDataMapped[$dealId])) {
                        $csvItem = $csvDataMapped[$dealId];

                        // Если сумма в CSV отличается от суммы в сделке
                        if ((float)$deal['OPPORTUNITY'] != $csvItem['amount'] || $csvItem['status_1c'] !== $deal['UF_CRM_1756198758969']) {
                            $updateFields = [
                                'OPPORTUNITY' => $csvItem['amount'],
                                'UF_CRM_1756198758969' => $csvItem['status_1c']
                            ];

                            $dealObj = new \CCrmDeal(false);
                            if ($dealObj->Update($deal['ID'], $updateFields)) {
                                $updatedCount++;
                            } else {
                                self::logError("Ошибка обновления сделки ID: {$deal['ID']}");
                            }
                        }
                    }
                }
                if ($foundDeals === 0) {
                    self::logError("Не найдено сделок для пакета ID: " . implode(', ', $batchIds));
                    continue; // или return в зависимости от логики
                }
            }

            $logMessage = "Обработано сделок: " . count($csvDataMapped) . "\n";
            $logMessage .= "Обновлено сумм: $updatedCount\n";

            // Логируем несколько примеров обновлений
            $examples = array_slice($csvDataMapped, 0, 3, true);
            foreach ($examples as $id => $data) {
                $logMessage .= "Пример: ID $id - новая сумма {$data['amount']}, статус 1C: '{$data['status_1c']}'\n";
            }

            $parser->FileLog($logMessage, 'deals');
            //$parser->DeleteFile($filePath);
            return __METHOD__ . '(5);';
        } catch (Exception $e) {
            self::logError($e->getMessage());
            return __METHOD__ . '(6);';
        }
    }

    protected static function logError(string $message): void
    {
        if (method_exists('ParserCSV\ParserCSV', 'FileLog')) {
            ParserCSV::FileLog($message, 'error');
        } else {
            AddMessage2Log($message, 'leadspace.parsercsv', 0, 'error');
        }
    }
}
