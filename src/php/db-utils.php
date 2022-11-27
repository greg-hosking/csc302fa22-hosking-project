<?php
require_once 'http-utils.php';

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
      'CREATE TABLE IF NOT EXISTS Attendants (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        email TEXT UNIQUE NOT NULL, 
        password TEXT NOT NULL,
        resetCode INTEGER)'
    );

    // Lots...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        address TEXT NOT NULL,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL,
        capacity INTEGER NOT NULL,
        vacancies INTEGER NOT NULL,
        flatRate REAL,
        hourlyRate REAL)'
    );

    // Lot Attendants...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Attendants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        attendantID INTEGER,
        FOREIGN KEY (lotID) REFERENCES Lots (id),
        FOREIGN KEY (attendantID) REFERENCES Attendants (id))'
    );

    // Lot Hours...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Hours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        day INTEGER NOT NULL,
        openTime TEXT NOT NULL,
        closeTime TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );

    // Lot Payment Options...
    $dbh->exec(
      'CREATE TABLE IF NOT EXISTS Lot_Payment_Options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lotID INTEGER,
        name TEXT NOT NULL,
        FOREIGN KEY (lotID) REFERENCES Lots (id))'
    );

  } catch (PDOException $ex) {
    error("Error in initTables: $ex");
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
    $dbh->exec('DROP TABLE IF EXISTS Lot_Payment_Options');

  } catch (PDOException $ex) {
    error("Error in dropTables: $ex");
  }
}

/**
 * Attempts to get all the rows from the given `$table`.
 * If successful, returns an array containing all rows from the table.
 * If unsuccessful, returns null.
 */
function getTableRows($table)
{
  global $dbh;

  try {
    $statement = $dbh->prepare("SELECT * FROM $table");
    $statement->execute();
    // fetchAll returns an empty array if there is no table with the given name.
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 0) {
      return $rows;
    }
    return null;

  } catch (PDOException $ex) {
    error("Error in getTable: $ex");
  }
}

/**
 * Attempts to get the row from the given `$table` with the given `$id`.
 * If successful, returns the row from the table.
 * If unsuccessful, returns null. 
 */
function getTableRow($table, $id)
{
  global $dbh;

  try {
    $statement = $dbh->prepare("SELECT * FROM $table WHERE id = :id");
    $statement->execute([':id' => $id]);
    // fetch returns false if there is no row with the given ID.
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      return $row;
    }
    return null;

  } catch (PDOException $ex) {
    error("Error in getTableRow: $ex");
  }
}

/**
 * Create some mock data in the database. 
 * This should only be used during development to test interactions with the API.
 */
function initMockData()
{
  global $dbh;

  try {
    $password = password_hash("password", PASSWORD_BCRYPT);

    $statement = $dbh->prepare(
      'INSERT INTO Attendants (email, password)
        VALUES 
        ("zahiraconor@example.com", :password), 
        ("vladimirrobert@example.com", :password),
        ("manusyakiv@example.com", :password),
        ("mordechaisvit@example.com", :password),
        ("nuralorri@example.com", :password)'
    );
    $statement->execute([':password' => $password]);

    $dbh->exec(
      'INSERT INTO Lots (address, latitude, longitude, capacity, vacancies, flatRate, hourlyRate)
        VALUES 
        ("746 Rosewood Ct, Faribault, MN 55021", 44.271680, -93.264140, 42, 14, 20, 5),
        ("2373 Gandy St, St. Louis, MO 63101", 38.630940, -90.192860, 82, 29, 30, 4),
        ("4621 James Ave, Wichita, KS 67214", 37.693700, -97.300350, 35, 23, 40, 6.5)'
    );

    $dbh->exec(
      'INSERT INTO Lot_Attendants (lotID, attendantID)
        VALUES
        (1, 1),
        (1, 2), 
        (2, 3),
        (2, 4),
        (2, 5),
        (3, 4),
        (3, 1)'
    );

    $dbh->exec(
      'INSERT INTO Lot_Hours (lotID, day, openTime, closeTime)
        VALUES
        (1, 1, "08:00", "20:00"),
        (1, 2, "08:00", "20:00"),
        (1, 3, "08:00", "20:00"),
        (1, 4, "08:00", "20:00"),
        (1, 5, "08:00", "23:59"),
        (1, 6, "08:00", "23:59"),
        
        (2, 1, "06:00", "20:00"),
        (2, 2, "06:00", "20:00"),
        (2, 3, "06:00", "20:00"),
        (2, 4, "06:00", "20:00"),
        (2, 5, "06:00", "20:00"),
        (2, 6, "06:00", "20:00"),
        (2, 7, "06:00", "12:00"),

        (3, 5, "16:00", "23:59"),
        (3, 6, "00:00", "23:59"),
        (3, 7, "00:00", "23:59")'
    );

    $dbh->exec(
      'INSERT INTO Lot_Payment_Options (lotID, name)
        VALUES
        (1, "Cash"),
        (1, "Credit"),
        (1, "PayPal"),
        (1, "Cash App"),
        (2, "Cash"),
        (3, "Cash"),
        (3, "Credit"),
        (3, "Apple Pay")'
    );

  } catch (PDOException $ex) {
    error("Error in initMockData: $ex");
  }
}
?>