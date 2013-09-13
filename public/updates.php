<?php

require ("../database.php");


if($_SERVER["REQUEST_METHOD"] == "GET")
{
    $username; $password; $results; $json;
    if(array_key_exists("username", $_COOKIE) && array_key_exists("password", $_COOKIE))
    {
	if(!auth($dbc, $_COOKIE["username"], $_COOKIE["password"]))
	{
	    header('WWW-Authenticate: Basic realm="RCloud Login"', $http_response_code = 401);
	    access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "download.php", $username = $_COOKIE["username"], $password = $_COOKIE["password"]);
	    die("invalid credentials");
	}

	$username = $_COOKIE["username"];
	$password = $_COOKIE["password"];
    }
    elseif(isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"]))
    {
	if(!auth($dbc, $_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]))
	{
	    header('WWW-Authenticate: Basic realm="RCloud Login"', $http_response_code = 401);
	    access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "download.php", $username = $_SERVER["PHP_AUTH_USER"], $password = $_SERVER["PHP_AUTH_PW"]);
	    die("invalid credentials");
	}

	setcookie("username", $_SERVER["PHP_AUTH_USER"], $secure = true, $httponly = true);
	setcookie("password", $_SERVER["PHP_AUTH_PW"], $secure = true, $httponly = true);

	$username = $_SERVER["PHP_AUTH_USER"];
	$password = $_SERVER["PHP_AUTH_PW"];
    }
    else
    {
	header('WWW-Authenticate: Basic realm="RCloud Login"', $http_response_code = 401);
	access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "download.php", null, null, null);
	die("authentication required");
    }

    if(!array_key_exists("timestamp", $_GET))
    {
	http_response_code(400);
	die("last update timestamp required");
    }

    $results = listuploadssince($dbc, $username, $_GET["timestamp"]);
    header("Content-Type: application/json");

    if(sizeof($results) == 0)
    	die(json_encode(array("updates" => null)));

    $json = array();

    foreach($results as $r)
    	$json[$r["path"]] = array("upload time" => $r["upload_time"], "size" => $r["size"]);

    echo json_encode(array("updates" => $json));
}
else
{
	access($dbc, $_SERVER["REMOTE_ADDR"], false, $_SERVER["REQUEST_METHOD"], "updates.php", null, null, null);
    header("Allow: GET", $http_response_code = 405);
    die("method not allowed");
}
?>