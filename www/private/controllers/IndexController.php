<?php

declare(strict_types=1);

namespace QueryTool\controllers;

use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use UnexpectedValueException;
use xPaw\SourceQuery\EngineType;
use xPaw\SourceQuery\SourceQueryFactory;

class IndexController
{
    public function getAction(): string
    {
        return $this->render(['engines' => EngineType::ALL_ENGINES]);
    }

    public function postAction(array $postVars): string
    {
        $data = [];

        try {
            $data['formData'] = $postVars;
            $data['engines'] = EngineType::ALL_ENGINES;

            if (!is_string($postVars['hostname'])) {
                throw new UnexpectedValueException('Hostname must be a string');
            }

            if (!is_int($postVars['port'])) {
                throw new UnexpectedValueException('Port must be a number');
            }

            if (!is_string($postVars['engine'])) {
                throw new UnexpectedValueException('Engine must be a string');
            }

            $data = array_merge(
                $data,
                $this->queryData($postVars['hostname'], $postVars['port'], $postVars['engine']),
            );
        } catch (Throwable $exception) {
            $data['exception'] = $exception;
        }

        return $this->render($data);
    }

    private function queryData(string $hostName, int $port, string $engine): array
    {
        $timer = microtime(true);

        switch ($engine) {
            case EngineType::GOLDSOURCE:
                $query = SourceQueryFactory::createGoldSourceQuery();

                break;

            case EngineType::SOURCE:
            default:
                $query = SourceQueryFactory::createSourceQuery();
        }

        $info = [];
        $rules = [];
        $players = [];
        $exception = null;

        try {
            $query->connect($hostName, $port);
            //$query->setUseOldGetChallengeMethod(true); // Use this when players/rules retrieval fails on games like Starbound.

            $info = $query->getInfo();
            $players = $query->getPlayers();
            $rules = $query->getRules();
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            $query->disconnect();
        }

        $timer = number_format(microtime(true) - $timer, 4, '.', '');

        return [
            'timer' => $timer,
            'info' => $info,
            'players' => $players,
            'rules' => $rules,
            'exception' => $exception,
        ];
    }

    private function render(array $data): string
    {
        $loader = new FilesystemLoader('../private/views');
        $twig = new Environment($loader);

        return $twig->render('index.html.twig', $data);
    }
}
