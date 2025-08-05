<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('leadspace.parsercsv', [
    'Settings24\GlobalSettings' => 'lib/classes/settings.php',
    'ParserCSV\ParserCSV' => 'lib/classes/parserCSV.php',
    'AgentFunctions\Agent' => 'lib/classes/agent.php',

]);
?>