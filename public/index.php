<?php
namespace PressDo;

require dirname(__DIR__).'/vendor/autoload.php';

use PressDo\App\Helpers\{Config,Router,GeoIp,RouteControllerResolver,Csp};
use PressDo\App\Core\{Controller,Request,Response};

$request = Request::fromGlobals();

date_default_timezone_set(GeoIp::getTimezone($request->serverString('REMOTE_ADDR')) ?? Config::get('wiki.timezone'));
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if(!session_id())
    session_start();

$router = new Router();
$router->handleURI($request->serverString('REQUEST_URI'));

// initial
$pageClassName = RouteControllerResolver::resolve($router->uri_data);
if (
    $pageClassName === null
    || !class_exists($pageClassName)
    || !is_subclass_of($pageClassName, Controller::class)
) {
    Response::notFound();
}

$wiki = new $pageClassName();
$wiki->uri_data = $router->uri_data;

header('Content-Security-Policy: '.Csp::headerValue());

// Controller
$wiki->page = $wiki->makeData();

// call page
$getPage = $router->uri_data->page !== 'api';
$wiki->makePage($getPage);

$_SESSION = $wiki->session;
