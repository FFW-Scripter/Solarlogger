<?php
$host = null;
$dbname = null;
$user = null;
$pass = null;

if (file_exists('DB_data.php')) {
	require 'DB_data.php';
} else {
	header('Location: install.php');
}

try {
	$PDO = new PDO(
		'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4',
		$user,
		$pass,
		array(
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		));
} catch (Exception $e) {
	echo 'Problem beim DB-Verbindungsaufbau:' . "\n";
	echo $e->getMessage();
	if (!isset($install)) {
		die(1);
	}
}