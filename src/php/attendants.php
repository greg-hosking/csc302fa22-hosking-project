<?php
require_once 'db-utils.php';
require_once 'http-utils.php';

$dbh = initPDO();

/**
 * Handles `POST` requests made to `/attendants`. Requires two keys in `$params`: email and password.
 * Attempts to create a user with the given `email` and `password`.
 * If successful, emites a `201 Created` response with the location of the created user.
 * If unsuccessful, emits a `400 Bad Request` or `404 Not Found` response.
 */
function addAttendant($uri, $matches, $params)
{
  global $dbh;

  if (!key_exists('email', $params) || !key_exists('password', $params)) {
    badRequest('Both an email and password are required.');
  }

  $saltedHash = password_hash($params['password'], PASSWORD_BCRYPT);

  try {
    $statement = $dbh->prepare('INSERT INTO Attendants(email, password) ' .
      'VALUES (:email, :password)');
    $statement->execute([
      ':email' => $params['email'],
      ':password' => $saltedHash,
    ]);

    $attendantID = $dbh->lastInsertId();
    created("attendants/$attendantID", [
      'id' => $attendantID
    ]);

  } catch (PDOException $ex) {
    error($ex);
  }
}

/**
 * Handles `GET` requests made to `/attendants`. Optional key in `$params`: email.
 * Attempts to get all attendants or the attendant with the given email (if present).
 * If successful, emits a `200 OK` response with the attendant data (excluding confidential info).
 * If unsuccessful, emits a `404 Not Found` response or a `200 OK` response with an empty array if there are no attendants.
 */
function getAttendants($uri, $matches, $params)
{
  // If the request has an email query param, get the attendant with that email.
  if (key_exists('email', $params)) {
    $attendant = getAttendantByEmail($params['email']);
    if (is_null($attendant)) {
      notFound('Could not find an account with that email address.');
    }
    // Remove confidential information before returning the attendant.
    unset($attendant['password']);
    unset($attendant['resetCode']);
    success($attendant);
  }

  // Otherwise, get all attendants.
  $attendants = getTableRows('Attendants');
  if (is_null($attendants)) {
    success([]);
  }

  // Remove confidential information before returning the attendants.
  $attendantsPublic = array();
  foreach ($attendants as $attendant) {
    $attendantPublic = [
      'id' => $attendant['id'],
      'email' => $attendant['email']
    ];
    array_push($attendantsPublic, $attendantPublic);
  }
  success($attendantsPublic);
}

/**
 * Handles `GET` requests made to `/attendants/:id`.
 * Attempts to get the attendant with the given ID.
 * If successful, emits a `200 OK` response with the attendant data (excluding confidential info).
 * If unsuccessful, emits a `404 Not Found` response.
 */
function getAttendant($uri, $matches, $params)
{
  $id = intval($matches[1]);
  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  // Remove confidential information before returning the attendant.
  unset($attendant['password']);
  unset($attendant['resetCode']);
  success($attendant);
}

/**
 * Handles `GET` requests made to `/attendants/:id/lots`.
 * Attempts to get all lots of the attendant with the given ID.
 * If successful, emits a `200 OK` response with the lots data (excluding confidential info).
 * If unsuccessful, emits a `404 Not Found` response or a `200 OK` response with an empty array if there are no lots.
 */
function getAttendantLots($uri, $matches, $params)
{
  $id = $matches[1];
  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  // Remove confidential information before returning the attendant.
  unset($attendant['password']);
  unset($attendant['resetCode']);
  success($attendant);

  // TODO: get attendant lots...
}

/**
 * Handles `GET` requests made to `/attendants/:id/reset_password`.
 * Attempts to send an email to the attendant with the given ID including their password reset code.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `404 Not Found` or `500 Internal Server Error` response.
 */
function emailAttendantResetCode($uri, $matches, $params)
{
  global $dbh;
  $id = intval($matches[1]);

  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  try {
    $resetCode = random_int(100000, 999999);
    $statement = $dbh->prepare(
      'UPDATE Attendants 
        SET resetCode = :resetCode 
        WHERE id = :id'
    );
    $statement->execute([
      ':resetCode' => $resetCode,
      ':id' => $id
    ]);

    $email = $attendant['email'];
    // TODO: send email to given email including the reset code...
    //       for now, send the reset code back in the response.
    success([
      "resetCode" => $resetCode
    ]);

  } catch (PDOException $ex) {
    error("Error in emailAttendantResetCode: $ex");
  }
}

/**
 * Handles `PATCH` requests made to `/attendants/:id/reset_password`. Requires three keys in `$params`: email, resetCode, and password.
 * Attempts to reset the password of the attendant with the given ID.
 * If successful, emits a `200 OK` response.
 * If unsuccessful, emits a `400 Bad Request`, `401 Unauthorized`, `404 Not Found` or `500 Internal Server Error` response.
 */
function resetAttendantPassword($uri, $matches, $params)
{
  global $dbh;
  $id = $matches[1];

  $attendant = getTableRow('Attendants', $id);
  if (is_null($attendant)) {
    notFound('Could not find an account with that ID.');
  }

  if (!key_exists('email', $params) || !key_exists('resetCode', $params) || !key_exists('password', $params)) {
    badRequest('An email, reset code, and password are required.');
  }

  if ($params['email'] != $attendant['email'] || $params['resetCode'] != $attendant['resetCode']) {
    unauthorized('Incorrect email or reset code.');
  }

  try {
    // Replace the old password with the hash of the given new password.
    $statement = $dbh->prepare(
      'UPDATE Attendants 
        SET password = :password 
        WHERE id = :id'
    );
    $statement->execute([
      ':password' => password_hash($params['password'], PASSWORD_BCRYPT),
      ':id' => $id
    ]);

    // Clear the reset code so that it can only be used once.
    $statement = $dbh->prepare(
      'UPDATE Attendants 
        SET resetCode = :resetCode 
        WHERE id = :id'
    );
    $statement->execute([
      ':resetCode' => null,
      ':id' => $id
    ]);

    success();

  } catch (PDOException $ex) {
    error("Error in resetAttendantPassword: $ex");
  }
}

function getAttendantByEmail($email)
{
  global $dbh;

  try {
    $statement = $dbh->prepare('SELECT * FROM Attendants WHERE email = :email');
    $statement->execute([':email' => $email]);
    // fetch returns false if there is no attendant with the given email.
    $attendant = $statement->fetch(PDO::FETCH_ASSOC);
    if ($attendant) {
      return $attendant;
    }
    return null;

  } catch (PDOException $ex) {
    error($ex);
  }
}
?>