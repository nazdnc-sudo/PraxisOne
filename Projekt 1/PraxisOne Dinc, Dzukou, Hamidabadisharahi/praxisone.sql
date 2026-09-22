-- ============================================================
-- PraxisOne - Datenbankschema (v3)
-- Änderungen: Adresse aufgeteilt, Datum in messungen, Groesse
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `praxisone`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `praxisone`;

CREATE TABLE `patienten` (
  `PID`          INT(11)      NOT NULL AUTO_INCREMENT,
  `Name`         VARCHAR(100) DEFAULT NULL,
  `Vorname`      VARCHAR(100) DEFAULT NULL,
  `Geburtsdatum` DATE         DEFAULT NULL,
  `Strasse`      VARCHAR(100) DEFAULT NULL,
  `Hausnummer`   VARCHAR(10)  DEFAULT NULL,
  `PLZ`          VARCHAR(10)  DEFAULT NULL,
  `Ort`          VARCHAR(100) DEFAULT NULL,
  `Groesse`      DECIMAL(4,1) DEFAULT NULL,
  PRIMARY KEY (`PID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `benutzer` (
  `ID`       INT(11)      NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50)  DEFAULT NULL,
  `PW_Hash`  VARCHAR(255) DEFAULT NULL,
  `Rolle`    ENUM('Pat','Ass','Arzt') DEFAULT NULL,
  `Email`    VARCHAR(100) DEFAULT NULL,
  `Telefon`  VARCHAR(20)  DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `arzt` (
  `AID`      INT(11)     NOT NULL,
  `Nachname` VARCHAR(30) DEFAULT NULL,
  `Vorname`  VARCHAR(30) NOT NULL,
  PRIMARY KEY (`AID`),
  CONSTRAINT `arzt_ibfk_1` FOREIGN KEY (`AID`) REFERENCES `benutzer` (`ID`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `icd_codes` (
  `Code` VARCHAR(10) NOT NULL,
  `Text` TEXT        DEFAULT NULL,
  PRIMARY KEY (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `messungen` (
  `MID`      INT(11)      NOT NULL AUTO_INCREMENT,
  `PID`      INT(11)      DEFAULT NULL,
  `Datum`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `Gewicht`  DECIMAL(5,2) DEFAULT NULL,
  `RR`       VARCHAR(10)  DEFAULT NULL,
  `P`        INT(11)      DEFAULT NULL,
  `Befinden` INT(11)      DEFAULT NULL,
  PRIMARY KEY (`MID`),
  KEY `PID` (`PID`),
  CONSTRAINT `messungen_ibfk_1` FOREIGN KEY (`PID`) REFERENCES `patienten` (`PID`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diag_pro_pat` (
  `DPID`    INT(11)     NOT NULL AUTO_INCREMENT,
  `Pat_ID`  INT(11)     DEFAULT NULL,
  `Arzt_ID` INT(11)     DEFAULT NULL,
  `ICD`     VARCHAR(10) DEFAULT NULL,
  PRIMARY KEY (`DPID`),
  KEY `Pat_ID` (`Pat_ID`), KEY `Arzt_ID` (`Arzt_ID`), KEY `ICD` (`ICD`),
  CONSTRAINT `diag_pro_pat_ibfk_1` FOREIGN KEY (`Pat_ID`)  REFERENCES `patienten` (`PID`),
  CONSTRAINT `diag_pro_pat_ibfk_2` FOREIGN KEY (`Arzt_ID`) REFERENCES `arzt` (`AID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diag_pro_pat_ibfk_3` FOREIGN KEY (`ICD`)     REFERENCES `icd_codes` (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patienten` (`PID`,`Name`,`Vorname`,`Geburtsdatum`,`Strasse`,`Hausnummer`,`PLZ`,`Ort`,`Groesse`) VALUES
(1,'Mustermann','Max',   '1980-03-15','Hauptstraße',  '1',  '89073','Ulm',178.0),
(2,'Müller',    'Anna',  '1975-07-22','Gartenweg',    '5',  '89075','Ulm',165.0),
(3,'Schmidt',   'Klaus', '1968-11-05','Bahnhofstraße','12', '89077','Ulm',182.0),
(4,'Weber',     'Maria', '1990-01-30','Ringstraße',   '3',  '89079','Ulm',162.0),
(5,'Fischer',   'Thomas','1985-09-18','Ulmer Straße', '44', '89081','Ulm',175.0);

INSERT INTO `benutzer` (`ID`,`username`,`PW_Hash`,`Rolle`,`Email`,`Telefon`) VALUES
(1,'max.mustermann', '$2y$10$placeholder_hash_pat1', 'Pat', 'max@example.com',    '0731 111111'),
(2,'anna.mueller',   '$2y$10$placeholder_hash_pat2', 'Pat', 'anna@example.com',   '0731 222222'),
(3,'klaus.schmidt',  '$2y$10$placeholder_hash_pat3', 'Pat', 'klaus@example.com',  '0731 333333'),
(4,'maria.weber',    '$2y$10$placeholder_hash_pat4', 'Pat', 'maria@example.com',  '0731 444444'),
(5,'thomas.fischer', '$2y$10$placeholder_hash_pat5', 'Pat', 'thomas@example.com', '0731 555555'),
(6,'dr.schneider',   '$2y$10$placeholder_hash_arzt1','Arzt','schneider@praxis.de','0731 666666'),
(7,'dr.hoffmann',    '$2y$10$placeholder_hash_arzt2','Arzt','hoffmann@praxis.de', '0731 777777'),
(8,'lisa.klein',     '$2y$10$placeholder_hash_ass1', 'Ass', 'lisa@praxis.de',     '0731 888888'),
(9,'marco.braun',    '$2y$10$placeholder_hash_ass2', 'Ass', 'marco@praxis.de',    '0731 999999');

INSERT INTO `arzt` (`AID`,`Nachname`,`Vorname`) VALUES (6,'Schneider','Peter'),(7,'Hoffmann','Julia');

INSERT INTO `icd_codes` (`Code`,`Text`) VALUES
('J06.9','Akute Infektion der oberen Atemwege, nicht näher bezeichnet'),
('I10',  'Essentielle (primäre) Hypertonie'),
('E11.9','Diabetes mellitus Typ 2 ohne Komplikationen'),
('M54.5','Kreuzschmerz'),
('J45.9','Asthma bronchiale, nicht näher bezeichnet'),
('K29.5','Chronische Gastritis, nicht näher bezeichnet'),
('F32.1','Mittelgradige depressive Episode'),
('Z00.0','Allgemeinuntersuchung ohne Besonderheiten beim Erwachsenen'),
('N39.0','Harnwegsinfektion, Lokalisation nicht näher bezeichnet'),
('G43.9','Migräne, nicht näher bezeichnet');

INSERT INTO `diag_pro_pat` (`DPID`,`Pat_ID`,`Arzt_ID`,`ICD`) VALUES
(1,1,6,'I10'),(2,1,7,'M54.5'),(3,2,6,'J06.9'),(4,2,7,'E11.9'),
(5,3,6,'K29.5'),(6,3,7,'F32.1'),(7,4,6,'J45.9'),(8,4,7,'Z00.0'),
(9,5,6,'N39.0'),(10,5,7,'G43.9');

INSERT INTO `messungen` (`MID`,`PID`,`Datum`,`Gewicht`,`RR`,`P`,`Befinden`) VALUES
(1,1,'2026-04-01 08:00:00',78.50,'145/95', 88,2),
(2,1,'2026-04-04 08:15:00',78.00,'140/90', 85,3),
(3,1,'2026-04-07 09:00:00',77.80,'138/88', 82,3),
(4,2,'2026-04-01 10:00:00',68.50,'120/80', 72,4),
(5,2,'2026-04-04 10:30:00',67.80,'118/76', 70,4),
(6,2,'2026-04-07 11:00:00',68.10,'122/82', 74,3),
(7,3,'2026-04-01 07:30:00',92.30,'155/100',92,1),
(8,3,'2026-04-04 07:45:00',91.50,'150/95', 88,2),
(9,4,'2026-04-01 09:00:00',55.10,'110/70', 65,5),
(10,4,'2026-04-04 09:15:00',55.30,'112/72', 66,5),
(11,5,'2026-04-01 08:00:00',83.70,'130/85', 78,3),
(12,5,'2026-04-04 08:30:00',84.00,'132/86', 80,3);

COMMIT;
