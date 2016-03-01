<?php

use utility\UtilityClass;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$app->get('/', function () {
    echo "<h1>This can be the documentation entry point</h1>";
    echo "<p>This URL could also contain discovery"
    . " information in side the headers</p>";
    $status_code = 200;
    $response['status'] = "success";
    $response['message'] = 'got path /api/v1/';
    UtilityClass::echoResponse($status_code, $response);
});


$app->get('/courses', function () use ($app, $log, $dbHelperObject) {
    $log->debug("courses");
    $columns = $app->request->get('fields');
//    $columns = (($body->fields) ? $body->fields : "*");
    //$columns = "title,description,price,start_date,end_date,max_number_of_students";
    $table = 'v_courses';
    $wFields = $app->request->get('wFields');
    $wCond = $app->request->get('wCond');
    $oFields = $app->request->get('oFields');
    $oCond = $app->request->get('oCond');
    $operators = $app->request->get('op');
    $sort = $app->request->get('sort');
    $limit = $app->request->get('lim');
    $offset = $app->request->get('off');

    $response = $dbHelperObject->select($table, $columns, $wFields, $wCond, $oFields, $oCond, $operators, $sort, $limit, $offset);
    UtilityClass::echoResponse(200, $response);
});

$app->get('/courses/:id/lessons', function ($id) use ($app, $log, $dbHelperObject) {
    $log->debug("lessons of course $id");

    $columns = "title,date,start_at,finish_at,ID_course";
    $table = 'V_lesson_of_course';
    $where = array("ID_course" => "$id");
    $orwhere = array();
    $limit = 500;

    $response = $dbHelperObject->select($table, $columns, $where, $orwhere, $limit);
    UtilityClass::echoResponse(200, $response);
});


$app->get('/teachers', function() use ($app, $log, $dbHelperObject) {

    $columns = "ID,first_name,last_name,nickname,email,state,birthdate,gender,educational_qualification,image,is_active,created";
    $table = "v_teachers";
    $where = array();
    $orwhere = array();
    $limit = 9999;
    $response = $dbHelperObject->select($table, $columns, $where, $orwhere, $limit);
    echoResponse(200, $response);
});

$app->get('/teachers/:id', function($id) use ($app, $log, $dbHelperObject) {
    $log->debug("/teachers/:id");
    if (false === $id) {
        throw new Exception("Invalid contact ID");
    }

    $columns = "ID,first_name,last_name,nickname,email,state,birthdate,gender,educational_qualification,image,is_active,created";
    $table = "v_teachers";
    $where = array(
        "ID" => "$id"
    );
    $orwhere = array();
    $limit = 1;
    $result = $dbHelperObject->select($table, $columns, $where, $orwhere, $limit);
    echoResponse(200, $result);
});
