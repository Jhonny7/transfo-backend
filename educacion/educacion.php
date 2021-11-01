<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");

header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$method = $_SERVER['REQUEST_METHOD'];
if ($method == "OPTIONS") {
    die();
}

require_once '../include/DbHandler.php';
require_once '../services/fcm_service.php';
require '../libs/Slim/Slim.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../libs/PHPMailer/src/PHPMailer.php');
require('../libs/PHPMailer/src/SMTP.php');
require('../libs/PHPMailer/src/Exception.php');

/*
require("/home/site/libs/PHPMailer-master/src/PHPMailer.php");
require("/home/site/libs/PHPMailer-master/src/SMTP.php");

$mail = new PHPMailer\PHPMailer\PHPMailer();
 */

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->post('/petition', function () use ($app) {

    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();

    $body = $app->request->getBody();
    $data = json_decode($body, true);

    $sqlTotal = $data['query'];
    $push = $data['push'];
    $token = $data['token'];
    $retorna = $data['retorna'];
    $rowsUser = null;
    $query = null;
    $step = 0;
    $rows = null;
    $ultimo = null;
    try {

        $query = cryptoJsAesDecrypt("23deJulio08F!", $sqlTotal);
        if (strpos(strtoupper($sqlTotal), "DELETE") === true) {
            $response["status"] = false;
            $response["description"] = "Imposible borrar información";
            $response["idTransaction"] = time();
            $response["parameters"] = [];
            $response["timeRequest"] = date("Y-m-d H:i:s");

            echoResponse(400, $response);
        } else {

            $step = 1;
            $sthUsuario = $db->prepare($query);
            $sthUsuario->execute();
            if ($retorna == 1) {
                $rows = $sthUsuario->fetchAll(PDO::FETCH_ASSOC);
                $db->commit();
                $aRetornar = $rows;
            } else if ($retorna == 2) {
                //$sqlTotal = "SELECT * FROM user WHERE uid = '1'";

                //$sthUsuario = $db->prepare($sqlTotal);
                //$sthUsuario->bindParam(1, $uid, PDO::PARAM_STR);
                //$sthUsuario->execute();
                $rows = $sthUsuario->fetchAll(PDO::FETCH_ASSOC);
                $db->commit();
                $aRetornar = $rows;
            } else if ($retorna == 3) {
                $ultimo = $db->lastInsertId();
                $db->commit();
                $aRetornar = $ultimo;
            } else {
                $db->commit();
                $aRetornar = [];
            }

            if ($push !== null && $push !== "null") {
                $fcm = new FCMNotification();
                $title = "Menu Vid!";
                $data_body = array(
                    'view' => 1
                );

                $body = $push;
                $notification = array(
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'click_action' => 'FCM_PLUGIN_ACTIVITY'
                );

                $arrayToSend = array(
                    'to' => $token,
                    'notification' => $notification,
                    'data' => $data_body,
                    'priority' => 'high'
                );

                if ($token !== "tokenFake") {
                    $return = $fcm->sendData($arrayToSend);
                }
            }



            $response["status"] = true;
            $response["description"] = "SUCCESSFUL";
            $response["idTransaction"] = time();
            $response["parameters"] = $aRetornar;
            //$response["parameters2"] = $rows;
            $response["timeRequest"] = date("Y-m-d H:i:s");

            echoResponse(200, $response);
        }
    } catch (Exception $e) {
        if ($retorna) {
            $db->rollBack();
        }

        $response["status"] = false;
        $response["description"] = "GENERIC-ERROR";
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["p2"] = $query;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});

//Método para obtener imagen desde base de datos
$app->get('/image/:id', function ($id) use ($app) {

    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();
    $rows = null;
    //$debugger = null;
    try {
        $query = "SELECT file,content_type FROM archivo WHERE id = ?";
        $sthImg = $db->prepare($query);
        $sthImg->bindParam(1, $id, PDO::PARAM_INT);
        $sthImg->execute();

        $rows = $sthImg->fetchAll(PDO::FETCH_ASSOC);
        //$debugger = $rows;
        if ($rows && $rows[0]) {
            //$b64 = "data:image/png;base64,".base64_encode($rows[0]["file"]);

            echo ($rows[0]["file"]);
        } else {
            echoResponse(400, $id);
        }
    } catch (Exception $e) {
        $response["status"] = false;
        $response["description"] = "GENERIC-ERROR";
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["p2"] = $query;
        //$response["debug"] = $debugger;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});

$app->get('/pdf/:id', function ($id) use ($app) {

    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();
    $rows = null;
    //$debugger = null;
    try {
        $query = "SELECT file,content_type FROM archivo WHERE id = ?";
        $sthImg = $db->prepare($query);
        $sthImg->bindParam(1, $id, PDO::PARAM_INT);
        $sthImg->execute();

        $rows = $sthImg->fetch();
        //$debugger = $rows;
        if ($rows && $rows[0]) {
            $b64 = "data:application/pdf;base64," . base64_encode($rows["file"]);

            echo ($b64);
        } else {
            echoResponse(400, $id);
        }
    } catch (Exception $e) {
        $response["status"] = false;
        $response["description"] = "GENERIC-ERROR";
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["p2"] = $query;
        //$response["debug"] = $debugger;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});

$app->get('/testing', function () use ($app) {
    $targetDir = $_SERVER["DOCUMENT_ROOT"] . "/educacion";
    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();
    $rows = null;
    //$debugger = null;
    try {

        echo ($targetDir);
    } catch (Exception $e) {
        $response["status"] = false;
        $response["description"] = "GENERIC-ERROR";
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["p2"] = "";
        //$response["debug"] = $debugger;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});

//Método para cargar imagenes a partir de un json con files
$app->post('/load-blob', function () use ($app) {

    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();

    $body = $app->request->getBody();
    $data = json_decode($body, true);
    $idAdjuntoTmp = null;
    try {
        $files = $data['files'];
        $id = $data['idEmpresa'];
        $tipo = $data['tipo'];
        $name = $data['name'];
        $multi = $data['multi'];

        //Este bloque inserta el archivo en base de datos
        if ($files && sizeof($files) > 0) {
            foreach ($files as &$file) {
                $separado = explode("base64,", $file["base64"])[1];
                $blobData = base64_decode($separado);

                $sqlBlob = "INSERT INTO archivo (content_type, size, file_name, file_content_type, file)
                        VALUES (?,?,?,?,?)";
                $sthBlob = $db->prepare($sqlBlob);
                $sthBlob->bindParam(1, $file["type"], PDO::PARAM_STR);
                $sthBlob->bindParam(2, $file["size"], PDO::PARAM_STR);
                $sthBlob->bindParam(3, $file["name"], PDO::PARAM_STR);
                $sthBlob->bindParam(4, $file["type"], PDO::PARAM_STR);

                $sthBlob->bindParam(5, $blobData, PDO::PARAM_LOB);
                $sthBlob->execute();

                $idAdjuntoTmp = $db->lastInsertId();
            }
        }

        if (isset($data['update'])) {

            $idCat = $data['id'];
            $qq = "SELECT * FROM catalogo WHERE id = ?";
            $sthQQ = $db->prepare($qq);
            $sthQQ->bindParam(1, $idCat, PDO::PARAM_INT);
            $sthQQ->execute();
            $rows = $sthQQ->fetchAll(PDO::FETCH_ASSOC);

            if ($files && sizeof($files) > 0) {
                //Una vez que se obtiene el catalogo a actualizar se borrará imagen anterior
                $nullable = null;
                $updateCatalogQuerie = "UPDATE catalogo SET id_archivo = ? WHERE id = ?";
                $sthArchivoUpdate = $db->prepare($updateCatalogQuerie);
                $sthArchivoUpdate->bindParam(1, $nullable, PDO::PARAM_INT);
                $sthArchivoUpdate->bindParam(2, $rows[0]["id"], PDO::PARAM_INT);
                $sthArchivoUpdate->execute();

                $idArchivo = $rows[0]["id_archivo"];
                $deleteFileQuerie = "DELETE FROM archivo WHERE id = ?";
                $sthArchivo = $db->prepare($deleteFileQuerie);
                $sthArchivo->bindParam(1, $idArchivo, PDO::PARAM_INT);
                $sthArchivo->execute();
            }else{
                $idAdjuntoTmp = $data["idAdjunto"];
            }

            //Ahora se actualiza el catalogo con el nuevo id de archivo

            $descripcion = $data['description'];
            $nombre = $data['name'];
            $updateCatalogQuerie = "UPDATE catalogo SET id_archivo = ?, nombre = ?, descripcion = ? WHERE id = ?";
            $sthArchivoUpdate = $db->prepare($updateCatalogQuerie);
            $sthArchivoUpdate->bindParam(1, $idAdjuntoTmp, PDO::PARAM_INT);
            $sthArchivoUpdate->bindParam(2, $nombre, PDO::PARAM_STR);
            $sthArchivoUpdate->bindParam(3, $descripcion, PDO::PARAM_STR);
            $sthArchivoUpdate->bindParam(4, $rows[0]["id"], PDO::PARAM_INT);
            $sthArchivoUpdate->execute();
        } else {
            //Se debe verificar que exista el tipo de catalogo y archivo de empresa
            $qq = "SELECT * FROM catalogo WHERE id_empresa = ? AND id_tipo_catalogo = ?";
            $sthQQ = $db->prepare($qq);
            $sthQQ->bindParam(1, $id, PDO::PARAM_INT);
            $sthQQ->bindParam(2, $tipo, PDO::PARAM_INT);
            $sthQQ->execute();
            $rows = $sthQQ->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows || sizeof($rows) <= 0 || $multi) {
                if (!$multi) {
                    $in = "INSERT INTO catalogo (id_empresa, id_tipo_catalogo, descripcion, id_archivo) VALUES (?, ?, ?, ?)";
                    $sthin = $db->prepare($in);
                    $sthin->bindParam(1, $id, PDO::PARAM_INT);
                    $sthin->bindParam(2, $tipo, PDO::PARAM_INT);
                    $sthin->bindParam(3, $name, PDO::PARAM_STR);
                    $sthin->bindParam(4, $idAdjuntoTmp, PDO::PARAM_INT);
                    $sthin->execute();
                } else {
                    $descripcion = $data['description'];
                    $in = "INSERT INTO catalogo (id_empresa, id_tipo_catalogo, descripcion, id_archivo, nombre) VALUES (?, ?, ?, ?, ?)";
                    $sthin = $db->prepare($in);
                    $sthin->bindParam(1, $id, PDO::PARAM_INT);
                    $sthin->bindParam(2, $tipo, PDO::PARAM_INT);
                    $sthin->bindParam(3, $descripcion, PDO::PARAM_STR);
                    $sthin->bindParam(4, $idAdjuntoTmp, PDO::PARAM_INT);
                    $sthin->bindParam(5, $name, PDO::PARAM_STR);
                    $sthin->execute();
                }
            } else {
                $nullable = null;
                $updateCatalogQuerie = "UPDATE catalogo SET id_archivo = ? WHERE id = ?";
                $sthArchivoUpdate = $db->prepare($updateCatalogQuerie);
                $sthArchivoUpdate->bindParam(1, $nullable, PDO::PARAM_INT);
                $sthArchivoUpdate->bindParam(2, $rows[0]["id"], PDO::PARAM_INT);
                $sthArchivoUpdate->execute();

                $idArchivo = $rows[0]["id_archivo"];
                $deleteFileQuerie = "DELETE FROM archivo WHERE id = ?";
                $sthArchivo = $db->prepare($deleteFileQuerie);
                $sthArchivo->bindParam(1, $idArchivo, PDO::PARAM_INT);
                $sthArchivo->execute();

                //Ahora se actualiza el catalogo con el nuevo id de archivo

                $updateCatalogQuerie = "UPDATE catalogo SET id_archivo = ? WHERE id = ?";
                $sthArchivoUpdate = $db->prepare($updateCatalogQuerie);
                $sthArchivoUpdate->bindParam(1, $idAdjuntoTmp, PDO::PARAM_INT);
                $sthArchivoUpdate->bindParam(2, $rows[0]["id"], PDO::PARAM_INT);
                $sthArchivoUpdate->execute();
            }
        }

        //Se hace commit del archivo
        $db->commit();

        $response["status"] = true;
        $response["description"] = "Exitoso";
        $response["idTransaction"] = time();
        $response["parameters"] = $idAdjuntoTmp;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(200, $response);
    } catch (Exception $e) {
        $db->rollBack(); //rollback en caso de error
        $response["status"] = false;
        $response["description"] = $e->getMessage();
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});

//Método para cargar imagenes a partir de un json con files
$app->post('/load-pdf', function () use ($app) {
    $targetDir = $_SERVER["DOCUMENT_ROOT"] . "/api_ecommerce/pdf";
    $response = array();
    $dbHandler = new DbHandler();
    $db = $dbHandler->getConnection();
    $db->beginTransaction();

    $body = $app->request->getBody();
    $data = json_decode($body, true);
    $idAdjuntoTmp = null;
    try {
        $file = $data['file'];
        $idEmpresa = $data['idEmpresa'];
        $id = $data['id'];

        //Este bloque inserta el archivo en base de datos

        $separado = explode("base64,", $file["base64"])[1];
        $blobData = base64_decode($separado);

        //Crear folder
        $targetDir .= "/" . $id;
        $targetReal = "https://" . $_SERVER["SERVER_NAME"] . "/api_ecommerce/pdf/" . $id . "/";
        $random = rand();

        //$pathPDF = $targetReal. $random . ".pdf";
        $pathPDF = $targetDir . "/" . $random . ".pdf";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        } else {
            try {
                delete_directory($targetDir);
                mkdir($targetDir, 0755, true);
            } catch (Exception $e) {
                //throw $th;
            }
        }

        $file = fopen($pathPDF, "wb");
        fwrite($file, $blobData);
        fclose($file);

        //Se debe verificar que exista el tipo de catalogo y archivo de empresa
        $path = $targetReal . $random . ".pdf";
        $updateCatalogQuerie = "UPDATE producto SET pdf = ? WHERE id = ?";
        $sthArchivoUpdate = $db->prepare($updateCatalogQuerie);
        $sthArchivoUpdate->bindParam(1, $path, PDO::PARAM_STR);
        $sthArchivoUpdate->bindParam(2, $id, PDO::PARAM_INT);
        $sthArchivoUpdate->execute();
        //Se hace commit del archivo
        $db->commit();

        $response["status"] = true;
        $response["description"] = "Exitoso";
        $response["idTransaction"] = time();
        $response["parameters"] = $path;
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(200, $response);
    } catch (Exception $e) {
        $db->rollBack(); //rollback en caso de error
        $response["status"] = false;
        $response["description"] = $e->getMessage();
        $response["idTransaction"] = time();
        $response["parameters"] = $e->getMessage();
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);
    }
});
/* corremos la aplicación */
$app->run();

/*********************** USEFULL FUNCTIONS **************************************/

/**
 * Verificando los parametros requeridos en el metodo o endpoint
 */
function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();

        $response["status"] = "I";
        $response["description"] = 'Campo(s) Requerido(s) ' . substr($error_fields, 0, -2) . '';
        $response["idTransaction"] = time();
        $response["parameters"] = [];
        $response["timeRequest"] = date("Y-m-d H:i:s");

        echoResponse(400, $response);

        $app->stop();
    }
}

/**
 * Validando parametro email si necesario; un Extra ;)
 */
function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoResponse(400, $response);

        $app->stop();
    }
}

/**
 * Mostrando la respuesta en formato json al cliente o navegador
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoResponse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

/**
 * Agregando un leyer intermedio e autenticación para uno o todos los metodos, usar segun necesidad
 * Revisa si la consulta contiene un Header "Authorization" para validar
 */
function authenticate(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        //$db = new DbHandler(); //utilizar para manejar autenticacion contra base de datos

        // get the api key
        $token = $headers['Authorization'];

        // validating api key
        if (!($token == API_KEY)) { //API_KEY declarada en Config.php

            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Acceso denegado. Token inválido";
            echoResponse(401, $response);

            $app->stop(); //Detenemos la ejecución del programa al no validar

        } else {
            //procede utilizar el recurso o metodo del llamado
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Falta token de autorización";
        echoResponse(400, $response);

        $app->stop();
    }
}

function addCustomer($customerDetailsAry)
{
    /* $customer = new Customer();

    $customerDetails = $customer->create($customerDetailsAry);

    return $customerDetails; */
}


/*
 *Función para encriptar contraseñas
 */
function dec_enc($action, $string)
{
    $output = false;

    $encrypt_method = "AES-256-CBC";
    $secret_key = '23deJuLiO08F!';
    $secret_iv = '23dejUlIo08F!';

    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action == 'encrypt') {
        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    } else if ($action == 'decrypt') {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }

    return $output;
}

/**
 * Decrypt data from a CryptoJS json encoding string
 *
 * @param mixed $passphrase
 * @param mixed $jsonString
 * @return mixed
 */

function returnD($data)
{
    return cryptoJsAesDecrypt("23dejulio08F!", $data);
}

function cryptoJsAesDecrypt($passphrase, $jsonString)
{
    $jsondata = json_decode($jsonString, true);
    $salt = hex2bin($jsondata["s"]);
    $ct = base64_decode($jsondata["ct"]);
    $iv  = hex2bin($jsondata["iv"]);
    $concatedPassphrase = $passphrase . $salt;
    $md5 = array();
    $md5[0] = md5($concatedPassphrase, true);
    $result = $md5[0];
    for ($i = 1; $i < 3; $i++) {
        $md5[$i] = md5($md5[$i - 1] . $concatedPassphrase, true);
        $result .= $md5[$i];
    }
    $key = substr($result, 0, 32);
    $data = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
    return json_decode($data, true);
}

function better_crypt($input, $rounds = 7)
{
    $salt = "";
    $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
    for ($i = 0; $i < 22; $i++) {
        $salt .= $salt_chars[array_rand($salt_chars)];
    }
    return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
}
/**
 * Encrypt value to a cryptojs compatiable json encoding string
 *
 * @param mixed $passphrase
 * @param mixed $value
 * @return string
 */
function cryptoJsAesEncrypt($passphrase, $value)
{
    $salt = openssl_random_pseudo_bytes(8);
    $salted = '';
    $dx = '';
    while (strlen($salted) < 48) {
        $dx = md5($dx . $passphrase . $salt, true);
        $salted .= $dx;
    }
    $key = substr($salted, 0, 32);
    $iv  = substr($salted, 32, 16);
    $encrypted_data = openssl_encrypt(json_encode($value), 'aes-256-cbc', $key, true, $iv);
    $data = array("ct" => base64_encode($encrypted_data), "iv" => bin2hex($iv), "s" => bin2hex($salt));
    return json_encode($data);
}

function delete_directory($dirname)
{
    if (is_dir($dirname)) {
        $dir_handle = opendir($dirname);
    } else {
        return false;
    }

    while ($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname . "/" . $file)) {
                unlink($dirname . "/" . $file);
            } else {
                delete_directory($dirname . "/" . $file);
            }
        }
    }

    closedir($dir_handle);
    rmdir($dirname);
    return true;
}
