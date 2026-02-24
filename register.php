
<?php

$response = $rpc->call([
    "type" => "register",
    "username" => $_POST['username'], // send the username and data as a POST method
    "password" => $_POST['password']
]);

?>
