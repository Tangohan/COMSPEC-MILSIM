-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 13 avr. 2026 à 11:06
-- Version du serveur : 11.8.6-MariaDB-log
-- Version de PHP : 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `u416380327_BDD_PROD`
--

-- --------------------------------------------------------

--
-- Structure de la table `app_maintenance`
--

CREATE TABLE `app_maintenance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope` varchar(120) NOT NULL DEFAULT 'global',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT 'Maintenance en cours',
  `message` text DEFAULT NULL,
  `maintenance_code` varchar(80) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `allow_admin_bypass` tinyint(1) NOT NULL DEFAULT 1,
  `allowed_ips` text DEFAULT NULL,
  `allowed_roles` text DEFAULT NULL,
  `redirect_url` varchar(255) DEFAULT NULL,
  `http_status` smallint(6) NOT NULL DEFAULT 503,
  `priority` int(11) NOT NULL DEFAULT 100,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `app_maintenance_audit`
--

CREATE TABLE `app_maintenance_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `maintenance_id` bigint(20) UNSIGNED NOT NULL,
  `action_type` enum('create','update','enable','disable','delete') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_ip` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `asset_logistics_status`
--

CREATE TABLE `asset_logistics_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `vehicle_class` varchar(255) DEFAULT NULL,
  `fuel_ratio` decimal(5,4) DEFAULT NULL,
  `ammo_state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ammo_state_json`)),
  `damage_ratio` decimal(5,4) DEFAULT NULL,
  `crew_count` int(10) UNSIGNED DEFAULT NULL,
  `cargo_slots_free` int(10) UNSIGNED DEFAULT NULL,
  `slingload_capable` tinyint(1) DEFAULT 0,
  `last_update_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `asset_logistics_status_history`
--

CREATE TABLE `asset_logistics_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `fuel_ratio` decimal(5,4) DEFAULT NULL,
  `damage_ratio` decimal(5,4) DEFAULT NULL,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot_json`)),
  `logged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `async_jobs`
--

CREATE TABLE `async_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `job_type` varchar(64) NOT NULL,
  `payload_json` mediumtext NOT NULL,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `reserved_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_air_assets`
--

CREATE TABLE `atak_air_assets` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `callsign` varchar(128) NOT NULL,
  `model` varchar(255) DEFAULT NULL,
  `aircraft_type` varchar(32) DEFAULT NULL,
  `freq` varchar(64) DEFAULT NULL,
  `radio_main` varchar(64) DEFAULT NULL,
  `radio_aux` varchar(64) DEFAULT NULL,
  `laser` varchar(32) DEFAULT '1688',
  `auth` varchar(128) DEFAULT NULL,
  `auth_code` varchar(128) DEFAULT NULL,
  `pilot` varchar(255) DEFAULT NULL,
  `crew` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`crew`)),
  `fuel_pct` int(10) UNSIGNED DEFAULT NULL,
  `ordnance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ordnance`)),
  `station` varchar(128) DEFAULT NULL,
  `eta_minutes` int(10) UNSIGNED DEFAULT NULL,
  `bingo_fuel` varchar(32) DEFAULT NULL,
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `alt` decimal(10,2) DEFAULT NULL,
  `heading` decimal(8,2) DEFAULT NULL,
  `side` varchar(16) DEFAULT 'WEST',
  `status` varchar(32) DEFAULT 'IN-FLIGHT',
  `pilot_status` varchar(32) DEFAULT NULL,
  `aircraft_count` int(10) UNSIGNED DEFAULT 1,
  `last_update` bigint(20) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_chat_messages`
--

CREATE TABLE `atak_chat_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `author` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_designator_targets`
--

CREATE TABLE `atak_designator_targets` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_intel`
--

CREATE TABLE `atak_intel` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL,
  `author` varchar(255) NOT NULL,
  `pos_x` decimal(15,8) DEFAULT NULL,
  `pos_y` decimal(15,8) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_intel_photos`
--

CREATE TABLE `atak_intel_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_laser_codes`
--

CREATE TABLE `atak_laser_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `call_sign` varchar(128) NOT NULL,
  `laser_code` varchar(32) NOT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'ACTIVE',
  `last_update` bigint(20) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_last_activity`
--

CREATE TABLE `atak_last_activity` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `last_activity_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_layers`
--

CREATE TABLE `atak_layers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `label` varchar(255) NOT NULL,
  `phase` int(11) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_maps`
--

CREATE TABLE `atak_maps` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `world_name` varchar(50) NOT NULL DEFAULT 'altis',
  `tile_pattern` varchar(500) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `atak_maps`
--

INSERT INTO `atak_maps` (`id`, `slug`, `label`, `world_name`, `tile_pattern`, `config`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'altis', 'Altis', 'altis', '/assets/maps/altis/{z}/{x}/{y}.png', '{\"center\":[15360,15360],\"defaultZoom\":3,\"minZoom\":0,\"maxZoom\":6,\"tileSize\":212,\"worldSize\":30720,\"bounds\":[[0,0],[30720,30720]],\"crs\":{\"factorx\":0.0069010416666666664,\"factory\":0.0069010416666666664,\"tileWidth\":212},\"attribution\":\"&copy; Bohemia Interactive\",\"title\":\"Altis\"}', 0, '2026-03-13 22:57:32', '2026-03-15 13:51:18');

-- --------------------------------------------------------

--
-- Structure de la table `atak_map_shapes`
--

CREATE TABLE `atak_map_shapes` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `shape_uid` varchar(64) NOT NULL,
  `type` varchar(32) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT '#3388ff',
  `stroke` int(10) UNSIGNED DEFAULT 2,
  `fill_opacity` decimal(3,2) DEFAULT 0.15,
  `created_by` varchar(128) DEFAULT NULL,
  `visible_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visible_to`)),
  `geometry` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`geometry`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_markers`
--

CREATE TABLE `atak_markers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `layer_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `marker_data` text NOT NULL,
  `arma_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_nine_line`
--

CREATE TABLE `atak_nine_line` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `mission_id` varchar(128) DEFAULT NULL,
  `author` varchar(255) NOT NULL,
  `assigned_aircraft` varchar(128) DEFAULT NULL,
  `line1` varchar(255) DEFAULT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `line3` varchar(255) DEFAULT NULL,
  `line4` varchar(255) DEFAULT NULL,
  `line5` varchar(255) DEFAULT NULL,
  `line6` varchar(255) DEFAULT NULL,
  `line7` varchar(255) DEFAULT NULL,
  `line8` varchar(255) DEFAULT NULL,
  `line9` text DEFAULT NULL,
  `lines_checked` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`lines_checked`)),
  `status` varchar(50) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_pings`
--

CREATE TABLE `atak_pings` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `author` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_sigint_reports`
--

CREATE TABLE `atak_sigint_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `bearing` decimal(10,4) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `atak_units`
--

CREATE TABLE `atak_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `map_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `call_sign` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'linked',
  `grid_ref` varchar(100) DEFAULT NULL,
  `heading` decimal(10,4) DEFAULT NULL,
  `extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra`)),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip`, `user_agent`, `created_at`) VALUES
(1, 1, 1, 'auth.login_success', 'auth', 1, NULL, NULL, '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 14:18:47'),
(2, 6, 2, 'tenant.created', 'tenant', 6, NULL, 'ATHENA', '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 15:29:43'),
(3, 6, 2, 'tenant.setup_completed', 'tenant', 6, NULL, 'Europe/Paris', '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 15:33:39'),
(4, 1, NULL, 'auth.login_failure', 'auth', NULL, NULL, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 15:51:10'),
(5, 1, NULL, 'auth.login_failure', 'auth', NULL, NULL, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 16:09:43'),
(6, 1, NULL, 'auth.login_failure', 'auth', NULL, NULL, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 16:09:48'),
(7, 1, 3, 'auth.login_success', 'auth', 3, NULL, NULL, '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 16:10:11'),
(8, 1, 3, 'auth.login_success', 'auth', 3, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 08:11:48'),
(9, 1, 3, 'auth.login_success', 'auth', 3, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 08:24:25'),
(10, 7, 5, 'tenant.created', 'tenant', 7, NULL, 'ATHENA', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:10:02'),
(11, 7, 5, 'role_assigned', 'user', 5, '22', '22', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:13:03'),
(12, 7, 5, 'user_updated', 'user', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:13:03'),
(13, 7, 5, 'forum.moderation_action', 'forum_moderation', 3, NULL, 'lock_topic', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:25:40'),
(14, 7, 5, 'document_uploaded', 'document', 1, NULL, '1', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:38:22'),
(15, 7, 5, 'document_downloaded', 'document', 1, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:45:27'),
(16, 7, 5, 'role_assigned', 'user', 5, '22', '22', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 10:01:02'),
(17, 7, 5, 'user_updated', 'user', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 10:01:02'),
(18, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 10:02:15'),
(19, 1, 7, 'auth.register', 'user', 7, NULL, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 11:03:21'),
(20, 7, 5, 'role_assigned', 'user', 6, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 11:11:43'),
(21, 7, 5, 'user_updated', 'user', 6, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 11:11:44'),
(22, 1, 7, 'auth.login_success', 'auth', 7, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 11:22:14'),
(23, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 12:03:17'),
(24, 7, 5, 'invitation.sent', 'invitation', NULL, NULL, 'wikzzcoc@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 12:04:47'),
(25, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 15:52:27'),
(26, 7, 5, 'user_created', 'user', 8, NULL, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 16:32:08'),
(27, 1, 7, 'auth.login_success', 'auth', 7, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 16:33:02'),
(28, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 21:25:48'),
(29, 7, 5, 'role_assigned', 'user', 5, '22', '23,22,29,26,37,39,24,25,38,41,40,27,28', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 22:37:44'),
(30, 7, 5, 'user_updated', 'user', 5, NULL, NULL, '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 22:37:44'),
(31, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 22:38:29'),
(32, 7, 5, 'auth.logout', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 22:43:39'),
(33, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 10:37:30'),
(34, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 11:50:23'),
(35, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 17:01:20'),
(36, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 17:03:47'),
(37, 1, 7, 'auth.login_success', 'auth', 7, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 17:47:37'),
(38, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:04:22'),
(39, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 21:04:31'),
(40, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 21:07:34'),
(41, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a0d:e487:414f:dab8:b014:a438:5559:5d7e', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3 Mobile/15E148 Safari/604.1', '2026-04-06 21:43:28'),
(42, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '185.24.185.33', 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', '2026-04-07 09:08:59'),
(43, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:a8fc:7222:8fa0:df85', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 10:34:50'),
(44, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:a8fc:7222:8fa0:df85', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 18:17:00'),
(45, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '185.24.185.25', 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', '2026-04-08 08:33:22'),
(46, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-08 10:36:58'),
(47, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-09 06:55:25'),
(48, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-09 06:55:32'),
(49, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:b535:5c9:4a3b:b190', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-09 10:18:59'),
(50, 7, 5, 'site_role.assigned', 'site_role', 180, NULL, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:b535:5c9:4a3b:b190', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-09 11:01:09'),
(51, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:b535:5c9:4a3b:b190', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-09 17:04:37'),
(52, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:718e:7789:2f45:13d2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-10 13:01:39'),
(53, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:3553:1908:15f0:20d0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-12 16:42:09'),
(54, 7, 5, 'compliance.training_bundle_export', 'training_export', NULL, NULL, '{\"anonymized\":true,\"rows\":1,\"pdf_files\":0}', '2a01:e0a:8ee:2720:3553:1908:15f0:20d0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-12 17:15:33'),
(55, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:1c51:8e58:5169:60a4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-13 10:55:47');

-- --------------------------------------------------------

--
-- Structure de la table `badges`
--

CREATE TABLE `badges` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `icon_url` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blocked_indicators`
--

CREATE TABLE `blocked_indicators` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `indicator_type` varchar(32) NOT NULL,
  `value_hash` varchar(64) NOT NULL,
  `scope` varchar(16) NOT NULL DEFAULT 'tenant',
  `reason` varchar(500) DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `moderation_action_id` int(10) UNSIGNED DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'organizational',
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` varchar(600) DEFAULT NULL,
  `training_course_id` int(10) UNSIGNED DEFAULT NULL,
  `validity_days` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `certification_modules`
--

CREATE TABLE `certification_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `certification_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `minimum_score` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clearance_levels`
--

CREATE TABLE `clearance_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `rank_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `community_events`
--

CREATE TABLE `community_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `campaign_tag` varchar(100) DEFAULT NULL,
  `event_type` varchar(32) NOT NULL DEFAULT 'evenement',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `community_event_rsvps`
--

CREATE TABLE `community_event_rsvps` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'yes',
  `checked_in_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `community_invitations`
--

CREATE TABLE `community_invitations` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `invitation_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Unite et role metier prevus a l acceptation' CHECK (json_valid(`invitation_payload`)),
  `invited_by_user_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `accepted_user_id` int(10) UNSIGNED DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `community_invitations`
--

INSERT INTO `community_invitations` (`id`, `tenant_id`, `email`, `token_hash`, `role_id`, `invitation_payload`, `invited_by_user_id`, `status`, `expires_at`, `accepted_user_id`, `accepted_at`, `created_at`, `updated_at`) VALUES
(1, 7, 'wikzzcoc@gmail.com', '56ea13c46e80dfab3a38a5df5b68543f110d6b1f80116d1d431ef0897ea16b7c', 29, NULL, 5, 'revoked', '2026-04-12 12:04:47', NULL, NULL, '2026-04-05 12:04:47', '2026-04-06 17:44:33');

-- --------------------------------------------------------

--
-- Structure de la table `competencies`
--

CREATE TABLE `competencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `framework_id` int(10) UNSIGNED NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `domain_id` int(10) UNSIGNED NOT NULL,
  `parent_competency_id` int(10) UNSIGNED DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competency_domains`
--

CREATE TABLE `competency_domains` (
  `id` int(10) UNSIGNED NOT NULL,
  `framework_id` int(10) UNSIGNED NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competency_frameworks`
--

CREATE TABLE `competency_frameworks` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `competency_levels`
--

CREATE TABLE `competency_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `framework_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cooperation_announcement_templates`
--

CREATE TABLE `cooperation_announcement_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = défaut plateforme',
  `event_key` varchar(64) NOT NULL,
  `channel` enum('email','in_app','forum') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `forum_settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`forum_settings_json`)),
  `min_interval_hours` int(10) UNSIGNED NOT NULL DEFAULT 24,
  `is_active` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cooperation_announcement_templates`
--

INSERT INTO `cooperation_announcement_templates` (`id`, `tenant_id`, `event_key`, `channel`, `subject`, `body`, `forum_settings_json`, `min_interval_hours`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0, 'coop_mission_created', 'email', 'Nouveau dossier de coopération : {titre_cooperation}', 'Une nouvelle coopération a été créée par {unite_support}.\n\nVoir la synthèse : {lien_synthese}', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(2, 0, 'coop_proposal_updated', 'email', 'Proposition mise à jour : {titre_cooperation}', 'La proposition de coopération « {titre_cooperation} » a été modifiée.\n\n{lien_proposition}', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(3, 0, 'coop_invitation_sent', 'email', 'Invitation à une coopération inter-unités', '{unite_support} vous invite à rejoindre la coopération « {titre_cooperation} ».\n\nRépondre depuis le portail : {lien_synthese}', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(4, 0, 'coop_partner_accepted', 'email', 'Partenaire accepté : {titre_cooperation}', 'La communauté {unite_destinataire} a accepté de participer à « {titre_cooperation} ».', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(5, 0, 'coop_partner_declined', 'email', 'Partenaire a décliné : {titre_cooperation}', 'La communauté {unite_destinataire} a décliné l’invitation pour « {titre_cooperation} ».', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(6, 0, 'coop_mission_activated', 'email', 'Coopération ouverte : {titre_cooperation}', 'La coopération « {titre_cooperation} » est désormais active. Espace commun : {lien_espace_commun}', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(7, 0, 'coop_mission_closed', 'email', 'Coopération clôturée : {titre_cooperation}', 'La coopération « {titre_cooperation} » a été clôturée.\n\nSynthèse : {lien_synthese}', NULL, 24, 0, '2026-04-09 10:56:46', NULL),
(8, 0, 'coop_mission_created', 'in_app', NULL, 'Nouveau dossier de coopération : {titre_cooperation} — Une nouvelle coopération a été créée par {unite_support}. Voir la synthèse : {lien_synthese}', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(9, 0, 'coop_proposal_updated', 'in_app', NULL, 'Proposition mise à jour : {titre_cooperation} — La proposition de coopération « {titre_cooperation} » a été modifiée. {lien_proposition}', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(10, 0, 'coop_invitation_sent', 'in_app', NULL, 'Invitation à une coopération inter-unités — {unite_support} vous invite à rejoindre la coopération « {titre_cooperation} ». Répondre depuis le portail : {lien_synthese}', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(11, 0, 'coop_partner_accepted', 'in_app', NULL, 'Partenaire accepté : {titre_cooperation} — La communauté {unite_destinataire} a accepté de participer à « {titre_cooperation} ».', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(12, 0, 'coop_partner_declined', 'in_app', NULL, 'Partenaire a décliné : {titre_cooperation} — La communauté {unite_destinataire} a décliné l’invitation pour « {titre_cooperation} ».', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(13, 0, 'coop_mission_activated', 'in_app', NULL, 'Coopération ouverte : {titre_cooperation} — La coopération « {titre_cooperation} » est désormais active. Espace commun : {lien_espace_commun}', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(14, 0, 'coop_mission_closed', 'in_app', NULL, 'Coopération clôturée : {titre_cooperation} — La coopération « {titre_cooperation} » a été clôturée. Synthèse : {lien_synthese}', NULL, 0, 1, '2026-04-09 10:56:46', NULL),
(15, 0, 'coop_invitation_sent', 'forum', NULL, '{unite_support} vous invite à rejoindre la coopération « {titre_cooperation} ».\n\nRépondre depuis le portail : {lien_synthese}', '{\"as_draft\":true,\"category_id\":null,\"topic_id\":null}', 24, 0, '2026-04-09 10:56:46', NULL),
(16, 0, 'coop_mission_activated', 'forum', NULL, 'La coopération « {titre_cooperation} » est désormais active. Espace commun : {lien_espace_commun}', '{\"as_draft\":true,\"category_id\":null,\"topic_id\":null}', 24, 0, '2026-04-09 10:56:46', NULL),
(17, 0, 'coop_mission_closed', 'forum', NULL, 'La coopération « {titre_cooperation} » a été clôturée.\n\nSynthèse : {lien_synthese}', '{\"as_draft\":true,\"category_id\":null,\"topic_id\":null}', 24, 0, '2026-04-09 10:56:46', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `cooperation_catalog_entries`
--

CREATE TABLE `cooperation_catalog_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = référence plateforme',
  `slug` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `default_priority` varchar(24) DEFAULT NULL,
  `checklist_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_json`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cooperation_catalog_entries`
--

INSERT INTO `cooperation_catalog_entries` (`id`, `tenant_id`, `slug`, `label`, `description`, `default_priority`, `checklist_json`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0, 'formation', 'Formation', 'Mise en commun pour un entraînement ou un module pédagogique.', 'routine', NULL, 10, 1, '2026-04-09 10:56:46', NULL),
(2, 0, 'exercice', 'Exercice', 'Scénario structuré entre unités (validation mutuelle, calendrier).', 'planifiee', NULL, 20, 1, '2026-04-09 10:56:46', NULL),
(3, 0, 'appui_operationnel', 'Appui opérationnel', 'Soutien ponctuel ou spécialisé d’une unité à une autre.', 'prioritaire', NULL, 30, 1, '2026-04-09 10:56:46', NULL),
(4, 0, 'coordination_renseignement', 'Coordination renseignement', 'Partage d’information encadré entre communautés.', 'planifiee', NULL, 40, 1, '2026-04-09 10:56:46', NULL),
(5, 0, 'liaison_interservices', 'Liaison interservices', 'Alignement entre fonctions ou pôles distincts.', 'routine', NULL, 50, 1, '2026-04-09 10:56:46', NULL),
(6, 0, 'soutien_logistique', 'Soutien logistique', 'Coordination matériel, transport ou ressources.', 'routine', NULL, 60, 1, '2026-04-09 10:56:46', NULL),
(7, 0, 'preparation_mission', 'Préparation de mission', 'Montée en puissance avant une opération conjointe.', 'prioritaire', NULL, 70, 1, '2026-04-09 10:56:46', NULL),
(8, 0, 'retour_experience', 'Retour d’expérience', 'Capitalisation après action ou clôture de dossier.', 'routine', NULL, 80, 1, '2026-04-09 10:56:46', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `cooperation_forum_announcement_log`
--

CREATE TABLE `cooperation_forum_announcement_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(64) NOT NULL,
  `posted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cooperation_mission_templates`
--

CREATE TABLE `cooperation_mission_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `default_typology` varchar(48) DEFAULT NULL,
  `default_priority` varchar(24) DEFAULT NULL,
  `checklist_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cooperation_notification_outbox`
--

CREATE TABLE `cooperation_notification_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `event_key` varchar(96) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `aggregation_key` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cooperation_notification_outbox`
--

INSERT INTO `cooperation_notification_outbox` (`id`, `tenant_id`, `user_id`, `event_key`, `payload_json`, `aggregation_key`, `created_at`, `processed_at`) VALUES
(1, NULL, NULL, 'cooperation.signal.mission_created', '{\"mission_id\":1,\"title\":\"Exercice Winter- Coopération de formation\"}', 'mission:1:mission_created', '2026-04-09 07:02:00', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `courrier_documents`
--

CREATE TABLE `courrier_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `preset_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `title` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `destination_label` varchar(500) DEFAULT NULL,
  `issuer_label` varchar(500) DEFAULT NULL,
  `body_rendered` longtext DEFAULT NULL,
  `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables_json`)),
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `attachments_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments_json`)),
  `classification_level` varchar(50) DEFAULT 'interne',
  `moderation_state` varchar(32) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `signed_by` int(10) UNSIGNED DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `signature_data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`signature_data_json`)),
  `content_hash` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `courrier_documents`
--

INSERT INTO `courrier_documents` (`id`, `uuid`, `tenant_id`, `template_id`, `preset_id`, `type`, `status`, `title`, `reference_number`, `subject`, `destination_label`, `issuer_label`, `body_rendered`, `variables_json`, `metadata_json`, `attachments_json`, `classification_level`, `moderation_state`, `created_by`, `validated_by`, `signed_by`, `signed_at`, `signature_data_json`, `content_hash`, `created_at`, `updated_at`, `sent_at`, `archived_at`) VALUES
(1, '814a437b-206d-11f1-a9a0-91b7e349c605', 1, 2, 1, NULL, 'signed', 'test', 'CR-2026-0001', 'Test d\'objet', 'Personne', 'Tanguy TETARD', 'Mon Capitaine,\r\n\r\nJ\'ai l\'honneur de vous rendre compte des faits suivants survenus le 08 janvier 2026. Lors de la mise en place de la Section d\'Appui sur le point ALPHA, un incident de tir a été constaté sur l\'arme collective du personnel DUBOIS Arthur (MAT: 4512-01).\r\n\r\nConformément aux directives du TTA 150, les mesures de sécurité immédiates ont été appliquées. L\'intéressé a été retiré de la ligne de feu en attente de l\'expertise de l\'armurier. L\'intégrité physique du personnel n\'est pas engagée.\r\n\r\nJe vous ferai connaître les conclusions de l\'enquête technique dès réception du rapport de l\'armurerie.', NULL, NULL, NULL, 'interne', NULL, NULL, NULL, NULL, '2026-03-15 14:49:00', '{\"signature_image_path\":\"1\\/1\\/signature.png\",\"stamp_original_signed\":\"\",\"stamp_name_signature\":\"Tanguy TETARD\",\"stamp_grade\":\"Lieutenant\",\"signature_source\":\"pad\"}', '3bfe2b7291c8c6367cb89b1cd6278f0d5a407d8f9e65a8fcc43bb9eab5e41989', '2026-03-15 12:50:11', '2026-03-15 14:50:19', NULL, NULL),
(2, 'fecb83d9-2080-11f1-a9a0-91b7e349c605', 1, 2, 11, NULL, 'draft', 'eee', 'CR-2026-0002', 'ee', 'eeee', 'Tanguy TETARD', 'eeeee', NULL, NULL, NULL, 'interne', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-15 15:09:42', '2026-03-15 15:09:42', NULL, NULL),
(3, '8a524162-3043-11f1-bef7-617c4a00209f', 1, 2, 1, NULL, 'signed', 'NDS', 'CR-2026-0003', 'Mise en place d\'Athena', 'Tous utilisateurs', 'Sous-lieutenant Administrateur', '<p>    Conformément aux directives en vigueur, je vous informe qu\'après une phase de conception, d’architecture et de validation fonctionnelle, le système Athena est officiellement mis en service.</p>\r\n\r\n<b>ATHENA</b> constitue une infrastructure centralisée de commandement, d’analyse et de coordination. Elle agrège les flux opérationnels, structure les données critiques et impose une lecture unifiée de l’environnement tactique. Chaque module répond à un besoin identifié : gestion du personnel, suivi logistique, exploitation du renseignement, administration des ressources et supervision des opérations.\r\n\r\n<p>Je vous prie d\'agréer, Tous utilisateurs, l\'expression de mon profond respect.</p>', NULL, NULL, NULL, 'interne', NULL, 3, 3, 3, '2026-04-04 16:35:03', '{\"signature_image_path\":\"1\\/3\\/signature.png\",\"stamp_original_signed\":\"Original sign\\u00e9\",\"stamp_name_signature\":\"Administrateur Athena\",\"stamp_grade\":\"\",\"signature_source\":\"pad\",\"verification_code\":\"SIG-2026-04-04-9BC42B2B\"}', '57699b20a7e337a0d847b837be0f59b391ac3162d72996c3c92dffd14dbf7553', '2026-04-04 16:30:06', '2026-04-04 16:35:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `courrier_document_notifications`
--

CREATE TABLE `courrier_document_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `recipient_user_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `courrier_document_versions`
--

CREATE TABLE `courrier_document_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_json`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `courrier_document_versions`
--

INSERT INTO `courrier_document_versions` (`id`, `document_id`, `version_number`, `snapshot_json`, `created_by`, `created_at`) VALUES
(1, 1, 1, '{\"title\":null,\"subject\":null,\"reference_number\":null,\"body_rendered\":\"\",\"destination_label\":null,\"issuer_label\":null,\"updated_at\":\"2026-03-15 13:54:52\"}', NULL, '2026-03-15 14:12:56'),
(2, 1, 2, '{\"title\":null,\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:12:56\"}', NULL, '2026-03-15 14:16:48'),
(3, 1, 3, '{\"title\":\"test\",\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:16:48\"}', NULL, '2026-03-15 14:16:53'),
(4, 1, 4, '{\"title\":\"test\",\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:48:23\"}', NULL, '2026-03-15 14:48:34'),
(5, 1, 5, '{\"title\":\"test\",\"subject\":\"Test d\'objet\",\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":\"Personne\",\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:49:00\"}', NULL, '2026-03-15 14:50:19'),
(6, 3, 1, '{\"title\":\"NDS\",\"subject\":\"Mise en place d\'Athena\",\"reference_number\":\"CR-2026-0003\",\"body_rendered\":\"\",\"destination_label\":\"Tous utilisateurs\",\"issuer_label\":\"Sous-lieutenant Administrateur\",\"updated_at\":\"2026-04-04 16:30:06\"}', 3, '2026-04-04 16:30:43'),
(7, 3, 2, '{\"title\":\"NDS\",\"subject\":\"Mise en place d\'Athena\",\"reference_number\":\"CR-2026-0003\",\"body_rendered\":\"Apr\\u00e8s phase de conception, d\\u2019architecture et de validation fonctionnelle, le syst\\u00e8me Athena est officiellement mis en service.\\r\\n\\r\\nATHENA constitue une infrastructure centralis\\u00e9e de commandement, d\\u2019analyse et de coordination. Elle agr\\u00e8ge les flux op\\u00e9rationnels, structure les donn\\u00e9es critiques et impose une lecture unifi\\u00e9e de l\\u2019environnement tactique. Chaque module r\\u00e9pond \\u00e0 un besoin identifi\\u00e9 : gestion du personnel, suivi logistique, exploitation du renseignement, administration des ressources et supervision des op\\u00e9rations.\",\"destination_label\":\"Tous utilisateurs\",\"issuer_label\":\"Sous-lieutenant Administrateur\",\"updated_at\":\"2026-04-04 16:31:27\"}', 3, '2026-04-04 16:33:00'),
(8, 3, 3, '{\"title\":\"NDS\",\"subject\":\"Mise en place d\'Athena\",\"reference_number\":\"CR-2026-0003\",\"body_rendered\":\"<p>Conform\\u00e9ment aux directives en vigueur, je vous informe qu\'<\\/p>apr\\u00e8s une phase de conception, d\\u2019architecture et de validation fonctionnelle, le syst\\u00e8me Athena est officiellement mis en service.\\r\\n\\r\\nATHENA constitue une infrastructure centralis\\u00e9e de commandement, d\\u2019analyse et de coordination. Elle agr\\u00e8ge les flux op\\u00e9rationnels, structure les donn\\u00e9es critiques et impose une lecture unifi\\u00e9e de l\\u2019environnement tactique. Chaque module r\\u00e9pond \\u00e0 un besoin identifi\\u00e9 : gestion du personnel, suivi logistique, exploitation du renseignement, administration des ressources et supervision des op\\u00e9rations.\\r\\n\\r\\n<p>Je vous prie d\'agr\\u00e9er, Tous utilisateurs, l\'expression de mon profond respect.<\\/p>\",\"destination_label\":\"Tous utilisateurs\",\"issuer_label\":\"Sous-lieutenant Administrateur\",\"updated_at\":\"2026-04-04 16:33:00\"}', 3, '2026-04-04 16:33:16'),
(9, 3, 4, '{\"title\":\"NDS\",\"subject\":\"Mise en place d\'Athena\",\"reference_number\":\"CR-2026-0003\",\"body_rendered\":\"<p>Conform\\u00e9ment aux directives en vigueur, je vous informe qu\'apr\\u00e8s une phase de conception, d\\u2019architecture et de validation fonctionnelle, le syst\\u00e8me Athena est officiellement mis en service.<\\/p>\\r\\n\\r\\n<b>ATHENA<\\/b> constitue une infrastructure centralis\\u00e9e de commandement, d\\u2019analyse et de coordination. Elle agr\\u00e8ge les flux op\\u00e9rationnels, structure les donn\\u00e9es critiques et impose une lecture unifi\\u00e9e de l\\u2019environnement tactique. Chaque module r\\u00e9pond \\u00e0 un besoin identifi\\u00e9 : gestion du personnel, suivi logistique, exploitation du renseignement, administration des ressources et supervision des op\\u00e9rations.\\r\\n\\r\\n<p>Je vous prie d\'agr\\u00e9er, Tous utilisateurs, l\'expression de mon profond respect.<\\/p>\",\"destination_label\":\"Tous utilisateurs\",\"issuer_label\":\"Sous-lieutenant Administrateur\",\"updated_at\":\"2026-04-04 16:33:16\"}', 3, '2026-04-04 16:33:21');

-- --------------------------------------------------------

--
-- Structure de la table `courrier_snippets`
--

CREATE TABLE `courrier_snippets` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `label` varchar(255) NOT NULL,
  `phase` varchar(20) NOT NULL,
  `body` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `danger_zones`
--

CREATE TABLE `danger_zones` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `zone_type` varchar(64) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `color` varchar(32) DEFAULT '#ff0000',
  `fill_opacity` decimal(3,2) DEFAULT 0.25,
  `stroke_width` int(10) UNSIGNED DEFAULT 2,
  `geometry_type` varchar(32) NOT NULL,
  `geometry_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`geometry_json`)),
  `side_visibility_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`side_visibility_json`)),
  `threat_level` varchar(32) DEFAULT 'MEDIUM',
  `active` tinyint(1) DEFAULT 1,
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `document_category_id` int(10) UNSIGNED DEFAULT NULL,
  `classification_level` varchar(50) NOT NULL DEFAULT 'interne',
  `visibility_scope` varchar(50) NOT NULL DEFAULT 'private',
  `owner_user_id` int(10) UNSIGNED DEFAULT NULL,
  `author_user_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_document_id` int(10) UNSIGNED DEFAULT NULL,
  `relation_type` varchar(50) DEFAULT NULL,
  `version_label` varchar(50) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `current_file_id` int(10) UNSIGNED DEFAULT NULL,
  `formation_id` int(10) UNSIGNED DEFAULT NULL,
  `equipment_class_id` int(10) UNSIGNED DEFAULT NULL,
  `unit_id` int(10) UNSIGNED DEFAULT NULL,
  `operator_id` int(10) UNSIGNED DEFAULT NULL,
  `mission_id` varchar(128) DEFAULT NULL,
  `effective_at` datetime DEFAULT NULL,
  `review_due_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `print_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `inherit_parent_security` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(50) DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `documents`
--

INSERT INTO `documents` (`id`, `uuid`, `tenant_id`, `title`, `slug`, `short_description`, `document_type`, `description`, `document_category_id`, `classification_level`, `visibility_scope`, `owner_user_id`, `author_user_id`, `parent_document_id`, `relation_type`, `version_label`, `sort_order`, `current_file_id`, `formation_id`, `equipment_class_id`, `unit_id`, `operator_id`, `mission_id`, `effective_at`, `review_due_at`, `expires_at`, `download_allowed`, `print_allowed`, `locked`, `tags`, `inherit_parent_security`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2fd480f7-30d3-11f1-bef7-617c4a00209f', 7, 'JTAC - CAS Librairie', 'jtac-cas-librairie', 'Formation au CAS - JTAC', 'support_formation', 'Formation spécialisée dédiée à la conduite des appuis aériens rapprochés (CAS) dans un cadre opérationnel structuré.\r\nElle vise à former des JTAC capables de coordonner, sécuriser et diriger l’engagement des moyens aériens au profit des forces au sol.\r\n\r\nLes enseignements couvrent les procédures radio, la gestion de l’espace aérien, l’identification des objectifs, ainsi que la conduite des frappes dans le respect des règles d’engagement et de sécurité.\r\n\r\nObjectif : disposer d’opérateurs autonomes, rigoureux et capables d’intégrer un dispositif interarmes avec précision et fiabilité.', 15, 'interne', 'controlled', 5, 5, NULL, 'annexe', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '2026-04-05 11:32:00', NULL, NULL, 1, 1, 1, '[\"jtac\"]', 0, 'published', 5, '2026-04-05 09:38:22', '2026-04-05 09:38:22');

-- --------------------------------------------------------

--
-- Structure de la table `document_audit_log`
--

CREATE TABLE `document_audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_audit_log`
--

INSERT INTO `document_audit_log` (`id`, `document_id`, `user_id`, `action`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 5, 'document_created', NULL, '{\"title\":\"JTAC - CAS Librairie\",\"version_id\":1}', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 09:38:22');

-- --------------------------------------------------------

--
-- Structure de la table `document_categories`
--

CREATE TABLE `document_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_categories`
--

INSERT INTO `document_categories` (`id`, `tenant_id`, `name`, `slug`, `color`, `created_at`) VALUES
(1, 1, 'Doctrine / SOP', 'doctrine', 'emerald', '2026-03-14 00:01:46'),
(2, 1, 'Manuel opérateur', 'manuel', 'blue', '2026-03-14 00:01:46'),
(3, 1, 'Fiche équipement', 'fiche-equipement', 'amber', '2026-03-14 00:01:46'),
(4, 1, 'Rapport mission', 'rapport', 'slate', '2026-03-14 00:01:46'),
(5, 1, 'Média pédagogique', 'media', 'violet', '2026-03-14 00:01:46'),
(11, 7, 'Doctrine / SOP', 'doctrine', 'emerald', '2026-04-05 09:10:02'),
(12, 7, 'Manuel opérateur', 'manuel', 'blue', '2026-04-05 09:10:02'),
(13, 7, 'Fiche équipement', 'fiche-equipement', 'amber', '2026-04-05 09:10:02'),
(14, 7, 'Rapport mission', 'rapport', 'slate', '2026-04-05 09:10:02'),
(15, 7, 'Média pédagogique', 'media', 'violet', '2026-04-05 09:10:02');

-- --------------------------------------------------------

--
-- Structure de la table `document_collaborators`
--

CREATE TABLE `document_collaborators` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) NOT NULL,
  `granted_by` int(10) UNSIGNED DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_collaborators`
--

INSERT INTO `document_collaborators` (`id`, `document_id`, `user_id`, `role`, `granted_by`, `granted_at`) VALUES
(1, 1, 5, 'owner', 5, '2026-04-05 09:38:22');

-- --------------------------------------------------------

--
-- Structure de la table `document_links`
--

CREATE TABLE `document_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `entity_type` enum('training','equipment_class','unit','user') NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_permissions`
--

CREATE TABLE `document_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `permission_type` varchar(50) NOT NULL,
  `permission_value` varchar(190) NOT NULL,
  `access_level` varchar(50) NOT NULL DEFAULT 'read',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_presets`
--

CREATE TABLE `document_presets` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `paper_size` varchar(50) DEFAULT 'a4',
  `orientation` varchar(20) DEFAULT 'portrait',
  `margins_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`margins_json`)),
  `typography_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`typography_json`)),
  `header_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`header_config_json`)),
  `footer_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`footer_config_json`)),
  `signature_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`signature_config_json`)),
  `layout_config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config_json`)),
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_presets`
--

INSERT INTO `document_presets` (`id`, `tenant_id`, `name`, `code`, `paper_size`, `orientation`, `margins_json`, `typography_json`, `header_config_json`, `footer_config_json`, `signature_config_json`, `layout_config_json`, `is_system`, `is_default`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Format · A4 Portrait', 'a4_portrait', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(2, NULL, 'Format · A4 Paysage', 'a4_landscape', 'a4', 'landscape', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(3, NULL, 'Format · Note interne', 'note_interne', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(4, NULL, 'Format · Compte rendu', 'compte_rendu', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(5, NULL, 'Format · Courrier hiérarchique', 'courrier_hierarchique', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(6, NULL, 'Format · Décision', 'decision', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(7, NULL, 'Format · Fiche de transmission', 'fiche_transmission', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(8, NULL, 'Format · Message court', 'message_court', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(9, NULL, 'Format · Compte rendu incident', 'cr_incident', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(10, NULL, 'Format · Rapport circonstancié', 'rapport_circonstancie', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 12:43:17', '2026-03-15 13:51:18'),
(11, NULL, 'Compte-rendu officiel CERBERE / 92e RI', 'cerbere_officiel', 'a4', 'portrait', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-03-15 13:51:18', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `document_relations`
--

CREATE TABLE `document_relations` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_document_id` int(10) UNSIGNED NOT NULL,
  `child_document_id` int(10) UNSIGNED NOT NULL,
  `relation_type` varchar(50) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `preset_id` int(10) UNSIGNED DEFAULT NULL,
  `structure_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structure_json`)),
  `body_template` longtext DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_templates`
--

INSERT INTO `document_templates` (`id`, `tenant_id`, `name`, `slug`, `category`, `description`, `is_system`, `is_locked`, `is_active`, `preset_id`, `structure_json`, `body_template`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Note interne', 'note-interne', 'courrier', 'Modèle pour note interne.', 1, 0, 1, 3, NULL, '<div class=\"p-8 text-sm\"><p class=\"text-right text-gray-500\">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class=\"mt-4\">{{document.reference_number}}</p><div class=\"mt-6\"><p>Madame, Monsieur,</p><p>Contenu de la note.</p></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(2, NULL, 'Compte rendu', 'compte-rendu', 'courrier', 'Modèle compte rendu.', 1, 0, 1, 4, NULL, '<div class=\"p-8 text-sm\"><p class=\"text-right\">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class=\"mt-6 space-y-4\"><p>Rédigez le compte rendu ci-dessous.</p></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(3, NULL, 'Courrier hiérarchique', 'courrier-hierarchique', 'courrier', 'Courrier à la hiérarchie.', 1, 0, 1, 5, NULL, '<div class=\"p-8 text-sm\"><p>{{user.rank_label}} {{user.last_name}} {{user.first_name}}</p><p>Matricule : {{user.service_number}}</p><p class=\"text-right mt-4\">Le {{current_date_fr}}</p><p><strong>À :</strong> {{destination_label}}</p><p><strong>Objet :</strong> {{subject}}</p><div class=\"mt-6\">{{signature_block}}</div></div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(4, NULL, 'Décision', 'decision', 'courrier', 'Modèle décision.', 1, 0, 1, 6, NULL, '<div class=\"p-8 text-sm\"><p class=\"font-bold\">Décision</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class=\"mt-6\"><p>Il est décidé ce qui suit :</p><p class=\"mt-4\">[Contenu de la décision]</p></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(5, NULL, 'Fiche de transmission', 'fiche-transmission', 'courrier', 'Fiche de transmission.', 1, 0, 1, 7, NULL, '<div class=\"p-8 text-sm border border-gray-200\"><p><strong>Fiche de transmission</strong></p><p>Réf. {{document.reference_number}} — {{current_date_fr}}</p><p>De : {{user.full_name}} — À : {{destination_label}}</p><p>Objet : {{subject}}</p><div class=\"mt-4 min-h-[4rem] border-b border-gray-200\"></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(6, NULL, 'Message court', 'message-court', 'courrier', 'Message court.', 1, 0, 1, 8, NULL, '<div class=\"p-8 text-sm\"><p>{{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class=\"mt-4\">[Message]</p>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(7, NULL, 'Compte rendu incident', 'compte-rendu-incident', 'courrier', 'Compte rendu d\'incident.', 1, 0, 1, 9, NULL, '<div class=\"p-8 text-sm\"><p class=\"text-right\">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class=\"mt-6\"><p>Compte rendu des faits :</p><p class=\"mt-2\">[Rédiger le compte rendu]</p></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(8, NULL, 'Rapport circonstancié', 'rapport-circonstancie', 'courrier', 'Rapport circonstancié.', 1, 0, 1, 10, NULL, '<div class=\"p-8 text-sm\"><p class=\"font-bold\">Rapport circonstancié</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class=\"mt-6 space-y-4\"><p>[Exposé des faits]</p></div>{{signature_block}}</div>', NULL, NULL, '2026-03-15 13:51:18', NULL),
(9, NULL, 'Compte-rendu d\'incident CERBERE', 'compte-rendu-cerbere', 'cerbere', 'Modèle officiel type 92e RI / CERBERE', 1, 1, 1, 11, NULL, '<div class=\"p-12 bg-white text-gray-900 overflow-x-auto\">\n<div class=\"max-w-[21cm] mx-auto min-h-[29.7cm] p-10 border border-gray-200\">\n<div class=\"text-[10px] font-bold uppercase leading-tight mb-12\">\n<p>MINISTÈRE DE LA DÉFENSE</p>\n<p class=\"border-b-2 border-black w-fit mb-1\">UNITÉ : {{unit.name}}</p>\n<p>SECTION : {{unit.section}}</p>\n<p class=\"mt-4\">N° {{document.reference_number}} / CERBERE / RH</p>\n</div>\n<div class=\"text-right text-[11px] mb-10\">\n<p>Le {{current_date_fr}}</p>\n</div>\n<div class=\"ml-auto w-1/2 text-[11px] font-bold space-y-1 mb-12\">\n<p>{{user.rank_label}} {{user.last_name}} {{user.first_name}}</p>\n<p>Matricule : {{user.service_number}}</p>\n<p class=\"text-blue-600 italic py-2\">à</p>\n<p>{{destination_label}}</p>\n</div>\n<div class=\"text-[11px] space-y-2 mb-10\">\n<p><span class=\"underline font-bold\">OBJET</span> : {{subject}}</p>\n<p><span class=\"underline font-bold\">RÉFÉRENCE</span> : {{document.reference_number}}</p>\n</div>\n<div class=\"text-xs leading-relaxed text-justify space-y-4\">\n<p class=\"font-bold italic\">Mon Capitaine,</p>\n<p>Contenu du compte-rendu à rédiger.</p>\n</div>\n{{signature_block}}\n</div>\n</div>', NULL, NULL, '2026-03-15 13:51:18', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `document_template_versions`
--

CREATE TABLE `document_template_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `template_id` int(10) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `structure_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structure_json`)),
  `body_template` longtext DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `document_variables_catalog`
--

CREATE TABLE `document_variables_catalog` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_path` varchar(255) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_variables_catalog`
--

INSERT INTO `document_variables_catalog` (`id`, `tenant_id`, `code`, `label`, `source_type`, `source_path`, `description`, `category`, `is_active`, `created_at`) VALUES
(1, NULL, 'current_date_fr', 'Date du jour (FR)', 'system', NULL, 'Date au format français', 'system', 1, '2026-03-15 12:43:17'),
(2, NULL, 'current_datetime_fr', 'Date et heure (FR)', 'system', NULL, 'Date et heure au format français', 'system', 1, '2026-03-15 12:43:17'),
(3, NULL, 'current_year', 'Année en cours', 'system', NULL, 'Année courante', 'system', 1, '2026-03-15 12:43:17'),
(4, NULL, 'document.uuid', 'UUID du document', 'document', NULL, 'Identifiant unique du document', 'document', 1, '2026-03-15 12:43:17'),
(5, NULL, 'document.reference_number', 'Référence du document', 'document', NULL, 'Numéro de référence', 'document', 1, '2026-03-15 12:43:17'),
(6, NULL, 'user.first_name', 'Prénom', 'user', NULL, 'Prénom de l\'utilisateur connecté', 'user', 1, '2026-03-15 12:43:17'),
(7, NULL, 'user.last_name', 'Nom', 'user', NULL, 'Nom de l\'utilisateur connecté', 'user', 1, '2026-03-15 12:43:17'),
(8, NULL, 'user.full_name', 'Nom complet', 'user', NULL, 'Prénom et nom', 'user', 1, '2026-03-15 12:43:17'),
(9, NULL, 'user.rank', 'Grade (code)', 'user', NULL, 'Code court du grade', 'user', 1, '2026-03-15 12:43:17'),
(10, NULL, 'user.rank_label', 'Grade (libellé)', 'user', NULL, 'Libellé du grade', 'user', 1, '2026-03-15 12:43:17'),
(11, NULL, 'user.service_number', 'Matricule', 'user', NULL, 'Numéro de service', 'user', 1, '2026-03-15 12:43:17'),
(12, NULL, 'user.email', 'Email', 'user', NULL, 'Adresse email', 'user', 1, '2026-03-15 12:43:17'),
(13, NULL, 'unit.name', 'Unité (nom)', 'structure', NULL, 'Nom de l\'unité', 'structure', 1, '2026-03-15 12:43:17'),
(14, NULL, 'unit.company', 'Compagnie', 'structure', NULL, 'Nom de la compagnie', 'structure', 1, '2026-03-15 12:43:17'),
(15, NULL, 'unit.section', 'Section', 'structure', NULL, 'Section', 'structure', 1, '2026-03-15 12:43:17'),
(16, NULL, 'unit.address', 'Adresse', 'structure', NULL, 'Adresse de l\'unité', 'structure', 1, '2026-03-15 12:43:17'),
(17, NULL, 'unit.city', 'Ville', 'structure', NULL, 'Ville', 'structure', 1, '2026-03-15 12:43:17'),
(18, NULL, 'superior.rank_label', 'Grade du supérieur', 'hierarchy', NULL, 'Grade du supérieur hiérarchique', 'hierarchy', 1, '2026-03-15 12:43:17'),
(19, NULL, 'superior.full_name', 'Nom du supérieur', 'hierarchy', NULL, 'Nom complet du supérieur', 'hierarchy', 1, '2026-03-15 12:43:17'),
(20, NULL, 'superior.position_label', 'Fonction du supérieur', 'hierarchy', NULL, 'Fonction du supérieur', 'hierarchy', 1, '2026-03-15 12:43:17'),
(21, NULL, 'user.grade_text', 'Grade (texte classique)', 'user', NULL, 'Libellé classique du grade', 'user', 1, '2026-03-15 12:43:17'),
(22, NULL, 'user.grade_short', 'Grade (code court)', 'user', NULL, 'Code court (ex. CNE)', 'user', 1, '2026-03-15 12:43:17'),
(23, NULL, 'user.grade_otan', 'Grade (code OTAN)', 'user', NULL, 'Code OTAN (ex. OF-2)', 'user', 1, '2026-03-15 12:43:17'),
(24, NULL, 'user.grade_full', 'Grade (hybride)', 'user', NULL, 'Libellé avec code OTAN (ex. Capitaine (OF-2))', 'user', 1, '2026-03-15 12:43:17'),
(25, NULL, 'user.category_label', 'Catégorie de personnel', 'user', NULL, 'Libellé de la catégorie (Officier, etc.)', 'user', 1, '2026-03-15 12:43:17'),
(26, NULL, 'superior.grade_text', 'Grade du supérieur (texte)', 'hierarchy', NULL, 'Grade classique du supérieur', 'hierarchy', 1, '2026-03-15 12:43:17'),
(27, NULL, 'superior.grade_otan', 'Grade du supérieur (OTAN)', 'hierarchy', NULL, 'Code OTAN du supérieur', 'hierarchy', 1, '2026-03-15 12:43:17');

-- --------------------------------------------------------

--
-- Structure de la table `document_versions`
--

CREATE TABLE `document_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `change_notes` text DEFAULT NULL,
  `version_label` varchar(50) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_versions`
--

INSERT INTO `document_versions` (`id`, `document_id`, `version_number`, `file_path`, `original_name`, `checksum`, `mime_type`, `size`, `created_by`, `change_notes`, `version_label`, `published_at`, `is_current`, `created_at`) VALUES
(1, 1, 1, '7/1/v1.pdf', 'Close Air Support _ IVAO Documentation Library.pdf', '04bfc52079f3df444d62cd5abbc5ffe115e940648e9b22ff27fc5e46a29ac621', 'application/pdf', 1202664, 5, NULL, NULL, NULL, 1, '2026-04-05 09:38:22');

-- --------------------------------------------------------

--
-- Structure de la table `document_workflows`
--

CREATE TABLE `document_workflows` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `status_from` varchar(50) DEFAULT NULL,
  `status_to` varchar(50) NOT NULL,
  `action_label` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `acted_by` int(10) UNSIGNED DEFAULT NULL,
  `acted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `document_workflows`
--

INSERT INTO `document_workflows` (`id`, `document_id`, `status_from`, `status_to`, `action_label`, `comment`, `acted_by`, `acted_at`) VALUES
(1, 1, 'draft', 'pending_validation', 'Soumis à validation', NULL, NULL, '2026-03-15 14:48:23'),
(2, 1, 'pending_validation', 'validated', 'Validé', NULL, NULL, '2026-03-15 14:48:39'),
(3, 3, 'draft', 'pending_validation', 'Soumis à validation', NULL, 3, '2026-04-04 16:31:27'),
(4, 3, 'pending_validation', 'validated', 'Validé', NULL, 3, '2026-04-04 16:34:41');

-- --------------------------------------------------------

--
-- Structure de la table `email_deliveries`
--

CREATE TABLE `email_deliveries` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `event_code` varchar(80) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `transport` varchar(32) NOT NULL,
  `status` varchar(20) NOT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `payload_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_summary`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `email_deliveries`
--

INSERT INTO `email_deliveries` (`id`, `tenant_id`, `event_code`, `recipient`, `subject`, `transport`, `status`, `provider_message_id`, `error_message`, `payload_summary`, `created_at`) VALUES
(1, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tetard.tanguy@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not connect to SMTP host. STARTTLS command failed Try again later\r\n', '{\"target_user_id\":5}', '2026-04-05 09:46:40'),
(2, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tetard.tanguy@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"target_user_id\":5}', '2026-04-05 09:48:16'),
(3, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tetard.tanguy@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not connect to SMTP host. STARTTLS command failed Try again later\r\n', '{\"target_user_id\":5}', '2026-04-05 09:52:45'),
(4, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tetard.tanguy@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not connect to SMTP host. STARTTLS command failed Try again later\r\n', '{\"target_user_id\":5}', '2026-04-05 10:01:11'),
(5, 7, 'NEW_DEVICE_LOGIN', 'tetard.tanguy@gmail.com', 'Nouvelle connexion sur votre compte', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"new_device\"}', '2026-04-05 10:02:17'),
(6, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"register\"}', '2026-04-05 11:03:23'),
(7, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"register\"}', '2026-04-05 11:08:28'),
(8, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'failed', NULL, 'SMTP Error: Could not connect to SMTP host. STARTTLS command failed Try again later\r\n', '{\"purpose\":\"register\"}', '2026-04-05 11:13:07'),
(9, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"register\"}', '2026-04-05 11:14:00'),
(10, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"register\"}', '2026-04-05 11:18:34'),
(11, 1, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — Aucune organisation', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"register\"}', '2026-04-05 11:21:42'),
(12, 1, 'NEW_COMMUNITY_MEMBER', 'tetard.tanguy@gmail.com', 'Nouveau membre — Aucune organisation', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"staff_notify\"}', '2026-04-05 11:21:58'),
(13, 1, 'NEW_DEVICE_LOGIN', 'tanguy.inc@gmail.com', 'Nouvelle connexion sur votre compte', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"new_device\"}', '2026-04-05 11:22:15'),
(14, 7, 'COMMUNITY_INVITATION', 'wikzzcoc@gmail.com', 'Invitation — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"invitation\"}', '2026-04-05 12:04:47'),
(15, 7, 'TENANT_USER_SETUP', 'tanguy.inc@gmail.com', 'Finalisez votre compte — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"tenant_user_setup\"}', '2026-04-05 16:31:54'),
(16, 7, 'ENLISTMENT_ACCEPTED_CANDIDATE', 'tanguy.inc@gmail.com', 'Candidature acceptée — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"enlistment_accepted_candidate\"}', '2026-04-05 16:32:01'),
(17, 7, 'ENLISTMENT_ACCEPTED_STAFF', 'tetard.tanguy@gmail.com', 'Candidature acceptée — ATHENA — #2', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"purpose\":\"enlistment_accepted\",\"enlistment_id\":2}', '2026-04-05 16:32:08'),
(18, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tetard.tanguy@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'failed', NULL, 'SMTP Error: Could not authenticate.', '{\"target_user_id\":5}', '2026-04-05 22:37:54'),
(19, 7, 'PROFILE_INCOMPLETE_REMINDER', 'tanguy.inc@gmail.com', 'Complétez votre fiche personnelle — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"target_user_id\":8}', '2026-04-06 17:36:23'),
(20, 7, 'USER_REGISTER_CONFIRMATION', 'tanguy.inc@gmail.com', 'Confirmez votre adresse e-mail — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"register\"}', '2026-04-06 17:44:55'),
(21, 7, 'NEW_COMMUNITY_MEMBER', 'tetard.tanguy@gmail.com', 'Nouveau membre — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"staff_notify\"}', '2026-04-06 17:45:09'),
(22, NULL, 'privacy_rights_request', 'no-reply@athena.ttrd.fr', '[Athena] Demande relative aux données personnelles', 'smtp', 'sent', NULL, NULL, '{\"request_kind\":\"access\"}', '2026-04-06 20:28:36'),
(23, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[COMSPEC erreur] ArgumentCountError — /formations/installer-task-force-radio-arma3', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/formations\\/installer-task-force-radio-arma3\"}', '2026-04-06 21:24:21'),
(24, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/configuration', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/configuration\"}', '2026-04-06 21:28:37'),
(25, 7, 'NEW_DEVICE_LOGIN', 'tetard.tanguy@gmail.com', 'Nouvelle connexion sur votre compte', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"new_device\"}', '2026-04-06 21:43:35'),
(26, 7, 'NEW_DEVICE_LOGIN', 'tetard.tanguy@gmail.com', 'Nouvelle connexion sur votre compte', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"new_device\"}', '2026-04-07 09:09:00'),
(27, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/conformite/export-dossier/telecharger', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/conformite\\/export-dossier\\/telecharger\"}', '2026-04-08 08:37:12'),
(28, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/configuration', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/configuration\"}', '2026-04-08 10:44:50'),
(29, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /admin/ops-center', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/admin\\/ops-center\"}', '2026-04-09 06:58:14'),
(30, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/recruitment/offers', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/recruitment\\/offers\"}', '2026-04-09 10:47:32'),
(31, 7, 'ENLISTMENT_SUBMITTED_STAFF', 'tetard.tanguy@gmail.com', 'Nouvelle candidature — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"enlistment_submitted\",\"enlistment_id\":3}', '2026-04-09 10:52:53'),
(32, 7, 'ENLISTMENT_ACCEPTED_CANDIDATE', 'tetard.tanguy@gmail.com', 'Candidature acceptée — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"enlistment_accepted_candidate\",\"account_scenario\":\"existing\"}', '2026-04-09 10:53:37'),
(33, 7, 'ENLISTMENT_ACCEPTED_STAFF', 'tetard.tanguy@gmail.com', 'Candidature acceptée — ATHENA — #3', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"enlistment_accepted\",\"enlistment_id\":3}', '2026-04-09 10:53:37'),
(34, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/configuration', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/configuration\"}', '2026-04-10 13:12:49'),
(35, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/conformite/export-dossier/telecharger', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/conformite\\/export-dossier\\/telecharger\"}', '2026-04-10 13:13:51'),
(36, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/analytics', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/analytics\"}', '2026-04-10 13:14:33'),
(37, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/analytics', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/analytics\"}', '2026-04-10 13:18:26'),
(38, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/analytics', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/analytics\"}', '2026-04-10 13:20:47'),
(39, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/ressources/training/competences/commandement', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/ressources\\/training\\/competences\\/commandement\"}', '2026-04-10 13:26:07'),
(40, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /admin/ops-center', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/admin\\/ops-center\"}', '2026-04-10 13:31:38'),
(41, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /api/orbat/roster', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/api\\/orbat\\/roster\"}', '2026-04-12 17:14:15'),
(42, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /orbat', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/orbat\"}', '2026-04-12 17:16:20'),
(43, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /api/orbat/roster', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/api\\/orbat\\/roster\"}', '2026-04-12 17:18:14'),
(44, 7, 'error_alert', 'tetard.tanguy@gmail.com', '[Athena] Incident technique — /back-office/tableau-operationnel/stream', 'smtp', 'sent', NULL, NULL, '{\"kind\":\"exception\",\"path\":\"\\/back-office\\/tableau-operationnel\\/stream\"}', '2026-04-12 17:21:49');

-- --------------------------------------------------------

--
-- Structure de la table `email_tokens`
--

CREATE TABLE `email_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(64) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `nonce` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `email_tokens`
--

INSERT INTO `email_tokens` (`id`, `tenant_id`, `user_id`, `purpose`, `token_hash`, `nonce`, `expires_at`, `consumed_at`, `metadata`, `created_at`) VALUES
(4, 1, 7, 'register_confirm', '965a7a53f5a9940514a3d16025f1422a65f7b7774ba78febb387fe665edfe334', 'f3ac003c65139733b354104b25060a50', '2026-04-05 11:36:41', '2026-04-05 11:21:58', NULL, '2026-04-05 11:21:42'),
(5, 1, 7, 'device_deny', '54f65a2ffd5fe6bdb6124800702dc248d54c879b829d2b8b712b0fd50b9480c8', 'f12d050aa70608cf', '2026-04-07 11:22:14', NULL, '{\"ip\":\"2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4\",\"ua\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/146.0.0.0 Safari\\/537.36 Edg\\/146.0.0.0\"}', '2026-04-05 11:22:14'),
(6, 7, 8, 'register_confirm', 'f673ef0ac08697528698bd71daa44abf2cbbcb670cb418ec918c5c6c4e187cbf', '53b244b54eba8aa493fbccd897307f9b', '2026-04-06 17:59:54', '2026-04-06 17:45:08', NULL, '2026-04-06 17:44:55'),
(8, 7, 5, 'device_deny', 'b3d5ae2295393967181b7dafe5adf2a27023bb895636ad86d545123f92be6810', 'cac1567daca936f3', '2026-04-09 09:09:00', NULL, '{\"ip\":\"185.24.185.33\",\"ua\":\"Mozilla\\/5.0 (X11; Linux x86_64; rv:140.0) Gecko\\/20100101 Firefox\\/140.0\"}', '2026-04-07 09:09:00');

-- --------------------------------------------------------

--
-- Structure de la table `enlistments`
--

CREATE TABLE `enlistments` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `callsign` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `experience` varchar(255) DEFAULT NULL,
  `specialty` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `availability` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `age` smallint(5) UNSIGNED DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `weekly_availability` varchar(255) DEFAULT NULL,
  `system_config` varchar(500) DEFAULT NULL,
  `microphone_quality` varchar(20) DEFAULT NULL,
  `past_milsim_experience` text DEFAULT NULL,
  `ace_acre_level` varchar(50) DEFAULT NULL,
  `motivation_why_join` text DEFAULT NULL,
  `motivation_accountability` text DEFAULT NULL,
  `commitment_effort` varchar(20) DEFAULT NULL,
  `availability_wed_sat` varchar(20) DEFAULT NULL,
  `no_ai_confirmed` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'submitted',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewer_comment` text DEFAULT NULL,
  `submitter_user_id` int(10) UNSIGNED DEFAULT NULL,
  `recruitment_preset_id` int(10) UNSIGNED DEFAULT NULL,
  `recruitment_opening_id` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_via` varchar(20) NOT NULL DEFAULT 'guest',
  `consent_sharing_at` datetime DEFAULT NULL,
  `shared_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shared_fields`)),
  `recruitment_rp_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recruitment_rp_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `enlistments`
--

INSERT INTO `enlistments` (`id`, `tenant_id`, `first_name`, `last_name`, `email`, `callsign`, `country`, `experience`, `specialty`, `platform`, `availability`, `notes`, `age`, `timezone`, `weekly_availability`, `system_config`, `microphone_quality`, `past_milsim_experience`, `ace_acre_level`, `motivation_why_join`, `motivation_accountability`, `commitment_effort`, `availability_wed_sat`, `no_ai_confirmed`, `status`, `reviewed_by`, `reviewed_at`, `reviewer_comment`, `submitter_user_id`, `recruitment_preset_id`, `recruitment_opening_id`, `submitted_via`, `consent_sharing_at`, `shared_fields`, `recruitment_rp_json`, `created_at`, `updated_at`) VALUES
(2, 7, 'Melvin', 'MESNEL', 'tanguy.inc@gmail.com', NULL, NULL, NULL, NULL, NULL, 'Ok', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Ok', NULL, NULL, NULL, 1, 'reviewed', 5, '2026-04-05 16:31:53', 'Bienvenu(e)\r\nPense à compléter ton profil rapidement.\r\nLe service RH', 8, NULL, NULL, 'guest', NULL, NULL, NULL, '2026-04-05 11:23:35', '2026-04-05 16:31:53'),
(3, 7, 'Tanguy', 'TETARD', 'tetard.tanguy@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'reviewed', 5, '2026-04-09 10:53:36', NULL, 5, NULL, 1, 'account', '2026-04-09 10:52:49', '{\"share_name\":true,\"share_email\":true,\"share_callsign\":false}', NULL, '2026-04-09 10:52:49', '2026-04-09 10:53:36');

-- --------------------------------------------------------

--
-- Structure de la table `enlistment_canned_messages`
--

CREATE TABLE `enlistment_canned_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(160) NOT NULL,
  `body` text NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `equipment_classes`
--

CREATE TABLE `equipment_classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `equipment_classes`
--

INSERT INTO `equipment_classes` (`id`, `tenant_id`, `name`, `slug`, `category`, `description`, `created_at`) VALUES
(1, 1, 'Radio', 'radio', 'radio', NULL, '2026-03-14 00:01:46'),
(2, 1, 'Optique', 'optic', 'optic', NULL, '2026-03-14 00:01:46'),
(3, 1, 'Armement', 'weapon', 'weapon', NULL, '2026-03-14 00:01:46'),
(4, 1, 'Véhicule', 'vehicle', 'vehicle', NULL, '2026-03-14 00:01:46'),
(5, 1, 'Drone', 'drone', 'drone', NULL, '2026-03-14 00:01:46'),
(6, 1, 'Médical', 'medical', 'medical', NULL, '2026-03-14 00:01:46'),
(13, 7, 'Radio', 'radio', 'radio', NULL, '2026-04-05 09:10:02'),
(14, 7, 'Optique', 'optic', 'optic', NULL, '2026-04-05 09:10:02'),
(15, 7, 'Armement', 'weapon', 'weapon', NULL, '2026-04-05 09:10:02'),
(16, 7, 'Véhicule', 'vehicle', 'vehicle', NULL, '2026-04-05 09:10:02'),
(17, 7, 'Drone', 'drone', 'drone', NULL, '2026-04-05 09:10:02'),
(18, 7, 'Médical', 'medical', 'medical', NULL, '2026-04-05 09:10:02');

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `evaluation_type` enum('QCM','SCENARIO','FIELD') NOT NULL,
  `name` varchar(160) NOT NULL,
  `passing_score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `requires_validator` tinyint(1) NOT NULL DEFAULT 0,
  `validator_role_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fire_tables`
--

CREATE TABLE `fire_tables` (
  `id` int(10) UNSIGNED NOT NULL,
  `weapon_system` varchar(128) NOT NULL,
  `ammo_type` varchar(64) NOT NULL,
  `min_range` int(10) UNSIGNED DEFAULT 0,
  `max_range` int(10) UNSIGNED DEFAULT 0,
  `table_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`table_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `fire_tables`
--

INSERT INTO `fire_tables` (`id`, `weapon_system`, `ammo_type`, `min_range`, `max_range`, `table_json`, `created_at`) VALUES
(1, 'MK6', 'HE', 0, 1200, '[{\"range\":200,\"elevation_mils\":1520,\"charge\":0,\"tof\":8.4},{\"range\":300,\"elevation_mils\":1488,\"charge\":0,\"tof\":10.1},{\"range\":400,\"elevation_mils\":1450,\"charge\":1,\"tof\":11.9},{\"range\":500,\"elevation_mils\":1410,\"charge\":1,\"tof\":13.8},{\"range\":600,\"elevation_mils\":1365,\"charge\":2,\"tof\":15.9},{\"range\":800,\"elevation_mils\":1270,\"charge\":2,\"tof\":19.8},{\"range\":1000,\"elevation_mils\":1170,\"charge\":3,\"tof\":24.1}]', '2026-03-15 10:51:30');

-- --------------------------------------------------------

--
-- Structure de la table `fire_units`
--

CREATE TABLE `fire_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `vehicle_class` varchar(255) DEFAULT NULL,
  `weapon_system` varchar(128) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `pos_y` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `pos_z` decimal(15,4) DEFAULT 0.0000,
  `heading` decimal(10,4) DEFAULT NULL,
  `side` varchar(32) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `last_update_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_attachments`
--

CREATE TABLE `forum_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_banned_words`
--

CREATE TABLE `forum_banned_words` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `word` varchar(255) NOT NULL,
  `severity` varchar(20) DEFAULT 'block',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_banned_words`
--

INSERT INTO `forum_banned_words` (`id`, `tenant_id`, `word`, `severity`, `created_at`) VALUES
(2, 7, 'sexe', 'block', '2026-04-06 21:22:49'),
(3, 7, 'arabe', 'block', '2026-04-06 21:22:51');

-- --------------------------------------------------------

--
-- Structure de la table `forum_blacklisted_domains`
--

CREATE TABLE `forum_blacklisted_domains` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_categories`
--

CREATE TABLE `forum_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `scope` varchar(32) NOT NULL DEFAULT 'general',
  `owner_tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color_theme` varchar(50) DEFAULT 'slate',
  `display_order` int(11) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `min_role_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_categories`
--

INSERT INTO `forum_categories` (`id`, `tenant_id`, `scope`, `owner_tenant_id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color_theme`, `display_order`, `is_locked`, `min_role_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'general', NULL, NULL, 'Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', NULL, 'orange', 10, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(2, 1, 'general', NULL, NULL, 'Général', 'general', 'Discussions générales et présentation.', NULL, 'indigo', 20, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(3, 1, 'general', NULL, NULL, 'Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', NULL, 'violet', 30, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(4, 1, 'general', NULL, NULL, 'Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', NULL, 'rose', 40, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(5, 1, 'general', NULL, NULL, 'Hors sujet', 'hors-sujet', 'Échanges informels.', NULL, 'emerald', 50, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(6, 1, 'organization', 1, NULL, 'Default Organisation — Espace dédié', 'org-default', 'Section forum de votre organisation.', NULL, 'slate', 15, 0, NULL, '2026-04-04 15:13:10', '2026-04-04 15:13:10'),
(13, 7, 'general', NULL, NULL, 'Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', NULL, 'orange', 10, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(14, 7, 'general', NULL, NULL, 'Général', 'general', 'Discussions générales et présentation.', NULL, 'indigo', 20, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(15, 7, 'general', NULL, NULL, 'Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', NULL, 'violet', 30, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(16, 7, 'general', NULL, NULL, 'Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', NULL, 'rose', 40, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(17, 7, 'general', NULL, NULL, 'Hors sujet', 'hors-sujet', 'Échanges informels.', NULL, 'emerald', 50, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(18, 7, 'organization', 7, NULL, 'ATHENA — Espace dédié', 'org-athena-sys', 'Section forum de votre organisation.', NULL, 'slate', 15, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(19, 7, 'general', NULL, 16, 'ATAK / COMSPEC', 'atak-comspec', '', NULL, 'slate', 0, 0, NULL, '2026-04-06 17:07:42', '2026-04-06 17:07:42'),
(20, 7, 'general', NULL, 16, 'CTAB', 'ctab', '', NULL, 'slate', 0, 0, NULL, '2026-04-06 17:07:56', '2026-04-06 17:07:56'),
(21, 7, 'general', NULL, 14, 'FORMATIONS', 'formations', 'Pour partager vos code de formation hors catalogue', NULL, 'slate', 0, 0, NULL, '2026-04-06 17:12:51', '2026-04-06 17:12:51'),
(23, 7, 'general', NULL, 14, 'RECRUTEMENT EXTERNE', 'recrutement-externe', '', NULL, 'slate', 0, 0, NULL, '2026-04-09 10:59:07', '2026-04-09 10:59:07'),
(24, 7, 'organization', NULL, 18, 'RECRUTEMENT INTERNE', 'recrutement-interne', '', NULL, 'slate', 0, 0, NULL, '2026-04-09 10:59:13', '2026-04-09 10:59:13');

-- --------------------------------------------------------

--
-- Structure de la table `forum_category_subscriptions`
--

CREATE TABLE `forum_category_subscriptions` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_moderation_logs`
--

CREATE TABLE `forum_moderation_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `post_id` int(10) UNSIGNED DEFAULT NULL,
  `rule_type` varchar(64) NOT NULL,
  `score` decimal(10,4) DEFAULT NULL,
  `action_taken` varchar(64) NOT NULL,
  `detail_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_moderation_rules`
--

CREATE TABLE `forum_moderation_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `rule_type` varchar(64) NOT NULL,
  `threshold` decimal(10,4) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_json`)),
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_notifications`
--

CREATE TABLE `forum_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `parent_post_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `publication_badge` varchar(32) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `coop_source_tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `coop_official_kind` varchar(32) DEFAULT NULL,
  `is_draft` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `coop_mission_role` varchar(48) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `tenant_id`, `topic_id`, `parent_post_id`, `user_id`, `body`, `publication_badge`, `is_hidden`, `created_at`, `updated_at`, `coop_source_tenant_id`, `coop_official_kind`, `is_draft`, `coop_mission_role`) VALUES
(3, 7, 2, NULL, 5, 'Une structure dédiée est désormais en place afin d’assurer la gestion, la régulation et l’évolution du système. Cette équipe exerce une mission de pilotage, de contrôle et d’arbitrage sur l’ensemble des modules, des utilisateurs et des flux.\n\nChaque membre est investi d’une responsabilité claire : garantir la cohérence des données, la stabilité de la plateforme et le respect des règles internes. Les interventions sont tracées, les décisions sont assumées, les dérives sont corrigées.\n\nL’administration n’est pas un statut. C’est une fonction d’autorité technique et organisationnelle au service de l’ensemble.\n\n[ATHENA](https://athena.ttrd.fr/public/c/athena-sys)', NULL, 0, '2026-04-05 09:19:27', '2026-04-05 09:19:27', NULL, NULL, 0, NULL),
(4, 7, 3, NULL, 5, '**Cadre général du forum**\n\nLe forum constitue un espace commun, ouvert à l’ensemble des utilisateurs.\nIl est destiné aux échanges généraux, au partage d’informations et aux interactions transversales entre les différentes entités.\n\n**Espaces communautaires dédiés**\n\nChaque communauté dispose d’un espace propre, structuré et réservé à ses membres.\nCes sections permettent une organisation interne claire, des communications ciblées et une gestion autonome des contenus spécifiques à chaque groupe.\n\n**Autorité et modération**\n\nDans ces espaces dédiés, la modération relève en priorité de la chaîne de responsabilité interne à la communauté.\nLes encadrants y exercent une autorité directe, veillent au respect des règles propres à leur structure et assurent la régulation des échanges.\n\n**Supervision centrale**\n\nL’administration centrale conserve un droit permanent de supervision.\nElle peut intervenir en dernier ressort afin de garantir l’unité, la conformité et la cohérence globale du forum.', NULL, 0, '2026-04-05 09:25:31', '2026-04-05 09:26:14', NULL, NULL, 0, NULL),
(6, 7, 2, NULL, 5, 'https://hpanel.hostinger.com/websites/athena.ttrd.fr/databases/my-sql-databases', NULL, 0, '2026-04-05 16:13:11', '2026-04-05 16:13:11', NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `forum_post_reactions`
--

CREATE TABLE `forum_post_reactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reaction_key` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_post_votes`
--

CREATE TABLE `forum_post_votes` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `value` tinyint(4) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_read`
--

CREATE TABLE `forum_read` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `read_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_reports`
--

CREATE TABLE `forum_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED DEFAULT NULL,
  `topic_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `report_type` varchar(32) NOT NULL DEFAULT 'other',
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `reported_url` varchar(2048) DEFAULT NULL,
  `content_kind` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_reports`
--

INSERT INTO `forum_reports` (`id`, `tenant_id`, `reporter_id`, `post_id`, `topic_id`, `reason`, `report_type`, `comment`, `status`, `handled_by`, `handled_at`, `created_at`, `reported_url`, `content_kind`) VALUES
(1, 7, 5, NULL, NULL, 'Fiche personnelle signalée : Melvin MESNEL (compte n° 8)\nAutre — Test', 'other', 'Test', 'handled', 5, '2026-04-06 21:09:23', '2026-04-06 20:58:34', 'https://athena.ttrd.fr/public/personnel/melvin-mesnel', 'member_profile');

-- --------------------------------------------------------

--
-- Structure de la table `forum_report_events`
--

CREATE TABLE `forum_report_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_tags`
--

CREATE TABLE `forum_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_topics`
--

CREATE TABLE `forum_topics` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `is_solved` tinyint(1) NOT NULL DEFAULT 0,
  `best_answer_post_id` int(10) UNSIGNED DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `is_official` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Communiqué officiel (modo)',
  `view_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `auto_locked_at` datetime DEFAULT NULL COMMENT 'Verrouillage auto 6 mois',
  `suppress_auto_lock` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = déverrouillage manuel, ne pas reverrouiller auto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_topics`
--

INSERT INTO `forum_topics` (`id`, `tenant_id`, `category_id`, `user_id`, `title`, `slug`, `is_pinned`, `is_locked`, `is_archived`, `is_solved`, `best_answer_post_id`, `is_hidden`, `is_official`, `view_count`, `created_at`, `updated_at`, `auto_locked_at`, `suppress_auto_lock`) VALUES
(2, 7, 18, 5, 'Ouverture de l\'équipe d\'administration', 'ouverture-de-léquipe-dadministration-953547', 0, 0, 0, 0, NULL, 0, 0, 46, '2026-04-05 09:19:27', '2026-04-09 10:35:08', NULL, 0),
(3, 7, 13, 5, 'Fonctionnement du \"Brief\"', 'fonctionnement-du-brief-431290', 0, 1, 0, 0, NULL, 0, 0, 21, '2026-04-05 09:25:31', '2026-04-06 19:47:27', NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `forum_topic_subscriptions`
--

CREATE TABLE `forum_topic_subscriptions` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_topic_tags`
--

CREATE TABLE `forum_topic_tags` (
  `topic_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `grades`
--

CREATE TABLE `grades` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `grade_system_id` bigint(20) UNSIGNED NOT NULL,
  `grade_category_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `label_short` varchar(100) NOT NULL,
  `label_long` varchar(150) NOT NULL,
  `label_otan` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_commissioned` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `nato_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `grades`
--

INSERT INTO `grades` (`id`, `grade_system_id`, `grade_category_id`, `code`, `label_short`, `label_long`, `label_otan`, `sort_order`, `is_commissioned`, `is_active`, `created_at`, `updated_at`, `nato_code`) VALUES
(1, 1, 1, 'SL', 'Sous-lieutenant', 'Sous-lieutenant', 'OF-1', 11, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(2, 1, 1, 'LT', 'Lieutenant', 'Lieutenant', 'OF-1', 12, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(3, 1, 1, 'CNE', 'Capitaine', 'Capitaine', 'OF-2', 13, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(4, 1, 1, 'CDT', 'Commandant', 'Commandant', 'OF-3', 14, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(5, 1, 1, 'LCL', 'Lieutenant-colonel', 'Lieutenant-colonel', 'OF-4', 15, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(6, 1, 1, 'COL', 'Colonel', 'Colonel', 'OF-5', 16, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(7, 2, 1, '2LT', 'Second Lieutenant', 'Second Lieutenant', 'O-1', 11, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(8, 2, 1, '1LT', 'First Lieutenant', 'First Lieutenant', 'O-2', 12, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(9, 2, 1, 'CPT', 'Captain', 'Captain', 'O-3', 13, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(10, 2, 1, 'MAJ', 'Major', 'Major', 'O-4', 14, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(11, 2, 1, 'LTC', 'Lieutenant Colonel', 'Lieutenant Colonel', 'O-5', 15, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(12, 2, 1, 'COL', 'Colonel', 'Colonel', 'O-6', 16, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL),
(13, 1, 2, 'MAJ', 'Major', 'Major', 'OR-9', 21, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(14, 1, 2, 'ADC', 'Adc', 'Adjudant-chef', 'OR-8', 22, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(15, 1, 2, 'ADJ', 'Adj', 'Adjudant', 'OR-7', 23, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(16, 1, 2, 'SCH', 'Sch', 'Sergent-chef', 'OR-6', 24, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(17, 1, 2, 'SGT', 'Sgt', 'Sergent', 'OR-5', 25, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(18, 1, 3, 'CCH', 'Cch', 'Caporal-chef', 'OR-4', 31, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(19, 1, 3, 'CPL', 'Cpl', 'Caporal', 'OR-3', 32, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(20, 1, 3, 'SD1', 'Sdt 1', 'Soldat de 1re classe', 'OR-2', 33, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(21, 1, 3, 'SD2', 'Sdt 2', 'Soldat de 2e classe', 'OR-1', 34, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(22, 2, 2, 'SGM', 'SGM', 'Sergeant Major', 'E-9', 21, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(23, 2, 2, 'MSG', 'MSG', 'Master Sergeant', 'E-8', 22, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(24, 2, 2, 'SFC', 'SFC', 'Sergeant First Class', 'E-7', 23, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(25, 2, 2, 'SSG', 'SSG', 'Staff Sergeant', 'E-6', 24, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(26, 2, 2, 'SGT', 'SGT', 'Sergeant', 'E-5', 25, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(27, 2, 3, 'CPL', 'CPL', 'Corporal', 'E-4', 31, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(28, 2, 3, 'PFC', 'PFC', 'Private First Class', 'E-3', 32, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(29, 2, 3, 'PV2', 'PV2', 'Private Second Class', 'E-2', 33, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(30, 2, 3, 'PVT', 'PVT', 'Private', 'E-1', 34, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL),
(31, 1, 1, 'GBR', 'Gén. bde', 'Général de brigade', 'OF-6', 17, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(32, 1, 1, 'GDV', 'Gén. div.', 'Général de division', 'OF-7', 18, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(33, 1, 1, 'GCA', 'Gén. c. a.', 'Général de corps d’armée', 'OF-8', 19, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(34, 1, 1, 'GAR', 'Gén. armée', 'Général d’armée', 'OF-9', 20, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(35, 2, 1, 'BG', 'Brig. Gen.', 'Brigadier General', 'O-7', 17, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(36, 2, 1, 'MG', 'Maj. Gen.', 'Major General', 'O-8', 18, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(37, 2, 1, 'LTG', 'Lt Gen.', 'Lieutenant General', 'O-9', 19, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(38, 2, 1, 'GEN', 'Gen.', 'General', 'O-10', 20, 1, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(39, 1, 4, 'CIV', 'Civil', 'Personnel civil', NULL, 80, 0, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(40, 2, 4, 'CIV', 'Civilian', 'Civilian (non-military)', NULL, 80, 0, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(41, 1, 5, 'HG', 'Hors grade', 'Sans grade militaire', NULL, 90, 0, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL),
(42, 2, 5, 'HG', 'No grade', 'No military grade', NULL, 90, 0, 1, '2026-04-06 17:17:38', '2026-04-06 17:17:38', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `grades_legacy`
--

CREATE TABLE `grades_legacy` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `nato_code` varchar(10) DEFAULT NULL,
  `rank_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `grades_legacy`
--

INSERT INTO `grades_legacy` (`id`, `tenant_id`, `name`, `short_name`, `nato_code`, `rank_order`, `created_at`) VALUES
(1, 1, 'Officer', 'OFR', NULL, 10, '2026-03-13 17:47:31');

-- --------------------------------------------------------

--
-- Structure de la table `grade_categories`
--

CREATE TABLE `grade_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `grade_categories`
--

INSERT INTO `grade_categories` (`id`, `code`, `label`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'OFFICIER', 'Officier', 10, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17'),
(2, 'SOUS_OFFICIER', 'Sous-officier', 20, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17'),
(3, 'MDR', 'Militaire du rang', 30, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17'),
(4, 'CIVIL', 'Civil', 40, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17'),
(5, 'HORS_GRADE', 'Hors grades', 50, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17');

-- --------------------------------------------------------

--
-- Structure de la table `grade_systems`
--

CREATE TABLE `grade_systems` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `format_type` enum('classic','otan') NOT NULL DEFAULT 'classic',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `grade_systems`
--

INSERT INTO `grade_systems` (`id`, `code`, `label`, `country_code`, `format_type`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'FR_CLASSIC', 'Grades français (classique)', 'FR', 'classic', 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17'),
(2, 'US_CLASSIC', 'Grades américains (classique)', 'US', 'classic', 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17');

-- --------------------------------------------------------

--
-- Structure de la table `iff_asset_status`
--

CREATE TABLE `iff_asset_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `asset_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `platform_type` varchar(64) DEFAULT NULL,
  `current_challenge_id` int(10) UNSIGNED DEFAULT NULL,
  `response_code` varchar(64) DEFAULT NULL,
  `response_status` varchar(32) DEFAULT 'PENDING',
  `responded_at` datetime DEFAULT NULL,
  `grace_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `iff_challenges`
--

CREATE TABLE `iff_challenges` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `code` varchar(64) NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_until` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `intel_reports`
--

CREATE TABLE `intel_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `source_callsign` varchar(128) DEFAULT NULL,
  `report_type` varchar(64) DEFAULT NULL,
  `target_type` varchar(64) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `confidence_score` int(10) UNSIGNED DEFAULT 0,
  `raw_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_payload_json`)),
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `merged_count` int(10) UNSIGNED DEFAULT 1,
  `status` varchar(32) DEFAULT 'TEMPORARY',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `intel_reports`
--

INSERT INTO `intel_reports` (`id`, `mission_id`, `source_callsign`, `report_type`, `target_type`, `pos_x`, `pos_y`, `pos_z`, `confidence_score`, `raw_payload_json`, `first_seen_at`, `last_seen_at`, `merged_count`, `status`, `created_at`, `updated_at`) VALUES
(1, 'mission_7_map_1', 'C2', NULL, 'INFANTRY', 15000.0000, 15000.0000, 0.0000, 0, '{\"missionId\":\"mission_7_map_1\",\"target_type\":\"INFANTRY\",\"pos_x\":15000,\"pos_y\":15000,\"source_callsign\":\"C2\"}', '2026-04-12 17:17:11', '2026-04-12 17:17:11', 1, 'TEMPORARY', '2026-04-12 17:17:11', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `intel_reports_events`
--

CREATE TABLE `intel_reports_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `intel_report_id` int(10) UNSIGNED NOT NULL,
  `source_callsign` varchar(128) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_cooperation_consents`
--

CREATE TABLE `interteam_cooperation_consents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `selections_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selections_json`)),
  `otp_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consent_expires_at` datetime DEFAULT NULL,
  `justification_sensitive` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_cooperation_otp_attempts`
--

CREATE TABLE `interteam_cooperation_otp_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `outcome` varchar(16) NOT NULL,
  `ip_prefix` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_missions`
--

CREATE TABLE `interteam_missions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `created_by_tenant_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cooperation_starts_at` datetime DEFAULT NULL,
  `cooperation_ends_at` datetime DEFAULT NULL,
  `coop_forum_category_id` int(10) UNSIGNED DEFAULT NULL,
  `coop_forum_topic_id` int(10) UNSIGNED DEFAULT NULL,
  `meeting_replay_url` varchar(500) DEFAULT NULL,
  `atak_endpoint_primary` varchar(255) DEFAULT NULL,
  `atak_endpoint_partner` varchar(255) DEFAULT NULL,
  `liaison_notes` text DEFAULT NULL,
  `cooperation_phase` varchar(32) DEFAULT NULL,
  `cooperation_priority` varchar(24) NOT NULL DEFAULT 'routine',
  `cooperation_typology` varchar(48) DEFAULT NULL,
  `proposal_deadline_at` datetime DEFAULT NULL,
  `requesting_tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `counter_proposal_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`counter_proposal_json`)),
  `counter_proposal_submitted_at` datetime DEFAULT NULL,
  `counter_proposal_tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `counter_proposal_status` varchar(24) DEFAULT NULL,
  `proposal_deadline_notified_at` datetime DEFAULT NULL,
  `activation_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activation_snapshot_json`)),
  `suspensive_conditions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`suspensive_conditions_json`)),
  `exchange_lock_mode` varchar(24) NOT NULL DEFAULT 'none',
  `closure_summary` text DEFAULT NULL,
  `closure_motive` varchar(500) DEFAULT NULL,
  `archive_retention` varchar(24) DEFAULT 'standard',
  `atak_primary_label` varchar(160) DEFAULT NULL,
  `atak_partner_label` varchar(160) DEFAULT NULL,
  `atak_bascule_notes` text DEFAULT NULL,
  `atak_sync_status` varchar(32) DEFAULT NULL,
  `competency_needs_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`competency_needs_json`)),
  `cooperation_checklist_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cooperation_checklist_json`)),
  `template_source_mission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `crisis_mode` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `interteam_missions`
--

INSERT INTO `interteam_missions` (`id`, `title`, `slug`, `status`, `created_by_tenant_id`, `created_by_user_id`, `created_at`, `updated_at`, `cooperation_starts_at`, `cooperation_ends_at`, `coop_forum_category_id`, `coop_forum_topic_id`, `meeting_replay_url`, `atak_endpoint_primary`, `atak_endpoint_partner`, `liaison_notes`, `cooperation_phase`, `cooperation_priority`, `cooperation_typology`, `proposal_deadline_at`, `requesting_tenant_id`, `counter_proposal_json`, `counter_proposal_submitted_at`, `counter_proposal_tenant_id`, `counter_proposal_status`, `proposal_deadline_notified_at`, `activation_snapshot_json`, `suspensive_conditions_json`, `exchange_lock_mode`, `closure_summary`, `closure_motive`, `archive_retention`, `atak_primary_label`, `atak_partner_label`, `atak_bascule_notes`, `atak_sync_status`, `competency_needs_json`, `cooperation_checklist_json`, `template_source_mission_id`, `crisis_mode`) VALUES
(1, 'Exercice Winter- Coopération de formation', 'exercice-winter-coopération-de-formation', 'draft', 7, 5, '2026-04-09 07:02:00', '2026-04-09 07:02:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'draft', 'routine', NULL, NULL, 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', NULL, NULL, 'standard', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_events`
--

CREATE TABLE `interteam_mission_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED NOT NULL,
  `actor_tenant_id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `interteam_mission_events`
--

INSERT INTO `interteam_mission_events` (`id`, `mission_id`, `actor_user_id`, `actor_tenant_id`, `event_type`, `payload_json`, `created_at`) VALUES
(1, 1, 5, 7, 'mission_created', '{\"title\":\"Exercice Winter- Coopération de formation\"}', '2026-04-09 07:02:00');

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_forum_grants`
--

CREATE TABLE `interteam_mission_forum_grants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `grant_type` varchar(16) NOT NULL,
  `resource_id` int(10) UNSIGNED NOT NULL,
  `home_tenant_id` int(10) UNSIGNED NOT NULL,
  `consumer_tenant_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_meetings`
--

CREATE TABLE `interteam_mission_meetings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `replay_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `meeting_state` varchar(24) NOT NULL DEFAULT 'planned',
  `expected_participants_note` text DEFAULT NULL,
  `minutes_text` text DEFAULT NULL,
  `meeting_title` varchar(255) DEFAULT NULL,
  `meeting_agenda` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_members`
--

CREATE TABLE `interteam_mission_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `role_slug` varchar(48) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_by_user_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_participants`
--

CREATE TABLE `interteam_mission_participants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'partner',
  `status` varchar(16) NOT NULL DEFAULT 'invited',
  `invited_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `interteam_mission_participants`
--

INSERT INTO `interteam_mission_participants` (`id`, `mission_id`, `tenant_id`, `role`, `status`, `invited_at`, `responded_at`) VALUES
(1, 1, 7, 'lead', 'active', '2026-04-09 07:02:00', '2026-04-09 07:02:00');

-- --------------------------------------------------------

--
-- Structure de la table `interteam_mission_rex`
--

CREATE TABLE `interteam_mission_rex` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `worked_well` text DEFAULT NULL,
  `failed_aspects` text DEFAULT NULL,
  `coordination_incidents` text DEFAULT NULL,
  `sharing_difficulties` text DEFAULT NULL,
  `technical_difficulties` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `rating_fluidity` tinyint(3) UNSIGNED DEFAULT NULL,
  `rating_security` tinyint(3) UNSIGNED DEFAULT NULL,
  `rating_usefulness` tinyint(3) UNSIGNED DEFAULT NULL,
  `rating_reactivity` tinyint(3) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `knowledge_units`
--

CREATE TABLE `knowledge_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `competency_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `legacy_training_certificates`
--

CREATE TABLE `legacy_training_certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `training_module_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `certificate_code` varchar(50) NOT NULL,
  `issued_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `legacy_training_modules`
--

CREATE TABLE `legacy_training_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'html',
  `status` varchar(50) DEFAULT 'published',
  `estimated_duration_min` int(11) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `legacy_training_progress`
--

CREATE TABLE `legacy_training_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `training_module_id` int(10) UNSIGNED NOT NULL,
  `progress_percent` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT 'in_progress',
  `started_at` datetime DEFAULT current_timestamp(),
  `last_activity_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `success`, `created_at`) VALUES
(1, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 10:02:15'),
(2, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 11:22:14'),
(3, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 12:03:17'),
(4, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 15:52:27'),
(5, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 16:33:02'),
(6, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 1, '2026-04-05 21:25:48'),
(7, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 1, '2026-04-05 22:38:29'),
(8, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 10:37:30'),
(9, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 11:50:23'),
(10, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 17:01:20'),
(11, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 17:03:47'),
(12, 'tanguy.inc@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 17:47:37'),
(13, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 19:04:22'),
(14, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 21:04:31'),
(15, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 1, '2026-04-06 21:07:34'),
(16, 'tetard.tanguy@gmail.com', '2a0d:e487:414f:dab8:b014:a438:5559:5d7e', 1, '2026-04-06 21:43:28'),
(17, 'tetard.tanguy@gmail.com', '185.24.185.33', 1, '2026-04-07 09:08:59'),
(18, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:a8fc:7222:8fa0:df85', 1, '2026-04-07 10:34:50'),
(19, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:a8fc:7222:8fa0:df85', 1, '2026-04-07 18:17:00'),
(20, 'tetard.tanguy@gmail.com', '185.24.185.25', 1, '2026-04-08 08:33:22'),
(21, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 1, '2026-04-08 10:36:58'),
(22, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 1, '2026-04-09 06:55:25'),
(23, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:215e:b6db:1a93:eb5e', 1, '2026-04-09 06:55:32'),
(24, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:b535:5c9:4a3b:b190', 1, '2026-04-09 10:18:59'),
(25, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:b535:5c9:4a3b:b190', 1, '2026-04-09 17:04:37'),
(26, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:718e:7789:2f45:13d2', 1, '2026-04-10 13:01:39'),
(27, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:3553:1908:15f0:20d0', 1, '2026-04-12 16:42:09'),
(28, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:1c51:8e58:5169:60a4', 1, '2026-04-13 10:55:47');

-- --------------------------------------------------------

--
-- Structure de la table `logs_positions`
--

CREATE TABLE `logs_positions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `mission_id` varchar(128) NOT NULL,
  `unit_id` varchar(128) NOT NULL,
  `callsign` varchar(128) NOT NULL,
  `unit_type` varchar(64) DEFAULT NULL,
  `side` varchar(32) DEFAULT NULL,
  `pos_x` decimal(15,4) NOT NULL,
  `pos_y` decimal(15,4) NOT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `heading` decimal(10,4) DEFAULT NULL,
  `speed` decimal(10,4) DEFAULT NULL,
  `state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`state_json`)),
  `logged_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `moderation_actions`
--

CREATE TABLE `moderation_actions` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(10) UNSIGNED DEFAULT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `target_user_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED NOT NULL,
  `action_type` varchar(32) NOT NULL,
  `reason` text DEFAULT NULL,
  `restrictions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Granular restrictions' CHECK (json_valid(`restrictions_json`)),
  `sanction_scope` varchar(16) NOT NULL DEFAULT 'tenant' COMMENT 'tenant=org level 0, platform=site levels 1-3',
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `moderation_artifacts`
--

CREATE TABLE `moderation_artifacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Auteur upload ou saisie',
  `source_type` varchar(40) NOT NULL COMMENT 'forum_upload|document_version|courrier_document',
  `source_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'PK métier (version_id, courrier doc id); 0 si N/A',
  `source_key` varchar(255) DEFAULT NULL COMMENT 'Clé secondaire (ex. nom fichier forum)',
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Chemin relatif app (storage/... ou public/...)',
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `state` varchar(32) NOT NULL DEFAULT 'pending_scan' COMMENT 'pending_scan|clean|quarantined|rejected|approved_override',
  `risk_score` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `reason_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reason_codes`)),
  `scan_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scan_log`)),
  `ruleset_version` varchar(32) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `moderation_artifacts`
--

INSERT INTO `moderation_artifacts` (`id`, `tenant_id`, `user_id`, `source_type`, `source_id`, `source_key`, `file_path`, `original_name`, `mime`, `sha256`, `state`, `risk_score`, `reason_codes`, `scan_log`, `ruleset_version`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 7, 5, 'document_version', 1, NULL, 'documents/7/1/v1.pdf', 'Close Air Support _ IVAO Documentation Library.pdf', 'application/pdf', '04bfc52079f3df444d62cd5abbc5ffe115e940648e9b22ff27fc5e46a29ac621', 'clean', 0, '[]', '{\"clamav\":{\"infected\":false,\"skipped\":true,\"detail\":\"clamav_disabled\"},\"meta\":{\"score\":0,\"codes\":[]}}', '2026.04.05', NULL, '2026-04-05 09:38:22', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `moderation_cases`
--

CREATE TABLE `moderation_cases` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `subject_user_id` int(10) UNSIGNED NOT NULL,
  `opened_by_user_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `priority` varchar(20) DEFAULT 'normal',
  `created_at` datetime DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `moderation_decisions`
--

CREATE TABLE `moderation_decisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `artifact_id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(32) NOT NULL COMMENT 'approve_override|reject|release',
  `reason_code` varchar(64) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `moderation_evidence`
--

CREATE TABLE `moderation_evidence` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(1000) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `modpacks`
--

CREATE TABLE `modpacks` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `version` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `size` int(10) UNSIGNED DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `modpack_images`
--

CREATE TABLE `modpack_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `modpack_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `modules`
--

CREATE TABLE `modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `framework_id` int(10) UNSIGNED DEFAULT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `module_type` enum('ALPHA','BRAVO','CHARLIE','DELTA') NOT NULL,
  `delivery_mode` enum('INITIAL','RENFORCE','RECYCLAGE','CRITIQUE') NOT NULL DEFAULT 'INITIAL',
  `description` text DEFAULT NULL,
  `duration_min` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_mandatory_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `module_competencies`
--

CREATE TABLE `module_competencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `competency_id` int(10) UNSIGNED NOT NULL,
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `module_dependencies`
--

CREATE TABLE `module_dependencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `requires_module_id` int(10) UNSIGNED NOT NULL,
  `dependency_type` enum('PREREQUIS','RENFORCEMENT','RECYCLAGE') NOT NULL DEFAULT 'PREREQUIS',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `module_knowledge`
--

CREATE TABLE `module_knowledge` (
  `module_id` int(10) UNSIGNED NOT NULL,
  `knowledge_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `module_sequences`
--

CREATE TABLE `module_sequences` (
  `id` int(10) UNSIGNED NOT NULL,
  `framework_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `sequence_order` int(11) NOT NULL,
  `phase_label` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ops_board_assets`
--

CREATE TABLE `ops_board_assets` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(180) NOT NULL,
  `type` varchar(80) NOT NULL,
  `reference` varchar(512) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ops_board_assignments`
--

CREATE TABLE `ops_board_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_label` varchar(120) DEFAULT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ops_board_audience`
--

CREATE TABLE `ops_board_audience` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `audience_type` enum('tenant','unit','role','global') NOT NULL,
  `audience_value` varchar(191) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ops_board_history`
--

CREATE TABLE `ops_board_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ops_board_items`
--

CREATE TABLE `ops_board_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED DEFAULT NULL,
  `block_type` enum('permanence_speciale','info_pratique','manifestation','flash_info') NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `visibility_level` varchar(64) NOT NULL DEFAULT 'tenant',
  `linked_type` enum('event','mission','formation','none') DEFAULT 'none',
  `linked_id` int(10) UNSIGNED DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `publish_at` datetime DEFAULT NULL,
  `priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `status` enum('draft','published','archived','expired') NOT NULL DEFAULT 'draft',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `created_at`) VALUES
(2, 8, '96b859c892ac92a1b127e6a750706eddb6c860586656376cafb10615c1c27b97', '2026-04-08 16:31:53', '2026-04-05 16:31:53');

-- --------------------------------------------------------

--
-- Structure de la table `pending_community_creates`
--

CREATE TABLE `pending_community_creates` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `payload_json` text NOT NULL,
  `plan_slug` varchar(50) NOT NULL,
  `stripe_price_id` varchar(100) NOT NULL,
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `action` varchar(32) DEFAULT NULL,
  `scope` enum('site','community','intra') NOT NULL DEFAULT 'community',
  `rbac_scope` enum('global','tenant','unit') NOT NULL DEFAULT 'tenant',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `tenant_id`, `name`, `slug`, `module`, `action`, `scope`, `rbac_scope`, `created_at`) VALUES
(1, 1, 'Voir le forum', 'forum.view', 'forum', 'view', 'intra', 'unit', '2026-03-13 19:23:12'),
(2, 1, 'Créer un sujet', 'forum.create_topic', 'forum', 'create', 'intra', 'unit', '2026-03-13 19:23:12'),
(3, 1, 'Répondre', 'forum.reply', 'forum', 'create', 'intra', 'unit', '2026-03-13 19:23:12'),
(4, 1, 'Modifier ses messages', 'forum.edit_own', 'forum', 'update', 'intra', 'unit', '2026-03-13 19:23:12'),
(5, 1, 'Supprimer ses messages', 'forum.delete_own', 'forum', 'delete', 'intra', 'unit', '2026-03-13 19:23:12'),
(6, 1, 'Modérer le forum (périmètre étendu)', 'forum.moderate', 'forum', 'moderate', 'community', 'tenant', '2026-03-13 19:23:12'),
(7, 1, 'Gérer les catégories (identifiant historique)', 'forum.manage_categories', 'forum', 'manage', 'community', 'tenant', '2026-03-13 19:23:12'),
(8, 1, 'Accès administration (tenant)', 'admin.access', 'admin', 'manage', 'community', 'tenant', '2026-03-13 22:57:32'),
(9, 1, 'Voir les documents', 'documents.view', 'documents', 'view', 'community', 'tenant', '2026-03-14 00:01:46'),
(10, 1, 'Téléverser des documents', 'documents.upload', 'documents', 'create', 'community', 'tenant', '2026-03-14 00:01:46'),
(11, 1, 'Modifier les documents (héritage)', 'documents.update', 'documents', 'update', 'community', 'tenant', '2026-03-14 00:01:46'),
(12, 1, 'Archiver / désarchiver', 'documents.archive', 'documents', 'archive', 'community', 'tenant', '2026-03-14 00:01:46'),
(13, 1, 'Télécharger les documents sensibles', 'documents.download_sensitive', 'documents', 'view', 'community', 'tenant', '2026-03-14 00:01:46'),
(14, 1, 'Voir les formations', 'training.view', 'training', 'view', 'community', 'tenant', '2026-03-15 11:51:40'),
(15, 1, 'Gérer les formations (périmètre étendu)', 'training.manage', 'training', 'manage', 'community', 'tenant', '2026-03-15 11:51:40'),
(16, 1, 'Assigner les formations', 'training.assign', 'training', 'assign', 'community', 'tenant', '2026-03-15 11:51:40'),
(17, 1, 'Administration système', 'admin.system', 'admin', NULL, 'community', 'tenant', '2026-03-15 12:02:28'),
(18, 1, 'Administration organisationnelle', 'admin.organization', 'admin', 'manage', 'community', 'tenant', '2026-03-15 12:02:28'),
(19, 1, 'Voir le Bureau Courrier', 'courrier.view', 'courrier', 'view', 'community', 'tenant', '2026-03-15 12:43:17'),
(20, 1, 'Créer des documents courrier', 'courrier.create', 'courrier', 'create', 'community', 'tenant', '2026-03-15 12:43:17'),
(21, 1, 'Valider des documents courrier', 'courrier.validate', 'courrier', 'approve', 'community', 'tenant', '2026-03-15 12:43:17'),
(22, 1, 'Archiver des documents courrier', 'courrier.archive', 'courrier', 'archive', 'community', 'tenant', '2026-03-15 12:43:17'),
(23, NULL, 'Administration système (plateforme)', 'admin.system', 'admin', NULL, 'site', 'global', '2026-04-04 15:13:10'),
(24, NULL, 'Accès back-office plateforme', 'admin.access', 'admin', NULL, 'site', 'global', '2026-04-04 15:13:10'),
(25, NULL, 'Gérer les communautés (tenants)', 'site.tenants.manage', 'admin', NULL, 'site', 'global', '2026-04-04 15:13:10'),
(26, 1, 'Modérer la section forum organisation', 'forum.moderate_organization', 'forum', 'moderate', 'community', 'tenant', '2026-04-04 15:13:10'),
(45, 1, 'Envoyer des invitations', 'invitations.send', 'admin', 'create', 'community', 'tenant', '2026-04-05 08:48:49'),
(46, 1, 'Voir le back-office', 'admin.backoffice.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(47, 1, 'Voir les membres', 'admin.members.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(48, 1, 'Gérer les membres', 'admin.members.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(49, 1, 'Inviter des membres', 'admin.members.invite', 'admin', 'create', 'community', 'tenant', '2026-04-05 09:03:38'),
(50, 1, 'Gérer les restrictions d’activité des membres (organisation)', 'admin.members.moderate', 'admin', 'moderate', 'community', 'tenant', '2026-04-05 09:03:38'),
(51, 1, 'Gérer les rôles', 'admin.roles.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(52, 1, 'Gérer les permissions', 'admin.permissions.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(53, 1, 'Voir les journaux d’audit', 'admin.audit.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(54, 1, 'Gérer les paramètres de la communauté', 'admin.settings.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(55, 1, 'Gérer l’identité visuelle / branding', 'admin.branding.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(56, 1, 'Gérer les intégrations / API / webhooks', 'admin.integrations.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(57, 1, 'Voir les sections privées', 'forum.private.view', 'forum', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(58, 1, 'Épingler un sujet', 'forum.topic.pin', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(59, 1, 'Verrouiller / déverrouiller un sujet', 'forum.topic.lock', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:03:38'),
(60, 1, 'Déplacer un sujet', 'forum.topic.move', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(61, 1, 'Éditer n’importe quel message', 'forum.post.edit_any', 'forum', 'update', 'community', 'tenant', '2026-04-05 09:03:38'),
(62, 1, 'Supprimer n’importe quel message', 'forum.post.delete_any', 'forum', 'delete', 'community', 'tenant', '2026-04-05 09:03:38'),
(63, 1, 'Gérer les signalements', 'forum.reports.manage', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:03:38'),
(64, 1, 'Gérer les tags / labels', 'forum.tags.manage', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(65, 1, 'Gérer les catégories forum', 'forum.categories.manage', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(66, 1, 'Publier des annonces globales', 'forum.announcements.publish', 'forum', 'approve', 'community', 'tenant', '2026-04-05 09:03:38'),
(67, 1, 'Voir les documents sensibles', 'documents.sensitive.view', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(68, 1, 'Télécharger les documents standards', 'documents.download.standard', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(69, 1, 'Remplacer une version', 'documents.version.replace', 'documents', 'update', 'community', 'tenant', '2026-04-05 09:03:38'),
(70, 1, 'Modifier les métadonnées', 'documents.metadata.update', 'documents', 'update', 'community', 'tenant', '2026-04-05 09:03:38'),
(71, 1, 'Supprimer un document', 'documents.delete', 'documents', 'delete', 'community', 'tenant', '2026-04-05 09:03:38'),
(72, 1, 'Gérer les catégories documentaires', 'documents.categories.manage', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(73, 1, 'Gérer les droits d’accès documentaires', 'documents.access.manage', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(74, 1, 'Partager en lien public', 'documents.share.public', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(75, 1, 'Valider / publier un document', 'documents.publish', 'documents', 'approve', 'community', 'tenant', '2026-04-05 09:03:38'),
(76, 1, 'Créer une formation', 'training.create', 'training', 'create', 'community', 'tenant', '2026-04-05 09:03:38'),
(77, 1, 'Modifier une formation', 'training.update', 'training', 'update', 'community', 'tenant', '2026-04-05 09:03:38'),
(78, 1, 'Supprimer une formation', 'training.delete', 'training', 'delete', 'community', 'tenant', '2026-04-05 09:03:38'),
(79, 1, 'Publier / dépublier une formation', 'training.publish', 'training', 'approve', 'community', 'tenant', '2026-04-05 09:03:38'),
(80, 1, 'Corriger / valider les rendus', 'training.submissions.grade', 'training', 'approve', 'community', 'tenant', '2026-04-05 09:03:38'),
(81, 1, 'Voir les résultats', 'training.results.view', 'training', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(82, 1, 'Exporter les résultats', 'training.results.export', 'training', 'export', 'community', 'tenant', '2026-04-05 09:03:38'),
(83, 1, 'Gérer les certifications', 'training.certifications.manage', 'training', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(84, 1, 'Gérer les prérequis', 'training.prerequisites.manage', 'training', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(85, 1, 'Voir les fiches membres', 'personnel.profile.view', 'personnel', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(86, 1, 'Modifier les fiches membres', 'personnel.profile.update', 'personnel', 'update', 'community', 'tenant', '2026-04-05 09:03:38'),
(87, 1, 'Voir les informations sensibles', 'personnel.sensitive.view', 'personnel', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(88, 1, 'Gérer les grades', 'personnel.grades.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(89, 1, 'Gérer affectations / unités', 'personnel.assignments.manage', 'personnel', 'assign', 'community', 'tenant', '2026-04-05 09:03:38'),
(90, 1, 'Gérer les statuts', 'personnel.status.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(91, 1, 'Gérer badges / qualifications', 'personnel.badges.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(92, 1, 'Exporter l’annuaire', 'personnel.directory.export', 'personnel', 'export', 'community', 'tenant', '2026-04-05 09:03:38'),
(93, 1, 'Envoyer une annonce', 'comms.announcement.send', 'comms', 'create', 'community', 'tenant', '2026-04-05 09:03:38'),
(94, 1, 'Diffusion e-mail large (tous types de messages aux membres)', 'comms.email.broadcast', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(95, 1, 'Gérer les modèles d’email', 'comms.email_templates.manage', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(96, 1, 'Voir l’historique des notifications', 'comms.notifications.history.view', 'comms', 'view', 'community', 'tenant', '2026-04-05 09:03:38'),
(97, 1, 'Gérer les alertes automatiques', 'comms.alerts.manage', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(98, 1, 'Paramétrage fin des communications', 'comms.settings.advanced', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:03:38'),
(99, 7, 'Accès administration (tenant)', 'admin.access', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:01'),
(100, 7, 'Voir le forum', 'forum.view', 'forum', 'view', 'intra', 'unit', '2026-04-05 09:10:01'),
(101, 7, 'Créer un sujet', 'forum.create_topic', 'forum', 'create', 'intra', 'unit', '2026-04-05 09:10:01'),
(102, 7, 'Répondre', 'forum.reply', 'forum', 'create', 'intra', 'unit', '2026-04-05 09:10:01'),
(103, 7, 'Modifier ses messages', 'forum.edit_own', 'forum', 'update', 'intra', 'unit', '2026-04-05 09:10:01'),
(104, 7, 'Supprimer ses messages', 'forum.delete_own', 'forum', 'delete', 'intra', 'unit', '2026-04-05 09:10:01'),
(105, 7, 'Modérer le forum (périmètre étendu)', 'forum.moderate', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:10:01'),
(106, 7, 'Modérer la section forum organisation', 'forum.moderate_organization', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:10:01'),
(107, 7, 'Gérer les catégories (identifiant historique)', 'forum.manage_categories', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:10:01'),
(108, 7, 'Voir les documents', 'documents.view', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(109, 7, 'Téléverser des documents', 'documents.upload', 'documents', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(110, 7, 'Modifier les documents (héritage)', 'documents.update', 'documents', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(111, 7, 'Archiver / désarchiver', 'documents.archive', 'documents', 'archive', 'community', 'tenant', '2026-04-05 09:10:02'),
(112, 7, 'Télécharger les documents sensibles', 'documents.download_sensitive', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(113, 7, 'Administration organisationnelle', 'admin.organization', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(114, 7, 'Voir les formations', 'training.view', 'training', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(115, 7, 'Gérer les formations (périmètre étendu)', 'training.manage', 'training', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(116, 7, 'Assigner les formations', 'training.assign', 'training', 'assign', 'community', 'tenant', '2026-04-05 09:10:02'),
(117, 7, 'Voir le back-office', 'admin.backoffice.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(118, 7, 'Voir les membres', 'admin.members.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(119, 7, 'Gérer les membres', 'admin.members.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(120, 7, 'Inviter des membres', 'admin.members.invite', 'admin', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(121, 7, 'Gérer les restrictions d’activité des membres (organisation)', 'admin.members.moderate', 'admin', 'moderate', 'community', 'tenant', '2026-04-05 09:10:02'),
(122, 7, 'Gérer les rôles', 'admin.roles.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(123, 7, 'Gérer les permissions', 'admin.permissions.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(124, 7, 'Voir les journaux d’audit', 'admin.audit.view', 'admin', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(125, 7, 'Gérer les paramètres de la communauté', 'admin.settings.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(126, 7, 'Gérer l’identité visuelle / branding', 'admin.branding.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(127, 7, 'Gérer les intégrations / API / webhooks', 'admin.integrations.manage', 'admin', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(128, 7, 'Envoyer des invitations', 'invitations.send', 'admin', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(129, 7, 'Voir les sections privées', 'forum.private.view', 'forum', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(130, 7, 'Épingler un sujet', 'forum.topic.pin', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(131, 7, 'Verrouiller / déverrouiller un sujet', 'forum.topic.lock', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:10:02'),
(132, 7, 'Déplacer un sujet', 'forum.topic.move', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(133, 7, 'Éditer n’importe quel message', 'forum.post.edit_any', 'forum', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(134, 7, 'Supprimer n’importe quel message', 'forum.post.delete_any', 'forum', 'delete', 'community', 'tenant', '2026-04-05 09:10:02'),
(135, 7, 'Gérer les signalements', 'forum.reports.manage', 'forum', 'moderate', 'community', 'tenant', '2026-04-05 09:10:02'),
(136, 7, 'Gérer les tags / labels', 'forum.tags.manage', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(137, 7, 'Gérer les catégories forum', 'forum.categories.manage', 'forum', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(138, 7, 'Publier des annonces globales', 'forum.announcements.publish', 'forum', 'approve', 'community', 'tenant', '2026-04-05 09:10:02'),
(139, 7, 'Voir les documents sensibles', 'documents.sensitive.view', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(140, 7, 'Télécharger les documents standards', 'documents.download.standard', 'documents', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(141, 7, 'Remplacer une version', 'documents.version.replace', 'documents', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(142, 7, 'Modifier les métadonnées', 'documents.metadata.update', 'documents', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(143, 7, 'Supprimer un document', 'documents.delete', 'documents', 'delete', 'community', 'tenant', '2026-04-05 09:10:02'),
(144, 7, 'Gérer les catégories documentaires', 'documents.categories.manage', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(145, 7, 'Gérer les droits d’accès documentaires', 'documents.access.manage', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(146, 7, 'Partager en lien public', 'documents.share.public', 'documents', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(147, 7, 'Valider / publier un document', 'documents.publish', 'documents', 'approve', 'community', 'tenant', '2026-04-05 09:10:02'),
(148, 7, 'Créer une formation', 'training.create', 'training', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(149, 7, 'Modifier une formation', 'training.update', 'training', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(150, 7, 'Supprimer une formation', 'training.delete', 'training', 'delete', 'community', 'tenant', '2026-04-05 09:10:02'),
(151, 7, 'Publier / dépublier une formation', 'training.publish', 'training', 'approve', 'community', 'tenant', '2026-04-05 09:10:02'),
(152, 7, 'Corriger / valider les rendus', 'training.submissions.grade', 'training', 'approve', 'community', 'tenant', '2026-04-05 09:10:02'),
(153, 7, 'Voir les résultats', 'training.results.view', 'training', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(154, 7, 'Exporter les résultats', 'training.results.export', 'training', 'export', 'community', 'tenant', '2026-04-05 09:10:02'),
(155, 7, 'Gérer les certifications', 'training.certifications.manage', 'training', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(156, 7, 'Gérer les prérequis', 'training.prerequisites.manage', 'training', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(157, 7, 'Voir les fiches membres', 'personnel.profile.view', 'personnel', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(158, 7, 'Modifier les fiches membres', 'personnel.profile.update', 'personnel', 'update', 'community', 'tenant', '2026-04-05 09:10:02'),
(159, 7, 'Voir les informations sensibles', 'personnel.sensitive.view', 'personnel', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(160, 7, 'Gérer les grades', 'personnel.grades.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(161, 7, 'Gérer affectations / unités', 'personnel.assignments.manage', 'personnel', 'assign', 'community', 'tenant', '2026-04-05 09:10:02'),
(162, 7, 'Gérer les statuts', 'personnel.status.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(163, 7, 'Gérer badges / qualifications', 'personnel.badges.manage', 'personnel', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(164, 7, 'Exporter l’annuaire', 'personnel.directory.export', 'personnel', 'export', 'community', 'tenant', '2026-04-05 09:10:02'),
(165, 7, 'Envoyer une annonce', 'comms.announcement.send', 'comms', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(166, 7, 'Diffusion e-mail large (tous types de messages aux membres)', 'comms.email.broadcast', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(167, 7, 'Gérer les modèles d’email', 'comms.email_templates.manage', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(168, 7, 'Voir l’historique des notifications', 'comms.notifications.history.view', 'comms', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(169, 7, 'Gérer les alertes automatiques', 'comms.alerts.manage', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(170, 7, 'Paramétrage fin des communications', 'comms.settings.advanced', 'comms', 'manage', 'community', 'tenant', '2026-04-05 09:10:02'),
(171, 7, 'Voir le Bureau Courrier', 'courrier.view', 'courrier', 'view', 'community', 'tenant', '2026-04-05 09:10:02'),
(172, 7, 'Créer des documents courrier', 'courrier.create', 'courrier', 'create', 'community', 'tenant', '2026-04-05 09:10:02'),
(173, 7, 'Valider des documents courrier', 'courrier.validate', 'courrier', 'approve', 'community', 'tenant', '2026-04-05 09:10:02'),
(174, 7, 'Archiver des documents courrier', 'courrier.archive', 'courrier', 'archive', 'community', 'tenant', '2026-04-05 09:10:02'),
(175, 1, 'Gérer les raccourcis du tableau de bord', 'dashboard.pins.manage', 'dashboard', 'manage', 'community', 'tenant', '2026-04-05 16:40:50'),
(176, 7, 'Gérer les raccourcis du tableau de bord', 'dashboard.pins.manage', 'dashboard', 'manage', 'community', 'tenant', '2026-04-05 16:40:50'),
(177, 1, 'Exporter les dossiers conformité (formations)', 'admin.compliance.export', 'admin', 'export', 'community', 'tenant', '2026-04-06 18:33:03'),
(178, 1, 'Piloter les missions inter-unités (invitations, partages)', 'interteam.missions.manage', 'interteam', 'manage', 'community', 'tenant', '2026-04-06 18:33:03'),
(179, 1, 'Accepter ou refuser une mission inter-unités', 'interteam.missions.respond', 'interteam', 'approve', 'community', 'tenant', '2026-04-06 18:33:03'),
(180, 7, 'Exporter les dossiers conformité (formations)', 'admin.compliance.export', 'admin', 'export', 'community', 'tenant', '2026-04-06 18:33:03'),
(181, 7, 'Piloter les missions inter-unités (invitations, partages)', 'interteam.missions.manage', 'interteam', 'manage', 'community', 'tenant', '2026-04-06 18:33:03'),
(182, 7, 'Accepter ou refuser une mission inter-unités', 'interteam.missions.respond', 'interteam', 'approve', 'community', 'tenant', '2026-04-06 18:33:03'),
(183, 1, 'Consulter l’ORBAT', 'organization.orbat.view', 'organization', 'view', 'community', 'tenant', '2026-04-06 19:22:01'),
(184, 1, 'Gérer la structure ORBAT (unités, rattachements)', 'organization.orbat.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(185, 1, 'Accéder au hub effectifs', 'organization.effectifs.hub.view', 'organization', 'view', 'community', 'tenant', '2026-04-06 19:22:01'),
(186, 1, 'Gérer le recrutement (dossiers, décisions)', 'organization.recruitment.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(187, 1, 'Gérer le référentiel des emplois métier', 'organization.job_roles.referential.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(188, 7, 'Consulter l’ORBAT', 'organization.orbat.view', 'organization', 'view', 'community', 'tenant', '2026-04-06 19:22:01'),
(189, 7, 'Gérer la structure ORBAT (unités, rattachements)', 'organization.orbat.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(190, 7, 'Accéder au hub effectifs', 'organization.effectifs.hub.view', 'organization', 'view', 'community', 'tenant', '2026-04-06 19:22:01'),
(191, 7, 'Gérer le recrutement (dossiers, décisions)', 'organization.recruitment.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(192, 7, 'Gérer le référentiel des emplois métier', 'organization.job_roles.referential.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-06 19:22:01'),
(193, NULL, 'Modération forum (toutes communautés)', 'forum.moderate', 'forum', NULL, 'site', 'global', '2026-04-06 20:07:27'),
(194, NULL, 'Canaux forum (toutes communautés)', 'forum.categories.manage', 'forum', NULL, 'site', 'global', '2026-04-06 20:07:27'),
(195, NULL, 'Assistance membres (accès guidé)', 'site.support', 'admin', NULL, 'site', 'global', '2026-04-06 20:07:27'),
(196, 1, 'Voir les coopérations inter-unités', 'cooperation.missions.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(197, 1, 'Proposer une coopération inter-unités', 'cooperation.missions.create', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:20'),
(198, 1, 'Piloter une coopération (invitations, autorisations, liaisons)', 'cooperation.missions.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 06:55:20'),
(199, 1, 'Répondre à une proposition de coopération', 'cooperation.missions.respond', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:20'),
(200, 1, 'Lancer une coopération validée', 'cooperation.missions.activate', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:20'),
(201, 1, 'Clôturer une coopération', 'cooperation.missions.close', 'cooperation', 'archive', 'community', 'tenant', '2026-04-09 06:55:20'),
(202, 1, 'Archiver une coopération clôturée', 'cooperation.missions.archive', 'cooperation', 'archive', 'community', 'tenant', '2026-04-09 06:55:20'),
(203, 1, 'Consulter l’espace commun de coopération', 'cooperation.exchange.read', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(204, 1, 'Publier dans l’espace commun de coopération', 'cooperation.exchange.write', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:20'),
(205, 1, 'Modérer l’espace commun de coopération', 'cooperation.exchange.moderate', 'cooperation', 'moderate', 'community', 'tenant', '2026-04-09 06:55:20'),
(206, 1, 'Organiser ou ouvrir une réunion de coopération', 'cooperation.meeting.launch', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 06:55:20'),
(207, 1, 'Demander un partage de données dans une coopération', 'cooperation.data.request', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:20'),
(208, 1, 'Approuver un partage de données (autorisation de partage)', 'cooperation.data.approve', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:20'),
(209, 1, 'Révoquer un partage de données', 'cooperation.data.revoke', 'cooperation', 'delete', 'community', 'tenant', '2026-04-09 06:55:20'),
(210, 1, 'Voir les structures et liaisons de coopération', 'cooperation.orbat.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(211, 1, 'Voir la préparation opérationnelle liée à une coopération', 'cooperation.readiness.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(212, 1, 'Voir le journal d’audit d’une coopération', 'cooperation.audit.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(213, 1, 'Rédiger un retour d’expérience de coopération', 'cooperation.rex.submit', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:20'),
(214, 1, 'Lire les retours d’expérience consolidés', 'cooperation.rex.read', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:20'),
(215, 7, 'Voir les coopérations inter-unités', 'cooperation.missions.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(216, 7, 'Proposer une coopération inter-unités', 'cooperation.missions.create', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:21'),
(217, 7, 'Piloter une coopération (invitations, autorisations, liaisons)', 'cooperation.missions.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 06:55:21'),
(218, 7, 'Répondre à une proposition de coopération', 'cooperation.missions.respond', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:21'),
(219, 7, 'Lancer une coopération validée', 'cooperation.missions.activate', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:21'),
(220, 7, 'Clôturer une coopération', 'cooperation.missions.close', 'cooperation', 'archive', 'community', 'tenant', '2026-04-09 06:55:21'),
(221, 7, 'Archiver une coopération clôturée', 'cooperation.missions.archive', 'cooperation', 'archive', 'community', 'tenant', '2026-04-09 06:55:21'),
(222, 7, 'Consulter l’espace commun de coopération', 'cooperation.exchange.read', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(223, 7, 'Publier dans l’espace commun de coopération', 'cooperation.exchange.write', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:21'),
(224, 7, 'Modérer l’espace commun de coopération', 'cooperation.exchange.moderate', 'cooperation', 'moderate', 'community', 'tenant', '2026-04-09 06:55:21'),
(225, 7, 'Organiser ou ouvrir une réunion de coopération', 'cooperation.meeting.launch', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 06:55:21'),
(226, 7, 'Demander un partage de données dans une coopération', 'cooperation.data.request', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:21'),
(227, 7, 'Approuver un partage de données (autorisation de partage)', 'cooperation.data.approve', 'cooperation', 'approve', 'community', 'tenant', '2026-04-09 06:55:21'),
(228, 7, 'Révoquer un partage de données', 'cooperation.data.revoke', 'cooperation', 'delete', 'community', 'tenant', '2026-04-09 06:55:21'),
(229, 7, 'Voir les structures et liaisons de coopération', 'cooperation.orbat.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(230, 7, 'Voir la préparation opérationnelle liée à une coopération', 'cooperation.readiness.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(231, 7, 'Voir le journal d’audit d’une coopération', 'cooperation.audit.view', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(232, 7, 'Rédiger un retour d’expérience de coopération', 'cooperation.rex.submit', 'cooperation', 'create', 'community', 'tenant', '2026-04-09 06:55:21'),
(233, 7, 'Lire les retours d’expérience consolidés', 'cooperation.rex.read', 'cooperation', 'view', 'community', 'tenant', '2026-04-09 06:55:21'),
(234, 1, 'Gérer les offres publiées et le format des références', 'organization.recruitment.openings.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-09 10:54:23'),
(235, 7, 'Gérer les offres publiées et le format des références', 'organization.recruitment.openings.manage', 'organization', 'manage', 'community', 'tenant', '2026-04-09 10:54:23'),
(236, 1, 'Recevoir les messages internes adressés à l’encadrement', 'comms.tenant_messages.receive', 'comms', 'view', 'community', 'tenant', '2026-04-09 10:56:46'),
(237, 7, 'Recevoir les messages internes adressés à l’encadrement', 'comms.tenant_messages.receive', 'comms', 'view', 'community', 'tenant', '2026-04-09 10:56:46'),
(238, 1, 'Gérer le catalogue des types de coopération (communauté)', 'cooperation.catalog.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 10:56:46'),
(239, 1, 'Gérer les messages types d’annonces coopération (communauté)', 'cooperation.announcements.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 10:56:46'),
(240, 7, 'Gérer le catalogue des types de coopération (communauté)', 'cooperation.catalog.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 10:56:46'),
(241, 7, 'Gérer les messages types d’annonces coopération (communauté)', 'cooperation.announcements.manage', 'cooperation', 'manage', 'community', 'tenant', '2026-04-09 10:56:46'),
(242, 1, 'Envoyer un e-mail lié à la structure (ORBAT)', 'comms.email.send.orbat', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(243, 1, 'Envoyer un e-mail lié au pilotage opérationnel', 'comms.email.send.mission', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(244, 1, 'Envoyer un e-mail lié aux activités', 'comms.email.send.activity', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(245, 1, 'Envoyer un e-mail libre aux membres', 'comms.email.send.custom', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(246, 7, 'Envoyer un e-mail lié à la structure (ORBAT)', 'comms.email.send.orbat', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(247, 7, 'Envoyer un e-mail lié au pilotage opérationnel', 'comms.email.send.mission', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(248, 7, 'Envoyer un e-mail lié aux activités', 'comms.email.send.activity', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40'),
(249, 7, 'Envoyer un e-mail libre aux membres', 'comms.email.send.custom', 'comms', 'create', 'community', 'tenant', '2026-04-13 10:55:40');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_admin_data`
--

CREATE TABLE `personnel_admin_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `panel_id` int(10) UNSIGNED NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnel_admin_panels`
--

CREATE TABLE `personnel_admin_panels` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `personnel_admin_panels`
--

INSERT INTO `personnel_admin_panels` (`id`, `tenant_id`, `name`, `slug`, `description`, `display_order`, `created_at`) VALUES
(1, 1, 'État civil', 'etat-civil', 'Identité et état civil', 10, '2026-03-13 19:23:12'),
(2, 1, 'Affectation', 'affectation', 'Unité, poste, affectation', 20, '2026-03-13 19:23:12'),
(3, 1, 'Formation', 'formation', 'Parcours et qualifications', 30, '2026-03-13 19:23:12'),
(4, 1, 'Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40, '2026-03-13 19:23:12'),
(5, 1, 'Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50, '2026-03-13 19:23:12'),
(6, 1, 'Références / Notes', 'references-notes', 'Références et notes administratives', 60, '2026-03-13 19:23:12'),
(13, 7, 'État civil', 'etat-civil', 'Identité et état civil', 10, '2026-04-05 09:10:02'),
(14, 7, 'Affectation', 'affectation', 'Unité, poste, affectation', 20, '2026-04-05 09:10:02'),
(15, 7, 'Formation', 'formation', 'Parcours et qualifications', 30, '2026-04-05 09:10:02'),
(16, 7, 'Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40, '2026-04-05 09:10:02'),
(17, 7, 'Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50, '2026-04-05 09:10:02'),
(18, 7, 'Références / Notes', 'references-notes', 'Références et notes administratives', 60, '2026-04-05 09:10:02');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_assignments`
--

CREATE TABLE `personnel_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `personnel_assignments`
--

INSERT INTO `personnel_assignments` (`id`, `user_id`, `unit_id`, `role_name`, `is_primary`, `started_at`, `ended_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 5, 2, 'Officier opérations', 0, '2026-04-05', '2026-04-05', 'inactive', '2026-04-05 10:53:05', '2026-04-05 11:45:48'),
(2, 5, 2, 'Officier opérations', 0, '2026-04-05', '2026-04-05', 'inactive', '2026-04-05 11:45:48', '2026-04-05 11:59:27'),
(3, 5, 2, 'Officier opérations', 0, '2026-04-05', '2026-04-05', 'inactive', '2026-04-05 11:59:27', '2026-04-05 11:59:38'),
(4, 5, 2, 'Officier opérations', 0, '2026-04-05', '2026-04-05', 'inactive', '2026-04-05 11:59:38', '2026-04-05 12:09:47'),
(5, 5, 2, 'Officier opérations', 0, '2026-04-05', '2026-04-06', 'inactive', '2026-04-05 12:09:47', '2026-04-06 18:59:04'),
(6, 8, 4, 'Instructeur — Spécialiste communication', 0, '2026-04-06', '2026-04-06', 'inactive', '2026-04-06 17:46:32', '2026-04-06 20:49:50'),
(7, 5, 2, 'Officier opérations — Officier gestionnaire administratif', 0, '2026-04-06', '2026-04-06', 'inactive', '2026-04-06 18:59:04', '2026-04-06 18:59:13'),
(8, 5, 2, 'Officier opérations — Spécialiste gestionnaire administratif', 0, '2026-04-06', '2026-04-06', 'inactive', '2026-04-06 18:59:13', '2026-04-06 20:51:45'),
(9, 8, 4, 'Instructeur — Spécialiste communication · JTAC · Gestionnaire RH', 0, '2026-04-06', '2026-04-06', 'inactive', '2026-04-06 20:49:50', '2026-04-06 21:28:45'),
(10, 5, 2, 'Officier opérations — Spécialiste gestionnaire administratif · Formateur · En service actif', 0, '2026-04-06', '2026-04-06', 'inactive', '2026-04-06 20:51:45', '2026-04-06 20:53:13'),
(11, 5, 2, 'Officier opérations — Spécialiste gestionnaire administratif', 0, '2026-04-06', '2026-04-09', 'inactive', '2026-04-06 20:53:13', '2026-04-09 17:09:05'),
(12, 8, 3, 'Recrue', 1, '2026-04-06', NULL, 'active', '2026-04-06 21:28:45', NULL),
(13, 5, 2, 'Officier opérations — Spécialiste gestionnaire administratif', 1, '2026-04-09', NULL, 'active', '2026-04-09 17:09:05', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `personnel_extras`
--

CREATE TABLE `personnel_extras` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `service_number` varchar(50) DEFAULT NULL,
  `squadron` varchar(100) DEFAULT NULL,
  `date_of_enlistment` date DEFAULT NULL,
  `clearance_level` varchar(100) DEFAULT NULL,
  `clearance_level_id` int(10) UNSIGNED DEFAULT NULL,
  `flight_hours` decimal(10,1) DEFAULT NULL,
  `specializations` text DEFAULT NULL,
  `readiness_percent` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `personnel_extras`
--

INSERT INTO `personnel_extras` (`user_id`, `service_number`, `squadron`, `date_of_enlistment`, `clearance_level`, `clearance_level_id`, `flight_hours`, `specializations`, `readiness_percent`, `admin_notes`, `created_at`, `updated_at`) VALUES
(5, 'ATH-00001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-05 09:16:46', '2026-04-09 17:09:05'),
(8, 'ATH-00002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-06 17:46:32', '2026-04-06 17:46:35');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_job_roles`
--

CREATE TABLE `personnel_job_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `label_en` varchar(160) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personnel_job_roles`
--

INSERT INTO `personnel_job_roles` (`id`, `tenant_id`, `category_id`, `name`, `slug`, `description`, `label_en`, `sort_order`, `is_system`, `created_at`) VALUES
(1, 1, 5, 'Officier opérations', 'officier-operations', 'Coordination des opérations et briefs.', NULL, 0, 1, '2026-04-05 11:27:53'),
(2, 1, 5, 'Chef de section', 'chef-de-section', 'Encadrement de section.', NULL, 1, 1, '2026-04-05 11:27:53'),
(3, 1, 6, 'Fusilier', 'fusilier', 'Combattant polyvalent.', NULL, 2, 1, '2026-04-05 11:27:53'),
(4, 1, 6, 'Grenadier', 'grenadier', 'Appui grenades / lourd léger.', NULL, 3, 1, '2026-04-05 11:27:53'),
(5, 1, 7, 'JTAC / FO', 'jtac', 'Guidage feu indirect.', NULL, 4, 1, '2026-04-05 11:27:53'),
(6, 1, 7, 'Medic / secouriste', 'medic', 'Soutien sanitaire.', NULL, 5, 1, '2026-04-05 11:27:53'),
(7, 1, 8, 'Logistique', 'logistique-r', 'Ravitaillement, transport.', NULL, 6, 1, '2026-04-05 11:27:53'),
(8, 1, 9, 'Formateur', 'formateur', 'Pédagogie, montée en compétence.', NULL, 7, 1, '2026-04-05 11:27:53'),
(9, 1, 9, 'Instructeur', 'instructeur', 'Instruction collective.', NULL, 8, 1, '2026-04-05 11:27:53'),
(10, 7, 14, 'Officier opérations', 'officier-operations', 'Coordination des opérations et briefs.', NULL, 0, 1, '2026-04-05 11:27:53'),
(11, 7, 14, 'Chef de section', 'chef-de-section', 'Encadrement de section.', NULL, 1, 1, '2026-04-05 11:27:53'),
(12, 7, 15, 'Fusilier', 'fusilier', 'Combattant polyvalent.', NULL, 2, 1, '2026-04-05 11:27:53'),
(13, 7, 15, 'Grenadier', 'grenadier', 'Appui grenades / lourd léger.', NULL, 3, 1, '2026-04-05 11:27:53'),
(14, 7, 16, 'JTAC / FO', 'jtac', 'Guidage feu indirect.', NULL, 4, 1, '2026-04-05 11:27:53'),
(15, 7, 16, 'Medic / secouriste', 'medic', 'Soutien sanitaire.', NULL, 5, 1, '2026-04-05 11:27:53'),
(16, 7, 17, 'Logistique', 'logistique-r', 'Ravitaillement, transport.', NULL, 6, 1, '2026-04-05 11:27:53'),
(17, 7, 18, 'Formateur', 'formateur', 'Pédagogie, montée en compétence.', NULL, 7, 1, '2026-04-05 11:27:53'),
(18, 7, 18, 'Instructeur', 'instructeur', 'Instruction collective.', NULL, 8, 1, '2026-04-05 11:27:53'),
(19, 1, 20, 'Chef de corps', 'command_unit_commander', 'Autorité de commandement de l’unité.', 'Commanding Officer', 10, 1, '2026-04-06 18:58:10'),
(20, 1, 20, 'Chef adjoint', 'command_executive_officer', 'Adjoint au commandement et relais opérationnel.', 'Executive Officer', 20, 1, '2026-04-06 18:58:10'),
(21, 1, 20, 'Officier supérieur', 'command_senior_officer', 'Encadrement supérieur et coordination générale.', 'Senior Officer', 30, 1, '2026-04-06 18:58:10'),
(22, 1, 20, 'Officier de permanence', 'command_duty_officer', 'Responsable de la permanence et des décisions courantes.', 'Duty Officer', 40, 1, '2026-04-06 18:58:10'),
(23, 1, 21, 'Officier opérations', 'operations_officer', 'Coordination des opérations et activités.', 'Operations Officer (S3)', 50, 1, '2026-04-06 18:58:10'),
(24, 1, 21, 'Officier planification', 'staff_plans_officer', 'Plans, ordres et synchronisation des moyens.', 'Plans Officer', 60, 1, '2026-04-06 18:58:10'),
(25, 1, 21, 'Officier conduite', 'staff_battle_captain', 'Conduite de la manœuvre et de la situation tactique.', 'Battle Captain', 70, 1, '2026-04-06 18:58:10'),
(26, 1, 21, 'Officier coordination interarmes', 'staff_joint_coordination_officer', 'Coordination des effets interarmes et appuis.', 'Joint Fires Coordinator', 80, 1, '2026-04-06 18:58:10'),
(27, 1, 22, 'Officier renseignement', 'intelligence_officer', 'Collecte, analyse et diffusion du renseignement.', 'Intelligence Officer (S2)', 90, 1, '2026-04-06 18:58:10'),
(28, 1, 22, 'Analyste renseignement', 'staff_intel_analyst', 'Production d’analyses et de fiches situation.', 'Intelligence Analyst', 100, 1, '2026-04-06 18:58:10'),
(29, 1, 22, 'Officier exploitation', 'staff_intel_exploitation', 'Exploitation technique des sources et des flux.', 'SIGINT Specialist', 110, 1, '2026-04-06 18:58:10'),
(30, 1, 22, 'Cellule renseignement', 'staff_intel_cell', 'Traitement et diffusion au sein de la cellule.', 'Intelligence Cell Operator', 120, 1, '2026-04-06 18:58:10'),
(31, 1, 23, 'Officier logistique', 'logistics_officer', 'Pilotage du soutien et de la chaîne logistique.', 'Logistics Officer (S4)', 130, 1, '2026-04-06 18:58:10'),
(32, 1, 23, 'Responsable soutien', 'staff_sustainment_lead', 'Gestion des stocks et du soutien quotidien.', 'Supply Specialist', 140, 1, '2026-04-06 18:58:10'),
(33, 1, 23, 'Gestionnaire flux logistiques', 'staff_logistics_flow_manager', 'Organisation des flux, convois et dotations.', 'Motor Transport Operator', 150, 1, '2026-04-06 18:58:10'),
(34, 1, 25, 'Chef de section', 'infantry_section_chief', 'Encadrement d’une section au combat.', 'Platoon Leader', 10, 1, '2026-04-06 18:58:10'),
(35, 1, 25, 'Chef de groupe', 'infantry_group_chief', 'Encadrement d’un groupe tactique.', 'Squad Leader', 20, 1, '2026-04-06 18:58:10'),
(36, 1, 25, 'Chef d’équipe', 'infantry_team_chief', 'Encadrement d’une équipe élémentaire.', 'Team Leader', 30, 1, '2026-04-06 18:58:10'),
(37, 1, 26, 'Fusilier', 'infantry_rifleman', 'Combattant d’infanterie polyvalent.', 'Rifleman', 40, 1, '2026-04-06 18:58:10'),
(38, 1, 26, 'Grenadier', 'infantry_grenadier', 'Appui grenades et armement lourd léger.', 'Grenadier', 50, 1, '2026-04-06 18:58:10'),
(39, 1, 26, 'Tireur d’élite', 'infantry_sharpshooter', 'Précision renforcée et tir d’appui.', 'Sharpshooter', 60, 1, '2026-04-06 18:58:10'),
(40, 1, 26, 'Tireur de précision', 'infantry_marksman', 'Neutralisation sélective à moyenne portée.', 'Designated Marksman', 70, 1, '2026-04-06 18:58:10'),
(41, 1, 26, 'Tireur isolé', 'infantry_sniper', 'Tir de précision longue portée en retrait.', 'Sniper', 75, 1, '2026-04-06 18:58:10'),
(42, 1, 26, 'Mitrailleur', 'infantry_machine_gunner', 'Appui feu soutenu et manœuvre d’appui.', 'Automatic Rifleman', 80, 1, '2026-04-06 18:58:10'),
(43, 1, 27, 'Opérateur radio', 'infantry_radio_operator', 'Transmissions et liaisons tactiques.', 'Radio Operator', 90, 1, '2026-04-06 18:58:10'),
(44, 1, 27, 'Éclaireur', 'infantry_scout', 'Reconnaissance et renseignement terrain.', 'Scout', 100, 1, '2026-04-06 18:58:10'),
(45, 1, 27, 'Chef binôme', 'infantry_team_pair_chief', 'Coordination d’un binôme au contact.', 'Buddy team leader', 110, 1, '2026-04-06 18:58:10'),
(46, 1, 29, 'JTAC', 'fires_jtac', 'Contrôleur d’attaques au sol.', 'JTAC', 10, 1, '2026-04-06 18:58:10'),
(47, 1, 29, 'Forward Observer', 'fires_forward_observer', 'Observation et ajustement des tirs.', 'Forward Observer', 20, 1, '2026-04-06 18:58:10'),
(48, 1, 29, 'Officier appuis feux', 'fires_support_officer', 'Synthèse et coordination des appuis.', 'Fire Support Officer', 30, 1, '2026-04-06 18:58:10'),
(49, 1, 30, 'Chef pièce', 'fires_gun_chief', 'Chef de pièce et conduite du tir.', 'Fire Direction Specialist', 40, 1, '2026-04-06 18:58:10'),
(50, 1, 30, 'Servant artillerie', 'fires_gun_crew', 'Mise en œuvre et service de pièce.', 'Artillery Crew', 50, 1, '2026-04-06 18:58:10'),
(51, 1, 32, 'Sapeur', 'engineer_sapper', 'Ouverture de passages et travaux au contact.', 'Combat Engineer', 10, 1, '2026-04-06 18:58:10'),
(52, 1, 32, 'Démineur', 'engineer_eod', 'Neutralisation des dangers explosifs.', 'EOD Specialist', 20, 1, '2026-04-06 18:58:10'),
(53, 1, 32, 'Chef groupe génie', 'engineer_group_chief', 'Encadrement d’un groupe de combat du génie.', 'Engineer Squad Leader', 30, 1, '2026-04-06 18:58:10'),
(54, 1, 33, 'Technicien infrastructure', 'engineer_infra_technician', 'Travaux d’infrastructure et ouvrages.', 'Construction Engineer', 40, 1, '2026-04-06 18:58:10'),
(55, 1, 33, 'Responsable travaux', 'engineer_works_lead', 'Pilotage des chantiers et contrôle qualité.', 'Works Supervisor', 50, 1, '2026-04-06 18:58:10'),
(56, 1, 35, 'Conducteur militaire', 'logistics_driver', 'Conduite et manœuvre des véhicules logistiques.', 'Motor Transport Operator', 10, 1, '2026-04-06 18:58:10'),
(57, 1, 35, 'Chef convoi', 'logistics_convoy_chief', 'Responsabilité d’un convoi ou d’un détachement roulant.', 'Convoy Commander', 20, 1, '2026-04-06 18:58:10'),
(58, 1, 36, 'Mécanicien', 'logistics_mechanic', 'Maintenance de premier et second échelon.', 'Mechanic', 30, 1, '2026-04-06 18:58:10'),
(59, 1, 36, 'Technicien maintenance', 'logistics_maint_technician', 'Diagnostic et réparation des systèmes.', 'Maintenance Technician', 40, 1, '2026-04-06 18:58:10'),
(60, 1, 36, 'Responsable parc matériel', 'logistics_fleet_manager', 'Gestion du parc et disponibilité opérationnelle.', 'Fleet Manager', 50, 1, '2026-04-06 18:58:10'),
(61, 1, 38, 'Médecin militaire', 'medical_officer', 'Responsabilité médicale et décisions sanitaires.', 'Medical Officer', 10, 1, '2026-04-06 18:58:10'),
(62, 1, 38, 'Infirmier militaire', 'medical_nurse', 'Soins infirmiers et stabilisation.', 'Field Nurse', 20, 1, '2026-04-06 18:58:10'),
(63, 1, 38, 'Auxiliaire sanitaire', 'medical_auxiliary', 'Soutien sanitaire et assistance au poste de secours.', 'Medical Assistant', 30, 1, '2026-04-06 18:58:10'),
(64, 1, 38, 'Secouriste', 'medical_first_responder', 'Premiers secours et évacuation sanitaire initiale.', 'Combat Medic', 40, 1, '2026-04-06 18:58:10'),
(65, 1, 40, 'Instructeur', 'instructor', 'Instruction collective et maintien des standards.', 'Drill Instructor', 10, 1, '2026-04-06 18:58:10'),
(66, 1, 40, 'Formateur', 'instruction_trainer', 'Conception et animation de modules pédagogiques.', 'Training Instructor', 20, 1, '2026-04-06 18:58:10'),
(67, 1, 40, 'Responsable formation', 'training_officer', 'Pilotage des parcours et des qualifications.', 'Training Lead', 30, 1, '2026-04-06 18:58:10'),
(68, 1, 40, 'Évaluateur', 'instruction_evaluator', 'Évaluation des compétences et des qualifications.', 'Evaluator', 40, 1, '2026-04-06 18:58:10'),
(69, 1, 42, 'Gestionnaire RH', 'hr', 'Gestion des effectifs et du dossier personnel.', 'Human Resources Specialist', 10, 1, '2026-04-06 18:58:10'),
(70, 1, 42, 'Officier administratif', 'admin_staff_officer', 'Courrier, dossiers et formalités administratives.', 'Administrative Officer', 20, 1, '2026-04-06 18:58:10'),
(71, 1, 42, 'Secrétaire unité', 'admin_unit_secretary', 'Secrétariat et suivi administratif de l’unité.', 'Unit Secretary', 30, 1, '2026-04-06 18:58:10'),
(72, 1, 44, 'Vétéran', 'veteran', 'Ancien combattant ou membre d’honneur actif en visibilité.', 'Veteran', 10, 1, '2026-04-06 18:58:10'),
(73, 1, 44, 'En formation', 'status_in_training', 'Parcours de formation en cours.', 'In training', 20, 1, '2026-04-06 18:58:10'),
(74, 1, 44, 'En probation', 'probation_member', 'Intégration sous période probatoire.', 'Probationary Member', 30, 1, '2026-04-06 18:58:10'),
(75, 1, 44, 'Suspendu', 'suspended_status', 'Participation suspendue — visibilité limitée.', 'Suspended', 40, 1, '2026-04-06 18:58:10'),
(76, 1, 44, 'Réserviste', 'status_reservist', 'Statut de réserve et disponibilité partielle.', 'Reservist', 50, 1, '2026-04-06 18:58:10'),
(77, 1, 44, 'Instructeur certifié', 'certified_instructor', 'Qualification pédagogique reconnue.', 'Instructor Certified', 60, 1, '2026-04-06 18:58:10'),
(78, 1, 44, 'En service actif', 'status_active_duty', 'Engagement opérationnel à plein temps.', 'Active Duty', 70, 1, '2026-04-06 18:58:10'),
(79, 7, 46, 'Chef de corps', 'command_unit_commander', 'Autorité de commandement de l’unité.', 'Commanding Officer', 10, 1, '2026-04-06 18:58:10'),
(80, 7, 46, 'Chef adjoint', 'command_executive_officer', 'Adjoint au commandement et relais opérationnel.', 'Executive Officer', 20, 1, '2026-04-06 18:58:10'),
(81, 7, 46, 'Officier supérieur', 'command_senior_officer', 'Encadrement supérieur et coordination générale.', 'Senior Officer', 30, 1, '2026-04-06 18:58:10'),
(82, 7, 46, 'Officier de permanence', 'command_duty_officer', 'Responsable de la permanence et des décisions courantes.', 'Duty Officer', 40, 1, '2026-04-06 18:58:10'),
(83, 7, 47, 'Officier opérations', 'operations_officer', 'Coordination des opérations et activités.', 'Operations Officer (S3)', 50, 1, '2026-04-06 18:58:10'),
(84, 7, 47, 'Officier planification', 'staff_plans_officer', 'Plans, ordres et synchronisation des moyens.', 'Plans Officer', 60, 1, '2026-04-06 18:58:10'),
(85, 7, 47, 'Officier conduite', 'staff_battle_captain', 'Conduite de la manœuvre et de la situation tactique.', 'Battle Captain', 70, 1, '2026-04-06 18:58:10'),
(86, 7, 47, 'Officier coordination interarmes', 'staff_joint_coordination_officer', 'Coordination des effets interarmes et appuis.', 'Joint Fires Coordinator', 80, 1, '2026-04-06 18:58:10'),
(87, 7, 48, 'Officier renseignement', 'intelligence_officer', 'Collecte, analyse et diffusion du renseignement.', 'Intelligence Officer (S2)', 90, 1, '2026-04-06 18:58:10'),
(88, 7, 48, 'Analyste renseignement', 'staff_intel_analyst', 'Production d’analyses et de fiches situation.', 'Intelligence Analyst', 100, 1, '2026-04-06 18:58:10'),
(89, 7, 48, 'Officier exploitation', 'staff_intel_exploitation', 'Exploitation technique des sources et des flux.', 'SIGINT Specialist', 110, 1, '2026-04-06 18:58:10'),
(90, 7, 48, 'Cellule renseignement', 'staff_intel_cell', 'Traitement et diffusion au sein de la cellule.', 'Intelligence Cell Operator', 120, 1, '2026-04-06 18:58:10'),
(91, 7, 49, 'Officier logistique', 'logistics_officer', 'Pilotage du soutien et de la chaîne logistique.', 'Logistics Officer (S4)', 130, 1, '2026-04-06 18:58:10'),
(92, 7, 49, 'Responsable soutien', 'staff_sustainment_lead', 'Gestion des stocks et du soutien quotidien.', 'Supply Specialist', 140, 1, '2026-04-06 18:58:10'),
(93, 7, 49, 'Gestionnaire flux logistiques', 'staff_logistics_flow_manager', 'Organisation des flux, convois et dotations.', 'Motor Transport Operator', 150, 1, '2026-04-06 18:58:10'),
(94, 7, 51, 'Chef de section', 'infantry_section_chief', 'Encadrement d’une section au combat.', 'Platoon Leader', 10, 1, '2026-04-06 18:58:10'),
(95, 7, 51, 'Chef de groupe', 'infantry_group_chief', 'Encadrement d’un groupe tactique.', 'Squad Leader', 20, 1, '2026-04-06 18:58:10'),
(96, 7, 51, 'Chef d’équipe', 'infantry_team_chief', 'Encadrement d’une équipe élémentaire.', 'Team Leader', 30, 1, '2026-04-06 18:58:10'),
(97, 7, 52, 'Fusilier', 'infantry_rifleman', 'Combattant d’infanterie polyvalent.', 'Rifleman', 40, 1, '2026-04-06 18:58:10'),
(98, 7, 52, 'Grenadier', 'infantry_grenadier', 'Appui grenades et armement lourd léger.', 'Grenadier', 50, 1, '2026-04-06 18:58:10'),
(99, 7, 52, 'Tireur d’élite', 'infantry_sharpshooter', 'Précision renforcée et tir d’appui.', 'Sharpshooter', 60, 1, '2026-04-06 18:58:10'),
(100, 7, 52, 'Tireur de précision', 'infantry_marksman', 'Neutralisation sélective à moyenne portée.', 'Designated Marksman', 70, 1, '2026-04-06 18:58:10'),
(101, 7, 52, 'Tireur isolé', 'infantry_sniper', 'Tir de précision longue portée en retrait.', 'Sniper', 75, 1, '2026-04-06 18:58:10'),
(102, 7, 52, 'Mitrailleur', 'infantry_machine_gunner', 'Appui feu soutenu et manœuvre d’appui.', 'Automatic Rifleman', 80, 1, '2026-04-06 18:58:10'),
(103, 7, 53, 'Opérateur radio', 'infantry_radio_operator', 'Transmissions et liaisons tactiques.', 'Radio Operator', 90, 1, '2026-04-06 18:58:10'),
(104, 7, 53, 'Éclaireur', 'infantry_scout', 'Reconnaissance et renseignement terrain.', 'Scout', 100, 1, '2026-04-06 18:58:10'),
(105, 7, 53, 'Chef binôme', 'infantry_team_pair_chief', 'Coordination d’un binôme au contact.', 'Buddy team leader', 110, 1, '2026-04-06 18:58:10'),
(106, 7, 55, 'JTAC', 'fires_jtac', 'Contrôleur d’attaques au sol.', 'JTAC', 10, 1, '2026-04-06 18:58:10'),
(107, 7, 55, 'Forward Observer', 'fires_forward_observer', 'Observation et ajustement des tirs.', 'Forward Observer', 20, 1, '2026-04-06 18:58:10'),
(108, 7, 55, 'Officier appuis feux', 'fires_support_officer', 'Synthèse et coordination des appuis.', 'Fire Support Officer', 30, 1, '2026-04-06 18:58:10'),
(109, 7, 56, 'Chef pièce', 'fires_gun_chief', 'Chef de pièce et conduite du tir.', 'Fire Direction Specialist', 40, 1, '2026-04-06 18:58:10'),
(110, 7, 56, 'Servant artillerie', 'fires_gun_crew', 'Mise en œuvre et service de pièce.', 'Artillery Crew', 50, 1, '2026-04-06 18:58:10'),
(111, 7, 58, 'Sapeur', 'engineer_sapper', 'Ouverture de passages et travaux au contact.', 'Combat Engineer', 10, 1, '2026-04-06 18:58:10'),
(112, 7, 58, 'Démineur', 'engineer_eod', 'Neutralisation des dangers explosifs.', 'EOD Specialist', 20, 1, '2026-04-06 18:58:10'),
(113, 7, 58, 'Chef groupe génie', 'engineer_group_chief', 'Encadrement d’un groupe de combat du génie.', 'Engineer Squad Leader', 30, 1, '2026-04-06 18:58:10'),
(114, 7, 59, 'Technicien infrastructure', 'engineer_infra_technician', 'Travaux d’infrastructure et ouvrages.', 'Construction Engineer', 40, 1, '2026-04-06 18:58:10'),
(115, 7, 59, 'Responsable travaux', 'engineer_works_lead', 'Pilotage des chantiers et contrôle qualité.', 'Works Supervisor', 50, 1, '2026-04-06 18:58:10'),
(116, 7, 61, 'Conducteur militaire', 'logistics_driver', 'Conduite et manœuvre des véhicules logistiques.', 'Motor Transport Operator', 10, 1, '2026-04-06 18:58:10'),
(117, 7, 61, 'Chef convoi', 'logistics_convoy_chief', 'Responsabilité d’un convoi ou d’un détachement roulant.', 'Convoy Commander', 20, 1, '2026-04-06 18:58:10'),
(118, 7, 62, 'Mécanicien', 'logistics_mechanic', 'Maintenance de premier et second échelon.', 'Mechanic', 30, 1, '2026-04-06 18:58:10'),
(119, 7, 62, 'Technicien maintenance', 'logistics_maint_technician', 'Diagnostic et réparation des systèmes.', 'Maintenance Technician', 40, 1, '2026-04-06 18:58:10'),
(120, 7, 62, 'Responsable parc matériel', 'logistics_fleet_manager', 'Gestion du parc et disponibilité opérationnelle.', 'Fleet Manager', 50, 1, '2026-04-06 18:58:10'),
(121, 7, 64, 'Médecin militaire', 'medical_officer', 'Responsabilité médicale et décisions sanitaires.', 'Medical Officer', 10, 1, '2026-04-06 18:58:10'),
(122, 7, 64, 'Infirmier militaire', 'medical_nurse', 'Soins infirmiers et stabilisation.', 'Field Nurse', 20, 1, '2026-04-06 18:58:10'),
(123, 7, 64, 'Auxiliaire sanitaire', 'medical_auxiliary', 'Soutien sanitaire et assistance au poste de secours.', 'Medical Assistant', 30, 1, '2026-04-06 18:58:10'),
(124, 7, 64, 'Secouriste', 'medical_first_responder', 'Premiers secours et évacuation sanitaire initiale.', 'Combat Medic', 40, 1, '2026-04-06 18:58:10'),
(125, 7, 66, 'Instructeur', 'instructor', 'Instruction collective et maintien des standards.', 'Drill Instructor', 10, 1, '2026-04-06 18:58:10'),
(126, 7, 66, 'Formateur', 'instruction_trainer', 'Conception et animation de modules pédagogiques.', 'Training Instructor', 20, 1, '2026-04-06 18:58:10'),
(127, 7, 66, 'Responsable formation', 'training_officer', 'Pilotage des parcours et des qualifications.', 'Training Lead', 30, 1, '2026-04-06 18:58:10'),
(128, 7, 66, 'Évaluateur', 'instruction_evaluator', 'Évaluation des compétences et des qualifications.', 'Evaluator', 40, 1, '2026-04-06 18:58:10'),
(129, 7, 68, 'Gestionnaire RH', 'hr', 'Gestion des effectifs et du dossier personnel.', 'Human Resources Specialist', 10, 1, '2026-04-06 18:58:10'),
(130, 7, 68, 'Officier administratif', 'admin_staff_officer', 'Courrier, dossiers et formalités administratives.', 'Administrative Officer', 20, 1, '2026-04-06 18:58:10'),
(131, 7, 68, 'Secrétaire unité', 'admin_unit_secretary', 'Secrétariat et suivi administratif de l’unité.', 'Unit Secretary', 30, 1, '2026-04-06 18:58:10'),
(132, 7, 70, 'Vétéran', 'veteran', 'Ancien combattant ou membre d’honneur actif en visibilité.', 'Veteran', 10, 1, '2026-04-06 18:58:10'),
(133, 7, 70, 'En formation', 'status_in_training', 'Parcours de formation en cours.', 'In training', 20, 1, '2026-04-06 18:58:10'),
(134, 7, 70, 'En probation', 'probation_member', 'Intégration sous période probatoire.', 'Probationary Member', 30, 1, '2026-04-06 18:58:10'),
(135, 7, 70, 'Suspendu', 'suspended_status', 'Participation suspendue — visibilité limitée.', 'Suspended', 40, 1, '2026-04-06 18:58:10'),
(136, 7, 70, 'Réserviste', 'status_reservist', 'Statut de réserve et disponibilité partielle.', 'Reservist', 50, 1, '2026-04-06 18:58:10'),
(137, 7, 70, 'Instructeur certifié', 'certified_instructor', 'Qualification pédagogique reconnue.', 'Instructor Certified', 60, 1, '2026-04-06 18:58:10'),
(138, 7, 70, 'En service actif', 'status_active_duty', 'Engagement opérationnel à plein temps.', 'Active Duty', 70, 1, '2026-04-06 18:58:10');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_job_role_categories`
--

CREATE TABLE `personnel_job_role_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personnel_job_role_categories`
--

INSERT INTO `personnel_job_role_categories` (`id`, `tenant_id`, `parent_id`, `name`, `slug`, `sort_order`, `created_at`) VALUES
(1, 1, NULL, 'Commandement', 'cmd-root', 10, '2026-04-05 11:27:53'),
(2, 1, NULL, 'Combat & appui', 'combat-root', 20, '2026-04-05 11:27:53'),
(3, 1, NULL, 'Soutien', 'soutien-root', 30, '2026-04-05 11:27:53'),
(4, 1, NULL, 'Formation & encadrement', 'formation-root', 40, '2026-04-05 11:27:53'),
(5, 1, 1, 'État-major / Opérations', 'cmd-em', 1, '2026-04-05 11:27:53'),
(6, 1, 2, 'Infanterie', 'combat-infanterie', 1, '2026-04-05 11:27:53'),
(7, 1, 2, 'Appuis & feux', 'combat-appuis', 2, '2026-04-05 11:27:53'),
(8, 1, 3, 'Logistique', 'soutien-log', 1, '2026-04-05 11:27:53'),
(9, 1, 4, 'Instruction', 'formation-inst', 1, '2026-04-05 11:27:53'),
(10, 7, NULL, 'Commandement', 'cmd-root', 10, '2026-04-05 11:27:53'),
(11, 7, NULL, 'Combat & appui', 'combat-root', 20, '2026-04-05 11:27:53'),
(12, 7, NULL, 'Soutien', 'soutien-root', 30, '2026-04-05 11:27:53'),
(13, 7, NULL, 'Formation & encadrement', 'formation-root', 40, '2026-04-05 11:27:53'),
(14, 7, 10, 'État-major / Opérations', 'cmd-em', 1, '2026-04-05 11:27:53'),
(15, 7, 11, 'Infanterie', 'combat-infanterie', 1, '2026-04-05 11:27:53'),
(16, 7, 11, 'Appuis & feux', 'combat-appuis', 2, '2026-04-05 11:27:53'),
(17, 7, 12, 'Logistique', 'soutien-log', 1, '2026-04-05 11:27:53'),
(18, 7, 13, 'Instruction', 'formation-inst', 1, '2026-04-05 11:27:53'),
(19, 1, NULL, 'État-major', 'etat-major', 50, '2026-04-06 18:58:10'),
(20, 1, 19, 'Commandement', 'etat-major-commandement', 10, '2026-04-06 18:58:10'),
(21, 1, 19, 'Opérations', 'etat-major-operations', 20, '2026-04-06 18:58:10'),
(22, 1, 19, 'Renseignement', 'etat-major-renseignement', 30, '2026-04-06 18:58:10'),
(23, 1, 19, 'Logistique', 'etat-major-logistique', 40, '2026-04-06 18:58:10'),
(24, 1, NULL, 'Infanterie', 'infanterie', 60, '2026-04-06 18:58:10'),
(25, 1, 24, 'Commandement', 'infanterie-commandement', 10, '2026-04-06 18:58:10'),
(26, 1, 24, 'Combattant', 'infanterie-combattant', 20, '2026-04-06 18:58:10'),
(27, 1, 24, 'Spécialités', 'infanterie-specialites', 30, '2026-04-06 18:58:10'),
(28, 1, NULL, 'Appuis & feux', 'appuis-feux', 70, '2026-04-06 18:58:10'),
(29, 1, 28, 'Coordination', 'appuis-feux-coordination', 10, '2026-04-06 18:58:10'),
(30, 1, 28, 'Artillerie', 'appuis-feux-artillerie', 20, '2026-04-06 18:58:10'),
(31, 1, NULL, 'Génie', 'genie', 80, '2026-04-06 18:58:10'),
(32, 1, 31, 'Combat', 'genie-combat', 10, '2026-04-06 18:58:10'),
(33, 1, 31, 'Infrastructure', 'genie-infrastructure', 20, '2026-04-06 18:58:10'),
(34, 1, NULL, 'Logistique', 'logistique', 90, '2026-04-06 18:58:10'),
(35, 1, 34, 'Transport', 'logistique-transport', 10, '2026-04-06 18:58:10'),
(36, 1, 34, 'Maintenance', 'logistique-maintenance', 20, '2026-04-06 18:58:10'),
(37, 1, NULL, 'Santé', 'sante', 100, '2026-04-06 18:58:10'),
(38, 1, 37, 'Médical', 'sante-medical', 10, '2026-04-06 18:58:10'),
(39, 1, NULL, 'Instruction', 'instruction', 110, '2026-04-06 18:58:10'),
(40, 1, 39, 'Formation', 'instruction-formation', 10, '2026-04-06 18:58:10'),
(41, 1, NULL, 'Administration', 'administration', 120, '2026-04-06 18:58:10'),
(42, 1, 41, 'Gestion', 'administration-gestion', 10, '2026-04-06 18:58:10'),
(43, 1, NULL, 'Statut', 'statut', 130, '2026-04-06 18:58:10'),
(44, 1, 43, 'Affichage', 'statut-affichage', 10, '2026-04-06 18:58:10'),
(45, 7, NULL, 'État-major', 'etat-major', 50, '2026-04-06 18:58:10'),
(46, 7, 45, 'Commandement', 'etat-major-commandement', 10, '2026-04-06 18:58:10'),
(47, 7, 45, 'Opérations', 'etat-major-operations', 20, '2026-04-06 18:58:10'),
(48, 7, 45, 'Renseignement', 'etat-major-renseignement', 30, '2026-04-06 18:58:10'),
(49, 7, 45, 'Logistique', 'etat-major-logistique', 40, '2026-04-06 18:58:10'),
(50, 7, NULL, 'Infanterie', 'infanterie', 60, '2026-04-06 18:58:10'),
(51, 7, 50, 'Commandement', 'infanterie-commandement', 10, '2026-04-06 18:58:10'),
(52, 7, 50, 'Combattant', 'infanterie-combattant', 20, '2026-04-06 18:58:10'),
(53, 7, 50, 'Spécialités', 'infanterie-specialites', 30, '2026-04-06 18:58:10'),
(54, 7, NULL, 'Appuis & feux', 'appuis-feux', 70, '2026-04-06 18:58:10'),
(55, 7, 54, 'Coordination', 'appuis-feux-coordination', 10, '2026-04-06 18:58:10'),
(56, 7, 54, 'Artillerie', 'appuis-feux-artillerie', 20, '2026-04-06 18:58:10'),
(57, 7, NULL, 'Génie', 'genie', 80, '2026-04-06 18:58:10'),
(58, 7, 57, 'Combat', 'genie-combat', 10, '2026-04-06 18:58:10'),
(59, 7, 57, 'Infrastructure', 'genie-infrastructure', 20, '2026-04-06 18:58:10'),
(60, 7, NULL, 'Logistique', 'logistique', 90, '2026-04-06 18:58:10'),
(61, 7, 60, 'Transport', 'logistique-transport', 10, '2026-04-06 18:58:10'),
(62, 7, 60, 'Maintenance', 'logistique-maintenance', 20, '2026-04-06 18:58:10'),
(63, 7, NULL, 'Santé', 'sante', 100, '2026-04-06 18:58:10'),
(64, 7, 63, 'Médical', 'sante-medical', 10, '2026-04-06 18:58:10'),
(65, 7, NULL, 'Instruction', 'instruction', 110, '2026-04-06 18:58:10'),
(66, 7, 65, 'Formation', 'instruction-formation', 10, '2026-04-06 18:58:10'),
(67, 7, NULL, 'Administration', 'administration', 120, '2026-04-06 18:58:10'),
(68, 7, 67, 'Gestion', 'administration-gestion', 10, '2026-04-06 18:58:10'),
(69, 7, NULL, 'Statut', 'statut', 130, '2026-04-06 18:58:10'),
(70, 7, 69, 'Affichage', 'statut-affichage', 10, '2026-04-06 18:58:10');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_job_role_permissions`
--

CREATE TABLE `personnel_job_role_permissions` (
  `personnel_job_role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personnel_job_role_permissions`
--

INSERT INTO `personnel_job_role_permissions` (`personnel_job_role_id`, `permission_id`) VALUES
(10, 108),
(10, 109),
(10, 110),
(10, 111),
(10, 112),
(10, 114),
(10, 115),
(10, 116),
(10, 139),
(10, 140),
(10, 141),
(10, 142),
(10, 143),
(10, 144),
(10, 145),
(10, 146),
(10, 147),
(10, 148),
(10, 149),
(10, 150),
(10, 151),
(10, 152),
(10, 153),
(10, 154),
(10, 155),
(10, 156),
(10, 157),
(10, 158),
(10, 159),
(10, 160),
(10, 161),
(10, 162),
(10, 163),
(10, 164),
(10, 165),
(10, 166),
(10, 167),
(10, 168),
(10, 169),
(10, 170),
(10, 171),
(10, 172),
(10, 173),
(10, 174);

-- --------------------------------------------------------

--
-- Structure de la table `personnel_media`
--

CREATE TABLE `personnel_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `media_type` enum('avatar','portrait','banner','patch','signature','fullbody') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnel_profiles`
--

CREATE TABLE `personnel_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `character_name` varchar(150) DEFAULT NULL,
  `callsign` varchar(100) DEFAULT NULL,
  `rank_display` varchar(100) DEFAULT NULL,
  `rank_display_override` varchar(100) DEFAULT NULL COMMENT 'Exception métier; sinon grades + overrides',
  `primary_role` varchar(100) DEFAULT NULL,
  `secondary_role` varchar(100) DEFAULT NULL,
  `personnel_job_role_id` int(10) UNSIGNED DEFAULT NULL,
  `role_sub_label` varchar(150) DEFAULT NULL,
  `primary_unit_id` int(10) UNSIGNED DEFAULT NULL,
  `clearance_level` varchar(50) DEFAULT NULL,
  `character_portrait_path` varchar(255) DEFAULT NULL,
  `character_banner_path` varchar(255) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `enlistment_date` date DEFAULT NULL,
  `motto` varchar(255) DEFAULT NULL,
  `readiness_score` tinyint(3) UNSIGNED DEFAULT 0,
  `command_notes` longtext DEFAULT NULL,
  `matricule_internal` varchar(100) DEFAULT NULL,
  `clearance_reviewed_at` datetime DEFAULT NULL,
  `equipment_class` varchar(100) DEFAULT NULL,
  `kit_assigned` varchar(255) DEFAULT NULL,
  `radio_assigned` varchar(100) DEFAULT NULL,
  `vehicle_authorized` varchar(255) DEFAULT NULL,
  `weapon_specialty` varchar(100) DEFAULT NULL,
  `deployable` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `personnel_profiles`
--

INSERT INTO `personnel_profiles` (`id`, `user_id`, `character_name`, `callsign`, `rank_display`, `rank_display_override`, `primary_role`, `secondary_role`, `personnel_job_role_id`, `role_sub_label`, `primary_unit_id`, `clearance_level`, `character_portrait_path`, `character_banner_path`, `blood_type`, `nationality`, `languages`, `enlistment_date`, `motto`, `readiness_score`, `command_notes`, `matricule_internal`, `clearance_reviewed_at`, `equipment_class`, `kit_assigned`, `radio_assigned`, `vehicle_authorized`, `weapon_specialty`, `deployable`, `created_at`, `updated_at`) VALUES
(4, 5, 'NewPI', 'N-01', 'Administrateur système', NULL, 'Officier opérations — Spécialiste gestionnaire administratif', '', 10, 'Spécialiste gestionnaire administratif', 2, 'Secret', 'uploads/portraits/5_1775383355.png', NULL, NULL, NULL, NULL, '2026-04-05', NULL, 0, '', 'ATH-00001', '2026-04-09 00:00:00', 'Command & Control', 'C2 léger / tablette mission', 'PRC-152', 'Utility / VT4', 'Carabine / pistolet', 1, '2026-04-05 09:16:46', '2026-04-09 17:09:05'),
(28, 7, 'Melvin MESNEL', 'E-11', NULL, NULL, 'JTAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-05 11:03:21', '2026-04-05 11:03:21'),
(43, 8, 'Melvin MESNEL', '', NULL, NULL, 'Instructeur — Spécialiste communication · JTAC · Gestionnaire RH', NULL, 18, 'Spécialiste communication', 3, 'Confidentiel', NULL, NULL, NULL, NULL, NULL, '2026-04-06', NULL, 100, '', 'ATH-00002', '2026-04-06 00:00:00', '', '', '', '', '', 1, '2026-04-05 16:31:53', '2026-04-06 21:28:45');

-- --------------------------------------------------------

--
-- Structure de la table `personnel_profile_job_roles`
--

CREATE TABLE `personnel_profile_job_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `personnel_job_role_id` int(10) UNSIGNED NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `role_detail` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personnel_profile_job_roles`
--

INSERT INTO `personnel_profile_job_roles` (`id`, `tenant_id`, `user_id`, `personnel_job_role_id`, `is_primary`, `sort_order`, `role_detail`, `created_at`, `updated_at`) VALUES
(30, 7, 8, 18, 1, 0, NULL, '2026-04-06 20:49:50', NULL),
(31, 7, 8, 106, 0, 1, NULL, '2026-04-06 20:49:50', NULL),
(32, 7, 8, 129, 0, 2, NULL, '2026-04-06 20:49:50', NULL),
(63, 7, 5, 10, 1, 0, 'Spécialiste gestionnaire administratif', '2026-04-09 17:09:05', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `personnel_qualifications`
--

CREATE TABLE `personnel_qualifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `qualification_name` varchar(150) NOT NULL,
  `level` varchar(50) DEFAULT NULL,
  `status` enum('valid','expiring','expired','in_progress') DEFAULT 'valid',
  `obtained_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnel_service_history`
--

CREATE TABLE `personnel_service_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `event_type` enum('assignment','promotion','qualification','deployment','award','discipline','note') NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `platform_alerts`
--

CREATE TABLE `platform_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `kind` enum('discount','novelty','info','urgent') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `cta_label` varchar(120) DEFAULT NULL,
  `cta_url` varchar(512) DEFAULT NULL,
  `coupon_code` varchar(64) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `audience_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`audience_json`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_key`, `value`, `updated_at`) VALUES
('brief_member_access', '1', '2026-04-06 18:33:03'),
('brief_member_closed_message', '', '2026-04-06 18:33:03');

-- --------------------------------------------------------

--
-- Structure de la table `platform_usage_events`
--

CREATE TABLE `platform_usage_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `feature_key` varchar(64) NOT NULL,
  `action` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `platform_usage_events`
--

INSERT INTO `platform_usage_events` (`id`, `tenant_id`, `user_id`, `feature_key`, `action`, `created_at`) VALUES
(1, 1, 1, 'dashboard_visit', 'view', '2026-04-04 14:53:57'),
(2, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:27:33'),
(3, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:27:52'),
(4, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:33:40'),
(5, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:34:37'),
(6, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:34:41'),
(7, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:34:43'),
(8, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:34:43'),
(9, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:34:45'),
(10, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:34:46'),
(11, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:34:47'),
(12, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:39:05'),
(13, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:40:55'),
(14, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:40:58'),
(15, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:41:00'),
(16, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:02'),
(17, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:03'),
(18, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:04'),
(19, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:04'),
(20, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:04'),
(21, 6, 2, 'dashboard_visit', 'view', '2026-04-04 15:42:05'),
(22, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:42:07'),
(23, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:42:09'),
(24, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:46:31'),
(25, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:46:32'),
(26, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:46:33'),
(27, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:47:05'),
(28, 1, 1, 'dashboard_visit', 'view', '2026-04-04 15:47:08'),
(29, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:10:12'),
(30, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:15:37'),
(31, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:15:38'),
(32, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:16:04'),
(33, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:16:04'),
(34, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:36:00'),
(35, 1, 3, 'dashboard_visit', 'view', '2026-04-04 16:41:04'),
(36, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:11:49'),
(37, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:11:55'),
(38, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:13:08'),
(39, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:24:25'),
(40, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:50:44'),
(41, 1, 3, 'dashboard_visit', 'view', '2026-04-05 08:54:11'),
(42, 1, 3, 'dashboard_visit', 'view', '2026-04-05 09:03:40'),
(43, 1, 3, 'dashboard_visit', 'view', '2026-04-05 09:06:10'),
(44, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:10:02'),
(45, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:11:08'),
(46, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:11:09'),
(47, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:14:29'),
(48, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:16:20'),
(49, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:16:25'),
(50, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:26:44'),
(51, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:34:23'),
(52, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:35:09'),
(53, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:50:05'),
(54, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:50:42'),
(55, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:54:46'),
(56, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:57:29'),
(57, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:58:23'),
(58, 7, 5, 'dashboard_visit', 'view', '2026-04-05 09:58:37'),
(59, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:00:43'),
(60, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:01:49'),
(61, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:02:17'),
(62, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:02:43'),
(63, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:04:10'),
(64, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:04:31'),
(65, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:04:32'),
(66, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:04:51'),
(67, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:05:26'),
(68, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:23:11'),
(69, 7, 5, 'dashboard_visit', 'view', '2026-04-05 10:53:17'),
(70, 1, 7, 'dashboard_visit', 'view', '2026-04-05 11:22:15'),
(71, 1, 7, 'dashboard_visit', 'view', '2026-04-05 11:24:24'),
(72, 7, 5, 'dashboard_visit', 'view', '2026-04-05 11:24:40'),
(73, 7, 5, 'dashboard_visit', 'view', '2026-04-05 11:40:28'),
(74, 7, 5, 'dashboard_visit', 'view', '2026-04-05 12:03:17'),
(75, 7, 5, 'dashboard_visit', 'view', '2026-04-05 12:05:48'),
(76, 7, 5, 'dashboard_visit', 'view', '2026-04-05 12:07:58'),
(77, 1, 7, 'dashboard_visit', 'view', '2026-04-05 12:10:49'),
(78, 1, 7, 'dashboard_visit', 'view', '2026-04-05 12:10:52'),
(79, 7, 5, 'dashboard_visit', 'view', '2026-04-05 15:52:27'),
(80, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:03:32'),
(81, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:19:21'),
(82, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:19:22'),
(83, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:19:39'),
(84, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:19:43'),
(85, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:19:45'),
(86, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:22:44'),
(87, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:25:39'),
(88, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:25:44'),
(89, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:26:14'),
(90, 1, 7, 'dashboard_visit', 'view', '2026-04-05 16:33:02'),
(91, 1, 7, 'dashboard_visit', 'view', '2026-04-05 16:33:04'),
(92, 1, 7, 'dashboard_visit', 'view', '2026-04-05 16:33:12'),
(93, 1, 7, 'dashboard_visit', 'view', '2026-04-05 16:33:12'),
(94, 1, 7, 'dashboard_visit', 'view', '2026-04-05 16:33:13'),
(95, 7, 5, 'dashboard_visit', 'view', '2026-04-05 21:25:50'),
(96, 7, 5, 'dashboard_visit', 'view', '2026-04-05 21:38:01'),
(97, 7, 5, 'dashboard_visit', 'view', '2026-04-05 21:56:26'),
(98, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:02:02'),
(99, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:34:56'),
(100, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:34:56'),
(101, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:38:29'),
(102, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:40:02'),
(103, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:40:06'),
(104, 7, 5, 'dashboard_visit', 'view', '2026-04-05 22:43:34'),
(105, 7, 5, 'dashboard_visit', 'view', '2026-04-06 10:37:31'),
(106, 7, 5, 'dashboard_visit', 'view', '2026-04-06 10:39:38'),
(107, 7, 5, 'dashboard_visit', 'view', '2026-04-06 10:39:38'),
(108, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:35:12'),
(109, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:35:13'),
(110, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:35:13'),
(111, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:35:14'),
(112, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:36:38'),
(113, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:50:23'),
(114, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:12'),
(115, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:13'),
(116, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:21'),
(117, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:22'),
(118, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:23'),
(119, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:52:25'),
(120, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:53:34'),
(121, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:53:35'),
(122, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:53:35'),
(123, 7, 5, 'dashboard_visit', 'view', '2026-04-06 11:55:10'),
(124, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:41'),
(125, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:43'),
(126, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:45'),
(127, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:47'),
(128, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:48'),
(129, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:48'),
(130, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:48'),
(131, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:49'),
(132, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:49'),
(133, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:49'),
(134, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:50'),
(135, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:04:50'),
(136, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:01'),
(137, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:02'),
(138, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:02'),
(139, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:03'),
(140, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:03'),
(141, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:06'),
(142, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:07'),
(143, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:07'),
(144, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:07'),
(145, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:08'),
(146, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:08'),
(147, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:08'),
(148, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:08'),
(149, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:14:09'),
(150, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:02'),
(151, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:04'),
(152, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:05'),
(153, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:05'),
(154, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:06'),
(155, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:06'),
(156, 7, 5, 'dashboard_visit', 'view', '2026-04-06 12:21:06'),
(157, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:20'),
(158, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:21'),
(159, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:27'),
(160, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:27'),
(161, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:28'),
(162, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:28'),
(163, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:28'),
(164, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:28'),
(165, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(166, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(167, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(168, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(169, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(170, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:29'),
(171, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:30'),
(172, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:30'),
(173, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:30'),
(174, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:30'),
(175, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:30'),
(176, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:31'),
(177, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:40'),
(178, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:40'),
(179, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:41'),
(180, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:41'),
(181, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:41'),
(182, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:44'),
(183, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:01:45'),
(184, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:02:44'),
(185, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:02:46'),
(186, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:03:47'),
(187, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:05:36'),
(188, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:06:18'),
(189, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:08:20'),
(190, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:37'),
(191, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:38'),
(192, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:38'),
(193, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:38'),
(194, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:39'),
(195, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:41'),
(196, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:41'),
(197, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:41'),
(198, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:42'),
(199, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:42'),
(200, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:42'),
(201, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:42'),
(202, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:43'),
(203, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:13:48'),
(204, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:15:00'),
(205, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:15:01'),
(206, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:17:03'),
(207, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:01'),
(208, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:02'),
(209, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:02'),
(210, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:02'),
(211, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:28'),
(212, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:31'),
(213, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:31'),
(214, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:31'),
(215, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:32'),
(216, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:33'),
(217, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:33'),
(218, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:33'),
(219, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:33'),
(220, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:19:54'),
(221, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:22:55'),
(222, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:22:58'),
(223, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:22:58'),
(224, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:22:59'),
(225, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:22:59'),
(226, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:00'),
(227, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:00'),
(228, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:00'),
(229, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:00'),
(230, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:01'),
(231, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:01'),
(232, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:01'),
(233, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:04'),
(234, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:04'),
(235, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:04'),
(236, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:05'),
(237, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:05'),
(238, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:05'),
(239, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:10'),
(240, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:10'),
(241, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:12'),
(242, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:12'),
(243, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:12'),
(244, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(245, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(246, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(247, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(248, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(249, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:13'),
(250, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:14'),
(251, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:14'),
(252, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:23:14'),
(253, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:25:53'),
(254, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:25:54'),
(255, 7, 5, 'dashboard_visit', 'view', '2026-04-06 17:43:59'),
(256, 7, 8, 'dashboard_visit', 'view', '2026-04-06 17:47:37'),
(257, 7, 8, 'dashboard_visit', 'view', '2026-04-06 17:47:44'),
(258, 7, 5, 'dashboard_visit', 'view', '2026-04-06 18:17:42'),
(259, 7, 8, 'dashboard_visit', 'view', '2026-04-06 18:18:10'),
(260, 7, 5, 'dashboard_visit', 'view', '2026-04-06 19:04:22'),
(261, 7, 5, 'dashboard_visit', 'view', '2026-04-06 19:04:23'),
(262, 7, 8, 'dashboard_visit', 'view', '2026-04-06 19:10:29'),
(263, 7, 8, 'dashboard_visit', 'view', '2026-04-06 19:10:30'),
(264, 7, 8, 'dashboard_visit', 'view', '2026-04-06 19:11:08'),
(265, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:00:50'),
(266, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:02:48'),
(267, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:02:48'),
(268, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:07:31'),
(269, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:07:32'),
(270, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:11:15'),
(271, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:11:17'),
(272, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:11:18'),
(273, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:12:36'),
(274, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:14:03'),
(275, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:14:23'),
(276, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:15:32'),
(277, 7, 5, 'dashboard_visit', 'view', '2026-04-06 20:51:52'),
(278, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:03:47'),
(279, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:03:49'),
(280, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:04:31'),
(281, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:04:33'),
(282, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:07:09'),
(283, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:07:34'),
(284, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:21:43'),
(285, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:22:54'),
(286, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:25:15'),
(287, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:43:35'),
(288, 7, 5, 'dashboard_visit', 'view', '2026-04-06 21:44:13'),
(289, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:09:00'),
(290, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:09:28'),
(291, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:09:38'),
(292, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:09:43'),
(293, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:10:32'),
(294, 7, 5, 'dashboard_visit', 'view', '2026-04-07 09:10:37'),
(295, 7, 5, 'dashboard_visit', 'view', '2026-04-07 10:34:51'),
(296, 7, 5, 'dashboard_visit', 'view', '2026-04-07 10:34:51'),
(297, 7, 5, 'dashboard_visit', 'view', '2026-04-07 18:17:00'),
(298, 7, 5, 'dashboard_visit', 'view', '2026-04-08 08:33:23'),
(299, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:36:58'),
(300, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:37:40'),
(301, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:14'),
(302, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:14'),
(303, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:15'),
(304, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:15'),
(305, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:15'),
(306, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:20'),
(307, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:20'),
(308, 7, 5, 'dashboard_visit', 'view', '2026-04-08 10:43:21'),
(309, 7, 5, 'dashboard_visit', 'view', '2026-04-09 06:55:25'),
(310, 7, 5, 'dashboard_visit', 'view', '2026-04-09 06:55:32'),
(311, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:18:59'),
(312, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:30:22'),
(313, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:52:58'),
(314, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:54:25'),
(315, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:54:26'),
(316, 7, 5, 'dashboard_visit', 'view', '2026-04-09 10:58:16'),
(317, 7, 5, 'dashboard_visit', 'view', '2026-04-09 11:01:17'),
(318, 7, 5, 'dashboard_visit', 'view', '2026-04-09 17:04:37'),
(319, 7, 5, 'dashboard_visit', 'view', '2026-04-09 17:04:38'),
(320, 7, 5, 'dashboard_visit', 'view', '2026-04-09 17:05:10'),
(321, 7, 5, 'dashboard_visit', 'view', '2026-04-09 17:10:40'),
(322, 7, 5, 'dashboard_visit', 'view', '2026-04-10 13:01:39'),
(323, 7, 5, 'dashboard_visit', 'view', '2026-04-12 16:42:10'),
(324, 7, 5, 'dashboard_visit', 'view', '2026-04-12 17:14:28'),
(325, 7, 5, 'dashboard_visit', 'view', '2026-04-12 17:14:28'),
(326, 7, 5, 'dashboard_visit', 'view', '2026-04-12 17:22:57'),
(327, 7, 5, 'dashboard_visit', 'view', '2026-04-12 17:24:30'),
(328, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:55:47'),
(329, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:56:05'),
(330, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:56:06'),
(331, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:56:07'),
(332, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:56:08'),
(333, 7, 5, 'dashboard_visit', 'view', '2026-04-13 10:56:08'),
(334, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:28'),
(335, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:29'),
(336, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:30'),
(337, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:30'),
(338, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:37'),
(339, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:37'),
(340, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:38'),
(341, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:38'),
(342, 7, 5, 'dashboard_visit', 'view', '2026-04-13 11:05:38');

-- --------------------------------------------------------

--
-- Structure de la table `positions`
--

CREATE TABLE `positions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_temporary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recon_images`
--

CREATE TABLE `recon_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `mission_id` varchar(128) DEFAULT NULL,
  `author_callsign` varchar(128) NOT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `side` varchar(16) DEFAULT 'WEST',
  `image_path` varchar(500) NOT NULL,
  `thumb_path` varchar(500) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `pos_x` decimal(15,4) DEFAULT NULL,
  `pos_y` decimal(15,4) DEFAULT NULL,
  `pos_z` decimal(15,4) DEFAULT NULL,
  `grid_ref` varchar(32) DEFAULT NULL,
  `heading` decimal(8,2) DEFAULT NULL,
  `altitude` decimal(10,2) DEFAULT NULL,
  `device_type` varchar(64) DEFAULT 'CTAB',
  `captured_at` datetime DEFAULT NULL,
  `atak_cas_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recruitment_openings`
--

CREATE TABLE `recruitment_openings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `personnel_job_role_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements_json`)),
  `employment_contract_label` varchar(160) DEFAULT NULL,
  `employment_context_label` varchar(160) DEFAULT NULL,
  `personnel_category` varchar(32) NOT NULL DEFAULT 'other',
  `arm_domain` varchar(32) DEFAULT NULL,
  `clearance_level` varchar(32) NOT NULL DEFAULT 'none',
  `candidate_profile_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`candidate_profile_items`)),
  `technical_notice` text DEFAULT NULL,
  `mission_lead` text DEFAULT NULL,
  `responsibility_blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`responsibility_blocks`)),
  `public_page_slug` varchar(120) DEFAULT NULL,
  `reference_public` varchar(180) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `forum_topic_id_externe` int(10) UNSIGNED DEFAULT NULL,
  `forum_topic_id_interne` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recruitment_openings`
--

INSERT INTO `recruitment_openings` (`id`, `tenant_id`, `unit_id`, `created_by_user_id`, `personnel_job_role_id`, `title`, `summary`, `description`, `requirements_json`, `employment_contract_label`, `employment_context_label`, `personnel_category`, `arm_domain`, `clearance_level`, `candidate_profile_items`, `technical_notice`, `mission_lead`, `responsibility_blocks`, `public_page_slug`, `reference_public`, `status`, `published_at`, `closed_at`, `created_at`, `updated_at`, `forum_topic_id_externe`, `forum_topic_id_interne`) VALUES
(1, 7, 3, 5, 89, 'Technicien en cyber-défense', NULL, 'Description complète', '[\"-1\",\"-2\",\"-3\"]', '15', 'Unité cyber-défense', 'officer', 'signals', 'none', '[{\"rubrique\":\"Rubique 1\",\"detail\":\"Détails 1\"}]', 'Aucun', 'Accroche', '[{\"ordre\":1,\"theme\":\"PRIMO\",\"titre\":\"Responsabilité\",\"corps\":\"\"},{\"ordre\":2,\"theme\":\"SECONDO\",\"titre\":\"Responsabilité\",\"corps\":\"\"},{\"ordre\":3,\"theme\":\"TERTIO\",\"titre\":\"Responsabilité\",\"corps\":\"\"}]', 'athenasys-1resection-rec-001-2026', 'ATHENASYS/1RESECTION/REC/001-2026', 'closed', '2026-04-09 10:50:18', '2026-04-09 17:04:53', '2026-04-09 10:49:56', '2026-04-09 17:04:53', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `recruitment_opening_counters`
--

CREATE TABLE `recruitment_opening_counters` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `last_seq` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recruitment_opening_counters`
--

INSERT INTO `recruitment_opening_counters` (`tenant_id`, `year`, `last_seq`) VALUES
(7, 2026, 1);

-- --------------------------------------------------------

--
-- Structure de la table `recruitment_presets`
--

CREATE TABLE `recruitment_presets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(120) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `recruitment_presets`
--

INSERT INTO `recruitment_presets` (`id`, `user_id`, `label`, `payload`, `created_at`, `updated_at`) VALUES
(1, 3, 'Melvin MESNEL', '{\"callsign\":\"\",\"availability\":\"\",\"motivation_why_join\":\"\"}', '2026-04-04 16:42:22', '2026-04-04 16:42:22');

-- --------------------------------------------------------

--
-- Structure de la table `recurrence_rules`
--

CREATE TABLE `recurrence_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `recurrence_type` enum('NONE','PERIODIC','EVENT_BASED') NOT NULL DEFAULT 'NONE',
  `interval_days` int(10) UNSIGNED DEFAULT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `grace_days` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `referral_attributions`
--

CREATE TABLE `referral_attributions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referrer_user_id` int(10) UNSIGNED NOT NULL,
  `referred_tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(32) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `label_en` varchar(160) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `role_layer` enum('site','community','intra') NOT NULL DEFAULT 'community',
  `semantic_tier` enum('authority','function','specialty','status','support','liaison') NOT NULL DEFAULT 'function',
  `is_visual_only` tinyint(1) NOT NULL DEFAULT 0,
  `display_priority` int(11) NOT NULL DEFAULT 0,
  `display_weight` int(11) NOT NULL DEFAULT 0,
  `display_group` int(11) NOT NULL DEFAULT 2,
  `parent_role_id` int(10) UNSIGNED DEFAULT NULL,
  `badge_style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`badge_style`)),
  `is_system_critical` tinyint(1) NOT NULL DEFAULT 0,
  `definition_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `category`, `subcategory`, `label_en`, `slug`, `description`, `is_system`, `is_locked`, `role_layer`, `semantic_tier`, `is_visual_only`, `display_priority`, `display_weight`, `display_group`, `parent_role_id`, `badge_style`, `is_system_critical`, `definition_id`, `created_at`) VALUES
(1, 1, 'Gestionnaire administratif d’organisation', NULL, NULL, NULL, 'tenant_admin', 'Administration opérationnelle quotidienne : membres, contenus et paramètres internes.', 1, 0, 'community', 'authority', 0, 0, 0, 1, NULL, NULL, 0, NULL, '2026-03-13 17:47:31'),
(2, 1, 'Officier responsable de la communication', NULL, NULL, NULL, 'forum_moderator', 'Supervision des échanges, diffusion des annonces, modération et structuration du discours collectif.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-03-13 19:23:12'),
(3, 1, 'Opérateur', NULL, NULL, NULL, 'member', 'Membre titulaire de l’unité : accès forum, documents standards et formations selon affectation.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-03-13 19:23:12'),
(4, 1, 'Cadre', NULL, NULL, NULL, 'officer', 'Encadrement : coordination d’équipe, documents opérationnels et visibilité renforcée sur les ressources.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-03-14 00:01:46'),
(14, NULL, 'Gestionnaire de la plateforme', NULL, NULL, NULL, 'site_super_admin', 'Administration transverse du système : accès global, maintenance, sécurité et supervision technique.', 1, 1, 'site', 'authority', 0, 0, 0, 1, NULL, NULL, 1, NULL, '2026-04-04 15:13:10'),
(15, 1, 'Gestionnaire d’organisation', NULL, NULL, NULL, 'community_owner', 'Détient l’autorité stratégique sur l’entité. Gouvernance globale, hors périmètre technique plateforme.', 1, 1, 'community', 'authority', 0, 0, 0, 1, NULL, NULL, 1, NULL, '2026-04-04 15:13:10'),
(21, 1, 'Recruteur', NULL, NULL, NULL, 'recruiter', 'Pipeline recrutement : candidatures, échanges avec les postulants et liaison avec le commandement.', 1, 0, 'community', 'function', 0, 0, 0, 2, NULL, NULL, 0, 10, '2026-04-05 08:48:49'),
(22, 7, 'Gestionnaire d’organisation', NULL, NULL, NULL, 'community_owner', 'Détient l’autorité stratégique sur l’entité. Gouvernance globale, hors périmètre technique plateforme.', 1, 1, 'community', 'authority', 0, 0, 0, 1, NULL, NULL, 1, NULL, '2026-04-05 09:10:01'),
(23, 7, 'Gestionnaire administratif d’organisation', NULL, NULL, NULL, 'tenant_admin', 'Administration opérationnelle quotidienne : membres, contenus et paramètres internes.', 1, 0, 'community', 'authority', 0, 0, 0, 1, NULL, NULL, 0, NULL, '2026-04-05 09:10:01'),
(24, 7, 'Officier responsable de la communication', NULL, NULL, NULL, 'forum_moderator', 'Supervision des échanges, diffusion des annonces, modération et structuration du discours collectif.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 09:10:01'),
(25, 7, 'Opérateur', NULL, NULL, NULL, 'member', 'Membre titulaire de l’unité : accès forum, documents standards et formations selon affectation.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 09:10:01'),
(26, 7, 'Cadre', NULL, NULL, NULL, 'officer', 'Encadrement : coordination d’équipe, documents opérationnels et visibilité renforcée sur les ressources.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 09:10:01'),
(27, 7, 'Gestionnaire RH', 'Administration', 'Gestion', 'Human Resources Specialist', 'hr', 'Gestion des effectifs et du dossier personnel.', 1, 0, 'intra', 'support', 0, 10, 10, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-05 09:10:02'),
(28, 7, 'Visiteur', NULL, NULL, NULL, 'invite', 'Accès limité en attente d’intégration ou compte prospect (lecture ciblée).', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 09:10:02'),
(29, 7, 'Recruteur', NULL, NULL, NULL, 'recruiter', 'Pipeline recrutement : candidatures, échanges avec les postulants et liaison avec le commandement.', 1, 0, 'community', 'function', 0, 0, 0, 2, NULL, NULL, 0, 10, '2026-04-05 09:11:07'),
(30, 1, 'Gestionnaire RH', 'Administration', 'Gestion', 'Human Resources Specialist', 'hr', 'Gestion des effectifs et du dossier personnel.', 1, 0, 'intra', 'support', 0, 10, 10, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-05 11:45:15'),
(31, 1, 'Visiteur', NULL, NULL, NULL, 'invite', 'Accès limité en attente d’intégration ou compte prospect (lecture ciblée).', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(32, 1, 'Instructeur', 'Instruction', 'Formation', 'Drill Instructor', 'instructor', 'Instruction collective et maintien des standards.', 1, 0, 'intra', 'function', 0, 10, 10, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-05 11:45:15'),
(33, 1, 'OPSAN', NULL, NULL, NULL, 'medic', 'Santé / secours : visibilité renforcée sur les informations médicales autorisées et coordination sanitaire.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(34, 1, 'Logistique', NULL, NULL, NULL, 'logistics', 'Soutien matériel : dépôt, fiches équipement et documentation de soutien.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(36, 1, 'Période d’essai', NULL, NULL, NULL, 'probation', 'Intégration provisoire : participation encadrée au forum en attendant la titularisation.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(37, 7, 'Instructeur', 'Instruction', 'Formation', 'Drill Instructor', 'instructor', 'Instruction collective et maintien des standards.', 1, 0, 'intra', 'function', 0, 10, 10, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-05 11:45:15'),
(38, 7, 'OPSAN', NULL, NULL, NULL, 'medic', 'Santé / secours : visibilité renforcée sur les informations médicales autorisées et coordination sanitaire.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(39, 7, 'Logistique', NULL, NULL, NULL, 'logistics', 'Soutien matériel : dépôt, fiches équipement et documentation de soutien.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(40, 7, 'R2 (transmissions)', NULL, NULL, NULL, 'rto', 'Radio-téléphoniste / transmissions : diffusion d’informations officielles et coordination des annonces.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(41, 7, 'Période d’essai', NULL, NULL, NULL, 'probation', 'Intégration provisoire : participation encadrée au forum en attendant la titularisation.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-05 11:45:15'),
(42, 1, 'R2 (transmissions)', NULL, NULL, NULL, 'rto', 'Radio-téléphoniste / transmissions : diffusion d’informations officielles et coordination des annonces.', 1, 0, 'intra', 'function', 0, 0, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 17:33:18'),
(43, 1, 'Chef adjoint d’organisation', NULL, NULL, NULL, 'deputy_commander', 'Adjoint à la direction : coordination et relais de gouvernance.', 1, 0, 'community', 'authority', 0, 50, 0, 1, 15, NULL, 0, NULL, '2026-04-06 18:33:03'),
(44, 1, 'Officier opérations', 'État-major', 'Opérations', 'Operations Officer (S3)', 'operations_officer', 'Coordination des opérations et activités.', 1, 0, 'intra', 'function', 0, 50, 50, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, 8, '2026-04-06 18:33:03'),
(45, 1, 'Responsable formation', 'Instruction', 'Formation', 'Training Lead', 'training_officer', 'Pilotage des parcours et des qualifications.', 1, 0, 'intra', 'function', 0, 30, 30, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, 17, '2026-04-06 18:33:03'),
(46, 1, 'Officier renseignement', 'État-major', 'Renseignement', 'Intelligence Officer (S2)', 'intelligence_officer', 'Collecte, analyse et diffusion du renseignement.', 1, 0, 'intra', 'function', 0, 90, 90, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:33:03'),
(47, 1, 'Officier logistique', 'État-major', 'Logistique', 'Logistics Officer (S4)', 'logistics_officer', 'Pilotage du soutien et de la chaîne logistique.', 1, 0, 'intra', 'function', 0, 130, 130, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:33:03'),
(48, 1, 'Officier discipline', NULL, NULL, NULL, 'discipline_officer', 'Application du règlement intérieur et suivi des incidents.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(49, 1, 'Officier recrutement', NULL, NULL, NULL, 'recruitment_officer', 'Pipeline des candidatures et intégration des nouveaux membres.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(50, 1, 'Officier sécurité', NULL, NULL, NULL, 'security_officer', 'Sensibilisation, bonnes pratiques et coordination sécurité.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(51, 1, 'Administrateur technique local', NULL, NULL, NULL, 'technical_admin', 'Paramètres techniques et outils au sein de la communauté.', 1, 0, 'community', 'authority', 0, 50, 0, 1, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(52, 1, 'Contrôleur interne', NULL, NULL, NULL, 'auditor_internal', 'Contrôles internes et recommandations d’amélioration.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(53, 1, 'Fondateur', NULL, NULL, NULL, 'founder', 'Reconnaissance historique de la création de l’entité.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(54, 1, 'Vétéran', 'Statut', 'Affichage', 'Veteran', 'veteran', 'Ancien combattant ou membre d’honneur actif en visibilité.', 1, 0, 'intra', 'status', 1, 10, 10, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(55, 1, 'Instructeur certifié', 'Statut', 'Affichage', 'Instructor Certified', 'certified_instructor', 'Qualification pédagogique reconnue.', 1, 0, 'intra', 'status', 1, 60, 60, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(56, 1, 'Membre d’élite', NULL, NULL, NULL, 'elite_member', 'Performance ou engagement remarquable.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(57, 1, 'Sous surveillance', NULL, NULL, NULL, 'disciplinary_watch', 'Signal interne de suivi disciplinaire.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(58, 1, 'En probation', 'Statut', 'Affichage', 'Probationary Member', 'probation_member', 'Intégration sous période probatoire.', 1, 0, 'intra', 'status', 1, 30, 30, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(59, 1, 'Suspendu', 'Statut', 'Affichage', 'Suspended', 'suspended_status', 'Participation suspendue — visibilité limitée.', 1, 0, 'intra', 'status', 1, 40, 40, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(60, 1, 'Membre d’honneur', NULL, NULL, NULL, 'honorary_member', 'Reconnaissance pour membres externes ou retraités.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(61, 7, 'Chef adjoint d’organisation', NULL, NULL, NULL, 'deputy_commander', 'Adjoint à la direction : coordination et relais de gouvernance.', 1, 0, 'community', 'authority', 0, 50, 0, 1, 22, NULL, 0, NULL, '2026-04-06 18:33:03'),
(62, 7, 'Officier opérations', 'État-major', 'Opérations', 'Operations Officer (S3)', 'operations_officer', 'Coordination des opérations et activités.', 1, 0, 'intra', 'function', 0, 50, 50, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, 8, '2026-04-06 18:33:03'),
(63, 7, 'Responsable formation', 'Instruction', 'Formation', 'Training Lead', 'training_officer', 'Pilotage des parcours et des qualifications.', 1, 0, 'intra', 'function', 0, 30, 30, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, 17, '2026-04-06 18:33:03'),
(64, 7, 'Officier renseignement', 'État-major', 'Renseignement', 'Intelligence Officer (S2)', 'intelligence_officer', 'Collecte, analyse et diffusion du renseignement.', 1, 0, 'intra', 'function', 0, 90, 90, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:33:03'),
(65, 7, 'Officier logistique', 'État-major', 'Logistique', 'Logistics Officer (S4)', 'logistics_officer', 'Pilotage du soutien et de la chaîne logistique.', 1, 0, 'intra', 'function', 0, 130, 130, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:33:03'),
(66, 7, 'Officier discipline', NULL, NULL, NULL, 'discipline_officer', 'Application du règlement intérieur et suivi des incidents.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(67, 7, 'Officier recrutement', NULL, NULL, NULL, 'recruitment_officer', 'Pipeline des candidatures et intégration des nouveaux membres.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(68, 7, 'Officier sécurité', NULL, NULL, NULL, 'security_officer', 'Sensibilisation, bonnes pratiques et coordination sécurité.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(69, 7, 'Administrateur technique local', NULL, NULL, NULL, 'technical_admin', 'Paramètres techniques et outils au sein de la communauté.', 1, 0, 'community', 'authority', 0, 50, 0, 1, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(70, 7, 'Contrôleur interne', NULL, NULL, NULL, 'auditor_internal', 'Contrôles internes et recommandations d’amélioration.', 1, 0, 'intra', 'function', 0, 50, 0, 2, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(71, 7, 'Fondateur', NULL, NULL, NULL, 'founder', 'Reconnaissance historique de la création de l’entité.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(72, 7, 'Vétéran', 'Statut', 'Affichage', 'Veteran', 'veteran', 'Ancien combattant ou membre d’honneur actif en visibilité.', 1, 0, 'intra', 'status', 1, 10, 10, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(73, 7, 'Instructeur certifié', 'Statut', 'Affichage', 'Instructor Certified', 'certified_instructor', 'Qualification pédagogique reconnue.', 1, 0, 'intra', 'status', 1, 60, 60, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(74, 7, 'Membre d’élite', NULL, NULL, NULL, 'elite_member', 'Performance ou engagement remarquable.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(75, 7, 'Sous surveillance', NULL, NULL, NULL, 'disciplinary_watch', 'Signal interne de suivi disciplinaire.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(76, 7, 'En probation', 'Statut', 'Affichage', 'Probationary Member', 'probation_member', 'Intégration sous période probatoire.', 1, 0, 'intra', 'status', 1, 30, 30, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(77, 7, 'Suspendu', 'Statut', 'Affichage', 'Suspended', 'suspended_status', 'Participation suspendue — visibilité limitée.', 1, 0, 'intra', 'status', 1, 40, 40, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:33:03'),
(78, 7, 'Membre d’honneur', NULL, NULL, NULL, 'honorary_member', 'Reconnaissance pour membres externes ou retraités.', 1, 0, 'intra', 'status', 1, 80, 0, 3, NULL, NULL, 0, NULL, '2026-04-06 18:33:03'),
(79, 1, 'Chef de corps', 'État-major', 'Commandement', 'Commanding Officer', 'command_unit_commander', 'Autorité de commandement de l’unité.', 1, 0, 'intra', 'authority', 0, 10, 10, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(80, 1, 'Chef adjoint', 'État-major', 'Commandement', 'Executive Officer', 'command_executive_officer', 'Adjoint au commandement et relais opérationnel.', 1, 0, 'intra', 'authority', 0, 20, 20, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(81, 1, 'Officier supérieur', 'État-major', 'Commandement', 'Senior Officer', 'command_senior_officer', 'Encadrement supérieur et coordination générale.', 1, 0, 'intra', 'authority', 0, 30, 30, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(82, 1, 'Officier de permanence', 'État-major', 'Commandement', 'Duty Officer', 'command_duty_officer', 'Responsable de la permanence et des décisions courantes.', 1, 0, 'intra', 'function', 0, 40, 40, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(83, 1, 'Officier planification', 'État-major', 'Opérations', 'Plans Officer', 'staff_plans_officer', 'Plans, ordres et synchronisation des moyens.', 1, 0, 'intra', 'function', 0, 60, 60, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(84, 1, 'Officier conduite', 'État-major', 'Opérations', 'Battle Captain', 'staff_battle_captain', 'Conduite de la manœuvre et de la situation tactique.', 1, 0, 'intra', 'function', 0, 70, 70, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(85, 1, 'Officier coordination interarmes', 'État-major', 'Opérations', 'Joint Fires Coordinator', 'staff_joint_coordination_officer', 'Coordination des effets interarmes et appuis.', 1, 0, 'intra', 'liaison', 0, 80, 80, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(86, 1, 'Analyste renseignement', 'État-major', 'Renseignement', 'Intelligence Analyst', 'staff_intel_analyst', 'Production d’analyses et de fiches situation.', 1, 0, 'intra', 'function', 0, 100, 100, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(87, 1, 'Officier exploitation', 'État-major', 'Renseignement', 'SIGINT Specialist', 'staff_intel_exploitation', 'Exploitation technique des sources et des flux.', 1, 0, 'intra', 'specialty', 0, 110, 110, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(88, 1, 'Cellule renseignement', 'État-major', 'Renseignement', 'Intelligence Cell Operator', 'staff_intel_cell', 'Traitement et diffusion au sein de la cellule.', 1, 0, 'intra', 'function', 0, 120, 120, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(89, 1, 'Responsable soutien', 'État-major', 'Logistique', 'Supply Specialist', 'staff_sustainment_lead', 'Gestion des stocks et du soutien quotidien.', 1, 0, 'intra', 'support', 0, 140, 140, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(90, 1, 'Gestionnaire flux logistiques', 'État-major', 'Logistique', 'Motor Transport Operator', 'staff_logistics_flow_manager', 'Organisation des flux, convois et dotations.', 1, 0, 'intra', 'support', 0, 150, 150, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(91, 1, 'Chef de section', 'Infanterie', 'Commandement', 'Platoon Leader', 'infantry_section_chief', 'Encadrement d’une section au combat.', 1, 0, 'intra', 'authority', 0, 10, 10, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(92, 1, 'Chef de groupe', 'Infanterie', 'Commandement', 'Squad Leader', 'infantry_group_chief', 'Encadrement d’un groupe tactique.', 1, 0, 'intra', 'function', 0, 20, 20, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(93, 1, 'Chef d’équipe', 'Infanterie', 'Commandement', 'Team Leader', 'infantry_team_chief', 'Encadrement d’une équipe élémentaire.', 1, 0, 'intra', 'function', 0, 30, 30, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(94, 1, 'Fusilier', 'Infanterie', 'Combattant', 'Rifleman', 'infantry_rifleman', 'Combattant d’infanterie polyvalent.', 1, 0, 'intra', 'function', 0, 40, 40, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(95, 1, 'Grenadier', 'Infanterie', 'Combattant', 'Grenadier', 'infantry_grenadier', 'Appui grenades et armement lourd léger.', 1, 0, 'intra', 'function', 0, 50, 50, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(96, 1, 'Tireur d’élite', 'Infanterie', 'Combattant', 'Sharpshooter', 'infantry_sharpshooter', 'Précision renforcée et tir d’appui.', 1, 0, 'intra', 'specialty', 0, 60, 60, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(97, 1, 'Tireur de précision', 'Infanterie', 'Combattant', 'Designated Marksman', 'infantry_marksman', 'Neutralisation sélective à moyenne portée.', 1, 0, 'intra', 'specialty', 0, 70, 70, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(98, 1, 'Tireur isolé', 'Infanterie', 'Combattant', 'Sniper', 'infantry_sniper', 'Tir de précision longue portée en retrait.', 1, 0, 'intra', 'specialty', 0, 75, 75, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(99, 1, 'Mitrailleur', 'Infanterie', 'Combattant', 'Automatic Rifleman', 'infantry_machine_gunner', 'Appui feu soutenu et manœuvre d’appui.', 1, 0, 'intra', 'function', 0, 80, 80, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(100, 1, 'Opérateur radio', 'Infanterie', 'Spécialités', 'Radio Operator', 'infantry_radio_operator', 'Transmissions et liaisons tactiques.', 1, 0, 'intra', 'liaison', 0, 90, 90, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(101, 1, 'Éclaireur', 'Infanterie', 'Spécialités', 'Scout', 'infantry_scout', 'Reconnaissance et renseignement terrain.', 1, 0, 'intra', 'specialty', 0, 100, 100, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(102, 1, 'Chef binôme', 'Infanterie', 'Spécialités', 'Buddy team leader', 'infantry_team_pair_chief', 'Coordination d’un binôme au contact.', 1, 0, 'intra', 'function', 0, 110, 110, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(103, 1, 'JTAC', 'Appuis & feux', 'Coordination', 'JTAC', 'fires_jtac', 'Contrôleur d’attaques au sol.', 1, 0, 'intra', 'liaison', 0, 10, 10, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(104, 1, 'Forward Observer', 'Appuis & feux', 'Coordination', 'Forward Observer', 'fires_forward_observer', 'Observation et ajustement des tirs.', 1, 0, 'intra', 'liaison', 0, 20, 20, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(105, 1, 'Officier appuis feux', 'Appuis & feux', 'Coordination', 'Fire Support Officer', 'fires_support_officer', 'Synthèse et coordination des appuis.', 1, 0, 'intra', 'liaison', 0, 30, 30, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(106, 1, 'Chef pièce', 'Appuis & feux', 'Artillerie', 'Fire Direction Specialist', 'fires_gun_chief', 'Chef de pièce et conduite du tir.', 1, 0, 'intra', 'function', 0, 40, 40, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(107, 1, 'Servant artillerie', 'Appuis & feux', 'Artillerie', 'Artillery Crew', 'fires_gun_crew', 'Mise en œuvre et service de pièce.', 1, 0, 'intra', 'function', 0, 50, 50, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(108, 1, 'Sapeur', 'Génie', 'Combat', 'Combat Engineer', 'engineer_sapper', 'Ouverture de passages et travaux au contact.', 1, 0, 'intra', 'function', 0, 10, 10, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(109, 1, 'Démineur', 'Génie', 'Combat', 'EOD Specialist', 'engineer_eod', 'Neutralisation des dangers explosifs.', 1, 0, 'intra', 'specialty', 0, 20, 20, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(110, 1, 'Chef groupe génie', 'Génie', 'Combat', 'Engineer Squad Leader', 'engineer_group_chief', 'Encadrement d’un groupe de combat du génie.', 1, 0, 'intra', 'function', 0, 30, 30, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(111, 1, 'Technicien infrastructure', 'Génie', 'Infrastructure', 'Construction Engineer', 'engineer_infra_technician', 'Travaux d’infrastructure et ouvrages.', 1, 0, 'intra', 'support', 0, 40, 40, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(112, 1, 'Responsable travaux', 'Génie', 'Infrastructure', 'Works Supervisor', 'engineer_works_lead', 'Pilotage des chantiers et contrôle qualité.', 1, 0, 'intra', 'support', 0, 50, 50, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(113, 1, 'Conducteur militaire', 'Logistique', 'Transport', 'Motor Transport Operator', 'logistics_driver', 'Conduite et manœuvre des véhicules logistiques.', 1, 0, 'intra', 'support', 0, 10, 10, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(114, 1, 'Chef convoi', 'Logistique', 'Transport', 'Convoy Commander', 'logistics_convoy_chief', 'Responsabilité d’un convoi ou d’un détachement roulant.', 1, 0, 'intra', 'support', 0, 20, 20, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(115, 1, 'Mécanicien', 'Logistique', 'Maintenance', 'Mechanic', 'logistics_mechanic', 'Maintenance de premier et second échelon.', 1, 0, 'intra', 'support', 0, 30, 30, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(116, 1, 'Technicien maintenance', 'Logistique', 'Maintenance', 'Maintenance Technician', 'logistics_maint_technician', 'Diagnostic et réparation des systèmes.', 1, 0, 'intra', 'support', 0, 40, 40, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(117, 1, 'Responsable parc matériel', 'Logistique', 'Maintenance', 'Fleet Manager', 'logistics_fleet_manager', 'Gestion du parc et disponibilité opérationnelle.', 1, 0, 'intra', 'support', 0, 50, 50, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(118, 1, 'Médecin militaire', 'Santé', 'Médical', 'Medical Officer', 'medical_officer', 'Responsabilité médicale et décisions sanitaires.', 1, 0, 'intra', 'function', 0, 10, 10, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(119, 1, 'Infirmier militaire', 'Santé', 'Médical', 'Field Nurse', 'medical_nurse', 'Soins infirmiers et stabilisation.', 1, 0, 'intra', 'function', 0, 20, 20, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(120, 1, 'Auxiliaire sanitaire', 'Santé', 'Médical', 'Medical Assistant', 'medical_auxiliary', 'Soutien sanitaire et assistance au poste de secours.', 1, 0, 'intra', 'support', 0, 30, 30, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(121, 1, 'Secouriste', 'Santé', 'Médical', 'Combat Medic', 'medical_first_responder', 'Premiers secours et évacuation sanitaire initiale.', 1, 0, 'intra', 'support', 0, 40, 40, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(122, 1, 'Formateur', 'Instruction', 'Formation', 'Training Instructor', 'instruction_trainer', 'Conception et animation de modules pédagogiques.', 1, 0, 'intra', 'function', 0, 20, 20, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(123, 1, 'Évaluateur', 'Instruction', 'Formation', 'Evaluator', 'instruction_evaluator', 'Évaluation des compétences et des qualifications.', 1, 0, 'intra', 'function', 0, 40, 40, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(124, 1, 'Officier administratif', 'Administration', 'Gestion', 'Administrative Officer', 'admin_staff_officer', 'Courrier, dossiers et formalités administratives.', 1, 0, 'intra', 'support', 0, 20, 20, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(125, 1, 'Secrétaire unité', 'Administration', 'Gestion', 'Unit Secretary', 'admin_unit_secretary', 'Secrétariat et suivi administratif de l’unité.', 1, 0, 'intra', 'support', 0, 30, 30, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(126, 1, 'En formation', 'Statut', 'Affichage', 'In training', 'status_in_training', 'Parcours de formation en cours.', 1, 0, 'intra', 'status', 1, 20, 20, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(127, 1, 'Réserviste', 'Statut', 'Affichage', 'Reservist', 'status_reservist', 'Statut de réserve et disponibilité partielle.', 1, 0, 'intra', 'status', 1, 50, 50, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(128, 1, 'En service actif', 'Statut', 'Affichage', 'Active Duty', 'status_active_duty', 'Engagement opérationnel à plein temps.', 1, 0, 'intra', 'status', 1, 70, 70, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(129, 7, 'Chef de corps', 'État-major', 'Commandement', 'Commanding Officer', 'command_unit_commander', 'Autorité de commandement de l’unité.', 1, 0, 'intra', 'authority', 0, 10, 10, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(130, 7, 'Chef adjoint', 'État-major', 'Commandement', 'Executive Officer', 'command_executive_officer', 'Adjoint au commandement et relais opérationnel.', 1, 0, 'intra', 'authority', 0, 20, 20, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(131, 7, 'Officier supérieur', 'État-major', 'Commandement', 'Senior Officer', 'command_senior_officer', 'Encadrement supérieur et coordination générale.', 1, 0, 'intra', 'authority', 0, 30, 30, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(132, 7, 'Officier de permanence', 'État-major', 'Commandement', 'Duty Officer', 'command_duty_officer', 'Responsable de la permanence et des décisions courantes.', 1, 0, 'intra', 'function', 0, 40, 40, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(133, 7, 'Officier planification', 'État-major', 'Opérations', 'Plans Officer', 'staff_plans_officer', 'Plans, ordres et synchronisation des moyens.', 1, 0, 'intra', 'function', 0, 60, 60, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(134, 7, 'Officier conduite', 'État-major', 'Opérations', 'Battle Captain', 'staff_battle_captain', 'Conduite de la manœuvre et de la situation tactique.', 1, 0, 'intra', 'function', 0, 70, 70, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(135, 7, 'Officier coordination interarmes', 'État-major', 'Opérations', 'Joint Fires Coordinator', 'staff_joint_coordination_officer', 'Coordination des effets interarmes et appuis.', 1, 0, 'intra', 'liaison', 0, 80, 80, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(136, 7, 'Analyste renseignement', 'État-major', 'Renseignement', 'Intelligence Analyst', 'staff_intel_analyst', 'Production d’analyses et de fiches situation.', 1, 0, 'intra', 'function', 0, 100, 100, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(137, 7, 'Officier exploitation', 'État-major', 'Renseignement', 'SIGINT Specialist', 'staff_intel_exploitation', 'Exploitation technique des sources et des flux.', 1, 0, 'intra', 'specialty', 0, 110, 110, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(138, 7, 'Cellule renseignement', 'État-major', 'Renseignement', 'Intelligence Cell Operator', 'staff_intel_cell', 'Traitement et diffusion au sein de la cellule.', 1, 0, 'intra', 'function', 0, 120, 120, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(139, 7, 'Responsable soutien', 'État-major', 'Logistique', 'Supply Specialist', 'staff_sustainment_lead', 'Gestion des stocks et du soutien quotidien.', 1, 0, 'intra', 'support', 0, 140, 140, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(140, 7, 'Gestionnaire flux logistiques', 'État-major', 'Logistique', 'Motor Transport Operator', 'staff_logistics_flow_manager', 'Organisation des flux, convois et dotations.', 1, 0, 'intra', 'support', 0, 150, 150, 20, NULL, '{\"icon\":\"heroicon-o-building-library\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(141, 7, 'Chef de section', 'Infanterie', 'Commandement', 'Platoon Leader', 'infantry_section_chief', 'Encadrement d’une section au combat.', 1, 0, 'intra', 'authority', 0, 10, 10, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-rose-100 text-rose-900 ring-rose-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(142, 7, 'Chef de groupe', 'Infanterie', 'Commandement', 'Squad Leader', 'infantry_group_chief', 'Encadrement d’un groupe tactique.', 1, 0, 'intra', 'function', 0, 20, 20, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(143, 7, 'Chef d’équipe', 'Infanterie', 'Commandement', 'Team Leader', 'infantry_team_chief', 'Encadrement d’une équipe élémentaire.', 1, 0, 'intra', 'function', 0, 30, 30, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(144, 7, 'Fusilier', 'Infanterie', 'Combattant', 'Rifleman', 'infantry_rifleman', 'Combattant d’infanterie polyvalent.', 1, 0, 'intra', 'function', 0, 40, 40, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(145, 7, 'Grenadier', 'Infanterie', 'Combattant', 'Grenadier', 'infantry_grenadier', 'Appui grenades et armement lourd léger.', 1, 0, 'intra', 'function', 0, 50, 50, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(146, 7, 'Tireur d’élite', 'Infanterie', 'Combattant', 'Sharpshooter', 'infantry_sharpshooter', 'Précision renforcée et tir d’appui.', 1, 0, 'intra', 'specialty', 0, 60, 60, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(147, 7, 'Tireur de précision', 'Infanterie', 'Combattant', 'Designated Marksman', 'infantry_marksman', 'Neutralisation sélective à moyenne portée.', 1, 0, 'intra', 'specialty', 0, 70, 70, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(148, 7, 'Tireur isolé', 'Infanterie', 'Combattant', 'Sniper', 'infantry_sniper', 'Tir de précision longue portée en retrait.', 1, 0, 'intra', 'specialty', 0, 75, 75, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(149, 7, 'Mitrailleur', 'Infanterie', 'Combattant', 'Automatic Rifleman', 'infantry_machine_gunner', 'Appui feu soutenu et manœuvre d’appui.', 1, 0, 'intra', 'function', 0, 80, 80, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(150, 7, 'Opérateur radio', 'Infanterie', 'Spécialités', 'Radio Operator', 'infantry_radio_operator', 'Transmissions et liaisons tactiques.', 1, 0, 'intra', 'liaison', 0, 90, 90, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(151, 7, 'Éclaireur', 'Infanterie', 'Spécialités', 'Scout', 'infantry_scout', 'Reconnaissance et renseignement terrain.', 1, 0, 'intra', 'specialty', 0, 100, 100, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(152, 7, 'Chef binôme', 'Infanterie', 'Spécialités', 'Buddy team leader', 'infantry_team_pair_chief', 'Coordination d’un binôme au contact.', 1, 0, 'intra', 'function', 0, 110, 110, 40, NULL, '{\"icon\":\"heroicon-o-shield-check\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(153, 7, 'JTAC', 'Appuis & feux', 'Coordination', 'JTAC', 'fires_jtac', 'Contrôleur d’attaques au sol.', 1, 0, 'intra', 'liaison', 0, 10, 10, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(154, 7, 'Forward Observer', 'Appuis & feux', 'Coordination', 'Forward Observer', 'fires_forward_observer', 'Observation et ajustement des tirs.', 1, 0, 'intra', 'liaison', 0, 20, 20, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(155, 7, 'Officier appuis feux', 'Appuis & feux', 'Coordination', 'Fire Support Officer', 'fires_support_officer', 'Synthèse et coordination des appuis.', 1, 0, 'intra', 'liaison', 0, 30, 30, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-amber-100 text-amber-950 ring-amber-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(156, 7, 'Chef pièce', 'Appuis & feux', 'Artillerie', 'Fire Direction Specialist', 'fires_gun_chief', 'Chef de pièce et conduite du tir.', 1, 0, 'intra', 'function', 0, 40, 40, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(157, 7, 'Servant artillerie', 'Appuis & feux', 'Artillerie', 'Artillery Crew', 'fires_gun_crew', 'Mise en œuvre et service de pièce.', 1, 0, 'intra', 'function', 0, 50, 50, 50, NULL, '{\"icon\":\"heroicon-o-fire\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(158, 7, 'Sapeur', 'Génie', 'Combat', 'Combat Engineer', 'engineer_sapper', 'Ouverture de passages et travaux au contact.', 1, 0, 'intra', 'function', 0, 10, 10, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(159, 7, 'Démineur', 'Génie', 'Combat', 'EOD Specialist', 'engineer_eod', 'Neutralisation des dangers explosifs.', 1, 0, 'intra', 'specialty', 0, 20, 20, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-violet-100 text-violet-900 ring-violet-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(160, 7, 'Chef groupe génie', 'Génie', 'Combat', 'Engineer Squad Leader', 'engineer_group_chief', 'Encadrement d’un groupe de combat du génie.', 1, 0, 'intra', 'function', 0, 30, 30, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(161, 7, 'Technicien infrastructure', 'Génie', 'Infrastructure', 'Construction Engineer', 'engineer_infra_technician', 'Travaux d’infrastructure et ouvrages.', 1, 0, 'intra', 'support', 0, 40, 40, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(162, 7, 'Responsable travaux', 'Génie', 'Infrastructure', 'Works Supervisor', 'engineer_works_lead', 'Pilotage des chantiers et contrôle qualité.', 1, 0, 'intra', 'support', 0, 50, 50, 60, NULL, '{\"icon\":\"heroicon-o-wrench-screwdriver\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(163, 7, 'Conducteur militaire', 'Logistique', 'Transport', 'Motor Transport Operator', 'logistics_driver', 'Conduite et manœuvre des véhicules logistiques.', 1, 0, 'intra', 'support', 0, 10, 10, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(164, 7, 'Chef convoi', 'Logistique', 'Transport', 'Convoy Commander', 'logistics_convoy_chief', 'Responsabilité d’un convoi ou d’un détachement roulant.', 1, 0, 'intra', 'support', 0, 20, 20, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(165, 7, 'Mécanicien', 'Logistique', 'Maintenance', 'Mechanic', 'logistics_mechanic', 'Maintenance de premier et second échelon.', 1, 0, 'intra', 'support', 0, 30, 30, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(166, 7, 'Technicien maintenance', 'Logistique', 'Maintenance', 'Maintenance Technician', 'logistics_maint_technician', 'Diagnostic et réparation des systèmes.', 1, 0, 'intra', 'support', 0, 40, 40, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(167, 7, 'Responsable parc matériel', 'Logistique', 'Maintenance', 'Fleet Manager', 'logistics_fleet_manager', 'Gestion du parc et disponibilité opérationnelle.', 1, 0, 'intra', 'support', 0, 50, 50, 70, NULL, '{\"icon\":\"heroicon-o-truck\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(168, 7, 'Médecin militaire', 'Santé', 'Médical', 'Medical Officer', 'medical_officer', 'Responsabilité médicale et décisions sanitaires.', 1, 0, 'intra', 'function', 0, 10, 10, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(169, 7, 'Infirmier militaire', 'Santé', 'Médical', 'Field Nurse', 'medical_nurse', 'Soins infirmiers et stabilisation.', 1, 0, 'intra', 'function', 0, 20, 20, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(170, 7, 'Auxiliaire sanitaire', 'Santé', 'Médical', 'Medical Assistant', 'medical_auxiliary', 'Soutien sanitaire et assistance au poste de secours.', 1, 0, 'intra', 'support', 0, 30, 30, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(171, 7, 'Secouriste', 'Santé', 'Médical', 'Combat Medic', 'medical_first_responder', 'Premiers secours et évacuation sanitaire initiale.', 1, 0, 'intra', 'support', 0, 40, 40, 80, NULL, '{\"icon\":\"heroicon-o-heart\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(172, 7, 'Formateur', 'Instruction', 'Formation', 'Training Instructor', 'instruction_trainer', 'Conception et animation de modules pédagogiques.', 1, 0, 'intra', 'function', 0, 20, 20, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(173, 7, 'Évaluateur', 'Instruction', 'Formation', 'Evaluator', 'instruction_evaluator', 'Évaluation des compétences et des qualifications.', 1, 0, 'intra', 'function', 0, 40, 40, 90, NULL, '{\"icon\":\"heroicon-o-academic-cap\",\"tierClass\":\"bg-sky-100 text-sky-900 ring-sky-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(174, 7, 'Officier administratif', 'Administration', 'Gestion', 'Administrative Officer', 'admin_staff_officer', 'Courrier, dossiers et formalités administratives.', 1, 0, 'intra', 'support', 0, 20, 20, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(175, 7, 'Secrétaire unité', 'Administration', 'Gestion', 'Unit Secretary', 'admin_unit_secretary', 'Secrétariat et suivi administratif de l’unité.', 1, 0, 'intra', 'support', 0, 30, 30, 100, NULL, '{\"icon\":\"heroicon-o-clipboard-document-list\",\"tierClass\":\"bg-teal-100 text-teal-900 ring-teal-200\"}', 0, NULL, '2026-04-06 18:58:10'),
(176, 7, 'En formation', 'Statut', 'Affichage', 'In training', 'status_in_training', 'Parcours de formation en cours.', 1, 0, 'intra', 'status', 1, 20, 20, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(177, 7, 'Réserviste', 'Statut', 'Affichage', 'Reservist', 'status_reservist', 'Statut de réserve et disponibilité partielle.', 1, 0, 'intra', 'status', 1, 50, 50, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(178, 7, 'En service actif', 'Statut', 'Affichage', 'Active Duty', 'status_active_duty', 'Engagement opérationnel à plein temps.', 1, 0, 'intra', 'status', 1, 70, 70, 110, NULL, '{\"icon\":\"heroicon-o-tag\",\"tierClass\":\"bg-slate-200 text-slate-800 ring-slate-300\"}', 0, NULL, '2026-04-06 18:58:10'),
(179, NULL, 'Modérateur plateforme', NULL, NULL, NULL, 'site_moderator', 'Modération des contenus du brief sur l’ensemble des communautés, sans administration système ni gestion des organisations.', 1, 0, 'site', 'function', 0, 0, 0, 1, NULL, NULL, 0, NULL, '2026-04-06 20:07:27'),
(180, NULL, 'Modérateur senior plateforme', NULL, NULL, NULL, 'site_senior_moderator', 'Même périmètre que le modérateur plateforme, avec la gestion de l’arborescence des canaux forum sur toutes les communautés.', 1, 0, 'site', 'authority', 0, 0, 0, 1, NULL, NULL, 0, NULL, '2026-04-06 20:07:27'),
(181, NULL, 'Équipe assistance', NULL, NULL, NULL, 'site_support', 'Accompagnement des membres : consultation des éléments utiles au support dans le back-office, sans modération globale des canaux ni réglages système.', 1, 0, 'site', 'authority', 0, 0, 0, 1, NULL, NULL, 0, NULL, '2026-04-06 20:07:27');

-- --------------------------------------------------------

--
-- Structure de la table `role_assignments_log`
--

CREATE TABLE `role_assignments_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `action` enum('assign','revoke') NOT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_assignments_log`
--

INSERT INTO `role_assignments_log` (`id`, `tenant_id`, `user_id`, `role_id`, `action`, `assigned_by`, `assigned_at`, `revoked_at`, `reason`) VALUES
(1, 7, 8, 25, 'revoke', NULL, '2026-04-06 21:28:45', NULL, NULL),
(2, 7, 8, 41, 'assign', NULL, '2026-04-06 21:28:45', NULL, NULL),
(3, 7, 5, 73, 'assign', 5, '2026-04-10 13:32:57', NULL, NULL),
(4, 7, 5, 172, 'assign', 5, '2026-04-10 13:32:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `role_definitions`
--

CREATE TABLE `role_definitions` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name_fr` varchar(160) NOT NULL,
  `name_us` varchar(160) NOT NULL,
  `family` varchar(64) NOT NULL DEFAULT 'general',
  `description` varchar(600) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_definitions`
--

INSERT INTO `role_definitions` (`id`, `slug`, `name_fr`, `name_us`, `family`, `description`, `sort_order`, `created_at`) VALUES
(1, 'unit_manager', 'Gestionnaire d’unité', 'Unit Manager', 'command', 'Ancrage fondateur / gestion d’unité (équivalent « Fondateur » historique).', 10, '2026-04-06 10:45:04'),
(2, 'unit_commander', 'Commandant d’unité', 'Unit Commander', 'command', 'Commandement de l’unité.', 20, '2026-04-06 10:45:04'),
(3, 'unit_responsible', 'Responsable d’unité', 'Unit Lead', 'command', 'Responsabilité opérationnelle de l’unité.', 30, '2026-04-06 10:45:04'),
(4, 'platoon_leader', 'Chef de peloton', 'Platoon Leader', 'command', 'Encadrement d’un peloton.', 40, '2026-04-06 10:45:04'),
(5, 'squad_leader', 'Chef de groupe', 'Squad Leader', 'command', 'Encadrement d’une équipe / groupe.', 50, '2026-04-06 10:45:04'),
(6, 'team_leader', 'Chef d’équipe', 'Team Leader', 'command', 'Encadrement d’une équipe réduite.', 60, '2026-04-06 10:45:04'),
(7, 'section_adjutant', 'Adjoint de section', 'Section Adjutant', 'command', 'Soutien au commandement de section.', 70, '2026-04-06 10:45:04'),
(8, 'operations_officer', 'Officier opérations', 'Operations Officer (S3)', 'command', 'Planification et conduite des opérations.', 80, '2026-04-06 10:45:04'),
(9, 'executive_officer', 'Officier adjoint', 'Executive Officer (XO)', 'command', 'Adjoint au commandement.', 90, '2026-04-06 10:45:04'),
(10, 'recruiter', 'Recruteur', 'Recruiting Officer', 'hr', 'Pipeline de recrutement.', 100, '2026-04-06 10:45:04'),
(11, 'recruitment_lead', 'Responsable recrutement', 'Recruiting Lead', 'hr', 'Pilotage du recrutement.', 110, '2026-04-06 10:45:04'),
(12, 'applications_analyst', 'Analyste candidatures', 'Applications Analyst', 'hr', 'Analyse des dossiers.', 120, '2026-04-06 10:45:04'),
(13, 'selection_officer', 'Officier sélection', 'Selection Officer', 'hr', 'Décision de sélection.', 130, '2026-04-06 10:45:04'),
(14, 'integration_lead', 'Responsable intégration', 'Integration Lead', 'hr', 'Onboarding des nouveaux membres.', 140, '2026-04-06 10:45:04'),
(15, 'trainer', 'Formateur', 'Trainer', 'training', 'Animation de formation.', 200, '2026-04-06 10:45:04'),
(16, 'senior_instructor', 'Instructeur senior', 'Senior Instructor', 'training', 'Expertise pédagogique avancée.', 210, '2026-04-06 10:45:04'),
(17, 'training_officer', 'Responsable instruction', 'Training Officer', 'training', 'Pilotage des programmes.', 220, '2026-04-06 10:45:04'),
(18, 'evaluator', 'Évaluateur', 'Evaluator', 'training', 'Évaluation des compétences.', 230, '2026-04-06 10:45:04'),
(19, 'pedagogy_coordinator', 'Coordinateur pédagogique', 'Pedagogy Coordinator', 'training', 'Coordination des parcours.', 240, '2026-04-06 10:45:04'),
(20, 'certification_lead', 'Responsable certification', 'Certification Lead', 'training', 'Gestion des certifications.', 250, '2026-04-06 10:45:04'),
(21, 'super_admin', 'Super Admin', 'Super Admin', 'system', 'Plateforme (hors tenant).', 300, '2026-04-06 10:45:04'),
(22, 'system_admin', 'Admin système', 'System Admin', 'system', 'Administration plateforme.', 310, '2026-04-06 10:45:04'),
(23, 'tech_admin', 'Admin technique', 'Technical Admin', 'system', 'Infrastructure et intégration.', 320, '2026-04-06 10:45:04'),
(24, 'security_admin', 'Admin sécurité', 'Security Admin', 'system', 'Sécurité et accès.', 330, '2026-04-06 10:45:04'),
(25, 'rbac_manager', 'Gestionnaire RBAC', 'RBAC Manager', 'system', 'Gouvernance des rôles et permissions.', 340, '2026-04-06 10:45:04'),
(26, 'logistics_lead', 'Responsable logistique', 'Logistics Officer (S4)', 'support', 'Logistique générale.', 400, '2026-04-06 10:45:04'),
(27, 'equipment_manager', 'Gestionnaire matériel', 'Equipment Manager', 'support', 'Suivi du matériel.', 410, '2026-04-06 10:45:04'),
(28, 'fleet_lead', 'Responsable parc', 'Fleet Lead', 'support', 'Parc véhicules / équipements lourds.', 420, '2026-04-06 10:45:04'),
(29, 'mission_coordinator', 'Coordinateur missions', 'Mission Coordinator', 'support', 'Coordination des missions.', 430, '2026-04-06 10:45:04'),
(30, 'comms_lead', 'Responsable communication', 'Communications Lead', 'comms', 'Stratégie de communication.', 500, '2026-04-06 10:45:04'),
(31, 'global_moderator', 'Modérateur global', 'Global Moderator', 'comms', 'Modération transverse.', 510, '2026-04-06 10:45:04'),
(32, 'unit_moderator', 'Modérateur unité', 'Unit Moderator', 'comms', 'Modération au sein d’une unité.', 520, '2026-04-06 10:45:04'),
(33, 'content_analyst', 'Analyste contenu', 'Content Analyst', 'comms', 'Qualité et analyse du contenu.', 530, '2026-04-06 10:45:04'),
(34, 'intel_officer', 'Officier renseignement', 'Intelligence Officer (S2)', 'support', 'Renseignement et synthèse.', 440, '2026-04-06 10:45:04'),
(35, 'first_sergeant', 'Sous-officier référent', 'First Sergeant', 'command', 'Encadrement et discipline.', 95, '2026-04-06 10:45:04');

-- --------------------------------------------------------

--
-- Structure de la table `role_definition_relations`
--

CREATE TABLE `role_definition_relations` (
  `id` int(10) UNSIGNED NOT NULL,
  `from_definition_id` int(10) UNSIGNED NOT NULL,
  `to_definition_id` int(10) UNSIGNED NOT NULL,
  `relation_type` varchar(32) NOT NULL DEFAULT 'reports_to'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_definition_relations`
--

INSERT INTO `role_definition_relations` (`id`, `from_definition_id`, `to_definition_id`, `relation_type`) VALUES
(3, 3, 2, 'reports_to'),
(2, 4, 3, 'reports_to'),
(1, 5, 4, 'reports_to'),
(5, 8, 2, 'reports_to'),
(4, 9, 2, 'reports_to'),
(9, 10, 3, 'cross_cutting'),
(6, 10, 11, 'reports_to'),
(8, 15, 5, 'independent'),
(7, 15, 17, 'reports_to');

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(15, 1),
(21, 1),
(30, 1),
(32, 1),
(33, 1),
(34, 1),
(36, 1),
(42, 1),
(79, 1),
(80, 1),
(81, 1),
(82, 1),
(83, 1),
(84, 1),
(85, 1),
(86, 1),
(87, 1),
(88, 1),
(89, 1),
(90, 1),
(91, 1),
(92, 1),
(93, 1),
(94, 1),
(95, 1),
(96, 1),
(97, 1),
(98, 1),
(99, 1),
(100, 1),
(101, 1),
(102, 1),
(103, 1),
(104, 1),
(105, 1),
(106, 1),
(107, 1),
(108, 1),
(109, 1),
(110, 1),
(111, 1),
(112, 1),
(113, 1),
(114, 1),
(115, 1),
(116, 1),
(117, 1),
(118, 1),
(119, 1),
(120, 1),
(121, 1),
(122, 1),
(123, 1),
(124, 1),
(125, 1),
(126, 1),
(127, 1),
(128, 1),
(1, 2),
(2, 2),
(3, 2),
(4, 2),
(15, 2),
(21, 2),
(30, 2),
(32, 2),
(34, 2),
(42, 2),
(79, 2),
(80, 2),
(81, 2),
(82, 2),
(83, 2),
(84, 2),
(85, 2),
(86, 2),
(87, 2),
(88, 2),
(89, 2),
(90, 2),
(91, 2),
(92, 2),
(93, 2),
(94, 2),
(95, 2),
(96, 2),
(97, 2),
(98, 2),
(99, 2),
(100, 2),
(101, 2),
(102, 2),
(103, 2),
(104, 2),
(105, 2),
(106, 2),
(107, 2),
(108, 2),
(109, 2),
(110, 2),
(111, 2),
(112, 2),
(113, 2),
(114, 2),
(115, 2),
(116, 2),
(117, 2),
(122, 2),
(123, 2),
(124, 2),
(125, 2),
(126, 2),
(127, 2),
(128, 2),
(1, 3),
(2, 3),
(3, 3),
(4, 3),
(15, 3),
(21, 3),
(30, 3),
(32, 3),
(33, 3),
(34, 3),
(36, 3),
(42, 3),
(79, 3),
(80, 3),
(81, 3),
(82, 3),
(83, 3),
(84, 3),
(85, 3),
(86, 3),
(87, 3),
(88, 3),
(89, 3),
(90, 3),
(91, 3),
(92, 3),
(93, 3),
(94, 3),
(95, 3),
(96, 3),
(97, 3),
(98, 3),
(99, 3),
(100, 3),
(101, 3),
(102, 3),
(103, 3),
(104, 3),
(105, 3),
(106, 3),
(107, 3),
(108, 3),
(109, 3),
(110, 3),
(111, 3),
(112, 3),
(113, 3),
(114, 3),
(115, 3),
(116, 3),
(117, 3),
(118, 3),
(119, 3),
(120, 3),
(121, 3),
(122, 3),
(123, 3),
(124, 3),
(125, 3),
(126, 3),
(127, 3),
(128, 3),
(1, 4),
(2, 4),
(3, 4),
(4, 4),
(15, 4),
(32, 4),
(42, 4),
(79, 4),
(80, 4),
(81, 4),
(82, 4),
(83, 4),
(84, 4),
(85, 4),
(86, 4),
(87, 4),
(88, 4),
(91, 4),
(92, 4),
(93, 4),
(94, 4),
(95, 4),
(96, 4),
(97, 4),
(98, 4),
(99, 4),
(100, 4),
(101, 4),
(102, 4),
(103, 4),
(104, 4),
(105, 4),
(106, 4),
(107, 4),
(108, 4),
(109, 4),
(110, 4),
(111, 4),
(112, 4),
(113, 4),
(114, 4),
(122, 4),
(123, 4),
(124, 4),
(125, 4),
(126, 4),
(127, 4),
(128, 4),
(1, 5),
(2, 5),
(4, 5),
(15, 5),
(32, 5),
(79, 5),
(80, 5),
(81, 5),
(82, 5),
(83, 5),
(84, 5),
(85, 5),
(86, 5),
(91, 5),
(92, 5),
(93, 5),
(103, 5),
(105, 5),
(110, 5),
(112, 5),
(114, 5),
(122, 5),
(123, 5),
(124, 5),
(1, 6),
(2, 6),
(15, 6),
(1, 7),
(2, 7),
(15, 7),
(1, 8),
(15, 8),
(1, 9),
(3, 9),
(4, 9),
(15, 9),
(30, 9),
(32, 9),
(33, 9),
(34, 9),
(79, 9),
(80, 9),
(81, 9),
(82, 9),
(83, 9),
(84, 9),
(85, 9),
(86, 9),
(87, 9),
(88, 9),
(89, 9),
(90, 9),
(91, 9),
(92, 9),
(93, 9),
(94, 9),
(95, 9),
(96, 9),
(97, 9),
(98, 9),
(99, 9),
(101, 9),
(102, 9),
(103, 9),
(104, 9),
(105, 9),
(106, 9),
(107, 9),
(108, 9),
(109, 9),
(110, 9),
(111, 9),
(112, 9),
(113, 9),
(114, 9),
(115, 9),
(116, 9),
(117, 9),
(118, 9),
(119, 9),
(120, 9),
(121, 9),
(122, 9),
(123, 9),
(124, 9),
(125, 9),
(126, 9),
(127, 9),
(128, 9),
(1, 10),
(4, 10),
(15, 10),
(34, 10),
(79, 10),
(80, 10),
(81, 10),
(82, 10),
(83, 10),
(84, 10),
(85, 10),
(86, 10),
(89, 10),
(90, 10),
(91, 10),
(92, 10),
(93, 10),
(103, 10),
(105, 10),
(110, 10),
(112, 10),
(114, 10),
(115, 10),
(116, 10),
(117, 10),
(124, 10),
(1, 11),
(4, 11),
(15, 11),
(79, 11),
(80, 11),
(81, 11),
(82, 11),
(83, 11),
(84, 11),
(85, 11),
(86, 11),
(91, 11),
(92, 11),
(93, 11),
(103, 11),
(105, 11),
(110, 11),
(112, 11),
(114, 11),
(124, 11),
(1, 12),
(15, 12),
(1, 13),
(15, 13),
(1, 14),
(4, 14),
(15, 14),
(30, 14),
(32, 14),
(79, 14),
(80, 14),
(81, 14),
(82, 14),
(83, 14),
(84, 14),
(85, 14),
(86, 14),
(91, 14),
(92, 14),
(93, 14),
(103, 14),
(105, 14),
(110, 14),
(112, 14),
(114, 14),
(122, 14),
(123, 14),
(124, 14),
(1, 15),
(15, 15),
(1, 16),
(15, 16),
(32, 16),
(122, 16),
(123, 16),
(1, 17),
(15, 17),
(1, 18),
(15, 18),
(1, 19),
(3, 19),
(15, 19),
(87, 19),
(88, 19),
(94, 19),
(95, 19),
(96, 19),
(97, 19),
(98, 19),
(99, 19),
(101, 19),
(102, 19),
(104, 19),
(106, 19),
(107, 19),
(108, 19),
(109, 19),
(111, 19),
(113, 19),
(125, 19),
(126, 19),
(127, 19),
(128, 19),
(1, 20),
(15, 20),
(1, 21),
(15, 21),
(1, 22),
(15, 22),
(14, 23),
(14, 24),
(14, 25),
(1, 26),
(2, 26),
(15, 26),
(1, 45),
(15, 45),
(21, 45),
(30, 45),
(1, 46),
(15, 46),
(1, 47),
(15, 47),
(21, 47),
(30, 47),
(1, 48),
(15, 48),
(1, 49),
(15, 49),
(1, 50),
(15, 50),
(1, 51),
(15, 51),
(1, 52),
(15, 52),
(1, 53),
(15, 53),
(1, 54),
(15, 54),
(1, 55),
(15, 55),
(1, 56),
(15, 56),
(1, 57),
(2, 57),
(15, 57),
(1, 58),
(2, 58),
(15, 58),
(1, 59),
(2, 59),
(15, 59),
(1, 60),
(2, 60),
(15, 60),
(1, 61),
(2, 61),
(15, 61),
(1, 62),
(2, 62),
(15, 62),
(1, 63),
(2, 63),
(15, 63),
(1, 64),
(2, 64),
(15, 64),
(1, 65),
(2, 65),
(15, 65),
(1, 66),
(2, 66),
(15, 66),
(1, 67),
(15, 67),
(1, 68),
(4, 68),
(15, 68),
(32, 68),
(33, 68),
(79, 68),
(80, 68),
(81, 68),
(82, 68),
(83, 68),
(84, 68),
(85, 68),
(86, 68),
(91, 68),
(92, 68),
(93, 68),
(103, 68),
(105, 68),
(110, 68),
(112, 68),
(114, 68),
(118, 68),
(119, 68),
(120, 68),
(121, 68),
(122, 68),
(123, 68),
(124, 68),
(1, 69),
(15, 69),
(1, 70),
(15, 70),
(34, 70),
(89, 70),
(90, 70),
(115, 70),
(116, 70),
(117, 70),
(1, 71),
(15, 71),
(1, 72),
(15, 72),
(1, 73),
(15, 73),
(1, 74),
(15, 74),
(1, 75),
(15, 75),
(1, 76),
(15, 76),
(1, 77),
(15, 77),
(1, 78),
(15, 78),
(1, 79),
(15, 79),
(1, 80),
(15, 80),
(32, 80),
(122, 80),
(123, 80),
(1, 81),
(15, 81),
(32, 81),
(122, 81),
(123, 81),
(1, 82),
(15, 82),
(1, 83),
(15, 83),
(1, 84),
(15, 84),
(1, 85),
(4, 85),
(15, 85),
(21, 85),
(30, 85),
(32, 85),
(33, 85),
(79, 85),
(80, 85),
(81, 85),
(82, 85),
(83, 85),
(84, 85),
(85, 85),
(86, 85),
(91, 85),
(92, 85),
(93, 85),
(103, 85),
(105, 85),
(110, 85),
(112, 85),
(114, 85),
(118, 85),
(119, 85),
(120, 85),
(121, 85),
(122, 85),
(123, 85),
(124, 85),
(1, 86),
(15, 86),
(30, 86),
(1, 87),
(15, 87),
(33, 87),
(118, 87),
(119, 87),
(120, 87),
(121, 87),
(1, 88),
(15, 88),
(1, 89),
(15, 89),
(1, 90),
(15, 90),
(1, 91),
(15, 91),
(1, 92),
(15, 92),
(1, 93),
(15, 93),
(42, 93),
(100, 93),
(1, 94),
(15, 94),
(1, 95),
(15, 95),
(1, 96),
(15, 96),
(1, 97),
(15, 97),
(1, 98),
(15, 98),
(22, 99),
(23, 99),
(22, 100),
(23, 100),
(24, 100),
(25, 100),
(26, 100),
(27, 100),
(28, 100),
(29, 100),
(37, 100),
(38, 100),
(39, 100),
(40, 100),
(41, 100),
(129, 100),
(130, 100),
(131, 100),
(132, 100),
(133, 100),
(134, 100),
(135, 100),
(136, 100),
(137, 100),
(138, 100),
(139, 100),
(140, 100),
(141, 100),
(142, 100),
(143, 100),
(144, 100),
(145, 100),
(146, 100),
(147, 100),
(148, 100),
(149, 100),
(150, 100),
(151, 100),
(152, 100),
(153, 100),
(154, 100),
(155, 100),
(156, 100),
(157, 100),
(158, 100),
(159, 100),
(160, 100),
(161, 100),
(162, 100),
(163, 100),
(164, 100),
(165, 100),
(166, 100),
(167, 100),
(168, 100),
(169, 100),
(170, 100),
(171, 100),
(172, 100),
(173, 100),
(174, 100),
(175, 100),
(176, 100),
(177, 100),
(178, 100),
(22, 101),
(23, 101),
(24, 101),
(25, 101),
(26, 101),
(27, 101),
(29, 101),
(37, 101),
(39, 101),
(40, 101),
(129, 101),
(130, 101),
(131, 101),
(132, 101),
(133, 101),
(134, 101),
(135, 101),
(136, 101),
(137, 101),
(138, 101),
(139, 101),
(140, 101),
(141, 101),
(142, 101),
(143, 101),
(144, 101),
(145, 101),
(146, 101),
(147, 101),
(148, 101),
(149, 101),
(150, 101),
(151, 101),
(152, 101),
(153, 101),
(154, 101),
(155, 101),
(156, 101),
(157, 101),
(158, 101),
(159, 101),
(160, 101),
(161, 101),
(162, 101),
(163, 101),
(164, 101),
(165, 101),
(166, 101),
(167, 101),
(172, 101),
(173, 101),
(174, 101),
(175, 101),
(176, 101),
(177, 101),
(178, 101),
(22, 102),
(23, 102),
(24, 102),
(25, 102),
(26, 102),
(27, 102),
(29, 102),
(37, 102),
(38, 102),
(39, 102),
(40, 102),
(41, 102),
(129, 102),
(130, 102),
(131, 102),
(132, 102),
(133, 102),
(134, 102),
(135, 102),
(136, 102),
(137, 102),
(138, 102),
(139, 102),
(140, 102),
(141, 102),
(142, 102),
(143, 102),
(144, 102),
(145, 102),
(146, 102),
(147, 102),
(148, 102),
(149, 102),
(150, 102),
(151, 102),
(152, 102),
(153, 102),
(154, 102),
(155, 102),
(156, 102),
(157, 102),
(158, 102),
(159, 102),
(160, 102),
(161, 102),
(162, 102),
(163, 102),
(164, 102),
(165, 102),
(166, 102),
(167, 102),
(168, 102),
(169, 102),
(170, 102),
(171, 102),
(172, 102),
(173, 102),
(174, 102),
(175, 102),
(176, 102),
(177, 102),
(178, 102),
(22, 103),
(23, 103),
(24, 103),
(25, 103),
(26, 103),
(37, 103),
(40, 103),
(129, 103),
(130, 103),
(131, 103),
(132, 103),
(133, 103),
(134, 103),
(135, 103),
(136, 103),
(137, 103),
(138, 103),
(141, 103),
(142, 103),
(143, 103),
(144, 103),
(145, 103),
(146, 103),
(147, 103),
(148, 103),
(149, 103),
(150, 103),
(151, 103),
(152, 103),
(153, 103),
(154, 103),
(155, 103),
(156, 103),
(157, 103),
(158, 103),
(159, 103),
(160, 103),
(161, 103),
(162, 103),
(163, 103),
(164, 103),
(172, 103),
(173, 103),
(174, 103),
(175, 103),
(176, 103),
(177, 103),
(178, 103),
(22, 104),
(23, 104),
(24, 104),
(26, 104),
(37, 104),
(129, 104),
(130, 104),
(131, 104),
(132, 104),
(133, 104),
(134, 104),
(135, 104),
(136, 104),
(141, 104),
(142, 104),
(143, 104),
(153, 104),
(155, 104),
(160, 104),
(162, 104),
(164, 104),
(172, 104),
(173, 104),
(174, 104),
(22, 105),
(23, 105),
(24, 105),
(22, 106),
(23, 106),
(24, 106),
(22, 107),
(23, 107),
(24, 107),
(22, 108),
(23, 108),
(25, 108),
(26, 108),
(27, 108),
(37, 108),
(38, 108),
(39, 108),
(129, 108),
(130, 108),
(131, 108),
(132, 108),
(133, 108),
(134, 108),
(135, 108),
(136, 108),
(137, 108),
(138, 108),
(139, 108),
(140, 108),
(141, 108),
(142, 108),
(143, 108),
(144, 108),
(145, 108),
(146, 108),
(147, 108),
(148, 108),
(149, 108),
(151, 108),
(152, 108),
(153, 108),
(154, 108),
(155, 108),
(156, 108),
(157, 108),
(158, 108),
(159, 108),
(160, 108),
(161, 108),
(162, 108),
(163, 108),
(164, 108),
(165, 108),
(166, 108),
(167, 108),
(168, 108),
(169, 108),
(170, 108),
(171, 108),
(172, 108),
(173, 108),
(174, 108),
(175, 108),
(176, 108),
(177, 108),
(178, 108),
(22, 109),
(23, 109),
(26, 109),
(39, 109),
(129, 109),
(130, 109),
(131, 109),
(132, 109),
(133, 109),
(134, 109),
(135, 109),
(136, 109),
(139, 109),
(140, 109),
(141, 109),
(142, 109),
(143, 109),
(153, 109),
(155, 109),
(160, 109),
(162, 109),
(164, 109),
(165, 109),
(166, 109),
(167, 109),
(174, 109),
(22, 110),
(23, 110),
(26, 110),
(129, 110),
(130, 110),
(131, 110),
(132, 110),
(133, 110),
(134, 110),
(135, 110),
(136, 110),
(141, 110),
(142, 110),
(143, 110),
(153, 110),
(155, 110),
(160, 110),
(162, 110),
(164, 110),
(174, 110),
(22, 111),
(23, 111),
(22, 112),
(23, 112),
(22, 113),
(23, 113),
(22, 114),
(23, 114),
(26, 114),
(27, 114),
(37, 114),
(129, 114),
(130, 114),
(131, 114),
(132, 114),
(133, 114),
(134, 114),
(135, 114),
(136, 114),
(141, 114),
(142, 114),
(143, 114),
(153, 114),
(155, 114),
(160, 114),
(162, 114),
(164, 114),
(172, 114),
(173, 114),
(174, 114),
(22, 115),
(23, 115),
(22, 116),
(23, 116),
(37, 116),
(172, 116),
(173, 116),
(22, 117),
(23, 117),
(22, 118),
(23, 118),
(27, 118),
(29, 118),
(22, 119),
(23, 119),
(22, 120),
(23, 120),
(22, 121),
(23, 121),
(22, 122),
(23, 122),
(22, 123),
(23, 123),
(22, 124),
(23, 124),
(22, 125),
(23, 125),
(22, 126),
(23, 126),
(22, 127),
(23, 127),
(22, 128),
(23, 128),
(27, 128),
(29, 128),
(22, 129),
(23, 129),
(24, 129),
(22, 130),
(23, 130),
(24, 130),
(22, 131),
(23, 131),
(24, 131),
(22, 132),
(23, 132),
(24, 132),
(22, 133),
(23, 133),
(24, 133),
(22, 134),
(23, 134),
(24, 134),
(22, 135),
(23, 135),
(24, 135),
(22, 136),
(23, 136),
(24, 136),
(22, 137),
(23, 137),
(24, 137),
(22, 138),
(23, 138),
(24, 138),
(22, 139),
(23, 139),
(22, 140),
(23, 140),
(26, 140),
(37, 140),
(38, 140),
(129, 140),
(130, 140),
(131, 140),
(132, 140),
(133, 140),
(134, 140),
(135, 140),
(136, 140),
(141, 140),
(142, 140),
(143, 140),
(153, 140),
(155, 140),
(160, 140),
(162, 140),
(164, 140),
(168, 140),
(169, 140),
(170, 140),
(171, 140),
(172, 140),
(173, 140),
(174, 140),
(22, 141),
(23, 141),
(22, 142),
(23, 142),
(39, 142),
(139, 142),
(140, 142),
(165, 142),
(166, 142),
(167, 142),
(22, 143),
(23, 143),
(22, 144),
(23, 144),
(22, 145),
(23, 145),
(22, 146),
(23, 146),
(22, 147),
(23, 147),
(22, 148),
(23, 148),
(22, 149),
(23, 149),
(22, 150),
(23, 150),
(22, 151),
(23, 151),
(22, 152),
(23, 152),
(37, 152),
(172, 152),
(173, 152),
(22, 153),
(23, 153),
(37, 153),
(172, 153),
(173, 153),
(22, 154),
(23, 154),
(22, 155),
(23, 155),
(22, 156),
(23, 156),
(22, 157),
(23, 157),
(26, 157),
(27, 157),
(29, 157),
(37, 157),
(38, 157),
(129, 157),
(130, 157),
(131, 157),
(132, 157),
(133, 157),
(134, 157),
(135, 157),
(136, 157),
(141, 157),
(142, 157),
(143, 157),
(153, 157),
(155, 157),
(160, 157),
(162, 157),
(164, 157),
(168, 157),
(169, 157),
(170, 157),
(171, 157),
(172, 157),
(173, 157),
(174, 157),
(22, 158),
(23, 158),
(27, 158),
(22, 159),
(23, 159),
(38, 159),
(168, 159),
(169, 159),
(170, 159),
(171, 159),
(22, 160),
(23, 160),
(22, 161),
(23, 161),
(22, 162),
(23, 162),
(22, 163),
(23, 163),
(22, 164),
(23, 164),
(22, 165),
(23, 165),
(40, 165),
(150, 165),
(22, 166),
(23, 166),
(22, 167),
(23, 167),
(22, 168),
(23, 168),
(22, 169),
(23, 169),
(22, 170),
(23, 170),
(22, 171),
(23, 171),
(22, 172),
(23, 172),
(22, 173),
(23, 173),
(22, 174),
(23, 174),
(1, 175),
(4, 175),
(15, 175),
(30, 175),
(32, 175),
(79, 175),
(80, 175),
(81, 175),
(82, 175),
(83, 175),
(84, 175),
(85, 175),
(86, 175),
(91, 175),
(92, 175),
(93, 175),
(103, 175),
(105, 175),
(110, 175),
(112, 175),
(114, 175),
(122, 175),
(123, 175),
(124, 175),
(22, 176),
(23, 176),
(26, 176),
(27, 176),
(37, 176),
(129, 176),
(130, 176),
(131, 176),
(132, 176),
(133, 176),
(134, 176),
(135, 176),
(136, 176),
(141, 176),
(142, 176),
(143, 176),
(153, 176),
(155, 176),
(160, 176),
(162, 176),
(164, 176),
(172, 176),
(173, 176),
(174, 176),
(1, 177),
(15, 177),
(1, 178),
(15, 178),
(1, 179),
(2, 179),
(4, 179),
(15, 179),
(79, 179),
(80, 179),
(81, 179),
(82, 179),
(83, 179),
(84, 179),
(85, 179),
(86, 179),
(91, 179),
(92, 179),
(93, 179),
(103, 179),
(105, 179),
(110, 179),
(112, 179),
(114, 179),
(124, 179),
(22, 180),
(23, 180),
(22, 181),
(23, 181),
(22, 182),
(23, 182),
(24, 182),
(26, 182),
(129, 182),
(130, 182),
(131, 182),
(132, 182),
(133, 182),
(134, 182),
(135, 182),
(136, 182),
(141, 182),
(142, 182),
(143, 182),
(153, 182),
(155, 182),
(160, 182),
(162, 182),
(164, 182),
(174, 182),
(1, 183),
(15, 183),
(1, 184),
(15, 184),
(1, 185),
(15, 185),
(1, 186),
(15, 186),
(1, 187),
(15, 187),
(22, 188),
(23, 188),
(22, 189),
(23, 189),
(22, 190),
(23, 190),
(22, 191),
(23, 191),
(22, 192),
(23, 192),
(179, 193),
(180, 193),
(180, 194),
(181, 195),
(1, 196),
(4, 196),
(15, 196),
(1, 197),
(15, 197),
(1, 198),
(15, 198),
(1, 199),
(4, 199),
(15, 199),
(1, 200),
(15, 200),
(1, 201),
(15, 201),
(1, 202),
(15, 202),
(1, 203),
(4, 203),
(15, 203),
(1, 204),
(4, 204),
(15, 204),
(1, 205),
(15, 205),
(1, 206),
(15, 206),
(1, 207),
(15, 207),
(1, 208),
(15, 208),
(1, 209),
(15, 209),
(1, 210),
(15, 210),
(1, 211),
(15, 211),
(1, 212),
(15, 212),
(1, 213),
(15, 213),
(1, 214),
(15, 214),
(22, 215),
(23, 215),
(26, 215),
(22, 216),
(23, 216),
(22, 217),
(23, 217),
(22, 218),
(23, 218),
(26, 218),
(22, 219),
(23, 219),
(22, 220),
(23, 220),
(22, 221),
(23, 221),
(22, 222),
(23, 222),
(26, 222),
(22, 223),
(23, 223),
(26, 223),
(22, 224),
(23, 224),
(22, 225),
(23, 225),
(22, 226),
(23, 226),
(22, 227),
(23, 227),
(22, 228),
(23, 228),
(22, 229),
(23, 229),
(22, 230),
(23, 230),
(22, 231),
(23, 231),
(22, 232),
(23, 232),
(22, 233),
(23, 233),
(1, 234),
(15, 234),
(22, 235),
(23, 235),
(1, 236),
(15, 236),
(22, 237),
(23, 237),
(1, 238),
(15, 238),
(1, 239),
(15, 239),
(22, 240),
(23, 240),
(22, 241),
(23, 241),
(1, 242),
(15, 242),
(42, 242),
(1, 243),
(15, 243),
(1, 244),
(15, 244),
(42, 244),
(1, 245),
(15, 245),
(42, 245),
(22, 246),
(23, 246),
(40, 246),
(22, 247),
(23, 247),
(22, 248),
(23, 248),
(40, 248),
(22, 249),
(23, 249),
(40, 249);

-- --------------------------------------------------------

--
-- Structure de la table `role_relations`
--

CREATE TABLE `role_relations` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `from_role_id` int(10) UNSIGNED NOT NULL,
  `to_role_id` int(10) UNSIGNED NOT NULL,
  `relation_type` varchar(32) NOT NULL DEFAULT 'reports_to',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `role_requirements`
--

CREATE TABLE `role_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `required_module_id` int(10) UNSIGNED DEFAULT NULL,
  `required_certification_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `role_sets`
--

CREATE TABLE `role_sets` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_sets`
--

INSERT INTO `role_sets` (`id`, `tenant_id`, `name`, `description`, `created_at`) VALUES
(1, 1, 'État-major opérations', 'Pack : opérations, renseignement et logistique.', '2026-04-06 18:33:03'),
(2, 7, 'État-major opérations', 'Pack : opérations, renseignement et logistique.', '2026-04-06 18:33:03');

-- --------------------------------------------------------

--
-- Structure de la table `role_set_roles`
--

CREATE TABLE `role_set_roles` (
  `role_set_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_set_roles`
--

INSERT INTO `role_set_roles` (`role_set_id`, `role_id`) VALUES
(1, 44),
(1, 46),
(1, 47),
(2, 62),
(2, 64),
(2, 65);

-- --------------------------------------------------------

--
-- Structure de la table `security_events`
--

CREATE TABLE `security_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `meta_json` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `payload` text NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `site_role_assignments`
--

CREATE TABLE `site_role_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `email_normalized` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `assigned_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `site_role_assignments`
--

INSERT INTO `site_role_assignments` (`id`, `email_normalized`, `role_id`, `assigned_by_user_id`, `created_at`, `revoked_at`) VALUES
(1, 'tetard.tanguy@gmail.com', 14, NULL, '2026-04-04 16:09:10', NULL),
(2, 'tanguy.inc@gmail.com', 180, 5, '2026-04-09 11:01:09', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `site_settings`
--

INSERT INTO `site_settings` (`id`, `tenant_id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 7, 'forum_hero_image_url', 'https://www.crimson.eu/images/domaines-applications/defense/cerbere2.jpg', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(2, 7, 'forum_enabled', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(3, 7, 'forum_guest_read', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(4, 7, 'forum_role_read_label', 'Lecture seule uniquement', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(5, 7, 'forum_role_write_label', '', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(6, 7, 'forum_topics_per_page', '20', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(7, 7, 'forum_posts_per_page', '20', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(8, 7, 'forum_cooldown_seconds', '0', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(9, 7, 'forum_antispam_enabled', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(10, 7, 'forum_antispam_min_length', '10', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(11, 7, 'forum_sandbox_enabled', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(12, 7, 'forum_bot_enabled', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(13, 7, 'forum_attachments_max_size', '0', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(14, 7, 'forum_attachments_allowed_ext', '', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(15, 7, 'forum_url_gate_enabled', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(16, 7, 'forum_notify_moderators', '1', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(17, 7, 'forum_moderation_tutorial_html', '', '2026-04-06 17:10:47', '2026-04-06 18:18:08'),
(18, 7, 'forum_name', 'Salle de brief', '2026-04-06 17:27:32', '2026-04-06 18:18:08'),
(19, 7, 'forum_subtitle', 'COMSPEC · Athena', '2026-04-06 17:27:32', '2026-04-06 18:18:08'),
(20, 7, 'forum_tagline', 'Ici, les ordres et les retours d\'opération circulent. Briefs, comptes-rendus et annonces au cœur de la communauté.', '2026-04-06 17:27:32', '2026-04-06 18:18:08'),
(21, 7, 'forum_context', 'Centre des transmissions · Communauté', '2026-04-06 17:27:32', '2026-04-06 18:18:08'),
(72, 7, 'forum_max_post_length', '10000', '2026-04-06 18:18:08', '2026-04-06 18:18:08');

-- --------------------------------------------------------

--
-- Structure de la table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `features_json` text DEFAULT NULL,
  `limits_json` text DEFAULT NULL COMMENT 'Quotas gratuit limité (JSON)',
  `stripe_price_id_monthly` varchar(100) DEFAULT NULL,
  `stripe_price_id_yearly` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `slug`, `name`, `sort_order`, `features_json`, `limits_json`, `stripe_price_id_monthly`, `stripe_price_id_yearly`, `created_at`) VALUES
(1, 'free', 'Gratuit', 10, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":false,\"max_members\":50,\"community_create\":true}', '{\"quotas\":{\"events\":{\"limit\":3,\"reset_period\":\"monthly\",\"soft_block_threshold\":0.8,\"soft_block_message\":\"Vous approchez de la limite de cr\\u00e9ations d\\u2019\\u00e9v\\u00e9nements ce mois-ci.\",\"upgrade_cta\":\"platform\\/upgrade\",\"binds_feature\":\"events\"}}}', NULL, NULL, '2026-04-04 14:27:08'),
(2, 'standard', 'Standard', 20, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":true,\"max_members\":200,\"community_create\":true,\"events\":true}', NULL, NULL, NULL, '2026-04-04 14:27:08'),
(3, 'pro', 'Pro', 30, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":true,\"analytics\":true,\"events\":true,\"max_members\":2000,\"community_create\":true}', NULL, NULL, NULL, '2026-04-04 14:27:08');

-- --------------------------------------------------------

--
-- Structure de la table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `community_code` varchar(64) DEFAULT NULL COMMENT 'Code court unique (MAJUSCULES/tirets) pour rejoindre la communauté',
  `logo_url` varchar(500) DEFAULT NULL,
  `settings` text DEFAULT NULL,
  `owner_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Utilisateur propriétaire créateur',
  `plan_slug` varchar(50) NOT NULL DEFAULT 'free',
  `stripe_customer_id` varchar(100) DEFAULT NULL,
  `stripe_subscription_id` varchar(100) DEFAULT NULL,
  `subscription_status` varchar(32) NOT NULL DEFAULT 'none',
  `subscription_current_period_end` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `default_timezone` varchar(64) DEFAULT 'Europe/Paris',
  `default_locale` char(5) DEFAULT 'fr-FR',
  `country_code` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `community_code`, `logo_url`, `settings`, `owner_user_id`, `plan_slug`, `stripe_customer_id`, `stripe_subscription_id`, `subscription_status`, `subscription_current_period_end`, `created_at`, `updated_at`, `default_timezone`, `default_locale`, `country_code`) VALUES
(1, 'Aucune organisation', 'default', NULL, NULL, NULL, NULL, 'free', NULL, NULL, 'none', NULL, '2026-03-13 17:47:31', '2026-03-13 17:47:31', 'Europe/Paris', 'fr-FR', NULL),
(7, 'ATHENA', 'athena-sys', 'ATHENA-SYS', 'https://athena.ttrd.fr/public/uploads/community-wizard/3/logo_1a0c4d6535b4040a.png', '{\"community\":{\"registration_mode\":\"simple\",\"community_locked\":false,\"require_ai_ack\":true,\"welcome_text\":\"Concerne l\'administration et la modération système\",\"public_page_layout\":\"showcase\",\"public_hero_subtitle\":\"Gestionnaire plateforme\",\"default_locale\":\"fr\",\"orbat_visibility\":\"public\",\"default_guest_role_slug\":\"invite\",\"presentation_mode\":\"simple\",\"style_badges\":[],\"simple_body\":\"Encadrement dynamique de la plateforme et de l\'administration technique\",\"expectations\":\"7/7 - 24-24\",\"enlistment_milsim\":{\"logo_letter\":\"F\",\"portal_title\":\"e\",\"portal_subtitle\":\"Infrastructure sécurisée — Athena COMSPEC\",\"preamble_title\":\"Accès Contrôlé\",\"preamble_lead\":\"Vous allez accéder à l’interface de candidature. Ce formulaire constitue un dossier d’évaluation préalable.\",\"preamble_status_lines\":[\"Vérification de session : conforme\",\"Canal de transmission : sécurisé\",\"Journalisation des accès : active\"],\"preamble_cta\":\"Accéder au Formulaire\",\"preamble_footer\":\"La poursuite vaut prise de connaissance des conditions de traitement des données.\",\"nav_brand\":\"Athena\",\"session_block_title\":\"Statut Session\",\"ref_label\":\"Réf.\",\"security_label\":\"Encrypted\",\"progress_prefix\":\"FORMULAIRE :\",\"roe_title\":\"Règles d\'Engagement (ROE)\",\"roe_items\":[\"Réponses détaillées obligatoires.\",\"Microphone de qualité requis.\",\"Disponibilité mercredi et samedi soir attendue.\",\"Ne pas relancer l\'état-major après soumission.\"],\"watermark\":\"OLYMPUS\",\"doc_control\":\"Document Control\",\"queue_label\":\"File d\'attente active\",\"candidate_prefix\":\"Candidature\",\"classified_badge\":\"CLASSIFIED\",\"op_note_title\":\"Note Opérationnelle\",\"op_col1\":\"Toute soumission est examinée par la cellule de recrutement.\",\"op_ai_warning\":\"L\'utilisation de l\'IA est strictement interdite.\",\"op_col2\":\"Les candidats retenus seront contactés directement.\",\"archive_note\":\"Chaque réponse incomplète ou assistée par IA entraîne l\'archivage du dossier.\",\"section_0\":\"Mode de candidature\",\"section_1\":\"Section I — Cadre administratif & contact\",\"section_2\":\"Section II — Matériel & expérience de jeu\",\"section_3\":\"Section III — Motivation & intention\",\"section_4\":\"Section IV — Engagement\",\"commitment_q13\":\"13 Je comprends l\'investissement temps/effort requis\",\"availability_q15\":\"15 Disponible mercredi & samedi soir\",\"ai_checkbox\":\"20 Je confirme l\'absence d\'IA dans ce rapport\",\"submit_button\":\"Soumettre au Commandement\",\"submit_footer\":\"Transmission sécurisée\",\"fields\":{\"full_name\":{\"label\":\"01 Nom & Prénom (identité dossier)\",\"placeholder\":\"ex: Jonathan King\",\"widget\":\"text\",\"options\":[]},\"legal_full_name\":{\"label\":\"Contact IRL (si personnage RP)\",\"placeholder\":\"Nom légal pour recontact — optionnel si déjà indiqué ailleurs\",\"widget\":\"text\",\"options\":[]},\"age\":{\"label\":\"02 Âge\",\"placeholder\":\"Âge minimum requis\",\"widget\":\"text\",\"options\":[]},\"timezone\":{\"label\":\"03 Fuseau Horaire\",\"placeholder\":\"ex: Paris (UTC+1)\",\"widget\":\"text\",\"options\":[]},\"weekly_availability\":{\"label\":\"04 Disponibilités Hebdomadaires\",\"placeholder\":\"Jours de la semaine\",\"widget\":\"text\",\"options\":[]},\"email\":{\"label\":\"Email (obligatoire)\",\"placeholder\":\"email@exemple.fr\",\"widget\":\"text\",\"options\":[]},\"callsign\":{\"label\":\"Indicatif / callsign (optionnel)\",\"placeholder\":\"ex: Ghost-2-1\",\"widget\":\"text\",\"options\":[]},\"system_config\":{\"label\":\"05 Configuration (CPU/GPU/RAM)\",\"placeholder\":\"Configuration système\",\"widget\":\"text\",\"options\":[]},\"microphone_quality\":{\"label\":\"06 Microphone de Haute Qualité ?\",\"placeholder\":\"\",\"widget\":\"yesno\",\"options\":[\"Oui\",\"Non\"]},\"past_milsim_experience\":{\"label\":\"07 Expériences MilSim Passées\",\"placeholder\":\"Unités, rôles, durées...\",\"widget\":\"textarea\",\"options\":[]},\"ace_acre_level\":{\"label\":\"08 Maîtrise ACE / ACRE\",\"placeholder\":\"\",\"widget\":\"select\",\"options\":[\"Aucune\",\"Basique\",\"Expérimenté\",\"Avancé\"]},\"motivation_why_join\":{\"label\":\"09 Pourquoi rejoindre ?\",\"placeholder\":\"Motivation, engagement...\",\"widget\":\"textarea\",\"options\":[]},\"motivation_accountability\":{\"label\":\"10 Qu\'est-ce que l\'Accountability ?\",\"placeholder\":\"Responsabilité individuelle dans une unité...\",\"widget\":\"textarea\",\"options\":[]}}},\"registry_listed\":true,\"forum_members_only\":false,\"game_label\":\"\",\"main_mods\":\"\",\"modpack_size_gb\":null,\"military_sections\":[],\"registry_tags\":[],\"contact_discord_url\":\"\",\"contact_email\":\"\",\"contact_form_enabled\":false,\"contact_intro\":\"\",\"public_audience\":\"unit\",\"public_doctrine\":\"\",\"public_access_label\":\"\",\"public_mission\":\"\",\"public_region_badges\":[],\"public_specialties\":[],\"public_stats_mode\":\"manual\",\"public_stats_manual\":{\"effectif\":\"\",\"unites\":\"\",\"activite_percent\":\"\",\"theatre\":\"\"},\"public_command_chain\":[],\"public_roster_enabled\":true,\"public_recruitment_badge_open\":true,\"public_modules\":{\"forum\":false,\"documents\":true,\"events\":true,\"roster\":false,\"training\":true,\"analytics\":false},\"member_can_choose_display_role\":1,\"display_badges_mode\":\"primary_only\"},\"founder_trial_ends_at\":\"2026-05-05T09:10:02+00:00\",\"grade_system_code\":\"FR_CLASSIC\",\"timezone\":\"Europe/Paris\",\"onboarding_wizard_version\":2,\"onboarding_completed_at\":\"2026-04-05T09:10:02+00:00\"}', 5, 'free', NULL, NULL, 'none', NULL, '2026-04-05 09:10:01', '2026-04-08 10:44:59', 'Europe/Paris', 'fr-FR', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `tenant_alerts`
--

CREATE TABLE `tenant_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `kind` enum('discount','novelty','info','urgent') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `cta_label` varchar(120) DEFAULT NULL,
  `cta_url` varchar(512) DEFAULT NULL,
  `coupon_code` varchar(64) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_api_keys`
--

CREATE TABLE `tenant_api_keys` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `key_prefix` varchar(16) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `scopes_json` text DEFAULT NULL,
  `quota_per_day` int(10) UNSIGNED NOT NULL DEFAULT 10000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_api_key_daily_usage`
--

CREATE TABLE `tenant_api_key_daily_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `api_key_id` int(10) UNSIGNED NOT NULL,
  `usage_day` date NOT NULL,
  `request_count` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_atak_config`
--

CREATE TABLE `tenant_atak_config` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `node_url` varchar(500) DEFAULT NULL,
  `jwt_secret` varchar(255) DEFAULT NULL,
  `arma_server_host` varchar(255) DEFAULT NULL,
  `arma_server_port` smallint(5) UNSIGNED DEFAULT NULL,
  `arma_mod_credentials` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `default_map_slug` varchar(50) DEFAULT 'altis',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `tenant_atak_config`
--

INSERT INTO `tenant_atak_config` (`tenant_id`, `node_url`, `jwt_secret`, `arma_server_host`, `arma_server_port`, `arma_mod_credentials`, `instructions`, `default_map_slug`, `created_at`, `updated_at`) VALUES
(1, 'http://athena.ttrd.fr:3001', 'Tt05032001_TETARD', '88.166.72.92', 2302, '', '', 'altis', '2026-03-14 10:15:18', '2026-03-14 10:55:59'),
(7, 'https://athena.ttrd.fr', 'Tt05032001_TETARD', 'tetard.tanguy@gmail.com', NULL, '', '', 'altis', '2026-04-05 09:41:39', '2026-04-05 09:41:39');

-- --------------------------------------------------------

--
-- Structure de la table `tenant_branding`
--

CREATE TABLE `tenant_branding` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `primary_color` char(7) DEFAULT NULL COMMENT '#RRGGBB',
  `accent_color` char(7) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `public_home_hero_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`public_home_hero_json`)),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tenant_branding`
--

INSERT INTO `tenant_branding` (`tenant_id`, `logo_url`, `banner_url`, `primary_color`, `accent_color`, `favicon_url`, `public_home_hero_json`, `updated_at`) VALUES
(7, 'https://athena.ttrd.fr/public/uploads/community-wizard/3/logo_1a0c4d6535b4040a.png', NULL, NULL, NULL, NULL, NULL, '2026-04-05 09:11:07');

-- --------------------------------------------------------

--
-- Structure de la table `tenant_community_feed`
--

CREATE TABLE `tenant_community_feed` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link_url` varchar(512) DEFAULT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tenant_community_feed`
--

INSERT INTO `tenant_community_feed` (`id`, `tenant_id`, `category`, `title`, `body`, `link_url`, `actor_user_id`, `created_at`) VALUES
(1, 7, 'training_course_completed', 'Parcours terminé — Parcours portail — Bien utiliser le site', 'NewPI a validé l’ensemble des exigences de cette formation.', 'https://athena.ttrd.fr/public/formations/parcours-portail', 5, '2026-04-06 19:36:17');

-- --------------------------------------------------------

--
-- Structure de la table `tenant_dashboard_pins`
--

CREATE TABLE `tenant_dashboard_pins` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `pin_type` enum('document_category','document','courrier_document','external_url','notice') NOT NULL,
  `document_category_id` int(10) UNSIGNED DEFAULT NULL,
  `document_id` int(10) UNSIGNED DEFAULT NULL,
  `courrier_document_id` int(10) UNSIGNED DEFAULT NULL,
  `external_url` varchar(2000) DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `notice_body` mediumtext DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_grade_overrides`
--

CREATE TABLE `tenant_grade_overrides` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `grade_id` bigint(20) UNSIGNED NOT NULL,
  `label_short_override` varchar(100) DEFAULT NULL,
  `label_long_override` varchar(150) DEFAULT NULL,
  `sort_order_override` int(11) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_matricule_config`
--

CREATE TABLE `tenant_matricule_config` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(20) DEFAULT '',
  `format_pattern` varchar(80) NOT NULL DEFAULT '{prefix}-{seq}',
  `next_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `tenant_matricule_config`
--

INSERT INTO `tenant_matricule_config` (`tenant_id`, `prefix`, `format_pattern`, `next_number`, `updated_at`) VALUES
(1, 'ATH', '{prefix}-{seq:5}', 2, '2026-03-13 19:23:21'),
(7, 'ATH', '{prefix}-{seq:5}', 3, '2026-04-06 17:46:35');

-- --------------------------------------------------------

--
-- Structure de la table `tenant_messages`
--

CREATE TABLE `tenant_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `thread_id` int(10) UNSIGNED NOT NULL,
  `sender_user_id` int(10) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_message_threads`
--

CREATE TABLE `tenant_message_threads` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_message_thread_users`
--

CREATE TABLE `tenant_message_thread_users` (
  `thread_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `last_read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_modules`
--

CREATE TABLE `tenant_modules` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `custom_order` int(11) DEFAULT NULL,
  `recurrence_override_type` enum('NONE','PERIODIC','EVENT_BASED') DEFAULT NULL,
  `recurrence_override_days` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_module_entitlements`
--

CREATE TABLE `tenant_module_entitlements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `module_key` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config_schema_version` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config_json`)),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_quotas`
--

CREATE TABLE `tenant_quotas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `metric_key` varchar(64) NOT NULL,
  `limit_value` bigint(20) NOT NULL DEFAULT 0,
  `period` enum('none','day','month') NOT NULL DEFAULT 'none',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_security_policy`
--

CREATE TABLE `tenant_security_policy` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `password_min_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 12,
  `session_idle_timeout_minutes` int(10) UNSIGNED NOT NULL DEFAULT 480,
  `lockout_max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 8,
  `require_email_verified_for_enlistment` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_training_logs`
--

CREATE TABLE `tenant_training_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_role_id` int(10) UNSIGNED DEFAULT NULL,
  `event_scope` enum('FRAMEWORK','TENANT_MODULE','RECURRENCE','ROLE_REQUIREMENT','CERTIFICATION') NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `old_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_payload`)),
  `new_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_payload`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_usage_counters`
--

CREATE TABLE `tenant_usage_counters` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `metric_key` varchar(64) NOT NULL,
  `period_start` date NOT NULL,
  `amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tenant_user_roles`
--

CREATE TABLE `tenant_user_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `org_unit_id` int(10) UNSIGNED DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `co_unit_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Miroir IFNULL(org_unit_id,0) pour unicité — maintenu par triggers (MariaDB sans GENERATED sur org_unit_id)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tenant_user_roles`
--

INSERT INTO `tenant_user_roles` (`id`, `tenant_id`, `user_id`, `role_id`, `org_unit_id`, `valid_from`, `valid_until`, `metadata`, `created_at`, `co_unit_id`) VALUES
(1, 1, 3, 15, NULL, NULL, NULL, NULL, '2026-04-05 11:59:36', 0),
(2, 1, 7, 3, NULL, NULL, NULL, NULL, '2026-04-05 11:59:36', 0),
(130, 7, 8, 41, NULL, NULL, NULL, NULL, '2026-04-10 13:32:29', 0),
(131, 7, 5, 22, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(132, 7, 5, 23, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(133, 7, 5, 29, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(134, 7, 5, 24, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(135, 7, 5, 25, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(136, 7, 5, 26, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(137, 7, 5, 27, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(138, 7, 5, 28, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(139, 7, 5, 37, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(140, 7, 5, 38, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(141, 7, 5, 39, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(142, 7, 5, 40, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(143, 7, 5, 41, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(144, 7, 5, 73, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0),
(145, 7, 5, 172, NULL, NULL, NULL, NULL, '2026-04-10 13:32:57', 0);

--
-- Déclencheurs `tenant_user_roles`
--
DELIMITER $$
CREATE TRIGGER `tur_co_unit_bi` BEFORE INSERT ON `tenant_user_roles` FOR EACH ROW SET NEW.co_unit_id = IFNULL(NEW.org_unit_id, 0)
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tur_co_unit_bu` BEFORE UPDATE ON `tenant_user_roles` FOR EACH ROW SET NEW.co_unit_id = IFNULL(NEW.org_unit_id, 0)
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `trainer_validation_logs`
--

CREATE TABLE `trainer_validation_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `instructor_user_id` int(10) UNSIGNED NOT NULL,
  `target_user_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `evaluation_id` int(10) UNSIGNED DEFAULT NULL,
  `user_progress_id` int(10) UNSIGNED DEFAULT NULL,
  `action_type` enum('VALIDATION_GRANTED','VALIDATION_REJECTED','FIELD_OBSERVATION','SCORING_OVERRIDE','RECERTIFICATION_REQUIRED') NOT NULL,
  `status_before` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `status_after` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `score_before` decimal(5,2) DEFAULT NULL,
  `score_after` decimal(5,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `observation_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`observation_payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_audit_log`
--

CREATE TABLE `training_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(100) NOT NULL,
  `target_id` bigint(20) UNSIGNED NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_audit_log`
--

INSERT INTO `training_audit_log` (`id`, `tenant_id`, `user_id`, `action`, `target_type`, `target_id`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 7, 5, 'course_created', 'training_course', 1, NULL, '{\"title\":\"Introduction à l\'unité\",\"slug\":\"introduction-a-l-unite\",\"visibility\":\"draft\"}', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 16:49:15'),
(2, 7, 5, 'course_updated', 'training_course', 1, '{\"title\":\"Introduction à l\'unité\",\"slug\":\"introduction-a-l-unite\",\"visibility\":\"draft\"}', '{\"title\":\"Introduction à l\'unité\",\"slug\":\"introduction-a-l-unite\",\"visibility\":\"draft\"}', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 21:43:30'),
(3, 7, 5, 'course_updated', 'training_course', 1, NULL, '{\"module_created\":1}', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 21:43:41'),
(4, 7, 5, 'course_updated', 'training_course', 1, NULL, '{\"module_created\":2}', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 21:56:50'),
(5, 7, 5, 'enrollment_assigned', 'training_enrollment', 1, NULL, '{\"user_id\":5,\"course_id\":1,\"assignment_type\":\"self_enroll\"}', '2a01:e0a:8ee:2720:14c:15c3:b6f6:9e3f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 22:01:39'),
(6, 7, 5, 'enrollment_assigned', 'training_enrollment', 2, NULL, '{\"user_id\":5,\"course_id\":3,\"assignment_type\":\"self_enroll\",\"motivation_provided\":false}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 10:47:11'),
(7, 7, 5, 'lesson_completed', 'training_progress', 2, NULL, '{\"lesson_id\":8}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 10:59:49'),
(8, 7, 5, 'enrollment_assigned', 'training_enrollment', 3, NULL, '{\"user_id\":5,\"course_id\":5,\"assignment_type\":\"self_enroll\",\"motivation_provided\":false}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 11:16:04'),
(9, 7, 5, 'course_created', 'training_course', 6, NULL, '{\"title\":\"Introduction au LMS\",\"slug\":\"introduction-au-lms\",\"visibility\":\"draft\"}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 12:23:01'),
(10, 7, 5, 'course_updated', 'training_course', 6, NULL, '{\"enrollment_share_code_regenerated\":true}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 12:23:09'),
(11, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":18}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:05:53'),
(12, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":30}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:05:56'),
(13, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":31}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:06:04'),
(14, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":32}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:06:07'),
(15, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":29}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:06:19'),
(16, 7, 5, 'quiz_attempt_submitted', 'training_quiz_attempt', 4, NULL, '{\"score\":\"100.00\",\"passed\":1}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:06:51'),
(17, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":19}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:08:32'),
(18, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":20}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:08:57'),
(19, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":33}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:09:03'),
(20, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":34}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:09:22'),
(21, 7, 5, 'quiz_attempt_submitted', 'training_quiz_attempt', 5, NULL, '{\"score\":\"100.00\",\"passed\":1}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:31:09'),
(22, 7, 5, 'quiz_attempt_submitted', 'training_quiz_attempt', 6, NULL, '{\"score\":\"100.00\",\"passed\":1}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:32:02'),
(23, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":21}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:35:20'),
(24, 7, 5, 'lesson_completed', 'training_progress', 3, NULL, '{\"lesson_id\":22}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:36:17'),
(25, 7, 5, 'certificate_issued', 'training_certificate', 1, NULL, '{\"certificate_number\":\"ATH-7-2026-00001\",\"enrollment_id\":3}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 19:52:31'),
(26, 7, 5, 'course_created', 'training_course', 7, NULL, '{\"title\":\"Installer Task Force Radio sur Arma 3\",\"slug\":\"installer-task-force-radio-arma3\",\"imported\":true}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 20:11:27'),
(27, 7, 5, 'enrollment_assigned', 'training_enrollment', 4, NULL, '{\"user_id\":5,\"course_id\":7,\"assignment_type\":\"self_enroll\",\"motivation_provided\":false,\"status\":\"assigned\"}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 20:13:37'),
(28, 7, 5, 'course_updated', 'training_course', 7, '{\"title\":\"Installer Task Force Radio sur Arma 3\",\"slug\":\"installer-task-force-radio-arma3\",\"visibility\":\"published\"}', '{\"title\":\"Installer Task Force Radio sur Arma 3\",\"slug\":\"installer-task-force-radio-arma3\",\"visibility\":\"published\"}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 20:16:57'),
(29, 7, 5, 'lesson_completed', 'training_progress', 4, NULL, '{\"lesson_id\":35}', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-06 20:40:37'),
(30, 7, 5, 'lesson_completed', 'training_progress', 4, NULL, '{\"lesson_id\":36}', '2a01:e0a:8ee:2720:a8fc:7222:8fa0:df85', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-07 18:21:05');

-- --------------------------------------------------------

--
-- Structure de la table `training_certificates`
--

CREATE TABLE `training_certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `issued_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `certificate_number` varchar(100) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `final_score` decimal(6,2) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `status` enum('valid','expired','revoked') DEFAULT 'valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_certificates`
--

INSERT INTO `training_certificates` (`id`, `tenant_id`, `enrollment_id`, `issued_by_user_id`, `certificate_number`, `issued_at`, `expires_at`, `final_score`, `pdf_path`, `status`) VALUES
(1, 7, 3, NULL, 'ATH-7-2026-00001', '2026-04-06 19:52:31', NULL, 100.00, NULL, 'valid');

-- --------------------------------------------------------

--
-- Structure de la table `training_certificate_templates`
--

CREATE TABLE `training_certificate_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT 'Modèle par défaut',
  `headline` varchar(255) NOT NULL DEFAULT 'Attestation de formation',
  `subtitle` varchar(255) DEFAULT NULL,
  `footer_legal` text DEFAULT NULL,
  `primary_hex` varchar(7) NOT NULL DEFAULT '#0f172a',
  `accent_hex` varchar(7) NOT NULL DEFAULT '#059669',
  `logo_relative_path` varchar(500) DEFAULT NULL,
  `background_relative_path` varchar(500) DEFAULT NULL,
  `layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_json`)),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `training_certificate_templates`
--

INSERT INTO `training_certificate_templates` (`id`, `tenant_id`, `name`, `headline`, `subtitle`, `footer_legal`, `primary_hex`, `accent_hex`, `logo_relative_path`, `background_relative_path`, `layout_json`, `updated_at`, `created_at`) VALUES
(1, 7, 'Modèle par défaut', 'Attestation de formation', NULL, NULL, '#0f172a', '#059669', NULL, 'storage/app/training-certificate-assets/7/fond-883d3a06407b60c1.png', NULL, '2026-04-10 13:27:16', '2026-04-10 13:27:16');

-- --------------------------------------------------------

--
-- Structure de la table `training_competency_matrices`
--

CREATE TABLE `training_competency_matrices` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `auto_detect_rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`auto_detect_rules_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_competency_matrix_assignments`
--

CREATE TABLE `training_competency_matrix_assignments` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `matrix_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `assigned_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `source` enum('manual','auto_detect') NOT NULL DEFAULT 'manual',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_courses`
--

CREATE TABLE `training_courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `lms_scope` enum('tenant','platform') NOT NULL DEFAULT 'tenant',
  `uuid` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `course_code` varchar(32) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `learning_objectives` longtext DEFAULT NULL,
  `theme_json` longtext DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `banner_path` varchar(255) DEFAULT NULL,
  `showcase_cycle_date` date DEFAULT NULL,
  `showcase_location` varchar(255) DEFAULT NULL,
  `showcase_badge` varchar(32) DEFAULT 'open',
  `showcase_card_style` varchar(32) DEFAULT 'default',
  `showcase_sort_order` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `level` enum('initiation','intermediaire','avance','expert') DEFAULT 'initiation',
  `language_code` varchar(10) DEFAULT 'fr',
  `estimated_minutes` int(10) UNSIGNED DEFAULT 0,
  `passing_score` decimal(5,2) DEFAULT 80.00,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `is_certifying` tinyint(1) DEFAULT 0,
  `validity_days` int(10) UNSIGNED DEFAULT NULL,
  `visibility` enum('draft','private','published','archived') DEFAULT 'draft',
  `created_by` int(10) UNSIGNED NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `enrollment_policy_json` longtext DEFAULT NULL,
  `instruction_audio_url` varchar(512) DEFAULT NULL,
  `instruction_audio_instructor_optional` tinyint(1) NOT NULL DEFAULT 1,
  `instruction_audio_notes` varchar(500) DEFAULT NULL,
  `enrollment_share_code` varchar(20) DEFAULT NULL,
  `lms_created_with_version` varchar(32) DEFAULT NULL,
  `lms_last_saved_with_version` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_courses`
--

INSERT INTO `training_courses` (`id`, `tenant_id`, `lms_scope`, `uuid`, `title`, `slug`, `course_code`, `short_description`, `description`, `learning_objectives`, `theme_json`, `thumbnail_path`, `banner_path`, `showcase_cycle_date`, `showcase_location`, `showcase_badge`, `showcase_card_style`, `showcase_sort_order`, `category`, `level`, `language_code`, `estimated_minutes`, `passing_score`, `is_mandatory`, `is_certifying`, `validity_days`, `visibility`, `created_by`, `updated_by`, `created_at`, `updated_at`, `enrollment_policy_json`, `instruction_audio_url`, `instruction_audio_instructor_optional`, `instruction_audio_notes`, `enrollment_share_code`, `lms_created_with_version`, `lms_last_saved_with_version`) VALUES
(4, 1, 'tenant', '56f57a31-c578-4d86-bffe1a9e892c', 'Parcours portail — Bien utiliser le site', 'parcours-portail', 'PORTAIL-101', 'Parcours structuré : finalité du portail, navigation et compte, contenus et formations, communauté, validation.', 'Ce parcours d’accueil fixe le socle commun pour utiliser le portail de votre communauté de manière correcte et prévisible. Il ne remplace ni le règlement intérieur ni les consignes d’emploi de votre unité : il précise où vit l’information sur le site, comment la retrouver sans perdre de temps, et quels gestes minimaux protègent votre compte et celui des autres.\n\nLa progression suit une montée en puissance : finalité du portail, repérage après connexion, actions sur le compte, lieux où l’information stable coexiste avec la coordination vivante, logique des formations et de la progression enregistrée, règles de vie collective (forum, événements), puis validation par questionnaires. Vous y verrez des situations types, des erreurs fréquentes et des procédures pas à pas lorsque c’est utile.\n\nLe ton est institutionnel et concret. Prenez le temps de lire les encadrés de vigilance et les synthèses de fin de module. Un bilan interrogé à mi-parcours ancre les acquis des trois premiers blocs ; un questionnaire final porte sur l’ensemble du parcours. En cas d’échec, les explications affichées servent de plan de révision avant une nouvelle tentative.', 'Comprendre la finalité du portail : information stabilisée, coordination, suivi pédagogique — et ce qu’il ne remplace pas\nSe repérer après connexion : tableau de bord, menu, zone Opérations selon les droits, multi-communautés\nAgir sur son compte : profil, préférences, sécurité, contact à jour\nSavoir où vit l’information : dossier personnel, organigramme, documents de référence, catalogue des formations\nComprendre la logique LMS : progression réelle, obligation, attestation, reprise de parcours\nAdopter les règles de vie collective : forum, annonces, événements, signalements, présence\nRéussir le bilan à mi-parcours puis le questionnaire final, et distinguer validation de parcours et habilitation métier', '{\"accent\":\"#0d9488\",\"accentRgb\":\"13, 148, 136\",\"font\":\"\'IBM Plex Sans\', system-ui, sans-serif\",\"radius\":\"1.25rem\",\"variant\":\"default\"}', NULL, NULL, NULL, NULL, 'open', 'default', 1, 'Portail', 'initiation', 'fr', 152, 80.00, 1, 1, NULL, 'published', 3, 3, '2026-04-06 11:14:44', '2026-04-13 11:05:26', '{}', NULL, 1, NULL, NULL, NULL, NULL),
(5, 7, 'tenant', 'bba229dc-fe11-46b7-a25ec28eedcb', 'Parcours portail — Bien utiliser le site', 'parcours-portail', 'PORTAIL-101', 'Parcours structuré : finalité du portail, navigation et compte, contenus et formations, communauté, validation.', 'Ce parcours d’accueil fixe le socle commun pour utiliser le portail de votre communauté de manière correcte et prévisible. Il ne remplace ni le règlement intérieur ni les consignes d’emploi de votre unité : il précise où vit l’information sur le site, comment la retrouver sans perdre de temps, et quels gestes minimaux protègent votre compte et celui des autres.\n\nLa progression suit une montée en puissance : finalité du portail, repérage après connexion, actions sur le compte, lieux où l’information stable coexiste avec la coordination vivante, logique des formations et de la progression enregistrée, règles de vie collective (forum, événements), puis validation par questionnaires. Vous y verrez des situations types, des erreurs fréquentes et des procédures pas à pas lorsque c’est utile.\n\nLe ton est institutionnel et concret. Prenez le temps de lire les encadrés de vigilance et les synthèses de fin de module. Un bilan interrogé à mi-parcours ancre les acquis des trois premiers blocs ; un questionnaire final porte sur l’ensemble du parcours. En cas d’échec, les explications affichées servent de plan de révision avant une nouvelle tentative.', 'Comprendre la finalité du portail : information stabilisée, coordination, suivi pédagogique — et ce qu’il ne remplace pas\nSe repérer après connexion : tableau de bord, menu, zone Opérations selon les droits, multi-communautés\nAgir sur son compte : profil, préférences, sécurité, contact à jour\nSavoir où vit l’information : dossier personnel, organigramme, documents de référence, catalogue des formations\nComprendre la logique LMS : progression réelle, obligation, attestation, reprise de parcours\nAdopter les règles de vie collective : forum, annonces, événements, signalements, présence\nRéussir le bilan à mi-parcours puis le questionnaire final, et distinguer validation de parcours et habilitation métier', '{\"accent\":\"#0d9488\",\"accentRgb\":\"13, 148, 136\",\"font\":\"\'IBM Plex Sans\', system-ui, sans-serif\",\"radius\":\"1.25rem\",\"variant\":\"default\"}', NULL, NULL, NULL, NULL, 'open', 'default', 1, 'Portail', 'initiation', 'fr', 152, 80.00, 1, 1, NULL, 'published', 5, 5, '2026-04-06 11:14:44', '2026-04-13 11:05:26', '{}', NULL, 1, NULL, NULL, NULL, NULL),
(6, 7, 'tenant', '1cf853ef-601b-4729-9bee-17452be1553f', 'Introduction au LMS', 'introduction-au-lms', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'open', 'default', NULL, NULL, 'initiation', 'fr', 0, 80.00, 0, 0, NULL, 'draft', 5, 5, '2026-04-06 12:23:01', '2026-04-06 12:23:09', NULL, NULL, 1, NULL, 'MWYHFAV64V', '1.0.0', '1.0.0'),
(7, 7, 'platform', '69c67f45-3207-41c3-8506-79f6075611b2', 'Installer Task Force Radio sur Arma 3', 'installer-task-force-radio-arma3', NULL, NULL, NULL, NULL, '{\"accent\":\"#0b019d\",\"accentRgb\":\"11, 1, 157\",\"font\":\"Inter, system-ui, sans-serif\",\"radius\":\"0.5rem\",\"variant\":\"soft\"}', 'https://i.redd.it/olaj2hud66d51.jpg', 'https://i.redd.it/olaj2hud66d51.jpg', NULL, NULL, 'required', 'default', 10, NULL, 'initiation', 'fr', 0, 80.00, 0, 0, NULL, 'published', 5, 5, '2026-04-06 20:11:27', '2026-04-06 21:27:25', '{\"enrollments_blocked\":false,\"self_enroll_allowed\":true,\"self_enroll_requires_approval\":false,\"comments_enabled\":true,\"enrollment_approver_user_ids\":[],\"prerequisite_course_ids\":[],\"require_certificate_from_course_ids\":[],\"required_role_ids\":[],\"required_grade_ids\":[],\"required_user_statuses\":[]}', NULL, 1, NULL, 'LSKGZ97F47', '1.1.1', '1.2.0');

-- --------------------------------------------------------

--
-- Structure de la table `training_course_comments`
--

CREATE TABLE `training_course_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('visible','hidden') NOT NULL DEFAULT 'visible',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `training_course_comments`
--

INSERT INTO `training_course_comments` (`id`, `tenant_id`, `course_id`, `user_id`, `parent_id`, `body`, `status`, `created_at`) VALUES
(1, 7, 5, 5, NULL, 'Je sais, la formation est un peu vide mais c\'est un premier jet pour le fonctionnement du LMS.', 'visible', '2026-04-06 17:16:51');

-- --------------------------------------------------------

--
-- Structure de la table `training_course_favorites`
--

CREATE TABLE `training_course_favorites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_course_likes`
--

CREATE TABLE `training_course_likes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_course_questions`
--

CREATE TABLE `training_course_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `answer_text` text DEFAULT NULL,
  `answered_by` int(10) UNSIGNED DEFAULT NULL,
  `answered_at` datetime DEFAULT NULL,
  `status` enum('open','answered','hidden') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_course_reviews`
--

CREATE TABLE `training_course_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `kind` enum('rating','review') NOT NULL DEFAULT 'rating',
  `status` enum('pending','published','hidden') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_course_sessions`
--

CREATE TABLE `training_course_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_seats` int(10) UNSIGNED DEFAULT NULL,
  `instructor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `audio_briefing_url` varchar(512) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `training_course_sessions`
--

INSERT INTO `training_course_sessions` (`id`, `tenant_id`, `course_id`, `starts_at`, `ends_at`, `label`, `location`, `max_seats`, `instructor_user_id`, `audio_briefing_url`, `notes`, `created_at`) VALUES
(1, 7, 7, '2026-04-07 22:17:00', '2026-04-07 22:17:00', NULL, 'Discord', 10, 1, NULL, NULL, '2026-04-06 20:17:42');

-- --------------------------------------------------------

--
-- Structure de la table `training_enrollments`
--

CREATE TABLE `training_enrollments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assignment_type` enum('manual','role','unit','campaign','self_enroll') DEFAULT 'manual',
  `status` enum('assigned','in_progress','completed','failed','expired','revoked','pending_approval','withdrawn') NOT NULL DEFAULT 'assigned',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `motivation_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_enrollments`
--

INSERT INTO `training_enrollments` (`id`, `tenant_id`, `course_id`, `user_id`, `assigned_by`, `assignment_type`, `status`, `assigned_at`, `started_at`, `completed_at`, `expires_at`, `motivation_text`) VALUES
(3, 7, 5, 5, 5, 'self_enroll', 'completed', '2026-04-06 11:16:04', '2026-04-06 11:16:04', '2026-04-06 19:36:17', NULL, NULL),
(4, 7, 7, 5, 5, 'self_enroll', 'in_progress', '2026-04-06 20:13:37', '2026-04-06 20:13:37', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `training_lessons`
--

CREATE TABLE `training_lessons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `learning_objectives` text DEFAULT NULL,
  `instructor_notes` text DEFAULT NULL,
  `lesson_type` enum('richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas','quiz','modals','video_embed','video_integrated','slideshow') NOT NULL DEFAULT 'richtext',
  `content` longtext DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `duration_minutes` int(10) UNSIGNED DEFAULT 0,
  `difficulty` varchar(20) DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_lessons`
--

INSERT INTO `training_lessons` (`id`, `module_id`, `title`, `summary`, `learning_objectives`, `instructor_notes`, `lesson_type`, `content`, `external_url`, `duration_minutes`, `difficulty`, `position`, `is_required`, `created_at`, `updated_at`) VALUES
(13, 13, 'Vue d’ensemble — parcours visuel', 'Rôle du portail, déroulé pédagogique, méthode de travail, sécurité du compte, liens vers l’aide.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[{\"id\":\"onb-sec\",\"title\":\"Rappels sécurité\",\"body\":\"<ul><li><strong>Mot de passe :</strong> gardez-le pour vous ; changez-le si vous pensez qu’il a pu être vu par une autre personne.</li><li><strong>Ordinateur partagé :</strong> déconnectez-vous du portail quand vous avez terminé.</li><li><strong>Adresse e-mail :</strong> si vous la modifiez, suivez les étapes de confirmation affichées sur le site.</li><li><strong>Contenus sensibles :</strong> ne les copiez pas sur des canaux personnels ; restez dans les espaces prévus par votre organisation.</li></ul>\"}],\"opening\":{\"eyebrow\":\"Parcours d’accueil\",\"title\":\"\",\"lead\":\"Ce module pose le cadre : à quoi sert le portail, comment lire ce parcours, et quels réflexes de sécurité garder en tête.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~26 min\"},{\"label\":\"Format\",\"value\":\"Parcours visuel\"},{\"label\":\"Objectif\",\"value\":\"Finalité + risques + sécurité\"}]},\"closure\":{\"title\":\"Synthèse — Vue d’ensemble\",\"seen\":[\"Finalité institutionnelle : information stable, coordination vivante, suivi pédagogique — avec des lieux distincts sur le site.\",\"Ce que le portail n’est pas : ni substitut à la chaîne de commandement, ni dépôt anarchique des notes officielles sur le forum.\",\"Erreurs fréquentes (forum = tout, panne imaginaire, session laissée ouverte) et comment les corriger.\"],\"acquired\":[\"Vous savez réagir de façon raisonnable si une rubrique manque : contexte, rôle, puis demande au staff.\",\"Vous distinguez référence documentaire et discussion ; vous connaissez les gestes de sécurité du compte.\"],\"nextHint\":\"Enchaînez avec le module « Navigation et compte » : tableau de bord, menus, profil, préférences et multi-communautés.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Bienvenue sur le portail\",\"subtitle\":\"Formation d’accueil — lecture active\",\"body\":\"<p>Ce site regroupe ce dont vous avez besoin pour suivre la vie de votre communauté : <strong>consignes stabilisées</strong> (documents), <strong>échanges</strong> (forum), <strong>compétences</strong> (formations), <strong>coordination</strong> (événements, pointage selon les réglages) et <strong>votre dossier</strong> (personnel). Ce parcours vise un seul résultat : que vous sachiez <em>où</em> chercher l’information et <em>comment</em> agir sans improviser.</p><p>Les textes sont longs volontairement : ce n’est pas une brochure marketing, c’est un mode d’emploi. Si une rubrique n’existe pas chez vous, c’est souvent lié aux droits ou à la configuration — ce n’est pas une erreur de parcours de votre part.</p>\",\"contextKicker\":\"Étape 01 · Cadrage\",\"surface\":\"elevated\",\"metric\":{\"label\":\"Pour qui\",\"value\":\"Tous les membres\"},\"cards\":[{\"label\":\"Documents\",\"body\":\"Notes et fichiers de référence, retrouvables et mis à jour par le staff.\"},{\"label\":\"Forum & annonces\",\"body\":\"Échanges et relances ; ce n’est pas le stockage des versions finales.\"},{\"label\":\"Formations\",\"body\":\"Parcours tracés, parfois obligatoires ou certifiants selon les règles.\"}],\"insights\":[{\"variant\":\"key\",\"title\":\"\",\"body\":\"Le portail oriente : tableau de bord et menu reflètent ce que votre rôle permet de voir.\"}]},{\"template\":\"reading_article\",\"title\":\"À quoi sert concrètement ce portail ?\",\"subtitle\":\"Stabiliser l’information, pas la noyer\",\"contextKicker\":\"Étape 02 · Lecture\",\"surface\":\"default\",\"insights\":[{\"variant\":\"vigilance\",\"title\":\"\",\"body\":\"Si une rubrique manque, vérifiez la communauté active et votre affectation avant de conclure à une « panne ».\"}],\"body\":\"<p>Le portail répond à un problème simple : lorsque chacun va chercher l’information sur des canaux informels, les versions se multiplient, les retardataires ne voient pas les mises à jour, et le staff passe son temps à répéter la même consigne. Ici, l’objectif est que la <strong>version de référence</strong> vive dans des endroits identifiables : documents publiés, fils de discussion classés, formations suivies et tracées.</p>\\n<p>Après connexion, vous n’êtes pas censé « explorer au hasard » : le <strong>tableau de bord</strong> et le <strong>menu</strong> vous orientent vers ce qui est ouvert pour votre rôle. Vous pouvez aussi disposer d’une zone regroupant les modules d’<strong>opérations</strong> : lieu central de mission, briefings, organigramme, outils tactiques selon ce que votre communauté a activé. Ce n’est pas décoratif : ce sont des raccourcis pour éviter les détours.</p>\\n<p>Le portail ne remplace pas le jugement ni la chaîne de commandement : il <strong>porte</strong> l’information et la formation. Une note officielle reste une note officielle ; un message sur le forum reste un échange ; une formation indique ce que vous avez parcouru et validé, pas votre valeur opérationnelle au sens tactique.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--info\\\"><p><strong>À retenir</strong> : si vous ne voyez pas une rubrique mentionnée dans ce parcours, commencez par vérifier que vous êtes dans la bonne communauté (lorsque vous en avez plusieurs), puis demandez au staff si l’accès est normal ou s’il manque une affectation de rôle.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Déroulé de ce parcours et méthode de travail\",\"subtitle\":\"Lectures, bilan interrogé, puis validation finale\",\"body\":\"<p>Ce parcours enchaîne plusieurs modules de lecture, un <strong>bilan interrogé à mi-parcours</strong> pour ancrer les premiers acquis, puis le module sur la vie collective (forum, événements) et enfin la <strong>validation finale</strong>. L’ordre est logique : d’abord la vision d’ensemble et la sécurité du compte, ensuite la navigation quotidienne, puis les contenus « métier » (personnel, documents, formations), avant le bilan, le collectif et la manière dont le site atteste vos acquis.</p>\\n<h3>Comment lire efficacement</h3>\\n<p>Utilisez les boutons <strong>Précédent</strong> et <strong>Suivant</strong> sous les diapositives. Ne cherchez pas à « swiper » trop vite : plusieurs écrans contiennent des nuances importantes (par exemple la différence entre un document officiel et un fil de discussion). Lorsqu’un <strong>texte à trous</strong> apparaît, complétez-le avant de valider : c’est un mini-test de vocabulaire intégré au parcours.</p>\\n<h3>Si quelque chose reste flou pour votre unité</h3>\\n<p>Notez la question pendant la lecture, puis posez-la sur le canal prévu par votre organisation (référent, réunion, fil dédié). Ce parcours décrit le fonctionnement général du portail ; votre unité peut avoir des conventions supplémentaires (horaires, niveaux de diffusion, procédure de validation des absences, etc.).</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--tip\\\"><p><strong>Erreur fréquente</strong> : croire que « tout est sur le forum ». Le forum sert à débattre, annoncer, relancer ; les fichiers de référence et les textes stabilisés doivent vivre dans la rubrique documents (ou équivalent) lorsque le staff les y place.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"À l’issue du parcours complet, vous saurez…\",\"subtitle\":\"Objectifs opérationnels\",\"body\":\"<ul>\\n<li>expliquer à un nouveau membre à quoi sert le tableau de bord et comment retrouver l’aide ou la documentation du site ;</li>\\n<li>mettre à jour vous-même profil, préférences et sécurité du compte sans demander au staff pour chaque détail ;</li>\\n<li>ouvrir la rubrique documents, comprendre pourquoi un fichier peut être masqué, et ne pas rediffuser un contenu sensible hors des canaux prévus ;</li>\\n<li>parcourir le catalogue des formations, distinguer inscription libre et assignation par le staff, et reprendre un module en cours ;</li>\\n<li>participer au forum sans saturer les catégories ni ignorer les annonces officielles ;</li>\\n<li>traiter un événement comme un engagement : inscription, prévenance en cas d’empêchement, respect des consignes de présence ;</li>\\n<li>réussir le bilan interrogé à mi-parcours puis le questionnaire final, et utiliser les explications affichées pour réviser en cas d’échec ;</li>\\n<li>comprendre ce que signifient pour vous une formation <strong>obligatoire</strong> et une formation <strong>certifiante</strong>, ainsi que le rôle de l’attestation.</li>\\n</ul>\\n<p>Ce n’est pas une liste à décorer : c’est le socle minimal attendu d’un membre qui utilise le portail au quotidien.</p>\"},{\"template\":\"reading_article\",\"title\":\"Ce que le portail n’est pas\",\"subtitle\":\"Éviter les malentendus d’usage\",\"contextKicker\":\"Étape 03 · Cadrage\",\"body\":\"<p>Le portail <strong>n’est pas</strong> un substitut à la chaîne de commandement ni au jugement sur le terrain : il porte l’information et la formation, pas l’autorité opérationnelle.</p>\\n<p>Il <strong>n’est pas</strong> un espace où toute note officielle peut rester définitivement dans un fil de discussion : la version stabilisée appartient aux documents (ou équivalent) lorsque le staff y procède.</p>\\n<p>Il <strong>n’est pas</strong> une messagerie personnelle : les échanges publics ou de service suivent des règles de canal ; les sujets sensibles passent par les procédures prévues.</p>\\n<p>Enfin, une formation validée sur le site <strong>n’est pas</strong>, à elle seule, une reconnaissance tacite de toutes les compétences métier : elle atteste du parcours réalisé selon les règles affichées.</p>\"},{\"template\":\"common_mistakes\",\"title\":\"Erreurs d’usage les plus fréquentes\",\"mistakes\":[{\"error\":\"Tout centraliser sur le forum\",\"why\":\"Le forum est conçu pour la conversation et les relais, pas pour remplacer la rubrique documents.\",\"consequence\":\"Versions multiples, fils longs, nouveaux membres qui ne retrouvent pas la référence.\",\"correction\":\"Demander ou attendre la publication dans les documents lorsque le staff valide un texte de référence.\"},{\"error\":\"Conclure trop vite à une « panne » du site\",\"why\":\"Souvent, une rubrique absente correspond à des droits, à une autre communauté active ou à une fonction non activée.\",\"consequence\":\"Messages d’alerte publics inutiles et temps perdu pour le staff.\",\"correction\":\"Vérifier le contexte (communauté, rôle), puis s’adresser au canal prévu pour le support.\"},{\"error\":\"Négliger la déconnexion sur poste partagé\",\"why\":\"La session peut rester ouverte pour le prochain utilisateur du même équipement.\",\"consequence\":\"Accès au compte et aux contenus au nom de la mauvaise personne.\",\"correction\":\"Utiliser la déconnexion explicite du portail en fin de session.\"}]},{\"template\":\"scenario_decision\",\"title\":\"Je ne trouve pas une rubrique mentionnée dans ce parcours\",\"context\":\"Vous suivez la formation ; un encadré cite une page (documents, organigramme, etc.) que vous ne voyez pas dans votre menu.\",\"situation\":\"<p>Vous devez agir rapidement pour un sujet opérationnel. Vous pensez que le site est « cassé ».</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Vérifier la communauté active et, si besoin, demander au staff si l’accès est normal pour votre rôle avant de conclure.\"},{\"id\":\"b\",\"text\":\"Publier immédiatement un message d’alerte dans toutes les catégories du forum.\"},{\"id\":\"c\",\"text\":\"Partager vos identifiants avec un camarade pour qu’il teste depuis son compte.\"},{\"id\":\"d\",\"text\":\"Abandonner toute utilisation du portail jusqu’à nouvel ordre.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>La première démarche raisonnable est de contrôler le <strong>contexte</strong> (communauté, rôle) puis de solliciter le staff sur le canal prévu. Les autres options créent du bruit, un risque de sécurité ou une interruption inutile de travail.</p>\"},{\"template\":\"title_hero\",\"title\":\"Sécurité : les bases\",\"subtitle\":\"Gestes simples, effet collectif\",\"body\":\"<p>Un compte compromis ou une session laissée ouverte sur un poste partagé, ce n’est pas « une affaire personnelle » : c’est un risque pour toute la communauté (usurpation, fuite de consignes, spam). Les bons réflexes sont courts : mot de passe sérieux, déconnexion explicite, prudence sur les copies d’écran et les transferts hors site.</p>\",\"primaryAction\":{\"type\":\"modal\",\"label\":\"Voir la liste des rappels\",\"modalId\":\"onb-sec\"}},{\"template\":\"resources_list\",\"title\":\"Accès directs après connexion\",\"subtitle\":\"Liens utiles\",\"body\":\"<p>Si un lien ne fonctionne pas, votre site peut utiliser une adresse légèrement différente : repassez alors par le menu principal.</p>\",\"resources\":[{\"title\":\"Tableau de bord\",\"url\":\"/public/dashboard\"},{\"title\":\"Documentation du portail\",\"url\":\"/public/documentation\"}]},{\"template\":\"reading_article\",\"title\":\"Avant de passer au module suivant\",\"subtitle\":\"Prenez le temps de l’ancrage\",\"body\":\"<p>La suite du parcours entre dans le détail de la navigation et du compte. Si vous avez sauté des paragraphes, revenez en arrière : les modules suivants supposent que vous savez déjà ce qu’est le tableau de bord, pourquoi les documents ne sont pas interchangeables avec le forum, et pourquoi la sécurité du compte est une responsabilité partagée.</p>\"}]}', NULL, 17, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(14, 14, 'Navigation et compte — parcours visuel', 'Menu principal, zone Opérations, tableau de bord, compte, préférences, recherche, bonnes pratiques.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module pratique\",\"title\":\"\",\"lead\":\"Menus, tableau de bord, compte et recherche : les bons réflexes pour ne pas perdre le fil au quotidien.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~28 min\"},{\"label\":\"Focus\",\"value\":\"Repérage + compte\"}]},\"closure\":{\"title\":\"Synthèse — Navigation et compte\",\"seen\":[\"Tableau de bord comme premier arrêt ; menu et zone Opérations selon les droits.\",\"Procédure type de mise à jour du profil et des préférences dans la rubrique compte.\",\"Comparaison poste personnel / poste partagé et conduite en cas de page invisible.\"],\"acquired\":[\"Vous savez enchaîner les étapes pour tenir votre compte à jour sans improvisation.\",\"Vous évitez les erreurs de contexte entre communautés et les sessions ouvertes sur poste partagé.\"],\"nextHint\":\"Poursuivez avec « Organisation et contenus » : personnel, documents, formations et attestations.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Navigation et compte\",\"subtitle\":\"Lire le site comme un outil de travail\",\"body\":\"<p>Le <strong>menu principal</strong> n’est pas une vitrine : c’est la liste des fonctions auxquelles votre rôle a droit. Les intitulés sont volontairement lisibles (accueil, formations, forum, personnel, documents…). Sur grand écran, vous pouvez aussi avoir un menu regroupant les <strong>opérations</strong> : lieu central de mission, pointage, briefings, organigramme, outils tactiques — selon ce que votre communauté a activé. Sur mobile, le même contenu est souvent dans un menu latéral ou derrière une icône « menu ».</p><p>L’habitude à prendre : avant de poster ou de répondre, vérifiez que vous êtes au bon endroit dans le site (bonne communauté, bonne rubrique).</p>\",\"contextKicker\":\"Étape 01 · Structure du site\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Menu principal\",\"body\":\"Accès aux rubriques autorisées pour votre rôle.\"},{\"label\":\"Zone Opérations\",\"body\":\"Raccourcis tactiques et logistiques si votre communauté les active.\"},{\"label\":\"Mobile\",\"body\":\"Même logique, présentation adaptée (menu latéral ou icône).\"}]},{\"template\":\"reading_article\",\"title\":\"Tableau de bord : votre premier arrêt\",\"subtitle\":\"Synthèse, pas détail tactique\",\"body\":\"<p>Le <strong>tableau de bord</strong> est l’écran qui accueille souvent la session après connexion. Il ne remplace pas une carte d’opération ni un ordre écrit : il <strong>signale</strong> ce qui mérite attention pour votre compte — raccourcis vers des pages utiles, rappels de formations en cours ou à venir, parfois les prochains événements ou des messages du staff selon la configuration.</p>\\n<p>Traitez-le comme la « une » du portail pour <em>vous</em> : deux minutes suffisent à repérer si une date limite approche, si une formation obligatoire attend une action, ou si une annonce récente a été mise en avant. Si le tableau de bord est vide, cela ne veut pas dire qu’il ne se passe rien dans la communauté : ouvrez le forum, les documents ou le calendrier selon votre fonction.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--tip\\\"><p><strong>Bon réflexe</strong> : à chaque retour sur le site, passez par le tableau de bord avant d’aller sur les réseaux sociaux ou messageries externes — la consigne officielle est ici en premier.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Compte, profil, préférences et sécurité\",\"subtitle\":\"Ce que vous contrôlez vous-même\",\"body\":\"<p>La rubrique <strong>compte</strong> (souvent « Mon compte » ou « Paramètres ») concentre tout ce qui touche à <em>votre</em> présence sur le portail. Elle sert à trois grandes familles d’actions.</p>\\n<h3>Profil et identité affichée</h3>\\n<p>Selon les règles de votre communauté, certaines informations peuvent être visibles par le staff ou d’autres membres (nom affiché, affectation, champs de dossier). Les mettre à jour quand elles changent évite les erreurs d’affectation et les convocations à mauvais escient.</p>\\n<h3>Préférences</h3>\\n<p>Notifications, affichage, parfois choix de ce que vous acceptez de montrer : ce sont des réglages personnels. Si vous désactivez tout sans le vouloir, vous raterez des rappels légitimes ; si vous laissez tout ouvert sur un canal bruyant, vous finirez par ignorer les messages importants. Trouvez un équilibre et révisez-le après une grosse période d’activité.</p>\\n<h3>Sécurité</h3>\\n<p>Mot de passe, confirmation d’adresse de contact, parfois la liste des appareils reconnus : toute modification sensible peut déclencher une vérification supplémentaire. C’est normal. Gardez une adresse de contact <strong>valide</strong> : c’est le filet de sécurité si vous perdez l’accès.</p>\"},{\"template\":\"reading_article\",\"title\":\"Recherche et multi-organisations\",\"subtitle\":\"Éviter les doublons et les erreurs de contexte\",\"body\":\"<p>Lorsque la recherche est disponible, utilisez-la avant de créer un nouveau sujet sur le forum ou avant de redemander un document : souvent, le fil ou le fichier existe déjà. Les résultats respectent vos droits : si quelque chose n’apparaît pas, ce n’est pas forcément qu’il n’existe pas — il peut être simplement hors de votre périmètre.</p>\\n<p>Si vous participez à <strong>plusieurs communautés</strong> sur la même plateforme, un écran de choix peut s’afficher à la connexion. L’erreur classique est de répondre à un briefing ou de signer une présence alors qu’on est encore « dans » l’autre organisation. Vérifiez l’en-tête du site ou le sélecteur avant toute action engageante.</p>\"},{\"template\":\"fill_blanks\",\"title\":\"Vérification rapide\",\"contextKicker\":\"Étape intermédiaire · Auto-évaluation\",\"metric\":{\"label\":\"Validation\",\"value\":\"Réponses exactes requises\"},\"body\":\"<p>Après connexion, l’écran qui regroupe en général raccourcis et rappels utiles pour votre session est le [[tableau de bord]].</p><p>Pour le mot de passe, les préférences et les réglages du compte, ouvrez la section <strong>compte</strong> (souvent intitulée « Mon compte ») depuis le menu principal.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Repères pour le quotidien\",\"body\":\"Revoyez périodiquement vos préférences de notification : un rappel de formation ou d’événement se joue souvent sur un simple e-mail ou une alerte interne.\\nSi un libellé de menu vous échappe, ouvrez la rubrique plutôt que d’ignorer : les intitulés sont pensés pour le langage courant.\\nSur un poste partagé, déconnectez-vous explicitement ; fermer l’onglet ne suffit pas toujours.\\nAvant d’ouvrir un nouveau fil sur le forum, recherchez ou parcourez la catégorie pour éviter les doublons.\\nSi une page refuse l’accès, considérez que votre rôle n’inclut peut-être pas cette fonction : demandez au staff au lieu d’essayer de contourner.\"},{\"template\":\"process_steps\",\"title\":\"Procédure type : mettre à jour son profil et ses préférences\",\"steps\":[{\"title\":\"Ouvrir la rubrique compte\",\"action\":\"Depuis le menu principal, accédez à « Mon compte » (ou libellé équivalent).\",\"vigilance\":\"Vérifiez que vous êtes dans la bonne communauté si vous en avez plusieurs.\"},{\"title\":\"Parcourir les sections proposées\",\"action\":\"Identifiez profil (identité affichée, champs de dossier), préférences (notifications, affichage) et sécurité (mot de passe, contact).\",\"vigilance\":\"Ne modifiez le mot de passe ou l’adresse de contact que si vous pouvez assumer la confirmation demandée par le site.\"},{\"title\":\"Enregistrer et contrôler\",\"action\":\"Validez les changements ; relisez les messages de confirmation ou les e-mails de vérification.\",\"vigilance\":\"Un contact obsolète bloque souvent la récupération d’accès en cas de problème.\"}]},{\"template\":\"role_scope_compare\",\"title\":\"Poste personnel et poste partagé\",\"memberView\":\"<p>Sur <strong>votre</strong> ordinateur ou appareil personnel, vous gérez la session comme d’habitude : fermeture du navigateur peut suffire selon les réglages, mais la déconnexion du portail reste recommandée si d’autres applications sont ouvertes.</p>\",\"staffView\":\"<p>Pour le <strong>staff</strong>, l’enjeu est le même au niveau collectif : rappeler la déconnexion sur les postes de permanence ou salles partagées fait partie du bon usage du service.</p>\",\"rightsNote\":\"<p>Les droits d’accès (menu, rubriques) ne changent pas selon le type de machine : ils dépendent du <strong>compte</strong> et du <strong>rôle</strong>. En revanche, le <strong>risque</strong> de session laissée ouverte est maximal sur un poste partagé.</p>\",\"notAnomaly\":\"<p>Que le site vous demande une reconnexion après une durée d’inactivité n’est pas une anomalie : c’est souvent une protection de session.</p>\"},{\"template\":\"scenario_decision\",\"title\":\"Une page du parcours ne s’affiche pas pour vous\",\"context\":\"Un encadré de formation cite une page (recherche, organigramme, etc.) ; chez vous le menu ne propose pas la même chose qu’illustré.\",\"situation\":\"<p>Vous devez compléter une tâche qui, selon vous, nécessite cette page.</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Contrôler la communauté active et demander au staff si l’accès est normal pour votre affectation avant d’alerter tout le monde.\"},{\"id\":\"b\",\"text\":\"Installer un outil tiers pour « forcer » l’affichage du site.\"},{\"id\":\"c\",\"text\":\"Utiliser le compte d’un autre membre pour entrer à sa place.\"},{\"id\":\"d\",\"text\":\"Publier sur le forum que le portail est inutilisable sans plus de précision.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>La conduite attendue combine <strong>vérification du contexte</strong> et <strong>escalade par le canal prévu</strong>. Les autres options exposent la sécurité ou créent du bruit.</p>\"},{\"template\":\"resources_list\",\"title\":\"Raccourcis fréquents\",\"subtitle\":\"\",\"body\":\"\",\"resources\":[{\"title\":\"Tableau de bord\",\"url\":\"/public/dashboard\"},{\"title\":\"Mon compte\",\"url\":\"/public/account\"},{\"title\":\"Préférences\",\"url\":\"/public/account/preferences\"},{\"title\":\"Recherche\",\"url\":\"/public/search\"}]}]}', NULL, 19, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(15, 15, 'Organisation et contenus — parcours visuel', 'Fiche personnelle, organigramme, documents officiels, catalogue LMS, progression et erreurs fréquentes.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Contenus structurés\",\"title\":\"\",\"lead\":\"Personnel, documents et formations : où vit l’information « durable » et comment la progression est enregistrée.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~32 min\"},{\"label\":\"Thème\",\"value\":\"Organisation\"}]},\"closure\":{\"title\":\"Synthèse — Organisation et contenus\",\"seen\":[\"Distinction nette : dossier personnel, organigramme, documents de référence, catalogue des formations.\",\"Cas pratiques : document sensible, version obsolète, formation assignée mais non terminée.\",\"Attestation : ce qu’elle atteste sur le portail et ce qu’elle ne remplace pas.\"],\"acquired\":[\"Vous savez pourquoi un contenu peut être invisible selon le rôle et que ce n’est pas forcément une erreur.\",\"Vous distinguez progression réelle et intention ; assignation vs inscription libre.\"],\"nextHint\":\"Passez au bilan interrogé de mi-parcours, puis au module sur le forum et les événements.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Organisation et contenus\",\"subtitle\":\"Personnel, documents, formations : la chaîne de l’information\",\"body\":\"<p>Ce module décrit comment le portail porte l’information « durable » : <strong>qui vous êtes dans l’unité</strong>, <strong>où sont les fichiers de référence</strong>, et <strong>comment le site enregistre ce que vous avez appris</strong>. Ce que vous voyez dépend de votre rôle ; l’absence d’accès n’est pas une punition, c’est en général un périmètre de diffusion.</p>\",\"contextKicker\":\"Étape 01 · Chaîne d’information\",\"surface\":\"elevated\",\"metric\":{\"label\":\"Principe\",\"value\":\"Périmètre selon le rôle\"},\"insights\":[{\"variant\":\"result\",\"title\":\"\",\"body\":\"Objectif : savoir où mettre à jour votre dossier et où trouver la version officielle d’un texte.\"}]},{\"template\":\"reading_article\",\"title\":\"Personnel et organigramme\",\"subtitle\":\"Dossier individuel et structure collective\",\"body\":\"<p>L’espace <strong>personnel</strong> relie votre compte de connexion à votre <strong>dossier</strong> tel que la communauté le tient : affectation, fonctions affichées, champs que le staff a demandés de remplir, parfois pièces ou validations selon les processus en place. Une fiche incomplète ou périmée produit des erreurs réelles : mauvaise convocation, mauvais groupe, retard sur une exigence administrative.</p>\\n<p>L’<strong>organigramme</strong> donne une vue de la structure et des rattachements. Il aide à savoir à qui s’adresser pour un sujet donné, mais il ne remplace pas un ordre du jour ou une note officielle : c’est une photographie organisationnelle, pas la doctrine complète.</p>\"},{\"template\":\"reading_article\",\"title\":\"Documents : la version de référence\",\"subtitle\":\"Pourquoi ce n’est pas « comme le forum »\",\"contextKicker\":\"Étape clé · Référence vs discussion\",\"surface\":\"default\",\"cards\":[{\"label\":\"Documents\",\"body\":\"Textes et fichiers stabilisés, avec contrôle de diffusion.\"},{\"label\":\"Forum\",\"body\":\"Conversation vivante : annonces, questions, relances.\"},{\"label\":\"Erreur fréquente\",\"body\":\"Publier la « version finale » uniquement dans un fil de discussion.\"}],\"body\":\"<p>La rubrique <strong>documents</strong> sert à publier ce qui doit rester <strong>stable</strong> et <strong>retrouvable</strong> : notes, guides, modèles, visuels autorisés, parfois packs techniques. Chaque dossier ou fichier peut avoir un niveau de diffusion différent ; si vous ne voyez pas un contenu, c’est souvent qu’il est réservé à un autre groupe.</p>\\n<p>Le <strong>forum</strong>, lui, vit par messages successifs : on y annonce, on débat, on relance. Un fil n’est pas un bon endroit pour « stocker » la version finale d’un texte : il se noie, on ne sait plus laquelle est la bonne page, et les nouveaux arrivants ne remontent pas 200 messages. En pratique, lorsque le staff valide un document, il doit vivre dans la rubrique documents (ou équivalent) ; le forum sert à expliquer le contexte ou à répondre aux questions.</p>\\n<p>Ne recopiez pas un fichier sensible sur une messagerie personnelle ou un stockage privé : vous perdez le contrôle de la diffusion et vous contournez les traces prévues par l’organisation.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--info\\\"><p><strong>À retenir</strong> : document = référence stabilisée ; forum = conversation. Si les deux se mélangent, l’information se dégrade pour tout le monde.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Formations et catalogue LMS\",\"subtitle\":\"Inscription, assignation, progression, obligation\",\"body\":\"<p>Le <strong>catalogue</strong> liste les parcours auxquels vous pouvez accéder. Deux grands cas : vous vous inscrivez vous-même à une formation ouverte, ou le staff vous <strong>assigne</strong> un parcours (souvent avec une attente de complétion dans un délai). La fiche indique en général la durée estimée, le niveau, et si le parcours est <strong>obligatoire</strong> et/ou <strong>certifiant</strong>.</p>\\n<p>À l’intérieur d’un parcours, les <strong>modules</strong> et <strong>leçons</strong> peuvent être verrouillés dans un ordre : respectez-le, sinon vous risquez de croire avoir « tout vu » alors qu’une étape bloquante manque encore. Le site enregistre la progression : vous pouvez fermer la session et reprendre, mais une formation n’est réellement terminée que lorsque toutes les étapes requises le sont — le système reflète le parcours effectif, pas l’intention.</p>\\n<p>Les parcours « canvas » comme celui-ci se lisent diapositive par diapositive ; d’autres formations mélangent texte, média, quiz intermédiaires. Le principe reste le même : chaque étape a une fonction pédagogique ou réglementaire.</p>\"},{\"template\":\"reading_article\",\"title\":\"Déroulé type d’un parcours sur le portail\",\"subtitle\":\"De l’ouverture à l’attestation\",\"body\":\"<p><strong>Ouverture.</strong> Vous accédez à la fiche formation après inscription ou assignation. Lisez l’introduction et les objectifs : elles disent ce que le staff attend comme résultat.</p>\\n<p><strong>Modules.</strong> Vous enchaînez les leçons selon les règles du parcours. Certaines sont de la lecture, d’autres des exercices ou des questionnaires partiels.</p>\\n<p><strong>Évaluation.</strong> Un quiz ou une épreuve finale peut exiger un score minimal. Les tentatives sont en nombre limité : utilisez les retours du questionnaire pour combler vos lacunes avant de retenter.</p>\\n<p><strong>Clôture.</strong> Lorsque tout est validé, le parcours est marqué comme terminé. Si la formation est certifiante, une <strong>attestation</strong> ou un équivalent peut être proposé selon les réglages de votre communauté.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : document sensible et diffusion\",\"caseText\":\"<p>Un fichier marqué restreint circule dans une messagerie personnelle externe « pour aller plus vite ». Un membre vous demande la « bonne » copie.</p>\",\"analysis\":\"<p>La diffusion hors des espaces prévus fait perdre la maîtrise des accès et la traçabilité attendue par l’organisation.</p>\",\"goodConduct\":\"<p>Ne pas recopier le fichier sur un canal non autorisé. Orienter vers la rubrique documents ou vers le staff si l’accès manque. Signaler la fuite si les règles internes l’exigent.</p>\",\"conclusion\":\"<p>La rapidité ne doit pas se faire au détriment du périmètre de diffusion défini par la communauté.</p>\"},{\"template\":\"common_mistakes\",\"title\":\"Document obsolète ou douteux\",\"mistakes\":[{\"error\":\"Recirculer une ancienne version « au cas où »\",\"why\":\"Plusieurs versions coexistent déjà ; en ajouter une informelle aggrave la confusion.\",\"consequence\":\"Des équipes travaillent sur des textes différents au même titre.\",\"correction\":\"Signaler au référent ou au staff ; laisser la mise à jour officielle dans la rubrique documents.\"},{\"error\":\"Considérer qu’une formation « presque finie » suffit\",\"why\":\"Le système enregistre les étapes réellement accomplies ; une obligation reste une obligation.\",\"consequence\":\"Retard sur l’exigence collective et rappels répétés du staff.\",\"correction\":\"Repérer les leçons ou quiz restants sur la fiche formation et les terminer dans le délai fixé.\"}]},{\"template\":\"reading_article\",\"title\":\"Attestation : ce qu’elle prouve et ce qu’elle ne prouve pas\",\"subtitle\":\"Lecture institutionnelle\",\"body\":\"<p>Lorsqu’une formation est <strong>certifiante</strong> et que vous avez accompli toutes les étapes requises (y compris les scores minimaux aux questionnaires), le portail peut délivrer une <strong>attestation</strong> (ou équivalent) selon les réglages de votre communauté.</p>\\n<p><strong>Ce que cela prouve en général</strong> : vous avez validé le parcours tel qu’il est conçu sur le site, aux dates enregistrées.</p>\\n<p><strong>Ce que cela ne prouve pas automatiquement</strong> : une habilitation opérationnelle spécifique, une clearance, ou toute compétence que seule votre unité peut reconnaître hors du LMS. L’attestation et le dossier métier peuvent coexiger : l’un ne remplace pas l’autre.</p>\"},{\"template\":\"role_scope_compare\",\"title\":\"Pourquoi un même contenu n’est pas visible pour tout le monde\",\"memberView\":\"<p>Un membre voit les dossiers, documents et formations correspondant à <strong>son rôle</strong> et aux <strong>niveaux de diffusion</strong> choisis par le staff. Certaines fiches ou fichiers sont volontairement limités à un groupe.</p>\",\"staffView\":\"<p>Le staff dispose en général d’outils d’administration ou de modération pour publier, retirer ou restreindre un contenu. La visibilité est une décision d’organisation, pas une préférence personnelle du site.</p>\",\"rightsNote\":\"<p>Si vous changez de fonction ou d’affectation, votre périmètre peut évoluer après mise à jour des rôles : ce n’est pas une punition, c’est l’alignement des accès.</p>\",\"notAnomaly\":\"<p>Deux camarades avec des rôles différents peuvent légitimement ne pas voir les mêmes rubriques : ce n’est pas systématiquement un dysfonctionnement.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Ce qui compte vraiment côté organisation\",\"body\":\"Les contenus sensibles restent dans les espaces prévus ; ne les faites pas migrer vers des canaux privés non maîtrisés.\\nUne formation obligatoire doit être traitée dans les délais fixés par le staff : l’outil permet de suivre l’avancement.\\nConsultez régulièrement votre espace formations pour voir les assignations et les rappels.\\nL’organigramme oriente ; il ne remplace pas une consigne écrite ou un ordre de mission.\\nSi un document semble faux ou obsolète, signalez-le au responsable plutôt que de le recirculer.\"},{\"template\":\"resources_list\",\"title\":\"Accès directs\",\"subtitle\":\"\",\"body\":\"\",\"resources\":[{\"title\":\"Ma fiche personnelle\",\"url\":\"/public/personnel/me\"},{\"title\":\"Organigramme\",\"url\":\"/public/orbat\"},{\"title\":\"Documents\",\"url\":\"/public/documents\"},{\"title\":\"Catalogue des formations\",\"url\":\"/public/formations\"}]}]}', NULL, 21, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(16, 16, 'Communauté — parcours visuel', 'Forum, annonces, événements, pointage, signalements, résumé des bons réflexes.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Vie collective\",\"title\":\"\",\"lead\":\"Forum, événements, annonces : des règles simples pour que l’information reste utile à toute l’unité.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~26 min\"},{\"label\":\"Enjeu\",\"value\":\"Canaux et rigueur\"}]},\"closure\":{\"title\":\"Synthèse — Communauté\",\"seen\":[\"Quand poster publiquement et quand passer par un canal dédié ou un signalement.\",\"Titres de sujet utiles vs vagues ; annonce officielle vs conversation libre.\",\"Cas types : doublon sur le forum, absence non signalée à un événement inscrit.\"],\"acquired\":[\"Vous réduisez le bruit informationnel par des réflexes simples (recherche, titre, prévenance).\",\"Vous savez qu’un engagement sur un créneau inscrit est une donnée logistique pour le staff.\"],\"nextHint\":\"Il reste le module « Validation finale » : questionnaire, attestation et limites de ce que couvre la certification sur le portail.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Vie de communauté\",\"subtitle\":\"Coordonner sans encombrer les canaux\",\"body\":\"<p>Le <strong>forum</strong> et les <strong>événements</strong> sont les lieux où la communauté vit au quotidien : annonces, questions, briefings, débriefs, organisation logistique. La qualité collective dépend de chacun : un fil lisible vaut mieux que vingt messages redondants ; une inscription honnête vaut mieux qu’une absence non signalée.</p>\",\"contextKicker\":\"Étape 01 · Cadre\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Forum\",\"body\":\"Structurer les sujets et respecter les annonces épinglées.\"},{\"label\":\"Événements\",\"body\":\"Inscription = engagement logistique pour le staff.\"},{\"label\":\"Signalement\",\"body\":\"Canal adapté pour les sujets sensibles.\"}]},{\"template\":\"reading_article\",\"title\":\"Forum : structurer la parole collective\",\"subtitle\":\"Titres, catégories, respect\",\"body\":\"<p>Avant d’ouvrir un <strong>nouveau sujet</strong>, parcourez la catégorie et utilisez la recherche : souvent, le problème est déjà en discussion. Si vous ouvrez un fil, choisissez un <strong>titre</strong> qui dit ce que vous cherchez ou ce que vous proposez, pas une phrase vague du type « question ».</p>\\n<p>Dans le fil, allez à l’essentiel : contexte utile, question claire, proposition si vous en avez une. Le désaccord est possible, la grossièreté n’apporte rien. Les messages hors-sujet répétés, le spam et les polémiques stériles obligent le staff à modérer — ce temps-là n’est plus disponible pour vous aider sur le fond.</p>\\n<p>Lorsque le staff épingle une annonce, considérez qu’elle a force de consigne pour la période concernée : lisez-la avant de poster une question déjà traitée.</p>\"},{\"template\":\"reading_article\",\"title\":\"Événements, inscriptions et présence\",\"subtitle\":\"Engagement et logistique\",\"body\":\"<p>Les <strong>événements</strong> matérialisent des créneaux : date, lieu ou lien, description, parfois matériel attendu ou tenue. Lorsque l’inscription est demandée, elle sert à dimensionner les moyens (places, encadrement, supports). S’inscrire « pour voir » puis ne pas venir sans prévenir dégrade la confiance et fait perdre du temps.</p>\\n<p>Si vous ne pouvez pas venir, <strong>prévenez</strong> selon la procédure de votre organisation (message au staff, modification de l’inscription, fil prévu). Ce n’est pas une option de politesse : c’est une donnée d’organisation.</p>\\n<p>Certaines communautés utilisent un <strong>pointage</strong> ou une feuille de présence numérique : suivez les consignes affichées sur place. Un pointage incorrect peut fausser les statistiques ou les validations administratives.</p>\"},{\"template\":\"reading_article\",\"title\":\"Annonces officielles et signalements\",\"subtitle\":\"Quand passer par un canal dédié\",\"body\":\"<p>Les annonces importantes sont souvent mises en avant en tête de forum ou sur le tableau de bord. Elles peuvent compléter une note dans les documents : l’une explique le « maintenant », l’autre stabilise le texte de référence.</p>\\n<p>Pour un problème sensible — contenu inapproprié, conflit personnel, erreur de sécurité — utilisez le <strong>canal prévu</strong> (signalement, message à un modérateur, procédure interne). Une « dénonciation » publique désordonnée crée du bruit, expose des personnes et complique la résolution.</p>\"},{\"template\":\"reading_article\",\"title\":\"Synthèse des bons réflexes\",\"subtitle\":\"À appliquer dès la première semaine\",\"body\":\"<p>Lisez les annonces avant de poster. Répondez dans le fil qui traite déjà le sujet lorsque c’est possible. Inscrivez-vous aux créneaux avec sérieux. Prévenez en cas d’empêchement. Remerciez ou synthétisez en fin de fil si cela clarifie la décision pour les suivants.</p>\\n<p>Ces gestes semblent mineurs ; cumulés sur une centaine de membres, ils font la différence entre un portail utilisable et un chaos de notifications.</p>\"},{\"template\":\"dos_donts\",\"title\":\"Canal public ou canal dédié ?\",\"dos\":[\"Poser une question générale dans la catégorie adaptée, après recherche.\",\"Utiliser le signalement ou la procédure interne pour un contenu inapproprié ou un conflit sensible.\",\"Écrire au staff sur le canal prévu pour un sujet personnel ou confidentiel.\"],\"donts\":[\"Épingler une polémique personnelle en tête de forum sans passer par la modération.\",\"Multiplier les posts identiques dans plusieurs catégories « pour être sûr d’être vu ».\",\"Diffuser des données sensibles sur un fil ouvert alors qu’un canal restreint existe.\"],\"synthesis\":\"<p>La règle simple : <strong>public</strong> pour ce qui doit être partagé et archivable par la collectivité ; <strong>canal dédié</strong> pour ce qui exige confidentialité, preuve ou traitement par le staff.</p>\"},{\"template\":\"reading_article\",\"title\":\"Titre utile, titre inutile\",\"subtitle\":\"Lisibilité collective\",\"body\":\"<p><strong>Inutile</strong> : « Question », « Urgent », « À lire » — aucun membre ne sait de quoi il s’agit sans ouvrir le fil.</p>\\n<p><strong>Utile</strong> : « Point logistique — convocation du 12 : tenue et horaire », « Document obsolète sur la fiche X : demande de retrait », « Besoin d’accès documents section Y pour la permanence ».</p>\\n<p>Le titre est le contrat de lecture avec les autres : il doit permettre de trier, d’archiver et de retrouver le sujet plus tard.</p>\"},{\"template\":\"reading_article\",\"title\":\"Annonce officielle et conversation\",\"subtitle\":\"Deux fonctions différentes\",\"body\":\"<p>Une <strong>annonce officielle</strong> (souvent épinglée ou mise en avant) fixe une consigne ou une information structurante pour une période donnée. Elle complète parfois un document de référence ; elle ne le remplace pas si la version stabilisée doit vivre dans la rubrique documents.</p>\\n<p>Une <strong>conversation</strong> sur le forum sert au débat, aux questions de détail, aux mises à jour de situation. Mélanger les deux — par exemple noyer une annonce sous des messages hors-sujet — rend la consigne illisible pour ceux qui arrivent après.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : doublon sur le forum\",\"caseText\":\"<p>Le même sujet apparaît en trois fils ouverts la même semaine dans la même catégorie. Les réponses se dispersent.</p>\",\"analysis\":\"<p>Chacun a voulu « gagner du temps » sans parcourir la catégorie ; le staff doit fusionner ou orienter, et les membres ne savent plus où lire la décision.</p>\",\"goodConduct\":\"<p>Avant d’ouvrir un sujet : recherche et lecture des fils récents. Si le sujet existe, poster dans le fil existant. Si vous avez ouvert par erreur un doublon, indiquez-le et renvoyez vers le fil principal.</p>\",\"conclusion\":\"<p>La discipline de fil unique sur un même sujet est un geste de respect du temps collectif.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : absence non signalée à un événement\",\"caseText\":\"<p>Vous étiez inscrit à un créneau ; un empêchement de dernière minute survient. Vous ne modifiez pas l’inscription et ne prévenez personne.</p>\",\"analysis\":\"<p>Le staff a dimensionné l’encadrement et le matériel sur la base des inscriptions. Une place vide non signalée est une ressource mal utilisée ; un autre membre aurait pu prendre la place.</p>\",\"goodConduct\":\"<p>Dès que l’empêchement est connu, suivre la procédure affichée (désinscription, message au référent, fil prévu). Mieux vaut prévenir tôt qu’imposer un silence au collectif.</p>\",\"conclusion\":\"<p>L’inscription à un événement est un engagement logistique, pas seulement un clic décoratif.</p>\"},{\"template\":\"fill_blanks\",\"title\":\"Une dernière vérification\",\"contextKicker\":\"Auto-évaluation\",\"metric\":{\"label\":\"Rappel\",\"value\":\"Une réponse exacte par trou\"},\"body\":\"<p>Avant d’ouvrir un nouveau sujet sur le forum, il est préférable de vérifier qu’un [[fil]] ou une discussion ne traite pas déjà le même problème.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Participation utile\",\"body\":\"Un retour sur une formation aide lorsqu’il est précis (ce qui manquait, ce qui était clair), pas lorsqu’il se limite à une critique vague.\\nPour un événement, l’empêchement se signale ; l’absence non expliquée se compte aussi.\\nNe divulguez pas des informations personnelles sur des tiers sans accord.\\nRespectez le ton fixé par votre communauté (formel, sobre, etc.).\\nEn cas de doute sur la catégorie du forum, demandez au staff avant de poster.\"}]}', NULL, 17, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26');
INSERT INTO `training_lessons` (`id`, `module_id`, `title`, `summary`, `learning_objectives`, `instructor_notes`, `lesson_type`, `content`, `external_url`, `duration_minutes`, `difficulty`, `position`, `is_required`, `created_at`, `updated_at`) VALUES
(17, 17, 'Validation — parcours visuel', 'Quiz, score, tentatives, attestation, reprise de parcours et gestion du stress de l’évaluation.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Validation\",\"title\":\"\",\"lead\":\"Questionnaire final, attestation et reprise de parcours : ce qui se passe après la dernière lecture.\",\"stats\":[{\"label\":\"Seuil de réussite\",\"value\":\"80 %\"},{\"label\":\"Tentatives\",\"value\":\"Plusieurs (selon la formation)\"}]},\"closure\":{\"title\":\"Avant de lancer le questionnaire\",\"seen\":[\"Le questionnaire final couvre l’ensemble du parcours : navigation, compte, contenus, forum, événements, sécurité.\",\"Les explications après une réponse incorrecte sont une aide pédagogique : servez-vous-en avant de retenter.\",\"Validation sur le portail et habilitation métier reconnue par l’unité sont deux choses distinctes.\"],\"acquired\":[\"Vous savez organiser une reprise de révision ciblée après un échec.\",\"Vous savez ce qu’une attestation atteste — et ce qu’elle ne remplace pas.\"],\"nextHint\":\"Passez à la leçon « Quiz » du module lorsqu’elle est disponible dans votre parcours.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Dernière étape : validation\",\"subtitle\":\"Quiz de fin de parcours\",\"body\":\"<p>Le questionnaire porte sur les <strong>idées directrices</strong> du portail : navigation, compte, documents, formations, forum, événements, sécurité. Le <strong>seuil de réussite est de 80&nbsp;%</strong>. Vous disposez de <strong>plusieurs tentatives</strong> dans la limite fixée par la formation.</p><p>Les formulations volontairement longues dans certaines réponses fausses imitent des croyances courantes : lisez jusqu’au bout avant de choisir.</p>\",\"contextKicker\":\"Étape finale · Évaluation\",\"surface\":\"elevated\",\"insights\":[{\"variant\":\"vigilance\",\"title\":\"\",\"body\":\"Ne validez pas la dernière réponse si votre connexion est très instable : en cas de doute, attendez un réseau fiable.\"}]},{\"template\":\"reading_article\",\"title\":\"Après le quiz : attestation, échec, reprise\",\"subtitle\":\"Ce que le site retient de vous\",\"body\":\"<p>Si vous atteignez le score requis et que la formation est <strong>certifiante</strong>, une <strong>attestation</strong> ou un équivalent peut être proposé (téléchargement, trace sur votre dossier, selon les réglages). Ce document atteste que vous avez parcouru et validé <em>ce</em> parcours à cette date — il ne remplace pas une habilitation métier qui serait définie ailleurs.</p>\\n<p>Si vous échouez, le questionnaire affiche en général des <strong>explications</strong> sur les réponses attendues. Utilisez-les comme liste de révision : retournez sur les modules qui coincent, puis retentez. L’objectif n’est pas de vous piéger mais de vérifier que vous ne partirez pas avec de fausses certitudes (par exemple confondre forum et documents, ou ignorer la déconnexion sur poste partagé).</p>\\n<p>Conservez une copie de votre attestation si votre organisation vous la demande hors ligne ; le portail peut aussi conserver l’historique de vos formations terminées.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Avant de lancer le questionnaire\",\"body\":\"Prévoyez environ quinze à vingt minutes sans interruption.\\nInstallez-vous dans un endroit où vous pouvez lire calmement chaque énoncé.\\nSi votre connexion est instable, évitez de valider la dernière réponse au moment où le signal faiblit.\\nLes questions restent au niveau « membre du portail », pas au niveau administration technique.\\nCe parcours vous a déjà donné le vocabulaire et les situations : le quiz ne demande pas de culture générale extérieure au site.\"},{\"template\":\"reading_article\",\"title\":\"Pourquoi cette validation existe\",\"subtitle\":\"Responsabilité partagée\",\"body\":\"<p>La communauté a intérêt à ce que chaque membre sache se servir du portail correctement : moins d’erreurs de diffusion, moins de fichiers égarés, moins de questions répétitives au staff. En validant ce parcours, vous confirmez que vous connaissez les bons réflexes — pas que vous êtes infaillible, mais que vous savez où relire l’information quand un doute revient.</p>\"},{\"template\":\"scenario_decision\",\"title\":\"Vous avez réussi le quiz certifiant : que pouvez-vous en déduire ?\",\"context\":\"Le portail affiche le parcours comme terminé et propose une attestation.\",\"situation\":\"<p>Un camarade affirme que vous êtes « habilité » sur un poste sensible uniquement sur cette base.</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Considérer que l’attestation couvre le parcours sur le site ; toute habilitation opérationnelle spécifique relève encore des règles de l’unité.\"},{\"id\":\"b\",\"text\":\"Conclure que l’attestation remplace toute validation métier interne sans autre formalité.\"},{\"id\":\"c\",\"text\":\"Refuser d’afficher l’attestation car elle n’a aucune valeur.\"},{\"id\":\"d\",\"text\":\"Publier l’attestation sur le forum comme preuve de clearance.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>L’attestation atteste la <strong>validation du parcours</strong> tel que paramétré sur le portail. Les exigences métier (affectation, validation d’un chef, clearance) restent du ressort de l’organisation : ne pas les confondre évite les malentendus.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"En cas de doute pendant le questionnaire\",\"body\":\"Si deux réponses semblent crédibles, demandez-vous laquelle correspond au réflexe « membre du portail » décrit dans ce parcours, pas à une habitude personnelle ou à une astuce technique.\\nEn cas d’échec, notez les thèmes signalés par les explications puis rouvrez les synthèses des modules concernés.\\nNe tentez pas le quiz dans des conditions de connexion très dégradées : une coupure peut interrompre la session.\\nLe score seuil est rappelé sur la fiche formation : il est identique pour tous les membres sur ce parcours.\\nAprès réussite, conservez ou téléchargez l’attestation selon les options proposées par votre communauté.\"}]}', NULL, 15, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(18, 18, 'Vue d’ensemble — parcours visuel', 'Rôle du portail, déroulé pédagogique, méthode de travail, sécurité du compte, liens vers l’aide.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[{\"id\":\"onb-sec\",\"title\":\"Rappels sécurité\",\"body\":\"<ul><li><strong>Mot de passe :</strong> gardez-le pour vous ; changez-le si vous pensez qu’il a pu être vu par une autre personne.</li><li><strong>Ordinateur partagé :</strong> déconnectez-vous du portail quand vous avez terminé.</li><li><strong>Adresse e-mail :</strong> si vous la modifiez, suivez les étapes de confirmation affichées sur le site.</li><li><strong>Contenus sensibles :</strong> ne les copiez pas sur des canaux personnels ; restez dans les espaces prévus par votre organisation.</li></ul>\"}],\"opening\":{\"eyebrow\":\"Parcours d’accueil\",\"title\":\"\",\"lead\":\"Ce module pose le cadre : à quoi sert le portail, comment lire ce parcours, et quels réflexes de sécurité garder en tête.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~26 min\"},{\"label\":\"Format\",\"value\":\"Parcours visuel\"},{\"label\":\"Objectif\",\"value\":\"Finalité + risques + sécurité\"}]},\"closure\":{\"title\":\"Synthèse — Vue d’ensemble\",\"seen\":[\"Finalité institutionnelle : information stable, coordination vivante, suivi pédagogique — avec des lieux distincts sur le site.\",\"Ce que le portail n’est pas : ni substitut à la chaîne de commandement, ni dépôt anarchique des notes officielles sur le forum.\",\"Erreurs fréquentes (forum = tout, panne imaginaire, session laissée ouverte) et comment les corriger.\"],\"acquired\":[\"Vous savez réagir de façon raisonnable si une rubrique manque : contexte, rôle, puis demande au staff.\",\"Vous distinguez référence documentaire et discussion ; vous connaissez les gestes de sécurité du compte.\"],\"nextHint\":\"Enchaînez avec le module « Navigation et compte » : tableau de bord, menus, profil, préférences et multi-communautés.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Bienvenue sur le portail\",\"subtitle\":\"Formation d’accueil — lecture active\",\"body\":\"<p>Ce site regroupe ce dont vous avez besoin pour suivre la vie de votre communauté : <strong>consignes stabilisées</strong> (documents), <strong>échanges</strong> (forum), <strong>compétences</strong> (formations), <strong>coordination</strong> (événements, pointage selon les réglages) et <strong>votre dossier</strong> (personnel). Ce parcours vise un seul résultat : que vous sachiez <em>où</em> chercher l’information et <em>comment</em> agir sans improviser.</p><p>Les textes sont longs volontairement : ce n’est pas une brochure marketing, c’est un mode d’emploi. Si une rubrique n’existe pas chez vous, c’est souvent lié aux droits ou à la configuration — ce n’est pas une erreur de parcours de votre part.</p>\",\"contextKicker\":\"Étape 01 · Cadrage\",\"surface\":\"elevated\",\"metric\":{\"label\":\"Pour qui\",\"value\":\"Tous les membres\"},\"cards\":[{\"label\":\"Documents\",\"body\":\"Notes et fichiers de référence, retrouvables et mis à jour par le staff.\"},{\"label\":\"Forum & annonces\",\"body\":\"Échanges et relances ; ce n’est pas le stockage des versions finales.\"},{\"label\":\"Formations\",\"body\":\"Parcours tracés, parfois obligatoires ou certifiants selon les règles.\"}],\"insights\":[{\"variant\":\"key\",\"title\":\"\",\"body\":\"Le portail oriente : tableau de bord et menu reflètent ce que votre rôle permet de voir.\"}]},{\"template\":\"reading_article\",\"title\":\"À quoi sert concrètement ce portail ?\",\"subtitle\":\"Stabiliser l’information, pas la noyer\",\"contextKicker\":\"Étape 02 · Lecture\",\"surface\":\"default\",\"insights\":[{\"variant\":\"vigilance\",\"title\":\"\",\"body\":\"Si une rubrique manque, vérifiez la communauté active et votre affectation avant de conclure à une « panne ».\"}],\"body\":\"<p>Le portail répond à un problème simple : lorsque chacun va chercher l’information sur des canaux informels, les versions se multiplient, les retardataires ne voient pas les mises à jour, et le staff passe son temps à répéter la même consigne. Ici, l’objectif est que la <strong>version de référence</strong> vive dans des endroits identifiables : documents publiés, fils de discussion classés, formations suivies et tracées.</p>\\n<p>Après connexion, vous n’êtes pas censé « explorer au hasard » : le <strong>tableau de bord</strong> et le <strong>menu</strong> vous orientent vers ce qui est ouvert pour votre rôle. Vous pouvez aussi disposer d’une zone regroupant les modules d’<strong>opérations</strong> : lieu central de mission, briefings, organigramme, outils tactiques selon ce que votre communauté a activé. Ce n’est pas décoratif : ce sont des raccourcis pour éviter les détours.</p>\\n<p>Le portail ne remplace pas le jugement ni la chaîne de commandement : il <strong>porte</strong> l’information et la formation. Une note officielle reste une note officielle ; un message sur le forum reste un échange ; une formation indique ce que vous avez parcouru et validé, pas votre valeur opérationnelle au sens tactique.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--info\\\"><p><strong>À retenir</strong> : si vous ne voyez pas une rubrique mentionnée dans ce parcours, commencez par vérifier que vous êtes dans la bonne communauté (lorsque vous en avez plusieurs), puis demandez au staff si l’accès est normal ou s’il manque une affectation de rôle.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Déroulé de ce parcours et méthode de travail\",\"subtitle\":\"Lectures, bilan interrogé, puis validation finale\",\"body\":\"<p>Ce parcours enchaîne plusieurs modules de lecture, un <strong>bilan interrogé à mi-parcours</strong> pour ancrer les premiers acquis, puis le module sur la vie collective (forum, événements) et enfin la <strong>validation finale</strong>. L’ordre est logique : d’abord la vision d’ensemble et la sécurité du compte, ensuite la navigation quotidienne, puis les contenus « métier » (personnel, documents, formations), avant le bilan, le collectif et la manière dont le site atteste vos acquis.</p>\\n<h3>Comment lire efficacement</h3>\\n<p>Utilisez les boutons <strong>Précédent</strong> et <strong>Suivant</strong> sous les diapositives. Ne cherchez pas à « swiper » trop vite : plusieurs écrans contiennent des nuances importantes (par exemple la différence entre un document officiel et un fil de discussion). Lorsqu’un <strong>texte à trous</strong> apparaît, complétez-le avant de valider : c’est un mini-test de vocabulaire intégré au parcours.</p>\\n<h3>Si quelque chose reste flou pour votre unité</h3>\\n<p>Notez la question pendant la lecture, puis posez-la sur le canal prévu par votre organisation (référent, réunion, fil dédié). Ce parcours décrit le fonctionnement général du portail ; votre unité peut avoir des conventions supplémentaires (horaires, niveaux de diffusion, procédure de validation des absences, etc.).</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--tip\\\"><p><strong>Erreur fréquente</strong> : croire que « tout est sur le forum ». Le forum sert à débattre, annoncer, relancer ; les fichiers de référence et les textes stabilisés doivent vivre dans la rubrique documents (ou équivalent) lorsque le staff les y place.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"À l’issue du parcours complet, vous saurez…\",\"subtitle\":\"Objectifs opérationnels\",\"body\":\"<ul>\\n<li>expliquer à un nouveau membre à quoi sert le tableau de bord et comment retrouver l’aide ou la documentation du site ;</li>\\n<li>mettre à jour vous-même profil, préférences et sécurité du compte sans demander au staff pour chaque détail ;</li>\\n<li>ouvrir la rubrique documents, comprendre pourquoi un fichier peut être masqué, et ne pas rediffuser un contenu sensible hors des canaux prévus ;</li>\\n<li>parcourir le catalogue des formations, distinguer inscription libre et assignation par le staff, et reprendre un module en cours ;</li>\\n<li>participer au forum sans saturer les catégories ni ignorer les annonces officielles ;</li>\\n<li>traiter un événement comme un engagement : inscription, prévenance en cas d’empêchement, respect des consignes de présence ;</li>\\n<li>réussir le bilan interrogé à mi-parcours puis le questionnaire final, et utiliser les explications affichées pour réviser en cas d’échec ;</li>\\n<li>comprendre ce que signifient pour vous une formation <strong>obligatoire</strong> et une formation <strong>certifiante</strong>, ainsi que le rôle de l’attestation.</li>\\n</ul>\\n<p>Ce n’est pas une liste à décorer : c’est le socle minimal attendu d’un membre qui utilise le portail au quotidien.</p>\"},{\"template\":\"reading_article\",\"title\":\"Ce que le portail n’est pas\",\"subtitle\":\"Éviter les malentendus d’usage\",\"contextKicker\":\"Étape 03 · Cadrage\",\"body\":\"<p>Le portail <strong>n’est pas</strong> un substitut à la chaîne de commandement ni au jugement sur le terrain : il porte l’information et la formation, pas l’autorité opérationnelle.</p>\\n<p>Il <strong>n’est pas</strong> un espace où toute note officielle peut rester définitivement dans un fil de discussion : la version stabilisée appartient aux documents (ou équivalent) lorsque le staff y procède.</p>\\n<p>Il <strong>n’est pas</strong> une messagerie personnelle : les échanges publics ou de service suivent des règles de canal ; les sujets sensibles passent par les procédures prévues.</p>\\n<p>Enfin, une formation validée sur le site <strong>n’est pas</strong>, à elle seule, une reconnaissance tacite de toutes les compétences métier : elle atteste du parcours réalisé selon les règles affichées.</p>\"},{\"template\":\"common_mistakes\",\"title\":\"Erreurs d’usage les plus fréquentes\",\"mistakes\":[{\"error\":\"Tout centraliser sur le forum\",\"why\":\"Le forum est conçu pour la conversation et les relais, pas pour remplacer la rubrique documents.\",\"consequence\":\"Versions multiples, fils longs, nouveaux membres qui ne retrouvent pas la référence.\",\"correction\":\"Demander ou attendre la publication dans les documents lorsque le staff valide un texte de référence.\"},{\"error\":\"Conclure trop vite à une « panne » du site\",\"why\":\"Souvent, une rubrique absente correspond à des droits, à une autre communauté active ou à une fonction non activée.\",\"consequence\":\"Messages d’alerte publics inutiles et temps perdu pour le staff.\",\"correction\":\"Vérifier le contexte (communauté, rôle), puis s’adresser au canal prévu pour le support.\"},{\"error\":\"Négliger la déconnexion sur poste partagé\",\"why\":\"La session peut rester ouverte pour le prochain utilisateur du même équipement.\",\"consequence\":\"Accès au compte et aux contenus au nom de la mauvaise personne.\",\"correction\":\"Utiliser la déconnexion explicite du portail en fin de session.\"}]},{\"template\":\"scenario_decision\",\"title\":\"Je ne trouve pas une rubrique mentionnée dans ce parcours\",\"context\":\"Vous suivez la formation ; un encadré cite une page (documents, organigramme, etc.) que vous ne voyez pas dans votre menu.\",\"situation\":\"<p>Vous devez agir rapidement pour un sujet opérationnel. Vous pensez que le site est « cassé ».</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Vérifier la communauté active et, si besoin, demander au staff si l’accès est normal pour votre rôle avant de conclure.\"},{\"id\":\"b\",\"text\":\"Publier immédiatement un message d’alerte dans toutes les catégories du forum.\"},{\"id\":\"c\",\"text\":\"Partager vos identifiants avec un camarade pour qu’il teste depuis son compte.\"},{\"id\":\"d\",\"text\":\"Abandonner toute utilisation du portail jusqu’à nouvel ordre.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>La première démarche raisonnable est de contrôler le <strong>contexte</strong> (communauté, rôle) puis de solliciter le staff sur le canal prévu. Les autres options créent du bruit, un risque de sécurité ou une interruption inutile de travail.</p>\"},{\"template\":\"title_hero\",\"title\":\"Sécurité : les bases\",\"subtitle\":\"Gestes simples, effet collectif\",\"body\":\"<p>Un compte compromis ou une session laissée ouverte sur un poste partagé, ce n’est pas « une affaire personnelle » : c’est un risque pour toute la communauté (usurpation, fuite de consignes, spam). Les bons réflexes sont courts : mot de passe sérieux, déconnexion explicite, prudence sur les copies d’écran et les transferts hors site.</p>\",\"primaryAction\":{\"type\":\"modal\",\"label\":\"Voir la liste des rappels\",\"modalId\":\"onb-sec\"}},{\"template\":\"resources_list\",\"title\":\"Accès directs après connexion\",\"subtitle\":\"Liens utiles\",\"body\":\"<p>Si un lien ne fonctionne pas, votre site peut utiliser une adresse légèrement différente : repassez alors par le menu principal.</p>\",\"resources\":[{\"title\":\"Tableau de bord\",\"url\":\"/public/dashboard\"},{\"title\":\"Documentation du portail\",\"url\":\"/public/documentation\"}]},{\"template\":\"reading_article\",\"title\":\"Avant de passer au module suivant\",\"subtitle\":\"Prenez le temps de l’ancrage\",\"body\":\"<p>La suite du parcours entre dans le détail de la navigation et du compte. Si vous avez sauté des paragraphes, revenez en arrière : les modules suivants supposent que vous savez déjà ce qu’est le tableau de bord, pourquoi les documents ne sont pas interchangeables avec le forum, et pourquoi la sécurité du compte est une responsabilité partagée.</p>\"}]}', NULL, 17, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(19, 19, 'Navigation et compte — parcours visuel', 'Menu principal, zone Opérations, tableau de bord, compte, préférences, recherche, bonnes pratiques.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module pratique\",\"title\":\"\",\"lead\":\"Menus, tableau de bord, compte et recherche : les bons réflexes pour ne pas perdre le fil au quotidien.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~28 min\"},{\"label\":\"Focus\",\"value\":\"Repérage + compte\"}]},\"closure\":{\"title\":\"Synthèse — Navigation et compte\",\"seen\":[\"Tableau de bord comme premier arrêt ; menu et zone Opérations selon les droits.\",\"Procédure type de mise à jour du profil et des préférences dans la rubrique compte.\",\"Comparaison poste personnel / poste partagé et conduite en cas de page invisible.\"],\"acquired\":[\"Vous savez enchaîner les étapes pour tenir votre compte à jour sans improvisation.\",\"Vous évitez les erreurs de contexte entre communautés et les sessions ouvertes sur poste partagé.\"],\"nextHint\":\"Poursuivez avec « Organisation et contenus » : personnel, documents, formations et attestations.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Navigation et compte\",\"subtitle\":\"Lire le site comme un outil de travail\",\"body\":\"<p>Le <strong>menu principal</strong> n’est pas une vitrine : c’est la liste des fonctions auxquelles votre rôle a droit. Les intitulés sont volontairement lisibles (accueil, formations, forum, personnel, documents…). Sur grand écran, vous pouvez aussi avoir un menu regroupant les <strong>opérations</strong> : lieu central de mission, pointage, briefings, organigramme, outils tactiques — selon ce que votre communauté a activé. Sur mobile, le même contenu est souvent dans un menu latéral ou derrière une icône « menu ».</p><p>L’habitude à prendre : avant de poster ou de répondre, vérifiez que vous êtes au bon endroit dans le site (bonne communauté, bonne rubrique).</p>\",\"contextKicker\":\"Étape 01 · Structure du site\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Menu principal\",\"body\":\"Accès aux rubriques autorisées pour votre rôle.\"},{\"label\":\"Zone Opérations\",\"body\":\"Raccourcis tactiques et logistiques si votre communauté les active.\"},{\"label\":\"Mobile\",\"body\":\"Même logique, présentation adaptée (menu latéral ou icône).\"}]},{\"template\":\"reading_article\",\"title\":\"Tableau de bord : votre premier arrêt\",\"subtitle\":\"Synthèse, pas détail tactique\",\"body\":\"<p>Le <strong>tableau de bord</strong> est l’écran qui accueille souvent la session après connexion. Il ne remplace pas une carte d’opération ni un ordre écrit : il <strong>signale</strong> ce qui mérite attention pour votre compte — raccourcis vers des pages utiles, rappels de formations en cours ou à venir, parfois les prochains événements ou des messages du staff selon la configuration.</p>\\n<p>Traitez-le comme la « une » du portail pour <em>vous</em> : deux minutes suffisent à repérer si une date limite approche, si une formation obligatoire attend une action, ou si une annonce récente a été mise en avant. Si le tableau de bord est vide, cela ne veut pas dire qu’il ne se passe rien dans la communauté : ouvrez le forum, les documents ou le calendrier selon votre fonction.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--tip\\\"><p><strong>Bon réflexe</strong> : à chaque retour sur le site, passez par le tableau de bord avant d’aller sur les réseaux sociaux ou messageries externes — la consigne officielle est ici en premier.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Compte, profil, préférences et sécurité\",\"subtitle\":\"Ce que vous contrôlez vous-même\",\"body\":\"<p>La rubrique <strong>compte</strong> (souvent « Mon compte » ou « Paramètres ») concentre tout ce qui touche à <em>votre</em> présence sur le portail. Elle sert à trois grandes familles d’actions.</p>\\n<h3>Profil et identité affichée</h3>\\n<p>Selon les règles de votre communauté, certaines informations peuvent être visibles par le staff ou d’autres membres (nom affiché, affectation, champs de dossier). Les mettre à jour quand elles changent évite les erreurs d’affectation et les convocations à mauvais escient.</p>\\n<h3>Préférences</h3>\\n<p>Notifications, affichage, parfois choix de ce que vous acceptez de montrer : ce sont des réglages personnels. Si vous désactivez tout sans le vouloir, vous raterez des rappels légitimes ; si vous laissez tout ouvert sur un canal bruyant, vous finirez par ignorer les messages importants. Trouvez un équilibre et révisez-le après une grosse période d’activité.</p>\\n<h3>Sécurité</h3>\\n<p>Mot de passe, confirmation d’adresse de contact, parfois la liste des appareils reconnus : toute modification sensible peut déclencher une vérification supplémentaire. C’est normal. Gardez une adresse de contact <strong>valide</strong> : c’est le filet de sécurité si vous perdez l’accès.</p>\"},{\"template\":\"reading_article\",\"title\":\"Recherche et multi-organisations\",\"subtitle\":\"Éviter les doublons et les erreurs de contexte\",\"body\":\"<p>Lorsque la recherche est disponible, utilisez-la avant de créer un nouveau sujet sur le forum ou avant de redemander un document : souvent, le fil ou le fichier existe déjà. Les résultats respectent vos droits : si quelque chose n’apparaît pas, ce n’est pas forcément qu’il n’existe pas — il peut être simplement hors de votre périmètre.</p>\\n<p>Si vous participez à <strong>plusieurs communautés</strong> sur la même plateforme, un écran de choix peut s’afficher à la connexion. L’erreur classique est de répondre à un briefing ou de signer une présence alors qu’on est encore « dans » l’autre organisation. Vérifiez l’en-tête du site ou le sélecteur avant toute action engageante.</p>\"},{\"template\":\"fill_blanks\",\"title\":\"Vérification rapide\",\"contextKicker\":\"Étape intermédiaire · Auto-évaluation\",\"metric\":{\"label\":\"Validation\",\"value\":\"Réponses exactes requises\"},\"body\":\"<p>Après connexion, l’écran qui regroupe en général raccourcis et rappels utiles pour votre session est le [[tableau de bord]].</p><p>Pour le mot de passe, les préférences et les réglages du compte, ouvrez la section <strong>compte</strong> (souvent intitulée « Mon compte ») depuis le menu principal.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Repères pour le quotidien\",\"body\":\"Revoyez périodiquement vos préférences de notification : un rappel de formation ou d’événement se joue souvent sur un simple e-mail ou une alerte interne.\\nSi un libellé de menu vous échappe, ouvrez la rubrique plutôt que d’ignorer : les intitulés sont pensés pour le langage courant.\\nSur un poste partagé, déconnectez-vous explicitement ; fermer l’onglet ne suffit pas toujours.\\nAvant d’ouvrir un nouveau fil sur le forum, recherchez ou parcourez la catégorie pour éviter les doublons.\\nSi une page refuse l’accès, considérez que votre rôle n’inclut peut-être pas cette fonction : demandez au staff au lieu d’essayer de contourner.\"},{\"template\":\"process_steps\",\"title\":\"Procédure type : mettre à jour son profil et ses préférences\",\"steps\":[{\"title\":\"Ouvrir la rubrique compte\",\"action\":\"Depuis le menu principal, accédez à « Mon compte » (ou libellé équivalent).\",\"vigilance\":\"Vérifiez que vous êtes dans la bonne communauté si vous en avez plusieurs.\"},{\"title\":\"Parcourir les sections proposées\",\"action\":\"Identifiez profil (identité affichée, champs de dossier), préférences (notifications, affichage) et sécurité (mot de passe, contact).\",\"vigilance\":\"Ne modifiez le mot de passe ou l’adresse de contact que si vous pouvez assumer la confirmation demandée par le site.\"},{\"title\":\"Enregistrer et contrôler\",\"action\":\"Validez les changements ; relisez les messages de confirmation ou les e-mails de vérification.\",\"vigilance\":\"Un contact obsolète bloque souvent la récupération d’accès en cas de problème.\"}]},{\"template\":\"role_scope_compare\",\"title\":\"Poste personnel et poste partagé\",\"memberView\":\"<p>Sur <strong>votre</strong> ordinateur ou appareil personnel, vous gérez la session comme d’habitude : fermeture du navigateur peut suffire selon les réglages, mais la déconnexion du portail reste recommandée si d’autres applications sont ouvertes.</p>\",\"staffView\":\"<p>Pour le <strong>staff</strong>, l’enjeu est le même au niveau collectif : rappeler la déconnexion sur les postes de permanence ou salles partagées fait partie du bon usage du service.</p>\",\"rightsNote\":\"<p>Les droits d’accès (menu, rubriques) ne changent pas selon le type de machine : ils dépendent du <strong>compte</strong> et du <strong>rôle</strong>. En revanche, le <strong>risque</strong> de session laissée ouverte est maximal sur un poste partagé.</p>\",\"notAnomaly\":\"<p>Que le site vous demande une reconnexion après une durée d’inactivité n’est pas une anomalie : c’est souvent une protection de session.</p>\"},{\"template\":\"scenario_decision\",\"title\":\"Une page du parcours ne s’affiche pas pour vous\",\"context\":\"Un encadré de formation cite une page (recherche, organigramme, etc.) ; chez vous le menu ne propose pas la même chose qu’illustré.\",\"situation\":\"<p>Vous devez compléter une tâche qui, selon vous, nécessite cette page.</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Contrôler la communauté active et demander au staff si l’accès est normal pour votre affectation avant d’alerter tout le monde.\"},{\"id\":\"b\",\"text\":\"Installer un outil tiers pour « forcer » l’affichage du site.\"},{\"id\":\"c\",\"text\":\"Utiliser le compte d’un autre membre pour entrer à sa place.\"},{\"id\":\"d\",\"text\":\"Publier sur le forum que le portail est inutilisable sans plus de précision.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>La conduite attendue combine <strong>vérification du contexte</strong> et <strong>escalade par le canal prévu</strong>. Les autres options exposent la sécurité ou créent du bruit.</p>\"},{\"template\":\"resources_list\",\"title\":\"Raccourcis fréquents\",\"subtitle\":\"\",\"body\":\"\",\"resources\":[{\"title\":\"Tableau de bord\",\"url\":\"/public/dashboard\"},{\"title\":\"Mon compte\",\"url\":\"/public/account\"},{\"title\":\"Préférences\",\"url\":\"/public/account/preferences\"},{\"title\":\"Recherche\",\"url\":\"/public/search\"}]}]}', NULL, 19, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(20, 20, 'Organisation et contenus — parcours visuel', 'Fiche personnelle, organigramme, documents officiels, catalogue LMS, progression et erreurs fréquentes.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Contenus structurés\",\"title\":\"\",\"lead\":\"Personnel, documents et formations : où vit l’information « durable » et comment la progression est enregistrée.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~32 min\"},{\"label\":\"Thème\",\"value\":\"Organisation\"}]},\"closure\":{\"title\":\"Synthèse — Organisation et contenus\",\"seen\":[\"Distinction nette : dossier personnel, organigramme, documents de référence, catalogue des formations.\",\"Cas pratiques : document sensible, version obsolète, formation assignée mais non terminée.\",\"Attestation : ce qu’elle atteste sur le portail et ce qu’elle ne remplace pas.\"],\"acquired\":[\"Vous savez pourquoi un contenu peut être invisible selon le rôle et que ce n’est pas forcément une erreur.\",\"Vous distinguez progression réelle et intention ; assignation vs inscription libre.\"],\"nextHint\":\"Passez au bilan interrogé de mi-parcours, puis au module sur le forum et les événements.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Organisation et contenus\",\"subtitle\":\"Personnel, documents, formations : la chaîne de l’information\",\"body\":\"<p>Ce module décrit comment le portail porte l’information « durable » : <strong>qui vous êtes dans l’unité</strong>, <strong>où sont les fichiers de référence</strong>, et <strong>comment le site enregistre ce que vous avez appris</strong>. Ce que vous voyez dépend de votre rôle ; l’absence d’accès n’est pas une punition, c’est en général un périmètre de diffusion.</p>\",\"contextKicker\":\"Étape 01 · Chaîne d’information\",\"surface\":\"elevated\",\"metric\":{\"label\":\"Principe\",\"value\":\"Périmètre selon le rôle\"},\"insights\":[{\"variant\":\"result\",\"title\":\"\",\"body\":\"Objectif : savoir où mettre à jour votre dossier et où trouver la version officielle d’un texte.\"}]},{\"template\":\"reading_article\",\"title\":\"Personnel et organigramme\",\"subtitle\":\"Dossier individuel et structure collective\",\"body\":\"<p>L’espace <strong>personnel</strong> relie votre compte de connexion à votre <strong>dossier</strong> tel que la communauté le tient : affectation, fonctions affichées, champs que le staff a demandés de remplir, parfois pièces ou validations selon les processus en place. Une fiche incomplète ou périmée produit des erreurs réelles : mauvaise convocation, mauvais groupe, retard sur une exigence administrative.</p>\\n<p>L’<strong>organigramme</strong> donne une vue de la structure et des rattachements. Il aide à savoir à qui s’adresser pour un sujet donné, mais il ne remplace pas un ordre du jour ou une note officielle : c’est une photographie organisationnelle, pas la doctrine complète.</p>\"},{\"template\":\"reading_article\",\"title\":\"Documents : la version de référence\",\"subtitle\":\"Pourquoi ce n’est pas « comme le forum »\",\"contextKicker\":\"Étape clé · Référence vs discussion\",\"surface\":\"default\",\"cards\":[{\"label\":\"Documents\",\"body\":\"Textes et fichiers stabilisés, avec contrôle de diffusion.\"},{\"label\":\"Forum\",\"body\":\"Conversation vivante : annonces, questions, relances.\"},{\"label\":\"Erreur fréquente\",\"body\":\"Publier la « version finale » uniquement dans un fil de discussion.\"}],\"body\":\"<p>La rubrique <strong>documents</strong> sert à publier ce qui doit rester <strong>stable</strong> et <strong>retrouvable</strong> : notes, guides, modèles, visuels autorisés, parfois packs techniques. Chaque dossier ou fichier peut avoir un niveau de diffusion différent ; si vous ne voyez pas un contenu, c’est souvent qu’il est réservé à un autre groupe.</p>\\n<p>Le <strong>forum</strong>, lui, vit par messages successifs : on y annonce, on débat, on relance. Un fil n’est pas un bon endroit pour « stocker » la version finale d’un texte : il se noie, on ne sait plus laquelle est la bonne page, et les nouveaux arrivants ne remontent pas 200 messages. En pratique, lorsque le staff valide un document, il doit vivre dans la rubrique documents (ou équivalent) ; le forum sert à expliquer le contexte ou à répondre aux questions.</p>\\n<p>Ne recopiez pas un fichier sensible sur une messagerie personnelle ou un stockage privé : vous perdez le contrôle de la diffusion et vous contournez les traces prévues par l’organisation.</p>\\n<div class=\\\"lms-reading-callout lms-reading-callout--info\\\"><p><strong>À retenir</strong> : document = référence stabilisée ; forum = conversation. Si les deux se mélangent, l’information se dégrade pour tout le monde.</p></div>\"},{\"template\":\"reading_article\",\"title\":\"Formations et catalogue LMS\",\"subtitle\":\"Inscription, assignation, progression, obligation\",\"body\":\"<p>Le <strong>catalogue</strong> liste les parcours auxquels vous pouvez accéder. Deux grands cas : vous vous inscrivez vous-même à une formation ouverte, ou le staff vous <strong>assigne</strong> un parcours (souvent avec une attente de complétion dans un délai). La fiche indique en général la durée estimée, le niveau, et si le parcours est <strong>obligatoire</strong> et/ou <strong>certifiant</strong>.</p>\\n<p>À l’intérieur d’un parcours, les <strong>modules</strong> et <strong>leçons</strong> peuvent être verrouillés dans un ordre : respectez-le, sinon vous risquez de croire avoir « tout vu » alors qu’une étape bloquante manque encore. Le site enregistre la progression : vous pouvez fermer la session et reprendre, mais une formation n’est réellement terminée que lorsque toutes les étapes requises le sont — le système reflète le parcours effectif, pas l’intention.</p>\\n<p>Les parcours « canvas » comme celui-ci se lisent diapositive par diapositive ; d’autres formations mélangent texte, média, quiz intermédiaires. Le principe reste le même : chaque étape a une fonction pédagogique ou réglementaire.</p>\"},{\"template\":\"reading_article\",\"title\":\"Déroulé type d’un parcours sur le portail\",\"subtitle\":\"De l’ouverture à l’attestation\",\"body\":\"<p><strong>Ouverture.</strong> Vous accédez à la fiche formation après inscription ou assignation. Lisez l’introduction et les objectifs : elles disent ce que le staff attend comme résultat.</p>\\n<p><strong>Modules.</strong> Vous enchaînez les leçons selon les règles du parcours. Certaines sont de la lecture, d’autres des exercices ou des questionnaires partiels.</p>\\n<p><strong>Évaluation.</strong> Un quiz ou une épreuve finale peut exiger un score minimal. Les tentatives sont en nombre limité : utilisez les retours du questionnaire pour combler vos lacunes avant de retenter.</p>\\n<p><strong>Clôture.</strong> Lorsque tout est validé, le parcours est marqué comme terminé. Si la formation est certifiante, une <strong>attestation</strong> ou un équivalent peut être proposé selon les réglages de votre communauté.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : document sensible et diffusion\",\"caseText\":\"<p>Un fichier marqué restreint circule dans une messagerie personnelle externe « pour aller plus vite ». Un membre vous demande la « bonne » copie.</p>\",\"analysis\":\"<p>La diffusion hors des espaces prévus fait perdre la maîtrise des accès et la traçabilité attendue par l’organisation.</p>\",\"goodConduct\":\"<p>Ne pas recopier le fichier sur un canal non autorisé. Orienter vers la rubrique documents ou vers le staff si l’accès manque. Signaler la fuite si les règles internes l’exigent.</p>\",\"conclusion\":\"<p>La rapidité ne doit pas se faire au détriment du périmètre de diffusion défini par la communauté.</p>\"},{\"template\":\"common_mistakes\",\"title\":\"Document obsolète ou douteux\",\"mistakes\":[{\"error\":\"Recirculer une ancienne version « au cas où »\",\"why\":\"Plusieurs versions coexistent déjà ; en ajouter une informelle aggrave la confusion.\",\"consequence\":\"Des équipes travaillent sur des textes différents au même titre.\",\"correction\":\"Signaler au référent ou au staff ; laisser la mise à jour officielle dans la rubrique documents.\"},{\"error\":\"Considérer qu’une formation « presque finie » suffit\",\"why\":\"Le système enregistre les étapes réellement accomplies ; une obligation reste une obligation.\",\"consequence\":\"Retard sur l’exigence collective et rappels répétés du staff.\",\"correction\":\"Repérer les leçons ou quiz restants sur la fiche formation et les terminer dans le délai fixé.\"}]},{\"template\":\"reading_article\",\"title\":\"Attestation : ce qu’elle prouve et ce qu’elle ne prouve pas\",\"subtitle\":\"Lecture institutionnelle\",\"body\":\"<p>Lorsqu’une formation est <strong>certifiante</strong> et que vous avez accompli toutes les étapes requises (y compris les scores minimaux aux questionnaires), le portail peut délivrer une <strong>attestation</strong> (ou équivalent) selon les réglages de votre communauté.</p>\\n<p><strong>Ce que cela prouve en général</strong> : vous avez validé le parcours tel qu’il est conçu sur le site, aux dates enregistrées.</p>\\n<p><strong>Ce que cela ne prouve pas automatiquement</strong> : une habilitation opérationnelle spécifique, une clearance, ou toute compétence que seule votre unité peut reconnaître hors du LMS. L’attestation et le dossier métier peuvent coexiger : l’un ne remplace pas l’autre.</p>\"},{\"template\":\"role_scope_compare\",\"title\":\"Pourquoi un même contenu n’est pas visible pour tout le monde\",\"memberView\":\"<p>Un membre voit les dossiers, documents et formations correspondant à <strong>son rôle</strong> et aux <strong>niveaux de diffusion</strong> choisis par le staff. Certaines fiches ou fichiers sont volontairement limités à un groupe.</p>\",\"staffView\":\"<p>Le staff dispose en général d’outils d’administration ou de modération pour publier, retirer ou restreindre un contenu. La visibilité est une décision d’organisation, pas une préférence personnelle du site.</p>\",\"rightsNote\":\"<p>Si vous changez de fonction ou d’affectation, votre périmètre peut évoluer après mise à jour des rôles : ce n’est pas une punition, c’est l’alignement des accès.</p>\",\"notAnomaly\":\"<p>Deux camarades avec des rôles différents peuvent légitimement ne pas voir les mêmes rubriques : ce n’est pas systématiquement un dysfonctionnement.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Ce qui compte vraiment côté organisation\",\"body\":\"Les contenus sensibles restent dans les espaces prévus ; ne les faites pas migrer vers des canaux privés non maîtrisés.\\nUne formation obligatoire doit être traitée dans les délais fixés par le staff : l’outil permet de suivre l’avancement.\\nConsultez régulièrement votre espace formations pour voir les assignations et les rappels.\\nL’organigramme oriente ; il ne remplace pas une consigne écrite ou un ordre de mission.\\nSi un document semble faux ou obsolète, signalez-le au responsable plutôt que de le recirculer.\"},{\"template\":\"resources_list\",\"title\":\"Accès directs\",\"subtitle\":\"\",\"body\":\"\",\"resources\":[{\"title\":\"Ma fiche personnelle\",\"url\":\"/public/personnel/me\"},{\"title\":\"Organigramme\",\"url\":\"/public/orbat\"},{\"title\":\"Documents\",\"url\":\"/public/documents\"},{\"title\":\"Catalogue des formations\",\"url\":\"/public/formations\"}]}]}', NULL, 21, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26');
INSERT INTO `training_lessons` (`id`, `module_id`, `title`, `summary`, `learning_objectives`, `instructor_notes`, `lesson_type`, `content`, `external_url`, `duration_minutes`, `difficulty`, `position`, `is_required`, `created_at`, `updated_at`) VALUES
(21, 21, 'Communauté — parcours visuel', 'Forum, annonces, événements, pointage, signalements, résumé des bons réflexes.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Vie collective\",\"title\":\"\",\"lead\":\"Forum, événements, annonces : des règles simples pour que l’information reste utile à toute l’unité.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~26 min\"},{\"label\":\"Enjeu\",\"value\":\"Canaux et rigueur\"}]},\"closure\":{\"title\":\"Synthèse — Communauté\",\"seen\":[\"Quand poster publiquement et quand passer par un canal dédié ou un signalement.\",\"Titres de sujet utiles vs vagues ; annonce officielle vs conversation libre.\",\"Cas types : doublon sur le forum, absence non signalée à un événement inscrit.\"],\"acquired\":[\"Vous réduisez le bruit informationnel par des réflexes simples (recherche, titre, prévenance).\",\"Vous savez qu’un engagement sur un créneau inscrit est une donnée logistique pour le staff.\"],\"nextHint\":\"Il reste le module « Validation finale » : questionnaire, attestation et limites de ce que couvre la certification sur le portail.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Vie de communauté\",\"subtitle\":\"Coordonner sans encombrer les canaux\",\"body\":\"<p>Le <strong>forum</strong> et les <strong>événements</strong> sont les lieux où la communauté vit au quotidien : annonces, questions, briefings, débriefs, organisation logistique. La qualité collective dépend de chacun : un fil lisible vaut mieux que vingt messages redondants ; une inscription honnête vaut mieux qu’une absence non signalée.</p>\",\"contextKicker\":\"Étape 01 · Cadre\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Forum\",\"body\":\"Structurer les sujets et respecter les annonces épinglées.\"},{\"label\":\"Événements\",\"body\":\"Inscription = engagement logistique pour le staff.\"},{\"label\":\"Signalement\",\"body\":\"Canal adapté pour les sujets sensibles.\"}]},{\"template\":\"reading_article\",\"title\":\"Forum : structurer la parole collective\",\"subtitle\":\"Titres, catégories, respect\",\"body\":\"<p>Avant d’ouvrir un <strong>nouveau sujet</strong>, parcourez la catégorie et utilisez la recherche : souvent, le problème est déjà en discussion. Si vous ouvrez un fil, choisissez un <strong>titre</strong> qui dit ce que vous cherchez ou ce que vous proposez, pas une phrase vague du type « question ».</p>\\n<p>Dans le fil, allez à l’essentiel : contexte utile, question claire, proposition si vous en avez une. Le désaccord est possible, la grossièreté n’apporte rien. Les messages hors-sujet répétés, le spam et les polémiques stériles obligent le staff à modérer — ce temps-là n’est plus disponible pour vous aider sur le fond.</p>\\n<p>Lorsque le staff épingle une annonce, considérez qu’elle a force de consigne pour la période concernée : lisez-la avant de poster une question déjà traitée.</p>\"},{\"template\":\"reading_article\",\"title\":\"Événements, inscriptions et présence\",\"subtitle\":\"Engagement et logistique\",\"body\":\"<p>Les <strong>événements</strong> matérialisent des créneaux : date, lieu ou lien, description, parfois matériel attendu ou tenue. Lorsque l’inscription est demandée, elle sert à dimensionner les moyens (places, encadrement, supports). S’inscrire « pour voir » puis ne pas venir sans prévenir dégrade la confiance et fait perdre du temps.</p>\\n<p>Si vous ne pouvez pas venir, <strong>prévenez</strong> selon la procédure de votre organisation (message au staff, modification de l’inscription, fil prévu). Ce n’est pas une option de politesse : c’est une donnée d’organisation.</p>\\n<p>Certaines communautés utilisent un <strong>pointage</strong> ou une feuille de présence numérique : suivez les consignes affichées sur place. Un pointage incorrect peut fausser les statistiques ou les validations administratives.</p>\"},{\"template\":\"reading_article\",\"title\":\"Annonces officielles et signalements\",\"subtitle\":\"Quand passer par un canal dédié\",\"body\":\"<p>Les annonces importantes sont souvent mises en avant en tête de forum ou sur le tableau de bord. Elles peuvent compléter une note dans les documents : l’une explique le « maintenant », l’autre stabilise le texte de référence.</p>\\n<p>Pour un problème sensible — contenu inapproprié, conflit personnel, erreur de sécurité — utilisez le <strong>canal prévu</strong> (signalement, message à un modérateur, procédure interne). Une « dénonciation » publique désordonnée crée du bruit, expose des personnes et complique la résolution.</p>\"},{\"template\":\"reading_article\",\"title\":\"Synthèse des bons réflexes\",\"subtitle\":\"À appliquer dès la première semaine\",\"body\":\"<p>Lisez les annonces avant de poster. Répondez dans le fil qui traite déjà le sujet lorsque c’est possible. Inscrivez-vous aux créneaux avec sérieux. Prévenez en cas d’empêchement. Remerciez ou synthétisez en fin de fil si cela clarifie la décision pour les suivants.</p>\\n<p>Ces gestes semblent mineurs ; cumulés sur une centaine de membres, ils font la différence entre un portail utilisable et un chaos de notifications.</p>\"},{\"template\":\"dos_donts\",\"title\":\"Canal public ou canal dédié ?\",\"dos\":[\"Poser une question générale dans la catégorie adaptée, après recherche.\",\"Utiliser le signalement ou la procédure interne pour un contenu inapproprié ou un conflit sensible.\",\"Écrire au staff sur le canal prévu pour un sujet personnel ou confidentiel.\"],\"donts\":[\"Épingler une polémique personnelle en tête de forum sans passer par la modération.\",\"Multiplier les posts identiques dans plusieurs catégories « pour être sûr d’être vu ».\",\"Diffuser des données sensibles sur un fil ouvert alors qu’un canal restreint existe.\"],\"synthesis\":\"<p>La règle simple : <strong>public</strong> pour ce qui doit être partagé et archivable par la collectivité ; <strong>canal dédié</strong> pour ce qui exige confidentialité, preuve ou traitement par le staff.</p>\"},{\"template\":\"reading_article\",\"title\":\"Titre utile, titre inutile\",\"subtitle\":\"Lisibilité collective\",\"body\":\"<p><strong>Inutile</strong> : « Question », « Urgent », « À lire » — aucun membre ne sait de quoi il s’agit sans ouvrir le fil.</p>\\n<p><strong>Utile</strong> : « Point logistique — convocation du 12 : tenue et horaire », « Document obsolète sur la fiche X : demande de retrait », « Besoin d’accès documents section Y pour la permanence ».</p>\\n<p>Le titre est le contrat de lecture avec les autres : il doit permettre de trier, d’archiver et de retrouver le sujet plus tard.</p>\"},{\"template\":\"reading_article\",\"title\":\"Annonce officielle et conversation\",\"subtitle\":\"Deux fonctions différentes\",\"body\":\"<p>Une <strong>annonce officielle</strong> (souvent épinglée ou mise en avant) fixe une consigne ou une information structurante pour une période donnée. Elle complète parfois un document de référence ; elle ne le remplace pas si la version stabilisée doit vivre dans la rubrique documents.</p>\\n<p>Une <strong>conversation</strong> sur le forum sert au débat, aux questions de détail, aux mises à jour de situation. Mélanger les deux — par exemple noyer une annonce sous des messages hors-sujet — rend la consigne illisible pour ceux qui arrivent après.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : doublon sur le forum\",\"caseText\":\"<p>Le même sujet apparaît en trois fils ouverts la même semaine dans la même catégorie. Les réponses se dispersent.</p>\",\"analysis\":\"<p>Chacun a voulu « gagner du temps » sans parcourir la catégorie ; le staff doit fusionner ou orienter, et les membres ne savent plus où lire la décision.</p>\",\"goodConduct\":\"<p>Avant d’ouvrir un sujet : recherche et lecture des fils récents. Si le sujet existe, poster dans le fil existant. Si vous avez ouvert par erreur un doublon, indiquez-le et renvoyez vers le fil principal.</p>\",\"conclusion\":\"<p>La discipline de fil unique sur un même sujet est un geste de respect du temps collectif.</p>\"},{\"template\":\"case_review\",\"title\":\"Cas : absence non signalée à un événement\",\"caseText\":\"<p>Vous étiez inscrit à un créneau ; un empêchement de dernière minute survient. Vous ne modifiez pas l’inscription et ne prévenez personne.</p>\",\"analysis\":\"<p>Le staff a dimensionné l’encadrement et le matériel sur la base des inscriptions. Une place vide non signalée est une ressource mal utilisée ; un autre membre aurait pu prendre la place.</p>\",\"goodConduct\":\"<p>Dès que l’empêchement est connu, suivre la procédure affichée (désinscription, message au référent, fil prévu). Mieux vaut prévenir tôt qu’imposer un silence au collectif.</p>\",\"conclusion\":\"<p>L’inscription à un événement est un engagement logistique, pas seulement un clic décoratif.</p>\"},{\"template\":\"fill_blanks\",\"title\":\"Une dernière vérification\",\"contextKicker\":\"Auto-évaluation\",\"metric\":{\"label\":\"Rappel\",\"value\":\"Une réponse exacte par trou\"},\"body\":\"<p>Avant d’ouvrir un nouveau sujet sur le forum, il est préférable de vérifier qu’un [[fil]] ou une discussion ne traite pas déjà le même problème.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Participation utile\",\"body\":\"Un retour sur une formation aide lorsqu’il est précis (ce qui manquait, ce qui était clair), pas lorsqu’il se limite à une critique vague.\\nPour un événement, l’empêchement se signale ; l’absence non expliquée se compte aussi.\\nNe divulguez pas des informations personnelles sur des tiers sans accord.\\nRespectez le ton fixé par votre communauté (formel, sobre, etc.).\\nEn cas de doute sur la catégorie du forum, demandez au staff avant de poster.\"}]}', NULL, 17, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(22, 22, 'Validation — parcours visuel', 'Quiz, score, tentatives, attestation, reprise de parcours et gestion du stress de l’évaluation.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Validation\",\"title\":\"\",\"lead\":\"Questionnaire final, attestation et reprise de parcours : ce qui se passe après la dernière lecture.\",\"stats\":[{\"label\":\"Seuil de réussite\",\"value\":\"80 %\"},{\"label\":\"Tentatives\",\"value\":\"Plusieurs (selon la formation)\"}]},\"closure\":{\"title\":\"Avant de lancer le questionnaire\",\"seen\":[\"Le questionnaire final couvre l’ensemble du parcours : navigation, compte, contenus, forum, événements, sécurité.\",\"Les explications après une réponse incorrecte sont une aide pédagogique : servez-vous-en avant de retenter.\",\"Validation sur le portail et habilitation métier reconnue par l’unité sont deux choses distinctes.\"],\"acquired\":[\"Vous savez organiser une reprise de révision ciblée après un échec.\",\"Vous savez ce qu’une attestation atteste — et ce qu’elle ne remplace pas.\"],\"nextHint\":\"Passez à la leçon « Quiz » du module lorsqu’elle est disponible dans votre parcours.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Dernière étape : validation\",\"subtitle\":\"Quiz de fin de parcours\",\"body\":\"<p>Le questionnaire porte sur les <strong>idées directrices</strong> du portail : navigation, compte, documents, formations, forum, événements, sécurité. Le <strong>seuil de réussite est de 80&nbsp;%</strong>. Vous disposez de <strong>plusieurs tentatives</strong> dans la limite fixée par la formation.</p><p>Les formulations volontairement longues dans certaines réponses fausses imitent des croyances courantes : lisez jusqu’au bout avant de choisir.</p>\",\"contextKicker\":\"Étape finale · Évaluation\",\"surface\":\"elevated\",\"insights\":[{\"variant\":\"vigilance\",\"title\":\"\",\"body\":\"Ne validez pas la dernière réponse si votre connexion est très instable : en cas de doute, attendez un réseau fiable.\"}]},{\"template\":\"reading_article\",\"title\":\"Après le quiz : attestation, échec, reprise\",\"subtitle\":\"Ce que le site retient de vous\",\"body\":\"<p>Si vous atteignez le score requis et que la formation est <strong>certifiante</strong>, une <strong>attestation</strong> ou un équivalent peut être proposé (téléchargement, trace sur votre dossier, selon les réglages). Ce document atteste que vous avez parcouru et validé <em>ce</em> parcours à cette date — il ne remplace pas une habilitation métier qui serait définie ailleurs.</p>\\n<p>Si vous échouez, le questionnaire affiche en général des <strong>explications</strong> sur les réponses attendues. Utilisez-les comme liste de révision : retournez sur les modules qui coincent, puis retentez. L’objectif n’est pas de vous piéger mais de vérifier que vous ne partirez pas avec de fausses certitudes (par exemple confondre forum et documents, ou ignorer la déconnexion sur poste partagé).</p>\\n<p>Conservez une copie de votre attestation si votre organisation vous la demande hors ligne ; le portail peut aussi conserver l’historique de vos formations terminées.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Avant de lancer le questionnaire\",\"body\":\"Prévoyez environ quinze à vingt minutes sans interruption.\\nInstallez-vous dans un endroit où vous pouvez lire calmement chaque énoncé.\\nSi votre connexion est instable, évitez de valider la dernière réponse au moment où le signal faiblit.\\nLes questions restent au niveau « membre du portail », pas au niveau administration technique.\\nCe parcours vous a déjà donné le vocabulaire et les situations : le quiz ne demande pas de culture générale extérieure au site.\"},{\"template\":\"reading_article\",\"title\":\"Pourquoi cette validation existe\",\"subtitle\":\"Responsabilité partagée\",\"body\":\"<p>La communauté a intérêt à ce que chaque membre sache se servir du portail correctement : moins d’erreurs de diffusion, moins de fichiers égarés, moins de questions répétitives au staff. En validant ce parcours, vous confirmez que vous connaissez les bons réflexes — pas que vous êtes infaillible, mais que vous savez où relire l’information quand un doute revient.</p>\"},{\"template\":\"scenario_decision\",\"title\":\"Vous avez réussi le quiz certifiant : que pouvez-vous en déduire ?\",\"context\":\"Le portail affiche le parcours comme terminé et propose une attestation.\",\"situation\":\"<p>Un camarade affirme que vous êtes « habilité » sur un poste sensible uniquement sur cette base.</p>\",\"options\":[{\"id\":\"a\",\"text\":\"Considérer que l’attestation couvre le parcours sur le site ; toute habilitation opérationnelle spécifique relève encore des règles de l’unité.\"},{\"id\":\"b\",\"text\":\"Conclure que l’attestation remplace toute validation métier interne sans autre formalité.\"},{\"id\":\"c\",\"text\":\"Refuser d’afficher l’attestation car elle n’a aucune valeur.\"},{\"id\":\"d\",\"text\":\"Publier l’attestation sur le forum comme preuve de clearance.\"}],\"correctOptionId\":\"a\",\"explanation\":\"<p>L’attestation atteste la <strong>validation du parcours</strong> tel que paramétré sur le portail. Les exigences métier (affectation, validation d’un chef, clearance) restent du ressort de l’organisation : ne pas les confondre évite les malentendus.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"En cas de doute pendant le questionnaire\",\"body\":\"Si deux réponses semblent crédibles, demandez-vous laquelle correspond au réflexe « membre du portail » décrit dans ce parcours, pas à une habitude personnelle ou à une astuce technique.\\nEn cas d’échec, notez les thèmes signalés par les explications puis rouvrez les synthèses des modules concernés.\\nNe tentez pas le quiz dans des conditions de connexion très dégradées : une coupure peut interrompre la session.\\nLe score seuil est rappelé sur la fiche formation : il est identique pour tous les membres sur ce parcours.\\nAprès réussite, conservez ou téléchargez l’attestation selon les options proposées par votre communauté.\"}]}', NULL, 15, 'initiation', 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(23, 23, 'Pourquoi ce bilan', 'Fiche de révision puis questionnaire sur les trois premiers blocs avant la suite du parcours.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\">\n<h3 class=\"text-base font-bold text-slate-900\">Portée du bilan</h3>\n<p>Ce bilan porte sur les <strong>trois premiers modules</strong> : finalité du portail et cadre, navigation et compte, organisation des contenus (personnel, documents, formations). Il permet de vérifier que vous maîtrisez le vocabulaire et les réflexes avant le module <strong>Communauté</strong> et la <strong>validation finale</strong>.</p>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Fiche de révision — rappels utiles</h3>\n<ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\">\n<li><strong>Information stabilisée</strong> : documents (ou équivalent) — version de référence contrôlée par le staff.</li>\n<li><strong>Coordination vivante</strong> : forum, annonces, fils — échanges, pas stockage de la version finale d’un texte officiel.</li>\n<li><strong>Tableau de bord</strong> : synthèse après connexion ; ne remplace ni ordre écrit ni carte tactique.</li>\n<li><strong>Compte</strong> : profil, préférences, sécurité — à jour pour éviter erreurs d’affectation et perte d’accès.</li>\n<li><strong>Multi-communautés</strong> : vérifier le contexte actif avant toute action engageante.</li>\n<li><strong>Progression LMS</strong> : une formation n’est achevée que lorsque toutes les étapes requises le sont ; l’affichage reflète le parcours réel.</li>\n<li><strong>Attestation</strong> : atteste la validation du parcours sur le portail selon les règles affichées ; elle ne remplace pas une habilitation métier décidée par l’unité.</li>\n</ul>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Erreurs fréquentes à éviter</h3>\n<ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\">\n<li>Publier ou chercher la « version finale » d’une note uniquement dans un fil de discussion ancien.</li>\n<li>Conclure à une panne du site sans avoir vérifié la communauté active ou les droits de son rôle.</li>\n<li>Ignorer le tableau de bord et rater des rappels de formation ou d’événement.</li>\n<li>Laisser une session ouverte sur un poste partagé après utilisation du portail.</li>\n</ul>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Méthode pour le questionnaire</h3>\n<p>Lisez chaque question en entier. Plusieurs réponses peuvent sembler raisonnables ; une seule correspond à la conduite ou au réflexe attendu dans ce parcours. Les propositions sont mélangées à chaque affichage. En cas de doute, revoyez les synthèses « À retenir » des trois premiers modules.</p>\n</div>', NULL, 3, 'initiation', 1, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(24, 13, 'À retenir — Vue d’ensemble', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : documents, forum, formations et dossier personnel ont des rôles distincts.</li><li><strong>Bonne pratique</strong> : lire le tableau de bord en premier après connexion.</li><li><strong>Point de vigilance</strong> : une rubrique absente peut venir des droits ou de la communauté active, pas d’une « panne » systématique.</li><li><strong>Erreur fréquente</strong> : confondre conversation sur le forum et version de référence d’un texte.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(25, 14, 'À retenir — Navigation et compte', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : le menu n’affiche que ce que votre rôle autorise.</li><li><strong>Bonne pratique</strong> : vérifier la communauté active avant une action engageante.</li><li><strong>Procédure</strong> : compte → profil / préférences / sécurité selon le besoin.</li><li><strong>Vigilance</strong> : contact (e-mail) valide pour les vérifications et la récupération d’accès.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(26, 15, 'À retenir — Organisation et contenus', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : la version de référence vit dans les documents, pas dans un fil ancien du forum.</li><li><strong>Bonne pratique</strong> : signaler une erreur au responsable plutôt que rediffuser hors canal.</li><li><strong>Point clé</strong> : l’attestation atteste du parcours sur le portail, pas une habilitation métier tacite.</li><li><strong>Visibilité</strong> : l’absence d’un contenu peut être normale selon le rôle.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(27, 16, 'À retenir — Communauté', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : rechercher avant d’ouvrir un nouveau sujet.</li><li><strong>Bonne pratique</strong> : prévenir en cas d’absence à un créneau où vous étiez inscrit.</li><li><strong>Vigilance</strong> : sujets sensibles → canal prévu, pas tribune publique désordonnée.</li><li><strong>Différence</strong> : annonce officielle ≠ conversation libre.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(28, 17, 'Avant le questionnaire final', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Avant le questionnaire final</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Méthode</strong> : lire l’énoncé jusqu’au bout ; plusieurs réponses peuvent sembler crédibles.</li><li><strong>Règle</strong> : le seuil et les tentatives sont fixés sur la fiche formation.</li><li><strong>Pédagogie</strong> : en cas d’échec, utiliser les explications comme liste de révision.</li><li><strong>Clarification</strong> : la validation du parcours ne dispense pas des exigences métier de l’organisation.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(29, 24, 'Pourquoi ce bilan', 'Fiche de révision puis questionnaire sur les trois premiers blocs avant la suite du parcours.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\">\n<h3 class=\"text-base font-bold text-slate-900\">Portée du bilan</h3>\n<p>Ce bilan porte sur les <strong>trois premiers modules</strong> : finalité du portail et cadre, navigation et compte, organisation des contenus (personnel, documents, formations). Il permet de vérifier que vous maîtrisez le vocabulaire et les réflexes avant le module <strong>Communauté</strong> et la <strong>validation finale</strong>.</p>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Fiche de révision — rappels utiles</h3>\n<ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\">\n<li><strong>Information stabilisée</strong> : documents (ou équivalent) — version de référence contrôlée par le staff.</li>\n<li><strong>Coordination vivante</strong> : forum, annonces, fils — échanges, pas stockage de la version finale d’un texte officiel.</li>\n<li><strong>Tableau de bord</strong> : synthèse après connexion ; ne remplace ni ordre écrit ni carte tactique.</li>\n<li><strong>Compte</strong> : profil, préférences, sécurité — à jour pour éviter erreurs d’affectation et perte d’accès.</li>\n<li><strong>Multi-communautés</strong> : vérifier le contexte actif avant toute action engageante.</li>\n<li><strong>Progression LMS</strong> : une formation n’est achevée que lorsque toutes les étapes requises le sont ; l’affichage reflète le parcours réel.</li>\n<li><strong>Attestation</strong> : atteste la validation du parcours sur le portail selon les règles affichées ; elle ne remplace pas une habilitation métier décidée par l’unité.</li>\n</ul>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Erreurs fréquentes à éviter</h3>\n<ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\">\n<li>Publier ou chercher la « version finale » d’une note uniquement dans un fil de discussion ancien.</li>\n<li>Conclure à une panne du site sans avoir vérifié la communauté active ou les droits de son rôle.</li>\n<li>Ignorer le tableau de bord et rater des rappels de formation ou d’événement.</li>\n<li>Laisser une session ouverte sur un poste partagé après utilisation du portail.</li>\n</ul>\n<h3 class=\"text-base font-bold text-slate-900 mt-4\">Méthode pour le questionnaire</h3>\n<p>Lisez chaque question en entier. Plusieurs réponses peuvent sembler raisonnables ; une seule correspond à la conduite ou au réflexe attendu dans ce parcours. Les propositions sont mélangées à chaque affichage. En cas de doute, revoyez les synthèses « À retenir » des trois premiers modules.</p>\n</div>', NULL, 3, 'initiation', 1, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(30, 18, 'À retenir — Vue d’ensemble', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : documents, forum, formations et dossier personnel ont des rôles distincts.</li><li><strong>Bonne pratique</strong> : lire le tableau de bord en premier après connexion.</li><li><strong>Point de vigilance</strong> : une rubrique absente peut venir des droits ou de la communauté active, pas d’une « panne » systématique.</li><li><strong>Erreur fréquente</strong> : confondre conversation sur le forum et version de référence d’un texte.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(31, 19, 'À retenir — Navigation et compte', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : le menu n’affiche que ce que votre rôle autorise.</li><li><strong>Bonne pratique</strong> : vérifier la communauté active avant une action engageante.</li><li><strong>Procédure</strong> : compte → profil / préférences / sécurité selon le besoin.</li><li><strong>Vigilance</strong> : contact (e-mail) valide pour les vérifications et la récupération d’accès.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(32, 20, 'À retenir — Organisation et contenus', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : la version de référence vit dans les documents, pas dans un fil ancien du forum.</li><li><strong>Bonne pratique</strong> : signaler une erreur au responsable plutôt que rediffuser hors canal.</li><li><strong>Point clé</strong> : l’attestation atteste du parcours sur le portail, pas une habilitation métier tacite.</li><li><strong>Visibilité</strong> : l’absence d’un contenu peut être normale selon le rôle.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(33, 21, 'À retenir — Communauté', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Synthèse du module</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Règle</strong> : rechercher avant d’ouvrir un nouveau sujet.</li><li><strong>Bonne pratique</strong> : prévenir en cas d’absence à un créneau où vous étiez inscrit.</li><li><strong>Vigilance</strong> : sujets sensibles → canal prévu, pas tribune publique désordonnée.</li><li><strong>Différence</strong> : annonce officielle ≠ conversation libre.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(34, 22, 'Avant le questionnaire final', 'Synthèse courte pour ancrer les idées du module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Avant le questionnaire final</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li><strong>Méthode</strong> : lire l’énoncé jusqu’au bout ; plusieurs réponses peuvent sembler crédibles.</li><li><strong>Règle</strong> : le seuil et les tentatives sont fixés sur la fiche formation.</li><li><strong>Pédagogie</strong> : en cas d’échec, utiliser les explications comme liste de révision.</li><li><strong>Clarification</strong> : la validation du parcours ne dispense pas des exigences métier de l’organisation.</li></ul></div>', NULL, 5, 'initiation', 2, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(35, 25, 'Vue d’ensemble — parcours visuel', 'Finalité du mod, articulation avec TeamSpeak, erreurs de compréhension fréquentes.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[{\"id\":\"m1-warning\",\"title\":\"Point de méthode\",\"body\":\"<p>Ne commencez pas par copier des fichiers au hasard. Dans la plupart des cas, les échecs viennent d’une confusion entre trois éléments distincts : le jeu Arma 3, le mod Task Force Radio et le plugin TeamSpeak associé.</p>\"}],\"opening\":{\"eyebrow\":\"Module 1\",\"title\":\"\",\"lead\":\"Avant l’installation, il faut comprendre ce que vous installez réellement et pourquoi deux environnements distincts doivent fonctionner ensemble.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~12 min\"},{\"label\":\"Objet\",\"value\":\"Compréhension\"},{\"label\":\"Niveau\",\"value\":\"Débutant\"}]},\"closure\":{\"title\":\"Synthèse — Vue d’ensemble\",\"seen\":[\"TFAR n’est pas un simple fichier Arma 3 : il implique aussi un plugin côté TeamSpeak.\",\"Une installation incomplète donne souvent l’illusion que le mod est présent alors que la chaîne vocale reste rompue.\"],\"acquired\":[\"Vous savez distinguer le mod de jeu et le plugin vocal.\",\"Vous comprenez pourquoi une vérification finale est obligatoire avant emploi réel.\"],\"nextHint\":\"Poursuivez avec les prérequis techniques et la préparation du poste.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Installer Task Force Radio\",\"subtitle\":\"Comprendre la chaîne complète avant action\",\"body\":\"<p><strong>Task Force Radio</strong> est un ensemble qui relie la simulation Arma 3 à un environnement vocal externe afin de produire un comportement radio crédible : proximité, réseaux, portée, gestion des postes et discipline des communications selon les réglages retenus par l’unité.</p><p>Beaucoup d’échecs viennent d’une erreur simple : croire qu’il suffit d’activer un mod Arma 3. En réalité, le fonctionnement dépend d’au moins <strong>deux éléments</strong> : le mod côté jeu et le <strong>plugin TeamSpeak</strong>. Si l’un des deux manque ou est mal placé, la chaîne est incomplète.</p>\",\"contextKicker\":\"Étape 01 · Finalité\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Arma 3\",\"body\":\"Charge le mod et ses composants côté jeu.\"},{\"label\":\"TeamSpeak\",\"body\":\"Exécute le plugin vocal nécessaire à l’intégration.\"},{\"label\":\"Utilisateur\",\"body\":\"Doit vérifier que les deux environnements sont cohérents.\"}]},{\"template\":\"reading_article\",\"title\":\"Ce que TFAR fait concrètement\",\"subtitle\":\"Communication simulée, pas simple vocal externe\",\"body\":\"<p>Sans TFAR, un groupe peut certes parler sur un logiciel vocal, mais la simulation ne tient pas compte de la distance, du poste radio porté, ni des canaux réellement utilisés. Avec TFAR, la voix et les communications sont intégrées à la logique de jeu : parler près d’un joueur n’est pas équivalent à émettre sur un réseau radio.</p><p>Le but de cette installation n’est donc pas purement technique. Il s’agit de rendre votre poste compatible avec les procédures de communication de l’unité. Une mauvaise installation ne gêne pas seulement votre confort personnel : elle perturbe la coordination collective.</p><div class=\\\"lms-reading-callout lms-reading-callout--info\\\"><p><strong>À retenir</strong> : si TFAR est mal installé, le problème n’est pas seulement « je n’entends pas bien » ; cela peut compromettre toute une séquence radio pendant une mission.</p></div>\"},{\"template\":\"dos_donts\",\"title\":\"À faire / À ne pas faire\",\"body\":\"Préparer une installation propre évite la majorité des incidents.\",\"dos\":[\"Identifier la méthode officielle utilisée par votre unité\",\"Vérifier Arma 3, TeamSpeak et les droits d’accès au poste\",\"Garder les chemins d’installation cohérents\"],\"donts\":[\"Mélanger plusieurs versions de TFAR sans contrôle\",\"Copier un plugin ancien sans savoir sa provenance\",\"Considérer l’installation comme terminée avant test réel\"]},{\"template\":\"common_mistakes\",\"title\":\"Erreurs de compréhension fréquentes\",\"items\":[{\"title\":\"Le mod est activé, donc tout est bon\",\"body\":\"Faux : le plugin TeamSpeak peut manquer ou rester désactivé.\"},{\"title\":\"TeamSpeak fonctionne, donc TFAR aussi\",\"body\":\"Faux : TeamSpeak peut fonctionner seul sans intégration TFAR.\"},{\"title\":\"Un ami m’a envoyé un fichier, je l’ai copié\",\"body\":\"Méthode instable : sans contrôle de version, vous multipliez les conflits et les faux diagnostics.\"}]},{\"template\":\"knowledge_check\",\"title\":\"Repères de départ\",\"body\":\"TFAR repose sur une articulation entre le jeu et le logiciel vocal.\\nUn poste prêt doit être cohérent, pas partiellement installé.\\nLa validation finale passe par un test, pas par une simple présence des fichiers.\\nUne installation défaillante est un risque collectif, pas seulement individuel.\"},{\"template\":\"title_hero\",\"title\":\"Avant d’aller plus loin\",\"subtitle\":\"Méthode et discipline\",\"body\":\"<p>Suivez l’ordre logique : comprendre, préparer, installer, vérifier, corriger. Ne sautez pas directement à la fin. Une installation réussie est une installation <strong>contrôlée</strong>.</p>\",\"primaryAction\":{\"type\":\"modal\",\"label\":\"Voir le rappel méthode\",\"modalId\":\"m1-warning\"}}]}', NULL, 9, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL),
(36, 25, 'À retenir — Vue d’ensemble', 'Synthèse d’ancrage du premier module.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">À retenir</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>TFAR repose sur deux composantes : un mod Arma 3 et un plugin TeamSpeak.</li><li>Une installation partielle peut sembler correcte alors qu’elle est inutilisable en mission.</li><li>La bonne méthode consiste à préparer le poste, installer proprement, puis vérifier en situation.</li></ul></div>', NULL, 3, 'initiation', 2, 1, '2026-04-06 20:11:27', NULL),
(37, 26, 'Pré requis — parcours visuel', 'Arma 3, TeamSpeak, launcher, droits et hygiène de poste.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module 2\",\"title\":\"\",\"lead\":\"Avant l’installation, vérifiez que le poste est prêt. Beaucoup d’erreurs techniques sont en réalité des erreurs de préparation.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~14 min\"},{\"label\":\"Focus\",\"value\":\"Pré requis\"}]},\"closure\":{\"title\":\"Synthèse — Préparer le poste\",\"seen\":[\"Arma 3 et TeamSpeak doivent être présents et exploitables.\",\"Un environnement brouillé par des anciennes copies ou des droits insuffisants produit des erreurs évitables.\"],\"acquired\":[\"Vous savez ce qu’il faut contrôler avant l’installation.\",\"Vous pouvez distinguer un problème de préparation d’un problème TFAR réel.\"],\"nextHint\":\"Poursuivez avec l’installation du mod côté Arma 3.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Préparer le poste\",\"subtitle\":\"Ne pas construire sur une base instable\",\"body\":\"<p>Avant toute installation, vérifiez que votre poste remplit les conditions minimales de travail. TFAR ne corrige pas un environnement dégradé. Un jeu incomplet, un logiciel vocal absent ou une arborescence chaotique conduisent à des diagnostics faux.</p>\",\"contextKicker\":\"Étape 01 · Pré requis\",\"surface\":\"elevated\",\"cards\":[{\"label\":\"Jeu\",\"body\":\"Arma 3 installé et lancé au moins une fois.\"},{\"label\":\"Vocal\",\"body\":\"TeamSpeak installé localement et ouvrable.\"},{\"label\":\"Méthode\",\"body\":\"Voie officielle connue : workshop, pack unité ou dépôt interne.\"}]},{\"template\":\"process_steps\",\"title\":\"Contrôle préalable du poste\",\"steps\":[{\"label\":\"Étape 1\",\"title\":\"Vérifier Arma 3\",\"body\":\"Le jeu doit être installé, à jour et déjà lancé au moins une fois afin que son environnement initial soit créé correctement.\",\"note\":\"Un jeu jamais lancé peut générer des erreurs de chemin ou de détection.\"},{\"label\":\"Étape 2\",\"title\":\"Vérifier TeamSpeak\",\"body\":\"Le logiciel vocal doit être installé sur le poste et s’ouvrir normalement.\",\"note\":\"TFAR s’interface avec TeamSpeak ; sans TeamSpeak exploitable, le plugin n’a aucun effet utile.\"},{\"label\":\"Étape 3\",\"title\":\"Identifier la source du mod\",\"body\":\"Déterminez la méthode officielle imposée par l’unité : Steam Workshop, pack local, dépôt synchronisé ou autre.\",\"note\":\"Ne mélangez pas plusieurs sources sans contrôle.\"},{\"label\":\"Étape 4\",\"title\":\"Nettoyer les anciennes tentatives confuses\",\"body\":\"Supprimez ou isolez les copies anciennes, fichiers errants ou doublons si vous savez qu’ils traînent sur le poste.\",\"note\":\"Conserver plusieurs versions mal identifiées est une cause classique de panne.\"}]},{\"template\":\"role_scope_compare\",\"title\":\"Ce qui relève du jeu / du vocal / de l’utilisateur\",\"rows\":[{\"left\":\"Arma 3\",\"right\":\"Charge le mod, les fichiers et les composants côté simulation.\"},{\"left\":\"TeamSpeak\",\"right\":\"Exécute le plugin vocal associé et gère le canal vocal.\"},{\"left\":\"Utilisateur\",\"right\":\"Doit conserver une méthode propre, cohérente et conforme à la doctrine de l’unité.\"}]},{\"template\":\"case_review\",\"title\":\"Cas pratique — Poste mal préparé\",\"body\":\"<p>Un membre affirme que TFAR ne fonctionne pas. Après vérification, Arma 3 est bien installé, mais TeamSpeak ne l’est pas sur le poste concerné. Il a pourtant copié un dossier plugin provenant d’un autre PC.</p><p><strong>Analyse :</strong> le diagnostic initial est faux. Le problème ne vient pas d’abord de TFAR, mais d’un environnement incomplet. Copier un plugin sans logiciel cible exploitable n’a pas de sens opérationnel.</p><p><strong>Bonne conduite :</strong> rétablir d’abord les pré requis, puis reprendre l’installation dans l’ordre.</p>\"},{\"template\":\"fill_blanks\",\"title\":\"Contrôle rapide\",\"body\":\"<p>Avant l’installation de TFAR, il faut vérifier la présence d’<strong>Arma 3</strong> et de [[TeamSpeak]] sur le poste.</p><p>Il est préférable d’utiliser la [[méthode officielle]] retenue par l’unité plutôt que de mélanger des copies incertaines.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Réflexes de préparation\",\"body\":\"Un poste propre vaut mieux qu’une accumulation de tentatives.\\nUn ancien plugin non identifié peut perturber les vérifications.\\nLe bon ordre commence toujours par les pré requis.\\nUne panne apparente TFAR peut en réalité provenir du poste lui-même.\"}]}', NULL, 10, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL),
(38, 26, 'Fiche d’ancrage — Pré requis', 'Récapitulatif court des contrôles préalables.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">Pré requis à contrôler</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>Arma 3 installé, à jour, déjà lancé au moins une fois.</li><li>TeamSpeak installé et fonctionnel.</li><li>Méthode officielle d’installation identifiée.</li><li>Anciennes copies confuses de TFAR supprimées ou isolées.</li></ul><h4 class=\"text-sm font-bold text-slate-900 mt-5\">Erreurs à éviter</h4><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>Installer plusieurs versions en parallèle.</li><li>Copier un plugin sans savoir pour quelle version il a été prévu.</li><li>Commencer les tests avant d’avoir stabilisé l’environnement.</li></ul></div>', NULL, 4, 'initiation', 2, 1, '2026-04-06 20:11:27', NULL),
(39, 27, 'Installation Arma 3 — parcours visuel', 'Méthode générale d’intégration du mod, activation, chargement et contrôles de cohérence.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module 3\",\"title\":\"\",\"lead\":\"Vous installez maintenant le mod TFAR côté Arma 3. Cette étape ne suffit pas à elle seule, mais elle doit être propre et traçable.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~16 min\"},{\"label\":\"Périmètre\",\"value\":\"Jeu\"}]},\"closure\":{\"title\":\"Synthèse — Mod Arma 3\",\"seen\":[\"Le mod doit être présent et activé dans un cadre de version cohérent.\",\"Le launcher Arma 3 sert à contrôler ce qui est réellement chargé.\"],\"acquired\":[\"Vous savez installer ou activer TFAR côté jeu.\",\"Vous savez pourquoi une activation incohérente suffit à faire échouer la suite.\"],\"nextHint\":\"Poursuivez avec l’installation du plugin TeamSpeak, indispensable au fonctionnement réel.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Installer TFAR dans Arma 3\",\"subtitle\":\"Mod présent, activé et lisible\",\"body\":\"<p>Selon la doctrine de votre unité, le mod peut être distribué par <strong>Steam Workshop</strong>, pack local, synchronisation dédiée ou dépôt interne. La méthode exacte varie ; le principe, lui, reste le même : le mod doit être <strong>présent</strong>, <strong>reconnu</strong> et <strong>chargé</strong> par Arma 3.</p><p>Cette formation reste volontairement générique : elle vous donne la logique à respecter quel que soit le mode de distribution retenu.</p>\",\"contextKicker\":\"Étape 01 · Chargement côté jeu\",\"surface\":\"elevated\"},{\"template\":\"process_steps\",\"title\":\"Procédure générale côté Arma 3\",\"steps\":[{\"label\":\"Étape 1\",\"title\":\"Récupérer le mod via la source officielle\",\"body\":\"Téléchargez ou synchronisez TFAR uniquement depuis la source imposée par l’unité.\",\"note\":\"Évitez les archives isolées ou les partages non contrôlés.\"},{\"label\":\"Étape 2\",\"title\":\"Vérifier sa présence dans le launcher\",\"body\":\"Ouvrez le launcher Arma 3 et vérifiez que le mod apparaît clairement dans la liste des mods disponibles ou chargés.\",\"note\":\"Un dossier présent sur le disque n’est pas forcément chargé par le jeu.\"},{\"label\":\"Étape 3\",\"title\":\"Activer le mod\",\"body\":\"Activez TFAR dans le preset de lancement utilisé par l’unité.\",\"note\":\"Le bon mod doit être actif au moment du lancement réel du jeu.\"},{\"label\":\"Étape 4\",\"title\":\"Contrôler la cohérence du preset\",\"body\":\"Vérifiez qu’aucun doublon manifeste ou ancienne variante du mod n’est activée simultanément.\",\"note\":\"Le conflit de versions est un classique.\"}]},{\"template\":\"scenario_decision\",\"title\":\"Décision correcte — Le mod apparaît deux fois\",\"context\":\"Dans le launcher, un membre voit deux entrées proches pour Task Force Radio, issues de deux sources différentes.\",\"options\":[{\"label\":\"A\",\"body\":\"Activer les deux pour être sûr que l’un fonctionne.\"},{\"label\":\"B\",\"body\":\"Ne garder que la source officielle utilisée par l’unité et supprimer l’ambiguïté.\"},{\"label\":\"C\",\"body\":\"Ignorer la situation et lancer rapidement le jeu.\"}],\"correct\":\"B\",\"explanation\":\"Activer plusieurs variantes ou sources concurrentes d’un même mod crée des conflits de chargement et rend le diagnostic presque impossible.\"},{\"template\":\"common_mistakes\",\"title\":\"Erreurs classiques côté launcher\",\"items\":[{\"title\":\"Le mod est téléchargé mais pas activé\",\"body\":\"Le fichier existe, mais le jeu ne le charge pas.\"},{\"title\":\"Le mauvais preset est lancé\",\"body\":\"Le membre pense jouer avec le bon ensemble alors qu’il a ouvert un preset ancien ou générique.\"},{\"title\":\"Deux variantes du mod coexistent\",\"body\":\"Le jeu charge un environnement ambigu, parfois sans message immédiatement clair.\"}]},{\"template\":\"dos_donts\",\"title\":\"Bonnes pratiques de chargement\",\"dos\":[\"Utiliser un preset identifié\",\"Contrôler la présence du mod avant lancement\",\"Rester fidèle à la source officielle\"],\"donts\":[\"Empiler les variantes du même mod\",\"Bricoler plusieurs répertoires sans méthode\",\"Lancer une mission sans vérifier le preset\"]},{\"template\":\"knowledge_check\",\"title\":\"Ce qui compte vraiment côté jeu\",\"body\":\"Le launcher Arma 3 est votre point de contrôle.\\nLa présence sur disque ne vaut pas activation réelle.\\nUne source officielle unique simplifie les diagnostics.\\nUn preset brouillon fait perdre du temps à toute l’équipe de support.\"}]}', NULL, 11, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL),
(40, 27, 'À retenir — Installation côté jeu', 'Synthèse sur l’installation du mod TFAR dans Arma 3.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">À retenir</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>TFAR doit être récupéré depuis la source officielle de l’unité.</li><li>Le launcher Arma 3 doit montrer clairement que le mod est activé dans le bon preset.</li><li>La coexistence de plusieurs variantes du mod est un facteur majeur de panne.</li></ul></div>', NULL, 3, 'initiation', 2, 1, '2026-04-06 20:11:27', NULL);
INSERT INTO `training_lessons` (`id`, `module_id`, `title`, `summary`, `learning_objectives`, `instructor_notes`, `lesson_type`, `content`, `external_url`, `duration_minutes`, `difficulty`, `position`, `is_required`, `created_at`, `updated_at`) VALUES
(41, 28, 'Plugin TeamSpeak — parcours visuel', 'Installation du plugin, activation, redémarrage et contrôles simples.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[{\"id\":\"m4-reminder\",\"title\":\"Rappel clé\",\"body\":\"<p>Un TeamSpeak qui s’ouvre n’est pas la preuve que TFAR fonctionne. Le plugin doit être présent, reconnu et activé.</p>\"}],\"opening\":{\"eyebrow\":\"Module 4\",\"title\":\"\",\"lead\":\"Le plugin TeamSpeak est indispensable. Sans lui, TFAR reste incomplet même si le mod Arma 3 est actif.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~14 min\"},{\"label\":\"Périmètre\",\"value\":\"Vocal\"}]},\"closure\":{\"title\":\"Synthèse — Plugin TeamSpeak\",\"seen\":[\"Le plugin constitue le pont vocal nécessaire à TFAR.\",\"Une installation oubliée ou inactive côté TeamSpeak suffit à bloquer le système.\"],\"acquired\":[\"Vous savez installer et activer le plugin TeamSpeak.\",\"Vous comprenez l’intérêt d’un redémarrage propre après installation.\"],\"nextHint\":\"Poursuivez avec les vérifications finales de bon fonctionnement.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Installer le plugin TeamSpeak\",\"subtitle\":\"Le maillon vocal de la chaîne\",\"body\":\"<p>TFAR nécessite en général un <strong>plugin TeamSpeak</strong> spécifique. C’est lui qui permet l’intégration entre le jeu et l’environnement vocal. Sans plugin actif, TeamSpeak reste un logiciel vocal ordinaire, non synchronisé avec la logique radio attendue.</p>\",\"contextKicker\":\"Étape 01 · Intégration vocale\",\"surface\":\"elevated\",\"primaryAction\":{\"type\":\"modal\",\"label\":\"Voir le rappel clé\",\"modalId\":\"m4-reminder\"}},{\"template\":\"process_steps\",\"title\":\"Procédure générale côté TeamSpeak\",\"steps\":[{\"label\":\"Étape 1\",\"title\":\"Localiser le plugin officiel\",\"body\":\"Récupérez le plugin correspondant à la version de TFAR utilisée par l’unité.\",\"note\":\"Le bon plugin doit correspondre au bon environnement.\"},{\"label\":\"Étape 2\",\"title\":\"Lancer son installation ou le déposer selon la méthode prévue\",\"body\":\"Suivez la procédure retenue par la version distribuée : installateur, paquet dédié ou intégration guidée.\",\"note\":\"Ne remplacez pas au hasard des fichiers système si une méthode propre est fournie.\"},{\"label\":\"Étape 3\",\"title\":\"Ouvrir TeamSpeak et vérifier la présence du plugin\",\"body\":\"Dans TeamSpeak, contrôlez que le plugin figure bien dans la liste des plugins disponibles.\",\"note\":\"Présence visible et activation sont deux choses distinctes.\"},{\"label\":\"Étape 4\",\"title\":\"Activer le plugin puis redémarrer si nécessaire\",\"body\":\"Activez le plugin et redémarrez TeamSpeak si la procédure ou le logiciel l’exige.\",\"note\":\"Certaines intégrations ne sont effectives qu’après relance propre.\"}]},{\"template\":\"scenario_decision\",\"title\":\"Décision correcte — TeamSpeak fonctionne mais pas TFAR\",\"context\":\"Un membre rejoint le serveur TeamSpeak et parle normalement avec les autres, mais les fonctions radio attendues ne semblent pas réagir en jeu.\",\"options\":[{\"label\":\"A\",\"body\":\"Conclure que TFAR fonctionne quand même puisque l’audio passe.\"},{\"label\":\"B\",\"body\":\"Vérifier la présence et l’activation effective du plugin TeamSpeak lié à TFAR.\"},{\"label\":\"C\",\"body\":\"Réinstaller tout Arma 3 immédiatement sans autre contrôle.\"}],\"correct\":\"B\",\"explanation\":\"Le fait d’entendre ou de parler sur TeamSpeak ne prouve pas que l’intégration TFAR est active. Il faut d’abord contrôler le plugin dédié.\"},{\"template\":\"common_mistakes\",\"title\":\"Erreurs fréquentes côté vocal\",\"items\":[{\"title\":\"Plugin installé mais désactivé\",\"body\":\"Le logiciel le connaît, mais il n’est pas réellement employé.\"},{\"title\":\"Ancien plugin conservé\",\"body\":\"La version présente peut être incompatible avec l’environnement actuel.\"},{\"title\":\"Pas de redémarrage après installation\",\"body\":\"Le membre croit avoir terminé alors que TeamSpeak n’a pas chargé le plugin proprement.\"}]},{\"template\":\"dos_donts\",\"title\":\"Ce qu’il faut faire / éviter\",\"dos\":[\"Contrôler explicitement la liste des plugins\",\"Utiliser le plugin lié à la version officielle\",\"Redémarrer proprement si nécessaire\"],\"donts\":[\"Supposer que TeamSpeak seul suffit\",\"Garder plusieurs plugins concurrents sans contrôle\",\"Sauter la vérification visuelle dans TeamSpeak\"]},{\"template\":\"knowledge_check\",\"title\":\"Repères côté TeamSpeak\",\"body\":\"Un plugin absent ou inactif bloque TFAR.\\nUn logiciel vocal fonctionnel n’est pas la preuve d’une intégration réussie.\\nLe contrôle visuel dans TeamSpeak est indispensable.\\nLe redémarrage propre fait partie de la procédure normale.\"}]}', NULL, 10, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL),
(42, 28, 'À retenir — Plugin vocal', 'Synthèse d’ancrage sur le plugin TeamSpeak.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">À retenir</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>TFAR n’est réellement exploitable que si le plugin TeamSpeak correspondant est présent et activé.</li><li>Parler sur TeamSpeak ne prouve pas que TFAR est opérationnel.</li><li>Après installation, un redémarrage propre du logiciel vocal est souvent nécessaire.</li></ul></div>', NULL, 3, 'initiation', 2, 1, '2026-04-06 20:11:27', NULL),
(43, 29, 'Vérifier et corriger — parcours visuel', 'Test complet, signes d’échec, méthode de diagnostic et points de reprise.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module 5\",\"title\":\"\",\"lead\":\"Une installation n’est validée qu’après contrôle. Ce module vous donne une méthode de test simple et une logique de dépannage initiale.\",\"stats\":[{\"label\":\"Durée indicative\",\"value\":\"~18 min\"},{\"label\":\"Objet\",\"value\":\"Vérification\"}]},\"closure\":{\"title\":\"Synthèse — Vérification et dépannage\",\"seen\":[\"Une installation se contrôle en chaîne : jeu, mod, plugin, test en situation.\",\"Les symptômes les plus simples suffisent souvent à orienter le diagnostic.\"],\"acquired\":[\"Vous savez effectuer un premier contrôle logique avant d’appeler à l’aide.\",\"Vous pouvez décrire une panne utilement au support ou au staff technique.\"],\"nextHint\":\"Terminez avec le quiz final de validation.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Tester avant mission\",\"subtitle\":\"La validation passe par la preuve de fonctionnement\",\"body\":\"<p>Le bon réflexe n’est pas de dire « j’ai installé ». Le bon réflexe est de pouvoir dire : <strong>j’ai installé, contrôlé, puis testé</strong>. Une configuration non testée est une configuration présumée douteuse.</p>\",\"contextKicker\":\"Étape 01 · Vérification finale\",\"surface\":\"elevated\"},{\"template\":\"process_steps\",\"title\":\"Méthode simple de vérification\",\"steps\":[{\"label\":\"Étape 1\",\"title\":\"Contrôler le launcher\",\"body\":\"Vérifiez encore une fois que TFAR est bien chargé dans le preset réellement utilisé.\",\"note\":\"Le mauvais preset rend les autres contrôles inutiles.\"},{\"label\":\"Étape 2\",\"title\":\"Contrôler TeamSpeak\",\"body\":\"Vérifiez la présence et l’activation du plugin TFAR dans TeamSpeak.\",\"note\":\"Sans plugin actif, la chaîne est rompue.\"},{\"label\":\"Étape 3\",\"title\":\"Lancer le jeu puis rejoindre l’environnement de test\",\"body\":\"Connectez-vous au serveur ou à l’environnement prévu pour la vérification.\",\"note\":\"Un test réel vaut mieux qu’une supposition locale.\"},{\"label\":\"Étape 4\",\"title\":\"Vérifier les effets attendus\",\"body\":\"Confirmez que les comportements radio ou de proximité attendus apparaissent réellement.\",\"note\":\"L’installation n’est validée que si les effets fonctionnels se manifestent.\"}]},{\"template\":\"common_mistakes\",\"title\":\"Symptômes typiques et premières hypothèses\",\"items\":[{\"title\":\"Le mod est actif mais aucune logique radio n’apparaît\",\"body\":\"Commencez par vérifier le plugin TeamSpeak et son activation.\"},{\"title\":\"Tout semblait marcher hier mais plus aujourd’hui\",\"body\":\"Vérifiez le preset lancé, une mise à jour, ou une divergence de version.\"},{\"title\":\"Le membre dit “ça ne marche pas” sans autre détail\",\"body\":\"Le problème est aussi méthodologique : il faut décrire le symptôme exact avant de conclure.\"}]},{\"template\":\"case_review\",\"title\":\"Cas pratique — Panne mal décrite\",\"body\":\"<p>Un membre indique seulement : « TFAR ne marche pas ». Après questions, on découvre qu’il n’a pas vérifié le preset Arma, n’a pas ouvert la liste des plugins TeamSpeak et n’a réalisé aucun test contrôlé.</p><p><strong>Analyse :</strong> il n’existe pas encore de diagnostic. Il existe seulement une impression d’échec.</p><p><strong>Bonne conduite :</strong> reprendre une séquence courte et ordonnée : preset, plugin, redémarrage propre, test en environnement prévu.</p>\"},{\"template\":\"dos_donts\",\"title\":\"Comportement utile en cas de panne\",\"dos\":[\"Décrire le symptôme exact\",\"Indiquer ce qui a été vérifié\",\"Tester avant de solliciter le support\"],\"donts\":[\"Dire seulement “ça ne marche pas”\",\"Réinstaller tout sans logique\",\"Modifier plusieurs paramètres à la fois sans suivi\"]},{\"template\":\"scenario_decision\",\"title\":\"Décision correcte — Demande d’aide\",\"context\":\"Après test, un membre n’obtient toujours pas le résultat attendu et doit contacter le support interne.\",\"options\":[{\"label\":\"A\",\"body\":\"Envoyer “aidez-moi” sans autre détail.\"},{\"label\":\"B\",\"body\":\"Décrire la version utilisée, ce qui a été installé, ce qui a été vérifié et le symptôme précis observé.\"},{\"label\":\"C\",\"body\":\"Attendre la mission en espérant que cela fonctionne sur place.\"}],\"correct\":\"B\",\"explanation\":\"Une demande d’aide utile repose sur des éléments vérifiables. Cela réduit le temps de diagnostic et évite les échanges inutiles.\"},{\"template\":\"fill_blanks\",\"title\":\"Vérification finale\",\"body\":\"<p>Une installation n’est réellement validée qu’après un [[test]] de fonctionnement.</p><p>En cas de panne, il faut décrire le [[symptôme]] observé et les vérifications déjà menées.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Ce qu’on attend avant mission\",\"body\":\"Le poste doit être vérifié avant l’heure de rassemblement.\\nLe support n’a pas vocation à reconstituer à votre place ce que vous n’avez pas contrôlé.\\nUne panne décrite proprement se corrige plus vite.\\nL’ordre des vérifications compte autant que les fichiers eux-mêmes.\"}]}', NULL, 13, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL),
(44, 29, 'À retenir — Vérification et dépannage', 'Synthèse finale avant évaluation.', NULL, NULL, 'richtext', '<div class=\"prose prose-slate max-w-none\"><h3 class=\"text-base font-bold text-slate-900\">À retenir</h3><ul class=\"list-disc pl-5 space-y-2 text-slate-700 text-sm leading-relaxed\"><li>Une installation ne vaut rien sans test de fonctionnement.</li><li>Le bon ordre de contrôle est : preset Arma 3, plugin TeamSpeak, redémarrage propre, test réel.</li><li>En cas de panne, décrivez le symptôme exact et les vérifications déjà réalisées.</li></ul></div>', NULL, 3, 'initiation', 2, 1, '2026-04-06 20:11:27', NULL),
(45, 30, 'Avant le quiz final', 'Consignes avant validation finale.', NULL, NULL, 'canvas', '{\"version\":2,\"modals\":[],\"opening\":{\"eyebrow\":\"Module 6\",\"title\":\"\",\"lead\":\"Le questionnaire final vérifie que vous maîtrisez la logique d’installation, de contrôle et de premier dépannage de TFAR.\",\"stats\":[{\"label\":\"Seuil de réussite\",\"value\":\"80 %\"},{\"label\":\"Temps indicatif\",\"value\":\"~12 min\"}]},\"closure\":{\"title\":\"Fin de parcours\",\"seen\":[\"Le quiz porte sur les étapes de préparation, d’installation et de vérification.\",\"La réussite ne remplace pas un test réel sur l’environnement de l’unité.\"],\"acquired\":[\"Vous avez le cadre logique minimal pour préparer votre poste proprement.\",\"Vous savez où reprendre si un doute persiste.\"],\"nextHint\":\"Passez maintenant au questionnaire final lorsqu’il est disponible.\"},\"slides\":[{\"template\":\"title_hero\",\"title\":\"Validation finale\",\"subtitle\":\"Contrôler la compréhension avant emploi\",\"body\":\"<p>Ce questionnaire final ne cherche pas à vous piéger. Il vérifie que vous ne partez pas avec de fausses certitudes sur l’installation de TFAR. Une bonne réponse traduit une méthode correcte ; une mauvaise réponse signale une étape à revoir.</p>\",\"contextKicker\":\"Étape finale · Contrôle des acquis\",\"surface\":\"elevated\"},{\"template\":\"reading_article\",\"title\":\"Ce que le quiz valide — et ce qu’il ne valide pas\",\"subtitle\":\"Connaissance de la méthode, pas garantie absolue du poste\",\"body\":\"<p>La réussite au questionnaire signifie que vous comprenez la logique correcte : pré requis, mod Arma 3, plugin TeamSpeak, vérification et dépannage initial. Elle ne remplace pas un test effectif sur le serveur ou l’environnement réel de votre unité.</p><p>Autrement dit : le quiz valide un <strong>niveau de compréhension</strong>. Le test réel valide un <strong>poste opérationnel</strong>.</p>\"},{\"template\":\"knowledge_check\",\"title\":\"Avant de répondre\",\"body\":\"Lisez jusqu’au bout.\\nDistinguez toujours jeu, plugin et test final.\\nÉvitez les réponses intuitives mais incomplètes.\\nLe bon ordre des étapes compte.\"}]}', NULL, 4, 'initiation', 1, 1, '2026-04-06 20:11:27', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `learning_objectives` text DEFAULT NULL,
  `estimated_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_modules`
--

INSERT INTO `training_modules` (`id`, `course_id`, `title`, `description`, `subtitle`, `learning_objectives`, `estimated_minutes`, `position`, `is_required`, `created_at`, `updated_at`) VALUES
(13, 4, 'Vue d’ensemble', 'Ce module pose pourquoi le portail existe, ce qu’il centralise (information stable, coordination, formations) et ce qu’il ne remplace pas. Il introduit la méthode de lecture du parcours, les risques d’une mauvaise utilisation et les réflexes de sécurité du compte.', 'Finalité du portail et cadre', '[\"Expliquer en une phrase la différence entre information stabilisée, échanges vivants et suivi pédagogique sur le site.\",\"Identifier ce que le portail n’est pas (substitut à la chaîne de commandement, stockage anarchique sur le forum).\",\"Citer au moins trois erreurs d’usage fréquentes et leur correction.\"]', 26, 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(14, 4, 'Navigation et compte', 'Menus, tableau de bord, zone Opérations, profil, préférences, sécurité et multi-communautés : ce module décrit ce que vous faites réellement sur le portail au quotidien et comment éviter les erreurs de contexte.', 'Se repérer et agir sur son compte', '[\"Décrire le rôle du tableau de bord par rapport au menu principal.\",\"Enchaîner les étapes pour mettre à jour le profil et les préférences dans la rubrique compte.\",\"Expliquer pourquoi le poste partagé impose une déconnexion explicite.\"]', 28, 2, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(15, 4, 'Organisation et contenus', 'Personnel, organigramme, documents, catalogue des formations, progression et attestations : le cœur opérationnel du portail. Le module distingue référence documentaire et discussion, et clarifie ce qu’une attestation prouve ou ne prouve pas.', 'Où vit l’information et le LMS', '[\"Distinguer dossier personnel, organigramme et documents officiels.\",\"Traiter correctement un document sensible ou une version obsolète.\",\"Expliquer pourquoi une formation assignée mais incomplète reste « non validée ».\"]', 32, 3, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(16, 4, 'Communauté', 'Règles de participation au forum, distinction annonce officielle et conversation, inscriptions aux événements, présence et signalements. Le module vise à réduire le bruit informationnel et à sécuriser les canaux sensibles.', 'Forum, annonces, événements', '[\"Choisir entre message public et canal dédié selon le type de sujet.\",\"Rédiger un titre de sujet utile et éviter les doublons.\",\"Adopter la conduite attendue en cas d’empêchement à un événement inscrit.\"]', 26, 5, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(17, 4, 'Validation', 'Préparation au questionnaire final, logique du score et des tentatives, obtention de l’attestation lorsque le parcours est certifiant, et rappel de la différence entre validation LMS et compétence opérationnelle reconnue par l’unité.', 'Questionnaire, attestation, limites', '[\"Expliquer l’usage des explications après une réponse incorrecte.\",\"Décrire ce que couvre une attestation de fin de parcours sur le portail.\",\"Organiser une reprise de révision avant une nouvelle tentative de quiz.\"]', 22, 6, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(18, 5, 'Vue d’ensemble', 'Ce module pose pourquoi le portail existe, ce qu’il centralise (information stable, coordination, formations) et ce qu’il ne remplace pas. Il introduit la méthode de lecture du parcours, les risques d’une mauvaise utilisation et les réflexes de sécurité du compte.', 'Finalité du portail et cadre', '[\"Expliquer en une phrase la différence entre information stabilisée, échanges vivants et suivi pédagogique sur le site.\",\"Identifier ce que le portail n’est pas (substitut à la chaîne de commandement, stockage anarchique sur le forum).\",\"Citer au moins trois erreurs d’usage fréquentes et leur correction.\"]', 26, 1, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(19, 5, 'Navigation et compte', 'Menus, tableau de bord, zone Opérations, profil, préférences, sécurité et multi-communautés : ce module décrit ce que vous faites réellement sur le portail au quotidien et comment éviter les erreurs de contexte.', 'Se repérer et agir sur son compte', '[\"Décrire le rôle du tableau de bord par rapport au menu principal.\",\"Enchaîner les étapes pour mettre à jour le profil et les préférences dans la rubrique compte.\",\"Expliquer pourquoi le poste partagé impose une déconnexion explicite.\"]', 28, 2, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(20, 5, 'Organisation et contenus', 'Personnel, organigramme, documents, catalogue des formations, progression et attestations : le cœur opérationnel du portail. Le module distingue référence documentaire et discussion, et clarifie ce qu’une attestation prouve ou ne prouve pas.', 'Où vit l’information et le LMS', '[\"Distinguer dossier personnel, organigramme et documents officiels.\",\"Traiter correctement un document sensible ou une version obsolète.\",\"Expliquer pourquoi une formation assignée mais incomplète reste « non validée ».\"]', 32, 3, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(21, 5, 'Communauté', 'Règles de participation au forum, distinction annonce officielle et conversation, inscriptions aux événements, présence et signalements. Le module vise à réduire le bruit informationnel et à sécuriser les canaux sensibles.', 'Forum, annonces, événements', '[\"Choisir entre message public et canal dédié selon le type de sujet.\",\"Rédiger un titre de sujet utile et éviter les doublons.\",\"Adopter la conduite attendue en cas d’empêchement à un événement inscrit.\"]', 26, 5, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(22, 5, 'Validation', 'Préparation au questionnaire final, logique du score et des tentatives, obtention de l’attestation lorsque le parcours est certifiant, et rappel de la différence entre validation LMS et compétence opérationnelle reconnue par l’unité.', 'Questionnaire, attestation, limites', '[\"Expliquer l’usage des explications après une réponse incorrecte.\",\"Décrire ce que couvre une attestation de fin de parcours sur le portail.\",\"Organiser une reprise de révision avant une nouvelle tentative de quiz.\"]', 22, 6, 1, '2026-04-06 11:14:44', '2026-04-13 11:05:26'),
(23, 4, 'Bilan à mi-parcours', 'Révision structurée des trois premiers blocs puis questionnaire à choix multiples. L’objectif est de consolider le vocabulaire et les réflexes avant la vie collective et la validation finale.', 'Ancrer les acquis (modules 1 à 3)', '[\"Relier les notions de tableau de bord, compte, documents et formations.\",\"Repérer les pièges classiques (forum vs documents, multi-communautés).\",\"Aborder le questionnaire avec une méthode de lecture complète des énoncés.\"]', 18, 4, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(24, 5, 'Bilan à mi-parcours', 'Révision structurée des trois premiers blocs puis questionnaire à choix multiples. L’objectif est de consolider le vocabulaire et les réflexes avant la vie collective et la validation finale.', 'Ancrer les acquis (modules 1 à 3)', '[\"Relier les notions de tableau de bord, compte, documents et formations.\",\"Repérer les pièges classiques (forum vs documents, multi-communautés).\",\"Aborder le questionnaire avec une méthode de lecture complète des énoncés.\"]', 18, 4, 1, '2026-04-06 18:33:03', '2026-04-13 11:05:26'),
(25, 7, 'Vue d’ensemble et cadre', 'Module 1 — Finalité de TFAR, logique générale d’installation et points de vigilance.', 'Comprendre avant d’installer', '[\"Saisir le rôle de TFAR dans Arma 3\",\"Comprendre la chaîne mod + plugin vocal\",\"Identifier ce qui relève d’Arma et ce qui relève de TeamSpeak\"]', 12, 1, 1, '2026-04-06 20:11:27', NULL),
(26, 7, 'Préparer le poste', 'Module 2 — Pré requis techniques, environnement logiciel et contrôles avant installation.', 'Pré requis et préparation', '[\"Vérifier les logiciels nécessaires\",\"Identifier les points de friction avant installation\",\"Préparer une base propre pour éviter les conflits\"]', 14, 2, 1, '2026-04-06 20:11:27', NULL),
(27, 7, 'Installer le mod côté Arma 3', 'Module 3 — Intégration de TFAR dans Arma 3 selon la méthode officielle de l’unité.', 'Installation côté jeu', '[\"Installer ou activer correctement le mod TFAR\",\"Comprendre le rôle du launcher Arma 3\",\"Éviter les erreurs de version ou de chargement\"]', 16, 3, 1, '2026-04-06 20:11:27', NULL),
(28, 7, 'Installer le plugin TeamSpeak', 'Module 4 — Installation et activation du plugin TeamSpeak nécessaire à TFAR.', 'Installation côté vocal', '[\"Comprendre le rôle du plugin TeamSpeak\",\"Installer ou activer correctement le plugin\",\"Distinguer TeamSpeak fonctionnel de TFAR réellement intégré\"]', 14, 4, 1, '2026-04-06 20:11:27', NULL),
(29, 7, 'Vérification et dépannage initial', 'Module 5 — Contrôles de bon fonctionnement, symptômes typiques et premières corrections.', 'Tester avant mission', '[\"Savoir effectuer une vérification simple\",\"Identifier les symptômes les plus classiques\",\"Appliquer une méthode de dépannage initiale\"]', 18, 5, 1, '2026-04-06 20:11:27', NULL),
(30, 7, 'Validation finale', 'Module 6 — Quiz final de validation du parcours.', 'Évaluation', '[\"Vérifier la compréhension des étapes d’installation\",\"Valider les bons réflexes de contrôle\",\"Confirmer l’autonomie minimale du membre\"]', 12, 6, 1, '2026-04-06 20:11:27', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `training_progress`
--

CREATE TABLE `training_progress` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `lesson_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('not_started','in_progress','completed','skipped') DEFAULT 'not_started',
  `progress_percent` decimal(5,2) DEFAULT 0.00,
  `time_spent_seconds` int(10) UNSIGNED DEFAULT 0,
  `last_position_seconds` int(10) UNSIGNED DEFAULT 0,
  `viewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_progress`
--

INSERT INTO `training_progress` (`id`, `enrollment_id`, `lesson_id`, `status`, `progress_percent`, `time_spent_seconds`, `last_position_seconds`, `viewed_at`, `completed_at`, `updated_at`) VALUES
(8, 3, 18, 'completed', 100.00, 0, 0, '2026-04-06 19:05:53', '2026-04-06 19:05:53', '2026-04-06 19:05:53'),
(9, 3, 19, 'completed', 100.00, 0, 0, '2026-04-06 19:08:32', '2026-04-06 19:08:32', '2026-04-06 19:08:32'),
(10, 3, 20, 'completed', 100.00, 0, 0, '2026-04-06 19:08:57', '2026-04-06 19:08:57', '2026-04-06 19:08:57'),
(11, 3, 21, 'completed', 100.00, 0, 0, '2026-04-06 19:35:20', '2026-04-06 19:35:20', '2026-04-06 19:35:20'),
(12, 3, 22, 'completed', 100.00, 0, 0, '2026-04-06 19:36:17', '2026-04-06 19:36:17', '2026-04-06 19:36:17'),
(13, 3, 30, 'completed', 100.00, 0, 0, '2026-04-06 19:05:56', '2026-04-06 19:05:56', '2026-04-06 19:05:56'),
(14, 3, 31, 'completed', 100.00, 0, 0, '2026-04-06 19:06:04', '2026-04-06 19:06:04', '2026-04-06 19:06:04'),
(15, 3, 32, 'completed', 100.00, 0, 0, '2026-04-06 19:06:07', '2026-04-06 19:06:07', '2026-04-06 19:06:07'),
(16, 3, 29, 'completed', 100.00, 0, 0, '2026-04-06 19:06:19', '2026-04-06 19:06:19', '2026-04-06 19:06:19'),
(17, 3, 33, 'completed', 100.00, 0, 0, '2026-04-06 19:09:03', '2026-04-06 19:09:03', '2026-04-06 19:09:03'),
(18, 3, 34, 'completed', 100.00, 0, 0, '2026-04-06 19:09:22', '2026-04-06 19:09:22', '2026-04-06 19:09:22'),
(19, 4, 35, 'completed', 100.00, 0, 0, '2026-04-06 20:40:37', '2026-04-06 20:40:37', '2026-04-06 20:40:37'),
(20, 4, 36, 'completed', 100.00, 0, 0, '2026-04-07 18:21:05', '2026-04-07 18:21:05', '2026-04-07 18:21:05'),
(21, 4, 37, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(22, 4, 38, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(23, 4, 39, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(24, 4, 40, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(25, 4, 41, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(26, 4, 42, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(27, 4, 43, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(28, 4, 44, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37'),
(29, 4, 45, 'not_started', 0.00, 0, 0, NULL, NULL, '2026-04-06 20:13:37');

-- --------------------------------------------------------

--
-- Structure de la table `training_quizzes`
--

CREATE TABLE `training_quizzes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `passing_score` decimal(5,2) DEFAULT 80.00,
  `max_attempts` int(10) UNSIGNED DEFAULT 3,
  `time_limit_minutes` int(10) UNSIGNED DEFAULT NULL,
  `randomize_questions` tinyint(1) DEFAULT 0,
  `is_final_exam` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_quizzes`
--

INSERT INTO `training_quizzes` (`id`, `module_id`, `title`, `description`, `passing_score`, `max_attempts`, `time_limit_minutes`, `randomize_questions`, `is_final_exam`, `created_at`) VALUES
(3, 17, 'Quiz — fonctionnement du portail', 'Validez vos acquis sur la navigation, le compte et la vie de la communauté.', 80.00, 5, 15, 0, 1, '2026-04-06 11:14:44'),
(4, 22, 'Quiz — fonctionnement du portail', 'Validez vos acquis sur la navigation, le compte et la vie de la communauté.', 80.00, 5, 15, 0, 1, '2026-04-06 11:14:44'),
(5, 23, 'Bilan — premiers réflexes', 'Questions sur la navigation, le compte, les documents et le catalogue des formations.', 75.00, 4, 15, 1, 0, '2026-04-06 18:33:03'),
(6, 24, 'Bilan — premiers réflexes', 'Questions sur la navigation, le compte, les documents et le catalogue des formations.', 75.00, 4, 15, 1, 0, '2026-04-06 18:33:03'),
(7, 29, 'Bilan intermédiaire — installation TFAR', 'Questions sur la structure de l’installation, les prérequis et les premières vérifications.', 75.00, 4, 12, 1, 0, '2026-04-06 20:11:27'),
(8, 30, 'Quiz final — Installer Task Force Radio', 'Validation finale des connaissances sur l’installation et le contrôle de TFAR.', 80.00, 5, 15, 0, 1, '2026-04-06 20:11:27');

-- --------------------------------------------------------

--
-- Structure de la table `training_quiz_answers`
--

CREATE TABLE `training_quiz_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_text` longtext NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_quiz_answers`
--

INSERT INTO `training_quiz_answers` (`id`, `question_id`, `answer_text`, `is_correct`, `position`) VALUES
(49, 13, 'Le tableau de bord', 1, 1),
(50, 13, 'Uniquement l’écran de déconnexion', 0, 2),
(51, 13, 'La page d’accueil du navigateur, hors portail', 0, 3),
(52, 13, 'Un écran réservé aux seuls instructeurs', 0, 4),
(53, 14, 'Dans le profil / Mon compte', 1, 1),
(54, 14, 'Sur un document papier uniquement', 0, 2),
(55, 14, 'En changeant le nom de son ordinateur', 0, 3),
(56, 14, 'En contactant uniquement un hébergeur extérieur', 0, 4),
(57, 15, 'Ouvrir la fiche formation et suivre l’inscription prévue (selon les règles de la communauté)', 1, 1),
(58, 15, 'En demandant à un ami de s’inscrire à votre place', 0, 2),
(59, 15, 'En fermant le navigateur puis en rouvrant n’importe quelle page', 0, 3),
(60, 15, 'Ce n’est jamais possible', 0, 4),
(61, 16, 'Respecter les canaux, rester courtois et pertinent', 1, 1),
(62, 16, 'Publier des informations sensibles hors rubrique autorisée', 0, 2),
(63, 16, 'Ignorer les annonces officielles', 0, 3),
(64, 16, 'Créer un sujet par message', 0, 4),
(65, 17, 'Prévenir selon les consignes de l’organisation (fil prévu, message au staff, etc.)', 1, 1),
(66, 17, 'Ne jamais prévenir', 0, 2),
(67, 17, 'Supprimer son compte', 0, 3),
(68, 17, 'Modifier l’événement pour tout le monde', 0, 4),
(69, 18, 'Elle est importante pour le collectif et peut débloquer une attestation après validation complète', 1, 1),
(70, 18, 'Elle est optionnelle et sans suivi', 0, 2),
(71, 18, 'Elle ne concerne que les administrateurs système', 0, 3),
(72, 18, 'Elle remplace le règlement sans validation', 0, 4),
(73, 19, 'Le tableau de bord', 1, 1),
(74, 19, 'Uniquement l’écran de déconnexion', 0, 2),
(75, 19, 'La page d’accueil du navigateur, hors portail', 0, 3),
(76, 19, 'Un écran réservé aux seuls instructeurs', 0, 4),
(77, 20, 'Dans le profil / Mon compte', 1, 1),
(78, 20, 'Sur un document papier uniquement', 0, 2),
(79, 20, 'En changeant le nom de son ordinateur', 0, 3),
(80, 20, 'En contactant uniquement un hébergeur extérieur', 0, 4),
(81, 21, 'Ouvrir la fiche formation et suivre l’inscription prévue (selon les règles de la communauté)', 1, 1),
(82, 21, 'En demandant à un ami de s’inscrire à votre place', 0, 2),
(83, 21, 'En fermant le navigateur puis en rouvrant n’importe quelle page', 0, 3),
(84, 21, 'Ce n’est jamais possible', 0, 4),
(85, 22, 'Respecter les canaux, rester courtois et pertinent', 1, 1),
(86, 22, 'Publier des informations sensibles hors rubrique autorisée', 0, 2),
(87, 22, 'Ignorer les annonces officielles', 0, 3),
(88, 22, 'Créer un sujet par message', 0, 4),
(89, 23, 'Prévenir selon les consignes de l’organisation (fil prévu, message au staff, etc.)', 1, 1),
(90, 23, 'Ne jamais prévenir', 0, 2),
(91, 23, 'Supprimer son compte', 0, 3),
(92, 23, 'Modifier l’événement pour tout le monde', 0, 4),
(93, 24, 'Elle est importante pour le collectif et peut débloquer une attestation après validation complète', 1, 1),
(94, 24, 'Elle est optionnelle et sans suivi', 0, 2),
(95, 24, 'Elle ne concerne que les administrateurs système', 0, 3),
(96, 24, 'Elle remplace le règlement sans validation', 0, 4),
(97, 25, 'L’historique du navigateur, hors portail', 0, 1),
(98, 25, 'Le tableau de bord', 1, 2),
(99, 25, 'Un écran réservé aux seuls formateurs', 0, 3),
(100, 25, 'Uniquement la page de réinitialisation du mot de passe', 0, 4),
(101, 26, 'En modifiant le nom du poste de travail', 0, 1),
(102, 26, 'Uniquement sur un réseau social extérieur', 0, 2),
(103, 26, 'Dans la rubrique compte (souvent « Mon compte ») du portail', 1, 3),
(104, 26, 'Ce n’est jamais possible en ligne', 0, 4),
(105, 27, 'Sur une messagerie personnelle uniquement', 0, 1),
(106, 27, 'Dans la rubrique documents du portail, selon le niveau de diffusion', 1, 2),
(107, 27, 'Uniquement en enchaînant des messages sur le forum sans fiche dédiée', 0, 3),
(108, 27, 'Dans les préférences du compte', 0, 4),
(109, 28, 'Le staff attend sa complétion dans le cadre fixé par la communauté', 1, 1),
(110, 28, 'Elle est purement décorative sur le site', 0, 2),
(111, 28, 'Elle ne concerne que les visiteurs non connectés', 0, 3),
(112, 28, 'Elle se valide sans parcourir le contenu', 0, 4),
(113, 29, 'Pour remplacer tous les documents officiels', 0, 1),
(114, 29, 'Pour envoyer des messages privés automatiques', 0, 2),
(115, 29, 'Pour comprendre la structure et les rattachements de l’unité', 1, 3),
(116, 29, 'Pour stocker les mots de passe partagés', 0, 4),
(117, 30, 'Un écran réservé aux seuls formateurs', 0, 1),
(118, 30, 'Le tableau de bord', 1, 2),
(119, 30, 'Uniquement la page de réinitialisation du mot de passe', 0, 3),
(120, 30, 'L’historique du navigateur, hors portail', 0, 4),
(121, 31, 'Uniquement sur un réseau social extérieur', 0, 1),
(122, 31, 'En modifiant le nom du poste de travail', 0, 2),
(123, 31, 'Dans la rubrique compte (souvent « Mon compte ») du portail', 1, 3),
(124, 31, 'Ce n’est jamais possible en ligne', 0, 4),
(125, 32, 'Sur une messagerie personnelle uniquement', 0, 1),
(126, 32, 'Uniquement en enchaînant des messages sur le forum sans fiche dédiée', 0, 2),
(127, 32, 'Dans les préférences du compte', 0, 3),
(128, 32, 'Dans la rubrique documents du portail, selon le niveau de diffusion', 1, 4),
(129, 33, 'Le staff attend sa complétion dans le cadre fixé par la communauté', 1, 1),
(130, 33, 'Elle se valide sans parcourir le contenu', 0, 2),
(131, 33, 'Elle est purement décorative sur le site', 0, 3),
(132, 33, 'Elle ne concerne que les visiteurs non connectés', 0, 4),
(133, 34, 'Pour envoyer des messages privés automatiques', 0, 1),
(134, 34, 'Pour remplacer tous les documents officiels', 0, 2),
(135, 34, 'Pour stocker les mots de passe partagés', 0, 3),
(136, 34, 'Pour comprendre la structure et les rattachements de l’unité', 1, 4),
(137, 35, 'Parce qu’il faut aussi un plugin TeamSpeak adapté', 1, 1),
(138, 35, 'Parce qu’Arma 3 n’accepte jamais les mods audio', 0, 2),
(139, 35, 'Parce que TeamSpeak remplace complètement Arma 3', 0, 3),
(140, 35, 'Parce que le launcher Arma 3 est inutile', 0, 4),
(141, 36, 'Vérifier qu’Arma 3 et TeamSpeak sont installés et exploitables', 1, 1),
(142, 36, 'Supprimer immédiatement tous les autres mods sans distinction', 0, 2),
(143, 36, 'Lancer une mission sans préparation pour voir si cela passe', 0, 3),
(144, 36, 'Modifier le micro Windows avant toute autre chose, sans raison', 0, 4),
(145, 37, 'Activer les deux pour augmenter les chances', 0, 1),
(146, 37, 'Ne garder que la version officielle et cohérente', 1, 2),
(147, 37, 'Ignorer l’ambiguïté et partir en mission', 0, 3),
(148, 37, 'Désinstaller Arma 3 immédiatement', 0, 4),
(149, 38, 'Si TeamSpeak fonctionne, TFAR est automatiquement validé', 0, 1),
(150, 38, 'TeamSpeak doit aussi disposer du plugin TFAR actif', 1, 2),
(151, 38, 'TeamSpeak est inutile si le mod est installé', 0, 3),
(152, 38, 'TFAR remplace complètement le logiciel vocal', 0, 4),
(153, 39, 'La présence des fichiers suffit à valider définitivement le poste', 0, 1),
(154, 39, 'Le poste doit encore être contrôlé et testé en situation', 1, 2),
(155, 39, 'Il n’est plus nécessaire d’ouvrir TeamSpeak', 0, 3),
(156, 39, 'Le launcher Arma 3 n’a plus aucune utilité', 0, 4),
(157, 40, 'Dire seulement “ça ne marche pas”', 0, 1),
(158, 40, 'Décrire le symptôme précis et les contrôles déjà menés', 1, 2),
(159, 40, 'Modifier au hasard plusieurs paramètres à la fois', 0, 3),
(160, 40, 'Attendre la mission réelle pour refaire un essai', 0, 4),
(161, 41, 'Ajouter uniquement un fond musical en mission', 0, 1),
(162, 41, 'Intégrer la communication vocale à la logique radio et de proximité du jeu', 1, 2),
(163, 41, 'Remplacer totalement Arma 3 par TeamSpeak', 0, 3),
(164, 41, 'Supprimer le besoin de toute discipline radio', 0, 4),
(165, 42, 'Le navigateur web et la carte graphique', 0, 1),
(166, 42, 'Le mod Arma 3 et le plugin TeamSpeak', 1, 2),
(167, 42, 'Le pare-feu Windows et le papier peint du bureau', 0, 3),
(168, 42, 'Le micro USB et le thème Windows uniquement', 0, 4),
(169, 43, 'Vérifier qu’Arma 3 et TeamSpeak sont installés et utilisables', 1, 1),
(170, 43, 'Lancer la mission sans TeamSpeak pour gagner du temps', 0, 2),
(171, 43, 'Activer tous les mods disponibles sans tri', 0, 3),
(172, 43, 'Répondre au support avant toute vérification', 0, 4),
(173, 44, 'Parce que le jeu refuse tout mod téléchargé officiellement', 0, 1),
(174, 44, 'Parce que cela produit une configuration ambiguë et instable', 1, 2),
(175, 44, 'Parce que TeamSpeak ne peut gérer qu’un seul casque', 0, 3),
(176, 44, 'Parce que le micro devient inactif dans Windows immédiatement', 0, 4),
(177, 45, 'Contrôler les mods présents et activés dans le bon preset', 1, 1),
(178, 45, 'Installer automatiquement tous les plugins vocaux de Windows', 0, 2),
(179, 45, 'Remplacer TeamSpeak', 0, 3),
(180, 45, 'Valider à lui seul le fonctionnement complet de TFAR', 0, 4),
(181, 46, 'Contrôler la présence et l’activation du plugin', 1, 1),
(182, 46, 'Changer systématiquement de pseudo', 0, 2),
(183, 46, 'Supprimer tous les autres plugins sans regarder', 0, 3),
(184, 46, 'Désinstaller puis réinstaller le micro', 0, 4),
(185, 47, 'S’il s’ouvre, TFAR est forcément opérationnel', 0, 1),
(186, 47, 'Il peut fonctionner sans que TFAR soit correctement intégré', 1, 2),
(187, 47, 'Il devient inutile dès que le mod est téléchargé', 0, 3),
(188, 47, 'Il remplace automatiquement le launcher Arma 3', 0, 4),
(189, 48, 'La présence des fichiers suffit définitivement', 0, 1),
(190, 48, 'Il faut encore contrôler et tester en situation', 1, 2),
(191, 48, 'Il n’est plus nécessaire d’ouvrir le logiciel vocal', 0, 3),
(192, 48, 'Le support doit valider à distance sans autre élément', 0, 4),
(193, 49, 'Preset Arma 3, plugin TeamSpeak, test réel', 1, 1),
(194, 49, 'Test réel, puis éventuellement vérifier les mods plus tard', 0, 2),
(195, 49, 'Changer tous les périphériques avant de regarder le preset', 0, 3),
(196, 49, 'Demander au support d’emblée sans rien contrôler', 0, 4),
(197, 50, 'Écrire simplement “TFAR cassé”', 0, 1),
(198, 50, 'Décrire précisément le symptôme et les vérifications déjà menées', 1, 2),
(199, 50, 'Attendre le début de mission sans prévenir', 0, 3),
(200, 50, 'Réinstaller plusieurs fois sans méthode avant de contacter qui que ce soit', 0, 4);

-- --------------------------------------------------------

--
-- Structure de la table `training_quiz_attempts`
--

CREATE TABLE `training_quiz_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quiz_id` bigint(20) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `passed` tinyint(1) DEFAULT 0,
  `status` enum('in_progress','submitted','graded','expired') DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_quiz_attempts`
--

INSERT INTO `training_quiz_attempts` (`id`, `quiz_id`, `enrollment_id`, `started_at`, `submitted_at`, `score`, `passed`, `status`) VALUES
(2, 4, 3, '2026-04-06 17:15:12', NULL, NULL, 0, 'expired'),
(3, 4, 3, '2026-04-06 17:42:14', NULL, NULL, 0, 'expired'),
(4, 6, 3, '2026-04-06 19:06:20', '2026-04-06 19:06:51', 100.00, 1, 'graded'),
(5, 4, 3, '2026-04-06 19:30:53', '2026-04-06 19:31:09', 100.00, 1, 'graded'),
(6, 6, 3, '2026-04-06 19:31:43', '2026-04-06 19:32:02', 100.00, 1, 'graded'),
(7, 7, 4, '2026-04-06 20:34:10', NULL, NULL, 0, 'in_progress');

-- --------------------------------------------------------

--
-- Structure de la table `training_quiz_questions`
--

CREATE TABLE `training_quiz_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quiz_id` bigint(20) UNSIGNED NOT NULL,
  `question_type` enum('single_choice','multiple_choice','true_false','short_text','long_text') NOT NULL,
  `question_text` longtext NOT NULL,
  `explanation` longtext DEFAULT NULL,
  `points` decimal(6,2) DEFAULT 1.00,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_quiz_questions`
--

INSERT INTO `training_quiz_questions` (`id`, `quiz_id`, `question_type`, `question_text`, `explanation`, `points`, `position`, `created_at`) VALUES
(13, 3, 'single_choice', 'Après connexion, quel écran sert principalement de point de départ pour les rappels et raccourcis ?', 'Le tableau de bord regroupe l’essentiel de l’activité utile pour votre compte.', 1.00, 1, '2026-04-06 11:14:44'),
(14, 3, 'single_choice', 'Où un membre met-il généralement à jour son identité affichée et ses préférences ?', NULL, 1.00, 2, '2026-04-06 11:14:44'),
(15, 3, 'single_choice', 'Comment s’inscrire à une formation publiée dans le catalogue ?', NULL, 1.00, 3, '2026-04-06 11:14:44'),
(16, 3, 'single_choice', 'Sur le forum, quelle attitude est attendue par défaut ?', NULL, 1.00, 4, '2026-04-06 11:14:44'),
(17, 3, 'single_choice', 'Pour les événements communautaires (brief, séance…), que faut-il faire en cas d’empêchement ?', NULL, 1.00, 5, '2026-04-06 11:14:44'),
(18, 3, 'single_choice', 'Une formation marquée « obligatoire » et « certifiante » signifie en général que :', NULL, 1.00, 6, '2026-04-06 11:14:44'),
(19, 4, 'single_choice', 'Après connexion, quel écran sert principalement de point de départ pour les rappels et raccourcis ?', 'Le tableau de bord regroupe l’essentiel de l’activité utile pour votre compte.', 1.00, 1, '2026-04-06 11:14:44'),
(20, 4, 'single_choice', 'Où un membre met-il généralement à jour son identité affichée et ses préférences ?', NULL, 1.00, 2, '2026-04-06 11:14:44'),
(21, 4, 'single_choice', 'Comment s’inscrire à une formation publiée dans le catalogue ?', NULL, 1.00, 3, '2026-04-06 11:14:44'),
(22, 4, 'single_choice', 'Sur le forum, quelle attitude est attendue par défaut ?', NULL, 1.00, 4, '2026-04-06 11:14:44'),
(23, 4, 'single_choice', 'Pour les événements communautaires (brief, séance…), que faut-il faire en cas d’empêchement ?', NULL, 1.00, 5, '2026-04-06 11:14:44'),
(24, 4, 'single_choice', 'Une formation marquée « obligatoire » et « certifiante » signifie en général que :', NULL, 1.00, 6, '2026-04-06 11:14:44'),
(25, 5, 'single_choice', 'Après connexion, quel écran regroupe en général les raccourcis et rappels utiles pour votre session ?', 'Le tableau de bord est le point de départ logique après connexion.', 1.00, 1, '2026-04-06 18:33:03'),
(26, 5, 'single_choice', 'Où met-on à jour en principe ses préférences de notification et son profil affiché ?', NULL, 1.00, 2, '2026-04-06 18:33:03'),
(27, 5, 'single_choice', 'Où retrouver en priorité une note officielle stabilisée, destinée à tous les membres autorisés ?', NULL, 1.00, 3, '2026-04-06 18:33:03'),
(28, 5, 'single_choice', 'Une formation du catalogue apparaît comme obligatoire : que signifie cela en général ?', NULL, 1.00, 4, '2026-04-06 18:33:03'),
(29, 5, 'single_choice', 'Pourquoi l’organigramme (ORBAT) du portail est-il utile au quotidien ?', NULL, 1.00, 5, '2026-04-06 18:33:03'),
(30, 6, 'single_choice', 'Après connexion, quel écran regroupe en général les raccourcis et rappels utiles pour votre session ?', 'Le tableau de bord est le point de départ logique après connexion.', 1.00, 1, '2026-04-06 18:33:03'),
(31, 6, 'single_choice', 'Où met-on à jour en principe ses préférences de notification et son profil affiché ?', NULL, 1.00, 2, '2026-04-06 18:33:03'),
(32, 6, 'single_choice', 'Où retrouver en priorité une note officielle stabilisée, destinée à tous les membres autorisés ?', NULL, 1.00, 3, '2026-04-06 18:33:03'),
(33, 6, 'single_choice', 'Une formation du catalogue apparaît comme obligatoire : que signifie cela en général ?', NULL, 1.00, 4, '2026-04-06 18:33:03'),
(34, 6, 'single_choice', 'Pourquoi l’organigramme (ORBAT) du portail est-il utile au quotidien ?', NULL, 1.00, 5, '2026-04-06 18:33:03'),
(35, 7, 'single_choice', 'Pourquoi l’activation du mod TFAR dans Arma 3 ne suffit-elle pas à elle seule ?', 'Parce que TFAR dépend aussi d’un plugin TeamSpeak nécessaire à l’intégration vocale.', 1.00, 1, '2026-04-06 20:11:27'),
(36, 7, 'single_choice', 'Avant d’installer TFAR, quel contrôle est cohérent ?', 'Il faut vérifier les pré requis réels du poste avant de commencer.', 1.00, 2, '2026-04-06 20:11:27'),
(37, 7, 'single_choice', 'Dans le launcher Arma 3, vous voyez deux variantes proches de TFAR. Quelle conduite est correcte ?', 'Il faut supprimer l’ambiguïté et ne conserver que la source officielle retenue par l’unité.', 1.00, 3, '2026-04-06 20:11:27'),
(38, 7, 'single_choice', 'Quel énoncé décrit correctement la relation entre TeamSpeak et TFAR ?', 'TeamSpeak peut fonctionner seul sans pour autant fournir l’intégration TFAR.', 1.00, 4, '2026-04-06 20:11:27'),
(39, 7, 'single_choice', 'Quelle affirmation est la plus juste après installation ?', 'Une installation doit être testée pour être considérée comme valable.', 1.00, 5, '2026-04-06 20:11:27'),
(40, 7, 'single_choice', 'En cas de panne, quelle attitude est la plus utile pour le support ?', 'Un symptôme clair et les vérifications déjà effectuées permettent un diagnostic rapide.', 1.00, 6, '2026-04-06 20:11:27'),
(41, 8, 'single_choice', 'Quelle est la finalité correcte de Task Force Radio dans un environnement Arma 3 organisé ?', 'TFAR vise à intégrer la communication vocale à la logique radio et de proximité du jeu.', 1.00, 1, '2026-04-06 20:11:27'),
(42, 8, 'single_choice', 'Quel couple d’éléments doit être cohérent pour qu’une installation TFAR soit exploitable ?', 'Le mod côté jeu et le plugin côté TeamSpeak doivent fonctionner ensemble.', 1.00, 2, '2026-04-06 20:11:27'),
(43, 8, 'single_choice', 'Quelle étape est cohérente avant même de télécharger ou synchroniser TFAR ?', 'Il faut d’abord vérifier que le poste dispose des pré requis essentiels.', 1.00, 3, '2026-04-06 20:11:27'),
(44, 8, 'single_choice', 'Pourquoi faut-il éviter de mélanger plusieurs sources ou versions proches de TFAR ?', 'Parce que cela crée des conflits de chargement et rend le diagnostic difficile.', 1.00, 4, '2026-04-06 20:11:27'),
(45, 8, 'single_choice', 'Quel est le rôle principal du launcher Arma 3 dans le cadre de cette installation ?', 'Le launcher permet de voir et contrôler ce qui est réellement chargé côté jeu.', 1.00, 5, '2026-04-06 20:11:27'),
(46, 8, 'single_choice', 'Dans TeamSpeak, quel contrôle est indispensable après installation du plugin TFAR ?', 'Il faut vérifier que le plugin est bien présent et activé.', 1.00, 6, '2026-04-06 20:11:27'),
(47, 8, 'single_choice', 'Quel énoncé est juste concernant TeamSpeak seul ?', 'TeamSpeak peut fonctionner comme logiciel vocal sans que l’intégration TFAR soit active.', 1.00, 7, '2026-04-06 20:11:27'),
(48, 8, 'single_choice', 'Après installation du mod et du plugin, quelle affirmation est correcte ?', 'L’installation n’est considérée comme solide qu’après un test de fonctionnement.', 1.00, 8, '2026-04-06 20:11:27'),
(49, 8, 'single_choice', 'Quel ordre de vérification est le plus cohérent avant mission ?', 'On contrôle d’abord le chargement du mod, puis le plugin, puis le test réel.', 1.00, 9, '2026-04-06 20:11:27'),
(50, 8, 'single_choice', 'Quel comportement est le plus utile si vous devez demander de l’aide ?', 'Une demande utile décrit le symptôme et les contrôles déjà effectués.', 1.00, 10, '2026-04-06 20:11:27');

-- --------------------------------------------------------

--
-- Structure de la table `training_quiz_responses`
--

CREATE TABLE `training_quiz_responses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attempt_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `response_text` longtext DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_awarded` decimal(6,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_quiz_responses`
--

INSERT INTO `training_quiz_responses` (`id`, `attempt_id`, `question_id`, `answer_id`, `response_text`, `is_correct`, `points_awarded`, `created_at`) VALUES
(1, 4, 30, 118, NULL, 1, 1.00, '2026-04-06 19:06:51'),
(2, 4, 31, 123, NULL, 1, 1.00, '2026-04-06 19:06:51'),
(3, 4, 32, 128, NULL, 1, 1.00, '2026-04-06 19:06:51'),
(4, 4, 33, 129, NULL, 1, 1.00, '2026-04-06 19:06:51'),
(5, 4, 34, 136, NULL, 1, 1.00, '2026-04-06 19:06:51'),
(6, 5, 19, 73, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(7, 5, 20, 77, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(8, 5, 21, 81, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(9, 5, 22, 85, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(10, 5, 23, 89, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(11, 5, 24, 93, NULL, 1, 1.00, '2026-04-06 19:31:09'),
(12, 6, 30, 118, NULL, 1, 1.00, '2026-04-06 19:32:02'),
(13, 6, 31, 123, NULL, 1, 1.00, '2026-04-06 19:32:02'),
(14, 6, 32, 128, NULL, 1, 1.00, '2026-04-06 19:32:02'),
(15, 6, 33, 129, NULL, 1, 1.00, '2026-04-06 19:32:02'),
(16, 6, 34, 136, NULL, 1, 1.00, '2026-04-06 19:32:02');

-- --------------------------------------------------------

--
-- Structure de la table `training_resources`
--

CREATE TABLE `training_resources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_id` bigint(20) UNSIGNED NOT NULL,
  `resource_type` enum('pdf','image','video','audio','zip','attachment','link','library_document') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `training_resources`
--

INSERT INTO `training_resources` (`id`, `lesson_id`, `resource_type`, `title`, `file_path`, `external_url`, `mime_type`, `file_size`, `document_id`, `created_at`) VALUES
(1, 35, 'link', 'TFAR - Classique', NULL, 'https://steamcommunity.com/workshop/filedetails/?id=620019431', NULL, NULL, NULL, '2026-04-06 21:23:55'),
(2, 35, 'link', 'TFAR - Beta', NULL, 'https://steamcommunity.com/sharedfiles/filedetails/?id=894678801', NULL, NULL, NULL, '2026-04-06 21:24:14');

-- --------------------------------------------------------

--
-- Structure de la table `training_staff_ping_log`
--

CREATE TABLE `training_staff_ping_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `module_id` bigint(20) UNSIGNED NOT NULL,
  `ping_kind` varchar(32) NOT NULL DEFAULT 'module_blocked',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_trainer_roles`
--

CREATE TABLE `training_trainer_roles` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `training_trainer_roles`
--

INSERT INTO `training_trainer_roles` (`tenant_id`, `role_id`, `created_by_user_id`, `created_at`) VALUES
(7, 37, 5, '2026-04-10 13:32:50'),
(7, 73, 5, '2026-04-10 13:32:50'),
(7, 172, 5, '2026-04-10 13:32:50');

-- --------------------------------------------------------

--
-- Structure de la table `units`
--

CREATE TABLE `units` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `commander_user_id` int(10) UNSIGNED DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `public_blurb` text DEFAULT NULL,
  `public_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`public_tags`)),
  `show_on_public_page` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `units`
--

INSERT INTO `units` (`id`, `tenant_id`, `parent_id`, `name`, `slug`, `type`, `code`, `commander_user_id`, `display_order`, `public_blurb`, `public_tags`, `show_on_public_page`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Cerbere', 'cerbere', 'organization', NULL, 1, 0, NULL, NULL, 1, '2026-03-13 19:43:43', '2026-03-13 19:43:43'),
(2, 7, NULL, 'Administration de Recherche et Développement', 'etat-major', 'command', 'A-R&D', NULL, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-12 16:49:47'),
(3, 7, 2, 'UI/UX - Front', '1re-section', 'support', NULL, NULL, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-12 17:20:24'),
(4, 7, NULL, 'Administration Générale', 'administration-generale', 'command', 'AG-Global', 5, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-12 16:49:54');

-- --------------------------------------------------------

--
-- Structure de la table `usage_analytics_events`
--

CREATE TABLE `usage_analytics_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `session_hash` char(64) DEFAULT NULL,
  `category` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL,
  `subject_type` varchar(32) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL,
  `props` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`props`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `email_verification_sent_at` datetime DEFAULT NULL,
  `nationality_code` varchar(10) DEFAULT NULL,
  `preferred_grade_format` enum('classic','otan','hybrid') NOT NULL DEFAULT 'classic',
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `callsign` varchar(50) DEFAULT NULL,
  `profile_slug` varchar(40) DEFAULT NULL COMMENT 'Identifiant URL fiche personnel (tenant)',
  `steam_id` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `preferred_display_role_id` int(10) UNSIGNED DEFAULT NULL,
  `grade_id` bigint(20) UNSIGNED DEFAULT NULL,
  `professional_category_code` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `is_service_account` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Compte non connectable (bot / automatique)',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `email`, `email_verified_at`, `email_verification_sent_at`, `nationality_code`, `preferred_grade_format`, `password_hash`, `display_name`, `callsign`, `profile_slug`, `steam_id`, `avatar_url`, `role_id`, `preferred_display_role_id`, `grade_id`, `professional_category_code`, `status`, `is_service_account`, `last_login_at`, `created_at`, `updated_at`) VALUES
(3, 1, 'tetard.tanguy@gmail.com', '2026-04-04 16:09:10', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$R1JUM1hSLnlEenRpL3Ayaw$712JHsttH+eD0iS7qfW+jE1zovq+HrXCMEBg8mRDXbQ', 'NewPI', 'ADMIN', NULL, NULL, 'uploads/avatars/3_1775320910.png', 15, NULL, 1, NULL, 'active', 0, '2026-04-05 08:24:25', '2026-04-04 16:09:10', '2026-04-04 16:42:06'),
(4, 1, 'system.moderation@internal.local', '2026-04-05 08:43:52', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$eC5FYmV6U2NJLjVnemVCMw$STbZvnbDWhnbnZo5WgayzGr1HBeUHjpcOyM3Ud4Iaj4', 'Modération automatique', 'SYSMOD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'inactive', 1, NULL, '2026-04-05 08:43:52', '2026-04-05 08:43:52'),
(5, 7, 'tetard.tanguy@gmail.com', '2026-04-04 16:09:10', NULL, 'FR', 'hybrid', '$argon2id$v=19$m=65536,t=4,p=1$MWhSZGxDZmtjbU9kNGJHcg$m+jI/7zz2fIIkmy9jYDzLwoEph2TpFPfw99HPCTncyY', 'NewPI', 'ADMIN', 'newpi', NULL, 'uploads/avatars/5_1775380800.jpg', 22, NULL, 6, 'OFFICIER', 'active', 0, '2026-04-13 10:55:47', '2026-04-05 09:10:02', '2026-04-10 13:32:57'),
(6, 7, 'system.moderation@internal.local', '2026-04-05 09:10:02', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$OEJQVVBHSk9ZNlZoak1VOQ$XEXunBkJMfuo6mF4N8E6S7Klewf21XtRArOjqKcdX58', 'Modération automatique', 'SYSMOD', NULL, NULL, NULL, NULL, NULL, NULL, 'HORS_GRADE', 'inactive', 1, NULL, '2026-04-05 09:10:02', '2026-04-05 11:11:44'),
(7, 1, 'tanguy.inc@gmail.com', '2026-04-05 11:21:58', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$N1o3bHBoekVuWWlGcXNUdw$Oi+PE3ydLgjNaq38DM7myVdsiCu7aXBbTkd37pi0JL8', 'Tangohan', 'E-11', 'tangohan', NULL, NULL, 3, NULL, NULL, NULL, 'active', 0, '2026-04-06 17:47:37', '2026-04-05 11:03:21', '2026-04-05 11:21:58'),
(8, 7, 'tanguy.inc@gmail.com', '2026-04-06 21:28:45', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$cm5rZU9kSzRpUDBRMWFGMw$UB7YaFOYCX07ZU3a6oInuaPATSB9O/FsjYRMvHReRb8', 'Melvin MESNEL', NULL, 'melvin-mesnel', NULL, NULL, 41, NULL, NULL, NULL, 'active', 0, '2026-04-06 17:47:37', '2026-04-05 16:31:53', '2026-04-10 13:32:29');

-- --------------------------------------------------------

--
-- Structure de la table `user_alert_dismissals`
--

CREATE TABLE `user_alert_dismissals` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `scope` enum('platform','tenant') NOT NULL,
  `alert_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_alert_dismissals`
--

INSERT INTO `user_alert_dismissals` (`user_id`, `scope`, `alert_id`, `created_at`) VALUES
(5, 'platform', 1, '2026-04-05 09:52:10'),
(7, 'platform', 1, '2026-04-05 11:22:25');

-- --------------------------------------------------------

--
-- Structure de la table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `badge_id` int(10) UNSIGNED NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `granted_by_user_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_certifications`
--

CREATE TABLE `user_certifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `certification_id` int(10) UNSIGNED NOT NULL,
  `training_course_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `issued_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_forum_stats`
--

CREATE TABLE `user_forum_stats` (
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `post_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `reputation` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_forum_stats`
--

INSERT INTO `user_forum_stats` (`tenant_id`, `user_id`, `post_count`, `score`, `reputation`, `updated_at`) VALUES
(7, 5, 2, 0, 0, '2026-04-05 16:13:11');

-- --------------------------------------------------------

--
-- Structure de la table `user_login_devices`
--

CREATE TABLE `user_login_devices` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `fingerprint_hash` varchar(64) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `first_seen_ip` varchar(45) DEFAULT NULL,
  `last_seen_ip` varchar(45) DEFAULT NULL,
  `geo_country` varchar(2) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_login_devices`
--

INSERT INTO `user_login_devices` (`id`, `user_id`, `tenant_id`, `fingerprint_hash`, `user_agent`, `first_seen_ip`, `last_seen_ip`, `geo_country`, `last_seen_at`, `created_at`) VALUES
(1, 5, 7, '33ce8e1635031beb8fa0dbff7f1f3b22930089f4c8f62b83ad458bb135145ca1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', '2a01:e0a:8ee:2720:1c51:8e58:5169:60a4', 'FR', '2026-04-13 10:55:47', '2026-04-05 10:02:15'),
(2, 7, 1, 'fbecafe40e809d0105a4ab52af329e8f8660e8f5a54e5c52e6247c72f11bdfb2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', '2a01:e0a:8ee:2720:e5b0:837a:c533:57db', 'FR', '2026-04-06 17:47:37', '2026-04-05 11:22:14'),
(3, 5, 7, 'cb6a8b93bffcdbe2fa2689bdf60d0a13a6b918819b8a2af2886580899cd9b772', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3 Mobile/15E148 Safari/604.1', '2a0d:e487:414f:dab8:b014:a438:5559:5d7e', '2a0d:e487:414f:dab8:b014:a438:5559:5d7e', 'FR', '2026-04-06 21:43:28', '2026-04-06 21:43:28'),
(4, 5, 7, '938c12e8e4346402be687af02f040b6ff25590a7c5b40bd8f24c2a77a8be53a2', 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', '185.24.185.33', '185.24.185.25', 'FR', '2026-04-08 08:33:23', '2026-04-07 09:09:00');

-- --------------------------------------------------------

--
-- Structure de la table `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `channel` enum('email','in_app','push') NOT NULL DEFAULT 'in_app',
  `event_key` varchar(80) NOT NULL COMMENT 'forum.reply|courrier.sent|...',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_notification_preferences`
--

INSERT INTO `user_notification_preferences` (`id`, `user_id`, `tenant_id`, `channel`, `event_key`, `enabled`, `created_at`, `updated_at`) VALUES
(1, 5, 7, 'email', 'NEW_DEVICE_LOGIN', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(2, 5, 7, 'email', 'MULTIPLE_LOGIN_ATTEMPTS', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(3, 5, 7, 'email', 'PROFILE_INCOMPLETE_REMINDER', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(4, 5, 7, 'email', 'ATTENDANCE_REMINDER', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(5, 5, 7, 'email', 'ATTENDANCE_RSVP_CONFIRM', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(6, 5, 7, 'email', 'ATTENDANCE_EVENT_CANCELLED', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(7, 5, 7, 'email', 'ATTENDANCE_CHECKIN_CONFIRM', 1, '2026-04-06 17:19:51', '2026-04-06 21:20:20'),
(15, 5, 7, 'email', 'ATTENDANCE_RSVP_ORGANIZER', 1, '2026-04-06 21:20:20', NULL),
(16, 5, 7, 'email', 'COMMUNITY_REPORT_RECEIPT', 1, '2026-04-06 21:20:20', NULL),
(17, 5, 7, 'email', 'COMMUNITY_REPORT_HANDLED', 1, '2026-04-06 21:20:20', NULL),
(18, 5, 7, 'email', 'COMMUNITY_REPORT_NEW_STAFF', 1, '2026-04-06 21:20:20', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_permission_overrides`
--

CREATE TABLE `user_permission_overrides` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `grant_flag` tinyint(1) NOT NULL DEFAULT 1,
  `org_unit_id` int(10) UNSIGNED DEFAULT NULL,
  `co_unit_scope` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Miroir IFNULL(org_unit_id,0) — triggers',
  `reason` varchar(255) DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déclencheurs `user_permission_overrides`
--
DELIMITER $$
CREATE TRIGGER `upo_co_scope_bi` BEFORE INSERT ON `user_permission_overrides` FOR EACH ROW SET NEW.co_unit_scope = IFNULL(NEW.org_unit_id, 0)
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `upo_co_scope_bu` BEFORE UPDATE ON `user_permission_overrides` FOR EACH ROW SET NEW.co_unit_scope = IFNULL(NEW.org_unit_id, 0)
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `user_positions`
--

CREATE TABLE `user_positions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `position_id` int(10) UNSIGNED NOT NULL,
  `starts_at` date NOT NULL,
  `ends_at` date DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `arma_callsign` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `birth_date`, `nationality`, `timezone`, `language`, `arma_callsign`, `bio`, `phone`, `emergency_contact`, `created_at`, `updated_at`) VALUES
(3, 'Tanguy', 'TETARD', NULL, NULL, 'Europe/Paris', 'fr', NULL, NULL, '', NULL, '2026-04-04 16:42:06', NULL),
(5, 'Tanguy', 'TETARD', NULL, NULL, 'Europe/Paris', 'fr', NULL, NULL, NULL, NULL, '2026-04-05 09:53:44', '2026-04-09 17:09:05'),
(8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 17:47:45', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_profile_display_settings`
--

CREATE TABLE `user_profile_display_settings` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `forum_alias` varchar(80) DEFAULT NULL,
  `forum_label_mode` varchar(32) NOT NULL DEFAULT 'display_name',
  `forum_visible_role_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Rôle org affiché sur carte forum (NULL = rôle principal du compte)',
  `show_matricule_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_grade_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_unit_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_bio_forum` tinyint(1) NOT NULL DEFAULT 1,
  `hide_forum_level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = masquer LVL sur carte forum',
  `fiche_show_email_to_others` tinyint(1) NOT NULL DEFAULT 0,
  `fiche_show_matricule_to_others` tinyint(1) NOT NULL DEFAULT 1,
  `public_roster_opt_in` tinyint(1) NOT NULL DEFAULT 0,
  `hide_personal_info` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_profile_display_settings`
--

INSERT INTO `user_profile_display_settings` (`user_id`, `forum_alias`, `forum_label_mode`, `forum_visible_role_id`, `show_matricule_forum`, `show_grade_forum`, `show_unit_forum`, `show_bio_forum`, `hide_forum_level`, `fiche_show_email_to_others`, `fiche_show_matricule_to_others`, `public_roster_opt_in`, `hide_personal_info`, `created_at`, `updated_at`) VALUES
(5, NULL, 'display_name', 14, 0, 1, 1, 1, 1, 0, 0, 1, 1, '2026-04-05 09:17:12', '2026-04-09 17:09:05'),
(8, NULL, 'display_name', 25, 1, 1, 1, 1, 1, 0, 1, 1, 0, '2026-04-06 17:46:32', '2026-04-06 19:15:34');

-- --------------------------------------------------------

--
-- Structure de la table `user_progress`
--

CREATE TABLE `user_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `status` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') NOT NULL DEFAULT 'NOT_STARTED',
  `score` decimal(5,2) DEFAULT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_progress_event_logs`
--

CREATE TABLE `user_progress_event_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `module_id` int(10) UNSIGNED NOT NULL,
  `user_progress_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` enum('STATUS_CHANGED','RECURRENCE_SCHEDULED','RECURRENCE_DUE','RECERTIFICATION_ASSIGNED','AUTO_EXPIRED') NOT NULL,
  `status_before` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `status_after` enum('NOT_STARTED','IN_PROGRESS','COMPLETED','FAILED','EXPIRED') DEFAULT NULL,
  `expires_at_before` datetime DEFAULT NULL,
  `expires_at_after` datetime DEFAULT NULL,
  `source` enum('SYSTEM','INSTRUCTOR','COMMAND') NOT NULL DEFAULT 'SYSTEM',
  `source_user_id` int(10) UNSIGNED DEFAULT NULL,
  `event_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`) VALUES
(3, 15, '2026-04-05 11:59:36'),
(5, 22, '2026-04-10 13:32:57'),
(5, 23, '2026-04-10 13:32:57'),
(5, 24, '2026-04-10 13:32:57'),
(5, 25, '2026-04-10 13:32:57'),
(5, 26, '2026-04-10 13:32:57'),
(5, 27, '2026-04-10 13:32:57'),
(5, 28, '2026-04-10 13:32:57'),
(5, 29, '2026-04-10 13:32:57'),
(5, 37, '2026-04-10 13:32:57'),
(5, 38, '2026-04-10 13:32:57'),
(5, 39, '2026-04-10 13:32:57'),
(5, 40, '2026-04-10 13:32:57'),
(5, 41, '2026-04-10 13:32:57'),
(5, 73, '2026-04-10 13:32:57'),
(5, 172, '2026-04-10 13:32:57'),
(7, 3, '2026-04-05 11:59:36'),
(8, 41, '2026-04-10 13:32:29');

-- --------------------------------------------------------

--
-- Structure de la table `user_signatures`
--

CREATE TABLE `user_signatures` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Signature principale',
  `file_path` varchar(500) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_signatures`
--

INSERT INTO `user_signatures` (`id`, `user_id`, `tenant_id`, `name`, `file_path`, `is_default`, `created_at`) VALUES
(2, 3, 1, 'Signature principale', '1/3_69d13db7893584.81481083.png', 1, '2026-04-04 16:35:03');

-- --------------------------------------------------------

--
-- Structure de la table `user_ui_preferences`
--

CREATE TABLE `user_ui_preferences` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `theme` varchar(32) NOT NULL DEFAULT 'system' COMMENT 'system|light|dark|tenant',
  `density` varchar(16) NOT NULL DEFAULT 'comfortable' COMMENT 'compact|comfortable',
  `sidebar_collapsed` tinyint(1) NOT NULL DEFAULT 0,
  `dashboard_layout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{schema_version:int, widgets:[...]}' CHECK (json_valid(`dashboard_layout_json`)),
  `favorite_modules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`favorite_modules_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_ui_preferences`
--

INSERT INTO `user_ui_preferences` (`user_id`, `tenant_id`, `theme`, `density`, `sidebar_collapsed`, `dashboard_layout_json`, `favorite_modules_json`, `created_at`, `updated_at`) VALUES
(5, 7, 'dark', 'comfortable', 1, NULL, NULL, '2026-04-06 17:19:51', '2026-04-06 21:20:20');

-- --------------------------------------------------------

--
-- Structure de la table `user_units`
--

CREATE TABLE `user_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `assignment_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `user_units`
--

INSERT INTO `user_units` (`id`, `user_id`, `unit_id`, `is_primary`, `assigned_by`, `assigned_at`, `ended_at`, `assignment_type`, `notes`) VALUES
(1, 5, 2, 1, NULL, '2026-04-05 10:53:05', '2026-04-05 11:45:48', 'Officier opérations', NULL),
(2, 5, 2, 1, NULL, '2026-04-05 11:45:48', '2026-04-05 11:59:27', 'Officier opérations', NULL),
(3, 5, 2, 1, NULL, '2026-04-05 11:59:27', '2026-04-05 11:59:38', 'Officier opérations', NULL),
(4, 5, 2, 1, NULL, '2026-04-05 11:59:38', '2026-04-05 12:09:47', 'Officier opérations', NULL),
(5, 5, 2, 1, NULL, '2026-04-05 12:09:47', '2026-04-06 18:59:04', 'Officier opérations', NULL),
(6, 8, 4, 1, NULL, '2026-04-06 17:46:32', '2026-04-06 20:49:50', 'Instructeur — Spécialiste communication', NULL),
(7, 5, 2, 1, NULL, '2026-04-06 18:59:04', '2026-04-06 18:59:13', 'Officier opérations — Officier gestionnaire admini', NULL),
(8, 5, 2, 1, NULL, '2026-04-06 18:59:13', '2026-04-06 20:51:45', 'Officier opérations — Spécialiste gestionnaire adm', NULL),
(9, 8, 4, 1, NULL, '2026-04-06 20:49:50', '2026-04-06 21:28:45', 'Instructeur — Spécialiste communication · JTAC · G', NULL),
(10, 5, 2, 1, NULL, '2026-04-06 20:51:45', '2026-04-06 20:53:13', 'Officier opérations — Spécialiste gestionnaire adm', NULL),
(11, 5, 2, 1, NULL, '2026-04-06 20:53:13', '2026-04-09 17:09:05', 'Officier opérations — Spécialiste gestionnaire adm', NULL),
(12, 8, 3, 1, NULL, '2026-04-06 21:28:45', NULL, 'Recrue', NULL),
(13, 5, 2, 1, NULL, '2026-04-09 17:09:05', NULL, 'Officier opérations — Spécialiste gestionnaire adm', NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `app_maintenance`
--
ALTER TABLE `app_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scope_enabled` (`scope`,`is_enabled`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_starts_at` (`starts_at`),
  ADD KEY `idx_ends_at` (`ends_at`);

--
-- Index pour la table `app_maintenance_audit`
--
ALTER TABLE `app_maintenance_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maintenance_id` (`maintenance_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `asset_logistics_status`
--
ALTER TABLE `asset_logistics_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mission_asset` (`mission_id`,`asset_id`),
  ADD KEY `mission_id` (`mission_id`);

--
-- Index pour la table `asset_logistics_status_history`
--
ALTER TABLE `asset_logistics_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_logged` (`mission_id`,`logged_at`),
  ADD KEY `mission_asset_logged` (`mission_id`,`asset_id`,`logged_at`);

--
-- Index pour la table `async_jobs`
--
ALTER TABLE `async_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_async_jobs_poll` (`reserved_at`,`available_at`,`attempts`),
  ADD KEY `idx_async_jobs_type` (`job_type`);

--
-- Index pour la table `atak_air_assets`
--
ALTER TABLE `atak_air_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`callsign`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_chat_messages`
--
ALTER TABLE `atak_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_designator_targets`
--
ALTER TABLE `atak_designator_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`call_sign`);

--
-- Index pour la table `atak_intel`
--
ALTER TABLE `atak_intel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type_created` (`type`,`created_at`);

--
-- Index pour la table `atak_intel_photos`
--
ALTER TABLE `atak_intel_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_laser_codes`
--
ALTER TABLE `atak_laser_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_map_callsign` (`tenant_id`,`map_id`,`call_sign`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_last_activity`
--
ALTER TABLE `atak_last_activity`
  ADD PRIMARY KEY (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_layers`
--
ALTER TABLE `atak_layers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_maps`
--
ALTER TABLE `atak_maps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `atak_map_shapes`
--
ALTER TABLE `atak_map_shapes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_map_uid` (`tenant_id`,`map_id`,`shape_uid`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_markers`
--
ALTER TABLE `atak_markers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_nine_line`
--
ALTER TABLE `atak_nine_line`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_pings`
--
ALTER TABLE `atak_pings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_sigint_reports`
--
ALTER TABLE `atak_sigint_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`);

--
-- Index pour la table `atak_units`
--
ALTER TABLE `atak_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_map` (`tenant_id`,`map_id`),
  ADD KEY `map_callsign` (`map_id`,`call_sign`);

--
-- Index pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id_created_at` (`tenant_id`,`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_badges_tenant_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `blocked_indicators`
--
ALTER TABLE `blocked_indicators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scope_hash` (`scope`,`tenant_id`,`indicator_type`,`value_hash`),
  ADD KEY `expires` (`expires_at`),
  ADD KEY `idx_blocked_active_email_tenant` (`tenant_id`,`indicator_type`,`revoked_at`,`expires_at`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cert_tenant_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `certification_modules`
--
ALTER TABLE `certification_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_certification_module` (`certification_id`,`module_id`),
  ADD KEY `idx_certification_modules_module` (`module_id`);

--
-- Index pour la table `clearance_levels`
--
ALTER TABLE `clearance_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clearance_tenant_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `community_events`
--
ALTER TABLE `community_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_starts` (`tenant_id`,`starts_at`),
  ADD KEY `fk_ce_creator` (`created_by_user_id`),
  ADD KEY `ce_tenant_created` (`tenant_id`,`created_at`);

--
-- Index pour la table `community_event_rsvps`
--
ALTER TABLE `community_event_rsvps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `fk_rsvp_user` (`user_id`),
  ADD KEY `idx_rsvp_reminder` (`event_id`,`reminder_sent_at`);

--
-- Index pour la table `community_invitations`
--
ALTER TABLE `community_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `tenant_email` (`tenant_id`,`email`),
  ADD KEY `tenant_status` (`tenant_id`,`status`),
  ADD KEY `fk_ci_inviter` (`invited_by_user_id`),
  ADD KEY `fk_ci_accepted` (`accepted_user_id`);

--
-- Index pour la table `competencies`
--
ALTER TABLE `competencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_competency_domain_code` (`domain_id`,`code`),
  ADD KEY `idx_competency_framework` (`framework_id`),
  ADD KEY `idx_competency_parent` (`parent_competency_id`),
  ADD KEY `idx_competency_active` (`is_active`),
  ADD KEY `competency_level_fk` (`level_id`);

--
-- Index pour la table `competency_domains`
--
ALTER TABLE `competency_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_domain_level_code` (`level_id`,`code`),
  ADD KEY `idx_domain_framework` (`framework_id`),
  ADD KEY `idx_domain_level_order` (`level_id`,`sort_order`);

--
-- Index pour la table `competency_frameworks`
--
ALTER TABLE `competency_frameworks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_framework_tenant_code` (`tenant_id`,`code`),
  ADD KEY `idx_framework_tenant_active` (`tenant_id`,`is_active`);

--
-- Index pour la table `competency_levels`
--
ALTER TABLE `competency_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_level_framework_code` (`framework_id`,`code`),
  ADD KEY `idx_level_framework_order` (`framework_id`,`sort_order`);

--
-- Index pour la table `cooperation_announcement_templates`
--
ALTER TABLE `cooperation_announcement_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coop_ann_tpl` (`tenant_id`,`event_key`,`channel`),
  ADD KEY `idx_coop_ann_evt` (`event_key`,`is_active`);

--
-- Index pour la table `cooperation_catalog_entries`
--
ALTER TABLE `cooperation_catalog_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coop_catalog_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `idx_coop_catalog_tenant` (`tenant_id`,`is_active`,`sort_order`);

--
-- Index pour la table `cooperation_forum_announcement_log`
--
ALTER TABLE `cooperation_forum_announcement_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coop_forum_ann` (`mission_id`,`event_key`),
  ADD KEY `idx_coop_forum_ann_time` (`posted_at`);

--
-- Index pour la table `cooperation_mission_templates`
--
ALTER TABLE `cooperation_mission_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cmt_tenant` (`tenant_id`);

--
-- Index pour la table `cooperation_notification_outbox`
--
ALTER TABLE `cooperation_notification_outbox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cno_proc` (`processed_at`,`created_at`),
  ADD KEY `idx_cno_agg` (`aggregation_key`,`created_at`);

--
-- Index pour la table `courrier_documents`
--
ALTER TABLE `courrier_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `reference_number` (`reference_number`),
  ADD KEY `courrier_documents_preset_fk` (`preset_id`),
  ADD KEY `courrier_documents_validated_by_fk` (`validated_by`),
  ADD KEY `courrier_documents_signed_by_fk` (`signed_by`);

--
-- Index pour la table `courrier_document_notifications`
--
ALTER TABLE `courrier_document_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_doc_recipient` (`document_id`,`recipient_user_id`),
  ADD KEY `idx_tenant_recipient_unread` (`tenant_id`,`recipient_user_id`,`read_at`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `cdn_recipient_fk` (`recipient_user_id`),
  ADD KEY `cdn_creator_fk` (`created_by_user_id`);

--
-- Index pour la table `courrier_document_versions`
--
ALTER TABLE `courrier_document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `courrier_document_versions_created_by_fk` (`created_by`);

--
-- Index pour la table `courrier_snippets`
--
ALTER TABLE `courrier_snippets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_snippet_tenant_code` (`tenant_id`,`code`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `phase` (`phase`);

--
-- Index pour la table `danger_zones`
--
ALTER TABLE `danger_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_id` (`mission_id`),
  ADD KEY `mission_active` (`mission_id`,`active`);

--
-- Index pour la table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `documents_category_fk` (`document_category_id`),
  ADD KEY `idx_documents_status` (`status`),
  ADD KEY `idx_documents_owner` (`owner_user_id`),
  ADD KEY `idx_documents_parent` (`parent_document_id`),
  ADD KEY `idx_documents_classification` (`classification_level`),
  ADD KEY `idx_documents_visibility` (`visibility_scope`),
  ADD KEY `documents_author_user_id_fk` (`author_user_id`),
  ADD KEY `documents_current_file_id_fk` (`current_file_id`);

--
-- Index pour la table `document_audit_log`
--
ALTER TABLE `document_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_audit_log_document` (`document_id`),
  ADD KEY `idx_document_audit_log_user` (`user_id`);

--
-- Index pour la table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_document_collaborator` (`document_id`,`user_id`,`role`),
  ADD KEY `idx_document_collaborators_user` (`user_id`);

--
-- Index pour la table `document_links`
--
ALTER TABLE `document_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `entity_type` (`entity_type`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `document_permissions`
--
ALTER TABLE `document_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_permissions_lookup` (`permission_type`,`permission_value`,`access_level`),
  ADD KEY `fk_document_permissions_document` (`document_id`);

--
-- Index pour la table `document_presets`
--
ALTER TABLE `document_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `code` (`code`);

--
-- Index pour la table `document_relations`
--
ALTER TABLE `document_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_document_relation` (`parent_document_id`,`child_document_id`,`relation_type`),
  ADD KEY `fk_document_relations_child` (`child_document_id`);

--
-- Index pour la table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `slug` (`slug`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `preset_id` (`preset_id`),
  ADD KEY `document_templates_created_by_fk` (`created_by`),
  ADD KEY `document_templates_updated_by_fk` (`updated_by`);

--
-- Index pour la table `document_template_versions`
--
ALTER TABLE `document_template_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `document_template_versions_created_by_fk` (`created_by`);

--
-- Index pour la table `document_variables_catalog`
--
ALTER TABLE `document_variables_catalog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `code` (`code`),
  ADD KEY `category` (`category`);

--
-- Index pour la table `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id_is_current` (`document_id`,`is_current`);

--
-- Index pour la table `document_workflows`
--
ALTER TABLE `document_workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `document_workflows_acted_by_fk` (`acted_by`);

--
-- Index pour la table `email_deliveries`
--
ALTER TABLE `email_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_deliveries_tenant_created` (`tenant_id`,`created_at`),
  ADD KEY `idx_email_deliveries_event` (`event_code`,`created_at`);

--
-- Index pour la table `email_tokens`
--
ALTER TABLE `email_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_email_tokens_hash` (`token_hash`),
  ADD KEY `idx_email_tokens_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_email_tokens_expires` (`expires_at`),
  ADD KEY `email_tokens_tenant_fk` (`tenant_id`);

--
-- Index pour la table `enlistments`
--
ALTER TABLE `enlistments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id_status` (`tenant_id`,`status`),
  ADD KEY `submitter_user_id` (`submitter_user_id`),
  ADD KEY `enlistments_recruitment_preset_fk` (`recruitment_preset_id`),
  ADD KEY `enlistments_recruitment_opening` (`recruitment_opening_id`);

--
-- Index pour la table `enlistment_canned_messages`
--
ALTER TABLE `enlistment_canned_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_sort` (`tenant_id`,`sort_order`);

--
-- Index pour la table `equipment_classes`
--
ALTER TABLE `equipment_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evaluation_module_type` (`module_id`,`evaluation_type`),
  ADD KEY `evaluation_validator_role_fk` (`validator_role_id`);

--
-- Index pour la table `fire_tables`
--
ALTER TABLE `fire_tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `weapon_ammo` (`weapon_system`,`ammo_type`);

--
-- Index pour la table `fire_units`
--
ALTER TABLE `fire_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_id` (`mission_id`),
  ADD KEY `mission_callsign` (`mission_id`,`callsign`);

--
-- Index pour la table `forum_attachments`
--
ALTER TABLE `forum_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Index pour la table `forum_banned_words`
--
ALTER TABLE `forum_banned_words`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `forum_blacklisted_domains`
--
ALTER TABLE `forum_blacklisted_domains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `forum_categories`
--
ALTER TABLE `forum_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `forum_categories_min_role_id_fk` (`min_role_id`),
  ADD KEY `forum_categories_scope` (`scope`),
  ADD KEY `forum_categories_owner_tenant` (`owner_tenant_id`);

--
-- Index pour la table `forum_category_subscriptions`
--
ALTER TABLE `forum_category_subscriptions`
  ADD PRIMARY KEY (`user_id`,`category_id`),
  ADD KEY `forum_category_subscriptions_category_id_fk` (`category_id`);

--
-- Index pour la table `forum_moderation_logs`
--
ALTER TABLE `forum_moderation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `forum_moderation_logs_user_fk` (`user_id`);

--
-- Index pour la table `forum_moderation_rules`
--
ALTER TABLE `forum_moderation_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `forum_notifications`
--
ALTER TABLE `forum_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_user` (`tenant_id`,`user_id`),
  ADD KEY `read_at` (`read_at`),
  ADD KEY `forum_notifications_user_fk` (`user_id`);

--
-- Index pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `topic_created` (`topic_id`,`created_at`),
  ADD KEY `forum_posts_user_id_fk` (`user_id`),
  ADD KEY `forum_posts_parent` (`parent_post_id`),
  ADD KEY `forum_posts_coop_src` (`coop_source_tenant_id`);

--
-- Index pour la table `forum_post_reactions`
--
ALTER TABLE `forum_post_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_post_user` (`post_id`,`user_id`),
  ADD KEY `idx_tenant_post` (`tenant_id`,`post_id`),
  ADD KEY `forum_post_reactions_user_fk` (`user_id`);

--
-- Index pour la table `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `forum_vote_user_post` (`post_id`,`user_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `forum_read`
--
ALTER TABLE `forum_read`
  ADD PRIMARY KEY (`user_id`,`topic_id`),
  ADD KEY `forum_read_topic_id_fk` (`topic_id`);

--
-- Index pour la table `forum_reports`
--
ALTER TABLE `forum_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `forum_reports_reporter_id_fk` (`reporter_id`),
  ADD KEY `forum_reports_post_id_fk` (`post_id`),
  ADD KEY `forum_reports_topic_id_fk` (`topic_id`),
  ADD KEY `forum_reports_handled_by_fk` (`handled_by`);

--
-- Index pour la table `forum_report_events`
--
ALTER TABLE `forum_report_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `forum_report_events_actor_fk` (`actor_id`);

--
-- Index pour la table `forum_tags`
--
ALTER TABLE `forum_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `category_updated` (`category_id`,`updated_at`),
  ADD KEY `forum_topics_user_id_fk` (`user_id`),
  ADD KEY `forum_topics_best_answer` (`best_answer_post_id`);

--
-- Index pour la table `forum_topic_subscriptions`
--
ALTER TABLE `forum_topic_subscriptions`
  ADD PRIMARY KEY (`user_id`,`topic_id`),
  ADD KEY `forum_topic_subscriptions_topic_id_fk` (`topic_id`);

--
-- Index pour la table `forum_topic_tags`
--
ALTER TABLE `forum_topic_tags`
  ADD PRIMARY KEY (`topic_id`,`tag_id`),
  ADD KEY `forum_topic_tags_tag_fk` (`tag_id`);

--
-- Index pour la table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_system_id` (`grade_system_id`),
  ADD KEY `grade_category_id` (`grade_category_id`),
  ADD KEY `sort_order` (`sort_order`),
  ADD KEY `is_active` (`is_active`);

--
-- Index pour la table `grades_legacy`
--
ALTER TABLE `grades_legacy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `grade_categories`
--
ALTER TABLE `grade_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `is_active` (`is_active`);

--
-- Index pour la table `grade_systems`
--
ALTER TABLE `grade_systems`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `country_code` (`country_code`),
  ADD KEY `is_active` (`is_active`);

--
-- Index pour la table `iff_asset_status`
--
ALTER TABLE `iff_asset_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mission_asset` (`mission_id`,`asset_id`),
  ADD KEY `mission_id` (`mission_id`),
  ADD KEY `current_challenge_id` (`current_challenge_id`);

--
-- Index pour la table `iff_challenges`
--
ALTER TABLE `iff_challenges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_id` (`mission_id`),
  ADD KEY `mission_valid` (`mission_id`,`valid_until`);

--
-- Index pour la table `intel_reports`
--
ALTER TABLE `intel_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_id` (`mission_id`),
  ADD KEY `mission_status` (`mission_id`,`status`),
  ADD KEY `mission_last_seen` (`mission_id`,`last_seen_at`);

--
-- Index pour la table `intel_reports_events`
--
ALTER TABLE `intel_reports_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `intel_report_id` (`intel_report_id`);

--
-- Index pour la table `interteam_cooperation_consents`
--
ALTER TABLE `interteam_cooperation_consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interteam_consent_uq` (`mission_id`,`user_id`),
  ADD KEY `interteam_consent_user` (`user_id`);

--
-- Index pour la table `interteam_cooperation_otp_attempts`
--
ALTER TABLE `interteam_cooperation_otp_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user_mission` (`user_id`,`mission_id`,`created_at`),
  ADD KEY `fk_otp_mission` (`mission_id`);

--
-- Index pour la table `interteam_missions`
--
ALTER TABLE `interteam_missions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interteam_missions_slug` (`slug`),
  ADD KEY `interteam_missions_lead_tenant` (`created_by_tenant_id`),
  ADD KEY `interteam_missions_status` (`status`);

--
-- Index pour la table `interteam_mission_events`
--
ALTER TABLE `interteam_mission_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interteam_ev_mission` (`mission_id`,`created_at`),
  ADD KEY `interteam_ev_actor` (`actor_user_id`);

--
-- Index pour la table `interteam_mission_forum_grants`
--
ALTER TABLE `interteam_mission_forum_grants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interteam_grant_unique` (`mission_id`,`grant_type`,`resource_id`,`consumer_tenant_id`),
  ADD KEY `interteam_grant_consumer` (`consumer_tenant_id`,`mission_id`),
  ADD KEY `interteam_grant_home` (`home_tenant_id`);

--
-- Index pour la table `interteam_mission_meetings`
--
ALTER TABLE `interteam_mission_meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interteam_mm_mission` (`mission_id`);

--
-- Index pour la table `interteam_mission_members`
--
ALTER TABLE `interteam_mission_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_imm_mission_user` (`mission_id`,`user_id`),
  ADD KEY `idx_imm_mission` (`mission_id`),
  ADD KEY `idx_imm_tenant` (`tenant_id`);

--
-- Index pour la table `interteam_mission_participants`
--
ALTER TABLE `interteam_mission_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interteam_mp_unique` (`mission_id`,`tenant_id`),
  ADD KEY `interteam_mp_tenant` (`tenant_id`),
  ADD KEY `interteam_mp_mission_status` (`mission_id`,`status`);

--
-- Index pour la table `interteam_mission_rex`
--
ALTER TABLE `interteam_mission_rex`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `interteam_rex_mission_tenant` (`mission_id`,`tenant_id`),
  ADD KEY `interteam_rex_mission` (`mission_id`);

--
-- Index pour la table `knowledge_units`
--
ALTER TABLE `knowledge_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_knowledge_competency_code` (`competency_id`,`code`),
  ADD KEY `idx_knowledge_competency_order` (`competency_id`,`sort_order`);

--
-- Index pour la table `legacy_training_certificates`
--
ALTER TABLE `legacy_training_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_training_module_id` (`user_id`,`training_module_id`),
  ADD KEY `training_certificates_tenant_id_fk` (`tenant_id`),
  ADD KEY `training_certificates_module_fk` (`training_module_id`);

--
-- Index pour la table `legacy_training_modules`
--
ALTER TABLE `legacy_training_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `legacy_training_progress`
--
ALTER TABLE `legacy_training_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_training_module_id` (`user_id`,`training_module_id`),
  ADD KEY `training_progress_tenant_id_fk` (`tenant_id`),
  ADD KEY `training_progress_module_fk` (`training_module_id`);

--
-- Index pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_created_at` (`email`,`created_at`);

--
-- Index pour la table `logs_positions`
--
ALTER TABLE `logs_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mission_logged` (`mission_id`,`logged_at`),
  ADD KEY `mission_unit_logged` (`mission_id`,`unit_id`,`logged_at`);

--
-- Index pour la table `moderation_actions`
--
ALTER TABLE `moderation_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_target` (`tenant_id`,`target_user_id`),
  ADD KEY `active_revoked` (`tenant_id`,`target_user_id`,`revoked_at`),
  ADD KEY `fk_ma_case` (`case_id`),
  ADD KEY `fk_ma_target` (`target_user_id`),
  ADD KEY `fk_ma_actor` (`actor_user_id`);

--
-- Index pour la table `moderation_artifacts`
--
ALTER TABLE `moderation_artifacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mod_tenant_state_score` (`tenant_id`,`state`,`risk_score`,`created_at`),
  ADD KEY `idx_mod_source` (`source_type`,`source_id`),
  ADD KEY `idx_mod_source_key` (`tenant_id`,`source_type`,`source_key`(120)),
  ADD KEY `moderation_artifacts_user_fk` (`user_id`);

--
-- Index pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_subject` (`tenant_id`,`subject_user_id`),
  ADD KEY `fk_mc_subject` (`subject_user_id`),
  ADD KEY `fk_mc_opener` (`opened_by_user_id`);

--
-- Index pour la table `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mod_dec_artifact` (`artifact_id`),
  ADD KEY `moderation_decisions_actor_fk` (`actor_user_id`);

--
-- Index pour la table `moderation_evidence`
--
ALTER TABLE `moderation_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `fk_me_tenant` (`tenant_id`),
  ADD KEY `fk_me_author` (`created_by_user_id`);

--
-- Index pour la table `modpacks`
--
ALTER TABLE `modpacks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `modpacks_created_by_fk` (`created_by`);

--
-- Index pour la table `modpack_images`
--
ALTER TABLE `modpack_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modpack_id` (`modpack_id`);

--
-- Index pour la table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_module_tenant_code` (`tenant_id`,`code`),
  ADD KEY `idx_module_tenant_type` (`tenant_id`,`module_type`),
  ADD KEY `idx_module_framework` (`framework_id`),
  ADD KEY `idx_module_active` (`is_active`),
  ADD KEY `module_created_by_fk` (`created_by`);

--
-- Index pour la table `module_competencies`
--
ALTER TABLE `module_competencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_module_competency` (`module_id`,`competency_id`),
  ADD KEY `idx_module_competency_competency` (`competency_id`);

--
-- Index pour la table `module_dependencies`
--
ALTER TABLE `module_dependencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_module_dep_pair` (`module_id`,`requires_module_id`,`dependency_type`),
  ADD KEY `idx_module_dep_requires` (`requires_module_id`);

--
-- Index pour la table `module_knowledge`
--
ALTER TABLE `module_knowledge`
  ADD PRIMARY KEY (`module_id`,`knowledge_id`),
  ADD KEY `idx_module_knowledge_knowledge` (`knowledge_id`);

--
-- Index pour la table `module_sequences`
--
ALTER TABLE `module_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_module_sequence_framework_module` (`framework_id`,`module_id`),
  ADD UNIQUE KEY `uk_module_sequence_framework_order` (`framework_id`,`sequence_order`),
  ADD KEY `module_sequence_module_fk` (`module_id`);

--
-- Index pour la table `ops_board_assets`
--
ALTER TABLE `ops_board_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ops_board_assets_item` (`item_id`);

--
-- Index pour la table `ops_board_assignments`
--
ALTER TABLE `ops_board_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ops_board_assignments` (`item_id`,`user_id`,`role_label`),
  ADD KEY `idx_ops_board_assignments_item` (`item_id`,`is_lead`),
  ADD KEY `fk_ops_board_assignments_user` (`user_id`);

--
-- Index pour la table `ops_board_audience`
--
ALTER TABLE `ops_board_audience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ops_board_audience_item` (`item_id`,`audience_type`),
  ADD KEY `idx_ops_board_audience_lookup` (`audience_type`,`audience_value`);

--
-- Index pour la table `ops_board_history`
--
ALTER TABLE `ops_board_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ops_board_history_item` (`item_id`,`created_at`),
  ADD KEY `fk_ops_board_history_actor` (`actor_user_id`);

--
-- Index pour la table `ops_board_items`
--
ALTER TABLE `ops_board_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ops_board_items_tenant_block` (`tenant_id`,`block_type`,`status`),
  ADD KEY `idx_ops_board_items_dates` (`start_date`,`end_date`,`publish_at`),
  ADD KEY `idx_ops_board_items_priority` (`priority`,`is_pinned`,`display_order`),
  ADD KEY `idx_ops_board_items_visibility` (`visibility_level`),
  ADD KEY `fk_ops_board_items_unit` (`unit_id`),
  ADD KEY `fk_ops_board_items_created_by` (`created_by`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token_hash` (`token_hash`),
  ADD KEY `password_resets_user_id_fk` (`user_id`);

--
-- Index pour la table `pending_community_creates`
--
ALTER TABLE `pending_community_creates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pcc_token` (`token`),
  ADD KEY `pcc_user` (`user_id`),
  ADD KEY `pcc_stripe_sess` (`stripe_checkout_session_id`),
  ADD KEY `pcc_tenant` (`tenant_id`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `permissions_tenant_module_action` (`tenant_id`,`module`,`action`);

--
-- Index pour la table `personnel_admin_data`
--
ALTER TABLE `personnel_admin_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_panel_id` (`user_id`,`panel_id`),
  ADD KEY `personnel_admin_data_panel_id_fk` (`panel_id`);

--
-- Index pour la table `personnel_admin_panels`
--
ALTER TABLE `personnel_admin_panels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`);

--
-- Index pour la table `personnel_assignments`
--
ALTER TABLE `personnel_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_assignments_user` (`user_id`),
  ADD KEY `personnel_assignments_unit` (`unit_id`);

--
-- Index pour la table `personnel_extras`
--
ALTER TABLE `personnel_extras`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `personnel_extras_clearance_level_id` (`clearance_level_id`);

--
-- Index pour la table `personnel_job_roles`
--
ALTER TABLE `personnel_job_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pjr_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `pjr_tenant_cat` (`tenant_id`,`category_id`),
  ADD KEY `pjr_category_fk` (`category_id`);

--
-- Index pour la table `personnel_job_role_categories`
--
ALTER TABLE `personnel_job_role_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pjrc_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `pjrc_tenant_parent` (`tenant_id`,`parent_id`),
  ADD KEY `pjrc_parent_fk` (`parent_id`);

--
-- Index pour la table `personnel_job_role_permissions`
--
ALTER TABLE `personnel_job_role_permissions`
  ADD PRIMARY KEY (`personnel_job_role_id`,`permission_id`),
  ADD KEY `pjrp_perm` (`permission_id`);

--
-- Index pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_media_user_type` (`user_id`,`media_type`);

--
-- Index pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personnel_profiles_user_id` (`user_id`),
  ADD KEY `personnel_profiles_primary_unit` (`primary_unit_id`),
  ADD KEY `pp_personnel_job_role_fk` (`personnel_job_role_id`);

--
-- Index pour la table `personnel_profile_job_roles`
--
ALTER TABLE `personnel_profile_job_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ppjr_tenant_user_role` (`tenant_id`,`user_id`,`personnel_job_role_id`),
  ADD KEY `idx_ppjr_tenant_user` (`tenant_id`,`user_id`),
  ADD KEY `idx_ppjr_jobrole` (`personnel_job_role_id`),
  ADD KEY `ppjr_user_fk` (`user_id`);

--
-- Index pour la table `personnel_qualifications`
--
ALTER TABLE `personnel_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_qualifications_user` (`user_id`),
  ADD KEY `personnel_qualifications_status` (`status`);

--
-- Index pour la table `personnel_service_history`
--
ALTER TABLE `personnel_service_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `personnel_service_history_user` (`user_id`),
  ADD KEY `personnel_service_history_date` (`event_date`);

--
-- Index pour la table `platform_alerts`
--
ALTER TABLE `platform_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_platform_alerts_active` (`is_active`,`sort_order`);

--
-- Index pour la table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Index pour la table `platform_usage_events`
--
ALTER TABLE `platform_usage_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_day` (`tenant_id`,`created_at`),
  ADD KEY `feature` (`feature_key`);

--
-- Index pour la table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_positions_tenant` (`tenant_id`);

--
-- Index pour la table `recon_images`
--
ALTER TABLE `recon_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_mission` (`tenant_id`,`mission_id`),
  ADD KEY `author_callsign` (`author_callsign`),
  ADD KEY `captured_at` (`captured_at`);

--
-- Index pour la table `recruitment_openings`
--
ALTER TABLE `recruitment_openings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ro_tenant_public_slug` (`tenant_id`,`public_page_slug`),
  ADD KEY `idx_ro_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_ro_unit` (`unit_id`),
  ADD KEY `ro_creator_fk` (`created_by_user_id`),
  ADD KEY `ro_pjr_fk` (`personnel_job_role_id`);

--
-- Index pour la table `recruitment_opening_counters`
--
ALTER TABLE `recruitment_opening_counters`
  ADD PRIMARY KEY (`tenant_id`,`year`);

--
-- Index pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `recurrence_rules`
--
ALTER TABLE `recurrence_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_recurrence_module` (`module_id`);

--
-- Index pour la table `referral_attributions`
--
ALTER TABLE `referral_attributions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referral_attr` (`referrer_user_id`,`referred_tenant_id`,`event_type`),
  ADD KEY `idx_referrer` (`referrer_user_id`),
  ADD KEY `idx_tenant` (`referred_tenant_id`);

--
-- Index pour la table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referral_codes_user` (`user_id`),
  ADD UNIQUE KEY `uq_referral_codes_code` (`code`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `roles_tenant_layer` (`tenant_id`,`role_layer`),
  ADD KEY `roles_definition_id` (`definition_id`),
  ADD KEY `roles_parent_fk` (`parent_role_id`);

--
-- Index pour la table `role_assignments_log`
--
ALTER TABLE `role_assignments_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ral_tenant_user` (`tenant_id`,`user_id`),
  ADD KEY `idx_ral_role` (`role_id`);

--
-- Index pour la table `role_definitions`
--
ALTER TABLE `role_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_definitions_slug` (`slug`),
  ADD KEY `idx_role_definitions_family` (`family`);

--
-- Index pour la table `role_definition_relations`
--
ALTER TABLE `role_definition_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rdr_pair` (`from_definition_id`,`to_definition_id`,`relation_type`),
  ADD KEY `idx_rdr_to` (`to_definition_id`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Index pour la table `role_relations`
--
ALTER TABLE `role_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rr_tenant_pair` (`tenant_id`,`from_role_id`,`to_role_id`,`relation_type`),
  ADD KEY `idx_rr_from` (`from_role_id`),
  ADD KEY `idx_rr_to` (`to_role_id`);

--
-- Index pour la table `role_requirements`
--
ALTER TABLE `role_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_requirement_unique` (`role_id`,`required_module_id`,`required_certification_id`),
  ADD KEY `idx_role_requirement_module` (`required_module_id`),
  ADD KEY `idx_role_requirement_certification` (`required_certification_id`);

--
-- Index pour la table `role_sets`
--
ALTER TABLE `role_sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_role_sets_tenant` (`tenant_id`);

--
-- Index pour la table `role_set_roles`
--
ALTER TABLE `role_set_roles`
  ADD PRIMARY KEY (`role_set_id`,`role_id`),
  ADD KEY `rsr_role_fk` (`role_id`);

--
-- Index pour la table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_type` (`tenant_id`,`event_type`),
  ADD KEY `created` (`created_at`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_tenant_id` (`user_id`,`tenant_id`),
  ADD KEY `sessions_tenant_id_fk` (`tenant_id`);

--
-- Index pour la table `site_role_assignments`
--
ALTER TABLE `site_role_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_site_role_email_role` (`email_normalized`,`role_id`),
  ADD KEY `email_normalized` (`email_normalized`),
  ADD KEY `role_id` (`role_id`);

--
-- Index pour la table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_key` (`tenant_id`,`key`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `tenants_community_code` (`community_code`),
  ADD KEY `tenants_plan_slug` (`plan_slug`),
  ADD KEY `tenants_owner_user_id_fk` (`owner_user_id`);

--
-- Index pour la table `tenant_alerts`
--
ALTER TABLE `tenant_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_alerts_tenant` (`tenant_id`,`is_active`,`sort_order`);

--
-- Index pour la table `tenant_api_keys`
--
ALTER TABLE `tenant_api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_api_keys_prefix` (`key_prefix`),
  ADD KEY `idx_tenant_api_keys_tenant` (`tenant_id`);

--
-- Index pour la table `tenant_api_key_daily_usage`
--
ALTER TABLE `tenant_api_key_daily_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_api_key_day` (`api_key_id`,`usage_day`),
  ADD KEY `idx_api_usage_key` (`api_key_id`);

--
-- Index pour la table `tenant_atak_config`
--
ALTER TABLE `tenant_atak_config`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Index pour la table `tenant_branding`
--
ALTER TABLE `tenant_branding`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Index pour la table `tenant_community_feed`
--
ALTER TABLE `tenant_community_feed`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcf_tenant_created` (`tenant_id`,`created_at`);

--
-- Index pour la table `tenant_dashboard_pins`
--
ALTER TABLE `tenant_dashboard_pins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tdp_tenant_sort` (`tenant_id`,`sort_order`),
  ADD KEY `tdp_doc_cat_fk` (`document_category_id`),
  ADD KEY `tdp_document_fk` (`document_id`),
  ADD KEY `tdp_courrier_fk` (`courrier_document_id`),
  ADD KEY `tdp_created_by_fk` (`created_by`);

--
-- Index pour la table `tenant_grade_overrides`
--
ALTER TABLE `tenant_grade_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_grade` (`tenant_id`,`grade_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Index pour la table `tenant_matricule_config`
--
ALTER TABLE `tenant_matricule_config`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Index pour la table `tenant_messages`
--
ALTER TABLE `tenant_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_created` (`thread_id`,`created_at`),
  ADD KEY `tm_sender_fk` (`sender_user_id`);

--
-- Index pour la table `tenant_message_threads`
--
ALTER TABLE `tenant_message_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_updated` (`tenant_id`,`updated_at`),
  ADD KEY `tmt_creator_fk` (`created_by_user_id`);

--
-- Index pour la table `tenant_message_thread_users`
--
ALTER TABLE `tenant_message_thread_users`
  ADD PRIMARY KEY (`thread_id`,`user_id`),
  ADD KEY `user_tenant_lookup` (`user_id`);

--
-- Index pour la table `tenant_modules`
--
ALTER TABLE `tenant_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_module` (`tenant_id`,`module_id`),
  ADD KEY `idx_tenant_module_active` (`tenant_id`,`is_active`),
  ADD KEY `tenant_modules_module_fk` (`module_id`);

--
-- Index pour la table `tenant_module_entitlements`
--
ALTER TABLE `tenant_module_entitlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tme_tenant_module` (`tenant_id`,`module_key`),
  ADD KEY `idx_tme_enabled` (`tenant_id`,`enabled`);

--
-- Index pour la table `tenant_quotas`
--
ALTER TABLE `tenant_quotas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tq_tenant_metric_period` (`tenant_id`,`metric_key`,`period`);

--
-- Index pour la table `tenant_security_policy`
--
ALTER TABLE `tenant_security_policy`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Index pour la table `tenant_training_logs`
--
ALTER TABLE `tenant_training_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_training_logs_tenant_scope` (`tenant_id`,`event_scope`,`created_at`),
  ADD KEY `idx_tenant_training_logs_actor` (`actor_user_id`,`created_at`),
  ADD KEY `idx_tenant_training_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `tenant_training_logs_actor_role_fk` (`actor_role_id`);

--
-- Index pour la table `tenant_usage_counters`
--
ALTER TABLE `tenant_usage_counters`
  ADD PRIMARY KEY (`tenant_id`,`metric_key`,`period_start`),
  ADD KEY `tenant_metric` (`tenant_id`,`metric_key`);

--
-- Index pour la table `tenant_user_roles`
--
ALTER TABLE `tenant_user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tur_scope` (`tenant_id`,`user_id`,`role_id`,`co_unit_id`),
  ADD KEY `idx_tur_user` (`user_id`),
  ADD KEY `idx_tur_tenant_role` (`tenant_id`,`role_id`),
  ADD KEY `idx_tur_unit` (`org_unit_id`),
  ADD KEY `tur_role_fk` (`role_id`);

--
-- Index pour la table `trainer_validation_logs`
--
ALTER TABLE `trainer_validation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trainer_validation_tenant` (`tenant_id`,`created_at`),
  ADD KEY `idx_trainer_validation_instructor` (`instructor_user_id`,`created_at`),
  ADD KEY `idx_trainer_validation_target` (`target_user_id`,`created_at`),
  ADD KEY `idx_trainer_validation_module` (`module_id`,`action_type`),
  ADD KEY `trainer_validation_logs_evaluation_fk` (`evaluation_id`),
  ADD KEY `trainer_validation_logs_progress_fk` (`user_progress_id`);

--
-- Index pour la table `training_audit_log`
--
ALTER TABLE `training_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_audit_target` (`target_type`,`target_id`),
  ADD KEY `idx_training_audit_user` (`user_id`),
  ADD KEY `idx_training_audit_tenant` (`tenant_id`);

--
-- Index pour la table `training_certificates`
--
ALTER TABLE `training_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_certificates_number` (`certificate_number`),
  ADD KEY `idx_training_certificates_enrollment` (`enrollment_id`),
  ADD KEY `fk_training_certificates_tenant` (`tenant_id`),
  ADD KEY `idx_training_certificates_issued_by` (`issued_by_user_id`);

--
-- Index pour la table `training_certificate_templates`
--
ALTER TABLE `training_certificate_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_cert_tpl_tenant` (`tenant_id`);

--
-- Index pour la table `training_competency_matrices`
--
ALTER TABLE `training_competency_matrices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcm_tenant` (`tenant_id`),
  ADD KEY `fk_tcm_created_by` (`created_by_user_id`),
  ADD KEY `fk_tcm_updated_by` (`updated_by_user_id`);

--
-- Index pour la table `training_competency_matrix_assignments`
--
ALTER TABLE `training_competency_matrix_assignments`
  ADD PRIMARY KEY (`matrix_id`,`user_id`),
  ADD KEY `idx_tcma_tenant_user` (`tenant_id`,`user_id`),
  ADD KEY `fk_tcma_user` (`user_id`),
  ADD KEY `fk_tcma_assigned_by` (`assigned_by_user_id`);

--
-- Index pour la table `training_courses`
--
ALTER TABLE `training_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_courses_uuid` (`uuid`),
  ADD UNIQUE KEY `uk_training_courses_tenant_slug` (`tenant_id`,`slug`),
  ADD UNIQUE KEY `uk_training_courses_enrollment_share_code` (`enrollment_share_code`),
  ADD KEY `idx_training_courses_visibility` (`visibility`),
  ADD KEY `idx_training_courses_category` (`category`),
  ADD KEY `idx_training_courses_tenant` (`tenant_id`);

--
-- Index pour la table `training_course_comments`
--
ALTER TABLE `training_course_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcc_course` (`course_id`),
  ADD KEY `idx_tcc_parent` (`parent_id`),
  ADD KEY `fk_tcc_tenant` (`tenant_id`),
  ADD KEY `fk_tcc_user` (`user_id`);

--
-- Index pour la table `training_course_favorites`
--
ALTER TABLE `training_course_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tcf_user_course` (`user_id`,`course_id`),
  ADD KEY `idx_tcf_course` (`course_id`),
  ADD KEY `fk_tcf_tenant` (`tenant_id`);

--
-- Index pour la table `training_course_likes`
--
ALTER TABLE `training_course_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tcl_user_course` (`user_id`,`course_id`),
  ADD KEY `idx_tcl_course` (`course_id`),
  ADD KEY `fk_tcl_tenant` (`tenant_id`);

--
-- Index pour la table `training_course_questions`
--
ALTER TABLE `training_course_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcq_course` (`course_id`),
  ADD KEY `fk_tcq_tenant` (`tenant_id`),
  ADD KEY `fk_tcq_user` (`user_id`);

--
-- Index pour la table `training_course_reviews`
--
ALTER TABLE `training_course_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tcr_user_course_kind` (`course_id`,`user_id`,`kind`),
  ADD KEY `idx_tcr_course` (`course_id`),
  ADD KEY `fk_tcr_tenant` (`tenant_id`),
  ADD KEY `fk_tcr_user` (`user_id`);

--
-- Index pour la table `training_course_sessions`
--
ALTER TABLE `training_course_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tcs_course` (`course_id`),
  ADD KEY `idx_tcs_tenant` (`tenant_id`);

--
-- Index pour la table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_enrollment` (`course_id`,`user_id`),
  ADD KEY `idx_training_enrollments_user` (`user_id`),
  ADD KEY `idx_training_enrollments_status` (`status`),
  ADD KEY `idx_training_enrollments_tenant` (`tenant_id`);

--
-- Index pour la table `training_lessons`
--
ALTER TABLE `training_lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_lessons_module_position` (`module_id`,`position`);

--
-- Index pour la table `training_modules`
--
ALTER TABLE `training_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_modules_course_position` (`course_id`,`position`);

--
-- Index pour la table `training_progress`
--
ALTER TABLE `training_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_progress` (`enrollment_id`,`lesson_id`),
  ADD KEY `idx_training_progress_lesson` (`lesson_id`);

--
-- Index pour la table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_training_quizzes_module` (`module_id`);

--
-- Index pour la table `training_quiz_answers`
--
ALTER TABLE `training_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_training_quiz_answers_question` (`question_id`);

--
-- Index pour la table `training_quiz_attempts`
--
ALTER TABLE `training_quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_quiz_attempts_enrollment` (`enrollment_id`),
  ADD KEY `fk_training_quiz_attempts_quiz` (`quiz_id`);

--
-- Index pour la table `training_quiz_questions`
--
ALTER TABLE `training_quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_quiz_questions_quiz_position` (`quiz_id`,`position`);

--
-- Index pour la table `training_quiz_responses`
--
ALTER TABLE `training_quiz_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_quiz_responses_attempt` (`attempt_id`),
  ADD KEY `fk_training_quiz_responses_question` (`question_id`);

--
-- Index pour la table `training_resources`
--
ALTER TABLE `training_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_training_resources_lesson` (`lesson_id`),
  ADD KEY `idx_training_resources_document` (`document_id`);

--
-- Index pour la table `training_staff_ping_log`
--
ALTER TABLE `training_staff_ping_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tsp_cooldown` (`enrollment_id`,`module_id`,`ping_kind`,`created_at`),
  ADD KEY `tsp_tenant_fk` (`tenant_id`);

--
-- Index pour la table `training_trainer_roles`
--
ALTER TABLE `training_trainer_roles`
  ADD PRIMARY KEY (`tenant_id`,`role_id`),
  ADD KEY `idx_ttr_role` (`role_id`),
  ADD KEY `fk_ttr_user` (`created_by_user_id`);

--
-- Index pour la table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `usage_analytics_events`
--
ALTER TABLE `usage_analytics_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uae_tenant_cat_time` (`tenant_id`,`category`,`created_at`),
  ADD KEY `idx_uae_tenant_subject_time` (`tenant_id`,`subject_type`,`subject_id`,`created_at`),
  ADD KEY `idx_uae_name_time` (`name`,`created_at`),
  ADD KEY `fk_uae_actor` (`actor_user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_email` (`tenant_id`,`email`),
  ADD UNIQUE KEY `users_tenant_profile_slug` (`tenant_id`,`profile_slug`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `idx_users_tenant_status_login` (`tenant_id`,`status`,`last_login_at`);

--
-- Index pour la table `user_alert_dismissals`
--
ALTER TABLE `user_alert_dismissals`
  ADD PRIMARY KEY (`user_id`,`scope`,`alert_id`);

--
-- Index pour la table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_badge` (`user_id`,`badge_id`),
  ADD KEY `idx_ub_tenant` (`tenant_id`),
  ADD KEY `ub_badge_fk` (`badge_id`);

--
-- Index pour la table `user_certifications`
--
ALTER TABLE `user_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ucert_user` (`user_id`,`certification_id`),
  ADD KEY `idx_ucert_tenant` (`tenant_id`),
  ADD KEY `ucert_cert_fk` (`certification_id`);

--
-- Index pour la table `user_forum_stats`
--
ALTER TABLE `user_forum_stats`
  ADD PRIMARY KEY (`tenant_id`,`user_id`),
  ADD KEY `user_forum_stats_user_fk` (`user_id`);

--
-- Index pour la table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_device` (`user_id`,`fingerprint_hash`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_unp_user_channel_event` (`user_id`,`channel`,`event_key`),
  ADD KEY `idx_unp_tenant` (`tenant_id`);

--
-- Index pour la table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_upo` (`tenant_id`,`user_id`,`permission_id`,`co_unit_scope`),
  ADD KEY `idx_upo_user` (`user_id`),
  ADD KEY `upo_perm_fk` (`permission_id`),
  ADD KEY `upo_unit_fk` (`org_unit_id`);

--
-- Index pour la table `user_positions`
--
ALTER TABLE `user_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_up_user` (`user_id`),
  ADD KEY `idx_up_position` (`position_id`),
  ADD KEY `idx_up_tenant` (`tenant_id`);

--
-- Index pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `user_profile_display_settings`
--
ALTER TABLE `user_profile_display_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `user_progress`
--
ALTER TABLE `user_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_progress` (`user_id`,`module_id`),
  ADD KEY `idx_user_progress_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_user_progress_expiry` (`expires_at`),
  ADD KEY `user_progress_module_fk` (`module_id`),
  ADD KEY `user_progress_validator_fk` (`validated_by`);

--
-- Index pour la table `user_progress_event_logs`
--
ALTER TABLE `user_progress_event_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_progress_event_tenant` (`tenant_id`,`created_at`),
  ADD KEY `idx_progress_event_user` (`user_id`,`created_at`),
  ADD KEY `idx_progress_event_module` (`module_id`,`event_type`),
  ADD KEY `idx_progress_event_source` (`source`,`source_user_id`),
  ADD KEY `progress_event_progress_fk` (`user_progress_id`),
  ADD KEY `progress_event_source_user_fk` (`source_user_id`);

--
-- Index pour la table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `user_roles_role_id` (`role_id`);

--
-- Index pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Index pour la table `user_ui_preferences`
--
ALTER TABLE `user_ui_preferences`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_uui_tenant` (`tenant_id`);

--
-- Index pour la table `user_units`
--
ALTER TABLE `user_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_unit_id` (`user_id`,`unit_id`),
  ADD KEY `user_units_unit_id_fk` (`unit_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `app_maintenance`
--
ALTER TABLE `app_maintenance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `app_maintenance_audit`
--
ALTER TABLE `app_maintenance_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `asset_logistics_status`
--
ALTER TABLE `asset_logistics_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `asset_logistics_status_history`
--
ALTER TABLE `asset_logistics_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `async_jobs`
--
ALTER TABLE `async_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_air_assets`
--
ALTER TABLE `atak_air_assets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_chat_messages`
--
ALTER TABLE `atak_chat_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_designator_targets`
--
ALTER TABLE `atak_designator_targets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_intel`
--
ALTER TABLE `atak_intel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_intel_photos`
--
ALTER TABLE `atak_intel_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_laser_codes`
--
ALTER TABLE `atak_laser_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_layers`
--
ALTER TABLE `atak_layers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_maps`
--
ALTER TABLE `atak_maps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `atak_map_shapes`
--
ALTER TABLE `atak_map_shapes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_markers`
--
ALTER TABLE `atak_markers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_nine_line`
--
ALTER TABLE `atak_nine_line`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_pings`
--
ALTER TABLE `atak_pings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_sigint_reports`
--
ALTER TABLE `atak_sigint_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `atak_units`
--
ALTER TABLE `atak_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT pour la table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blocked_indicators`
--
ALTER TABLE `blocked_indicators`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `certification_modules`
--
ALTER TABLE `certification_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clearance_levels`
--
ALTER TABLE `clearance_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `community_events`
--
ALTER TABLE `community_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `community_event_rsvps`
--
ALTER TABLE `community_event_rsvps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `community_invitations`
--
ALTER TABLE `community_invitations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `competencies`
--
ALTER TABLE `competencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competency_domains`
--
ALTER TABLE `competency_domains`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competency_frameworks`
--
ALTER TABLE `competency_frameworks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `competency_levels`
--
ALTER TABLE `competency_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cooperation_announcement_templates`
--
ALTER TABLE `cooperation_announcement_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `cooperation_catalog_entries`
--
ALTER TABLE `cooperation_catalog_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `cooperation_forum_announcement_log`
--
ALTER TABLE `cooperation_forum_announcement_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cooperation_mission_templates`
--
ALTER TABLE `cooperation_mission_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cooperation_notification_outbox`
--
ALTER TABLE `cooperation_notification_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `courrier_documents`
--
ALTER TABLE `courrier_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `courrier_document_notifications`
--
ALTER TABLE `courrier_document_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `courrier_document_versions`
--
ALTER TABLE `courrier_document_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `courrier_snippets`
--
ALTER TABLE `courrier_snippets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `danger_zones`
--
ALTER TABLE `danger_zones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `document_audit_log`
--
ALTER TABLE `document_audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `document_links`
--
ALTER TABLE `document_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_permissions`
--
ALTER TABLE `document_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_presets`
--
ALTER TABLE `document_presets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `document_relations`
--
ALTER TABLE `document_relations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `document_template_versions`
--
ALTER TABLE `document_template_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_variables_catalog`
--
ALTER TABLE `document_variables_catalog`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `document_versions`
--
ALTER TABLE `document_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `document_workflows`
--
ALTER TABLE `document_workflows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `email_deliveries`
--
ALTER TABLE `email_deliveries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT pour la table `email_tokens`
--
ALTER TABLE `email_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `enlistments`
--
ALTER TABLE `enlistments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `enlistment_canned_messages`
--
ALTER TABLE `enlistment_canned_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `equipment_classes`
--
ALTER TABLE `equipment_classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `fire_tables`
--
ALTER TABLE `fire_tables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `fire_units`
--
ALTER TABLE `fire_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_attachments`
--
ALTER TABLE `forum_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_banned_words`
--
ALTER TABLE `forum_banned_words`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `forum_blacklisted_domains`
--
ALTER TABLE `forum_blacklisted_domains`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_categories`
--
ALTER TABLE `forum_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `forum_moderation_logs`
--
ALTER TABLE `forum_moderation_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_moderation_rules`
--
ALTER TABLE `forum_moderation_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_notifications`
--
ALTER TABLE `forum_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `forum_post_reactions`
--
ALTER TABLE `forum_post_reactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_reports`
--
ALTER TABLE `forum_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `forum_report_events`
--
ALTER TABLE `forum_report_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_tags`
--
ALTER TABLE `forum_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `grades_legacy`
--
ALTER TABLE `grades_legacy`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `grade_categories`
--
ALTER TABLE `grade_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `grade_systems`
--
ALTER TABLE `grade_systems`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `iff_asset_status`
--
ALTER TABLE `iff_asset_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `iff_challenges`
--
ALTER TABLE `iff_challenges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intel_reports`
--
ALTER TABLE `intel_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `intel_reports_events`
--
ALTER TABLE `intel_reports_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_cooperation_consents`
--
ALTER TABLE `interteam_cooperation_consents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_cooperation_otp_attempts`
--
ALTER TABLE `interteam_cooperation_otp_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_missions`
--
ALTER TABLE `interteam_missions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `interteam_mission_events`
--
ALTER TABLE `interteam_mission_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `interteam_mission_forum_grants`
--
ALTER TABLE `interteam_mission_forum_grants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_mission_meetings`
--
ALTER TABLE `interteam_mission_meetings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_mission_members`
--
ALTER TABLE `interteam_mission_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interteam_mission_participants`
--
ALTER TABLE `interteam_mission_participants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `interteam_mission_rex`
--
ALTER TABLE `interteam_mission_rex`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `knowledge_units`
--
ALTER TABLE `knowledge_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `legacy_training_certificates`
--
ALTER TABLE `legacy_training_certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `legacy_training_modules`
--
ALTER TABLE `legacy_training_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `legacy_training_progress`
--
ALTER TABLE `legacy_training_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `logs_positions`
--
ALTER TABLE `logs_positions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moderation_actions`
--
ALTER TABLE `moderation_actions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moderation_artifacts`
--
ALTER TABLE `moderation_artifacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `moderation_evidence`
--
ALTER TABLE `moderation_evidence`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `modpacks`
--
ALTER TABLE `modpacks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `modpack_images`
--
ALTER TABLE `modpack_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `module_competencies`
--
ALTER TABLE `module_competencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `module_dependencies`
--
ALTER TABLE `module_dependencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `module_sequences`
--
ALTER TABLE `module_sequences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ops_board_assets`
--
ALTER TABLE `ops_board_assets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ops_board_assignments`
--
ALTER TABLE `ops_board_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ops_board_audience`
--
ALTER TABLE `ops_board_audience`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ops_board_history`
--
ALTER TABLE `ops_board_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ops_board_items`
--
ALTER TABLE `ops_board_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `pending_community_creates`
--
ALTER TABLE `pending_community_creates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT pour la table `personnel_admin_data`
--
ALTER TABLE `personnel_admin_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_admin_panels`
--
ALTER TABLE `personnel_admin_panels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `personnel_assignments`
--
ALTER TABLE `personnel_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `personnel_job_roles`
--
ALTER TABLE `personnel_job_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT pour la table `personnel_job_role_categories`
--
ALTER TABLE `personnel_job_role_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT pour la table `personnel_profile_job_roles`
--
ALTER TABLE `personnel_profile_job_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT pour la table `personnel_qualifications`
--
ALTER TABLE `personnel_qualifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_service_history`
--
ALTER TABLE `personnel_service_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `platform_alerts`
--
ALTER TABLE `platform_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `platform_usage_events`
--
ALTER TABLE `platform_usage_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=343;

--
-- AUTO_INCREMENT pour la table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recon_images`
--
ALTER TABLE `recon_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recruitment_openings`
--
ALTER TABLE `recruitment_openings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `recurrence_rules`
--
ALTER TABLE `recurrence_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `referral_attributions`
--
ALTER TABLE `referral_attributions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT pour la table `role_assignments_log`
--
ALTER TABLE `role_assignments_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `role_definitions`
--
ALTER TABLE `role_definitions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3571;

--
-- AUTO_INCREMENT pour la table `role_definition_relations`
--
ALTER TABLE `role_definition_relations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=919;

--
-- AUTO_INCREMENT pour la table `role_relations`
--
ALTER TABLE `role_relations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `role_requirements`
--
ALTER TABLE `role_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `role_sets`
--
ALTER TABLE `role_sets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `site_role_assignments`
--
ALTER TABLE `site_role_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT pour la table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `tenant_alerts`
--
ALTER TABLE `tenant_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_api_keys`
--
ALTER TABLE `tenant_api_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_api_key_daily_usage`
--
ALTER TABLE `tenant_api_key_daily_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_community_feed`
--
ALTER TABLE `tenant_community_feed`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `tenant_dashboard_pins`
--
ALTER TABLE `tenant_dashboard_pins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `tenant_grade_overrides`
--
ALTER TABLE `tenant_grade_overrides`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_messages`
--
ALTER TABLE `tenant_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_message_threads`
--
ALTER TABLE `tenant_message_threads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_modules`
--
ALTER TABLE `tenant_modules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_module_entitlements`
--
ALTER TABLE `tenant_module_entitlements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_quotas`
--
ALTER TABLE `tenant_quotas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_training_logs`
--
ALTER TABLE `tenant_training_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tenant_user_roles`
--
ALTER TABLE `tenant_user_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT pour la table `trainer_validation_logs`
--
ALTER TABLE `trainer_validation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_audit_log`
--
ALTER TABLE `training_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `training_certificates`
--
ALTER TABLE `training_certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `training_certificate_templates`
--
ALTER TABLE `training_certificate_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `training_competency_matrices`
--
ALTER TABLE `training_competency_matrices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_courses`
--
ALTER TABLE `training_courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `training_course_comments`
--
ALTER TABLE `training_course_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `training_course_favorites`
--
ALTER TABLE `training_course_favorites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_course_likes`
--
ALTER TABLE `training_course_likes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_course_questions`
--
ALTER TABLE `training_course_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_course_reviews`
--
ALTER TABLE `training_course_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_course_sessions`
--
ALTER TABLE `training_course_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `training_lessons`
--
ALTER TABLE `training_lessons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT pour la table `training_modules`
--
ALTER TABLE `training_modules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `training_progress`
--
ALTER TABLE `training_progress`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `training_quiz_answers`
--
ALTER TABLE `training_quiz_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT pour la table `training_quiz_attempts`
--
ALTER TABLE `training_quiz_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `training_quiz_questions`
--
ALTER TABLE `training_quiz_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT pour la table `training_quiz_responses`
--
ALTER TABLE `training_quiz_responses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `training_resources`
--
ALTER TABLE `training_resources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `training_staff_ping_log`
--
ALTER TABLE `training_staff_ping_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `usage_analytics_events`
--
ALTER TABLE `usage_analytics_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_certifications`
--
ALTER TABLE `user_certifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_positions`
--
ALTER TABLE `user_positions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_progress`
--
ALTER TABLE `user_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_progress_event_logs`
--
ALTER TABLE `user_progress_event_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `user_units`
--
ALTER TABLE `user_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `atak_air_assets`
--
ALTER TABLE `atak_air_assets`
  ADD CONSTRAINT `atak_air_assets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_chat_messages`
--
ALTER TABLE `atak_chat_messages`
  ADD CONSTRAINT `atak_chat_messages_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_designator_targets`
--
ALTER TABLE `atak_designator_targets`
  ADD CONSTRAINT `atak_designator_targets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_intel_photos`
--
ALTER TABLE `atak_intel_photos`
  ADD CONSTRAINT `atak_intel_photos_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_laser_codes`
--
ALTER TABLE `atak_laser_codes`
  ADD CONSTRAINT `atak_laser_codes_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_last_activity`
--
ALTER TABLE `atak_last_activity`
  ADD CONSTRAINT `atak_last_activity_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_layers`
--
ALTER TABLE `atak_layers`
  ADD CONSTRAINT `atak_layers_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_map_shapes`
--
ALTER TABLE `atak_map_shapes`
  ADD CONSTRAINT `atak_map_shapes_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_markers`
--
ALTER TABLE `atak_markers`
  ADD CONSTRAINT `atak_markers_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_nine_line`
--
ALTER TABLE `atak_nine_line`
  ADD CONSTRAINT `atak_nine_line_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_pings`
--
ALTER TABLE `atak_pings`
  ADD CONSTRAINT `atak_pings_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_sigint_reports`
--
ALTER TABLE `atak_sigint_reports`
  ADD CONSTRAINT `atak_sigint_reports_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `atak_units`
--
ALTER TABLE `atak_units`
  ADD CONSTRAINT `atak_units_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `badges`
--
ALTER TABLE `badges`
  ADD CONSTRAINT `badges_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `cert_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `certification_modules`
--
ALTER TABLE `certification_modules`
  ADD CONSTRAINT `certification_modules_cert_fk` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certification_modules_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `clearance_levels`
--
ALTER TABLE `clearance_levels`
  ADD CONSTRAINT `clr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `community_events`
--
ALTER TABLE `community_events`
  ADD CONSTRAINT `fk_ce_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `community_event_rsvps`
--
ALTER TABLE `community_event_rsvps`
  ADD CONSTRAINT `fk_rsvp_event` FOREIGN KEY (`event_id`) REFERENCES `community_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rsvp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `community_invitations`
--
ALTER TABLE `community_invitations`
  ADD CONSTRAINT `fk_ci_accepted` FOREIGN KEY (`accepted_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ci_inviter` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ci_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `competencies`
--
ALTER TABLE `competencies`
  ADD CONSTRAINT `competency_domain_fk` FOREIGN KEY (`domain_id`) REFERENCES `competency_domains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competency_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competency_level_fk` FOREIGN KEY (`level_id`) REFERENCES `competency_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competency_parent_fk` FOREIGN KEY (`parent_competency_id`) REFERENCES `competencies` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `competency_domains`
--
ALTER TABLE `competency_domains`
  ADD CONSTRAINT `domain_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `domain_level_fk` FOREIGN KEY (`level_id`) REFERENCES `competency_levels` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `competency_frameworks`
--
ALTER TABLE `competency_frameworks`
  ADD CONSTRAINT `framework_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `competency_levels`
--
ALTER TABLE `competency_levels`
  ADD CONSTRAINT `level_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `courrier_documents`
--
ALTER TABLE `courrier_documents`
  ADD CONSTRAINT `courrier_documents_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courrier_documents_preset_fk` FOREIGN KEY (`preset_id`) REFERENCES `document_presets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courrier_documents_signed_by_fk` FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courrier_documents_template_fk` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courrier_documents_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courrier_documents_validated_by_fk` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `courrier_document_notifications`
--
ALTER TABLE `courrier_document_notifications`
  ADD CONSTRAINT `cdn_creator_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cdn_doc_fk` FOREIGN KEY (`document_id`) REFERENCES `courrier_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cdn_recipient_fk` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cdn_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `courrier_document_versions`
--
ALTER TABLE `courrier_document_versions`
  ADD CONSTRAINT `courrier_document_versions_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courrier_document_versions_document_fk` FOREIGN KEY (`document_id`) REFERENCES `courrier_documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `courrier_snippets`
--
ALTER TABLE `courrier_snippets`
  ADD CONSTRAINT `courrier_snippets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_author_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `documents_category_fk` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `documents_current_file_id_fk` FOREIGN KEY (`current_file_id`) REFERENCES `document_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `documents_owner_user_id_fk` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `documents_parent_document_id_fk` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `documents_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `document_audit_log`
--
ALTER TABLE `document_audit_log`
  ADD CONSTRAINT `fk_document_audit_log_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_categories`
--
ALTER TABLE `document_categories`
  ADD CONSTRAINT `document_categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  ADD CONSTRAINT `fk_document_collaborators_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_document_collaborators_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_links`
--
ALTER TABLE `document_links`
  ADD CONSTRAINT `document_links_document_id_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `document_links_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `document_permissions`
--
ALTER TABLE `document_permissions`
  ADD CONSTRAINT `fk_document_permissions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_presets`
--
ALTER TABLE `document_presets`
  ADD CONSTRAINT `document_presets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_relations`
--
ALTER TABLE `document_relations`
  ADD CONSTRAINT `fk_document_relations_child` FOREIGN KEY (`child_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_document_relations_parent` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_templates`
--
ALTER TABLE `document_templates`
  ADD CONSTRAINT `document_templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_templates_preset_fk` FOREIGN KEY (`preset_id`) REFERENCES `document_presets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_templates_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_templates_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `document_template_versions`
--
ALTER TABLE `document_template_versions`
  ADD CONSTRAINT `document_template_versions_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_template_versions_template_fk` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_variables_catalog`
--
ALTER TABLE `document_variables_catalog`
  ADD CONSTRAINT `document_variables_catalog_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `document_versions`
--
ALTER TABLE `document_versions`
  ADD CONSTRAINT `document_versions_document_id_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `document_workflows`
--
ALTER TABLE `document_workflows`
  ADD CONSTRAINT `document_workflows_acted_by_fk` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_workflows_document_fk` FOREIGN KEY (`document_id`) REFERENCES `courrier_documents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `email_tokens`
--
ALTER TABLE `email_tokens`
  ADD CONSTRAINT `email_tokens_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `enlistments`
--
ALTER TABLE `enlistments`
  ADD CONSTRAINT `enlistments_recruitment_preset_fk` FOREIGN KEY (`recruitment_preset_id`) REFERENCES `recruitment_presets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `enlistments_ro_fk` FOREIGN KEY (`recruitment_opening_id`) REFERENCES `recruitment_openings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `enlistments_submitter_user_fk` FOREIGN KEY (`submitter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `enlistments_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `enlistment_canned_messages`
--
ALTER TABLE `enlistment_canned_messages`
  ADD CONSTRAINT `enlistment_canned_messages_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `equipment_classes`
--
ALTER TABLE `equipment_classes`
  ADD CONSTRAINT `equipment_classes_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluation_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_validator_role_fk` FOREIGN KEY (`validator_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `forum_attachments`
--
ALTER TABLE `forum_attachments`
  ADD CONSTRAINT `forum_attachments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_attachments_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_banned_words`
--
ALTER TABLE `forum_banned_words`
  ADD CONSTRAINT `forum_banned_words_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_blacklisted_domains`
--
ALTER TABLE `forum_blacklisted_domains`
  ADD CONSTRAINT `forum_blacklisted_domains_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_categories`
--
ALTER TABLE `forum_categories`
  ADD CONSTRAINT `forum_categories_min_role_id_fk` FOREIGN KEY (`min_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_categories_owner_tenant_fk` FOREIGN KEY (`owner_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_categories_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_category_subscriptions`
--
ALTER TABLE `forum_category_subscriptions`
  ADD CONSTRAINT `forum_category_subscriptions_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_category_subscriptions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_moderation_logs`
--
ALTER TABLE `forum_moderation_logs`
  ADD CONSTRAINT `forum_moderation_logs_post_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `forum_moderation_logs_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_moderation_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `forum_moderation_rules`
--
ALTER TABLE `forum_moderation_rules`
  ADD CONSTRAINT `forum_moderation_rules_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_notifications`
--
ALTER TABLE `forum_notifications`
  ADD CONSTRAINT `forum_notifications_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_coop_src_fk` FOREIGN KEY (`coop_source_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `forum_posts_parent_fk` FOREIGN KEY (`parent_post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_post_reactions`
--
ALTER TABLE `forum_post_reactions`
  ADD CONSTRAINT `forum_post_reactions_post_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_post_reactions_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_post_reactions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  ADD CONSTRAINT `forum_post_votes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_post_votes_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_post_votes_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_read`
--
ALTER TABLE `forum_read`
  ADD CONSTRAINT `forum_read_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_read_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_reports`
--
ALTER TABLE `forum_reports`
  ADD CONSTRAINT `forum_reports_handled_by_fk` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_reports_post_id_fk` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_reports_reporter_id_fk` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_reports_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_reports_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_report_events`
--
ALTER TABLE `forum_report_events`
  ADD CONSTRAINT `forum_report_events_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `forum_report_events_report_fk` FOREIGN KEY (`report_id`) REFERENCES `forum_reports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_tags`
--
ALTER TABLE `forum_tags`
  ADD CONSTRAINT `forum_tags_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD CONSTRAINT `forum_topics_best_answer_fk` FOREIGN KEY (`best_answer_post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_topics_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_topics_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_topics_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_topic_subscriptions`
--
ALTER TABLE `forum_topic_subscriptions`
  ADD CONSTRAINT `forum_topic_subscriptions_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_topic_subscriptions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_topic_tags`
--
ALTER TABLE `forum_topic_tags`
  ADD CONSTRAINT `forum_topic_tags_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `forum_tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_topic_tags_topic_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `fk_grades_ref_category` FOREIGN KEY (`grade_category_id`) REFERENCES `grade_categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grades_ref_system` FOREIGN KEY (`grade_system_id`) REFERENCES `grade_systems` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `grades_legacy`
--
ALTER TABLE `grades_legacy`
  ADD CONSTRAINT `grades_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `iff_asset_status`
--
ALTER TABLE `iff_asset_status`
  ADD CONSTRAINT `iff_asset_status_challenge_fk` FOREIGN KEY (`current_challenge_id`) REFERENCES `iff_challenges` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `intel_reports_events`
--
ALTER TABLE `intel_reports_events`
  ADD CONSTRAINT `intel_reports_events_report_fk` FOREIGN KEY (`intel_report_id`) REFERENCES `intel_reports` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_cooperation_consents`
--
ALTER TABLE `interteam_cooperation_consents`
  ADD CONSTRAINT `interteam_consent_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interteam_consent_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_cooperation_otp_attempts`
--
ALTER TABLE `interteam_cooperation_otp_attempts`
  ADD CONSTRAINT `fk_otp_mission` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_missions`
--
ALTER TABLE `interteam_missions`
  ADD CONSTRAINT `interteam_missions_tenant_fk` FOREIGN KEY (`created_by_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_events`
--
ALTER TABLE `interteam_mission_events`
  ADD CONSTRAINT `interteam_ev_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_forum_grants`
--
ALTER TABLE `interteam_mission_forum_grants`
  ADD CONSTRAINT `interteam_grant_consumer_tenant_fk` FOREIGN KEY (`consumer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interteam_grant_home_tenant_fk` FOREIGN KEY (`home_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interteam_grant_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_meetings`
--
ALTER TABLE `interteam_mission_meetings`
  ADD CONSTRAINT `interteam_mm_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_members`
--
ALTER TABLE `interteam_mission_members`
  ADD CONSTRAINT `fk_imm_mission` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_participants`
--
ALTER TABLE `interteam_mission_participants`
  ADD CONSTRAINT `interteam_mp_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interteam_mp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `interteam_mission_rex`
--
ALTER TABLE `interteam_mission_rex`
  ADD CONSTRAINT `interteam_rex_mission_fk` FOREIGN KEY (`mission_id`) REFERENCES `interteam_missions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `knowledge_units`
--
ALTER TABLE `knowledge_units`
  ADD CONSTRAINT `knowledge_competency_fk` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `legacy_training_certificates`
--
ALTER TABLE `legacy_training_certificates`
  ADD CONSTRAINT `training_certificates_module_fk` FOREIGN KEY (`training_module_id`) REFERENCES `legacy_training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `training_certificates_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `training_certificates_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `legacy_training_modules`
--
ALTER TABLE `legacy_training_modules`
  ADD CONSTRAINT `training_modules_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `legacy_training_progress`
--
ALTER TABLE `legacy_training_progress`
  ADD CONSTRAINT `training_progress_module_fk` FOREIGN KEY (`training_module_id`) REFERENCES `legacy_training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `training_progress_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `training_progress_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `moderation_actions`
--
ALTER TABLE `moderation_actions`
  ADD CONSTRAINT `fk_ma_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ma_case` FOREIGN KEY (`case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ma_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ma_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `moderation_artifacts`
--
ALTER TABLE `moderation_artifacts`
  ADD CONSTRAINT `moderation_artifacts_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `moderation_artifacts_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  ADD CONSTRAINT `fk_mc_opener` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_subject` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `moderation_decisions`
--
ALTER TABLE `moderation_decisions`
  ADD CONSTRAINT `moderation_decisions_actor_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `moderation_decisions_artifact_fk` FOREIGN KEY (`artifact_id`) REFERENCES `moderation_artifacts` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `moderation_evidence`
--
ALTER TABLE `moderation_evidence`
  ADD CONSTRAINT `fk_me_author` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_me_case` FOREIGN KEY (`case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_me_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `modpacks`
--
ALTER TABLE `modpacks`
  ADD CONSTRAINT `modpacks_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `modpacks_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `modpack_images`
--
ALTER TABLE `modpack_images`
  ADD CONSTRAINT `modpack_images_modpack_id_fk` FOREIGN KEY (`modpack_id`) REFERENCES `modpacks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `module_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `module_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `module_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_competencies`
--
ALTER TABLE `module_competencies`
  ADD CONSTRAINT `module_competencies_competency_fk` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_competencies_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_dependencies`
--
ALTER TABLE `module_dependencies`
  ADD CONSTRAINT `module_dep_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_dep_requires_fk` FOREIGN KEY (`requires_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_knowledge`
--
ALTER TABLE `module_knowledge`
  ADD CONSTRAINT `module_knowledge_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_knowledge_unit_fk` FOREIGN KEY (`knowledge_id`) REFERENCES `knowledge_units` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `module_sequences`
--
ALTER TABLE `module_sequences`
  ADD CONSTRAINT `module_sequence_framework_fk` FOREIGN KEY (`framework_id`) REFERENCES `competency_frameworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_sequence_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ops_board_assets`
--
ALTER TABLE `ops_board_assets`
  ADD CONSTRAINT `fk_ops_board_assets_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `ops_board_assignments`
--
ALTER TABLE `ops_board_assignments`
  ADD CONSTRAINT `fk_ops_board_assignments_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ops_board_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `ops_board_audience`
--
ALTER TABLE `ops_board_audience`
  ADD CONSTRAINT `fk_ops_board_audience_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `ops_board_history`
--
ALTER TABLE `ops_board_history`
  ADD CONSTRAINT `fk_ops_board_history_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ops_board_history_item` FOREIGN KEY (`item_id`) REFERENCES `ops_board_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `ops_board_items`
--
ALTER TABLE `ops_board_items`
  ADD CONSTRAINT `fk_ops_board_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ops_board_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ops_board_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_admin_data`
--
ALTER TABLE `personnel_admin_data`
  ADD CONSTRAINT `personnel_admin_data_panel_id_fk` FOREIGN KEY (`panel_id`) REFERENCES `personnel_admin_panels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_admin_data_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `personnel_admin_panels`
--
ALTER TABLE `personnel_admin_panels`
  ADD CONSTRAINT `personnel_admin_panels_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_assignments`
--
ALTER TABLE `personnel_assignments`
  ADD CONSTRAINT `personnel_assignments_unit_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_assignments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_extras`
--
ALTER TABLE `personnel_extras`
  ADD CONSTRAINT `pe_clearance_fk` FOREIGN KEY (`clearance_level_id`) REFERENCES `clearance_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_extras_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `personnel_job_roles`
--
ALTER TABLE `personnel_job_roles`
  ADD CONSTRAINT `pjr_category_fk` FOREIGN KEY (`category_id`) REFERENCES `personnel_job_role_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pjr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_job_role_categories`
--
ALTER TABLE `personnel_job_role_categories`
  ADD CONSTRAINT `pjrc_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `personnel_job_role_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pjrc_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_job_role_permissions`
--
ALTER TABLE `personnel_job_role_permissions`
  ADD CONSTRAINT `pjrp_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pjrp_role_fk` FOREIGN KEY (`personnel_job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  ADD CONSTRAINT `personnel_media_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  ADD CONSTRAINT `personnel_profiles_primary_unit_fk` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pp_personnel_job_role_fk` FOREIGN KEY (`personnel_job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_profile_job_roles`
--
ALTER TABLE `personnel_profile_job_roles`
  ADD CONSTRAINT `ppjr_jobrole_fk` FOREIGN KEY (`personnel_job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ppjr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ppjr_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_qualifications`
--
ALTER TABLE `personnel_qualifications`
  ADD CONSTRAINT `personnel_qualifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_service_history`
--
ALTER TABLE `personnel_service_history`
  ADD CONSTRAINT `personnel_service_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recon_images`
--
ALTER TABLE `recon_images`
  ADD CONSTRAINT `recon_images_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recruitment_openings`
--
ALTER TABLE `recruitment_openings`
  ADD CONSTRAINT `ro_creator_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `ro_pjr_fk` FOREIGN KEY (`personnel_job_role_id`) REFERENCES `personnel_job_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `ro_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ro_unit_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `recruitment_opening_counters`
--
ALTER TABLE `recruitment_opening_counters`
  ADD CONSTRAINT `roc_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  ADD CONSTRAINT `recruitment_presets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `recurrence_rules`
--
ALTER TABLE `recurrence_rules`
  ADD CONSTRAINT `recurrence_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `referral_attributions`
--
ALTER TABLE `referral_attributions`
  ADD CONSTRAINT `fk_referral_attr_referrer` FOREIGN KEY (`referrer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referral_attr_tenant` FOREIGN KEY (`referred_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD CONSTRAINT `fk_referral_codes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_definition_fk` FOREIGN KEY (`definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `roles_parent_fk` FOREIGN KEY (`parent_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `roles_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `role_definition_relations`
--
ALTER TABLE `role_definition_relations`
  ADD CONSTRAINT `rdr_from_fk` FOREIGN KEY (`from_definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rdr_to_fk` FOREIGN KEY (`to_definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_permission_id_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_relations`
--
ALTER TABLE `role_relations`
  ADD CONSTRAINT `rr_from_fk` FOREIGN KEY (`from_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rr_to_fk` FOREIGN KEY (`to_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_requirements`
--
ALTER TABLE `role_requirements`
  ADD CONSTRAINT `role_requirements_certification_fk` FOREIGN KEY (`required_certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_requirements_module_fk` FOREIGN KEY (`required_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_requirements_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_sets`
--
ALTER TABLE `role_sets`
  ADD CONSTRAINT `role_sets_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_set_roles`
--
ALTER TABLE `role_set_roles`
  ADD CONSTRAINT `rsr_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rsr_set_fk` FOREIGN KEY (`role_set_id`) REFERENCES `role_sets` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `site_role_assignments`
--
ALTER TABLE `site_role_assignments`
  ADD CONSTRAINT `site_role_assignments_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `site_settings`
--
ALTER TABLE `site_settings`
  ADD CONSTRAINT `site_settings_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `tenants_owner_user_id_fk` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_alerts`
--
ALTER TABLE `tenant_alerts`
  ADD CONSTRAINT `tenant_alerts_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_atak_config`
--
ALTER TABLE `tenant_atak_config`
  ADD CONSTRAINT `tenant_atak_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_branding`
--
ALTER TABLE `tenant_branding`
  ADD CONSTRAINT `tenant_branding_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_community_feed`
--
ALTER TABLE `tenant_community_feed`
  ADD CONSTRAINT `tcf_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_dashboard_pins`
--
ALTER TABLE `tenant_dashboard_pins`
  ADD CONSTRAINT `tdp_courrier_fk` FOREIGN KEY (`courrier_document_id`) REFERENCES `courrier_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tdp_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tdp_doc_cat_fk` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tdp_document_fk` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tdp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_grade_overrides`
--
ALTER TABLE `tenant_grade_overrides`
  ADD CONSTRAINT `tenant_grade_overrides_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_matricule_config`
--
ALTER TABLE `tenant_matricule_config`
  ADD CONSTRAINT `tenant_matricule_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_messages`
--
ALTER TABLE `tenant_messages`
  ADD CONSTRAINT `tm_sender_fk` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tm_thread_fk` FOREIGN KEY (`thread_id`) REFERENCES `tenant_message_threads` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_message_threads`
--
ALTER TABLE `tenant_message_threads`
  ADD CONSTRAINT `tmt_creator_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tmt_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_message_thread_users`
--
ALTER TABLE `tenant_message_thread_users`
  ADD CONSTRAINT `tmtu_thread_fk` FOREIGN KEY (`thread_id`) REFERENCES `tenant_message_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tmtu_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_modules`
--
ALTER TABLE `tenant_modules`
  ADD CONSTRAINT `tenant_modules_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenant_modules_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_module_entitlements`
--
ALTER TABLE `tenant_module_entitlements`
  ADD CONSTRAINT `tme_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_quotas`
--
ALTER TABLE `tenant_quotas`
  ADD CONSTRAINT `tq_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_security_policy`
--
ALTER TABLE `tenant_security_policy`
  ADD CONSTRAINT `tenant_security_policy_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_training_logs`
--
ALTER TABLE `tenant_training_logs`
  ADD CONSTRAINT `tenant_training_logs_actor_role_fk` FOREIGN KEY (`actor_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tenant_training_logs_actor_user_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tenant_training_logs_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_usage_counters`
--
ALTER TABLE `tenant_usage_counters`
  ADD CONSTRAINT `fk_tuc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tenant_user_roles`
--
ALTER TABLE `tenant_user_roles`
  ADD CONSTRAINT `tur_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tur_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tur_unit_fk` FOREIGN KEY (`org_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tur_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `trainer_validation_logs`
--
ALTER TABLE `trainer_validation_logs`
  ADD CONSTRAINT `trainer_validation_logs_evaluation_fk` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trainer_validation_logs_instructor_fk` FOREIGN KEY (`instructor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trainer_validation_logs_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trainer_validation_logs_progress_fk` FOREIGN KEY (`user_progress_id`) REFERENCES `user_progress` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trainer_validation_logs_target_fk` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trainer_validation_logs_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `training_certificates`
--
ALTER TABLE `training_certificates`
  ADD CONSTRAINT `fk_training_certificates_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_certificates_issued_by` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_certificates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_certificate_templates`
--
ALTER TABLE `training_certificate_templates`
  ADD CONSTRAINT `fk_training_cert_tpl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_competency_matrices`
--
ALTER TABLE `training_competency_matrices`
  ADD CONSTRAINT `fk_tcm_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcm_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_competency_matrix_assignments`
--
ALTER TABLE `training_competency_matrix_assignments`
  ADD CONSTRAINT `fk_tcma_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcma_matrix` FOREIGN KEY (`matrix_id`) REFERENCES `training_competency_matrices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcma_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_courses`
--
ALTER TABLE `training_courses`
  ADD CONSTRAINT `fk_training_courses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_comments`
--
ALTER TABLE `training_course_comments`
  ADD CONSTRAINT `fk_tcc_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_favorites`
--
ALTER TABLE `training_course_favorites`
  ADD CONSTRAINT `fk_tcf_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcf_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_likes`
--
ALTER TABLE `training_course_likes`
  ADD CONSTRAINT `fk_tcl_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_questions`
--
ALTER TABLE `training_course_questions`
  ADD CONSTRAINT `fk_tcq_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcq_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_reviews`
--
ALTER TABLE `training_course_reviews`
  ADD CONSTRAINT `fk_tcr_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_course_sessions`
--
ALTER TABLE `training_course_sessions`
  ADD CONSTRAINT `fk_tcs_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tcs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD CONSTRAINT `fk_training_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_enrollments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_enrollments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_lessons`
--
ALTER TABLE `training_lessons`
  ADD CONSTRAINT `fk_training_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_modules`
--
ALTER TABLE `training_modules`
  ADD CONSTRAINT `fk_training_modules_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_progress`
--
ALTER TABLE `training_progress`
  ADD CONSTRAINT `fk_training_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `training_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  ADD CONSTRAINT `fk_training_quizzes_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_quiz_answers`
--
ALTER TABLE `training_quiz_answers`
  ADD CONSTRAINT `fk_training_quiz_answers_question` FOREIGN KEY (`question_id`) REFERENCES `training_quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_quiz_attempts`
--
ALTER TABLE `training_quiz_attempts`
  ADD CONSTRAINT `fk_training_quiz_attempts_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_quiz_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `training_quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_quiz_questions`
--
ALTER TABLE `training_quiz_questions`
  ADD CONSTRAINT `fk_training_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `training_quizzes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_quiz_responses`
--
ALTER TABLE `training_quiz_responses`
  ADD CONSTRAINT `fk_training_quiz_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `training_quiz_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_quiz_responses_question` FOREIGN KEY (`question_id`) REFERENCES `training_quiz_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_resources`
--
ALTER TABLE `training_resources`
  ADD CONSTRAINT `fk_training_resources_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `training_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_staff_ping_log`
--
ALTER TABLE `training_staff_ping_log`
  ADD CONSTRAINT `tsp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `training_trainer_roles`
--
ALTER TABLE `training_trainer_roles`
  ADD CONSTRAINT `fk_ttr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ttr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ttr_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `usage_analytics_events`
--
ALTER TABLE `usage_analytics_events`
  ADD CONSTRAINT `fk_uae_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uae_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_grade_id_fk` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `users_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `users_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_alert_dismissals`
--
ALTER TABLE `user_alert_dismissals`
  ADD CONSTRAINT `user_alert_dismissals_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `ub_badge_fk` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ub_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ub_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_certifications`
--
ALTER TABLE `user_certifications`
  ADD CONSTRAINT `ucert_cert_fk` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ucert_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ucert_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_forum_stats`
--
ALTER TABLE `user_forum_stats`
  ADD CONSTRAINT `user_forum_stats_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_forum_stats_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  ADD CONSTRAINT `user_login_devices_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_login_devices_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD CONSTRAINT `unp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `unp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD CONSTRAINT `upo_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `upo_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `upo_unit_fk` FOREIGN KEY (`org_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `upo_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_positions`
--
ALTER TABLE `user_positions`
  ADD CONSTRAINT `up_position_fk` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `up_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_profile_display_settings`
--
ALTER TABLE `user_profile_display_settings`
  ADD CONSTRAINT `user_profile_display_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_progress`
--
ALTER TABLE `user_progress`
  ADD CONSTRAINT `user_progress_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_progress_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_progress_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_progress_validator_fk` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_progress_event_logs`
--
ALTER TABLE `user_progress_event_logs`
  ADD CONSTRAINT `progress_event_module_fk` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `progress_event_progress_fk` FOREIGN KEY (`user_progress_id`) REFERENCES `user_progress` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `progress_event_source_user_fk` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `progress_event_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `progress_event_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_roles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD CONSTRAINT `user_signatures_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_signatures_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_ui_preferences`
--
ALTER TABLE `user_ui_preferences`
  ADD CONSTRAINT `user_ui_preferences_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_ui_preferences_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_units`
--
ALTER TABLE `user_units`
  ADD CONSTRAINT `user_units_unit_id_fk` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_units_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
