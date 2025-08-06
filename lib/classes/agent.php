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

            // Создаем массив для сопоставления ID и суммы из CSV
            $csvAmounts = [];
            foreach ($csvData as $item) {
                if (!empty($item['id']) && isset($item['amount'])) {
                    $id = ltrim(explode('-', $item['id'])[1], '0');
                    $csvAmounts[$id] = (float)$item['amount'];
                }
            }

            if (empty($csvAmounts)) {
                self::logError('Не найдено ID и сумм для обновления');
                return __METHOD__ . '(4);';
            }

            $batchSize = 500;
            $updatedCount = 0;
            $idBatches = array_chunk(array_keys($csvAmounts), $batchSize);

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

                while ($deal = $dbResult->Fetch()) {
                    $dealId = $deal['UF_CRM_1730208607985'];

                    // Если сумма в CSV отличается от суммы в сделке
                    if (isset($csvAmounts[$dealId]) && (float)$deal['OPPORTUNITY'] != $csvAmounts[$dealId]) {
                        $updateFields = ['OPPORTUNITY' => $csvAmounts[$dealId]];

                        $dealObj = new \CCrmDeal(false);
                        if ($dealObj->Update($deal['ID'], $updateFields)) {
                            $updatedCount++;
                        } else {
                            self::logError("Ошибка обновления сделки ID: {$deal['ID']}");
                        }
                    }
                }
            }

            $logMessage = "Обработано сделок: " . count($csvAmounts);
            $logMessage .= "Обновлено сумм: $updatedCount\n";

            // // Логируем несколько примеров обновлений
            // $examples = array_slice($csvAmounts, 0, 3, true);
            // foreach ($examples as $id => $amount) {
            //     $logMessage .= "Пример: ID $id - новая сумма $amount\n";
            // }

            $parser->FileLog($logMessage, 'deals');
            $parser->DeleteFile($filePath);

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
