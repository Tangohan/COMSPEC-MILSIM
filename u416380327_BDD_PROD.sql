-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 05 avr. 2026 à 16:24
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
(25, 7, 5, 'auth.login_success', 'auth', 5, NULL, NULL, '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-05 15:52:27');

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
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
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
(1, 7, 'wikzzcoc@gmail.com', '56ea13c46e80dfab3a38a5df5b68543f110d6b1f80116d1d431ef0897ea16b7c', 29, NULL, 5, 'pending', '2026-04-12 12:04:47', NULL, NULL, '2026-04-05 12:04:47', NULL);

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
(14, 7, 'COMMUNITY_INVITATION', 'wikzzcoc@gmail.com', 'Invitation — ATHENA', 'smtp', 'sent', NULL, NULL, '{\"purpose\":\"invitation\"}', '2026-04-05 12:04:47');

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
(1, 7, 5, 'device_deny', '7ff2009903eb7f62ab3dfb76afac0d7212b5e0ffe1d995011c0ee65a1a12303b', 'effbbc8d4dc54d3a', '2026-04-07 10:02:15', NULL, '{\"ip\":\"2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4\",\"ua\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/146.0.0.0 Safari\\/537.36 Edg\\/146.0.0.0\"}', '2026-04-05 10:02:15'),
(4, 1, 7, 'register_confirm', '965a7a53f5a9940514a3d16025f1422a65f7b7774ba78febb387fe665edfe334', 'f3ac003c65139733b354104b25060a50', '2026-04-05 11:36:41', '2026-04-05 11:21:58', NULL, '2026-04-05 11:21:42'),
(5, 1, 7, 'device_deny', '54f65a2ffd5fe6bdb6124800702dc248d54c879b829d2b8b712b0fd50b9480c8', 'f12d050aa70608cf', '2026-04-07 11:22:14', NULL, '{\"ip\":\"2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4\",\"ua\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/146.0.0.0 Safari\\/537.36 Edg\\/146.0.0.0\"}', '2026-04-05 11:22:14');

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

INSERT INTO `enlistments` (`id`, `tenant_id`, `first_name`, `last_name`, `email`, `callsign`, `country`, `experience`, `specialty`, `platform`, `availability`, `notes`, `age`, `timezone`, `weekly_availability`, `system_config`, `microphone_quality`, `past_milsim_experience`, `ace_acre_level`, `motivation_why_join`, `motivation_accountability`, `commitment_effort`, `availability_wed_sat`, `no_ai_confirmed`, `status`, `reviewed_by`, `reviewed_at`, `reviewer_comment`, `submitter_user_id`, `recruitment_preset_id`, `submitted_via`, `consent_sharing_at`, `shared_fields`, `recruitment_rp_json`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tanguy', 'TETARD', 'wikzzcoc@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 25, 'Paris', 'Jeudi et vendredi', 'I9', 'Oui', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Basique', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Oui', 'Variable', 1, 'submitted', NULL, NULL, NULL, NULL, NULL, 'guest', NULL, NULL, NULL, '2026-03-13 19:38:46', '2026-03-13 19:38:46'),
(2, 7, 'Melvin', 'MESNEL', 'tanguy.inc@gmail.com', NULL, NULL, NULL, NULL, NULL, 'Ok', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Ok', NULL, NULL, NULL, 1, 'reviewed', 5, '2026-04-05 12:04:09', 'Bienvenu(e)', NULL, NULL, 'guest', NULL, NULL, NULL, '2026-04-05 11:23:35', '2026-04-05 12:04:09');

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
(18, 7, 'organization', 7, NULL, 'ATHENA — Espace dédié', 'org-athena-sys', 'Section forum de votre organisation.', NULL, 'slate', 15, 0, NULL, '2026-04-05 09:10:02', '2026-04-05 09:10:02');

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
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `tenant_id`, `topic_id`, `parent_post_id`, `user_id`, `body`, `is_hidden`, `created_at`, `updated_at`) VALUES
(3, 7, 2, NULL, 5, 'Une structure dédiée est désormais en place afin d’assurer la gestion, la régulation et l’évolution du système. Cette équipe exerce une mission de pilotage, de contrôle et d’arbitrage sur l’ensemble des modules, des utilisateurs et des flux.\n\nChaque membre est investi d’une responsabilité claire : garantir la cohérence des données, la stabilité de la plateforme et le respect des règles internes. Les interventions sont tracées, les décisions sont assumées, les dérives sont corrigées.\n\nL’administration n’est pas un statut. C’est une fonction d’autorité technique et organisationnelle au service de l’ensemble.\n\n[ATHENA](https://athena.ttrd.fr/public/c/athena-sys)', 0, '2026-04-05 09:19:27', '2026-04-05 09:19:27'),
(4, 7, 3, NULL, 5, '**Cadre général du forum**\n\nLe forum constitue un espace commun, ouvert à l’ensemble des utilisateurs.\nIl est destiné aux échanges généraux, au partage d’informations et aux interactions transversales entre les différentes entités.\n\n**Espaces communautaires dédiés**\n\nChaque communauté dispose d’un espace propre, structuré et réservé à ses membres.\nCes sections permettent une organisation interne claire, des communications ciblées et une gestion autonome des contenus spécifiques à chaque groupe.\n\n**Autorité et modération**\n\nDans ces espaces dédiés, la modération relève en priorité de la chaîne de responsabilité interne à la communauté.\nLes encadrants y exercent une autorité directe, veillent au respect des règles propres à leur structure et assurent la régulation des échanges.\n\n**Supervision centrale**\n\nL’administration centrale conserve un droit permanent de supervision.\nElle peut intervenir en dernier ressort afin de garantir l’unité, la conformité et la cohérence globale du forum.', 0, '2026-04-05 09:25:31', '2026-04-05 09:26:14'),
(6, 7, 2, NULL, 5, 'https://hpanel.hostinger.com/websites/athena.ttrd.fr/databases/my-sql-databases', 0, '2026-04-05 16:13:11', '2026-04-05 16:13:11');

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
  `reported_url` varchar(2048) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
(2, 7, 18, 5, 'Ouverture de l\'équipe d\'administration', 'ouverture-de-léquipe-dadministration-953547', 0, 0, 0, 0, NULL, 0, 0, 40, '2026-04-05 09:19:27', '2026-04-05 16:14:17', NULL, 0),
(3, 7, 13, 5, 'Fonctionnement du \"Brief\"', 'fonctionnement-du-brief-431290', 0, 1, 0, 0, NULL, 0, 0, 11, '2026-04-05 09:25:31', '2026-04-05 12:00:03', NULL, 0);

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
(30, 2, 3, 'PVT', 'PVT', 'Private', 'E-1', 34, 0, 1, '2026-04-05 08:28:44', '2026-04-05 08:28:44', NULL);

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
(4, 'tetard.tanguy@gmail.com', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 1, '2026-04-05 15:52:27');

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
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `tenant_id`, `name`, `slug`, `module`, `action`, `scope`, `created_at`) VALUES
(1, 1, 'Voir le forum', 'forum.view', 'forum', 'view', 'intra', '2026-03-13 19:23:12'),
(2, 1, 'Créer un sujet', 'forum.create_topic', 'forum', 'create', 'intra', '2026-03-13 19:23:12'),
(3, 1, 'Répondre', 'forum.reply', 'forum', 'create', 'intra', '2026-03-13 19:23:12'),
(4, 1, 'Modifier ses messages', 'forum.edit_own', 'forum', 'update', 'intra', '2026-03-13 19:23:12'),
(5, 1, 'Supprimer ses messages', 'forum.delete_own', 'forum', 'delete', 'intra', '2026-03-13 19:23:12'),
(6, 1, 'Modérer le forum (périmètre étendu)', 'forum.moderate', 'forum', 'moderate', 'community', '2026-03-13 19:23:12'),
(7, 1, 'Gérer les catégories (identifiant historique)', 'forum.manage_categories', 'forum', 'manage', 'community', '2026-03-13 19:23:12'),
(8, 1, 'Accès administration (tenant)', 'admin.access', 'admin', 'manage', 'community', '2026-03-13 22:57:32'),
(9, 1, 'Voir les documents', 'documents.view', 'documents', 'view', 'community', '2026-03-14 00:01:46'),
(10, 1, 'Téléverser des documents', 'documents.upload', 'documents', 'create', 'community', '2026-03-14 00:01:46'),
(11, 1, 'Modifier les documents (héritage)', 'documents.update', 'documents', 'update', 'community', '2026-03-14 00:01:46'),
(12, 1, 'Archiver / désarchiver', 'documents.archive', 'documents', 'archive', 'community', '2026-03-14 00:01:46'),
(13, 1, 'Télécharger les documents sensibles', 'documents.download_sensitive', 'documents', 'view', 'community', '2026-03-14 00:01:46'),
(14, 1, 'Voir les formations', 'training.view', 'training', 'view', 'community', '2026-03-15 11:51:40'),
(15, 1, 'Gérer les formations (périmètre étendu)', 'training.manage', 'training', 'manage', 'community', '2026-03-15 11:51:40'),
(16, 1, 'Assigner les formations', 'training.assign', 'training', 'assign', 'community', '2026-03-15 11:51:40'),
(17, 1, 'Administration système', 'admin.system', 'admin', NULL, 'community', '2026-03-15 12:02:28'),
(18, 1, 'Administration organisationnelle', 'admin.organization', 'admin', 'manage', 'community', '2026-03-15 12:02:28'),
(19, 1, 'Voir le Bureau Courrier', 'courrier.view', 'courrier', 'view', 'community', '2026-03-15 12:43:17'),
(20, 1, 'Créer des documents courrier', 'courrier.create', 'courrier', 'create', 'community', '2026-03-15 12:43:17'),
(21, 1, 'Valider des documents courrier', 'courrier.validate', 'courrier', 'approve', 'community', '2026-03-15 12:43:17'),
(22, 1, 'Archiver des documents courrier', 'courrier.archive', 'courrier', 'archive', 'community', '2026-03-15 12:43:17'),
(23, NULL, 'Administration système (plateforme)', 'admin.system', 'admin', NULL, 'site', '2026-04-04 15:13:10'),
(24, NULL, 'Accès back-office plateforme', 'admin.access', 'admin', NULL, 'site', '2026-04-04 15:13:10'),
(25, NULL, 'Gérer les communautés (tenants)', 'site.tenants.manage', 'admin', NULL, 'site', '2026-04-04 15:13:10'),
(26, 1, 'Modérer la section forum organisation', 'forum.moderate_organization', 'forum', 'moderate', 'community', '2026-04-04 15:13:10'),
(45, 1, 'Envoyer des invitations', 'invitations.send', 'admin', 'create', 'community', '2026-04-05 08:48:49'),
(46, 1, 'Voir le back-office', 'admin.backoffice.view', 'admin', 'view', 'community', '2026-04-05 09:03:38'),
(47, 1, 'Voir les membres', 'admin.members.view', 'admin', 'view', 'community', '2026-04-05 09:03:38'),
(48, 1, 'Gérer les membres', 'admin.members.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(49, 1, 'Inviter des membres', 'admin.members.invite', 'admin', 'create', 'community', '2026-04-05 09:03:38'),
(50, 1, 'Suspendre / exclure un membre', 'admin.members.moderate', 'admin', 'moderate', 'community', '2026-04-05 09:03:38'),
(51, 1, 'Gérer les rôles', 'admin.roles.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(52, 1, 'Gérer les permissions', 'admin.permissions.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(53, 1, 'Voir les journaux d’audit', 'admin.audit.view', 'admin', 'view', 'community', '2026-04-05 09:03:38'),
(54, 1, 'Gérer les paramètres de la communauté', 'admin.settings.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(55, 1, 'Gérer l’identité visuelle / branding', 'admin.branding.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(56, 1, 'Gérer les intégrations / API / webhooks', 'admin.integrations.manage', 'admin', 'manage', 'community', '2026-04-05 09:03:38'),
(57, 1, 'Voir les sections privées', 'forum.private.view', 'forum', 'view', 'community', '2026-04-05 09:03:38'),
(58, 1, 'Épingler un sujet', 'forum.topic.pin', 'forum', 'manage', 'community', '2026-04-05 09:03:38'),
(59, 1, 'Verrouiller / déverrouiller un sujet', 'forum.topic.lock', 'forum', 'moderate', 'community', '2026-04-05 09:03:38'),
(60, 1, 'Déplacer un sujet', 'forum.topic.move', 'forum', 'manage', 'community', '2026-04-05 09:03:38'),
(61, 1, 'Éditer n’importe quel message', 'forum.post.edit_any', 'forum', 'update', 'community', '2026-04-05 09:03:38'),
(62, 1, 'Supprimer n’importe quel message', 'forum.post.delete_any', 'forum', 'delete', 'community', '2026-04-05 09:03:38'),
(63, 1, 'Gérer les signalements', 'forum.reports.manage', 'forum', 'moderate', 'community', '2026-04-05 09:03:38'),
(64, 1, 'Gérer les tags / labels', 'forum.tags.manage', 'forum', 'manage', 'community', '2026-04-05 09:03:38'),
(65, 1, 'Gérer les catégories forum', 'forum.categories.manage', 'forum', 'manage', 'community', '2026-04-05 09:03:38'),
(66, 1, 'Publier des annonces globales', 'forum.announcements.publish', 'forum', 'approve', 'community', '2026-04-05 09:03:38'),
(67, 1, 'Voir les documents sensibles', 'documents.sensitive.view', 'documents', 'view', 'community', '2026-04-05 09:03:38'),
(68, 1, 'Télécharger les documents standards', 'documents.download.standard', 'documents', 'view', 'community', '2026-04-05 09:03:38'),
(69, 1, 'Remplacer une version', 'documents.version.replace', 'documents', 'update', 'community', '2026-04-05 09:03:38'),
(70, 1, 'Modifier les métadonnées', 'documents.metadata.update', 'documents', 'update', 'community', '2026-04-05 09:03:38'),
(71, 1, 'Supprimer un document', 'documents.delete', 'documents', 'delete', 'community', '2026-04-05 09:03:38'),
(72, 1, 'Gérer les catégories documentaires', 'documents.categories.manage', 'documents', 'manage', 'community', '2026-04-05 09:03:38'),
(73, 1, 'Gérer les droits d’accès documentaires', 'documents.access.manage', 'documents', 'manage', 'community', '2026-04-05 09:03:38'),
(74, 1, 'Partager en lien public', 'documents.share.public', 'documents', 'manage', 'community', '2026-04-05 09:03:38'),
(75, 1, 'Valider / publier un document', 'documents.publish', 'documents', 'approve', 'community', '2026-04-05 09:03:38'),
(76, 1, 'Créer une formation', 'training.create', 'training', 'create', 'community', '2026-04-05 09:03:38'),
(77, 1, 'Modifier une formation', 'training.update', 'training', 'update', 'community', '2026-04-05 09:03:38'),
(78, 1, 'Supprimer une formation', 'training.delete', 'training', 'delete', 'community', '2026-04-05 09:03:38'),
(79, 1, 'Publier / dépublier une formation', 'training.publish', 'training', 'approve', 'community', '2026-04-05 09:03:38'),
(80, 1, 'Corriger / valider les rendus', 'training.submissions.grade', 'training', 'approve', 'community', '2026-04-05 09:03:38'),
(81, 1, 'Voir les résultats', 'training.results.view', 'training', 'view', 'community', '2026-04-05 09:03:38'),
(82, 1, 'Exporter les résultats', 'training.results.export', 'training', 'export', 'community', '2026-04-05 09:03:38'),
(83, 1, 'Gérer les certifications', 'training.certifications.manage', 'training', 'manage', 'community', '2026-04-05 09:03:38'),
(84, 1, 'Gérer les prérequis', 'training.prerequisites.manage', 'training', 'manage', 'community', '2026-04-05 09:03:38'),
(85, 1, 'Voir les fiches membres', 'personnel.profile.view', 'personnel', 'view', 'community', '2026-04-05 09:03:38'),
(86, 1, 'Modifier les fiches membres', 'personnel.profile.update', 'personnel', 'update', 'community', '2026-04-05 09:03:38'),
(87, 1, 'Voir les informations sensibles', 'personnel.sensitive.view', 'personnel', 'view', 'community', '2026-04-05 09:03:38'),
(88, 1, 'Gérer les grades', 'personnel.grades.manage', 'personnel', 'manage', 'community', '2026-04-05 09:03:38'),
(89, 1, 'Gérer affectations / unités', 'personnel.assignments.manage', 'personnel', 'assign', 'community', '2026-04-05 09:03:38'),
(90, 1, 'Gérer les statuts', 'personnel.status.manage', 'personnel', 'manage', 'community', '2026-04-05 09:03:38'),
(91, 1, 'Gérer badges / qualifications', 'personnel.badges.manage', 'personnel', 'manage', 'community', '2026-04-05 09:03:38'),
(92, 1, 'Exporter l’annuaire', 'personnel.directory.export', 'personnel', 'export', 'community', '2026-04-05 09:03:38'),
(93, 1, 'Envoyer une annonce', 'comms.announcement.send', 'comms', 'create', 'community', '2026-04-05 09:03:38'),
(94, 1, 'Envoyer un email aux membres', 'comms.email.broadcast', 'comms', 'manage', 'community', '2026-04-05 09:03:38'),
(95, 1, 'Gérer les modèles d’email', 'comms.email_templates.manage', 'comms', 'manage', 'community', '2026-04-05 09:03:38'),
(96, 1, 'Voir l’historique des notifications', 'comms.notifications.history.view', 'comms', 'view', 'community', '2026-04-05 09:03:38'),
(97, 1, 'Gérer les alertes automatiques', 'comms.alerts.manage', 'comms', 'manage', 'community', '2026-04-05 09:03:38'),
(98, 1, 'Paramétrage fin des communications', 'comms.settings.advanced', 'comms', 'manage', 'community', '2026-04-05 09:03:38'),
(99, 7, 'Accès administration (tenant)', 'admin.access', 'admin', 'manage', 'community', '2026-04-05 09:10:01'),
(100, 7, 'Voir le forum', 'forum.view', 'forum', 'view', 'intra', '2026-04-05 09:10:01'),
(101, 7, 'Créer un sujet', 'forum.create_topic', 'forum', 'create', 'intra', '2026-04-05 09:10:01'),
(102, 7, 'Répondre', 'forum.reply', 'forum', 'create', 'intra', '2026-04-05 09:10:01'),
(103, 7, 'Modifier ses messages', 'forum.edit_own', 'forum', 'update', 'intra', '2026-04-05 09:10:01'),
(104, 7, 'Supprimer ses messages', 'forum.delete_own', 'forum', 'delete', 'intra', '2026-04-05 09:10:01'),
(105, 7, 'Modérer le forum (périmètre étendu)', 'forum.moderate', 'forum', 'moderate', 'community', '2026-04-05 09:10:01'),
(106, 7, 'Modérer la section forum organisation', 'forum.moderate_organization', 'forum', 'moderate', 'community', '2026-04-05 09:10:01'),
(107, 7, 'Gérer les catégories (identifiant historique)', 'forum.manage_categories', 'forum', 'manage', 'community', '2026-04-05 09:10:01'),
(108, 7, 'Voir les documents', 'documents.view', 'documents', 'view', 'community', '2026-04-05 09:10:02'),
(109, 7, 'Téléverser des documents', 'documents.upload', 'documents', 'create', 'community', '2026-04-05 09:10:02'),
(110, 7, 'Modifier les documents (héritage)', 'documents.update', 'documents', 'update', 'community', '2026-04-05 09:10:02'),
(111, 7, 'Archiver / désarchiver', 'documents.archive', 'documents', 'archive', 'community', '2026-04-05 09:10:02'),
(112, 7, 'Télécharger les documents sensibles', 'documents.download_sensitive', 'documents', 'view', 'community', '2026-04-05 09:10:02'),
(113, 7, 'Administration organisationnelle', 'admin.organization', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(114, 7, 'Voir les formations', 'training.view', 'training', 'view', 'community', '2026-04-05 09:10:02'),
(115, 7, 'Gérer les formations (périmètre étendu)', 'training.manage', 'training', 'manage', 'community', '2026-04-05 09:10:02'),
(116, 7, 'Assigner les formations', 'training.assign', 'training', 'assign', 'community', '2026-04-05 09:10:02'),
(117, 7, 'Voir le back-office', 'admin.backoffice.view', 'admin', 'view', 'community', '2026-04-05 09:10:02'),
(118, 7, 'Voir les membres', 'admin.members.view', 'admin', 'view', 'community', '2026-04-05 09:10:02'),
(119, 7, 'Gérer les membres', 'admin.members.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(120, 7, 'Inviter des membres', 'admin.members.invite', 'admin', 'create', 'community', '2026-04-05 09:10:02'),
(121, 7, 'Suspendre / exclure un membre', 'admin.members.moderate', 'admin', 'moderate', 'community', '2026-04-05 09:10:02'),
(122, 7, 'Gérer les rôles', 'admin.roles.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(123, 7, 'Gérer les permissions', 'admin.permissions.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(124, 7, 'Voir les journaux d’audit', 'admin.audit.view', 'admin', 'view', 'community', '2026-04-05 09:10:02'),
(125, 7, 'Gérer les paramètres de la communauté', 'admin.settings.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(126, 7, 'Gérer l’identité visuelle / branding', 'admin.branding.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(127, 7, 'Gérer les intégrations / API / webhooks', 'admin.integrations.manage', 'admin', 'manage', 'community', '2026-04-05 09:10:02'),
(128, 7, 'Envoyer des invitations', 'invitations.send', 'admin', 'create', 'community', '2026-04-05 09:10:02'),
(129, 7, 'Voir les sections privées', 'forum.private.view', 'forum', 'view', 'community', '2026-04-05 09:10:02'),
(130, 7, 'Épingler un sujet', 'forum.topic.pin', 'forum', 'manage', 'community', '2026-04-05 09:10:02'),
(131, 7, 'Verrouiller / déverrouiller un sujet', 'forum.topic.lock', 'forum', 'moderate', 'community', '2026-04-05 09:10:02'),
(132, 7, 'Déplacer un sujet', 'forum.topic.move', 'forum', 'manage', 'community', '2026-04-05 09:10:02'),
(133, 7, 'Éditer n’importe quel message', 'forum.post.edit_any', 'forum', 'update', 'community', '2026-04-05 09:10:02'),
(134, 7, 'Supprimer n’importe quel message', 'forum.post.delete_any', 'forum', 'delete', 'community', '2026-04-05 09:10:02'),
(135, 7, 'Gérer les signalements', 'forum.reports.manage', 'forum', 'moderate', 'community', '2026-04-05 09:10:02'),
(136, 7, 'Gérer les tags / labels', 'forum.tags.manage', 'forum', 'manage', 'community', '2026-04-05 09:10:02'),
(137, 7, 'Gérer les catégories forum', 'forum.categories.manage', 'forum', 'manage', 'community', '2026-04-05 09:10:02'),
(138, 7, 'Publier des annonces globales', 'forum.announcements.publish', 'forum', 'approve', 'community', '2026-04-05 09:10:02'),
(139, 7, 'Voir les documents sensibles', 'documents.sensitive.view', 'documents', 'view', 'community', '2026-04-05 09:10:02'),
(140, 7, 'Télécharger les documents standards', 'documents.download.standard', 'documents', 'view', 'community', '2026-04-05 09:10:02'),
(141, 7, 'Remplacer une version', 'documents.version.replace', 'documents', 'update', 'community', '2026-04-05 09:10:02'),
(142, 7, 'Modifier les métadonnées', 'documents.metadata.update', 'documents', 'update', 'community', '2026-04-05 09:10:02'),
(143, 7, 'Supprimer un document', 'documents.delete', 'documents', 'delete', 'community', '2026-04-05 09:10:02'),
(144, 7, 'Gérer les catégories documentaires', 'documents.categories.manage', 'documents', 'manage', 'community', '2026-04-05 09:10:02'),
(145, 7, 'Gérer les droits d’accès documentaires', 'documents.access.manage', 'documents', 'manage', 'community', '2026-04-05 09:10:02'),
(146, 7, 'Partager en lien public', 'documents.share.public', 'documents', 'manage', 'community', '2026-04-05 09:10:02'),
(147, 7, 'Valider / publier un document', 'documents.publish', 'documents', 'approve', 'community', '2026-04-05 09:10:02'),
(148, 7, 'Créer une formation', 'training.create', 'training', 'create', 'community', '2026-04-05 09:10:02'),
(149, 7, 'Modifier une formation', 'training.update', 'training', 'update', 'community', '2026-04-05 09:10:02'),
(150, 7, 'Supprimer une formation', 'training.delete', 'training', 'delete', 'community', '2026-04-05 09:10:02'),
(151, 7, 'Publier / dépublier une formation', 'training.publish', 'training', 'approve', 'community', '2026-04-05 09:10:02'),
(152, 7, 'Corriger / valider les rendus', 'training.submissions.grade', 'training', 'approve', 'community', '2026-04-05 09:10:02'),
(153, 7, 'Voir les résultats', 'training.results.view', 'training', 'view', 'community', '2026-04-05 09:10:02'),
(154, 7, 'Exporter les résultats', 'training.results.export', 'training', 'export', 'community', '2026-04-05 09:10:02'),
(155, 7, 'Gérer les certifications', 'training.certifications.manage', 'training', 'manage', 'community', '2026-04-05 09:10:02'),
(156, 7, 'Gérer les prérequis', 'training.prerequisites.manage', 'training', 'manage', 'community', '2026-04-05 09:10:02'),
(157, 7, 'Voir les fiches membres', 'personnel.profile.view', 'personnel', 'view', 'community', '2026-04-05 09:10:02'),
(158, 7, 'Modifier les fiches membres', 'personnel.profile.update', 'personnel', 'update', 'community', '2026-04-05 09:10:02'),
(159, 7, 'Voir les informations sensibles', 'personnel.sensitive.view', 'personnel', 'view', 'community', '2026-04-05 09:10:02'),
(160, 7, 'Gérer les grades', 'personnel.grades.manage', 'personnel', 'manage', 'community', '2026-04-05 09:10:02'),
(161, 7, 'Gérer affectations / unités', 'personnel.assignments.manage', 'personnel', 'assign', 'community', '2026-04-05 09:10:02'),
(162, 7, 'Gérer les statuts', 'personnel.status.manage', 'personnel', 'manage', 'community', '2026-04-05 09:10:02'),
(163, 7, 'Gérer badges / qualifications', 'personnel.badges.manage', 'personnel', 'manage', 'community', '2026-04-05 09:10:02'),
(164, 7, 'Exporter l’annuaire', 'personnel.directory.export', 'personnel', 'export', 'community', '2026-04-05 09:10:02'),
(165, 7, 'Envoyer une annonce', 'comms.announcement.send', 'comms', 'create', 'community', '2026-04-05 09:10:02'),
(166, 7, 'Envoyer un email aux membres', 'comms.email.broadcast', 'comms', 'manage', 'community', '2026-04-05 09:10:02'),
(167, 7, 'Gérer les modèles d’email', 'comms.email_templates.manage', 'comms', 'manage', 'community', '2026-04-05 09:10:02'),
(168, 7, 'Voir l’historique des notifications', 'comms.notifications.history.view', 'comms', 'view', 'community', '2026-04-05 09:10:02'),
(169, 7, 'Gérer les alertes automatiques', 'comms.alerts.manage', 'comms', 'manage', 'community', '2026-04-05 09:10:02'),
(170, 7, 'Paramétrage fin des communications', 'comms.settings.advanced', 'comms', 'manage', 'community', '2026-04-05 09:10:02'),
(171, 7, 'Voir le Bureau Courrier', 'courrier.view', 'courrier', 'view', 'community', '2026-04-05 09:10:02'),
(172, 7, 'Créer des documents courrier', 'courrier.create', 'courrier', 'create', 'community', '2026-04-05 09:10:02'),
(173, 7, 'Valider des documents courrier', 'courrier.validate', 'courrier', 'approve', 'community', '2026-04-05 09:10:02'),
(174, 7, 'Archiver des documents courrier', 'courrier.archive', 'courrier', 'archive', 'community', '2026-04-05 09:10:02');

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
(5, 5, 2, 'Officier opérations', 1, '2026-04-05', NULL, 'active', '2026-04-05 12:09:47', NULL);

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

INSERT INTO `personnel_extras` (`user_id`, `service_number`, `squadron`, `date_of_enlistment`, `clearance_level`, `flight_hours`, `specializations`, `readiness_percent`, `admin_notes`, `created_at`, `updated_at`) VALUES
(5, 'ATH-00001', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-04-05 09:16:46', '2026-04-05 12:09:47');

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
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personnel_job_roles`
--

INSERT INTO `personnel_job_roles` (`id`, `tenant_id`, `category_id`, `name`, `slug`, `description`, `sort_order`, `is_system`, `created_at`) VALUES
(1, 1, 5, 'Officier opérations', 'officier-operations', 'Coordination des opérations et briefs.', 0, 1, '2026-04-05 11:27:53'),
(2, 1, 5, 'Chef de section', 'chef-de-section', 'Encadrement de section.', 1, 1, '2026-04-05 11:27:53'),
(3, 1, 6, 'Fusilier', 'fusilier', 'Combattant polyvalent.', 2, 1, '2026-04-05 11:27:53'),
(4, 1, 6, 'Grenadier', 'grenadier', 'Appui grenades / lourd léger.', 3, 1, '2026-04-05 11:27:53'),
(5, 1, 7, 'JTAC / FO', 'jtac', 'Guidage feu indirect.', 4, 1, '2026-04-05 11:27:53'),
(6, 1, 7, 'Medic / secouriste', 'medic', 'Soutien sanitaire.', 5, 1, '2026-04-05 11:27:53'),
(7, 1, 8, 'Logistique', 'logistique-r', 'Ravitaillement, transport.', 6, 1, '2026-04-05 11:27:53'),
(8, 1, 9, 'Formateur', 'formateur', 'Pédagogie, montée en compétence.', 7, 1, '2026-04-05 11:27:53'),
(9, 1, 9, 'Instructeur', 'instructeur', 'Instruction collective.', 8, 1, '2026-04-05 11:27:53'),
(10, 7, 14, 'Officier opérations', 'officier-operations', 'Coordination des opérations et briefs.', 0, 1, '2026-04-05 11:27:53'),
(11, 7, 14, 'Chef de section', 'chef-de-section', 'Encadrement de section.', 1, 1, '2026-04-05 11:27:53'),
(12, 7, 15, 'Fusilier', 'fusilier', 'Combattant polyvalent.', 2, 1, '2026-04-05 11:27:53'),
(13, 7, 15, 'Grenadier', 'grenadier', 'Appui grenades / lourd léger.', 3, 1, '2026-04-05 11:27:53'),
(14, 7, 16, 'JTAC / FO', 'jtac', 'Guidage feu indirect.', 4, 1, '2026-04-05 11:27:53'),
(15, 7, 16, 'Medic / secouriste', 'medic', 'Soutien sanitaire.', 5, 1, '2026-04-05 11:27:53'),
(16, 7, 17, 'Logistique', 'logistique-r', 'Ravitaillement, transport.', 6, 1, '2026-04-05 11:27:53'),
(17, 7, 18, 'Formateur', 'formateur', 'Pédagogie, montée en compétence.', 7, 1, '2026-04-05 11:27:53'),
(18, 7, 18, 'Instructeur', 'instructeur', 'Instruction collective.', 8, 1, '2026-04-05 11:27:53');

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
(18, 7, 13, 'Instruction', 'formation-inst', 1, '2026-04-05 11:27:53');

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
(4, 5, 'NewPI', 'ADMIN', NULL, NULL, 'Officier opérations', 'Liaison', 10, NULL, 2, 'Secret', 'uploads/portraits/5_1775383355.png', NULL, NULL, NULL, NULL, '2026-04-05', NULL, 0, '', 'ATH-00001', NULL, 'Command & Control', 'C2 léger / tablette mission', 'PRC-152', 'Utility / VT4', 'Carabine / pistolet', 1, '2026-04-05 09:16:46', '2026-04-05 12:09:47'),
(28, 7, 'Melvin MESNEL', 'E-11', NULL, NULL, 'JTAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-05 11:03:21', '2026-04-05 11:03:21');

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

--
-- Déchargement des données de la table `platform_alerts`
--

INSERT INTO `platform_alerts` (`id`, `kind`, `title`, `body`, `cta_label`, `cta_url`, `coupon_code`, `starts_at`, `ends_at`, `sort_order`, `is_active`, `audience_json`, `created_at`, `updated_at`) VALUES
(1, 'info', 'Mise à jour', 'Une important update de la base de donnée est en cours, des ralentissements peuvent se produire.', NULL, NULL, NULL, '2026-04-04 11:50:00', NULL, 0, 1, '{\"guest\":true,\"authenticated\":true,\"free\":true,\"paid\":true}', '2026-04-05 09:50:38', '2026-04-05 09:51:41');

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
(86, 7, 5, 'dashboard_visit', 'view', '2026-04-05 16:22:44');

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
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `role_layer` enum('site','community','intra') NOT NULL DEFAULT 'community',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `slug`, `description`, `is_system`, `is_locked`, `role_layer`, `created_at`) VALUES
(1, 1, 'État-major', 'tenant_admin', 'Direction de l’unité au quotidien : effectifs, ORBAT, rôles, invitations, modération organisationnelle et paramètres.', 1, 0, 'community', '2026-03-13 17:47:31'),
(2, 1, 'Modérateur forum', 'forum_moderator', 'Modération des espaces de discussion de l’unité (épinglage, signalements, catégories). Périmètre organisation, pas administration plateforme.', 1, 0, 'intra', '2026-03-13 19:23:12'),
(3, 1, 'Opérateur', 'member', 'Membre titulaire de l’unité : accès forum, documents standards et formations selon affectation.', 1, 0, 'intra', '2026-03-13 19:23:12'),
(4, 1, 'Cadre', 'officer', 'Encadrement : coordination d’équipe, documents opérationnels et visibilité renforcée sur les ressources.', 1, 0, 'intra', '2026-03-14 00:01:46'),
(14, NULL, 'Super administrateur site', 'site_super_admin', 'Administration plateforme (global)', 1, 1, 'site', '2026-04-04 15:13:10'),
(15, 1, 'Fondateur', 'community_owner', 'Propriétaire de la communauté : vision, gouvernance et validation stratégique. Ne confère pas l’administration technique de la plateforme.', 1, 1, 'community', '2026-04-04 15:13:10'),
(21, 1, 'Recruteur', 'recruiter', 'Pipeline recrutement : candidatures, échanges avec les postulants et liaison avec le commandement.', 1, 0, 'community', '2026-04-05 08:48:49'),
(22, 7, 'Fondateur', 'community_owner', 'Propriétaire de la communauté : vision, gouvernance et validation stratégique. Ne confère pas l’administration technique de la plateforme.', 1, 1, 'community', '2026-04-05 09:10:01'),
(23, 7, 'État-major', 'tenant_admin', 'Direction de l’unité au quotidien : effectifs, ORBAT, rôles, invitations, modération organisationnelle et paramètres.', 1, 0, 'community', '2026-04-05 09:10:01'),
(24, 7, 'Modérateur forum', 'forum_moderator', 'Modération des espaces de discussion de l’unité (épinglage, signalements, catégories). Périmètre organisation, pas administration plateforme.', 1, 0, 'intra', '2026-04-05 09:10:01'),
(25, 7, 'Opérateur', 'member', 'Membre titulaire de l’unité : accès forum, documents standards et formations selon affectation.', 1, 0, 'intra', '2026-04-05 09:10:01'),
(26, 7, 'Cadre', 'officer', 'Encadrement : coordination d’équipe, documents opérationnels et visibilité renforcée sur les ressources.', 1, 0, 'intra', '2026-04-05 09:10:01'),
(27, 7, 'RH (S1)', 'hr', 'Ressources humaines : dossiers personnel, grades et suivi administratif des effectifs.', 1, 0, 'intra', '2026-04-05 09:10:02'),
(28, 7, 'Visiteur', 'invite', 'Accès limité en attente d’intégration ou compte prospect (lecture ciblée).', 1, 0, 'intra', '2026-04-05 09:10:02'),
(29, 7, 'Recruteur', 'recruiter', 'Pipeline recrutement : candidatures, échanges avec les postulants et liaison avec le commandement.', 1, 0, 'community', '2026-04-05 09:11:07'),
(30, 1, 'RH (S1)', 'hr', 'Ressources humaines : dossiers personnel, grades et suivi administratif des effectifs.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(31, 1, 'Visiteur', 'invite', 'Accès limité en attente d’intégration ou compte prospect (lecture ciblée).', 1, 0, 'intra', '2026-04-05 11:45:15'),
(32, 1, 'Instructeur', 'instructor', 'Pôle formation : parcours, assignations, correction des rendus et suivi des qualifications.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(33, 1, 'OPSAN', 'medic', 'Santé / secours : visibilité renforcée sur les informations médicales autorisées et coordination sanitaire.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(34, 1, 'Logistique', 'logistics', 'Soutien matériel : dépôt, fiches équipement et documentation de soutien.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(35, 1, 'R2 (transmissions)', 'rto', 'Radio-téléphoniste / transmissions : diffusion d’informations officielles et coordination des annonces.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(36, 1, 'Période d’essai', 'probation', 'Intégration provisoire : participation encadrée au forum en attendant la titularisation.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(37, 7, 'Instructeur', 'instructor', 'Pôle formation : parcours, assignations, correction des rendus et suivi des qualifications.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(38, 7, 'OPSAN', 'medic', 'Santé / secours : visibilité renforcée sur les informations médicales autorisées et coordination sanitaire.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(39, 7, 'Logistique', 'logistics', 'Soutien matériel : dépôt, fiches équipement et documentation de soutien.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(40, 7, 'R2 (transmissions)', 'rto', 'Radio-téléphoniste / transmissions : diffusion d’informations officielles et coordination des annonces.', 1, 0, 'intra', '2026-04-05 11:45:15'),
(41, 7, 'Période d’essai', 'probation', 'Intégration provisoire : participation encadrée au forum en attendant la titularisation.', 1, 0, 'intra', '2026-04-05 11:45:15');

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
(15, 1),
(21, 1),
(30, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(1, 2),
(2, 2),
(3, 2),
(15, 2),
(21, 2),
(30, 2),
(32, 2),
(34, 2),
(35, 2),
(1, 3),
(2, 3),
(3, 3),
(15, 3),
(21, 3),
(30, 3),
(32, 3),
(33, 3),
(34, 3),
(35, 3),
(36, 3),
(1, 4),
(2, 4),
(3, 4),
(15, 4),
(32, 4),
(35, 4),
(1, 5),
(2, 5),
(15, 5),
(32, 5),
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
(1, 10),
(4, 10),
(15, 10),
(34, 10),
(1, 11),
(4, 11),
(15, 11),
(1, 12),
(15, 12),
(1, 13),
(15, 13),
(1, 14),
(15, 14),
(30, 14),
(32, 14),
(1, 15),
(15, 15),
(1, 16),
(15, 16),
(32, 16),
(1, 17),
(15, 17),
(1, 18),
(15, 18),
(1, 19),
(3, 19),
(15, 19),
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
(15, 68),
(32, 68),
(33, 68),
(1, 69),
(15, 69),
(1, 70),
(15, 70),
(34, 70),
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
(1, 81),
(15, 81),
(32, 81),
(1, 82),
(15, 82),
(1, 83),
(15, 83),
(1, 84),
(15, 84),
(1, 85),
(15, 85),
(21, 85),
(30, 85),
(32, 85),
(33, 85),
(1, 86),
(15, 86),
(30, 86),
(1, 87),
(15, 87),
(33, 87),
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
(35, 93),
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
(27, 100),
(28, 100),
(29, 100),
(37, 100),
(38, 100),
(39, 100),
(40, 100),
(41, 100),
(22, 101),
(23, 101),
(24, 101),
(25, 101),
(27, 101),
(29, 101),
(37, 101),
(39, 101),
(40, 101),
(22, 102),
(23, 102),
(24, 102),
(25, 102),
(27, 102),
(29, 102),
(37, 102),
(38, 102),
(39, 102),
(40, 102),
(41, 102),
(22, 103),
(23, 103),
(24, 103),
(25, 103),
(37, 103),
(40, 103),
(22, 104),
(23, 104),
(24, 104),
(37, 104),
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
(22, 109),
(23, 109),
(26, 109),
(39, 109),
(22, 110),
(23, 110),
(26, 110),
(22, 111),
(23, 111),
(22, 112),
(23, 112),
(22, 113),
(23, 113),
(22, 114),
(23, 114),
(27, 114),
(37, 114),
(22, 115),
(23, 115),
(22, 116),
(23, 116),
(37, 116),
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
(37, 140),
(38, 140),
(22, 141),
(23, 141),
(22, 142),
(23, 142),
(39, 142),
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
(22, 153),
(23, 153),
(37, 153),
(22, 154),
(23, 154),
(22, 155),
(23, 155),
(22, 156),
(23, 156),
(22, 157),
(23, 157),
(27, 157),
(29, 157),
(37, 157),
(38, 157),
(22, 158),
(23, 158),
(27, 158),
(22, 159),
(23, 159),
(38, 159),
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
(23, 174);

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
(1, 'tetard.tanguy@gmail.com', 14, NULL, '2026-04-04 16:09:10', NULL);

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
(7, 'ATHENA', 'athena-sys', 'ATHENA-SYS', 'https://athena.ttrd.fr/public/uploads/community-wizard/3/logo_1a0c4d6535b4040a.png', '{\"community\":{\"registration_mode\":\"simple\",\"community_locked\":false,\"require_ai_ack\":true,\"welcome_text\":\"Concerne l\'administration et la mod\\u00e9ration syst\\u00e8me\",\"public_page_layout\":\"legacy\",\"public_hero_subtitle\":\"Gestionnaire plateforme\",\"default_locale\":\"fr\",\"orbat_visibility\":\"public\",\"default_guest_role_slug\":\"invite\",\"presentation_mode\":\"simple\",\"style_badges\":[],\"simple_body\":\"Encadrement dynamique de la plateforme et de l\'administration technique\",\"expectations\":\"7\\/7 - 24-24\",\"enlistment_milsim\":{\"portal_title\":\"e\",\"fields\":{\"full_name\":{\"label\":\"01 Nom & Pr\\u00e9nom (identit\\u00e9 dossier)\",\"placeholder\":\"ex: Jonathan King\",\"widget\":\"text\",\"options\":[]},\"legal_full_name\":{\"label\":\"Contact IRL (si personnage RP)\",\"placeholder\":\"Nom l\\u00e9gal pour recontact \\u2014 optionnel si d\\u00e9j\\u00e0 indiqu\\u00e9 ailleurs\",\"widget\":\"text\",\"options\":[]},\"age\":{\"label\":\"02 \\u00c2ge\",\"placeholder\":\"\\u00c2ge minimum requis\",\"widget\":\"text\",\"options\":[]},\"timezone\":{\"label\":\"03 Fuseau Horaire\",\"placeholder\":\"ex: Paris (UTC+1)\",\"widget\":\"text\",\"options\":[]},\"weekly_availability\":{\"label\":\"04 Disponibilit\\u00e9s Hebdomadaires\",\"placeholder\":\"Jours de la semaine\",\"widget\":\"text\",\"options\":[]},\"email\":{\"label\":\"Email (obligatoire)\",\"placeholder\":\"email@exemple.fr\",\"widget\":\"text\",\"options\":[]},\"callsign\":{\"label\":\"Indicatif \\/ callsign (optionnel)\",\"placeholder\":\"ex: Ghost-2-1\",\"widget\":\"text\",\"options\":[]},\"system_config\":{\"label\":\"05 Configuration (CPU\\/GPU\\/RAM)\",\"placeholder\":\"Configuration syst\\u00e8me\",\"widget\":\"text\",\"options\":[]},\"microphone_quality\":{\"label\":\"06 Microphone de Haute Qualit\\u00e9 ?\",\"placeholder\":\"\",\"widget\":\"yesno\",\"options\":[\"Oui\",\"Non\"]},\"past_milsim_experience\":{\"label\":\"07 Exp\\u00e9riences MilSim Pass\\u00e9es\",\"placeholder\":\"Unit\\u00e9s, r\\u00f4les, dur\\u00e9es...\",\"widget\":\"textarea\",\"options\":[]},\"ace_acre_level\":{\"label\":\"08 Ma\\u00eetrise ACE \\/ ACRE\",\"placeholder\":\"\",\"widget\":\"select\",\"options\":[\"Aucune\",\"Basique\",\"Exp\\u00e9riment\\u00e9\",\"Avanc\\u00e9\"]},\"motivation_why_join\":{\"label\":\"09 Pourquoi rejoindre ?\",\"placeholder\":\"Motivation, engagement...\",\"widget\":\"textarea\",\"options\":[]},\"motivation_accountability\":{\"label\":\"10 Qu\'est-ce que l\'Accountability ?\",\"placeholder\":\"Responsabilit\\u00e9 individuelle dans une unit\\u00e9...\",\"widget\":\"textarea\",\"options\":[]}},\"nav_brand\":\"Athena\"}},\"founder_trial_ends_at\":\"2026-05-05T09:10:02+00:00\",\"grade_system_code\":\"FR_CLASSIC\",\"timezone\":\"Europe\\/Paris\",\"onboarding_wizard_version\":2,\"onboarding_completed_at\":\"2026-04-05T09:10:02+00:00\"}', 5, 'free', NULL, NULL, 'none', NULL, '2026-04-05 09:10:01', '2026-04-05 09:10:02', 'Europe/Paris', 'fr-FR', NULL);

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
(7, 'ATH', '{prefix}-{seq:5}', 2, '2026-04-05 09:16:46');

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

-- --------------------------------------------------------

--
-- Structure de la table `training_certificates`
--

CREATE TABLE `training_certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `certificate_number` varchar(100) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `final_score` decimal(6,2) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `status` enum('valid','expired','revoked') DEFAULT 'valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_courses`
--

CREATE TABLE `training_courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
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
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
  `status` enum('assigned','in_progress','completed','failed','expired','revoked') DEFAULT 'assigned',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_lessons`
--

CREATE TABLE `training_lessons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `lesson_type` enum('richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas') NOT NULL DEFAULT 'richtext',
  `content` longtext DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `duration_minutes` int(10) UNSIGNED DEFAULT 0,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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

-- --------------------------------------------------------

--
-- Structure de la table `training_resources`
--

CREATE TABLE `training_resources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_id` bigint(20) UNSIGNED NOT NULL,
  `resource_type` enum('pdf','image','video','audio','zip','attachment','link') NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
(2, 7, NULL, 'État-major', 'etat-major', 'group', NULL, NULL, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(3, 7, 2, '1re section', '1re-section', 'section', NULL, NULL, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-05 09:10:02'),
(4, 7, NULL, 'Administration Générale', 'administration-generale', 'team', NULL, NULL, 0, NULL, NULL, 1, '2026-04-05 09:10:02', '2026-04-05 11:36:26');

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

INSERT INTO `users` (`id`, `tenant_id`, `email`, `email_verified_at`, `email_verification_sent_at`, `nationality_code`, `preferred_grade_format`, `password_hash`, `display_name`, `callsign`, `profile_slug`, `steam_id`, `avatar_url`, `role_id`, `grade_id`, `professional_category_code`, `status`, `is_service_account`, `last_login_at`, `created_at`, `updated_at`) VALUES
(3, 1, 'tetard.tanguy@gmail.com', '2026-04-04 16:09:10', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$R1JUM1hSLnlEenRpL3Ayaw$712JHsttH+eD0iS7qfW+jE1zovq+HrXCMEBg8mRDXbQ', 'NewPI', 'ADMIN', NULL, NULL, 'uploads/avatars/3_1775320910.png', 15, 1, NULL, 'active', 0, '2026-04-05 08:24:25', '2026-04-04 16:09:10', '2026-04-04 16:42:06'),
(4, 1, 'system.moderation@internal.local', '2026-04-05 08:43:52', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$eC5FYmV6U2NJLjVnemVCMw$STbZvnbDWhnbnZo5WgayzGr1HBeUHjpcOyM3Ud4Iaj4', 'Modération automatique', 'SYSMOD', NULL, NULL, NULL, NULL, NULL, NULL, 'inactive', 1, NULL, '2026-04-05 08:43:52', '2026-04-05 08:43:52'),
(5, 7, 'tetard.tanguy@gmail.com', '2026-04-04 16:09:10', NULL, 'FR', 'hybrid', '$argon2id$v=19$m=65536,t=4,p=1$MFdQcHZLUzU3YVpEZ1VhVQ$ZTTgyYqHqr5Jk28sA/OPz1JPAU5UEb9/5rASCT3s2b4', 'NewPI', 'ADMIN', 'newpi', NULL, 'uploads/avatars/5_1775380800.jpg', 22, 6, 'OFFICIER', 'active', 0, '2026-04-05 15:52:27', '2026-04-05 09:10:02', '2026-04-05 10:01:02'),
(6, 7, 'system.moderation@internal.local', '2026-04-05 09:10:02', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$OEJQVVBHSk9ZNlZoak1VOQ$XEXunBkJMfuo6mF4N8E6S7Klewf21XtRArOjqKcdX58', 'Modération automatique', 'SYSMOD', NULL, NULL, NULL, NULL, NULL, 'HORS_GRADE', 'inactive', 1, NULL, '2026-04-05 09:10:02', '2026-04-05 11:11:44'),
(7, 1, 'tanguy.inc@gmail.com', '2026-04-05 11:21:58', NULL, NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$N1o3bHBoekVuWWlGcXNUdw$Oi+PE3ydLgjNaq38DM7myVdsiCu7aXBbTkd37pi0JL8', 'Tangohan', 'E-11', 'tangohan', NULL, NULL, 3, NULL, NULL, 'active', 0, '2026-04-05 11:22:14', '2026-04-05 11:03:21', '2026-04-05 11:21:58');

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
(1, 5, 7, '33ce8e1635031beb8fa0dbff7f1f3b22930089f4c8f62b83ad458bb135145ca1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'FR', '2026-04-05 15:52:27', '2026-04-05 10:02:15'),
(2, 7, 1, 'fbecafe40e809d0105a4ab52af329e8f8660e8f5a54e5c52e6247c72f11bdfb2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', '2a01:e0a:8ee:2720:ec90:e096:ed67:b2c4', 'FR', '2026-04-05 11:22:14', '2026-04-05 11:22:14');

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
(5, 'Tanguy', 'TETARD', NULL, NULL, 'Europe/Paris', 'fr', NULL, NULL, NULL, NULL, '2026-04-05 09:53:44', '2026-04-05 12:09:47');

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
(5, NULL, 'display_name', NULL, 0, 1, 1, 1, 1, 0, 0, 1, 1, '2026-04-05 09:17:12', '2026-04-05 12:09:47');

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
(5, 22, '2026-04-05 11:59:36'),
(7, 3, '2026-04-05 11:59:36');

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
(5, 5, 2, 1, NULL, '2026-04-05 12:09:47', NULL, 'Officier opérations', NULL);

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
-- Index pour la table `blocked_indicators`
--
ALTER TABLE `blocked_indicators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scope_hash` (`scope`,`tenant_id`,`indicator_type`,`value_hash`),
  ADD KEY `expires` (`expires_at`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

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
  ADD KEY `enlistments_recruitment_preset_fk` (`recruitment_preset_id`);

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
  ADD KEY `forum_posts_parent` (`parent_post_id`);

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
  ADD PRIMARY KEY (`user_id`);

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
-- Index pour la table `platform_usage_events`
--
ALTER TABLE `platform_usage_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_day` (`tenant_id`,`created_at`),
  ADD KEY `feature` (`feature_key`);

--
-- Index pour la table `recon_images`
--
ALTER TABLE `recon_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_mission` (`tenant_id`,`mission_id`),
  ADD KEY `author_callsign` (`author_callsign`),
  ADD KEY `captured_at` (`captured_at`);

--
-- Index pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `roles_tenant_layer` (`tenant_id`,`role_layer`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

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
-- Index pour la table `tenant_usage_counters`
--
ALTER TABLE `tenant_usage_counters`
  ADD PRIMARY KEY (`tenant_id`,`metric_key`,`period_start`),
  ADD KEY `tenant_metric` (`tenant_id`,`metric_key`);

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
  ADD KEY `fk_training_certificates_tenant` (`tenant_id`);

--
-- Index pour la table `training_courses`
--
ALTER TABLE `training_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_training_courses_uuid` (`uuid`),
  ADD UNIQUE KEY `uk_training_courses_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `idx_training_courses_visibility` (`visibility`),
  ADD KEY `idx_training_courses_category` (`category`),
  ADD KEY `idx_training_courses_tenant` (`tenant_id`);

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
  ADD KEY `idx_training_resources_lesson` (`lesson_id`);

--
-- Index pour la table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `email_tokens`
--
ALTER TABLE `email_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `enlistments`
--
ALTER TABLE `enlistments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_blacklisted_domains`
--
ALTER TABLE `forum_blacklisted_domains`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_categories`
--
ALTER TABLE `forum_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
-- AUTO_INCREMENT pour la table `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_reports`
--
ALTER TABLE `forum_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `intel_reports_events`
--
ALTER TABLE `intel_reports_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `pending_community_creates`
--
ALTER TABLE `pending_community_creates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `personnel_job_roles`
--
ALTER TABLE `personnel_job_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `personnel_job_role_categories`
--
ALTER TABLE `personnel_job_role_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT pour la table `recon_images`
--
ALTER TABLE `recon_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT pour la table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `site_role_assignments`
--
ALTER TABLE `site_role_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `training_audit_log`
--
ALTER TABLE `training_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_certificates`
--
ALTER TABLE `training_certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_courses`
--
ALTER TABLE `training_courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_lessons`
--
ALTER TABLE `training_lessons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_modules`
--
ALTER TABLE `training_modules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_progress`
--
ALTER TABLE `training_progress`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_quizzes`
--
ALTER TABLE `training_quizzes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_quiz_answers`
--
ALTER TABLE `training_quiz_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_quiz_attempts`
--
ALTER TABLE `training_quiz_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_quiz_questions`
--
ALTER TABLE `training_quiz_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_quiz_responses`
--
ALTER TABLE `training_quiz_responses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `training_resources`
--
ALTER TABLE `training_resources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `forum_posts_parent_fk` FOREIGN KEY (`parent_post_id`) REFERENCES `forum_posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Contraintes pour la table `recon_images`
--
ALTER TABLE `recon_images`
  ADD CONSTRAINT `recon_images_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recruitment_presets`
--
ALTER TABLE `recruitment_presets`
  ADD CONSTRAINT `recruitment_presets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `roles_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_permission_id_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

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
-- Contraintes pour la table `tenant_usage_counters`
--
ALTER TABLE `tenant_usage_counters`
  ADD CONSTRAINT `fk_tuc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `training_certificates`
--
ALTER TABLE `training_certificates`
  ADD CONSTRAINT `fk_training_certificates_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_training_certificates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `training_courses`
--
ALTER TABLE `training_courses`
  ADD CONSTRAINT `fk_training_courses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Contraintes pour la table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
