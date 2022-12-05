<?php
require_once 'db-utils.php';
require_once 'http-utils.php';
require_once 'sessions.php';
require_once 'attendants.php';
require_once 'lots.php';

// Enable error reporting for debugging.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load the requested file, if it exists.
if (file_exists('.' . $_SERVER['REQUEST_URI'])) {
  return false;
}

session_start();
header('Content-type: application/json');

// dropTables();
initTables();
// initMockData();

$routes = [
  // Sessions routes...
  initRoute("POST", "#^/sessions/?(\?.*)?$#", "signIn"),
  initRoute("DELETE", "#^/sessions/?(\?.*)?$#", "signOut"),
  // Attendants routes...
  initRoute("POST", "#^/attendants/?(\?.*)?$#", "addAttendant"),
  // initRoute("GET", "#^/attendants/?(\?.*)?$#", "getAttendants"), // For testing...
  // initRoute("GET", "#^/attendants/(\w+)/?(\?.*)?$#", "getAttendant"), // For testing...
  initRoute("GET", "#^/attendants/(\w+)/reset_password/?(\?.*)?$#", "emailAttendantResetCode"),
  initRoute("PATCH", "#^/attendants/(\w+)/reset_password/?(\?.*)?$#", "resetAttendantPassword"),
  // Lots routes...
  initRoute("POST", "#^/attendants/(\w+)/lots/?(\?.*)?$#", "addAttendantLot"),
  initRoute("GET", "#^/attendants/(\w+)/lots/?(\?.*)?$#", "getAttendantLots"),
  initRoute("GET", "#^/lots/?(\?.*)?$#", "getLots"),
  // initRoute("GET", "#^/lots/(\w+)/?(\?.*)?$#", "getLot"), // For testing...
  initRoute("PUT", "#^/lots/(\w+)/?(\?.*)?$#", "updateLot"),
  initRoute("POST", "#^/lots/(\w+)/increment_vacancies/?(\?.*)?$#", "incrementLotVacancies"),
  initRoute("POST", "#^/lots/(\w+)/decrement_vacancies/?(\?.*)?$#", "decrementLotVacancies"),
  initRoute("POST", "#^/lots/(\w+)/attendants/?(\?.*)?$#", "addLotAttendant"),
  initRoute("POST", "#^/lots/(\w+)/attendants/?(\?.*)?$#", "getLotAttendants"),
  initRoute("DELETE", "#^/lots/(\w+)/attendants/?(\?.*)?$#", "deleteLotAttendant"),
];

// Initial request processing...
// If this is being served from a public_html folder, find the prefix.
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
if (count($matches) > 0) {
  $matches = [];
  preg_match("#/home/([^/]+)/public_html/(.*$)#", dirname(__FILE__), $matches);
  $prefix = "/~" . $matches[1] . "/" . $matches[2];
  $uri = preg_replace("#^" . $prefix . "/?#", "/", $_SERVER['REQUEST_URI']);
} else {
  $prefix = "";
  $uri = $_SERVER['REQUEST_URI'];
}

// Get the request method...
// PHP does not support requests outside of GET and POST well, so other requests
// are sent as POST requests with a "_method" param that sets the desired method.
$method = $_SERVER["REQUEST_METHOD"];
$params = $_GET;
if ($method == "POST") {
  $params = $_POST;
  if (array_key_exists("_method", $_POST))
    $method = strtoupper($_POST["_method"]);
}

// Parse the request and send it to the corresponding handler.
$foundMatchingRoute = false;
$match = [];
foreach ($routes as $route) {
  if ($method == $route["method"]) {
    preg_match($route["pattern"], $uri, $match);
    if ($match) {
      $foundMatchingRoute = true;
      die(json_encode($route["handler"]($uri, $match, $params)));
    }
  }
}
if (!$foundMatchingRoute) {
  error("No route found for: $method $uri");
}

/**
 * @param string $method The HTTP method for this route.
 * @param string $pattern The pattern the URI is matched against. 
 * @param string $handler The name of the handler function.
 * @return mixed An associative array with three keys pointing to the given arguments.
 */
function initRoute($method, $pattern, $handler)
{
  return [
    "method" => $method,
    "pattern" => $pattern,
    "handler" => $handler
  ];
}
?>