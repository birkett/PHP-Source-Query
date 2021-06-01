<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use QueryTool\controllers\IndexController;

$page = new IndexController();

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    echo $page->postAction([
        'hostname' => filter_input(INPUT_POST, 'hostname', FILTER_SANITIZE_STRING),
        'port' => filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT),
        'engine' => filter_input(INPUT_POST, 'engine', FILTER_SANITIZE_STRING),
    ]);

    return;
}

echo $page->getAction();
