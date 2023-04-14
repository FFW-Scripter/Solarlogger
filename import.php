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

$optimizeFile = './optimize';
if (!file_exists($optimizeFile)) {
	touch($optimizeFile);
}
if (time() - filemtime($optimizeFile) > 24 * 60 * 60) {
	unlink($optimizeFile);
	echo "\nOptimiere Tabelle";

	//Wenn es mehr als eine Zeile pro Minute gibt -> Optimieren
	$createTable = $PDO->query('SHOW CREATE TABLE inverter__data')->fetch();
	$createTable_sql = str_replace($createTable['Table'], $createTable['Table'] . '_tmp', $createTable['Create Table']);
	$PDO->exec($createTable_sql);

	if ($PDO->errorCode() == 0) {
		$PDO->exec('Insert into ' . $createTable['Table'] . '_tmp Select serial,
       avg(power)                               as power,
       max(yieldday)                            as yieldday,
       avg(temperature)                         as temperature,
       date_format(timestamp, \'%Y-%m-%d %H:%i\') as ts,
       avg(power_0)                             as power_0,
       avg(power_1)                             as power_1,
       avg(power_2)                             as power_2,
       avg(power_3)                             as power_3,
       avg(power_4)                             as power_4,
       avg(power_5)                             as power_5
from ' . $createTable['Table'] . ' group by serial, ts');

		if ($PDO->errorCode() == 0) {
			$PDO->exec('truncate table ' . $createTable['Table']);
			$PDO->exec('Insert into ' . $createTable['Table'] . ' Select * from ' . $createTable['Table'] . '_tmp');
			$PDO->exec('drop table ' . $createTable['Table'] . '_tmp');
			$PDO->exec('optimize table ' . $createTable['Table'] . ';flush table ' . $createTable['Table']);
		}
	}
	touch($optimizeFile);
}