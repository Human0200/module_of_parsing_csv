<?php

namespace AgentFunctions;

use Bitrix\Main\Loader;
use Exception;
use ParserCSV\ParserCSV;

class Agent
{

    public static function run()
    {


        $currentHour = (int)date('G');

        if ($currentHour < 8 || $currentHour >= 19) {
            return "\\AgentFunctions\\Agent::run();";
        }

        $filePath = __DIR__ . '/../../bitrix.csv';


        try {
            // Подключаем модули
            if (!Loader::includeModule('leadspace.parsercsv') || !Loader::includeModule('crm')) {
                self::logError('Не удалось подключить необходимые модули');
                return "\\AgentFunctions\\Agent::run();";
            }

            $parser = new ParserCSV(false);

            // Обработка CSV
            $csvData = $parser->parseToArray($filePath);
            if (empty($csvData)) {
                self::logError('CSV файл пуст или не содержит данных');
                return "\\AgentFunctions\\Agent::run();";
            }

            // Подготовка ID
            $allIds = array_unique(array_filter(array_map(function ($item) {
                return !empty($item['id']) ? ltrim(explode('-', $item['id'])[1], '0') : null;
            }, $csvData)));

            if (empty($allIds)) {
                self::logError('Не найдено ID для поиска');
                return "\\AgentFunctions\\Agent::run();";
            }

            // Настройки пагинации
            $batchSize = 500;
            $deals = [];

            // Разбиваем на пачки
            $idBatches = array_chunk($allIds, $batchSize);

            foreach ($idBatches as $batchIds) {
                $dbResult = \CCrmDeal::GetListEx(
                    ['ID' => 'DESC'],
                    [
                        '@UF_CRM_1730208607985' => $batchIds,
                        'CHECK_PERMISSIONS' => 'N'
                    ],
                    false,
                    false,
                    ['ID', 'TITLE', 'STAGE_ID', 'UF_CRM_1730208607985', 'OPPORTUNITY', 'DATE_CREATE']
                );

                if (!$dbResult) {
                    self::logError("Ошибка при обработке пачки ID: " . implode(',', $batchIds));
                    continue;
                }

                while ($deal = $dbResult->Fetch()) {
                    $dealId = $deal['UF_CRM_1730208607985'];
                    $deals[$dealId] = $deal;
                }
            }

            if (empty($deals)) {
                $parser->FileLog('Нет данных для обработки', 'deals');
                return "\\AgentFunctions\\Agent::run();";
            }

            $parser->DeleteFile($filePath);
            //$parser->FileLog('Скрипт выполен успешно', 'deals');

            return "\\AgentFunctions\\Agent::run();";
        } catch (Exception $e) {
            self::logError($e->getMessage());
            return "\\AgentFunctions\\Agent::run();";
        }
    }

    protected static function logError(string $message): void
    {
        // Используем либо вашу реализацию FileLog, либо стандартное логирование
        if (method_exists('ParserCSV\ParserCSV', 'FileLog')) {
            ParserCSV::FileLog($message, 'error');
        } else {
            AddMessage2Log($message, 'leadspace.parsercsv', 0, 'error');
        }
    }
}
