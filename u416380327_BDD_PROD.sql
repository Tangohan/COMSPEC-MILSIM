-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : sam. 04 avr. 2026 à 14:58
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
(1, 1, 1, 'auth.login_success', 'auth', 1, NULL, NULL, '2a01:e0a:8ee:2720:2183:6d5a:c7d5:4be', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 14:18:47');

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
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
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
  `invited_by_user_id` int(10) UNSIGNED NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `accepted_user_id` int(10) UNSIGNED DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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

INSERT INTO `courrier_documents` (`id`, `uuid`, `tenant_id`, `template_id`, `preset_id`, `type`, `status`, `title`, `reference_number`, `subject`, `destination_label`, `issuer_label`, `body_rendered`, `variables_json`, `metadata_json`, `attachments_json`, `classification_level`, `created_by`, `validated_by`, `signed_by`, `signed_at`, `signature_data_json`, `content_hash`, `created_at`, `updated_at`, `sent_at`, `archived_at`) VALUES
(1, '814a437b-206d-11f1-a9a0-91b7e349c605', 1, 2, 1, NULL, 'signed', 'test', 'CR-2026-0001', 'Test d\'objet', 'Personne', 'Tanguy TETARD', 'Mon Capitaine,\r\n\r\nJ\'ai l\'honneur de vous rendre compte des faits suivants survenus le 08 janvier 2026. Lors de la mise en place de la Section d\'Appui sur le point ALPHA, un incident de tir a été constaté sur l\'arme collective du personnel DUBOIS Arthur (MAT: 4512-01).\r\n\r\nConformément aux directives du TTA 150, les mesures de sécurité immédiates ont été appliquées. L\'intéressé a été retiré de la ligne de feu en attente de l\'expertise de l\'armurier. L\'intégrité physique du personnel n\'est pas engagée.\r\n\r\nJe vous ferai connaître les conclusions de l\'enquête technique dès réception du rapport de l\'armurerie.', NULL, NULL, NULL, 'interne', 1, 1, 1, '2026-03-15 14:49:00', '{\"signature_image_path\":\"1\\/1\\/signature.png\",\"stamp_original_signed\":\"\",\"stamp_name_signature\":\"Tanguy TETARD\",\"stamp_grade\":\"Lieutenant\",\"signature_source\":\"pad\"}', '3bfe2b7291c8c6367cb89b1cd6278f0d5a407d8f9e65a8fcc43bb9eab5e41989', '2026-03-15 12:50:11', '2026-03-15 14:50:19', NULL, NULL),
(2, 'fecb83d9-2080-11f1-a9a0-91b7e349c605', 1, 2, 11, NULL, 'draft', 'eee', 'CR-2026-0002', 'ee', 'eeee', 'Tanguy TETARD', 'eeeee', NULL, NULL, NULL, 'interne', 1, NULL, NULL, NULL, NULL, NULL, '2026-03-15 15:09:42', '2026-03-15 15:09:42', NULL, NULL);

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
(1, 1, 1, '{\"title\":null,\"subject\":null,\"reference_number\":null,\"body_rendered\":\"\",\"destination_label\":null,\"issuer_label\":null,\"updated_at\":\"2026-03-15 13:54:52\"}', 1, '2026-03-15 14:12:56'),
(2, 1, 2, '{\"title\":null,\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:12:56\"}', 1, '2026-03-15 14:16:48'),
(3, 1, 3, '{\"title\":\"test\",\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:16:48\"}', 1, '2026-03-15 14:16:53'),
(4, 1, 4, '{\"title\":\"test\",\"subject\":null,\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":null,\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:48:23\"}', 1, '2026-03-15 14:48:34'),
(5, 1, 5, '{\"title\":\"test\",\"subject\":\"Test d\'objet\",\"reference_number\":\"CR-2026-0001\",\"body_rendered\":\"test\",\"destination_label\":\"Personne\",\"issuer_label\":\"Tanguy TETARD\",\"updated_at\":\"2026-03-15 14:49:00\"}', 1, '2026-03-15 14:50:19');

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
(5, 1, 'Média pédagogique', 'media', 'violet', '2026-03-14 00:01:46');

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
(1, 1, 'draft', 'pending_validation', 'Soumis à validation', NULL, 1, '2026-03-15 14:48:23'),
(2, 1, 'pending_validation', 'validated', 'Validé', NULL, 1, '2026-03-15 14:48:39');

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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `enlistments`
--

INSERT INTO `enlistments` (`id`, `tenant_id`, `first_name`, `last_name`, `email`, `callsign`, `country`, `experience`, `specialty`, `platform`, `availability`, `notes`, `age`, `timezone`, `weekly_availability`, `system_config`, `microphone_quality`, `past_milsim_experience`, `ace_acre_level`, `motivation_why_join`, `motivation_accountability`, `commitment_effort`, `availability_wed_sat`, `no_ai_confirmed`, `status`, `reviewed_by`, `reviewed_at`, `reviewer_comment`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tanguy', 'TETARD', 'wikzzcoc@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 25, 'Paris', 'Jeudi et vendredi', 'I9', 'Oui', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Basique', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer interdum at sem ac finibus. Pellentesque pellentesque justo lorem, sit amet placerat augue finibus nec. Proin ac libero eget mi iaculis tempor eget non felis. Phasellus euismod, nibh sit amet tempus imperdiet, massa sem luctus metus, et laoreet velit leo et nibh. Vivamus ac libero sed ex rhoncus cursus at eu turpis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Ut posuere ante nec ipsum facilisis, quis pulvinar ex maximus. Phasellus a tempus augue. Etiam accumsan lacinia felis, eget eleifend tortor suscipit eget. Etiam at sollicitudin turpis. Pellentesque sed sodales nisl, eu sollicitudin massa. Etiam pulvinar magna nisi, nec aliquam erat consequat et.', 'Oui', 'Variable', 1, 'submitted', NULL, NULL, NULL, '2026-03-13 19:38:46', '2026-03-13 19:38:46');

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
(6, 1, 'Médical', 'medical', 'medical', NULL, '2026-03-14 00:01:46');

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

INSERT INTO `forum_categories` (`id`, `tenant_id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color_theme`, `display_order`, `is_locked`, `min_role_id`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', NULL, 'orange', 10, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(2, 1, NULL, 'Général', 'general', 'Discussions générales et présentation.', NULL, 'indigo', 20, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(3, 1, NULL, 'Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', NULL, 'violet', 30, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(4, 1, NULL, 'Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', NULL, 'rose', 40, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12'),
(5, 1, NULL, 'Hors sujet', 'hors-sujet', 'Échanges informels.', NULL, 'emerald', 50, 0, NULL, '2026-03-13 19:23:12', '2026-03-13 19:23:12');

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
-- Structure de la table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `tenant_id`, `topic_id`, `user_id`, `body`, `is_hidden`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '# COMSPEC ATAK — Documentation produit\n\n**Version** : 1.0  \n**Public** : Utilisateurs, administrateurs, responsables technique  \n**Style** : Présentation produit, configuration et technique opérationnelle (sans détail d’implémentation)\n\n---\n\n## 1. Présentation du produit\n\n### 1.1 Qu’est-ce que COMSPEC ATAK ?\n\n**COMSPEC ATAK** (ou **ATAK / Tacmap**) est le module de **carte tactique temps réel** et de **liaison terrain–commandement** de la plateforme COMSPEC. Il permet de visualiser sur un navigateur la situation sur le théâtre d’opérations (positions des unités, marqueurs, messages, appuis air, etc.) et de rester synchronisé avec les opérateurs en jeu (Arma 3) grâce au mod **COMSPEC Overwatch**.\n\nEn résumé :\n\n- **Côté navigateur** : une **carte tactique** (Tacmap) affiche unités, marqueurs, tchat, pings, flux photos, demandes CAS (9-Line), désignateurs, rapports SIGINT et assets aériens.\n- **Côté jeu** : le **mod Arma COMSPEC Overwatch** envoie la position des joueurs, les marqueurs, les photos (type CTAB) et d’autres renseignements vers le serveur, qui les redistribue à l’overlay.\n\nL’ensemble forme un **système de commandement et contrôle (C2)** léger : le commandement suit la situation sur l’overlay ; les équipes en mission voient leurs actions reflétées en temps réel sur la carte.\n\n### 1.2 Objectifs opérationnels\n\n- **Situations tactiques partagées** : une seule vue carte pour tous les acteurs autorisés (même théâtre, même mission).\n- **Liaison Arma ↔ site** : les positions et événements issus du jeu remontent automatiquement vers l’overlay après configuration du mod.\n- **Coordination** : tchat, pings, photos intel, 9-Line CAS, codes laser, désignateurs et rapports SIGINT pour coordonner les appuis et le renseignement.\n- **Multi-théâtres** : plusieurs cartes (ex. Altis, Tanoa) et contextes (serveurs / missions) peuvent être proposés selon la configuration de l’équipe.\n\n### 1.3 Utilisateurs types\n\n| Rôle | Usage principal |\n|------|------------------|\n| **Opérateur terrain** | Joue avec le mod Arma ; sa position et ses actions (marqueurs, intel) apparaissent sur la Tacmap. |\n| **Commandement / overwatch** | Consulte la carte ATAK (ou Overwatch) pour suivre les unités, le tchat, les pings et les demandes CAS. |\n| **JTAC / contrôleur aérien** | Crée des 9-Line, gère les codes laser et les cibles désignateur ; les pilotes déclarent leurs assets (Flight Manifest). |\n| **Administrateur d’équipe** | Configure l’adresse du serveur C2, la carte par défaut, les identifiants mod, les instructions et le pack mod à télécharger. |\n\n### 1.4 Fonctionnalités principales (côté overlay)\n\n- **Carte tactique** : fond de carte type théâtre Arma (ex. Altis), avec coordonnées alignées sur le monde jeu. Zoom, pan, changement de carte ou de contexte (serveur / mission) selon configuration.\n- **Unités (contacts)** : liste des contacts connectés avec indicatif ; affichage sur la carte (position en temps réel). Filtres « Live » / « All » et recherche par indicatif.\n- **Cams / Intel photos** : flux des photos envoyées depuis le jeu (ex. captures type CTAB) ; consultation dans l’onglet dédié.\n- **Tchat** : messagerie partagée sur le théâtre actif ; échange entre overwatch et terrain.\n- **Pings** : alertes ou marqueurs rapides partagés (position + message) ; liste dans un onglet dédié.\n- **JTAC** : création et suivi des demandes **9-Line CAS** (type, position, élévation, cible, marqueur, ami/ennemi, retrait, autres, remarques) ; gestion des **codes laser** ; liste des demandes et statut.\n- **Air Support Assets** : liste des aéronefs déclarés par les pilotes (Flight Manifest depuis le menu Arma) ; statut pilote et liaison avec les 9-Line si l’équipe l’utilise.\n- **Marqueurs et formes** : marqueurs tactiques sur la carte ; formes (zones, axes) selon les capacités déployées.\n- **Désignateur** : position des cibles désignées au laser (JTAC) pour visualisation commandement.\n- **SIGINT** : rapports de renseignement d’origine électromagnétique (zones, émissions) ; affichage selon configuration.\n- **Heure Zulu** : affichage de l’heure UTC (Z) dans l’en-tête.\n- **État de santé** : section dépliable pour vérifier la disponibilité des services (connexion, dernière activité Arma, nombre d’unités, erreurs éventuelles).\n\n### 1.5 Mod Arma COMSPEC Overwatch\n\nLe mod fournit la **liaison jeu → serveur** :\n\n- **Connexion** : au chargement de la mission, le mod se connecte au serveur C2 (adresse configurée dans les paramètres CBA → COMSPEC Overwatch).\n- **Position** : envoi périodique de la position du joueur pour affichage sur la Tacmap.\n- **Marqueurs** : synchronisation des marqueurs (création, modification, suppression) entre le jeu et l’overlay.\n- **Intel / photos** : envoi de captures (ex. type CTAB) vers l’overlay pour partage avec le commandement.\n- **Autres** : selon version du mod et configuration (9-Line, désignateur, Flight Manifest, etc.).\n\n**Prérequis** : Arma 3 à jour, **CBA A3** (Community Base Addons). Le mod est fourni en pack téléchargeable (ex. lien sur le tableau de bord ou depuis l’admin) et doit être extrait puis activé dans le launcher.', 0, '2026-03-15 13:17:07', '2026-03-15 14:38:49'),
(2, 1, 1, 1, '## 2. Configuration\r\n\r\n### 2.1 Vue d’ensemble\r\n\r\nLa configuration ATAK est **par équipe** (tenant). Elle couvre :\r\n\r\n- La **carte par défaut** affichée sur l’overlay ATAK.\r\n- L’**URL de base du serveur C2** (optionnel ; si vide, le site courant est utilisé).\r\n- Le **secret JWT** (optionnel) pour la signature des jetons d’accès.\r\n- Les **informations serveur Arma** (adresse, port) affichées aux utilisateurs.\r\n- Les **identifiants ou paramètres mod** (texte libre) à communiquer aux opérateurs pour configurer le mod dans Arma.\r\n- Les **instructions équipe** (procédures, liens, rappels).\r\n\r\nSeuls les **administrateurs** accèdent à l’écran **Configuration ATAK / Arma**. Les opérateurs voient uniquement les informations que l’admin a choisies (ex. dans la section « Configuration pour le jeu » sur la page ATAK).\r\n\r\n### 2.2 Carte par défaut\r\n\r\n- L’administrateur choisit la **carte de l’overlay** pour l’équipe (ex. Altis, Tanoa).\r\n- Cette carte s’affiche par défaut à l’ouverture de la page ATAK ; l’utilisateur peut en changer si plusieurs cartes sont proposées.\r\n\r\n### 2.3 URL de base et secret JWT\r\n\r\n- **URL de base API ATAK** : en général, le C2 est servi par le même site (même origine). On ne renseigne une URL dédiée que si l’équipe utilise un domaine ou un port spécifique (ex. pour la DLL du mod Arma).\r\n- Pour le mod Arma, on configure en pratique **l’URL du site** (ex. `https://votre-domaine.fr`) dans les paramètres du mod (Paramètres → Addons → COMSPEC Overwatch → Connexion), pas une URL de « nœud » séparée, lorsque tout passe par le site.\r\n- **Secret JWT** : optionnel ; si renseigné, les jetons de cette équipe sont signés avec ce secret (sinon avec le secret global). À utiliser si l’équipe a besoin d’une clé dédiée.\r\n\r\n### 2.4 Serveur Arma 3\r\n\r\n- **Adresse du serveur** : hostname ou IP du serveur de jeu Arma 3 (affichée aux opérateurs pour information).\r\n- **Port** : port du serveur (ex. 2302). Ces informations permettent à l’équipe d’identifier le bon serveur et de vérifier la cohérence avec le mod.\r\n\r\n### 2.5 Identifiants / liaison mod Arma\r\n\r\n- Champ **texte libre** (identifiants, clé, paramètres à coller dans le mod).\r\n- Affiché aux opérateurs sur la page ATAK (section « Configuration pour le jeu ») pour qu’ils saisissent les mêmes valeurs dans Arma (Options → Jeu → Configurer les mods → COMSPEC Overwatch → Connexion).\r\n\r\n### 2.6 Instructions équipe\r\n\r\n- **Instructions** : texte libre pour procédures de connexion, liens utiles, rappels (ex. « Toujours vérifier l’indicatif Arma dans les préférences du compte »).\r\n- Visible sur la page ATAK selon la mise en page (ex. dans la zone « Configuration pour le jeu » ou « Instructions »).\r\n\r\n### 2.7 Mod ATAK (pack téléchargeable)\r\n\r\n- L’administrateur peut **déposer une version du mod** (fichier .zip, ex. COMSPEC Overwatch) depuis **Admin → Mod ATAK (upload)**.\r\n- Une fois le pack en place, un **lien de téléchargement** est proposé aux utilisateurs (tableau de bord, page ATAK ou assistant d’installation), pour qu’ils récupèrent toujours la version validée par l’équipe.\r\n\r\n### 2.8 Préférences utilisateur (liaison compte ↔ jeu)\r\n\r\nPour que l’overlay affiche correctement l’indicatif et le lien avec le compte :\r\n\r\n- **Indicatif** : renseigné dans le profil ou les préférences du compte.\r\n- **Liaison Steam** : optionnel ; identifiant Steam si utilisé pour l’authentification ou la corrélation.\r\n- **Indicatif Arma** : doit correspondre à l’indicatif utilisé en jeu pour que la liste des contacts et la carte associent la bonne identité.\r\n\r\nCes réglages se font dans **Mon compte** / **Préférences**, pas dans la configuration ATAK admin.\r\n\r\n---\r\n\r\n## 3. Utilisation opérationnelle\r\n\r\n### 3.1 Accéder à la carte ATAK\r\n\r\n- Depuis le **tableau de bord** : lien « ATAK / Tacmap ».\r\n- Depuis le menu principal : lien **ATAK**.\r\n- URL directe : `/atak` (après connexion).\r\n\r\nL’utilisateur doit être **connecté** et, le cas échéant, rattaché à une **équipe** pour voir la carte et les données du théâtre correspondant.\r\n\r\n### 3.2 Interface principale\r\n\r\n- **En-tête** : logo COMSPEC Overwatch, heure Zulu, indicateur « Réseau actif » (ou perte de connexion), sélecteur de serveur/mission, sélecteur de carte, liens Overwatch / Dashboard, bouton **Paramètres** (données compte, liaison Steam/Arma, lien vers config jeu).\r\n- **Panneau gauche** (onglets) :\r\n  - **Cams** : flux des photos intel envoyées depuis Arma.\r\n  - **Tchat** : messages partagés ; saisie et envoi de messages.\r\n  - **Pings** : liste des pings avec position et message.\r\n  - **JTAC** : bouton « Nouvelle 9-Line CAS », formulaire 9 lignes, liste des 9-Line, codes laser.\r\n- **Carte** : zone centrale ; affichage des unités, marqueurs, formes, désignateur, etc. Interaction au clic (info, déplacement de vue).\r\n- **Panneau droit** :\r\n  - **Air Support Assets** : liste des aéronefs déclarés (Flight Manifest).\r\n  - **Contacts (All Workspaces)** : liste des unités avec filtre et mode Live / All.', 0, '2026-03-15 13:17:15', '2026-03-15 13:17:15');

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
  `status` varchar(20) DEFAULT 'pending',
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
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
  `is_hidden` tinyint(1) DEFAULT 0,
  `view_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `forum_topics`
--

INSERT INTO `forum_topics` (`id`, `tenant_id`, `category_id`, `user_id`, `title`, `slug`, `is_pinned`, `is_locked`, `is_archived`, `is_hidden`, `view_count`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 1, 'ATAK - Mise en place', 'atak-mise-en-place-339664', 0, 0, 0, 0, 76, '2026-03-15 13:17:07', '2026-04-04 14:51:18');

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
(12, 2, 1, 'COL', 'Colonel', 'Colonel', 'O-6', 16, 1, 1, '2026-03-15 12:43:17', '2026-03-15 12:43:17', NULL);

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
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `tenant_id`, `name`, `slug`, `module`, `created_at`) VALUES
(1, 1, 'Voir le forum', 'forum.view', 'forum', '2026-03-13 19:23:12'),
(2, 1, 'Créer un sujet', 'forum.create_topic', 'forum', '2026-03-13 19:23:12'),
(3, 1, 'Répondre', 'forum.reply', 'forum', '2026-03-13 19:23:12'),
(4, 1, 'Modifier son message', 'forum.edit_own', 'forum', '2026-03-13 19:23:12'),
(5, 1, 'Supprimer son message', 'forum.delete_own', 'forum', '2026-03-13 19:23:12'),
(6, 1, 'Modérer le forum', 'forum.moderate', 'forum', '2026-03-13 19:23:12'),
(7, 1, 'Gérer les catégories', 'forum.manage_categories', 'forum', '2026-03-13 19:23:12'),
(8, 1, 'Accès administration', 'admin.access', 'admin', '2026-03-13 22:57:32'),
(9, 1, 'Voir les documents', 'documents.view', 'documents', '2026-03-14 00:01:46'),
(10, 1, 'Uploader des documents', 'documents.upload', 'documents', '2026-03-14 00:01:46'),
(11, 1, 'Modifier les documents', 'documents.update', 'documents', '2026-03-14 00:01:46'),
(12, 1, 'Archiver les documents', 'documents.archive', 'documents', '2026-03-14 00:01:46'),
(13, 1, 'Télécharger documents sensibles', 'documents.download_sensitive', 'documents', '2026-03-14 00:01:46'),
(14, 1, 'Voir les formations', 'training.view', 'training', '2026-03-15 11:51:40'),
(15, 1, 'Gérer les formations', 'training.manage', 'training', '2026-03-15 11:51:40'),
(16, 1, 'Assigner des formations', 'training.assign', 'training', '2026-03-15 11:51:40'),
(17, 1, 'Administration système', 'admin.system', 'admin', '2026-03-15 12:02:28'),
(18, 1, 'Administration organisationnelle', 'admin.organization', 'admin', '2026-03-15 12:02:28'),
(19, 1, 'Voir le Bureau Courrier', 'courrier.view', 'courrier', '2026-03-15 12:43:17'),
(20, 1, 'Créer des documents courrier', 'courrier.create', 'courrier', '2026-03-15 12:43:17'),
(21, 1, 'Valider des documents', 'courrier.validate', 'courrier', '2026-03-15 12:43:17'),
(22, 1, 'Archiver des documents', 'courrier.archive', 'courrier', '2026-03-15 12:43:17');

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
(6, 1, 'Références / Notes', 'references-notes', 'Références et notes administratives', 60, '2026-03-13 19:23:12');

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
(1, 'ATH-00001', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-03-13 19:23:21', '2026-03-15 11:57:51');

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
  `primary_role` varchar(100) DEFAULT NULL,
  `secondary_role` varchar(100) DEFAULT NULL,
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

INSERT INTO `personnel_profiles` (`id`, `user_id`, `character_name`, `callsign`, `rank_display`, `primary_role`, `secondary_role`, `primary_unit_id`, `clearance_level`, `character_portrait_path`, `character_banner_path`, `blood_type`, `nationality`, `languages`, `enlistment_date`, `motto`, `readiness_score`, `command_notes`, `matricule_internal`, `clearance_reviewed_at`, `equipment_class`, `kit_assigned`, `radio_assigned`, `vehicle_authorized`, `weapon_specialty`, `deployable`, `created_at`, `updated_at`) VALUES
(1, 1, 'NewPI', 'E-10', NULL, 'Officier de commandement', 'Responsable de formations', 1, '', 'uploads/portraits/1_1773575991.png', NULL, NULL, NULL, NULL, '2026-03-15', NULL, 0, '', 'ATH-00001', NULL, '', '', '', '', '', 1, '2026-03-13 19:23:21', '2026-03-15 11:59:51');

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
(1, 1, 1, 'dashboard_visit', 'view', '2026-04-04 14:53:57');

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

--
-- Déchargement des données de la table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `user_id`, `code`, `created_at`) VALUES
(1, 1, 'YFTPZZ38JZ', '2026-04-04 14:25:06');

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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `slug`, `description`, `is_system`, `is_locked`, `created_at`) VALUES
(1, 1, 'Administrator', 'tenant_admin', 'Full access', 1, 0, '2026-03-13 17:47:31'),
(2, 1, 'Modérateur forum', 'forum_moderator', '', 1, 0, '2026-03-13 19:23:12'),
(3, 1, 'Membre', 'member', '', 1, 0, '2026-03-13 19:23:12'),
(4, 1, 'Officier', 'officer', 'Encadrement, organisations, équipes', 1, 0, '2026-03-14 00:01:46'),
(5, 1, 'Super Administrator', 'super_admin', 'Accès administration système et organisation', 1, 1, '2026-03-15 12:02:28');

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
(1, 2),
(2, 2),
(3, 2),
(1, 3),
(2, 3),
(3, 3),
(1, 4),
(2, 4),
(3, 4),
(1, 5),
(1, 6),
(2, 6),
(1, 7),
(1, 8),
(5, 8),
(1, 9),
(3, 9),
(4, 9),
(1, 10),
(4, 10),
(1, 11),
(4, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(5, 17),
(1, 18),
(5, 18),
(1, 19),
(3, 19),
(1, 20),
(1, 21),
(1, 22);

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
  `stripe_price_id_monthly` varchar(100) DEFAULT NULL,
  `stripe_price_id_yearly` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `slug`, `name`, `sort_order`, `features_json`, `stripe_price_id_monthly`, `stripe_price_id_yearly`, `created_at`) VALUES
(1, 'free', 'Gratuit', 10, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":false,\"max_members\":50,\"community_create\":true}', NULL, NULL, '2026-04-04 14:27:08'),
(2, 'standard', 'Standard', 20, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":true,\"max_members\":200,\"community_create\":true}', NULL, NULL, '2026-04-04 14:27:08'),
(3, 'pro', 'Pro', 30, '{\"forum\":true,\"documents\":true,\"training\":true,\"atak\":true,\"analytics\":true,\"events\":true,\"max_members\":2000,\"community_create\":true}', NULL, NULL, '2026-04-04 14:27:08');

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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `community_code`, `logo_url`, `settings`, `owner_user_id`, `plan_slug`, `stripe_customer_id`, `stripe_subscription_id`, `subscription_status`, `subscription_current_period_end`, `created_at`, `updated_at`) VALUES
(1, 'Default Organisation', 'default', NULL, NULL, NULL, NULL, 'free', NULL, NULL, 'none', NULL, '2026-03-13 17:47:31', '2026-03-13 17:47:31');

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
(1, 'http://athena.ttrd.fr:3001', 'Tt05032001_TETARD', '88.166.72.92', 2302, '', '', 'altis', '2026-03-14 10:15:18', '2026-03-14 10:55:59');

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
(1, 'ATH', '{prefix}-{seq:5}', 2, '2026-03-13 19:23:21');

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
  `lesson_type` enum('richtext','video','pdf','audio','scorm_like','checklist','external_link') NOT NULL DEFAULT 'richtext',
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `units`
--

INSERT INTO `units` (`id`, `tenant_id`, `parent_id`, `name`, `slug`, `type`, `code`, `commander_user_id`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Cerbere', 'cerbere', 'organization', NULL, 1, 0, '2026-03-13 19:43:43', '2026-03-13 19:43:43');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `nationality_code` varchar(10) DEFAULT NULL,
  `preferred_grade_format` enum('classic','otan','hybrid') NOT NULL DEFAULT 'classic',
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `callsign` varchar(50) DEFAULT NULL,
  `steam_id` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `grade_id` bigint(20) UNSIGNED DEFAULT NULL,
  `professional_category_code` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `email`, `nationality_code`, `preferred_grade_format`, `password_hash`, `display_name`, `callsign`, `steam_id`, `avatar_url`, `role_id`, `grade_id`, `professional_category_code`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'tetard.tanguy@gmail.com', NULL, 'classic', '$argon2id$v=19$m=65536,t=4,p=1$R1JUM1hSLnlEenRpL3Ayaw$712JHsttH+eD0iS7qfW+jE1zovq+HrXCMEBg8mRDXbQ', 'NewPI', 'ADMIN', '76561198267756457', 'uploads/avatars/1_1773430155.jpg', 1, NULL, NULL, 'active', '2026-04-04 14:18:47', '2026-03-13 17:47:31', '2026-03-14 00:03:18');

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
(1, 'Tanguy', 'TETARD', NULL, NULL, 'Europe/Paris', 'fr', 'E-10', NULL, '', NULL, '2026-03-13 19:29:32', '2026-03-14 00:03:18');

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
(1, 1, 1, 'Signature principale', '1/1_69b6c6dce8f8c7.62450757.png', 1, '2026-03-15 14:49:00');

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
-- Index pour les tables déchargées
--

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
  ADD KEY `fk_ce_creator` (`created_by_user_id`);

--
-- Index pour la table `community_event_rsvps`
--
ALTER TABLE `community_event_rsvps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `fk_rsvp_user` (`user_id`);

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
-- Index pour la table `enlistments`
--
ALTER TABLE `enlistments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id_status` (`tenant_id`,`status`);

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
  ADD KEY `forum_categories_min_role_id_fk` (`min_role_id`);

--
-- Index pour la table `forum_category_subscriptions`
--
ALTER TABLE `forum_category_subscriptions`
  ADD PRIMARY KEY (`user_id`,`category_id`),
  ADD KEY `forum_category_subscriptions_category_id_fk` (`category_id`);

--
-- Index pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `topic_created` (`topic_id`,`created_at`),
  ADD KEY `forum_posts_user_id_fk` (`user_id`);

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
-- Index pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `category_updated` (`category_id`,`updated_at`),
  ADD KEY `forum_topics_user_id_fk` (`user_id`);

--
-- Index pour la table `forum_topic_subscriptions`
--
ALTER TABLE `forum_topic_subscriptions`
  ADD PRIMARY KEY (`user_id`,`topic_id`),
  ADD KEY `forum_topic_subscriptions_topic_id_fk` (`topic_id`);

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
-- Index pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_subject` (`tenant_id`,`subject_user_id`),
  ADD KEY `fk_mc_subject` (`subject_user_id`),
  ADD KEY `fk_mc_opener` (`opened_by_user_id`);

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
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

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
  ADD KEY `personnel_profiles_primary_unit` (`primary_unit_id`);

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
  ADD KEY `tenant_id` (`tenant_id`);

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
-- Index pour la table `tenant_atak_config`
--
ALTER TABLE `tenant_atak_config`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Index pour la table `tenant_matricule_config`
--
ALTER TABLE `tenant_matricule_config`
  ADD PRIMARY KEY (`tenant_id`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Index pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `courrier_documents`
--
ALTER TABLE `courrier_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `courrier_document_versions`
--
ALTER TABLE `courrier_document_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_audit_log`
--
ALTER TABLE `document_audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `document_workflows`
--
ALTER TABLE `document_workflows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `enlistments`
--
ALTER TABLE `enlistments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `equipment_classes`
--
ALTER TABLE `equipment_classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `forum_reports`
--
ALTER TABLE `forum_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `personnel_admin_data`
--
ALTER TABLE `personnel_admin_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_admin_panels`
--
ALTER TABLE `personnel_admin_panels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `personnel_assignments`
--
ALTER TABLE `personnel_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT pour la table `platform_usage_events`
--
ALTER TABLE `platform_usage_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `recon_images`
--
ALTER TABLE `recon_images`
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_units`
--
ALTER TABLE `user_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- Contraintes pour la table `enlistments`
--
ALTER TABLE `enlistments`
  ADD CONSTRAINT `enlistments_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `equipment_classes`
--
ALTER TABLE `equipment_classes`
  ADD CONSTRAINT `equipment_classes_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `forum_categories_parent_id_fk` FOREIGN KEY (`parent_id`) REFERENCES `forum_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_categories_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_category_subscriptions`
--
ALTER TABLE `forum_category_subscriptions`
  ADD CONSTRAINT `forum_category_subscriptions_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_category_subscriptions_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `forum_posts_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Contraintes pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
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
-- Contraintes pour la table `moderation_cases`
--
ALTER TABLE `moderation_cases`
  ADD CONSTRAINT `fk_mc_opener` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_subject` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
-- Contraintes pour la table `personnel_media`
--
ALTER TABLE `personnel_media`
  ADD CONSTRAINT `personnel_media_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  ADD CONSTRAINT `personnel_profiles_primary_unit_fk` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `personnel_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Contraintes pour la table `tenant_atak_config`
--
ALTER TABLE `tenant_atak_config`
  ADD CONSTRAINT `tenant_atak_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tenant_matricule_config`
--
ALTER TABLE `tenant_matricule_config`
  ADD CONSTRAINT `tenant_matricule_config_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Contraintes pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_signatures`
--
ALTER TABLE `user_signatures`
  ADD CONSTRAINT `user_signatures_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_signatures_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
