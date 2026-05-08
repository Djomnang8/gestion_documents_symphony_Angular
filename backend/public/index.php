<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '1';
$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'dev';

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
