<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/../phplib/vendor-php-jwt/autoload.php";

use \Tsugi\Util\LTI;
use \Tsugi\Core\Settings;
use \Tsugi\Core\LTIX;
use \Tsugi\UI\SettingsForm;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class GlobalConfigTypeAccess{
    const Unit = 'Unit';
    const Course = 'Course';
    const Evaluation = 'Evaluation';
}

class GlobalConfig
{
    private $vars;
    private $decodedToken;

    private static $instance;

    private function __construct() {
        $this->vars = array();
    }

    public function set($name, $value) {
        $this->vars[$name] = $value;
    }

    public function isSet($name){
        if(isset($this->vars[$name])) {
            return true;
        }else{
            return false;
        }
    }
    public function get($name) {
        if(isset($this->vars[$name])) {
            return $this->vars[$name];
        }
    }

    private static function hideErrors(){
        error_reporting(0);
        ini_set('display_errors', 0);
    }

    public static function showErrors(){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }

    public static function getInstance(){
        self::hideErrors();
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function sendGrade($gradetosend){
        $debug_log = array();
        $retval = LTIX::gradeSend($gradetosend, false, $debug_log);  
        return $retval;
    }

    public function isInstructor(){
        return $this->vars["ROLES"] == 'Instructor' ? true : false;
    }

    public function redirectError(){
        header("Location: ../../error/index.php");
    }
    
    public function initialize($idUnit, $typeUnit, $la, $title){
        $this->vars["TYPE_UNIT"] = $typeUnit;
        $this->vars["LEARNING_ID"] = $idUnit;
        $this->vars["LA"] = $la;
        $this->vars["TITLE"] = $title;
    }

    function refreshToken(){
        try{
            $refreshtoken = $this->vars["REFRESH_TOKEN"];
            $key = MY_JWT_KEY;                    
            $decoded = JWT::decode($refreshtoken, new Key($key, 'My_algorithm'));

            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => my_backend_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=refresh_token&refresh_token='.$refreshtoken,
            CURLOPT_HTTPHEADER => array(
                my_password,
                'Content-Type: application/x-www-form-urlencoded'
            ),
            ));
        
            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpcode == 200){
                $json = json_decode($response);
                $this->vars["TOKEN"] = $json->access_token;
                $this->vars["REFRESH_TOKEN"] = $json->refresh_token;
                return true;
            }else{
                return false;
            }
        }catch (Exception $e){
            //En el caso de que el token esté caducado salta la excepción
            return false;
        }
        
    }

    public function checkToken(){
        try{
            $key =my_key;                    
            $this->decodedToken = JWT::decode($this->vars["TOKEN"], new Key($key, my_algorithm));
            return true;
        }catch (Exception $e){
            //En el caso de que el token esté caducado salta la excepción y creo un nuevo token.
            return $this->refreshToken();
        }
    }

    private function stringNewToken(){
        preg_match('/(es)([^?]*)/', $_SESSION["lti"]["link_path"], $matches);
        $cadenaLTI = "|LTI|" . $_SESSION["lti_post"]["tool_consumer_instance_guid"] . "|" . $_SESSION["lti_post"]["context_id"]. "|" . 
        $_SESSION["lti_post"]["resource_link_id"]. "|" .  $_SESSION["lti_post"]["lis_person_contact_email_primary"] . "|" .  $this->vars["LEARNING_ID"] . "|" . 
        $_SESSION["lti_post"]["user_id"] . "|" . $_SESSION["lti_post"]["lis_person_name_given"]. "|" . $_SESSION["lti_post"]["lis_person_name_family"] . "|" .
        $_SESSION["lti_post"]["resource_link_title"]. "|" . $_SESSION["lti_post"]["origin"] . "|" . ($_SESSION["lti_post"]["oauth_consumer_key"]) . "|" .
        $_SESSION["lti_post"]["roles"] . "|" . $matches[2] . "|" . $this->vars["TITLE"];
        return $cadenaLTI;
    }

    private function stringCompareToken(){
        preg_match('/(es)([^?]*)/', $_SESSION["lti"]["link_path"], $matches);    
        $cadenaLTI = "|REFRESHLTI|" . $_SESSION["lti_post"]["tool_consumer_instance_guid"] . "|" . $_SESSION["lti_post"]["context_id"]. "|" . 
        $_SESSION["lti_post"]["resource_link_id"]. "|" .  $_SESSION["lti_post"]["lis_person_contact_email_primary"] . "|" .  $this->vars["LEARNING_ID"] . "|" . 
        $_SESSION["lti_post"]["user_id"] . "|" . $_SESSION["lti_post"]["lis_person_name_given"]. "|" . $_SESSION["lti_post"]["lis_person_name_family"] . "|" .
        $_SESSION["lti_post"]["resource_link_title"]. "|" . $_SESSION["lti_post"]["origin"] . "|" . $_SESSION["lti_post"]["roles"] . "|" . $matches[2] . "|" . $this->vars["TITLE"];
        return $cadenaLTI;
    }

    public function isSameToken(){  
        $cadenaLTI = $this->stringCompareToken();
        if (strcmp($cadenaLTI, $this->decodedToken->user_name)==0){
            return true;
        }
        return false;
    }

    public function getSessionInternalCall(){
        $LTI = LTIX::requireData(); 
        if(isset($_SESSION["data_upctforma"])) {
            $this->vars = $_SESSION["data_upctforma"];
        }
    }

    public function getDataLA(){    
        $postfield = '{ }';   
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => my_analytics_url . $this->vars["ROLES"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',  
        CURLOPT_POSTFIELDS => $postfield,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $this->vars["TOKEN"],
            'Content-Type: application/json'
        ),
        ));
    
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);   
        http_response_code($httpcode);
    
        if ($httpcode==200){
            return $response;
        }else{
            $this->redirectError();
        }
    }

    public function getSessionLACall(){
        $LTI = LTIX::requireData(); 
        if ($_SESSION["lti"]["link_path"] == $_SESSION["data_upctforma"]["LINK_PATH"]){
            $this->vars = $_SESSION["data_upctforma"];
        }else{
            $this->vars["ROLES"] = $_SESSION['lti_post']['roles'];
            $this->vars["TOKEN"] = $_SESSION["lti_post"]["token"];
        }
    }

    private function newSession($idUnit, $typeUnit, $la, $title){
        $this->initialize($idUnit, $typeUnit, $la, $title);
        $this->createToken();

        switch ($typeUnit){
            case GlobalConfigTypeAccess::Unit:
                $this->getContentObjectives();
                break;
            case GlobalConfigTypeAccess::Evaluation:
                $this->getEvaluationObjectives();
                break;
            case GlobalConfigTypeAccess::Course:
                $this->getCourseObjectives();
                break;
        }            
    }
    


    public function initializeData($idUnit, $typeUnit, $la, $title){   
        $LTI = LTIX::requireData(); 
        if(isset($_SESSION["data_upctforma"])) {
            $data = $_SESSION["data_upctforma"];         
            if ((($data["LEARNING_ID"] == $idUnit)&&($data["TYPE_UNIT"] == $typeUnit))||
                ((isset($data["COURSE_UNITS"]))&&(in_array($idUnit, $data["COURSE_UNITS"]))&&($data["TYPE_UNIT"] == GlobalConfigTypeAccess::Course)))
            {
                $this->vars = $data;
                $this->vars["LA"] = $la;
                $this->vars["TITLE"] = $title;          
                if ((!$this->checkToken()) || (!$this->isSameToken())){
                    $this->newSession($idUnit, $typeUnit, $la, $title);
                }
                return true;
            }
        }
        $this->newSession($idUnit, $typeUnit, $la, $title);
        return true;
    }

    public function createToken(){    
        $this->vars["LINK_PATH"] = $_SESSION["lti"]["link_path"];
        $this->vars["ROLES"] = $_SESSION["lti_post"]["roles"];

        $cadenaLTI = $this->stringNewToken();
        $this->generar_token($cadenaLTI);	
    }

    function getJSONEvaluationObjectives(){           
        $postfield = '{"nota": -1}';		
        return $postfield;
    }

    function setEvaluationObjective($nota){
        $objectives =  json_decode($this->vars["OBJECTIVES"]);
        $objectives->nota = $nota;
        $this->vars["OBJECTIVES"] = json_encode($objectives); 
    }

    function setContentObjective($objective){
        $objectiveComplete = json_decode($objective);
        $objectives =  json_decode($this->vars["OBJECTIVES"]);
        foreach($objectives as $section){
            foreach ($section as $data){
                if ($data->id == $objectiveComplete->id){
                    $data->_porcentaje = 100;
                }
            }
        }
        $this->vars["OBJECTIVES"] = json_encode($objectives);      
    }

    function getJSONContentObjectives(){
        $strJsonFileContents = file_get_contents("progreso_obj.json");
        $array = json_decode($strJsonFileContents, true);
                
        $postfield = '[';
        $position = 0;
        $positiong = 0;
        foreach ($array as $data) {
            if (strcmp("objetivos", $data)!=0){
                if (strcmp("section", $data)==0){
                    $position = $position + 1;
                }else{
                    if ($positiong!=0){$postfield = $postfield . ",";}else{$positiong=1;}
                    $postfield = $postfield . '{"nombre": "'. $data .'", "posicion": ' . $position . '}';		
                }		
            }
        }
        $postfield = $postfield . ']';
        return $postfield;
    }

    function processEvaluationObjectives($response){
        $cadenjs= '{"nota": '. $response["nota"] .', "id" : '. $response["id"] .'}';
        $this->vars["GRADE"] =  $response["nota"]; 
        return $cadenjs;
    }

    function processContentObjectives($response){
        $cadenjs="[[";
        $posicion = 0;
        $posiciong = 0;
        $numeroobjetivos = 0;
        $objetivossuperados = 0;
        foreach ($response as $obj) {
            while ($posicion < $obj["posicion"]){
                $posicion = $posicion +1;
                $cadenjs = $cadenjs . "],["; 	
                $posiciong=0;
            }
            if ($posiciong!=0){$cadenjs = $cadenjs . ",";}else{$posiciong=1;}
            $cadenjs = $cadenjs . '{"_id": "' . $obj["nombre"] . '", "_porcentaje": '. $obj["superado"] .', "id" : '. $obj["id"] .'}';
            $numeroobjetivos =  $numeroobjetivos + 1;
            if ($obj["superado"]==100){
                $objetivossuperados = $objetivossuperados + 1;
            }
        }
        $cadenjs= $cadenjs . "]]";    
        $this->vars["NUMBER_OBJECTIVES"] = $numeroobjetivos; 
        $this->vars["PASS_OBJECTIVES"] = $objetivossuperados; 
        return $cadenjs;
    }

    function getCourseObjectives(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => my_objectives_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $this->vars["TOKEN"]
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode == 200){
            $porcentajecurso = json_decode($response, true);
            $this->vars["COURSE_PERCENTAGE"] = json_encode($porcentajecurso["porcentaje"]);
        }else{
            $this->redirectError();
        }
    }

    function getEvaluationObjectives(){
        $curl = curl_init();

        $postfield = $this->getJSONEvaluationObjectives();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => my_evaluation_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postfield,
            CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' .  $this->vars["TOKEN"],
            'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (($httpcode == 200)||($httpcode == 201)){            
            $responseobjetivos = json_decode($response, true);	
            $this->vars["OBJECTIVES"] = $this->processEvaluationObjectives($responseobjetivos["evaluacion"]);
        }else{
            $this->redirectError();
        }
    }

    function getContentObjectives(){
        $curl = curl_init();

        $postfield = $this->getJSONContentObjectives();
        curl_setopt_array($curl, array(
            CURLOPT_URL => my_unit_objectives_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postfield,
            CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $this->vars["TOKEN"],
            'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (($httpcode == 200)||($httpcode == 201)){
            $responseObjectives = json_decode($response, true);			
            $this->vars["OBJECTIVES"] = $this->processContentObjectives($responseObjectives["objetivos"]);
        }else{
            $this->redirectError();

        }

    }
    function generar_token($cadenaLTI){
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
        CURLOPT_URL => my_backend_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'username=' .$cadenaLTI . '&password=my_password&grant_type=password',
        CURLOPT_HTTPHEADER => array(
            my_password,
            'Content-Type: application/x-www-form-urlencoded'
        ),
        ));
        
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    
        if ($httpcode == 200){
            $json = json_decode($response);
            $this->vars["TOKEN"] = $json->access_token;
            $this->vars["REFRESH_TOKEN"] = $json->refresh_token;
        }else{
            $this->redirectError();
        }
    }

    public function setSession(){
        $_SESSION["data_upctforma"] = $this->vars;     
    }

    public function setCookie(){  
        setcookie("my_token",openssl_encrypt($this->vars["TOKEN"],'my_algorithm',getenv('APPSETTING_KEY'),false,getenv('APPSETTING_IV')),time() + (86400 * 30),"/","my_domain",true,true);	
    }
}