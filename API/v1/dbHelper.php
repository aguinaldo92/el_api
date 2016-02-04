<?php 
namespace el_api;

use PDO;
use PDOException;
use el_api;


class dbHelper {

    private function __construct() {
        
    }

    public static function select($pdo,$table, $columns, $where, $orwhere, $limit = 99999) {  // $table supporta le join: $rows = $db->select("ts_users LEFT JOIN ts_companies ON usr_co_id = co_id",array());
        try {
            $a = array();  // chiave associativa per l'array di condizioni where in AND and OR

            $w = "";
            $ow = "";
            foreach ($where as $key => $value) {
                $w .= " and " . $key . " like :" . $key;
                $a[":" . $key] = $value;
            }

            foreach ($orwhere as $key => $value) {
                $ow .= " or " . $key . " like :" . $key;
                $a[":" . $key] = $value;
            }

            $query = "SELECT " . $columns . " FROM " . $table . " WHERE 1=1 " . $w . " " . $ow . " LIMIT " . $limit;
            $stmt = $pdo->prepare($query);
            
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

    public function select2($pdo,$table, $columns, $where, $order) {
        try {
            $a = array();
            $w = "";
            foreach ($where as $key => $value) {
                $w .= " and " . $key . " like :" . $key;
                $a[":" . $key] = $value;
            }
            $stmt = $pdo->prepare("select " . $columns . " from " . $table . " where 1=1 " . $w . " " . $order);
            $stmt->execute($a);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) <= 0) {
                $response["status"] = "warning";
                $response["message"] = "No data found.";
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

    public static function insert($pdo,$table, $columnsArray, $requiredColumnsArray) {
        self::verifyRequiredParams($requiredColumnsArray,$columnsArray);
            
        try {
            $a = array();
            $c = "";
            $v = "";
            foreach ($columnsArray as $key => $value) {
                $c .= $key . ", ";
                $v .= ":" . $key . ", ";
                $a[":" . $key] = $value;
            }
            $c = rtrim($c, ', ');
            $v = rtrim($v, ', ');
            $stmt = $pdo->prepare("INSERT INTO $table($c) VALUES($v)");
            $stmt->execute($a);
            $affected_rows = $stmt->rowCount();
            $lastInsertId = $pdo->lastInsertId();
            
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

    public static function update($pdo,$table, $columnsArray, $where, $requiredColumnsArray) {
        self::verifyRequiredParams($columnsArray, $requiredColumnsArray);
        try {
            $a = array();
            $w = "";
            $c = "";
            foreach ($where as $key => $value) {
                $w .= " and " . $key . " = :" . $key;
                $a[":" . $key] = $value;
            }
            foreach ($columnsArray as $key => $value) {
                $c .= $key . " = :" . $key . ", ";
                $a[":" . $key] = $value;
            }
            $c = rtrim($c, ", ");

            $stmt = $pdo->prepare("UPDATE $table SET $c WHERE 1=1 " . $w);
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

    public static function delete($pdo,$table, $where) {
        if (count($where) <= 0) {
            $response["status"] = "warning";
            $response["message"] = "Delete Failed: At least one condition is required";
        } else {
            try {
                $a = array();
                $w = "";
                foreach ($where as $key => $value) {
                    $w .= " and " . $key . " = :" . $key;
                    $a[":" . $key] = $value;
                }
                $stmt = $pdo->prepare("DELETE FROM $table WHERE 1=1 " . $w);
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

   
    public static function verifyRequiredParams($requiredColumns,$inArray) {
        $error = false;
        $errorColumns = "";
        foreach ($requiredColumns as $field) {
          
            if (!isset($inArray->$field) || strlen(trim($inArray->$field)) <= 0) {
                $error = true;
                $errorColumns .= $field . ', ';
            }
        }

        if ($error) {
            $response = array();
            $response["status"] = "error";
            $response["message"] = 'Required field(s) ' . rtrim($errorColumns, ', ') . ' is missing or empty';
            UtilityClass::echoResponse(200, $response);
            exit;
        }
    }

}

?>
