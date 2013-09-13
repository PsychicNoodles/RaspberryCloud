<?php

require ("../database.php");


if($_SERVER["REQUEST_METHOD"] == "GET")
{
    $username; $password; $src;
    if(array_key_exists("username", $_COOKIE) && array_key_exists("password", $_COOKIE))
    {
	if(!auth($dbc, $_COOKIE["username"], $_COOKIE["password"]))
	{
	    header('WWW-Authenticate: Basic realm="RCloud Login"', $http_response_code = 401);
	    access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "check.php", $username = $_COOKIE["username"], $password = $_COOKIE["password"]);
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
	    access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "check.php", $username = $_SERVER["PHP_AUTH_USER"], $password = $_SERVER["PHP_AUTH_PW"]);
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
	access($dbc, $_SERVER["REMOTE_ADDR"], false, "GET", "check.php", null, null, null);
	die("authentication required");
    }

    if(!array_key_exists("path", $_GET))
    {
	http_response_code(400);
	die("request file path required");
    }

    $src = apache_getenv("RCLOUD_BASE_DIR") . $username . $_GET["path"];

    if(!checkfile($dbc, $src, $username) || !file_exists($src)) #not found
    {
	access($dbc, $_SERVER["REMOTE_ADDR"], true, "GET", "check.php", $username = $username, $password = $password, $requested_file = $src);
	if(checkqueue($dbc, $src, $username)) #in the queue
	{
	    http_response_code(403);
	    die("file needs CRC32 checksum before being available");
	}
	http_response_code(404);
	die("file not found");
    }

    access($dbc, $_SERVER["REMOTE_ADDR"], true, "GET", "check.php", $username = $username, $password = $password, $requested_file = $src);
    echo "file found";
}
else
{
	access($dbc, $_SERVER["REMOTE_ADDR"], false, $_SERVER["REQUEST_METHOD"], "check.php", null, null, null);
    header("Allow: GET", $http_response_code = 405);
    die("method not allowed");
}
?>
