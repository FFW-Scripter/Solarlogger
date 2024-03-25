<?php
chdir(__DIR__);

require_once 'phpMQTT/phpMQTT.php';

class Cron
{
	/**
	 * @var float|int
	 **/
	// */5 * * * * cd /var/www/solar; && php Cron.php
	private $Runtime = 300;//in Sekunden

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
	private $ClientID;

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

	/**
	 *
	 */
	public function __construct()
	{
		$this->startTime = time();
		ini_set('max_execution_time', $this->Runtime);

		$this->getMQTTConfig();
		if (isset($this->Host)) {
			$this->connectDB();

			if (date('H') == 0) {
				self::optimizeTable();
			}

			$this->MQTT = new Bluerhinos\phpMQTT($this->Host, $this->Port, $this->ClientID);
			$this->MQTT->debug = false;

			if (!$this->MQTT->connect(true, NULL, $this->User, $this->Password)) {
				echo 'Konnte nicht zu Broker Verbinden!';
				exit;
			}
			$this->subscribe($this->Topic);
		} else {
			echo 'Kein Broker definiert - Config MQTT_data.php gesetzt?';
			exit;
		}
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

		if ($t[1] == 'dc' && $t['2'] == 'is_valid') {
			self::InsertData(self::$Buffer);
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
			(' . implode(', ', $Hoymiles['inverter__data']) . ')
			ON  DUPLICATE KEY UPDATE 
				power=' . $Hoymiles['inverter__data']['power'] . ',
				yieldday=' . $Hoymiles['inverter__data']['yieldday'] . ',
				temperature=' . $Hoymiles['inverter__data']['temperature'] . ',
				power_0=' . $Hoymiles['inverter__data']['power_0'] . ',
				power_1=' . $Hoymiles['inverter__data']['power_1'] . ',
				power_2=' . $Hoymiles['inverter__data']['power_2'] . ',
				power_3=' . $Hoymiles['inverter__data']['power_3'] . ',
				power_4=' . $Hoymiles['inverter__data']['power_4'] . ',
				power_5=' . $Hoymiles['inverter__data']['power_5'] . '
			');

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
		$Topic = null;
		$ClientID = null;
		$Runtime = null;

		if (is_file($config_file)) {
			include $config_file;
		}

		$this->Host = $Host;
		$this->Port = $Port;
		$this->User = $User;
		$this->Password = $Password;
		$this->Topic = $Topic;
		$this->ClientID = $ClientID;
		$this->Runtime = $Runtime;
	}

	public static function optimizeTable()
	{
		$optimizeFile = './optimize';
		if (!file_exists($optimizeFile)) {
			touch($optimizeFile);
		}
		if (time() - filemtime($optimizeFile) > 24 * 60 * 60) {
			unlink($optimizeFile);
			echo "\nOptimiere Tabelle";

			//Wenn es mehr als eine Zeile pro Minute gibt -> Optimieren
			$createTable = self::$PDO->query('SHOW CREATE TABLE inverter__data')->fetch();
			$createTable_sql = str_replace($createTable['Table'], $createTable['Table'] . '_tmp', $createTable['Create Table']);
			self::$PDO->exec($createTable_sql);

			if (self::$PDO->errorCode() == 0) {
				self::$PDO->exec('Insert into ' . $createTable['Table'] . '_tmp Select serial,
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

				if (self::$PDO->errorCode() == 0) {
					self::$PDO->exec('truncate table ' . $createTable['Table']);
					self::$PDO->exec('Insert into ' . $createTable['Table'] . ' Select * from ' . $createTable['Table'] . '_tmp');
					self::$PDO->exec('drop table ' . $createTable['Table'] . '_tmp');
					self::$PDO->exec('optimize table ' . $createTable['Table'] . ';flush table ' . $createTable['Table']);
				}
			}
			touch($optimizeFile);
		}
	}
}

new Cron();