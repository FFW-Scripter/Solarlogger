<?php
ob_start('ob_gzhandler');
/** @var \PDO $PDO */
require_once 'DB.php';

$inverter = $PDO->query('Select * from inverter order by name')->fetchAll();
$max_inverter = $PDO->query('SELECT serial, max(power) as max FROM `inverter__data` group by serial')->fetchAll();
$max = array();
foreach ($max_inverter as $m) {
	$max[$m['serial']] = $m['max'];
}
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
	<h3>Inverter</h3>
</section>
<?php if (count($inverter)) {
	foreach ($inverter as $inv) { ?>
		<section class="container mt-3" id="inv_<?= $inv['serial'] ?>">
			<h2>
				<a href="detail.php?inv=<?= $inv['serial'] ?>" style="color: inherit;text-decoration: none"
				   target="_blank">
					<span class="fa fa-solar-panel"></span> <?= $inv['name'] ?>
				</a>
			</h2>

			<div class="row">
				<div class="col-xs-12 col-md-6">
					<div class="alert alert-secondary text-center" id="kwh">
						Heute
						<div class="fa-2x">
							<i class="fa-solid fa-bolt"></i>
							<span><?= number_format($inv['yieldday'] / 1000, 3, ',', '.') ?></span> kW/h
						</div>
					</div>
				</div>
				<div class="col-xs-12 col-md-6" id="pwr">
					<div class="alert alert-secondary text-center">
						<div>
							Aktuell
							<div class="fa-2x">
								<i class="fas fa-solar-panel"></i>
								<span class="now"><?= $inv['power'] ?></span> / <span
										class="max"><?= $max[$inv['serial']] ?></span> W
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
	<?php }
} else { ?>
	<section class="container text-center text-muted">
		Es sind noch keine Inverter vorhanden…
	</section>
<?php } ?>
<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script>
    let data = <?=json_encode($inverter)?>;
    let sections = {};

    for (let i in data) {
        if (data.hasOwnProperty(i)) {
            sections[data[i]['serial']] = $('#inv_' + data[i]['serial']);
        }
    }

    let schedule = window.setInterval(function () {
        $.getJSON('data.php', function (res) {
            for (let i in res.inverter) {
                if (res.inverter.hasOwnProperty(i)) {
                    let inv = res.inverter[i];
                    if (sections[inv.serial]) {
                        $('#kwh span', sections[inv.serial]).text((inv.yieldday / 1000).toLocaleString('de', {
                            minimumFractionDigits: 3,
                            maximumFractionDigits: 3
                        }));
                        $('#pwr .now', sections[inv.serial]).text(inv.power);
                        $('#pwr .max', sections[inv.serial]).text(res.max[inv.serial]);
                    } else {
                        window.location.reload();
                    }
                }
            }
        });
    }, 5000);
</script>
<section class="text-center">
	<a href="https://github.com/FFW-Scripter/Solarlogger" target="_blank">https://github.com/FFW-Scripter/Solarlogger</a>
</section>
</body>
</html>