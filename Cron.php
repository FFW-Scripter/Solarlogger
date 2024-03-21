<?php

require_once 'phpMQTT/phpMQTT.php';

class Cron
{
	/**
	 * @var float|int
	 **/
	// */5 * * * * cd /var/www/solar; && php Cron.php
	private $Runtime = 5 * 60;//in Minuten

	/**
	 * @var array
	 */
	private static $Buffer = array();
	/**
	 * @var array[]
	 */
	private static $model_inverter = array(
		'inverter' => array(
			'serial' => null,
			'name' => null,
			'power' => null,
			'yieldday' => null,
			'temperature' => null,
			'name_power_0' => '',
			'name_power_1' => '',
			'name_power_2' => '',
			'name_power_3' => '',
			'name_power_4' => '',
			'name_power_5' => '',
		),
		'inverter__data' => array(
			'serial' => null,
			'power' => null,
			'yieldday' => null,
			'temperature' => null,
			'power_0' => 0,
			'power_1' => 0,
			'power_2' => 0,
			'power_3' => 0,
			'power_4' => 0,
			'power_5' => 0,
		),
	);

	/**
	 * @var PDO
	 */
	private static $PDO;

	/**
	 * @var string
	 */
	private $Host;

	/**
	 * @var int
	 */
	private $Port;

	/**
	 * @var string
	 */
	private $ClientID = 'SolarLogger';

	/**
	 * @var string
	 */
	private $User;

	/**
	 * @var string
	 */
	private $Password;

	/**
	 * @var string
	 */
	private $Topic = 'solar/#';

	/**
	 * @var \Bluerhinos\phpMQTT
	 */
	private $MQTT;

	/**
	 * @var int
	 */
	private $startTime;

	public function __construct()
	{
		$this->startTime = time();
		ini_set('max_execution_time', $this->Runtime);

		$this->getMQTTConfig();
		$this->connectDB();
		$this->MQTT = new Bluerhinos\phpMQTT($this->Host, $this->Port, $this->ClientID);
		$this->MQTT->debug = false;

		if (!$this->MQTT->connect(true, NULL, $this->User, $this->Password)) {
			exit(1);
		}
		$this->subscribe($this->Topic);
	}

	/**
	 * @param $topic
	 * @param $msg
	 * @return void
	 */
	public static function procMsg($topic, $msg)
	{
		//echo "Topic: {$topic}\n";
		//echo "\t$msg\n\n";

		$t = explode('/', $topic);

		if ($t[1] == 'dtu') {
			if ($t['2'] == 'rssi') {
				self::InsertData(self::$Buffer);
			}
		} elseif (preg_match('/^\d+$/', $t[1])) {
			if (!array_key_exists($t[1], self::$Buffer)) {
				self::$Buffer[$t[1]] = array_merge(self::$model_inverter);
				self::$Buffer[$t[1]]['inverter']['serial'] = $t[1];
				self::$Buffer[$t[1]]['inverter__data']['serial'] = $t[1];
			}

			if ($t[2] == 'name') {
				self::$Buffer[$t[1]]['inverter']['name'] = $msg;
			} else { //MPPT
				if (intval($t[2]) > 0) {
					$inv = intval($t[2]) - 1;
					switch ($t[3]) {
						case 'power':
							self::$Buffer[$t[1]]['inverter__data']['power_' . $inv] = $msg;
							break;
						case 'name':
							self::$Buffer[$t[1]]['inverter']['name_power_' . $inv] = $msg;
					}
				} else {
					switch ($t[3]) {
						case 'yieldday':
							self::$Buffer[$t[1]]['inverter']['yieldday'] = $msg;
							self::$Buffer[$t[1]]['inverter__data']['yieldday'] = $msg;
							break;
						case 'power':
							self::$Buffer[$t[1]]['inverter']['power'] = $msg;
							self::$Buffer[$t[1]]['inverter__data']['power'] = $msg;
							break;
						case 'temperature':
							self::$Buffer[$t[1]]['inverter']['temperature'] = $msg;
							self::$Buffer[$t[1]]['inverter__data']['temperature'] = $msg;
							break;
					}
				}
			}
		}
	}

	/**
	 * @param array $Buffer
	 * @return void
	 */
	private static function InsertData(array $Buffer)
	{
		foreach ($Buffer as $Hoymiles) {
			self::quote($Hoymiles['inverter']);
			self::quote($Hoymiles['inverter__data']);

			$sql = 'INSERT INTO `inverter` (' . implode(', ', array_keys($Hoymiles['inverter'])) . ') 
			VALUES (' . implode(', ', $Hoymiles['inverter']) . ')
			ON  DUPLICATE KEY UPDATE 
			name=' . $Hoymiles['inverter']['name'] . ',
			power=' . $Hoymiles['inverter']['power'] . ',
			yieldday=' . $Hoymiles['inverter']['yieldday'] . ',
			temperature=' . $Hoymiles['inverter']['temperature'];

			self::$PDO->exec($sql);
			self::$PDO->exec('INSERT INTO `inverter__data` 
    		(' . implode(', ', array_keys($Hoymiles['inverter__data'])) . ') VALUES 
			(' . implode(', ', $Hoymiles['inverter__data']) . ')');

			self::$Buffer = array();
		}
	}

	/**
	 * @param array $data
	 */
	private static function quote(array &$data)
	{
		foreach ($data as $k => $v) {
			$data[$k] = self::$PDO->quote($v);
		}
	}

	/**
	 *
	 */
	public function __destruct()
	{
		$this->MQTT->close();
	}

	/**
	 * @param string $Topic
	 * @return void
	 */
	private function subscribe(string $Topic)
	{
		$this->MQTT->subscribe(array($Topic => array('qos' => 0, 'function' => 'Cron::procMsg')));
		while (time() - $this->startTime < $this->Runtime - 1) {
			$this->MQTT->proc();
		}
	}

	/**
	 * @return void
	 */
	private function connectDB()
	{
		$PDO = null;
		require 'DB.php';
		self::$PDO = $PDO;
	}

	/**
	 * @return void
	 */
	private function getMQTTConfig()
	{
		$config_file = "MQTT_data.php";
		$Host = null;
		$Port = null;
		$User = null;
		$Password = null;

		if (is_file($config_file)) {
			include $config_file;
		}

		$this->Host = $Host;
		$this->Port = $Port;
		$this->User = $User;
		$this->Password = $Password;
	}
}

new Cron();