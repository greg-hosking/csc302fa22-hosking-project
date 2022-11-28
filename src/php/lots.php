<?php
require_once 'db-utils.php';
require_once 'http-utils.php';
require_once 'attendants.php';

$dbh = initPDO();

/**
 * Handles `POST` requests made to `/attendants/:id/lots`. Requires the following keys in `$params`:
 * `address` `latitude`, `longitude`, `capacity`, `flatRate` (optional), `hourlyRate` (optional), `attendants`, `hours`, and `paymentOptions`. 
 * Attempts to create a lot with the given params under the given attendant ID.
 * If successful, emites a `201 Created` response with the location of the created lot.
 * If unsuccessful, emits a `400 Bad Request` or `404 Not Found` or `500 Internal Server Error` response.
 */
function addAttendantLot($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  if (
    !key_exists('address', $params) || !key_exists('latitude', $params) ||
    !key_exists('longitude', $params) || !key_exists('capacity', $params) ||
    !key_exists('hours', $params) || !key_exists('paymentOptions', $params)
  ) {
    badRequest('Missing required parameters.');
  }

  try {
    $statement = $dbh->prepare(
      'INSERT INTO Lots (address, latitude, longitude, capacity, vacancies, flatRate, hourlyRate) 
        VALUES (:address, :latitude, :longitude, :capacity, :vacancies, :flatRate, :hourlyRate)'
    );
    $statement->execute([
    ]);

    $lotID = $dbh->lastInsertId();

    created("lots/$lotID", [
      'id' => $lotID
    ]);

  } catch (PDOException $ex) {
    error($ex);
  }
}

/**
 * Handles `GET` requests made to `/attendants/:id/lots`.
 * Attempts to get all lots of the attendant with the given ID.
 * If successful, emits a `200 OK` response with the lots data.
 * If unsuccessful, emits a `404 Not Found` or `500 Internal Server Error` response or a `200 OK` response with an empty array if there are no lots.
 */
function getAttendantLots($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  try {
    $statement = $dbh->prepare(
      'SELECT 
        Lots.id as id, address, latitude, longitude, capacity, vacancies, flatRate, hourlyRate
        FROM Lot_Attendants
        INNER JOIN Lots
        ON Lot_Attendants.lotID = Lots.id
        INNER JOIN Attendants
        ON Lot_Attendants.attendantID = Attendants.id
        WHERE Attendants.id = :id'
    );
    $statement->execute([':id' => $id]);

    $lots = $statement->fetchAll(PDO::FETCH_ASSOC);
    // Each of these lots only contains the data in the Lots table. Each of the
    // lots should be expanded further with data from the related tables.
    $lotsExpanded = array();
    foreach ($lots as $lot) {
      array_push($lotsExpanded, getExpandedLot($lot));
    }

    success($lotsExpanded);

  } catch (PDOException $ex) {
    error("Error in getAttendantLots: $ex");
  }
}

/**
 * Handles `GET` requests made to `/lots`.
 * Attempts to get all lots.
 * If successful, emits a `200 OK` response with the lots data.
 * If unsuccessful, emits a `500 Internal Server Error` response or a `200 OK` response with an empty array if there are no lots.
 */
function getLots($uri, $matches, $params)
{
  global $dbh;

  $lots = getTableRows("Lots");
  if (is_null($lots)) {
    success([]);
  }

  // Each of these lots only contains the data in the Lots table. Each of the
  // lots should be expanded further with data from the related tables.
  $lotsExpanded = array();
  foreach ($lots as $lot) {
    array_push($lotsExpanded, getExpandedLot($lot));
  }

  success($lotsExpanded);
}

/**
 * Handles `GET` requests made to `/lots/:id`.
 * Attempts to get the lot with the given ID.
 * If successful, emits a `200 OK` response with the lot data.
 * If unsuccessful, emits a `404 Not Found` response.
 */
function getLot($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $lot = getTableRow("Lots", $id);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  // The lot only contains the data in the Lots table. The lot should be 
  // expanded further with data from the related tables.
  success(getExpandedLot($lot));
}

/**
 * @TODO: documentation
 */
function updateLot($uri, $matches, $params)
{

}

/**
 * @TODO: documentation
 */
function incrementLotVacancies($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $lot = getTableRow('Lots', $id);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  try {
    if ($lot['vacancies'] < $lot['capacity']) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET vacancies = :vacancies
          WHERE id = :id'
      );
      $statement->execute([
        ':vacancies' => $lot['vacancies'] + 1,
        ':id' => $id
      ]);
    }

    success();

  } catch (PDOException $ex) {
    error("Error in incrementLotVacancies: $ex");
  }
}

/**
 * @TODO: documentation
 */
function decrementLotVacancies($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $lot = getTableRow('Lots', $id);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  try {
    if ($lot['vacancies'] > 0) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET vacancies = :vacancies
          WHERE id = :id'
      );
      $statement->execute([
        ':vacancies' => $lot['vacancies'] - 1,
        ':id' => $id
      ]);
    }

    success();

  } catch (PDOException $ex) {
    error("Error in decrementLotVacancies: $ex");
  }
}

/**
 * @TODO: documentation
 */
function addLotAttendant($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $lot = getTableRow('Lots', $id);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  if (!key_exists('email', $params)) {
    badRequest('Attendant email required.');
  }

  $attendant = getAttendantByEmail($params['email']);
  if (is_null($attendant)) {
    notFound('Could not find an attendant with that email.');
  }

  try {
    $statement = $dbh->prepare(
      'INSERT INTO Lot_Attendants (lotID, attendantID)
        VALUES (:lotID, :attendantID)'
    );
    $statement->execute([
      ':lotID' => $id,
      ':attendantID' => $attendant['id']
    ]);

    success();

  } catch (PDOException $ex) {
    error("Error in deleteLotAttendant: $ex");
  }
}

/**
 * @TODO: documentation
 */
function deleteLotAttendant($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $lot = getTableRow('Lots', $id);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  if (!key_exists('email', $params)) {
    badRequest('Attendant email required.');
  }

  $attendant = getAttendantByEmail($params['email']);
  if (is_null($attendant)) {
    notFound('Could not find an attendant with that email.');
  }

  try {
    $statement = $dbh->prepare(
      'DELETE FROM Lot_Attendants
        WHERE lotID = :lotID AND attendantID = :attendantID'
    );
    $statement->execute([
      ':lotID' => $id,
      ':attendantID' => $attendant['id']
    ]);

    success();

  } catch (PDOException $ex) {
    error("Error in deleteLotAttendant: $ex");
  }
}

function getExpandedLot($lot)
{
  global $dbh;
  $lotExpanded = $lot;

  try {
    // Get lot attendants (excluding confidential information).
    $statement = $dbh->prepare(
      'SELECT 
        Lot_Attendants.attendantID as id, Attendants.email 
        FROM Lot_Attendants
        INNER JOIN Attendants
        ON Lot_Attendants.attendantID = Attendants.id
        WHERE lotID = :lotID'
    );
    $statement->execute([':lotID' => $lot['id']]);
    $attendants = $statement->fetchAll(PDO::FETCH_ASSOC);
    $lotExpanded['attendants'] = $attendants;

    // Get lot hours.
    $statement = $dbh->prepare(
      'SELECT
        day, openTime, closeTime
        FROM Lot_Hours
        WHERE lotID = :lotID'
    );
    $statement->execute([':lotID' => $lot['id']]);
    $hours = $statement->fetchAll(PDO::FETCH_ASSOC);
    $lotExpanded['hours'] = $hours;

    // Get lot payment options.
    $statement = $dbh->prepare(
      'SELECT
        name
        FROM Lot_Payment_Options
        WHERE lotID = :lotID'
    );
    $statement->execute([':lotID' => $lot['id']]);

    // Collapse the payment options from an associative array to an indexed array.
    $paymentOptions = $statement->fetchAll(PDO::FETCH_ASSOC);
    $paymentOptionsCollapsed = array();
    foreach ($paymentOptions as $option) {
      array_push($paymentOptionsCollapsed, $option['name']);
    }
    $lotExpanded['paymentOptions'] = $paymentOptionsCollapsed;

    return $lotExpanded;

  } catch (PDOException $ex) {
    error("Error in getExpandedLot: $ex");
  }
}
?>