<?php
namespace PressDo;

require '../vendor/autoload.php';

use PressDo\App\Helpers\{Config,Router,GeoIp,RouteControllerResolver};
use PressDo\App\Core\Controller;

date_default_timezone_set(GeoIp::getTimezone(Controller::getIpAddr()) ?? Config::get('wiki.timezone'));
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if(!session_id())
    session_start();

$router = new Router();
$router->handleURI($_SERVER['REQUEST_URI']);

// initial
$pageClassName = RouteControllerResolver::resolve($router->uri_data);
if (
    $pageClassName === null
    || !class_exists($pageClassName)
    || !is_subclass_of($pageClassName, Controller::class)
) {
    http_response_code(404);
    exit('Not Found');
}

$wiki = new $pageClassName();
$wiki->uri_data = $router->uri_data;

header("Content-Security-Policy: default-src 'self'; img-src 'self' *.theseed.io secure.gravatar.com www.google-analytics.com 
http://tn-skr2.smilevideo.jp data:; media-src *; child-src *; script-src 'self' 'unsafe-eval' 'unsafe-inline' www.google.com www.gstatic.com 
www.googletagmanager.com www.google-analytics.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com; connect-src 'self'; font-src 'self' 
fonts.gstatic.com data:;");

// Controller
$wiki->page = $wiki->makeData();

// call page
$getPage = $router->uri_data->page !== 'api';
$wiki->makePage($getPage);

$_SESSION = $wiki->session;
