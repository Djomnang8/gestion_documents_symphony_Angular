<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Désactiver le Runtime pour éviter les erreurs
$_SERVER['APP_RUNTIME'] = 'Symfony\\Component\\HttpKernel\\Kernel';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '1';
$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'dev';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
