<?php
require 'rest-utils.php';

// Load the requested file, if it exists. (For running PHP in development mode.)
if (file_exists('.' . $_SERVER['REQUEST_URI'])) {
  return false;
}

// Enable debugging.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set up the PDO instance for the database.
$dbName = 'data.db';
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
$homeDir = count($matches) > 1 ? $matches[1] : '';
$dataDir = "/home/$homeDir/www-data";
if (!file_exists($dataDir)) {
  $dataDir = __DIR__;
}
$dbh = new PDO("sqlite:$dataDir/$dbName");
// Set PDO instance to raise exceptions when errors are encountered.
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-type: application/json');
createTables();
// createMockData();

// Routes.
$routes = [
  // Books.
  // makeRoute("POST", "#^/books/?(\?.*)?$#", "addBook"),
  // makeRoute("GET", "#^/books/?(\?.*)?$#", "getBooks"),
  // makeRoute("GET", "#^/books/(\w+)/?(\?.*)?$#", "getBook"),
  // // Patrons -- the handlers for these need to be re-vamped.
  // makeRoute("POST", "#^/patrons/?(\?.*)?$#", "addPatron"),
  // makeRoute("GET", "#^/patrons/?(\?.*)?$#", "getPatrons"),
  // makeRoute("GET", "#^/patrons/(\w+)/?(\?.*)?$#", "getPatron"),
  // Attendants.

  // Lots.
  // For testing directly in the browser...
  // makeRoute("GET", "#^/router.php/lots/?(\?.*)?$#", "getLots"),
  makeRoute("GET", "#^/lots/?(\?.*)?$#", "getLots"),


];

// Initial request processing.
// If this is being served from a public_html folder, find the prefix (e.g., 
// /~jsmith/path/to/dir).
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

// Get the request method; PHP doesn't handle non-GET or POST requests
// well, so we'll mimic them with POST requests with a "_method" param
// set to the method we want to use.
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
      die(json_encode($route["controller"]($uri, $match, $params)));
    }
  }
}

if (!$foundMatchingRoute) {
  error("No route found for: $method $uri");
}

////////////////////////////////////////////////////////////////////////////////
// FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

/**
 * Create these tables if they don't already exist:
 * 
 *  Attendants
 *  Lots
 *  Lot Attendants
 *  Lot Operation Hours
 *  Lot Payment Methods
 */
function createTables()
{
  global $dbh;

  try {
    // Attendants
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Attendants(
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        email TEXT NOT NULL, 
        encryptedPassword TEXT NOT NULL)'
    );

    // Lots
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lots(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        address TEXT NOT NULL,
        capacity INTEGER NOT NULL,
        availability INTEGER NOT NULL,
        flatRate REAL,
        hourlyRate REAL)'
    );

    // Lot Attendants
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS LotAttendants(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        attendantID INTEGER,
        FOREIGN KEY (lotID) REFERENCES Lots (id),
        FOREIGN KEY (attendantID) REFERENCES Attendants (id))'
    );

    // Lot Operation Hours
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS LotOperationHours(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        day INTEGER NOT NULL,
        openTime TEXT NOT NULL,
        closeTime TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );

    // Lot Payment Methods
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS LotPaymentMethods(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        method TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );
  } catch (PDOException $e) {
    error("There was an error creating the tables: $e");
  }
}

function createMockData()
{
  global $dbh;

  try {
    $dbh->exec(
      'INSERT INTO Lots(address, capacity, availability, flatRate, hourlyRate) 
        VALUES ("128 Humphrey St, Swampscott MA", 30, 15, 20, 5),
        ("11 Hardy St, Salem MA", 30, 15, 20, 5),
        ("376 Hale St, Beverly MA", 30, 15, 20, 5)'
    );

  } catch (PDOException $e) {
    error("There was an error adding the lots: $e");
  }
}


////////////////////////////////////////////////////////////////////////////////
// Handlers
////////////////////////////////////////////////////////////////////////////////
function getLots($uri, $matches, $data)
{
  global $prefix;
  getTable("Lots", $prefix);
}


////////////////////////////////////////////////////////////////////////////////
// Helper funcitons
////////////////////////////////////////////////////////////////////////////////

/**
 * Outupts the row of the given table that matches the given id.
 */
function getTableRow($table, $data, $uri)
{
  global $dbh;

  try {
    $statement = $dbh->prepare("select * from $table where id = :id");
    $statement->execute([':id' => $data['id']]);
    // Use fetch for getting the single row.
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      notFound($data['id']);
    }

    $row['uri'] = $uri;
    success($row);

  } catch (PDOException $e) {
    error("There was an error fetching rows from table $table: $e");
  }
}

/**
 * Outputs all the values of a database table. 
 * 
 * @param table The name of the table to display.
 */
function getTable($table, $uriPrefix)
{
  global $dbh;
  try {
    $statement = $dbh->prepare("select * from $table");
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['uri'] = "$uriPrefix/${row['id']}";
    }
    success($rows);

  } catch (PDOException $e) {
    error("There was an error fetching rows from table $table: $e");
  }
}



?>