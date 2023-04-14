<?php
error_reporting(E_ALL);
ini_set('display_errors', true);

/** @var \PDO $PDO */
require_once 'DB.php';

if (array_key_exists('ip', $_GET) && strlen($_GET['ip'])) {
	$input = 'http://' . $_GET['ip'] . '/api/livedata/status';
} else {
	$input = 'php://input';
}

$data = file_get_contents($input);

if (strlen($data)) {
	$json = json_decode($data, true);

	if (is_array($json) && array_key_exists('inverters', $json) && is_array($json['inverters'])) {
		foreach ($json['inverters'] as $inv) {

			//Übersicht
			$insert = array(
				'serial' => $PDO->quote($inv['serial']),
				'name' => $PDO->quote($inv['name']),
				'power' => $PDO->quote($inv['AC'][0]['Power']['v']),
				'yieldday' => $PDO->quote($inv['AC'][0]['YieldDay']['v']),
				'temperature' => $PDO->quote($inv['INV'][0]['Temperature']['v']),
			);

			$PDO->exec('INSERT INTO `inverter` (' . implode(', ', array_keys($insert)) . ') 
			VALUES (' . implode(', ', $insert) . ')
			ON  DUPLICATE KEY UPDATE 
			name=' . $insert['name'] . ',
			power=' . $insert['power'] . ',
			yieldday=' . $insert['yieldday'] . ',
			temperature=' . $insert['temperature']
			);

			//Daten-Log
			$insert = array(
				'serial' => $PDO->quote($inv['serial']),
				'power' => $PDO->quote($inv['AC'][0]['Power']['v']),
				'yieldday' => $PDO->quote($inv['AC'][0]['YieldDay']['v']),
				'temperature' => $PDO->quote($inv['INV'][0]['Temperature']['v']),
			);

			$StringMapping = array();

			foreach ($inv['DC'] as $s => $string) {
				$insert['power_' . $s] = $PDO->quote($string['Power']['v']);
				if (strlen($string['name']['u'])) {
					$StringMapping[] = 'name_power_' . $s . '=' . $PDO->quote($string['name']['u']);
				}
			}

			$PDO->exec('INSERT INTO `inverter__data` (' . implode(', ', array_keys($insert)) . ') VALUES (' . implode(', ', $insert) . ')');

			if (count($StringMapping)) {
				$PDO->exec('Update inverter set ' . implode(', ', $StringMapping) . ' where serial = ' . $PDO->quote($inv['serial']));
			}
		}
	} else {
		echo 'Daten konnten nicht geparsed werden!';
	}
} else {
	echo 'Keine Daten verfügbar!';
}