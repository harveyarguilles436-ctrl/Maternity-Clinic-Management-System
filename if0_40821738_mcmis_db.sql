-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql205.infinityfree.com
-- Generation Time: Mar 10, 2026 at 09:55 PM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40821738_mcmis_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `service` varchar(100) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `cancel_reason` varchar(255) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(50) DEFAULT NULL,
  `down_payment_status` varchar(50) DEFAULT 'Pending',
  `payment_mode` varchar(20) DEFAULT 'DownPayment'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appointment_id`, `patient_id`, `appointment_date`, `appointment_time`, `service`, `status`, `created_at`, `cancel_reason`, `payment_proof`, `reference_no`, `payment_reference`, `down_payment_status`, `payment_mode`) VALUES
(1, 48, '2026-01-26', '11:00:00', 'NCP - Newborn Care Package', 'Completed', '2026-01-26 10:45:34', NULL, 'Digital_Transaction', 'PAYID-OTKHS5RQR', NULL, 'Paid', 'DownPayment'),
(2, 49, '2026-01-26', '12:00:00', 'MCP01 - Maternity Care Package', 'Completed', '2026-01-26 11:24:38', NULL, 'Digital_Transaction', 'PAYID-0WNJO8QPA', NULL, 'Paid', 'DownPayment'),
(3, 49, '2026-01-26', '12:00:00', 'ANC01 - Antenatal Care Package', 'Completed', '2026-01-26 11:31:46', NULL, 'Digital_Transaction', 'PAYID-VL5MDF84U', NULL, 'Paid', 'DownPayment'),
(4, 49, '2026-01-26', '18:00:00', 'MCP01 - Maternity Care Package', 'Completed', '2026-01-26 17:50:27', NULL, 'Digital_Transaction', 'PAYID-2OWYXE1NC', NULL, 'Paid', 'DownPayment'),
(5, 50, '2026-01-27', '06:00:00', 'ANC02 - Antenatal Care + Monitoring', 'Cancelled', '2026-01-26 18:22:02', 'Schedule Conflict', 'Digital_Transaction', 'PAYID-M9RNT3L5P', NULL, 'Paid', 'DownPayment'),
(6, 49, '2026-01-27', '06:00:00', 'ANC02 - Antenatal Care + Monitoring', 'Cancelled', '2026-01-26 18:22:44', 'Emergency', 'Digital_Transaction', 'PAYID-CLLV9YA5B', NULL, 'Paid', 'DownPayment'),
(7, 48, '2026-01-27', '06:00:00', 'NCP - Newborn Care Package', 'Cancelled', '2026-01-26 18:26:26', 'Financial Issue', 'Digital_Transaction', 'PAYID-B2EO7E5XQ', NULL, 'Paid', 'DownPayment'),
(8, 51, '2026-01-27', '06:00:00', 'NCP - Newborn Care Package', 'Confirmed', '2026-01-26 18:29:05', NULL, 'Digital_Transaction', 'PAYID-IST52TPK9', NULL, 'Paid', 'DownPayment'),
(9, 48, '2026-01-26', '19:00:00', 'NCP - Newborn Care Package', 'Completed', '2026-01-26 18:40:05', NULL, 'Digital_Transaction', 'PAYID-R9K0XI431', NULL, 'Paid', 'DownPayment'),
(10, 48, '2026-01-29', '23:00:00', 'NCP - Newborn Care Package', 'Completed', '2026-01-29 22:39:02', NULL, NULL, NULL, NULL, 'Paid', 'DownPayment'),
(11, 48, '2026-02-01', '18:00:00', 'ANC01 - Antenatal Care Package', 'Completed', '2026-02-01 17:40:15', NULL, 'Digital_Transaction', 'PAYID-I20JK8II6', NULL, 'Paid', 'DownPayment'),
(12, 48, '2026-02-02', '14:00:00', 'ANC01 - Antenatal Care Package', 'Arrived', '2026-02-02 13:39:07', NULL, 'Digital_Transaction', 'PAYID-7JOYJYYCZ', NULL, 'Paid', 'DownPayment'),
(13, 50, '2026-02-02', '14:00:00', 'MCP01 - Maternity Care Package', 'Arrived', '2026-02-02 13:41:41', NULL, 'Digital_Transaction', 'PAYID-SUXMAI5GH', NULL, 'Paid', 'DownPayment'),
(14, 49, '2026-02-02', '14:00:00', 'NSD01 - Normal Spontaneous Delivery', 'Arrived', '2026-02-02 13:42:58', NULL, 'Digital_Transaction', 'PAYID-8JDELIPWP', NULL, 'Paid', 'DownPayment'),
(15, 49, '2026-02-03', '13:00:00', 'ANC02 - Antenatal Care + Monitoring', 'Arrived', '2026-02-03 12:20:53', NULL, NULL, NULL, NULL, 'Paid', 'DownPayment'),
(16, 50, '2026-02-03', '18:00:00', 'NCP - Newborn Care Package', 'Arrived', '2026-02-03 17:04:08', NULL, NULL, NULL, NULL, 'Paid', 'DownPayment'),
(17, 53, '2026-02-06', '12:00:00', 'General Consultation', 'Arrived', '2026-02-06 11:09:58', NULL, NULL, NULL, NULL, 'Pending', 'DownPayment'),
(18, 50, '2026-02-06', '12:00:00', 'MCP01 - Maternity Care Package', 'Arrived', '2026-02-06 11:13:44', NULL, NULL, NULL, NULL, 'Paid', 'DownPayment'),
(19, 54, '2026-03-09', '20:00:00', 'ANC01 - Antenatal Care Package', 'Cancelled', '2026-03-09 19:21:27', 'Schedule Conflict', 'Digital_Transaction', 'PAYID-2RHT7R8YM', NULL, 'Paid', 'DownPayment');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Unpaid','Pending','Paid','Rejected') DEFAULT 'Unpaid',
  `created_at` datetime DEFAULT current_timestamp(),
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `proof_image` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(50) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`bill_id`, `patient_id`, `bill_date`, `total_amount`, `payment_method`, `status`, `created_at`, `paid_amount`, `proof_image`, `payment_reference`, `reference_no`) VALUES
(1, 48, '2026-01-26', '2500.00', 'GCash (Ref: PAYID-04JM3S8W0)', 'Paid', '2026-01-26 10:48:00', '2500.00', NULL, NULL, NULL),
(2, 48, '2026-01-26', '0.00', 'PhilHealth', 'Paid', '2026-01-26 10:48:33', '0.00', NULL, NULL, NULL),
(3, 49, '2026-01-26', '6250.00', 'GCash (Ref: PAYID-EBGAQYQKU)', 'Paid', '2026-01-26 11:26:55', '6250.00', NULL, NULL, NULL),
(4, 49, '2026-01-26', '0.00', 'PhilHealth', 'Paid', '2026-01-26 11:35:15', '0.00', NULL, NULL, NULL),
(5, 49, '2026-01-26', '12500.00', 'GCash (Ref: PAYID-918EL5979)', 'Paid', '2026-01-26 17:58:23', '12500.00', NULL, NULL, NULL),
(6, 49, '2026-01-26', '0.00', 'PhilHealth', 'Paid', '2026-01-26 17:59:34', '0.00', NULL, NULL, NULL),
(7, 48, '2026-01-26', '5000.00', 'Cash', 'Paid', '2026-01-26 18:42:57', '5000.00', NULL, NULL, NULL),
(8, 48, '2026-02-04', '750.00', 'Cash', 'Paid', '2026-02-04 21:05:03', '750.00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `id` int(11) NOT NULL,
  `blocked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT 'No Staff Available',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_records`
--

CREATE TABLE `consultation_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_records`
--

CREATE TABLE `delivery_records` (
  `delivery_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `midwife_id` int(11) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `delivery_time` time DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `weight_g` int(11) DEFAULT NULL,
  `length_cm` decimal(5,2) DEFAULT NULL,
  `apgar_1min` int(11) DEFAULT NULL,
  `apgar_5min` int(11) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `einc_dry` tinyint(1) DEFAULT 0,
  `einc_ssc` tinyint(1) DEFAULT 0,
  `einc_cord` tinyint(1) DEFAULT 0,
  `einc_breast` tinyint(1) DEFAULT 0,
  `partograph_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `delivery_records`
--

INSERT INTO `delivery_records` (`delivery_id`, `patient_id`, `midwife_id`, `delivery_date`, `delivery_time`, `sex`, `weight_g`, `length_cm`, `apgar_1min`, `apgar_5min`, `medications`, `findings`, `next_visit`, `created_at`, `einc_dry`, `einc_ssc`, `einc_cord`, `einc_breast`, `partograph_used`) VALUES
(35, 49, 37, '2026-01-26', '11:25:00', 'Male', 3200, '45.00', 1, 6, '0', '', '2026-02-26', '2026-01-26 03:26:19', 1, 1, 1, 1, 0),
(36, 49, 37, '2026-01-26', '17:56:00', 'Male', 3200, '50.00', 1, 5, '0', '', '2026-02-05', '2026-01-26 09:57:33', 1, 1, 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `family_planning_records`
--

CREATE TABLE `family_planning_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `method_discussed` varchar(255) DEFAULT NULL,
  `method_chosen` varchar(255) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `date_submitted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `patient_id`, `message`, `reply`, `date_submitted`) VALUES
(2, 54, 'Very Good', 'thanks', '2026-03-09 19:30:47');

-- --------------------------------------------------------

--
-- Table structure for table `immunization_records`
--

CREATE TABLE `immunization_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `vaccine_type` varchar(255) DEFAULT NULL,
  `dose_number` varchar(50) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laboratory_records`
--

CREATE TABLE `laboratory_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `test_type` varchar(255) DEFAULT NULL,
  `lab_status` varchar(50) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newborn_records`
--

CREATE TABLE `newborn_records` (
  `newborn_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `midwife_id` int(11) DEFAULT NULL,
  `checkup_date` date DEFAULT NULL,
  `bcg_given` tinyint(1) DEFAULT 0,
  `hepb_given` tinyint(1) DEFAULT 0,
  `nbs_done` tinyint(1) DEFAULT 0,
  `hearing_test` tinyint(1) DEFAULT 0,
  `weight_g` int(11) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `vit_k` tinyint(1) DEFAULT 0,
  `eye_prophylaxis` tinyint(1) DEFAULT 0,
  `cord_care` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `newborn_records`
--

INSERT INTO `newborn_records` (`newborn_id`, `patient_id`, `midwife_id`, `checkup_date`, `bcg_given`, `hepb_given`, `nbs_done`, `hearing_test`, `weight_g`, `medications`, `findings`, `next_visit`, `created_at`, `vit_k`, `eye_prophylaxis`, `cord_care`) VALUES
(43, 48, 37, '2026-01-26', 0, 0, 0, 1, 2800, '', ' [Baby B (Twin)]', '2026-01-26', '2026-01-26 02:46:41', 1, 1, 1),
(44, 48, 37, '2026-01-26', 1, 1, 0, 0, 3000, '', ' [Baby A]', '2026-01-26', '2026-01-26 02:46:41', 1, 1, 1),
(45, 49, 37, '2026-01-26', 0, 1, 1, 1, 3000, '', '', '2026-02-26', '2026-01-26 03:26:19', 1, 1, 1),
(46, 49, 37, '2026-01-26', 0, 0, 0, 0, 3000, '', ' [Baby A]', '2026-02-05', '2026-01-26 09:57:33', 1, 0, 1),
(47, 49, 37, '2026-01-26', 0, 0, 0, 1, 2800, '', ' [Baby B (Twin)]', '2026-02-05', '2026-01-26 09:57:33', 1, 0, 1),
(48, 48, 37, '2026-01-26', 1, 1, 0, 1, 2800, '', ' [Baby B (Twin)]', '2026-02-06', '2026-01-26 10:41:43', 1, 0, 1),
(49, 48, 37, '2026-01-26', 1, 1, 0, 0, 3000, '', ' [Baby A]', '2026-02-06', '2026-01-26 10:41:43', 1, 1, 1),
(50, 48, 37, '2026-01-29', 1, 1, 0, 0, 505, '', '', '0000-00-00', '2026-01-29 14:40:13', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `age` int(11) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `lmp` date DEFAULT NULL,
  `edc` date DEFAULT NULL,
  `gravida` int(11) DEFAULT NULL,
  `para` int(11) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`patient_id`, `user_id`, `name`, `birth_date`, `contact_no`, `address`, `created_at`, `age`, `civil_status`, `occupation`, `lmp`, `edc`, `gravida`, `para`, `blood_type`, `medical_history`, `birthdate`, `sex`) VALUES
(48, 63, 'Harvey Arguilles', NULL, '09630606939', NULL, '2026-01-26 10:44:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(49, 64, 'Allain S. Almario', NULL, '09767276836', NULL, '2026-01-26 11:24:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 'Female'),
(50, 65, 'Argem Tiangco', NULL, '09826385825', NULL, '2026-01-26 18:21:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(51, 66, 'joen payawal', NULL, '09728636236', NULL, '2026-01-26 18:28:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(52, 67, 'Gerald Lopez', NULL, '09862864687', NULL, '2026-01-26 18:33:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(53, 70, 'Awet', NULL, '09623233333', NULL, '2026-02-04 20:55:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(54, 71, 'Justine Dayao', NULL, '09455615026', NULL, '2026-03-09 19:19:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 'Female');

-- --------------------------------------------------------

--
-- Table structure for table `pending_charges`
--

CREATE TABLE `pending_charges` (
  `charge_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_philhealth` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `pending_charges`
--

INSERT INTO `pending_charges` (`charge_id`, `patient_id`, `appointment_id`, `service_id`, `quantity`, `unit_price`, `total_amount`, `recorded_by`, `notes`, `status`, `reviewed_by`, `reviewed_at`, `created_at`, `is_philhealth`) VALUES
(1, 48, NULL, 52, 1, '5000.00', '2500.00', 37, ' [Twin Sequence: Charging Baby A/Mother first] [-2,500.00 Downpayment Deducted]', 'Approved', 61, '2026-01-26 02:48:00', '2026-01-26 02:47:00', 0),
(2, 48, NULL, 52, 1, '5000.00', '0.00', 37, 'Charge for Twin / Baby 2 (Additional Baby) [PhilHealth/No Balance Billing Applied]', 'Approved', 61, '2026-01-26 02:48:33', '2026-01-26 02:47:17', 1),
(3, 49, NULL, 50, 1, '12500.00', '6250.00', 37, ' [-6,250.00 Downpayment Deducted]', 'Approved', 61, '2026-01-26 03:26:55', '2026-01-26 03:26:27', 0),
(4, 49, NULL, 53, 1, '1500.00', '0.00', 37, ' [PhilHealth/No Balance Billing Applied]', 'Approved', 61, '2026-01-26 03:35:15', '2026-01-26 03:34:32', 1),
(5, 49, NULL, 50, 1, '12500.00', '12500.00', 37, ' [Twin Sequence: Charging Baby A/Mother first]', 'Approved', 61, '2026-01-26 09:58:23', '2026-01-26 09:57:53', 0),
(6, 49, NULL, 52, 1, '5000.00', '0.00', 37, 'Charge for Twin / Baby 2 (Additional Baby) [PhilHealth/No Balance Billing Applied]', 'Approved', 61, '2026-01-26 09:59:34', '2026-01-26 09:58:05', 1),
(7, 48, NULL, 52, 1, '5000.00', '5000.00', 37, ' [Twin Sequence: Charging Baby A/Mother first]', 'Rejected', 61, '2026-02-01 14:08:29', '2026-01-26 10:41:56', 0),
(8, 48, NULL, 52, 1, '5000.00', '5000.00', 37, 'Charge for Twin / Baby 2 (Additional Baby)', 'Approved', 61, '2026-01-26 10:42:57', '2026-01-26 10:42:34', 0),
(9, 48, NULL, 52, 1, '5000.00', '0.00', 37, ' [-2,500.00 Downpayment Deducted] [PhilHealth/No Balance Billing Applied]', 'Rejected', 61, '2026-02-01 14:08:19', '2026-01-29 14:40:45', 1),
(10, 48, NULL, 53, 1, '1500.00', '750.00', 37, ' [-750.00 Downpayment Deducted]', 'Approved', 69, '2026-02-04 13:05:03', '2026-02-01 13:50:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `postnatal_records`
--

CREATE TABLE `postnatal_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `blood_pressure` varchar(50) DEFAULT NULL,
  `temperature` varchar(50) DEFAULT NULL,
  `uterine_involution` varchar(100) DEFAULT NULL,
  `lochia` varchar(100) DEFAULT NULL,
  `breastfeeding_status` varchar(100) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `vit_a` tinyint(1) DEFAULT 0,
  `iron_supp` tinyint(1) DEFAULT 0,
  `fp_counseling` tinyint(1) DEFAULT 0,
  `perineal_care` tinyint(1) DEFAULT 0,
  `breastfeeding_support` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `postnatal_records`
--

INSERT INTO `postnatal_records` (`record_id`, `patient_id`, `midwife_id`, `checkup_date`, `blood_pressure`, `temperature`, `uterine_involution`, `lochia`, `breastfeeding_status`, `findings`, `medications`, `next_visit`, `created_at`, `vit_a`, `iron_supp`, `fp_counseling`, `perineal_care`, `breastfeeding_support`) VALUES
(17, 49, 37, '2026-01-26', '120/40', '36', 'Subinvolution', 'Abnormal', 'Mixed', '', '', '2026-02-26', '2026-01-26 03:26:19', 1, 1, 1, 1, 0),
(18, 49, 37, '2026-01-26', '120/30', '36', 'Subinvolution', 'Abnormal', 'Mixed', '', '', '2026-02-05', '2026-01-26 09:57:33', 0, 1, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `prenatal_records`
--

CREATE TABLE `prenatal_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` datetime DEFAULT current_timestamp(),
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `fetal_heart_rate` varchar(20) DEFAULT NULL,
  `aog_weeks` varchar(50) DEFAULT NULL,
  `fundic_height` varchar(50) DEFAULT NULL,
  `fetal_presentation` varchar(100) DEFAULT NULL,
  `vaginal_bleeding` varchar(5) DEFAULT 'No',
  `fever` varchar(5) DEFAULT 'No',
  `pallor` varchar(5) DEFAULT 'No',
  `edema` varchar(5) DEFAULT 'No',
  `medications` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `iron_supp` tinyint(1) DEFAULT 0,
  `calcium_supp` tinyint(1) DEFAULT 0,
  `tetanus_toxoid` tinyint(1) DEFAULT 0,
  `deworming` tinyint(1) DEFAULT 0,
  `birth_plan` tinyint(1) DEFAULT 0,
  `dental_advice` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `prenatal_records`
--

INSERT INTO `prenatal_records` (`record_id`, `patient_id`, `midwife_id`, `checkup_date`, `weight_kg`, `blood_pressure`, `fetal_heart_rate`, `aog_weeks`, `fundic_height`, `fetal_presentation`, `vaginal_bleeding`, `fever`, `pallor`, `edema`, `medications`, `findings`, `next_visit`, `iron_supp`, `calcium_supp`, `tetanus_toxoid`, `deworming`, `birth_plan`, `dental_advice`) VALUES
(1, 49, 37, '2026-01-26 11:34:00', '56.00', '120/40', '120', '28', '5', 'Breech', 'No', 'Yes', 'No', 'No', '', '', '2026-02-04', 1, 1, 1, 1, 1, 1),
(2, 48, 37, '2026-02-01 21:47:22', '0.01', '120/80', '21', '28', '', 'Cephalic', 'No', 'No', 'No', 'No', '', '', '0000-00-00', 1, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `rating_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `rating` int(11) NOT NULL
) ;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`rating_id`, `appointment_id`, `patient_id`, `service_name`, `rating`, `review_text`, `created_at`) VALUES
(10, 1, 48, 'NCP - Newborn Care Package', 5, 'solid ka yah', '2026-01-26 03:00:17'),
(11, 2, 49, 'MCP01 - Maternity Care Package', 4, 'very good po', '2026-01-26 09:35:38'),
(12, 3, 49, 'ANC01 - Antenatal Care Package', 5, 'keep it up', '2026-01-26 09:35:49'),
(13, 9, 48, 'NCP - Newborn Care Package', 4, 'good job', '2026-01-26 10:44:34');

-- --------------------------------------------------------

--
-- Table structure for table `service_pricing`
--

CREATE TABLE `service_pricing` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_category` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `service_pricing`
--

INSERT INTO `service_pricing` (`service_id`, `service_name`, `service_category`, `price`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Prenatal Checkup', 'Consultation', '500.00', 'Regular prenatal consultation and monitoring', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(2, 'Ultrasound', 'Diagnostic', '800.00', 'Ultrasound examination', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(3, 'Laboratory Tests', 'Diagnostic', '600.00', 'Blood tests and urinalysis', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(4, 'Postnatal Checkup', 'Consultation', '450.00', 'Post-delivery checkup', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(5, 'Family Planning Consultation', 'Consultation', '300.00', 'Family planning counseling', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(6, 'Immunization', 'Treatment', '250.00', 'Vaccination services', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(7, 'Normal Delivery', 'Delivery', '15000.00', 'Normal vaginal delivery', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(8, 'Cesarean Section', 'Delivery', '35000.00', 'Cesarean delivery', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(9, 'Walk-in Consultation', 'Consultation', '350.00', 'General consultation', 0, '2026-01-19 12:59:02', '2026-01-21 07:06:55'),
(10, 'BCG Vaccine Vial', 'Medicine', '160.00', 'Vaccine for Tuberculosis', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(11, 'Hepa Vaccine Vial', 'Medicine', '160.00', 'Hepatitis B Vaccine', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(12, 'Tetanus Toxoid Vaccine Amp', 'Medicine', '160.00', 'Tetanus prevention', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(13, 'Dexamethasone 5mg Vial', 'Medicine', '80.00', 'Corticosteroid', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(14, 'Atropine 1mg Amp', 'Medicine', '60.00', 'emergency use', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(15, 'Calcium Gluconate 10mg Vial', 'Medicine', '90.00', 'mineral supplement', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(16, 'Diphenhydramine 50mg Amp', 'Medicine', '40.00', 'antihistamine', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(17, 'Epinephrine 50mg Amp', 'Medicine', '60.00', 'emergency use', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(18, 'Lidocaine 2% Vial', 'Medicine', '35.00', 'local anesthetic', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(19, 'Magnesium Sulfate Vial', 'Medicine', '50.00', 'prevent seizures', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(20, 'Oxytocin 10 Units Amp', 'Medicine', '80.00', 'induce labor', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(21, 'Tranexamic Acid Amp', 'Medicine', '90.00', 'control bleeding', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(22, 'Plain NSS 1L Sol', 'Medicine', '100.00', 'Normal Saline Solution', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(23, 'D5LR/PLR 1L Sol', 'Medicine', '100.00', 'IV Fluids', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(24, 'Erythromycin Oint', 'Medicine', '60.00', 'Eye ointment for newborn', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(25, '1cc Syringe', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(26, '3cc Syringe', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(27, '5cc Syringe', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(28, '10cc Syringe', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(29, '20cc Syringe', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(30, 'G23 Needle', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(31, 'G26 Needle', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(32, 'G20 IV Cannula', 'Medical Supplies', '35.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(33, 'G22 IV Cannula', 'Medical Supplies', '35.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(34, 'G24 IV Cannula', 'Medical Supplies', '35.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(35, 'Oxygen Cannula Adult/Newborn', 'Medical Supplies', '80.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(36, 'Sterile Gauze', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(37, 'Sterile Gloves', 'Medical Supplies', '20.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(38, 'Clean Gloves', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(39, 'Surgical Mask', 'Medical Supplies', '5.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(40, 'Surgical Cap', 'Medical Supplies', '5.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(41, 'Umbilical Cord Clamp', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(42, 'Suction Catheter', 'Medical Supplies', '20.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(43, 'Micropore Plaster', 'Medical Supplies', '40.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(44, 'IV Catheter(Adult/Newborn)', 'Medical Supplies', '50.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(45, 'Povidone Iodine', 'Medical Supplies', '60.00', 'per 50mL', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(46, '70% Isopropyl Alcohol', 'Medical Supplies', '60.00', 'per 50mL', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(47, 'Sterile Absorbable Sutures', 'Medical Supplies', '120.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(48, 'Sterile Cotton Pledget', 'Medical Supplies', '10.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(49, 'Sterile Cotton Balls', 'Medical Supplies', '2.00', 'per piece', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(50, 'MCP01 - Maternity Care Package', 'PhilHealth Package', '15600.00', 'Routine Obstetric Care + Delivery + Newborn Services', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(51, 'NSD01 - Normal Spontaneous Delivery', 'PhilHealth Package', '12675.00', 'Routine Obstetric Care + Antepartum + Delivery + Postpartum', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(52, 'NCP - Newborn Care Package', 'PhilHealth Package', '5752.50', 'Essential Newborn Care + Expanded Newborn Screening + Hearing Test', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(53, 'ANC01 - Antenatal Care Package', 'PhilHealth Package', '2925.00', 'Essential Health Services + 4 Pre-natal Checkups', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(54, 'ANC02 - Antenatal Care + Monitoring', 'PhilHealth Package', '4192.50', 'Antenatal Care + Intrapartum Monitoring or Labor Watch', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55'),
(55, 'General Consultation', 'Consultation', '500.00', 'Standard Checkup', 1, '2026-01-21 07:06:55', '2026-01-21 07:06:55');

-- --------------------------------------------------------

--
-- Table structure for table `ultrasound_records`
--

CREATE TABLE `ultrasound_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `midwife_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `indication` varchar(255) DEFAULT NULL,
  `result_summary` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL,
  `api_address` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `username`, `password`, `role`, `created_at`, `profile_pic`, `api_address`, `license_number`, `specialization`, `contact_number`, `address`, `status`) VALUES
(1, 'System Admin', NULL, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', '2026-01-04 07:43:23', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(2, 'Head Midwife', NULL, 'midwife', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Midwife', '2026-01-04 07:43:23', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(3, 'Front Desk', NULL, 'clerk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Clerk', '2026-01-04 07:43:23', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(6, 'My Admin', NULL, 'superadmin', '$2y$10$6PcjnCaXilH4LXg4VUYZfuvBQ2xKybY2VkwDNMuCZaNehtuM.uPc.', 'Admin', '2026-01-04 08:19:12', 'user_6_1768489032.jpg', '', '', '', '', '', 'Active'),
(37, 'Lyra Belle', NULL, 'belle', '$2y$10$xJZxtR4sqTvDFlXdlFs5/e.q4Yc8Ic2TT3FxN9GKGpF2Zr51fFeXC', 'Midwife', '2026-01-15 05:37:16', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(61, 'Piere Miranda', NULL, 'piere143', '$2y$10$pzmwGZ8BDh8iJi9UkUoqvuFMXs8up6hMlcmJISMFTPAj/Ex3MshtK', 'Clerk', '2026-01-26 08:16:24', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(63, 'Harvey Arguilles', 'arguillesharvey29@gmail.com', 'harvey143', '$2y$10$EUCapb8RkL.nzOF7RGXOQeumJvEd1SLVJCv8xYRKx68XZ2SqAANla', 'Client', '2026-01-26 10:44:38', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(64, 'Allain S. Almario', 'minatozaki2113@gmail.com', 'allain143', '$2y$10$k2vii9TYiDn1YsGBnkY3oOu4qVpmfUcz/5LGpd4Y2KmwiKmHBRd7i', 'Client', '2026-01-26 11:24:03', NULL, '', '', '', '', '', 'Active'),
(65, 'Argem Tiangco', 'harveyarguilles436@gmail.com', 'argem143', '$2y$10$xHxDtdjBKsqF3RuCaZDIg.XKAj3Cy/lrS/yeNeihVtBZtEt4NleF6', 'Client', '2026-01-26 18:21:05', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(66, 'joen payawal', 'pierepaolom@gmail.com', 'joen143', '$2y$10$WTR6tZ7mmFSVU1SAVmgfb.q1hYhXxzSHwTUrc64eMd7jKidLhy.sa', 'Client', '2026-01-26 18:28:33', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(67, 'Gerald Lopez', 'joendave14@gmail.com', 'gerald143', '$2y$10$Ipp3uqV0SeCRpGejK6DJ4.TDjsr2LWrYO7OYAuXYGrp8Rzo6lvjwC', 'Client', '2026-01-26 18:33:59', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(68, 'Mywife', NULL, 'mywife', '$2y$10$l6sjYbM/4/1ffyO8nLd3KuENeacnqyCz/vsSDUds4HGyT4zyXcWQm', 'Midwife', '2026-02-04 20:50:09', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(69, 'myclerk', NULL, 'myclerk', '$2y$10$BykKzXKYf07/5KJz1KWC4OrnFpqyaXVRX1BcYHG8uj.GMnzbL7N6C', 'Clerk', '2026-02-04 20:50:33', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(70, 'Awet', 'boyawe01@gmail.com', 'awet', '$2y$10$32VmWeJ0K.Jo7qFavgIgE.ZLUvCRedht71rrIypObNYvj1BrTbLom', 'Client', '2026-02-04 20:55:22', NULL, NULL, NULL, NULL, NULL, NULL, 'Active'),
(71, 'Justine Dayao', 'justinedayao072004@gmail.com', 'justin', '$2y$10$eZPsjzJOWFsi7Lc7MsBz4eXPxVo0PMupU/hOb/Mz/yoncMdw38de2', 'Client', '2026-03-09 19:19:08', NULL, '', '', '', '8899889', '', 'Active'),
(72, 'Sarah Jenkins', NULL, 'sarahjenkins', '$2y$10$EPxbcqj0BYsno.8lcJkZru77DJmcXQ3Eq5eVWhHt1krJCWDzuZv6e', 'Midwife', '2026-03-09 19:41:40', NULL, NULL, NULL, NULL, NULL, NULL, 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointment_patient` (`patient_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `fk_billing_patient` (`patient_id`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blocked_date` (`blocked_date`);

--
-- Indexes for table `consultation_records`
--
ALTER TABLE `consultation_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_consult_patient` (`patient_id`),
  ADD KEY `fk_consult_midwife` (`midwife_id`);

--
-- Indexes for table `delivery_records`
--
ALTER TABLE `delivery_records`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `fk_delivery_patient` (`patient_id`),
  ADD KEY `fk_delivery_midwife` (`midwife_id`);

--
-- Indexes for table `family_planning_records`
--
ALTER TABLE `family_planning_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_fp_patient` (`patient_id`),
  ADD KEY `fk_fp_midwife` (`midwife_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `fk_feedback_patient` (`patient_id`);

--
-- Indexes for table `immunization_records`
--
ALTER TABLE `immunization_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_immuno_patient` (`patient_id`),
  ADD KEY `fk_immuno_midwife` (`midwife_id`);

--
-- Indexes for table `laboratory_records`
--
ALTER TABLE `laboratory_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_lab_patient` (`patient_id`),
  ADD KEY `fk_lab_midwife` (`midwife_id`);

--
-- Indexes for table `newborn_records`
--
ALTER TABLE `newborn_records`
  ADD PRIMARY KEY (`newborn_id`),
  ADD KEY `fk_newborn_patient` (`patient_id`),
  ADD KEY `fk_newborn_midwife` (`midwife_id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `fk_patient_user` (`user_id`);

--
-- Indexes for table `pending_charges`
--
ALTER TABLE `pending_charges`
  ADD PRIMARY KEY (`charge_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `fk_charge_patient` (`patient_id`),
  ADD KEY `fk_charge_service` (`service_id`),
  ADD KEY `fk_charge_recorder` (`recorded_by`);

--
-- Indexes for table `postnatal_records`
--
ALTER TABLE `postnatal_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_postnatal_patient` (`patient_id`),
  ADD KEY `fk_postnatal_midwife` (`midwife_id`);

--
-- Indexes for table `prenatal_records`
--
ALTER TABLE `prenatal_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_prenatal_patient` (`patient_id`),
  ADD KEY `fk_prenatal_midwife` (`midwife_id`);

--
-- Indexes for table `service_pricing`
--
ALTER TABLE `service_pricing`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `ultrasound_records`
--
ALTER TABLE `ultrasound_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_ultrasound_patient` (`patient_id`),
  ADD KEY `fk_ultrasound_midwife` (`midwife_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `consultation_records`
--
ALTER TABLE `consultation_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_records`
--
ALTER TABLE `delivery_records`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `family_planning_records`
--
ALTER TABLE `family_planning_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `immunization_records`
--
ALTER TABLE `immunization_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `laboratory_records`
--
ALTER TABLE `laboratory_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `newborn_records`
--
ALTER TABLE `newborn_records`
  MODIFY `newborn_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `pending_charges`
--
ALTER TABLE `pending_charges`
  MODIFY `charge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `postnatal_records`
--
ALTER TABLE `postnatal_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `prenatal_records`
--
ALTER TABLE `prenatal_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `service_pricing`
--
ALTER TABLE `service_pricing`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `ultrasound_records`
--
ALTER TABLE `ultrasound_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `fk_billing_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_records`
--
ALTER TABLE `consultation_records`
  ADD CONSTRAINT `fk_consult_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_consult_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_records`
--
ALTER TABLE `delivery_records`
  ADD CONSTRAINT `fk_delivery_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_delivery_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `family_planning_records`
--
ALTER TABLE `family_planning_records`
  ADD CONSTRAINT `fk_fp_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_fp_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `immunization_records`
--
ALTER TABLE `immunization_records`
  ADD CONSTRAINT `fk_immuno_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_immuno_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `laboratory_records`
--
ALTER TABLE `laboratory_records`
  ADD CONSTRAINT `fk_lab_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_lab_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `newborn_records`
--
ALTER TABLE `newborn_records`
  ADD CONSTRAINT `fk_newborn_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_newborn_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_charges`
--
ALTER TABLE `pending_charges`
  ADD CONSTRAINT `fk_charge_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_charge_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_charge_service` FOREIGN KEY (`service_id`) REFERENCES `service_pricing` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `postnatal_records`
--
ALTER TABLE `postnatal_records`
  ADD CONSTRAINT `fk_postnatal_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_postnatal_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `prenatal_records`
--
ALTER TABLE `prenatal_records`
  ADD CONSTRAINT `fk_prenatal_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_prenatal_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `ultrasound_records`
--
ALTER TABLE `ultrasound_records`
  ADD CONSTRAINT `fk_ultrasound_midwife` FOREIGN KEY (`midwife_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_ultrasound_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
