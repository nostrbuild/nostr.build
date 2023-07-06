<?php

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Summary of jsonResponse
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $status
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 * @return Psr\Http\Message\ResponseInterface
 * Utility function to return a JSON response
 */
function jsonResponse(Response $response, string $status, string $message, $data, int $statusCode = 200): Response
{
  $responseBody = [
    'status' => $status,
    'message' => $message,
    'data' => $data,
  ];
  $response->getBody()->write(json_encode($responseBody));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

/**
 * Summary of jsonResponse
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $status
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 * @return Psr\Http\Message\ResponseInterface
 * Utility function to return a JSON response
 */
function uppyResponse(Response $response, string $status, string $message, $data, int $statusCode = 200): Response
{
  $responseBody = ($status === 'success') ? createSuccessResponse($data) : createErrorResponse($message);

  $response->getBody()->write(json_encode($responseBody));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

function createSuccessResponse($data): array
{
  if (!is_array($data)) {
    return [];
  }

  $tmp = [];
  foreach ($data as $file) {
    $tmp[] = [
      "id" => $file['sha256'],
      "url" => $file['url'],
      "name" => $file['name'],
      "type" => $file['mime'],
      "size" => $file['size'],
    ];
  }
  return count($tmp) == 1 ? $tmp[0] : $tmp;
}

function createErrorResponse(string $message): array
{
  return [
    "error" => $message,
  ];
}