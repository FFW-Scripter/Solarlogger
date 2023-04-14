SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `inverter` (
                            `serial` varchar(12) NOT NULL,
                            `name` varchar(128) NOT NULL,
                            `power` int(10) UNSIGNED DEFAULT NULL,
                            `yieldday` int(10) UNSIGNED DEFAULT NULL,
                            `temperature` decimal(5,2) DEFAULT NULL,
                            `name_power_0` varchar(128) NOT NULL DEFAULT 'String 1',
                            `name_power_1` varchar(128) NOT NULL DEFAULT 'String 2',
                            `name_power_2` varchar(128) NOT NULL DEFAULT 'String 3',
                            `name_power_3` varchar(128) NOT NULL DEFAULT 'String 4',
                            `name_power_4` varchar(128) NOT NULL DEFAULT 'String 5',
                            `name_power_5` varchar(128) NOT NULL DEFAULT 'String 6'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `inverter__data` (
                                  `serial` varchar(12) CHARACTER SET utf8 NOT NULL,
                                  `power` int(10) UNSIGNED NOT NULL,
                                  `yieldday` int(10) UNSIGNED NOT NULL,
                                  `temperature` decimal(5,2) NOT NULL,
                                  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                  `power_0` int(10) UNSIGNED DEFAULT NULL,
                                  `power_1` int(10) UNSIGNED DEFAULT NULL,
                                  `power_2` int(10) UNSIGNED DEFAULT NULL,
                                  `power_3` int(10) UNSIGNED DEFAULT NULL,
                                  `power_4` int(10) UNSIGNED DEFAULT NULL,
                                  `power_5` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=ascii;


ALTER TABLE `inverter`
    ADD PRIMARY KEY (`serial`),
    ADD KEY `name` (`name`);

ALTER TABLE `inverter__data`
    ADD UNIQUE KEY `serial_2` (`serial`,`timestamp`),
    ADD KEY `serial` (`serial`);
COMMIT;
