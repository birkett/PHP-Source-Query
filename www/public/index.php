<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use xPaw\SourceQuery\SourceQueryFactory;

$loader = new FilesystemLoader('../private/views');
$twig = new Environment($loader);

$timer = microtime(true);

$query = SourceQueryFactory::createSourceQuery();

$info = [];
$rules = [];
$players = [];
$exception = null;

try {
    $query->connect('localhost', 27015);
    //$query->setUseOldGetChallengeMethod(true); // Use this when players/rules retrieval fails on games like Starbound.

    $info = $query->getInfo();
    $players = $query->getPlayers();
    $rules = $query->getRules();
} catch (Exception $e) {
    $exception = $e;
} finally {
    $query->disconnect();
}

$timer = number_format(microtime(true) - $timer, 4, '.', '');

echo $twig->render('index.html.twig', [
    'timer' => $timer,
    'info' => $info,
    'players' => $players,
    'rules' => $rules,
    'exception' => $exception ? $exception->__toString() : null,
]);
