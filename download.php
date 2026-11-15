<?php
	require_once 'class/Session.php';
	require_once 'class/FileHandler.php';

	$session = Session::getInstance();

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}

	// Registra i download in un file nascosto della cartella dei download,
	// fuori dalla cartella web (stringa vuota per disattivare)
	define('LOG_FILE', '.downloads.log');

	$file = new FileHandler;
	$name = isset($_GET['f']) ? $_GET['f'] : '';
	$path = $file->resolve($name);

	if($path === null)
	{
		http_response_code(404);
		die("File not found.");
	}

	FileHandler::send_file($path, $file->mime_type($path), false);

	if(LOG_FILE !== '')
	{
		@file_put_contents(rtrim($file->get_downloads_folder(), '/').'/'.LOG_FILE, date("d.m.Y H:i")."  ".$_SERVER['REMOTE_ADDR']."  ".$name."\n", FILE_APPEND);
	}
?>
