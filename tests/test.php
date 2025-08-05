<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
use Bitrix\Main\Loader;

try {
    // Подключаем модули
    if (!Loader::includeModule('leadspace.parsercsv') || !Loader::includeModule('crm')) {
        throw new Exception('Не удалось подключить необходимые модули');
    }

    $parser = new ParserCSV\ParserCSV(false);
    
    // Обработка CSV
    $csvData = $parser->parseToArray('bitrix.csv');
    if (empty($csvData)) {
        throw new Exception('CSV файл пуст или не содержит данных');
    }

    // Подготовка ID (уникальные, очищенные)
    $allIds = array_unique(array_filter(array_map(function($item) {
        return !empty($item['id']) ? ltrim(explode('-', $item['id'])[1], '0') : null;
    }, $csvData)));

    if (empty($allIds)) {
        throw new Exception('Не найдено ID для поиска');
    }

    // Настройки пагинации
    $batchSize = 500; // Размер пачки
    $deals = [];
    $totalProcessed = 0;
    
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
            error_log("Ошибка при обработке пачки ID: " . implode(',', $batchIds));
            continue;
        }

        while ($deal = $dbResult->Fetch()) {
            $dealId = $deal['UF_CRM_1730208607985'];
            $deals[$dealId][] = $deal;
        }
        
        $totalProcessed += count($batchIds);
        echo "Обработано: {$totalProcessed} из " . count($allIds) . " ID<br>";
        flush(); // Принудительно выводим данные
    }

    // Вывод результатов
    echo '<pre>';
    echo "Всего ID: " . count($allIds) . "\n";
    echo "Найдено сделок: " . array_reduce($deals, fn($carry, $item) => $carry + count($item), 0) . "\n";
    
    if (!empty($deals)) {
        // Выводим только первые 5 результатов для примера
        print_r(array_slice($deals, 0, 5));
        echo "\n... (показаны только первые 5 результатов)";
    } else {
        echo "Сделки не найдены";
    }
    echo '</pre>';

} catch (Exception $e) {
    die('<pre>Ошибка: ' . $e->getMessage() . '</pre>');
}