<?php
ob_start('ob_gzhandler');

/** @var \PDO $PDO */
require_once 'DB.php';

$inv = $_GET['inv'];
$inverter = $PDO->query('Select * from inverter where serial = ' . $PDO->quote($inv))->fetch();
$max = $PDO->query('SELECT serial, max(power) as max FROM `inverter__data` group by serial')->fetch();
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

	<title>Solarlogger | Detail: <?= $inverter['name'] ?></title>
	<link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap-reboot.min.css">
	<link rel="stylesheet" type="text/css" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
</head>
<body>
<section class="container mt-3">
	<a href="./" class="btn btn-primary"><i class="fa fa-arrow-left"></i> zur Übersicht</a>
</section>
<section class="container mt-3" id="inv_<?= $inverter['serial'] ?>">
	<h2>
		<span class="fa fa-solar-panel"></span> <?= $inverter['name'] ?>
	</h2>

	<div class="row">
		<div class="col-xs-12 col-md-6">
			<div class="alert alert-secondary text-center" id="kwh">
				Heute
				<div class="fa-2x">
					<i class="fa-solid fa-bolt"></i>
					<span><?= number_format($inverter['yieldday'] / 1000, 3, ',', '.') ?></span> kW/h
				</div>
			</div>
		</div>
		<div class="col-xs-12 col-md-6" id="pwr">
			<div class="alert alert-secondary text-center">
				<div>
					Aktuell
					<div class="fa-2x">
						<i class="fas fa-solar-panel"></i>
						<span class="now"><?= $inverter['power'] ?></span> / <span
								class="max"><?= $max['max'] ?></span> W
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12 col-md-6">
			<div class="alert alert-secondary text-center" id="kwhtotal">
				kWh Total
				<div class="fa-2x">
					<i class="fa-solid fa-calendar-alt"></i>
					<span> 0</span> kW/h
				</div>
			</div>
		</div>
		<div class="col-xs-12 col-md-6" id="temp">
			<div class="alert alert-secondary text-center">
				<div>
					Temperatur
					<div class="fa-2x">
						<i class="fas fa-temperature-empty"></i>
						<span><?= number_format($inverter['temperature'], 2, ',', '.') ?></span> °C
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<section class="container mt-3">
	<div class="row">
		<div class="col-12">
			<div class="alert alert-secondary text-center">
				<canvas id="chart"></canvas>
			</div>
		</div>
	</div>
</section>

<section class="container mt-3">
	<div class="row">
		<div class="col-12">
			<div class="alert alert-secondary text-center">
				<canvas id="chart1"></canvas>
			</div>
		</div>
	</div>
</section>

<section class="container mt-3">
	<div class="row">
		<div class="col-12">
			<div class="alert alert-secondary text-center">
				<canvas id="chart2"></canvas>
			</div>
		</div>
	</div>
</section>
<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="node_modules/chart.js/dist/chart.umd.js"></script>
<script>
    let data = <?=json_encode($inverter)?>;
    let sections = {};
    sections[data.serial] = $('#inv_' + data.serial);
    let colors = ['#d9534f', '#428bca', '#5cb85c', '#7e4710', '#5bc0de', '#333333'];

    let chart = new Chart(
        document.getElementById('chart'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Leistung',
                        data: [],
                        borderColor: colors[1],
                        backgroundColor: colors[1],
                        yAxisID: 'y',
                    },
                    {
                        label: 'Temperatur',
                        data: [],
                        borderColor: colors[0],
                        backgroundColor: colors[0],
                        yAxisID: 'y1',
                        fill: true,
                    }
                ]
            },
            options: {
                responsive: true,
                hoverMode: 'index',
                stacked: false,
                animation: {
                    duration: 1000,
                    easing: 'linear'
                },
                title: {
                    display: false,
                },
                tooltips: {
                    mode: 'index',
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',

                        // grid line settings
                        grid: {
                            drawOnChartArea: false, // only want the grid lines for one axis to show up
                        },
                    },
                }
            }
        }
    );

    let chart1 = new Chart(
        document.getElementById('chart1'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                hoverMode: 'index',
                stacked: false,
                animation: {
                    duration: 1000,
                    easing: 'linear'
                },
                title: {
                    display: false,
                },
                tooltips: {
                    mode: 'index',
                }
            }
        }
    );


    let chart2 = new Chart(
        document.getElementById('chart2'),
        {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'kWh Täglich',
                    data: [],
                    borderColor: colors[1],
                    backgroundColor: colors[1],
                }]
            },
            options: {
                responsive: true,
                hoverMode: 'index',
                stacked: false,
                animation: {
                    duration: 1000,
                    easing: 'linear'
                },
                title: {
                    display: false,
                },
                tooltips: {
                    mode: 'index',
                }
            }
        }
    );

    function update() {
        $.getJSON('data.php?inv=' + data.serial, function (res) {
            for (let i in res.inverter) {
                if (res.inverter.hasOwnProperty(i)) {
                    let inv = res.inverter[i];
                    if (sections[inv.serial]) {
                        $('#kwh span', sections[inv.serial]).text((inv.yieldday / 1000).toLocaleString('de', {
                            minimumFractionDigits: 3,
                            maximumFractionDigits: 3
                        }));
                        $('#kwhtotal span', sections[inv.serial]).text(res.detail.yieldtotal.toLocaleString('de', {
                            minimumFractionDigits: 3,
                            maximumFractionDigits: 3
                        }));
                        $('#temp span', sections[inv.serial]).text(parseFloat(inv.temperature).toLocaleString('de', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $('#pwr .now', sections[inv.serial]).text(inv.power);
                        $('#pwr .max', sections[inv.serial]).text(res.max[inv.serial]);
                    }
                }
            }

            if (res.detail) {
                chart.data.labels = res.detail.labels;
                chart.data.datasets[0].data = res.detail.chart['Leistung AC'];
                chart.data.datasets[1].data = res.detail.chart['Temperatur'];
                chart.update();

                chart1.data.labels = res.detail.labels;
                let i = 0;
                for (let name in res.detail.chart1) {
                    if (res.detail.chart1.hasOwnProperty(name)) {
                        if (!chart1.data.datasets[i]) {
                            chart1.data.datasets.push({
                                label: data['name_' + name],
                                data: [],
                                borderColor: colors[i],
                                backgroundColor: colors[i],
                            });
                        }
                        chart1.data.datasets[i].data = res.detail.chart1[name];
                        i++;
                    }
                }
                chart1.update();

                chart2.data.labels = res.total.labels;
                chart2.data.datasets[0].data = res.total.data;
                chart2.update();
            }
        });
    }

    let schedule = window.setInterval(function () {
        update();
    }, 5000);

    update();
</script>
</body>
</html>