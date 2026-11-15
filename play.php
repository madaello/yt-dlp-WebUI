<?php
	require_once 'class/Session.php';
	require_once 'class/FileHandler.php';

	$session = Session::getInstance();

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}

	// Registra le riproduzioni in un file nascosto della cartella dei download,
	// fuori dalla cartella web (stringa vuota per disattivare)
	define('LOG_FILE', '.plays.log');

	$file = new FileHandler;
	$name = isset($_GET['f']) ? $_GET['f'] : '';
	$path = $file->resolve($name);

	if($path === null)
	{
		http_response_code(404);
		die("File not found.");
	}

	// Il browser fa più richieste parziali mentre riproduce: registra solo la prima
	$first_request = !isset($_SERVER['HTTP_RANGE']) || preg_match('/bytes=0-/', $_SERVER['HTTP_RANGE']);

	FileHandler::send_file($path, $file->mime_type($path), true);

	if(LOG_FILE !== '' && $first_request)
	{
		@file_put_contents(rtrim($file->get_downloads_folder(), '/').'/'.LOG_FILE, date("d.m.Y H:i")."  ".$_SERVER['REMOTE_ADDR']."  ".$name."\n", FILE_APPEND);
	}
?>
