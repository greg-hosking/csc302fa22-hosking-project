<?php
/**
 * Emits a `200 OK` response along with the following JSON object:
 * `{ "success": true, "data": $data (if given) }`
 */
function success($data = null)
{
  http_response_code(200);
  $response = ['success' => true];
  if (!is_null($data)) {
    $response['data'] = $data;
  }
  die(json_encode($response));
}

/**
 * Emits a `201 Created` response with the `Location` field of the header set to `$uri`, along with the following JSON object:
 * `{ "success": true, "data": $data }`
 */
function created($uri, $data)
{
  http_response_code(201);
  header("Location: $uri");
  die(json_encode([
    'success' => true,
    'data' => $data
  ]));
}

/**
 * Emits a `400 Bad Request` response with the following JSON object:
 * `{ "success": false, "message": $message (if given) }`
 */
function badRequest($message = null)
{
  http_response_code(400);
  $response = ['success' => false];
  if (!is_null($message)) {
    $response['message'] = $message;
  }
  die(json_encode($response));
}

/**
 * Emits a `401 Unauthorized` response with the following JSON object:
 * `{ "success": false, "message": $message (if given) }`
 */
function unauthorized($message = null)
{
  http_response_code(401);
  $response = ['success' => false];
  if (!is_null($message)) {
    $response['message'] = $message;
  }
  die(json_encode($response));
}

/**
 * Emits a `403 Forbidden` response with the following JSON object:
 * `{ "success": false, "message": $message (if given) }`
 */
function forbidden($message = null)
{
  http_response_code(403);
  $response = ['success' => false];
  if (!is_null($message)) {
    $response['message'] = $message;
  }
  die(json_encode($response));
}

/**
 * Emits a `404 Not Found` response with the following JSON object:
 * `{ "success": false, "message": $message (if given) }`
 */
function notFound($message = null)
{
  http_response_code(404);
  $response = ['success' => false];
  if (!is_null($message)) {
    $response['message'] = $message;
  }
  die(json_encode($response));
}

/**
 * Emits a `500 Internal Server Error` response with the following JSON object:
 * `{ "success": false, "message": $message (if given) }`
 */
function error($message = null)
{
  http_response_code(500);
  $response = ['success' => false];
  if (!is_null($message)) {
    $response['message'] = $message;
  }
  die(json_encode($response));
}
?>