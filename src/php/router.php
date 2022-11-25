<?php
require 'rest-utils.php';

// Load the requested file, if it exists.
if (file_exists('.' . $_SERVER['REQUEST_URI'])) {
  return false;
}
// Enable error reporting for debugging.
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-type: application/json');
session_start();

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
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

createTables();
// createMockData();

// Set up REST API endpoints.
$routes = [
  // makeRoute("POST", "#^/sessions/?(\?.*)?$#", "signIn"),
  // makeRoute("DELETE", "#^/sessions/?(\?.*)?$#", "signOut"),

  // Attendants.
  // ADD USER -- POST
  // GET USER -- GET (when we make a user, generate password code)
  // UPDATE USER PASSWORD RESET CODE -- PATCH (when user is updated, generate new code)
  // UPDATE USER PASSWORD -- PATCH

  // Lots.
  // ADD LOT -- POST
  // GET LOTS -- GET
  // GET LOT -- GET
  // UPDATE LOT INFO -- PATCH
  // DELETE LOT -- DELETE

  // OTHER STUFF....

  makeRoute("POST", "#^/sessions/?(\?.*)?$#", "signin"),
  makeRoute("DELETE", "#^/sessions/?(\?.*)?$#", "signout"),
  // Attendants.
  makeRoute("POST", "#^/attendants/?(\?.*)?$#", "addAttendant"),
  // makeRoute("GET", "#^/attendants/(\w+)/password-reset-code/?(\?.*)?$#", "sendPasswordResetCode"),
  // Lots.
  // For testing directly in the browser...
  makeRoute("GET", "#^/router.php/lots/?(\?.*)?$#", "getLots"),
  makeRoute("GET", "#^/router.php/lots/(\w+)/?(\?.*)?$#", "getLot"),
  makeRoute("GET", "#^/lots/?(\?.*)?$#", "getLots"),


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

    // $dbh->exec('DROP TABLE IF EXISTS Attendants');



    // Attendants
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Attendants(
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        email TEXT UNIQUE NOT NULL, 
        password TEXT NOT NULL,
        password_reset_code TEXT)'
    );

    // TODO: create sessions table...

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



function authenticate($email, $password)
{
  global $dbh;

  // check that email and password are not null.
  if ($email == null || $password == null) {
    error('Bad request -- both a email and password are required');
  }

  // grab the row from Users that corresponds to $email
  try {
    $statement = $dbh->prepare('select password from Attendants ' .
      'where email = :email');
    $statement->execute([
      ':email' => $email,
    ]);

    // TODO: clean this up...
    $result = $statement->fetch();
    if ($result == false) {
      notFound('USER DOES NOT EXIST');
    }

    $passwordHash = $result[0];

    // user password_verify to check the password.
    if (password_verify($password, $passwordHash)) {
      return true;
    }
    error('Could not authenticate email and password.', 401);


  } catch (Exception $e) {
    error('User does not exist????: ' . $e);
  }
}

/**
 * Checks if the user is signed in; if not, emits a 403 error.
 */
function mustBeSignedIn()
{
  if (!(key_exists('signedin', $_SESSION) && $_SESSION['signedin'])) {
    error("You must be signed in to perform that action.", 403);
  }
}

/**
 * Log a user in. Requires the parameters:
 *  - username
 *  - password
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function signin($uri, $matches, $params)
{
  if (authenticate($params['email'], $params['password'])) {
    $_SESSION['signedin'] = true;
    $_SESSION['user-id'] = getUserByUsername($params['email'])['id'];
    $_SESSION['email'] = $params['email'];

    die(json_encode([
      'success' => true
    ]));
  } else {
    error('Username or password not found.', 401);
  }
}

/**
 * Logs the user out if they are logged in.
 * 
 * @param data An JSON object with these fields:
 *               - success -- whether everything was successful or not
 *               - error -- the error encountered, if any (only if success is false)
 */
function signout($data)
{
  session_destroy();
  die(json_encode([
    'success' => true
  ]));
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

function addAttendant($uri, $matches, $params)
{
  global $dbh;

  $saltedHash = password_hash($params['password'], PASSWORD_BCRYPT);
  $passwordResetCode = random_int(100000, 999999);

  try {
    $statement = $dbh->prepare('insert into Attendants(email, password, password_reset_code) ' .
      'values (:email, :password, :password_reset_code)');
    $statement->execute([
      ':email' => $params['email'],
      ':password' => $saltedHash,
      ':password_reset_code' => $passwordResetCode,
    ]);

    $attendantID = $dbh->lastInsertId();
    created("attendants/$attendantID", [
      'id' => $attendantID
    ]);

  } catch (PDOException $e) {
    http_response_code(400);
    die(json_encode([
      'success' => false,
      'error' => "There was an error adding the user: $e"
    ]));
  }
}

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



/**
 * Looks up a user by their username. 
 * 
 * @param $email string The username of the user to look up.
 * @return mixed The user's row in the Users table or null if no user is found.
 */
function getUserByUsername($email)
{
  global $dbh;
  try {
    $statement = $dbh->prepare("select * from Attendants where email = :email");
    $statement->execute([':email' => $email]);
    // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
    // most.
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row;

  } catch (PDOException $e) {
    return null;
  }
}

?>