<?php
ob_start('ob_gzhandler');
header('Content-Type: application/json;charset=utf-8');

/** @var \PDO $PDO */
require_once 'DB.php';

$inverter = $PDO->query('Select * from inverter order by name')->fetchAll();
$max_inverter = $PDO->query('SELECT serial, max(power) as max FROM `inverter__data` group by serial')->fetchAll();
$max = array();
foreach ($max_inverter as $m) {
	$max[$m['serial']] = intval($m['max']);
}

if (array_key_exists('date', $_GET)) {
	$time = strtotime($_GET['date']);
} else {
	$time = time();
}
$date = date('Y-m-d', $time);

$detail = array();
$total = array();
if (array_key_exists('inv', $_GET)) {
	$data = $PDO->query('Select date_format(timestamp, \'%H:%i\') as ts,
       avg(power)                      as power,
       avg(temperature)                as temperature,
       avg(power_0)                    as power_0,
       avg(power_1)                    as power_1,
       avg(power_2)                    as power_2,
       avg(power_3)                    as power_3,
       avg(power_4)                    as power_4,
       avg(power_5)                    as power_5
from inverter__data
where power > 0 
  and serial = ' . $PDO->quote($_GET['inv']) . '
  and date_format(timestamp, \'%Y-%m-%d\') = ' . $PDO->quote($date) . '
group by ts');

	$detail['labels'] = array();
	$detail['chart'] = array(
		'Leistung AC' => array(),
		'Temperatur' => array(),
	);
	$detail['chart1'] = array(
		'power_0' => array(),
		'power_1' => array(),
		'power_2' => array(),
		'power_3' => array(),
		'power_4' => array(),
		'power_5' => array(),
	);

	foreach ($data as $d) {
		$detail['labels'][] = $d['ts'];
		$detail['chart']['Leistung AC'][] = intval($d['power']);
		$detail['chart']['Temperatur'][] = floatval($d['temperature']);

		$detail['chart1']['power_0'][] = floatval($d['power_0']);
		$detail['chart1']['power_1'][] = floatval($d['power_1']);
		$detail['chart1']['power_2'][] = floatval($d['power_2']);
		$detail['chart1']['power_3'][] = floatval($d['power_3']);
		$detail['chart1']['power_4'][] = floatval($d['power_4']);
		$detail['chart1']['power_5'][] = floatval($d['power_5']);
	}

	//leere Strings ausblenden
	foreach ($detail['chart1'] as $k => $v) {
		if (array_sum($v) == 0) {
			unset($detail['chart1'][$k]);
		}
	}

	$total_raw = $PDO->query('Select 
       date_format(timestamp, \'%d.%m.%Y\') as date,
       max(yieldday)                      as yieldday
from inverter__data
where serial = ' . $PDO->quote($_GET['inv']) . '
group by date
order by date')->fetchAll();

	$total['labels'] = array();
	$total['data'] = array();

	$total_sum = 0;
	foreach ($total_raw as $k => $v) {
		$yd = $v['yieldday'] / 1000;
		$total['labels'][] = $v['date'];
		$total['data'][] = $yd;
		$total_sum += $yd;
	}
	$detail['yieldtotal'] = $total_sum;
}

echo json_encode(array(
	'inverter' => $inverter,
	'max' => $max,
	'detail' => $detail,
	'total' => $total,
));