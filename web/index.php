<?php

namespace web;

use Silex\Application;
use MongoDB\Client;
use app\WebHook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../app/Facebook.php';
require_once __DIR__.'/../app/WebHook.php';
require_once __DIR__.'/../app/Logger.php';
require_once __DIR__ . '/../vendor/autoload.php';
$mongo = require __DIR__ . '/../config/mongo.php';
$params = require __DIR__ . '/../config/params.php';

$client = new Client($mongo['uri']);
$db = $client->selectDatabase($mongo['db_name']);

$app = new Application();
$app->get('/', function () {
    return 'Hello World';
});

$app->match('/' . $params['webHook'], function(Request $request) use ($db, $params) {

    $response = new Response();

    if($hub = $request->query->get('hub_verify_token', false)) {
        if($hub == $params['hub_challenge']) {
            $response->setContent($request->query->get('hub_challenge'));
            $response->setStatusCode(200);
            return $response;
        }
    }

    new WebHook($request, $db);

    $response->setStatusCode(204);
    return $response;
});

$app->run();