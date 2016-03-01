<?php

namespace el_api_v1;

use PDO;
use PDOException;
use utility\UtilityClass;
use utility\Sanitize;
use utility\SanitizeNumber;
use utility\SanitizeField;
use utility\SanitizeCond;

class dbHelper {

    private $db;
    private $sanitizer;

//private $err;
    function __construct($mode) {
        $this->sanitizer = new Sanitize();
        $this->mode = $mode;
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
        try {
            $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $response["status"] = "error";
            $response["message"] = 'Connection failed: ' . $e->getMessage();
            $response["data"] = null;
            $status_code = 503;

            UtilityClass::echoResponse($status_code, $response);
            exit('unable to connect to Database');
        }
    }

    public function select($table, $fields, $wFields, $wCond, $oFields, $oCond, $operators, $sort, $limit, $offset) {  // $table supporta le join: $rows = $db->select("ts_users LEFT JOIN ts_companies ON usr_co_id = co_id",array());
        try {
            $q = "SHOW COLUMNS FROM " . $table;
            $qstmt = $this->db->query($q);
            $possible_columns_name = $qstmt->fetchAll(PDO::FETCH_COLUMN);
//$possible_columns_type = $qstmt->fetchAll(PDO::FETCH_COLUMN, 1); // not working beacuse fetchall discard other columns after first call
        } catch (Exception $ex) {
            $status_code = 500;
            $response = "Unable to get column names from table " . $ex->getMessage();
            UtilityClass::echoResponse($status_code, $response);
        }


        $a = array();  // chiave associativa per l'array di condizioni where in AND and OR
        $ow = ' ';
        $w = ' ';
        $percent_sx = ' ';
        $percent_dx = ' ';
        $lim = ' ';
        $off = ' ';



        if ($fields) { // @todo check is string
            $columns_array = explode(',', $fields);
            $this->sanitizer->setInput(new SanitizeField);

            foreach ($columns_array as $value) {
                $columns_sanitized[] = $this->sanitizer->loadInput($value);
            }
            $column_intersect = array_intersect($columns_sanitized, $possible_columns_name); // controlla che i campi facciano effettivamente parte di quelli disponibili
            if (!(count($column_intersect) == count($columns_sanitized))) { // controllo che il numero di campi 
                $status_code = 422; // Unprocessable Entity
                $response["status"] = "error";
                $response["message"] = "Some requested fields are mispelled possible fields are : " . implode(',', $possible_columns_name);
                UtilityClass::echoResponse($status_code, $response);
            }
            $columns = implode(',', $column_intersect);
        } else {
            $columns = implode(',', $possible_columns_name);
        }


        if (is_numeric($limit)) {
            $this->sanitizer->setInput(new SanitizeNumber());
            $limit = $this->sanitizer->loadInput($limit);
            $lim = " LIMIT " . $limit;
            if (is_numeric($offset)) {
                $this->sanitizer->setInput(new SanitizeNumber());
                $offset = $this->sanitizer->loadInput($offset);
                $off = " OFFSET " . $offset;
            }
        }


        if ($sort) {
            $order = $this->getOrder($sort, $possible_columns_name);
        } else {
            $order = " ";
        }

        if ($operators) {

            $wFields = explode(',', $wFields);
            $wCond = explode(',', $wCond);
            $oFields = explode(',', $oFields);
            $oCond = explode(',', $oCond);
            $op_array = explode(',', $operators);

            if (count($wFields) == count($wCond) && (count($oFields) == count($oCond)) && count($op_array) == (count($oFields) + count($wFields))) {
                if ($wFields) {
                    $w = ' WHERE 1=1 ';
                    $w_fields_sanitized = $this->getWhereSanitized($wFields, $wCond, $possible_columns_name);
                    foreach ($w_fields_sanitized as $key => $value) {
                        $op = trim($op_array[0]);
                        if ($op === "s") {
                            $percent_sx = "%";
                        }
                        if ($op === "d") {
                            $percent_dx = "%";
                        }
                        $operator = $this->getOp($op);
                        $w .= " and " . $key . " $operator :w" . $key;
                        $a[":w" . $key] = $percent_sx . $value . $percent_dx;
                        array_splice($op_array, 0, 1);
                    }
                } else {
                    $w = ' ';
                }
                if ($oFields) {
                    $ow = "";
                    $orwhere_sanitized = $this->getWhereSanitized($oFields, $oCond, $possible_columns_name);
                    foreach ($orwhere_sanitized as $key => $value) {
                        $op = trim($op_array[0]);
                        if ($op === "s") {
                            $percent_sx = "%";
                        }
                        if ($op === "d") {
                            $percent_dx = "%";
                        }
                        $operator = $this->getOp($op);
                        $ow .= " or " . $key . " $operator :o" . $key;
                        $a[":o" . $key] = $percent_sx . $value . $percent_dx;
                        array_splice($op_array, 0, 1);
                    }
                } else {
                    
                }
            } else {
                $status_code = 422; // Unprocessable Entity
                $response['status'] = 'error';
                $response['message'] = "Some fields in where or orwhere conditions are missing or are missing one or more parameters";
                UtilityClass::echoResponse($status_code, $response);
            }
        }
        try {

            $query = "SELECT " . $columns . " FROM " . $table . $w . $ow . $order . $lim . $off;
            $response['query'] = $query;
            $response['a'] = $a;
            $stmt = $this->db->prepare($query);

            $stmt->execute($a);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) <= 0) {
                $response["status"] = "warning";
                $response["message"] = "No data found";
            } else {
                $response["status"] = "success";
                $response["message"] = "Data selected from database";
            }
            $response["data"] = $rows;
        } catch (PDOException $e) {
            $response["status"] = "error";
            $response["message"] = 'Select Failed: ' . $e->getMessage();
            $response["data"] = null;
        }
        return $response;
    }

    public function insert($table, $columnsArray, $requiredColumnsArray) {
        $this->verifyRequiredParams($requiredColumnsArray, $columnsArray);

        $table = trim(filter_var($table, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
        try {
            $a = array();
            $c = "";
            $v = "";
            if (is_array($columnsArray)) {
                foreach ($columnsArray as $key => $value) {
                    $key = trim(filter_var($key, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $value = trim(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $c .= $key . ", ";
                    $v .= ":" . $key . ", ";
                    $a[":" . $key] = $value;
                }
            }
            $c = rtrim($c, ', ');
            $v = rtrim($v, ', ');
            $stmt = $this->db->prepare("INSERT INTO $table($c) VALUES($v)");
            $stmt->execute($a);
            $affected_rows = $stmt->rowCount();
            $lastInsertId = $this->db->lastInsertId();

            $response["status"] = "success";
            $response["message"] = $affected_rows . " row inserted into database";
            $response["data"] = $lastInsertId;
        } catch (PDOException $e) {
            $response["status"] = "error";
            $response["message"] = 'Insert Failed: ' . $e->getMessage();
            $response["data"] = 0;
        }
        return $response;
    }

    public function update($table, $columnsArray, $where, $requiredColumnsArray) {
        self::verifyRequiredParams($columnsArray, $requiredColumnsArray);
        $table = trim(filter_var($table, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
        try {
            $a = array();
            $w = "";
            $c = "";
            if (is_array($where)) {
                foreach ($where as $key => $value) {
                    $key = trim(filter_var($key, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $value = trim(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $w .= " and " . $key . " = :" . $key;
                    $a[":" . $key] = $value;
                }
            }
            if (is_array($columnsArray)) {
                foreach ($columnsArray as $key => $value) {
                    $key = trim(filter_var($key, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $value = trim(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                    $c .= $key . " = :" . $key . ", ";
                    $a[":" . $key] = $value;
                }
            }
            $c = rtrim($c, ", ");

            $stmt = $this->db->prepare("UPDATE $table SET $c WHERE 1=1 " . $w);
            $stmt->execute($a);
            $affected_rows = $stmt->rowCount();
            if ($affected_rows <= 0) {
                $response["status"] = "warning";
                $response["message"] = "No row updated";
            } else {
                $response["status"] = "success";
                $response["message"] = $affected_rows . " row(s) updated in database";
            }
        } catch (PDOException $e) {
            $response["status"] = "error";
            $response["message"] = "Update Failed: " . $e->getMessage();
        }
        return $response;
    }

    public function delete($table, $where) {
        $table = trim(filter_var($table, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
        if (count($where) <= 0) {
            $response["status"] = "warning";
            $response["message"] = "Delete Failed: At least one condition is required";
        } else {
            try {
                $a = array();
                $w = "";
                if (is_array($where)) {
                    foreach ($where as $key => $value) {
                        $key = trim(filter_var($key, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                        $value = trim(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW));
                        $w .= " and " . $key . " = :" . $key;
                        $a[":" . $key] = $value;
                    }
                }
                $stmt = $this->db->prepare("DELETE FROM $table WHERE 1=1 " . $w);
                $stmt->execute($a);
                $affected_rows = $stmt->rowCount();
                if ($affected_rows <= 0) {
                    $response["status"] = "warning";
                    $response["message"] = "No row deleted";
                } else {
                    $response["status"] = "success";
                    $response["message"] = $affected_rows . " row(s) deleted from database";
                }
            } catch (PDOException $e) {
                $response["status"] = "error";
                $response["message"] = 'Delete Failed: ' . $e->getMessage();
            }
        }
        return $response;
    }

    private function verifyRequiredParams($requiredColumns, $inArray) {
        $error = false;
        $errorColumns = "";
        if (is_array($requiredColumns) && is_object($inArray)) {
            foreach ($requiredColumns as $field) {
                if (!isset($inArray->$field) || strlen(trim($inArray->$field)) <= 0) {
                    $error = true;
                    $errorColumns .= $field . ', ';
                }
            }
        }
        if ($error) {
            $response = array();
            $response["status"] = "error";
            $response["message"] = 'Required field(s) ' . rtrim($errorColumns, ', ') . ' is missing or empty';
            UtilityClass::echoResponse(422, $response);
        }
    }

    private function getWhereSanitized($fields, $cond, $columns_array) {
        $this->sanitizer->setInput(new SanitizeCond());
        foreach ($cond as $value) {
            $cond_sanitized[] = $this->sanitizer->loadInput($value);
        }
        $this->sanitizer->setInput(new SanitizeField());
        foreach ($fields as $value) {
            $fields_sanitized[] = $this->sanitizer->loadInput($value);
        }
        $fields_intersected = array_intersect($fields_sanitized, $columns_array); // controlla che i campi facciano effettivamente parte di quelli disponibili
        if (count($fields_intersected) !== count($fields_sanitized)) {
            $status_code = 422; // Unprocessable Entity
            $response['status'] = 'error';
            $response['message'] = "Some fields in where or orwhere condition are mispelled";
            UtilityClass::echoResponse($status_code, $response);
        } elseif (count($fields_intersected) !== count($cond_sanitized)) {
            $status_code = 422; // Unprocessable Entity
            $response['status'] = 'error';
            $response['message'] = "Some fields in where or orwhere condition are missing";
            UtilityClass::echoResponse($status_code, $response);
        } else {
            $where_sanitized = array_combine($fields_intersected, $cond_sanitized);
            return $where_sanitized;
        }
    }

    private function getOrder($sort, $columns_array) {
        $this->sanitizer->setInput(new SanitizeField());
        $sort_array = explode(',', $sort);
        $i = 0;
        foreach ($sort_array as $expr) {
            if ('-' == substr($expr, 0, 1)) {
                $mixDesc_array[$i] = $this->sanitizer->loadInput($expr);
                $order_array[] = $mixDesc_array[$i] . " DESC ";
            } else {
                $order_array[$i] = $this->sanitizer->loadInput($expr);
                $mixAsc_array[] = $order_array[$i];
            }
            $i++;
        }
        $array_toCheck = array_merge($mixDesc_array, $mixAsc_array);
        $sort_intersected = array_intersect($array_toCheck, $columns_array); // controlla che i campi facciano effettivamente parte di quelli disponibili
        if (count($sort_intersected) !== count($order_array)) {
            $status_code = 422; // Unprocessable Entity
            $response['status'] = 'error';
            $response['message'] = "Some fields in sort condition are mispelled";
            UtilityClass::echoResponse($status_code, $response);
        } else {
            $order = implode(',', $order_array); // unisco i pezzi con la virgola 
            $order = " ORDER BY " . $order;
            return $order;
        }
    }

    private function getOp($op_element) {
        switch ($op_element) {

            case "l":
                $op = "<";
                break;

            case "le":
                $op = "<=";
                break;

            case "g":
                $op = ">";
                break;

            case "ge":
                $op = ">=";
                break;

            case "s":
                $op = "like";
                break;

            case "d":
                $op = "like";
                break;

            case "sd":
                $op = "like";
                break;

            default:
                $op = "=";
                break;
        }
        return $op;
    }

}
