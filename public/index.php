<?php
namespace PressDo;

use PressDo\App\Helpers\{Config,Router,GeoIp,RouteControllerResolver,Csp};
use PressDo\App\Core\{Controller,Request,Response};
use PressDo\App\Http\Security\{CsrfTokenManager,SessionLifecycle};
use PressDo\App\Models\Member;

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::fromGlobals();

date_default_timezone_set(GeoIp::getTimezone($request->serverString('REMOTE_ADDR')) ?? Config::get('wiki.timezone'));

SessionLifecycle::start($request, (string) Config::get('wiki.canonical_url'));
SessionLifecycle::discardStaleIdentity($_SESSION, Member::validateSession(...));

header('Content-Security-Policy: ' . Csp::headerValue());
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (!in_array($request->method(), ['GET', 'HEAD', 'POST'], true)) {
    Response::methodNotAllowed('GET', 'HEAD', 'POST');
}
if ($request->isMethod('POST') && !(new CsrfTokenManager())->validateBrowserPost($_SESSION, $request)) {
    Response::text('Forbidden', 403);
}

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

// Controller
$wiki->page = $wiki->makeData();

// call page
$getPage = $router->uri_data->page !== 'api';
$wiki->makePage($getPage);

$_SESSION = $wiki->session;
