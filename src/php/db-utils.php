<?php
$dbh = initPDO();

/**
 * Checks if there is a directory named `www-data` in the home directory. If so, the database file is created or read from there. Otherwise, the database file is created or read in the current directory.
 * Returns a PDO instance for interacting with the database.
 */
function initPDO()
{
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

  return $dbh;
}

function initTables()
{
  global $dbh;

  try {
    // Attendants...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Attendants(
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        email TEXT UNIQUE NOT NULL, 
        password TEXT NOT NULL,
        resetCode INTEGER)'
    );

    // Lots...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lots(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        address TEXT NOT NULL,
        capacity INTEGER NOT NULL,
        vacancies INTEGER NOT NULL,
        flatRate REAL,
        hourlyRate REAL)'
    );

    // Lot Attendants...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Attendants(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        attendantID INTEGER,
        FOREIGN KEY (lotID) REFERENCES Lots (id),
        FOREIGN KEY (attendantID) REFERENCES Attendants (id))'
    );

    // Lot Hours...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Hours(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        day INTEGER NOT NULL,
        openTime TEXT NOT NULL,
        closeTime TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );

    // Lot Payment Methods...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Payment_Methods(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        method TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );

  } catch (PDOException $ex) {
    error($ex);
  }
}

/**
 * Drops all tables in the database. 
 * This should only be used during development to rid the database of test data.
 */
function dropTables()
{
  global $dbh;

  try {
    $dbh->exec('DROP TABLE IF EXISTS Attendants');
    $dbh->exec('DROP TABLE IF EXISTS Lots');
    $dbh->exec('DROP TABLE IF EXISTS Lot_Attendants');
    $dbh->exec('DROP TABLE IF EXISTS Lot_Hours');
    $dbh->exec('DROP TABLE IF EXISTS Lot_Payment_Methods');

  } catch (PDOException $ex) {
    error($ex);
  }
}

/**
 * Attempts to get all the rows from the given `$table`.
 * If successful, emits a `200 OK` respone including the table rows.
 * If unsuccessful, emits a `404 Not Found` or `500 Internal Error` reponse.
 */
function getTable($table)
{
  global $dbh;

  try {
    $statement = $dbh->prepare("select * from :table");
    $statement->execute([':table' => $table]);
    // fetchAll returns false if there is no table with the given name.
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
      success($rows);
    }
    notFound("Could not find table $table.");

  } catch (PDOException $ex) {
    error($ex);
  }
}

/**
 * Attempts to get the row from the given `$table` with the given `$id`.
 * If successful, emits a `200 OK` respone including the table row.
 * If unsuccessful, emits a `404 Not Found` or `500 Internal Error` reponse. 
 */
function getTableRow($table, $id)
{
  global $dbh;

  try {
    $statement = $dbh->prepare("select * from :table where id = :id");
    $statement->execute([
      ':table' => $table,
      ':id' => $id
    ]);
    // fetch returns false if there is no row with the given ID.
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      success($row);
    }
    notFound("Could not find row with ID $id in $table.");

  } catch (PDOException $ex) {
    error($ex);
  }
}
?>