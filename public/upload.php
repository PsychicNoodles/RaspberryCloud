<?php

require ("../database.php");


if($_SERVER["REQUEST_METHOD"] == "POST")
{
    $username; $dest; $crc32;
    if(array_key_exists("username", $_COOKIE) && array_key_exists("password", $_COOKIE))
    {
        if(!auth($dbc, $_COOKIE["username"], $_COOKIE["password"]))
        {
            header('WWW-Authenticate: Basic realm="RCloud Login"', $http_response_code = 401);
            access($dbc, $_SERVER["REMOTE_ADDR"], false, "POST", "upload.php", $username = $_COOKIE["username"], $password = $_COOKIE["password"]);
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
            access($dbc, $_SERVER["REMOTE_ADDR"], false, "POST", "upload.php", $username = $_SERVER["PHP_AUTH_USER"], $password = $_SERVER["PHP_AUTH_PW"]);
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
	access($dbc, $_SERVER["REMOTE_ADDR"], false, "POST", "upload.php", null, null, null);
        die("authentication required");
    }

    if(!array_key_exists("Content-Location", getallheaders()))
    {
        http_response_code(400);
        die("missing content-location header");
    }

    $dest = apache_getenv("RCLOUD_BASE_DIR") . $username . getallheaders()["Content-Location"];

    if(substr($dest, -1) == "/") #non-file upload
    {
        mkdir($dest);
	chmod($dest, 0750);
        http_response_code(201);
        access($dbc, $_SERVER["REMOTE_ADDR"], true, "POST", "upload.php", $username = $username, $password = $password, $requested_file = $dest);
        die("dir created");
    }

    if(array_key_exists("Content-Type", getallheaders()))
    {
        if(getallheaders()["Content-Type"] == "message/plain") #crc32 information
        {
            $crc32 = file_get_contents("php://input");
            strlen($crc32) == 8 or die("invalid crc32 (incorrect length)");
            tryremovequeue($dbc, $dest, $crc32, $username);
        }
    }

    $length; $inlength = 0; $incrc32; $dbpointer;

    if(!array_key_exists("Content-Length", getallheaders()))
    {
        http_response_code(411);
        die("missing content-length header");
    }

    $length = getallheaders()["Content-Length"];

    if(!array_key_exists("Content-CRC32", getallheaders()))
    {
        header("Warning: 199 Missing content-crc32 header", $http_response_code = 202);
        $crc32 = null;
    }
    else
    {
        if(strlen(getallheaders()["Content-CRC32"]) == 8)
            $crc32 = getallheaders()["Content-CRC32"];
        else
        {
            header("Warning: 199 Missing content-crc32 header", $http_response_code = 202);
            $crc32 = null;
        }
    }

    http_response_code(file_exists($dest) ? 200 : 201);

    $in = fopen("php://input", "r");
    $out = fopen($crc32 == null ? apache_getenv("RCLOUD_BASE_DIR") . "tmp" . substr($dest, strrpos($dest, "/")) : $dest, "w");

    $dbpointer = access($dbc, $_SERVER["REMOTE_ADDR"], true, "POST", "upload.php", $username = $username, $password = $password, $requested_file = ($crc32 == null ? apache_getenv("RCLOUD_BASE_DIR") . "tmp" . substr($dest, strrpos($dest, "/")) : $dest));

    while($data = fread($in, 16*1024))
	$inlength += fwrite($out, $data);

    access_transferred($dbc, $dbpointer);

    chmod($crc32 == null ? apache_getenv("RCLOUD_BASE_DIR") . "tmp" . substr($dest, strrpos($dest, "/")) : $dest, 0750);

    if($inlength != $length)
        header("Warning: 199 content-length header != read bytes", false);

    $incrc32 = substr(shell_exec("jacksum -a crc32 -E hexup " . ($crc32 == null ? apache_getenv("RCLOUD_BASE_DIR") . "tmp" . substr($dest, strrpos($dest, "/")) : $dest)), 0, 8);

    if($incrc32 != $crc32 && $crc32 != null)
    {
        header("Warning: 199 content-crc32 header != read bytes crc32", false);
        newfile($dbc, $dest, $inlength, $incrc32, strrpos($dest, ".") > strrpos($dest, "/") ? substr($dest, strrpos($dest, ".") + 1) : "", $username);
    	die("upload complete, expected crc32 not equal to actual");
    }
    if($crc32 == null)
    {
    	newqueue($dbc, substr($dest, strrpos($dest, "/")), $inlength, $incrc32, $username, $dest);
    	http_response_code(202);
    	die("upload complete, waiting for crc32");
    }

    newfile($dbc, $dest, $inlength, $incrc32, strrpos($dest, ".") > strrpos($dest, "/") ? substr($dest, strrpos($dest, ".") + 1) : "", $username);

    echo "upload complete, success";
}
else
{
    access($dbc, $_SERVER["REMOTE_ADDR"], false, $_SERVER["REQUEST_METHOD"], "upload.php", null, null, null);
    header("Allow: POST", $http_response_code = 405);
    die("method not allowed");
}

?>
