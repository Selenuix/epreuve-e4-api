<?php

require_once "rest.inc.php";
require_once "restCave.inc.php";
// Traite la demande
$api = new RestCaveService();
$api->process();
