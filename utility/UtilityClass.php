<?php

namespace utility;

class UtilityClass {

    public static function echoResponse($status_code, $response) {
        $app = \Slim\Slim::getInstance();
        // Http response code
        $app->status($status_code);
        // setting response content type to json
        $app->contentType('application/json');
        echo json_encode($response, JSON_PRETTY_PRINT); // slim automaticcally append echo() to response body
        if (preg_match("/(4|5)\d\d/", $status_code)) {
            $app->halt($status_code); // se l'errore è di tipo 4xx o 5xx ferma l'esecuzione del codice
        }
    }

}
