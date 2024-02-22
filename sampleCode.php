<?php

include 'ESMSWS.php';


$username = '';
$password = '';

$session = createSession('',$username,$password,'');
var_dump($session);


	$message = "";
	$recipients='';
	$alias = '';

echo sendMessages($session,$alias,$message,explode(",",$recipients),0);

closeSession($session);





?>