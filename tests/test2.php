<?php
use Bitrix\Main\Loader;
use AgentFunctions\Agent;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
Loader::includeModule('leadspace.parsercsv');
$result = Agent::run(1);
echo '<pre>';
print_r($result);
echo '</pre>';