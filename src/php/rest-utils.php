<?php
/**
 * Emits a 200 OK response along with a JSON object with two fields:
 *   - success => true
 *   - data => the data that was passed in as `$data`
 * 
 * @param mixed $data The value to assign to the `data` field of the output.
 */
function success($data)
{
  http_response_code(200);
  $response = ['success' => true];
  if ($data) {
    $response['data'] = $data;
  }
  die(json_encode($response));
}

/**
 * Emits a 201 Created response along with a JSON object with two fields:
 *   - success => true
 *   - data => the data that was passed in as `$data`
 * 
 * @param string $uri The URI of the created resource.
 * @param mixed $data The value to assign to the `data` field of the output.
 */
function created($uri, $data)
{
  http_response_code(201);
  // Sets the `Location` field of the header to the given URI.
  header("Location: $uri");
  $response = ['success' => true];
  if ($data) {
    $response['data'] = $data;
  }
  die(json_encode($response));
}

/**
 * Emits a 404 Not Found response along with a JSON object with two fields:
 *   - success => false
 *   - error => the message that was passed in as `$error`
 * 
 * @param string $error The value to assign to the `error` field of the output.
 */
function notFound($error)
{
  http_response_code(404);
  die(
    json_encode(
      [
        'success' => false,
        'error' => $error
      ]
    )
    );
}

/**
 * Emits a 500 Internal Server Error response along with a JSON object with two fields:
 *   - success => false
 *   - error => the message that was passed in as `$error`
 * 
 * @param string $error The value to assign to the `error` field of the output.
 */
function error($error)
{
  http_response_code(500);
  die(
    json_encode(
      [
        'success' => false,
        'error' => $error
      ]
    )
    );
}

/**
 *  Creates a map with three keys pointing to the arguments passed in:
 *    - method => $method
 *    - pattern => $pattern
 *    - controller => $function
 * 
 * @param string method The HTTP method for this route.
 * @param string pattern The pattern the URI is matched against. 
 *                       Include groupings around IDs, etc.
 * @param string function The name of the function to call.
 * @return mixed A map with the key, value pairs described above.
 */
function makeRoute($method, $pattern, $function)
{
  return [
    "method" => $method,
    "pattern" => $pattern,
    "controller" => $function
  ];
}
?>