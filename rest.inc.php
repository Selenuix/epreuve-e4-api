<?php

class Rest
{
    protected $_content_type; // type de contenu demandé
    protected $_request;      // paramètres de la requêtes
    protected $_method;       // methodes
    protected $_code;         // code statut de retour

    /*
    *  Constructeur de la classe
    */
    public function __construct()
    {
        $this->_method = $this->get_request_method();
        $this->_code = 200;
        $this->_request = array();
        $this->_content_type = "application/json";
        // si on souhaite transmettre les données au format xml au lieu de json
        // remplacer la ligne précédente par la ligne suivante :
        // $this->_content_type = "application/xml";

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $this->_content_type = $_SERVER['CONTENT_TYPE'];
        }
        $this->inputs();
    }
    /*
    *  Traite la réponse à la requête
    */
    public function response($data, $status)
    {
        $this->_code = ($status) ? $status : 200;
        $this->set_headers();
        echo $data;
        exit;
    }
    /*
    *   Récupère la méthode associée à la demande
    */
    public function get_request_method()
    {
        return $_SERVER['REQUEST_METHOD'];
        //return 'PUT';
    }
    /*
    *  Récupére les paramètres de la requête
    *  et les stocke dans le champ $_request
    *  Une erreur est envoyée si la demande concerne
    *  une méthode autre que GET, POST, PUT ou DELETE
    */
    private function inputs()
    {
        switch ($this->_method) {
        case "POST":
        case "GET":
        case "DELETE":
        case "PUT":
            $this->_request = $this->cleanInputs($_REQUEST);
            //parse_str(file_get_contents("php://input"),$this->_request);
            //$this->_request = $this->cleanInputs($this->_request);
            break;
        default:
            $this->response('', 406);
            break;
        }
    }
    /*
    *   Analyse les paramètres d'entrée et les
    *   reformatte éventuellement afin d'obtenir
    *   un tableau associatif à une dimension des
    *   paramètres de la demande
    */
    private function cleanInputs($data)
    {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            if (get_magic_quotes_gpc()) {
                $data = trim(stripslashes($data));
            }
            $data = strip_tags($data);
            $clean_input = trim($data);
        }
        return $clean_input;
    }
    /*
    *  Gère les entêtes HTTP de la réponse
    */
    private function set_headers()
    {
        header("HTTP/1.1 " . $this->_code . " " . $this->get_status_message());
        header("Content-Type:" . $this->_content_type);
    }
    /*
    *  Définit le message associé au code statut HTTP
    *  Norme RFC 2616
    *  100 ==> 118 : codes d'information
    *  200 ==> 206 : codes de succès
    *  300 ==> 310 : codes de redirection
    *  400 ==> 417 : codes d'erreur du client
    *  500 ==> 505 : codes d'erreur du serveur
    */
    private function get_status_message()
    {
        $status = array(100 => 'Continue',
            101 => 'Switching Protocols',
            118 => 'Connection timed out',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            310 => 'Too many Redirects',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );
        return ($status[$this->_code]) ? $status[$this->_code] : $status[500];
    }
}
