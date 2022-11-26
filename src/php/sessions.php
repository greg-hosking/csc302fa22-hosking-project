<?php
require_once 'db-utils.php';
require_once 'http-utils.php';
require_once 'attendants.php';

$dbh = initPDO();

/**
 * Handles `POST` requests made to `/sessions`. Requires two keys in `$params`: email and password.
 * Attempts to sign the user in with the given `email` and `password`.
 * If successful, saves user ID, email, and signed in status in the session and emits a `200 OK` response.
 * If unsuccessful, emits a `400 Bad Request`, `401 Unauthorized`, or `404 Not Found` response.
 */
function signIn($uri, $matches, $params)
{
  if (!key_exists('email', $params) || !key_exists('password', $params)) {
    badRequest('Both an email and password are required.');
  }

  $attendant = getAttendantByEmail($params['email']);
  if (is_null($attendant)) {
    notFound('Could not find an account with that email address.');
  }

  if (password_verify($params['password'], $attendant['password'])) {
    $_SESSION['id'] = $attendant['id'];
    $_SESSION['email'] = $params['email'];
    $_SESSION['signedIn'] = true;

    success();
  }

  unauthorized('Incorrect email or password.');
}

/**
 * Handles `DELETE` requests made to `/sessions`.
 * Signs the user out by destroying the session, then emits a `200 OK` response.
 */
function signOut($uri, $matches, $params)
{
  session_destroy();
  success();
}

function requireSignedIn()
{
  if (!key_exists('signedIn', $_SESSION) && $_SESSION['signedIn']) {
    forbidden('You must be signed in to perform that action.');
  }
}
?>