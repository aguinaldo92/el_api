<?php namespace utility;

 class UtilityClass
{
  public static function echoResponse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
    // setting response content type to json
    $app->contentType('application/json');
    echo json_encode($response,JSON_PRETTY_PRINT);
}
}