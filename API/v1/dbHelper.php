<?php

namespace el_api_v1;

use PDO;
use PDOException;
use utility\UtilityClass;
use utility\Sanitize;
use utility\SanitizeNumber;

class dbHelper {

    private $db;
    private $sanitizer;

    //private $err;
    function __construct() {
        $this->sanitizer = new Sanitize();
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

    public function select($table, $columns, $where, $orwhere, $sort = null, $limit = 0, $offset = 0) {  // $table supporta le join: $rows = $db->select("ts_users LEFT JOIN ts_companies ON usr_co_id = co_id",array());
        try {
            $q = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS  WHERE table_name = 'v_courses'";
            $stmt = $this->db->query($q);
            $possible_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            $status_code = 500;
            $response =  "impossible get column names from table " . $e->getMessage();
            UtilityClass::echoResponse($status_code, $response);
        }


        try {
            $a = array();  // chiave associativa per l'array di condizioni where in AND and OR

            if ($limit) {
                $limit = $this->sanitizer->setInput(new SanitizeNumber($limit));
                $limit = " LIMIT " . $limit;
            } else {
                $limit = " ";
            }

            if ($limit && $offset) {
                $offset = $this->sanitizer->setInput(new SanitizeNumber($offset));
                $limited = " OFFSET " . $offset;
            } else {
                $offset = " ";
            }
            /*
             * Transform a string with comma in an array using comma as delimiter
             * then sanitize each element and remove spaces.
             * If an element start with - then that column will be sorted in descend way.
             */
            if ($sort) {
                
            } else {
                $order = " ";
            }
            if ($where) {
                $where_sanitized = $this->getWhere($where, $possible_columns);
                $w = ' WHERE 1=1 ';
                foreach ($where_sanitized as $key => $value) {
                    $w .= " and " . $key . " like :" . $key;
                    $a[":" . $key] = $value;
                }
            } else {
                $w = ' ';
            }
            if ($orwhere) {
                $orwhere_sanitized = $this->getWhere($orwhere, $possible_columns);
                foreach ($orwhere_sanitized as $key => $value) {
                    $ow .= " or " . $key . " like :" . $key;
                    $a[":" . $key] = $value;
                }
            } else {
                $ow = ' ';
            }


            $columns_array = explode(',', $columns);
            $columns_sanitized = array_map(function($s) {
                $s = filter_var($s, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                return trim($s);
            }, $columns_array);
            $column_intersect = array_intersect($possible_columns, $columns_sanitized); // controlla che i campi facciano effettivamente parte di quelli disponibili
            if (!(count($column_intersect) == count($columns_sanitized))) { // controllo che il numero di campi 
                $status_code = 422; // Unprocessable Entity
                $response = "Some fields in where or orwhere condition are mispelled";
                UtilityClass::echoResponse($status_code, $response);
            }
            $columns = implode(',', $columns_sanitized);

            $query = "SELECT " . $columns . " FROM " . $table . $w . $ow . $order . $limit . $offset;
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
            UtilityClass::echoResponse(204, $response);
            exit;
        }
    }

    private function getWhere($where, $columns_array) {
        $where_array = preg_split('/(=|,)/', $where);
        $where_sanitizied = array_map(function($s) {
            $s = filter_var($s, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
            return trim($s);
        }, $where_array);
        $field = array();
        $condition = array();
        foreach ($where_sanitizied as $k => $v) {
            if ($k % 2 == 0) {
                $field[] = $v;
            } else {
                $condition[] = $v;
            }
        }
        $field_intersect = array_intersect($field, $columns_array); // controlla che i campi facciano effettivamente parte di quelli disponibili
        if (!(count($field_intersect) == count($field))) {
            $status_code = 422; // Unprocessable Entity
            $response = "Some fields in where or orwhere condition are mispelled";
            UtilityClass::echoResponse($status_code, $response);
        }
    }

    private function getOrder($sort) {
        $order = " ORDER BY ";
        $sort = explode(',', $sort);
        $sort = array_map(function($s) {
            $s = filter_var($s, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
            return trim($s);
        }, $sort);
        foreach ($sort as $expr) {
            if ('-' == substr($expr, 0, 1)) {
                $order .= $expr . " DESC ,";
            } else {
                $order .= $expr . " ,";
            }
        }
        $order = substr($order, 0, (strlen($order) - 1));
        return $order;
    }



    private $output;

    public function setOutput(OutputInterface $outputType)
    {
        $this->output = $outputType;
    }

    public function loadOutput()
    {
        return $this->output->load();
    }

}
