<?php

class RestCaveService extends Rest
{
    public const DB_SERVER = "localhost";
    public const DB_USER = "root";
    public const DB_PASSWORD = "";
    public const DB_NAME = "cave";
    private $db;
    private $data;

    public function __construct()
    {
        // Appel du constructeur de la classe mère
        parent:: __construct();
        $data = "";
        $db = null;
        // Connexion à la base de données
        $this->dbConnect();
    }

    /*
    *  Connexion à la base de données
    */
    private function dbConnect()
    {
        $OPTIONS = array(PDO :: ATTR_ERRMODE => PDO :: ERRMODE_EXCEPTION, PDO :: ATTR_DEFAULT_FETCH_MODE => PDO :: FETCH_ASSOC);
        try {
            $connectionString = 'mysql:host=' . self :: DB_SERVER . ';port=3306;dbname=' . self :: DB_NAME . ';charset=utf8';
            $this->db = new PDO($connectionString, self :: DB_USER, self :: DB_PASSWORD, $OPTIONS);
            //$this->db->exec('SET NAMES utf8');
            //$this->db->exec('SET CHARACTER SET utf8');
        } catch (Exception $e) {
            //BD indisponible
            $this->response('', 503);
        }
    }

    /*
    * Methode publique d'accès au web service REST.
    * Cette méthode permet d'appeler dynamiquement la méthode correspondant
    * à la requête HTTP envoyée
    * La méthode (PUT, GET, POST ou DELETE) et uri utilisée permettent
    * de définir le traitement à effectuer
    * Par exemple :
    * GET    http://localhost/cave/vins ==> retourner la liste des vins
    * POST   http://localhost/cave/vins?nom=aaa&annee=2009&cepage=bbb... ==> créer un nouveau vin
    * GET    http://localhost/cave/vins/12 ==> retourner les données du vin n° 12
    * DELETE http://localhost/cave/vins/12 ==> supprimer le vin n° 12
    * PUT    http://localhost/cave/vins/12?nom=aaa&annee=2009&cepage=bbb... ==> modifier les informations du vin n° 12
    *
    */
    public function process()
    {
        /*    $_REQUEST['operation'] contient tous les caractères qui sont après
            http://localhost/cave/
            par exemple si l’URL est http://localhost/cave/vins/12 alors
            la variable $_REQUEST['operation'] contiendra vins/12

            On appelle la méthode explode permet d’obtenir dans le tableau qui s'appelle $tab
            tous les éléments de la variable $_REQUEST['operation']situés entre les /
            pour l'exemple ci-dessus, $tab contiendra 2 éléments :
            la première case contiendra vins et la deuxième contiendra 2
            */
        $tab = explode('/', $_REQUEST['operation']);

        /*    array_shift permet d'obtenir la première valeur d'un tableau PUIS
            LA SUPPRIME du tableau.
            Pour l'exemple ci-dessus après l'appel de array_shift, le tableau ne contiendra
            plus qu'un seul élément et $ressource contiendra le premier élément du tableau
            soit la valeur vins
        */
        $ressource = array_shift($tab);

        /*    si le tableau a encore des éléments, on appelle la méthode array_shift qui
            permet d'obtenir la première valeur du tableau dans la variable $id PUIS SUPPRIME la première case du tableau.
            Pour l'exemple ci-dessus après l'appel de array_shift, le tableau ne contiendra
            plus d'élément et $id contiendra l'identifiant du vin à consulter
        */
        if (count($tab) != 0) {
            $id = array_shift($tab);
        }
        /*    on récupère la méthode utilisée (PUT, GET, POST ou DELETE) */
        $methode = $this->get_request_method();  // appel d'une méthode de la classe mère
        /*
            on vérifie si les demandes sont conformes :
            Si la ressource demandée est différente de vins alors on retourne le code erreur 404
            Si la ressource demandée est égale à vins alors on détermine le nom de la fonction à appeler
            en fonction du type de méthode demandé
        */
        if ($ressource != "vins") {
            // la ressource demandée n'existe pas (vins n'a pas été indiqué dans l'URI)
            $this->response('', 404);
        } else {
            switch ($methode) {
            case "GET":
                if (isset($id) == true) {
                    // on a passé un numéro de vin à consulter
                    // on souhaite lire un vin
                    $this->_request['fonction'] = "lireUnVin";
                    $this->_request['id'] = $id;
                } else {
                    // on souhaite lire tous les vins
                    $this->_request['fonction'] = "lireLesVins";
                }
                break;
            case "POST":
                if (isset($id) == false) {
                    // on souhaite créer un vin
                    $this->_request['fonction'] = "creerUnVin";
                } else {
                    // on a passé un numéro de vin alors que l'on veut créer un vin => erreur
                    $this->response('', 400);
                }
                break;
            case "PUT":
                if (isset($id) == true) {
                    // on a passé un numéro de vin à modifier
                    // on souhaite modifier un vin
                    $this->_request['id'] = $id;
                    $this->_request['fonction'] = "modifierUnVin";
                } else {
                    // le numéro de vin à modifier n'a pas été fourni => erreur
                    $this->response('', 400);
                }
                break;
            case "DELETE":
                if (isset($id) == true) {
                    // on a passé un numéro de vin à suppimer
                    // on souhaite supprimer un vin
                    $this->_request['id'] = $id;
                    $this->_request['fonction'] = "supprimerUnVin";
                } else {
                    // le numéro de vin à supprimer n'a pas été fourni => erreur
                    $this->response('', 400);
                }
                break;
            default:
                // méthode invalide
                $this->response('', 404);
                break;
            }
        }

        // Appel de la méthode dont le nom est contenu dans _request['fonction'] (tableau de la classe mère)
        $func = $this->_request['fonction'];
        if ((int)method_exists($this, $func) > 0) {
            $this->$func();
        } else {
            // erreur Not found si la fonction n'existe pas
            $this->response('', 404);
        }
    }

    /*
    *  Retourne la liste de tous les vins
    *  Méhode GET
    */
    private function lireLesVins()
    {
        $sql = "SELECT * FROM vin";
        $requete = $this->db->prepare($sql);
        $requete->execute();
        if ($requete->rowCount() > 0) {
            $result = $requete->fetchAll();
            // Status OK + mise en forme des vins au format demandé (appel de la méthode convertirDonnees)
            $this->response($this->convertirDonnees($result), 200);
            $requete->closeCursor();
        } else {
            // Si aucun enregistrement, status "Not found"
            $this->response('', 404);
        }
    }

    /*
    *  Retourne les informations sur un vin
    *  identifiant du vin : $this->_request['id']
    *  Méhode GET
    */

    private function convertirDonnees($lesEnregs)
    {
        $reponse = array();
        $reponse["donnees"] = [];
        $reponse["nombre"] = count($lesEnregs);
        // les caractéristiques de chaque vin sont placées dans un tableau
        foreach ($lesEnregs as $k => $v) {
            extract($v);
            $unEnreg = array(
                "id" => $id,
                "nom" => $nom,
                "annee" => $annee,
                "cepage" => $cepage,
                "pays" => $pays,
                "region" => $region,
                "description" => $description
            );
            // ajout du tableau qui contient les caractéristiques d'un vin dans le tableau données
            array_push($reponse["donnees"], $unEnreg);
        }
        // encodage JSON de la réponse qui contient un tableau des enregistrements et
        // une donnée contenant le nombre d'enregistrements
        return json_encode($reponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /*
    *  Créer un vin
    *  Méthode POST

    */

    private function lireUnVin()
    {
        if (!empty($this->_request['id'])) {
            $sql = "SELECT * FROM vin WHERE id = :id";
            $requete = $this->db->prepare($sql);
            $requete->execute(array(':id' => $this->_request['id']));
            if ($requete->rowCount() > 0) {
                $result = $requete->fetchAll();
                // Status OK + mise en forme du vin au format demandé (appel de la méthode convertirDonnees)
                $this->response($this->convertirDonnees($result), 200);
                $requete->closeCursor();
            } else {
                // Si aucun enregistrement, status "No Content"
                $this->response('', 204);
            }
        } else {
            // id vin non transmis : status Bad Request
            $this->response('', 400);
        }
    }

    /*
    *  Créer un vin
    *  Méthode POST

    */
    private function creerUnVin()
    {
        $nom = isset($this->_request['nom']) ? $this->_request['nom'] : "";
        $annee = isset($this->_request['annee']) ? $this->_request['annee'] : 0;
        $cepage = isset($this->_request['cepage']) ? $this->_request['cepage'] : "";
        $pays = isset($this->_request['pays']) ? $this->_request['pays'] : "";
        $region = isset($this->_request['region']) ? $this->_request['region'] : "";
        $description = isset($this->_request['description']) ? $this->_request['description'] : "";
        //Tous les champs sont obligatoires sauf la description  - L'année doit être un entier
        if (!empty($nom) && !empty($annee) && !empty($cepage) && !empty($pays) && !empty($region) && intval($annee) != 0) {
            $sql = "INSERT INTO vin (nom, annee, cepage, pays, region, description) ";
            $sql .= "VALUES(:nom, :annee, :cepage, :pays, :region, :description)";
            $requete = $this->db->prepare($sql);
            $requete->execute(array(':nom' => $nom, ':annee' => $annee, ':cepage' => $cepage, ':pays' => $pays, ':region' => $region, ':description' => $description));
            if ($requete->rowCount() > 0) {
                $success = array('status' => "Success", "msg" => "Successfully one record inserted.");
                // Status OK + message enregistrement créé
                $this->response($this->convertirMessage($success), 200);
            } else {
                // Si aucun enregistrement créé, status "No Content"
                $this->response('', 204);
            }
        } else {
            // paramètres obligatoires non renseignés ou format incorrect : status Bad Request + message spécifique
            $this->response('', 400);
        }
    }

    /*
    *  supprimer un vin
    *  identifiant du vin : $this->_request['id']
    *  Méthode DELETE
    */

    public function convertirMessage($mess)
    {
        return json_encode($mess, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /*
    * Transformation des données au format JSON
    */

    private function modifierUnVin()
    {
        if (!empty($this->_request['id'])) {
            if (isset($this->_request['nom']) || isset($this->_request['annee']) || isset($this->_request['cepage']) || isset($this->_request['pays']) 
                || isset($this->_request['region']) || isset($this->_request['description'])
            ) {
                $vir = "";
                $sql = "UPDATE vin SET ";
                if (isset($this->_request['nom'])) {
                    $sql .= "nom = '" . $this->_request['nom'] . "'";
                    $vir = ',';
                }
                if (isset($this->_request['annee'])) {
                    $sql .= $vir . "annee = " . $this->_request['annee'];
                    $vir = ',';
                }
                if (isset($this->_request['cepage'])) {
                    $sql .= $vir . "cepage = '" . $this->_request['cepage'] . "'";
                    $vir = ',';
                }
                if (isset($this->_request['pays'])) {
                    $sql .= $vir . "pays = '" . $this->_request['pays'] . "'";
                    $vir = ',';
                }
                if (isset($this->_request['region'])) {
                    $sql .= $vir . "region = '" . $this->_request['region'] . "'";
                    $vir = ',';
                }
                if (isset($this->_request['description'])) {
                    $sql .= $vir . "description = '" . $this->_request['description'] . "'";
                    $vir = ',';
                }
                $sql .= " WHERE id = :id";
                $requete = $this->db->prepare($sql);
                $requete->execute(array(':id' => $this->_request['id']));
                if ($requete->rowCount() > 0) {
                    $success = array('status' => "Success", "msg" => "Successfully one record updated.");
                    $this->response($this->convertirMessage($success), 200);
                    // Status OK + message enregistrement modifié
                } else {
                    // Si aucun enregistrement modifié, status "No Content"
                    $this->response('', 204);
                }
            } else {
                // id vin non transmis : status Bad Request
                $this->response('', 400);
            }
        }
    }

    /*
    * Transformation du message au format demandé
    */

    private function supprimerUnVin()
    {
        if (!empty($this->_request['id'])) {
            $sql = "DELETE FROM vin WHERE id = :id";
            $requete = $this->db->prepare($sql);
            $requete->execute(array(':id' => $this->_request['id']));
            if ($requete->rowCount() > 0) {
                $success = array('status' => "Success", "msg" => "Successfully deleted one record .");
                $this->response($this->convertirMessage($success), 200);
                // Status OK + message enregistrement supprimé
            } else {
                // Si aucun enregistrement supprimé, status "No Content"
                $this->response('', 204);
            }
        } else {
            // id vin non transmis : status Bad Request
            $this->response('', 400);
        }
    }
}
