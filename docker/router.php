<?php

/**
 * Router do servidor embutido do PHP (`php -S`).
 *
 * Serve o arquivo estático quando ele existe em public/, e delega todo o
 * resto ao front controller do Laravel — que é o que o mod_rewrite/nginx
 * faria em produção.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists(__DIR__.'/../public'.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once __DIR__.'/../public/index.php';
