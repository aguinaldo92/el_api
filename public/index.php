<?php

require_once dirname(__FILE__) . '/../bootstrap.php';

//require_once '../API/v1/dbHelper.php'; l'autoloading di dbHelper funziona bene, proviamo
// a farlo per tutti i files e implementare le api che ci sono già.
// Inserire la creazione dei token tramite psr e poi jwt tuupla
use el_api\dbHelper;
use el_api\UtilityClass;
use el_api\passwordHash;
use \Firebase\JWT\JWT;

// API group
$app->get('/', function() {
    // deattivare il token
    $response["status"] = "info";
    $response["message"] = "You have get the Index";
    $status_code = 200;

    UtilityClass::echoResponse($status_code, $response);
    echo '<br>';
    $format = "H:i:s";
    echo date($format);
    echo '<br>';
});


$app->group('/api', function () use ($app, $log) {

    // Version group
    $app->group('/v1', function () use ($app, $log) {

        $app->get('/', function () {
            echo "<h1>This can be the documentation entry point</h1>";
            echo "<p>This URL could also contain discovery"
            . " information in side the headers</p>";
            $status_code = 200;
            $response['status'] = "success";
            $response['message'] = 'got path /api/v1/';
            UtilityClass::echoResponse($status_code, $response);
        });


        $app->get('/login', function () use ($app) {
            echo "<h1>Login</h1>";
            echo "<p>All'inizio del debugging si ottiene una chiamata get</p>";

            $log = $app->container['log'];
            $log->debug('get /login');
            //$app->response->status(200); non serve, però potrebbe essere una cosa utile
        });

        $app->post('/login', function() use ($app) {
            $aParams = $app->request->params();
            $r = json_decode($app->request->getBody());
            //print_r($r);

            dbHelper::verifyRequiredParams(array('email', 'password'), $r->user);
            $response = array();
            $password = $r->user->password;
            $email = $r->user->email;
            $columns = "ID,nickname,password,email,created,is_a_student,is_a_teacher";
            $table = "user";
            $limit = "1";
            $where = array("email" => "$email");
            $orwhere = array("nickname" => "$email");
            $pdo = $app->container['PDO'];
            $result = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            $user = $result['data'][0];

            if ($user != NULL) {
                if (passwordHash::check_password($user['password'], $password)) {
                    switch ($user['is_a_teacher'] + $user['is_a_student']) {
                        case 1:
                            $userType = "student";
                            break;
                        case 2:
                            $userType = "teacher";
                            break;
                        case 3:
                            $userType = "student and teacher";
                            break;
                        default :
                            $userType = "undefined";
                    }

                    $response['status'] = "success";
                    $response['message'] = 'Logged in successfully.';
                    $response['nickname'] = $user['nickname'];
                    $response['ID'] = $user['ID'];
                    $response['email'] = $user['email'];
                    $response['createdAt'] = $user['created'];
                    $response['ruolo'] = $userType;
                    $status_code = 200;
                    // creazione del token
                    $issuer = "http://www.el_api.io";
                    $tokenId = base64_encode(mcrypt_create_iv(32));
                    $issuedAt = time();
                    $notBefore = $issuedAt + 1;             //Adding 1 second
                    $expire = $notBefore + 60 * 60 * 24;            // il token ha validità giornaliera
                    $token = array(
                        "iss" => $issuer,
                        "iat" => $issuedAt,
                        "nbf" => $notBefore,
                        "exp" => $expire,
                        "userType" => $userType // lo scope dipende dall'utente che fa il login
                    );
                    $jwt = JWT::encode($token, SECRETJWT); // l'algoritmo predefinito è HS256
                    $response['jwt'] = $jwt;
                } else {
                    $response['status'] = "error";
                    $response['message'] = 'Login failed. Incorrect credentials';
                    $status_code = 401;
                }
            } else {
                $response['status'] = "error";
                $response['message'] = 'No such user is registered';
                $status_code = 401;
            }
            UtilityClass::echoResponse($status_code, $response);
        });
        $app->post('/signup', function() use ($app,$log) {
            $log->debug("/signup");
            $r = json_decode($app->request->getBody());
            dbHelper::verifyRequiredParams(array('email', 'nickname', 'password'), $r->user);

            $nickname = $r->user->nickname;
            $email = $r->user->email;
            $password = $r->user->password;

            $pdo = $app->container['PDO'];
            $columns = 'ID,nickname,password,email,created';
            $table = 'user';
            $where = array("email" => "$email");
            $orwhere = array("nickname" => "$nickname");
            $limit = 1;

            $response = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            $isUserExists = $response['data'];
            if (!$isUserExists) {
                $r->user->password = passwordHash::hash($password);
                $requiredColumnsArray = array('nickname', 'email', 'password');
                //$columnsArray = array("nickname" => "$nickname","email" => "$email","password" => "$password");
                $columnsArray = $r->user;
                $result = dbHelper::insert($pdo, $table, $columnsArray, $requiredColumnsArray);
                if ($result != NULL) {
                    $response["status"] = "success";
                    $response["message"] = "User account created successfully";
                    $response["ID"] = $result;
                    // creazione del token
                    $issuer = "http://www.el_api.io";
                    $tokenId = base64_encode(mcrypt_create_iv(32));
                    $issuedAt = time();
                    $notBefore = $issuedAt + 1;             //Adding 1 second
                    $expire = $notBefore + 60 * 60 * 24;            // il token ha validità giornaliera
                    $token = array(
                        "iss" => $issuer,
                        "iat" => $issuedAt,
                        "nbf" => $notBefore,
                        "exp" => $expire,
                        "userType" => $userType // lo scope dipende dall'utente che fa il login
                    );
                    $jwt = JWT::encode($token, SECRETJWT); // l'algoritmo predefinito è HS256
                    $response['jwt'] = $jwt;
                    UtilityClass::echoResponse(200, $response);
                } else {
                    $response["status"] = "error";
                    $response["message"] = "Failed to create user. Please try again";
                    UtilityClass::echoResponse(201, $response);
                }
            } else {
                $response["status"] = "error";
                $response["message"] = "An user with the provided nickname or email exists!";
                UtilityClass::echoResponse(201, $response);
            }
        });

        $app->get('/courses', function () use ($app, $log) {
            $log->debug("courses");

            $pdo = $app->container['PDO'];
            $columns = "ID,title,description,price,start_date,end_date,max_number_of_students,ID_subject";
            $table = 'course';
            $where = array();
            $orwhere = array();
            $limit = 50;

            $response = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            UtilityClass::echoResponse(200, $response);
        });
        
        $app->get('/courses/:id/lessons', function ($id) use ($app, $log) {
            $log->debug("lessons of course $id");

            $pdo = $app->container['PDO'];
            $columns = "title,date,start_at,finish_at,ID_course";
            $table = 'V_lesson_of_course';
            $where = array("ID_course" => "$id");
            $orwhere = array();
            $limit = 500;

            $response = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            UtilityClass::echoResponse(200, $response);
        });
        

        $app->get('/teachers', function() {

            $columns = "ID,first_name,last_name,nickname,email,password,address,birthdate,gender,educational_qualification,image,is_a_student,is_a_teacher,is_active,created";
            $table = "user";
            $where = array(
                "is_a_teacher" => "1"
            );
            $orwhere = array();
            //$limit = 9999;
            $response = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            echoResponse(200, $response);
        });
       
        $app->get('/teachers/:id', function($id) use ($app,$log){
            $log->debug("/teachers/:id");
            if (false === $id) {
                throw new Exception("Invalid contact ID");
            }

            $columns = "ID,first_name,last_name,nickname,email,password,address,birthdate,gender,educational_qualification,image,is_a_student,is_a_teacher,is_active,created";
            $table = "user";
            $where = array(
                "is_a_teacher" => "1",
                "ID" => "$id"
            );
            $orwhere = array();
            $limit = 1;
            $result = dbHelper::select($pdo, $table, $columns, $where, $orwhere, $limit);
            echoResponse(200, $result);
        });
    }); // fine del gruppo /api/v1
});
$app->run();
?>