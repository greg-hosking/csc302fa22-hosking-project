<?php
require_once 'db-utils.php';
require_once 'http-utils.php';
require_once 'sessions.php';
require_once 'attendants.php';

$dbh = initPDO();

/**
 * Handles `POST` requests made to `/attendants/:id/lots`. Requires the following keys in `$params`:
 * `name`, `address`, `latitude`, `longitude`, `capacity`, `flatRate` (optional), `hourlyRate` (optional), `hours`, and `paymentOptions`. 
 * Attempts to create a lot with the given params under the given attendant ID.
 * If successful, emits a `201 Created` response with the location of the created lot.
 * If unsuccessful, emits a `400 Bad Request`, `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function addAttendantLot($uri, $matches, $params)
{
  global $dbh;
  $attendantID = $matches[1];

  $attendant = getTableRow('Attendants', $attendantID);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  requireSignedIn();
  // Make sure that the signed in attendant is the one making this request.
  if ($_SESSION['id'] != $attendantID) {
    forbidden('You are not allowed to create a lot for another attendant.');
  }

  if (
    !key_exists('name', $params) || !key_exists('address', $params) ||
    !key_exists('latitude', $params) || !key_exists('longitude', $params) ||
    !key_exists('capacity', $params) || !key_exists('hours', $params) ||
    !key_exists('paymentOptions', $params)
  ) {
    badRequest('Missing required parameters.');
  }

  try {
    $dbh->beginTransaction();

    $flatRate = null;
    if (key_exists('flatRate', $params)) {
      $flatRate = $params['flatRate'];
    }
    $hourlyRate = null;
    if (key_exists('hourlyRate', $params)) {
      $hourlyRate = $params['hourlyRate'];
    }

    // Create the base lot with the given information.
    $statement = $dbh->prepare(
      'INSERT INTO Lots (name, address, latitude, longitude, capacity, vacancies, flatRate, hourlyRate) 
        VALUES (:name, :address, :latitude, :longitude, :capacity, :vacancies, :flatRate, :hourlyRate)'
    );
    $statement->execute([
      ':name' => $params['name'],
      ':address' => $params['address'],
      ':latitude' => $params['latitude'],
      ':longitude' => $params['longitude'],
      ':capacity' => $params['capacity'],
      ':vacancies' => $params['capacity'],
      ':flatRate' => $flatRate,
      ':hourlyRate' => $hourlyRate
    ]);

    $lotID = $dbh->lastInsertId();

    // Add the given attendant as the first attendant for the lot.
    $statement = $dbh->prepare(
      'INSERT INTO Lot_Attendants (lotID, attendantID)
        VALUES (:lotID, :attendantID)'
    );
    $statement->execute([
      ':lotID' => $lotID,
      ':attendantID' => $attendant['id']
    ]);

    // Add the given hours to the lot.
    foreach ($params['hours'] as $hours) {
      if (
        !key_exists('day', $hours) || !key_exists('openTime', $hours) ||
        !key_exists('closeTime', $hours)
      ) {
        $dbh->rollBack();
        badRequest('Each item in hours must include a day, openTime, and closeTime.');
      }

      $statement = $dbh->prepare(
        'INSERT INTO Lot_Hours (lotID, day, openTime, closeTime)
          VALUES (:lotID, :day, :openTime, :closeTime)'
      );
      $statement->execute([
        ':lotID' => $lotID,
        ':day' => $hours['day'],
        ':openTime' => $hours['openTime'],
        ':closeTime' => $hours['closeTime']
      ]);
    }

    // Add the given payment methods to the lot.
    foreach ($params['paymentOptions'] as $option) {
      $statement = $dbh->prepare(
        'INSERT INTO Lot_Payment_Options (lotID, name)
          VALUES (:lotID, :name)'
      );
      $statement->execute([
        ':lotID' => $lotID,
        ':name' => $option
      ]);
    }

    $dbh->commit();
    created("lots/$lotID", [
      'id' => $lotID
    ]);

  } catch (PDOException $ex) {
    $dbh->rollBack();
    error("Error in addAttendantLot: $ex");
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
  $attendantID = $matches[1];

  $attendant = getTableRow('Attendants', $attendantID);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  try {
    $statement = $dbh->prepare(
      'SELECT 
        Lots.id as id, name, address, latitude, longitude, capacity, vacancies, flatRate, hourlyRate
        FROM Lot_Attendants
        INNER JOIN Lots
        ON Lot_Attendants.lotID = Lots.id
        INNER JOIN Attendants
        ON Lot_Attendants.attendantID = Attendants.id
        WHERE Attendants.id = :id'
    );
    $statement->execute([':id' => $attendantID]);

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

  $lots = getTableRows('Lots');
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
  $lotID = $matches[1];

  $lot = getTableRow('Lots', $lotID);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  // The lot only contains the data in the Lots table. The lot should be 
  // expanded further with data from the related tables.
  success(getExpandedLot($lot));
}

/**
 * Handles `PUT` requests made to `/lots/:id`. The following keys in `$params` are optional:
 * `name`, `address`, `latitude`, `longitude`, `capacity`, `flatRate`, `hourlyRate`, `hours`, and `paymentOptions`. 
 * Attempts to update the given existing lot with the given params under the given attendant ID.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `400 Bad Request`, `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function updateLot($uri, $matches, $params)
{
  global $dbh;
  $lotID = $matches[1];

  $attendant = getTableRow('Lots', $lotID);
  if (is_null($attendant)) {
    notFound('Could not find a lot with that ID.');
  }

  requireSignedInLotAttendant($lotID);

  try {
    $dbh->beginTransaction();
    // If name was passed in, update it in the given lot.
    if (key_exists('name', $params)) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET name = :name
          WHERE id = :id'
      );
      $statement->execute([
        ':name' => $params['name'],
        ':id' => $lotID
      ]);
    }

    // If all of address, latitude, and longitude were passed in, update them
    // in the given lot.
    if (
      key_exists('address', $params) && key_exists('latitude', $params) &&
      key_exists('longitude', $params)
    ) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET address = :address,
            latitude = :latitude,
            longitude = :longitude,
          WHERE id = :id'
      );
      $statement->execute([
        ':address' => $params['address'],
        ':latitude' => $params['latitude'],
        ':longitude' => $params['longitude'],
        ':id' => $lotID
      ]);
    }

    // If capacity was passed in, update it and adjust the vacancies in the given lot.
    if (key_exists('capacity', $params)) {
      $statement = $dbh->prepare(
        'SELECT capacity, vacancies FROM Lots
          WHERE id = :id'
      );
      $statement->execute([
        ':id' => $lotID
      ]);
      $lot = $statement->fetch(PDO::FETCH_ASSOC);

      $prevCapacity = $lot['capacity'];
      $prevVacancies = $lot['vacancies'];
      $newCapacity = $params['capacity'];
      $capacityDelta = $newCapacity - $prevCapacity;

      $statement = $dbh->prepare(
        'UPDATE Lots
          SET capacity = :capacity,
            vacancies = :vacancies
          WHERE id = :id'
      );
      $statement->execute([
        ':capacity' => $newCapacity,
        ':vacancies' => max($prevVacancies + $capacityDelta, 0),
        ':id' => $lotID
      ]);
    }

    // If flatRate was passed in, update it in the given lot.
    if (key_exists('flatRate', $params)) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET flatRate = :flatRate
          WHERE id = :id'
      );
      $statement->execute([
        ':flatRate' => $params['flatRate'],
        ':id' => $lotID
      ]);
    }

    // If hourlyRate was passed in, update it in the given lot.
    if (key_exists('hourlyRate', $params)) {
      $statement = $dbh->prepare(
        'UPDATE Lots
              SET hourlyRate = :hourlyRate
              WHERE id = :id'
      );
      $statement->execute([
        ':hourlyRate' => $params['hourlyRate'],
        ':id' => $lotID
      ]);
    }

    // If hours were passed in, delete all hours for the given lot and replace them.
    if (key_exists('hours', $params)) {
      $statement = $dbh->prepare(
        'DELETE FROM Lot_Hours
          WHERE lotID = :lotID'
      );
      $statement->execute([
        ':lotID' => $lotID
      ]);

      foreach ($params['hours'] as $hours) {
        if (
          !key_exists('day', $hours) || !key_exists('openTime', $hours) ||
          !key_exists('closeTime', $hours)
        ) {
          $dbh->rollBack();
          badRequest('Each item in hours must include a day, openTime, and closeTime.');
        }

        $statement = $dbh->prepare(
          'INSERT INTO Lot_Hours (lotID, day, openTime, closeTime)
            VALUES (:lotID, :day, :openTime, :closeTime)'
        );
        $statement->execute([
          ':lotID' => $lotID,
          ':day' => $params['day'],
          ':openTime' => $params['openTime'],
          ':closeTime' => $params['closeTime']
        ]);
      }
    }

    // If paymentOptions were passed in, delete all payment options for the given
    // lot and replace them.
    if (key_exists('paymentOptions', $params)) {
      $statement = $dbh->prepare(
        'DELETE FROM Lot_Payment_Options
          WHERE lotID = :lotID'
      );
      $statement->execute([
        ':lotID' => $lotID
      ]);

      foreach ($params['paymentOptions'] as $option) {
        $statement = $dbh->prepare(
          'INSERT INTO Lot_Payment_Options (lotID, name)
          VALUES (:lotID, :name)'
        );
        $statement->execute([
          ':lotID' => $lotID,
          ':name' => $option
        ]);
      }
    }

    $dbh->commit();
    success();

  } catch (PDOException $ex) {
    $dbh->rollBack();
    error("Error in updateLot: $ex");
  }
}

/**
 * Handles `POST` requests made to `/lots/:id/increment_vacancies`. 
 * Attempts to increment the vacancies for the given lot by 1.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function incrementLotVacancies($uri, $matches, $params)
{
  global $dbh;
  $lotID = $matches[1];

  $lot = getTableRow('Lots', $lotID);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  requireSignedInLotAttendant($lotID);

  try {
    // Increment the lot vacancies, if less than the capacity.
    if ($lot['vacancies'] < $lot['capacity']) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET vacancies = :vacancies
          WHERE id = :id'
      );
      $statement->execute([
        ':vacancies' => $lot['vacancies'] + 1,
        ':id' => $lotID
      ]);
    }

    success();

  } catch (PDOException $ex) {
    error("Error in incrementLotVacancies: $ex");
  }
}

/**
 * Handles `POST` requests made to `/lots/:id/decrement_vacancies`. 
 * Attempts to decrement the vacancies for the given lot by 1.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function decrementLotVacancies($uri, $matches, $params)
{
  global $dbh;
  $lotID = $matches[1];

  $lot = getTableRow('Lots', $lotID);
  if (is_null($lot)) {
    notFound('Could not find a lot with that ID.');
  }

  requireSignedInLotAttendant($lotID);

  try {
    // Decrement the lot vacancies, if greater than zero.
    if ($lot['vacancies'] > 0) {
      $statement = $dbh->prepare(
        'UPDATE Lots
          SET vacancies = :vacancies
          WHERE id = :id'
      );
      $statement->execute([
        ':vacancies' => $lot['vacancies'] - 1,
        ':id' => $lotID
      ]);
    }

    success();

  } catch (PDOException $ex) {
    error("Error in decrementLotVacancies: $ex");
  }
}

/**
 * Handles `POST` requests made to `/lots/:id/attendants`. Requires one key in `$params`: `email`.
 * Attempts to add the given attendant to the list of attendants for the given lot.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `400 Bad Request`, `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function addLotAttendant($uri, $matches, $params)
{
  global $dbh;
  $lotID = $matches[1];

  $lot = getTableRow('Lots', $lotID);
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

  requireSignedInLotAttendant($lotID);

  try {
    $statement = $dbh->prepare(
      'INSERT INTO Lot_Attendants (lotID, attendantID)
        VALUES (:lotID, :attendantID)'
    );
    $statement->execute([
      ':lotID' => $lotID,
      ':attendantID' => $attendant['id']
    ]);

    success();

  } catch (PDOException $ex) {
    error("Error in addLotAttendant: $ex");
  }
}

/**
 * Handles `DELETE` requests made to `/lots/:id/attendants`. Requires one key in `$params`: `email`.
 * Attempts to delete the given attendant from the list of attendants for the given lot, or delete the lot and all related information if they are the only attendant.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `400 Bad Request`, `403 Forbidden`, `404 Not Found` or `500 Internal Server Error` response.
 */
function deleteLotAttendant($uri, $matches, $params)
{
  global $dbh;
  $lotID = $matches[1];

  $lot = getTableRow('Lots', $lotID);
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

  requireSignedInLotAttendant($lotID);

  try {
    // Make sure that the given attendant is an attendant for the lot.
    $statement = $dbh->prepare(
      'SELECT * FROM Lot_Attendants
        WHERE lotID = :lotID AND attendantID = :attendantID'
    );
    $statement->execute([
      ':lotID' => $lotID,
      ':attendantID' => $attendant['id']
    ]);
    if (count($statement->fetchAll(PDO::FETCH_ASSOC)) == 0) {
      notFound('Could not find an attendant with that email for the given lot.');
    }

    // Count the attendants for the lot.
    $statement = $dbh->prepare(
      'SELECT * FROM Lot_Attendants
        WHERE lotID = :lotID'
    );
    $statement->execute([
      ':lotID' => $lotID
    ]);
    $attendantCount = count($statement->fetchAll(PDO::FETCH_ASSOC));

    $dbh->beginTransaction();
    // If the lot is attended only by the given attendant, delete the lot and all
    // related information (except from the list of lot attendants).
    if ($attendantCount == 1) {
      $statement = $dbh->prepare(
        'DELETE FROM Lots
          WHERE id = :id'
      );
      $statement->execute([
        ':id' => $lotID
      ]);

      $statement = $dbh->prepare(
        'DELETE FROM Lot_Hours
          WHERE lotID = :lotID'
      );
      $statement->execute([
        ':lotID' => $lotID
      ]);

      $statement = $dbh->prepare(
        'DELETE FROM Lot_Payment_Options
          WHERE lotID = :lotID'
      );
      $statement->execute([
        ':lotID' => $lotID
      ]);
    }
    // Then, simply delete the given attendant from the list of attendants for the lot.
    $statement = $dbh->prepare(
      'DELETE FROM Lot_Attendants
        WHERE lotID = :lotID AND attendantID = :attendantID'
    );
    $statement->execute([
      ':lotID' => $lotID,
      ':attendantID' => $attendant['id']
    ]);

    $dbh->commit();
    success();

  } catch (PDOException $ex) {
    $dbh->rollBack();
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