<?php

$dbc = mysqli_connect("localhost", "rcloud", $dbname = "rcloud") or die(mysqli_connect_error());
mysqli_set_charset($dbc, "utf8");

function auth($dbc, $username, $password)
{
    return !is_null(mysqli_fetch_array(mysqli_query($dbc, "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($dbc, $username) . "' AND password = '" . mysqli_real_escape_string($dbc, $password) . "'"), MYSQLI_NUM));
}

function access($dbc, $ip, $authed, $request, $page, $username = null, $password = null, $requested_file = null)
{
    return mysqli_query($dbc, "INSERT INTO access (" . ($username ? "username, " : "") . ($password ? "password, " : "") . "ip, authenticated, request, page" . ($requested_file ? ", requested_file)" : ")") . " VALUES ('" . ($username ? (mysqli_real_escape_string($dbc, $username) . "', '") : "") . ($password ? (mysqli_real_escape_string($dbc, $password) . "', '") : "") . mysqli_real_escape_string($dbc, $ip) . "', '" . mysqli_real_escape_string($dbc, $authed ? 1 : 0) . "', '" . mysqli_real_escape_string($dbc, $request) . "', '" . mysqli_real_escape_string($dbc, $page) . ($requested_file ? ("', '" . mysqli_real_escape_string($dbc, $requested_file) . "')") : ("')")));
}

function access_transferred($dbc, $id)
{
    return mysqli_query($dbc, "UPDATE access SET transferred = 1 WHERE id = '" . mysqli_real_escape_string($dbc, $id) . "'");
}

function newfile($dbc, $path, $size, $crc32, $ext, $user)
{
    return mysqli_query($dbc, "INSERT INTO files (path, size, crc32, extension, user) VALUES ('" . mysqli_real_escape_string($dbc, $path) . "', '" . mysqli_real_escape_string($dbc, $size) . "', '" . mysqli_real_escape_string($dbc, $crc32) . "', '" . mysqli_real_escape_string($dbc, $ext) . "', '" . mysqli_real_escape_string($dbc, $user) . "')");
}

function checkfile($dbc, $path, $user = null)
{
    return mysqli_query($dbc, "SELECT crc32 FROM files WHERE path = '" . mysqli_real_escape_string($dbc, $path) . ($user == null ? "'" : "' AND user = '" . mysqli_real_escape_string($dbc, $user) . "'"))->num_rows > 0;
}

function newqueue($dbc, $filename, $size, $crc32, $user, $destination)
{
    return mysqli_query($dbc, "INSERT INTO queue (filename, size, crc32, user, destination) VALUES ('" . mysqli_real_escape_string($dbc, $filename) . "', '" . mysqli_real_escape_string($dbc, $size) . "', '" . mysqli_real_escape_string($dbc, $crc32) . "', '" . mysqli_real_escape_string($dbc, $user) . "', '" . mysqli_real_escape_string($dbc, $destination) . "')");
}

function checkqueue($dbc, $dest, $user)
{
    return mysqli_query($dbc, "SELECT id FROM queue WHERE destination = '" . mysqli_real_escape_string($dbc, $dest) . "' AND user = '" . mysqli_real_escape_string($dbc, $user) . "'")->num_rows > 0;
}

function tryremovequeue($dbc, $dest, $crc32, $user)
{
    if(mysqli_query($dbc, "SELECT crc32 FROM queue WHERE destination = '" . mysqli_real_escape_string($dbc, $dest) . "'")->num_rows == 0)
    {
	http_response_code(404);
	die("file not in queue");
    }
    $r = mysqli_query($dbc, "SELECT crc32 FROM queue WHERE destination = '" . mysqli_real_escape_string($dbc, $dest) . "' AND user = '" . mysqli_real_escape_string($dbc, $user) . "'");
    if($r->num_rows == 0)
    {
    	http_response_code(401);
    	die("invalid credentials");
    }
    if(($c = mysqli_fetch_row($r)[0]) === $crc32) #matching checksum
    {
        $old = mysqli_fetch_row(mysqli_query($dbc, "SELECT filename, size FROM queue WHERE destination ='" . mysqli_real_escape_string($dbc, $dest) . "'"));
    	if(!newfile($dbc, $dest, $old[1], $crc32, strrpos($dest, ".") > strrpos($dest, "/") ? substr($dest, strrpos($dest, ".") + 1) : "", $user) || !mysqli_query($dbc, "DELETE FROM queue WHERE crc32 = '$c'"))
    	{
    	    http_response_code(500);
    	    die("entry found, internal database error");
    	}
        if(!rename(apache_getenv("RCLOUD_BASE_DIR") . "tmp" . $old[0], $dest))
	{
	    http_response_code(500);
	    die("file move failure, internal database error");
	}
	chmod($old[0], 0755);

    	http_response_code(201);
    	die("checksum match, success");
    }
    http_response_code(404);
    die("file found, crc32 does not match");
}

function listuploadssince($dbc, $user, $timestamp)
{
    return mysqli_fetch_all(mysqli_query($dbc, "SELECT path, upload_time, size FROM files WHERE user = '" . mysqli_real_escape_string($dbc, $user) . "' AND UNIX_TIMESTAMP(upload_time) = $timestamp");
}

?>
