<?php
ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', true);

class install
{
	/**
	 * @var string
	 */
	private static $installDir = './';

	/**
	 * @var string
	 */
	private $node_modules = 'node_modules';

	/**
	 * @var string
	 */
	private $npm_url = 'https://registry.npmjs.com/';

	/**
	 *
	 */
	public static function init()
	{
		$install = new install();
		if ($install->writeCheck(self::$installDir)) {
			if (!$install->getDependencies('package.json')) {
				exit;
			}
		}
	}

	/**
	 * @param $dir
	 * @return bool
	 */
	private function writeCheck($dir)
	{
		if (is_writable($dir)) {
			return true;
		} else {
			echo 'Keine Schreibrechte!';
			return false;
		}
	}

	/**
	 * @param $package
	 * @return bool
	 */
	private function getDependencies($package)
	{
		if (!is_dir(self::$installDir . $this->node_modules)) {
			mkdir(self::$installDir . $this->node_modules);
			if (is_file($package)) {
				$dep = json_decode(file_get_contents($package), true);
				if (is_array($dep) && array_key_exists('dependencies', $dep)) {
					foreach ($dep['dependencies'] as $module => $version) {
						$version = trim($version, '<>=~^');
						$info = json_decode(file_get_contents($this->npm_url . $module), true);
						if (is_array($info) && array_key_exists('versions', $info)) {
							if (array_key_exists($version, $info['versions'])) {
								$data = $info['versions'][$version];
								if (!$this->getPackage($data['dist']['tarball'], $module)) {
									echo 'Problem beim holen von: ' . $module;
									break;
								}
							} else {
								echo 'Keine passende Version gefunden: ' . $module . ' > ' . $version;
								return false;
							}

						} else {
							echo 'Keine NPM Info für: ' . $module;
							return false;
						}
					}
				} else {
					echo 'package.json fehlerhaft!';
					return false;
				}
			} else {
				echo 'package.json nicht gefunden!';
				return false;
			}
			return true;
		}
		return true;
	}

	/**
	 * @param $tarball
	 * @param $module
	 * @return bool
	 */
	private function getPackage($tarball, $module)
	{
		$file = self::$installDir . pathinfo($tarball, PATHINFO_BASENAME);

		if (file_put_contents($file, file_get_contents($tarball))) {
			$phar = new PharData($file);
			if ($phar->isCompressed()) {
				$phar->decompress();
				$f = pathinfo($file, PATHINFO_FILENAME) . '.tar';
				unlink($file);
				$file = $f;
				$phar = new PharData($file);
			}
			if ($phar->extractTo(self::$installDir . $this->node_modules)) {
				$module_dir = self::$installDir . $this->node_modules . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $module);
				if (!is_dir(dirname($module_dir))) {
					mkdir(dirname($module_dir));
				}
				rename(self::$installDir . $this->node_modules . DIRECTORY_SEPARATOR . 'package', $module_dir);
				unlink($file);
				return true;
			} else {
				echo 'Problem beim Entpacken von: ' . $tarball . "\n";
			}
		}
		return false;
	}

	public static function setDB(array $data)
	{
		if (!strlen($data['dbname'])) {
			echo 'Kein Datenbankname angegeben!';
		} else {
			foreach ($data as $k => $v) {
				$data[$k] = str_replace("'", "\'", $v);
			}

			$config = '<?php
$host = \'' . $data['host'] . '\';
$dbname = \'' . $data['dbname'] . '\';
$user = \'' . $data['user'] . '\';
$pass = \'' . $data['pass'] . '\';
';

			if (file_put_contents('DB_data.php', $config)) {
				$PDO = null;
				$install = true;
				require 'DB.php';
				if ($PDO instanceof PDO) {
					echo 'Verbindung OK';
				} else {
					unlink('DB_data.php');
				}
			} else {
				echo 'Keine Schreibrechte!';
			}
		}
		exit;
	}

	public static function setMQTT(array $data)
	{
		if (!strlen($data['mqtt_host'])) {
			echo 'Keinen Broker angegeben!';
		} else {
			foreach ($data as $k => $v) {
				$data[$k] = str_replace("'", "\'", $v);
			}

			$config = '<?php
$Host = \'' . $data['mqtt_host'] . '\';
$Port = \'' . $data['mqtt_port'] . '\';
$User = \'' . $data['mqtt_user'] . '\';
$Password = \'' . $data['mqtt_pass'] . '\';
';

			if (file_put_contents('MQTT_data.php', $config)) {
				require_once 'phpMQTT/phpMQTT.php';
				$MQTT = new Bluerhinos\phpMQTT($data['mqtt_host'], $data['mqtt_port'], 'SolarLogger');
				if ($MQTT->connect(true, NULL, $data['mqtt_user'], $data['mqtt_pass'])) {
					echo 'Verbindung OK und Config gespeichert!';
				} else {
					unlink('MQTT_data.php');
				}
			} else {
				echo 'Keine Schreibrechte!';
			}
		}
		exit;
	}

	/**
	 * @param $sql
	 */
	public static function copyDB($sql)
	{
		$PDO = null;
		require 'DB.php';
		if ($PDO instanceof PDO) {
			$exec = $PDO->query(file_get_contents($sql));
			if ($exec->errorCode() == 0) {
				echo 'Datenbank kopiert!';
			} else {
				print_r($PDO->errorInfo());
			}
		}
		exit;
	}
}

if (array_key_exists('host', $_POST)) {
	install::setDB($_POST);
} elseif (array_key_exists('mqtt_host', $_POST)) {
	install::setMQTT($_POST);
} elseif (array_key_exists('copy', $_POST)) {
	install::copyDB('helper/solar.sql');
} else {
	install::init();
}

$importURL = 'http' . (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] == 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/import.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="msapplication-starturl" content="/">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="apple-touch-icon" sizes="180x180" href="./favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./favicon/favicon-16x16.png">
    <link rel="manifest" href="./favicon/site.webmanifest">
    <link rel="mask-icon" href="./favicon/safari-pinned-tab.svg" color="#77b433">
    <link rel="shortcut icon" href="./favicon/favicon.ico">
    <meta name="msapplication-TileColor" content="#77b433">
    <meta name="msapplication-config" content="./favicon/browserconfig.xml">
    <meta name="theme-color" content="#77b433">

    <title>Solarlogger</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap-reboot.min.css">
    <link rel="stylesheet" type="text/css" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
</head>
<body>
<section class="container">
    <h1>Installer</h1>
    <div class="row">
        <div class="col-12 alert alert-secondary" id="MySQL">
            <h3>Datenbank:</h3>
            <div class="input-group mb-3">
                <label for="host" class="input-group-text">Host</label>
                <input id="host" type="text" class="form-control" placeholder="localhost" value="localhost">
            </div>
            <div class="input-group mb-3">
                <label for="dbname" class="input-group-text">Datenbankname</label>
                <input id="dbname" type="text" class="form-control">
            </div>
            <div class="input-group mb-3">
                <label for="user" class="input-group-text">User</label>
                <input id="user" type="text" class="form-control">
            </div>
            <div class="input-group mb-3">
                <label for="pass" class="input-group-text">Passwort</label>
                <input id="pass" type="password" class="form-control">
            </div>
            <button class="btn btn-primary btn-lg" id="checkMySQL">MySQL Verbindung testen</button>
            <button class="ml-3 btn btn-primary btn-lg" id="copyDB" disabled="disabled">Datenbank einrichten</button>
            <pre id="meldung" class="mt-3 text-muted"></pre>
        </div>
        <div class="col-12 alert alert-secondary" id="MQTT">
            <h3>MQTT:</h3>
            <div class="input-group mb-3">
                <label for="mqtt_host" class="input-group-text">Broker</label>
                <input id="mqtt_host" type="text" class="form-control" placeholder="localhost" value="localhost">
            </div>
            <div class="input-group mb-3">
                <label for="mqtt_port" class="input-group-text">Port</label>
                <input id="mqtt_port" type="text" class="form-control" placeholder="1883" value="1883">
            </div>
            <div class="input-group mb-3">
                <label for="mqtt_user" class="input-group-text">User</label>
                <input id="mqtt_user" type="text" class="form-control">
            </div>
            <div class="input-group mb-3">
                <label for="mqtt_pass" class="input-group-text">Passwort</label>
                <input id="mqtt_pass" type="password" class="form-control">
            </div>
            <button class="btn btn-primary btn-lg" id="checkMQTT">MQTT Verbindung testen</button>
            <pre id="meldung" class="mt-3 text-muted"></pre>
        </div>
    </div>
</section>
<section id="info" class="container">
    <div class="row">
        <div class="col-12 alert alert-secondary">
            <h2 class="text-success mb-5">Super, die Installation ist abgeschlossen :-)</h2>
            <h3 class="mb-3">Wie geht es jetzt weiter?</h3>
            <p>Die Daten aus der <a href="https://github.com/tbnobody/OpenDTU" target="_blank">openDTU</a> müssen jetzt
                in die Datenbank. Dafür gibt es die <code>import.php</code>.<br>
                Die erste Möglichkeit ist es, die <code>import.php</code> mit dem GET Parameter ip=[DTU-IP]
                aufzurufen:<br><br>
                <kbd>
					<?= $importURL ?>?ip=192.168.x.x
                </kbd>
            </p>
            <p>
                Die zweite Möglichkeit ist, auf einem Server innerhalb des Netzwerkes ein kleines Shell-/Batch-Script
                laufen zu lassen.<br>
            </p>
            <i>data.sh</i>
            <pre class="bg-dark p-3" style="border-radius: 0.25rem"><kbd
                        class="p-0"><?= trim(str_replace('[importURL]', $importURL, file_get_contents('helper/data.sh'))) ?></kbd></pre>
            <br>
            <i>data.bat</i><br>
            <pre class="bg-dark p-3" style="border-radius: 0.25rem"><kbd
                        class="p-0"><?= trim(str_replace('[importURL]', $importURL, file_get_contents('helper/data.sh'))) ?></kbd></pre>
            <p>
                Dabei werden die JSON-Daten aus der openDTU zwischengespeichert und anschließend via POST an die <code>import.php</code>
                gesendet.
            </p>
            <p>
                <i>Übrigens:</i><br>
                Die Namen der Inverter/Strings werden aus der openDTU übernommen und auch in den Charts verwendet.<br>
                Es können auch mehrere openDTUs in die gleiche Datenbank eingespielt werden.
            </p>
            <p>
                <b>Zum Schluss:</b><br>
                Lösche dieses Script <code>install.php</code> zur Sicherheit bitte aus dem Hauptverzeichniss.<br>
                Die Installation ist damit abgeschlossen, hier geht es zur Hauptseite:<br>
            </p>
            <div class="text-center">
                <a href="./" class="btn btn-success btn-lg">zur Hauptseite</a>
            </div>
        </div>
    </div>
</section>
<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script>
    $('#checkMySQL').on('click', function () {
        let me = $(this);
        me.prop('disabled', true);
        let data = {
            host: $('#host').val(),
            dbname: $('#dbname').val(),
            user: $('#user').val(),
            pass: $('#pass').val()
        }
        $.post('install.php', data, function (res) {
            me.prop('disabled', false);
            $('#meldung').text(res);
            me.removeClass('btn-primary btn-success btn-danger');
            if (res === 'Verbindung OK') {
                me.addClass('btn-success');
                $('#copyDB').prop('disabled', false);
            } else {
                me.addClass('btn-danger');
                $('#copyDB').prop('disabled', true);
            }
        })
    });

    $('#copyDB').on('click', function () {
        let me = $(this);
        me.prop('disabled', true);
        $('#checkMySQL').prop('disabled', true);
        $.post('install.php', {copy: 1}, function (res) {
            $('#meldung').text(res);

            if (res === 'Datenbank kopiert!') {
                $('#info').slideDown();
            } else {
                me.prop('disabled', false);
            }
        });
    });

    $('#checkMQTT').on('click', function () {
        let me = $(this);
        me.prop('disabled', true);
        let data = {
            mqtt_host: $('#mqtt_host').val(),
            mqtt_port: $('#mqtt_port').val(),
            mqtt_user: $('#mqtt_user').val(),
            mqtt_pass: $('#mqtt_pass').val()
        }
        $.post('install.php', data, function (res) {
            me.prop('disabled', false);
            $('#meldung').text(res);
            me.removeClass('btn-primary btn-success btn-danger');
            if (res.indexOf('Verbindung OK') !== -1) {
                me.addClass('btn-success');
                $('#copyDB').prop('disabled', false);
            } else {
                me.addClass('btn-danger');
                $('#copyDB').prop('disabled', true);
            }
        })
    });
</script>
</body>
</html>

