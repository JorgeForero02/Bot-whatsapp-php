-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: b1rubiph1crftroa3r5y-mysql.services.clever-cloud.com:3306
-- Tiempo de generación: 03-03-2026 a las 15:04:10
-- Versión del servidor: 8.0.22-13
-- Versión de PHP: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `b1rubiph1crftroa3r5y`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bot_credentials`
--

CREATE TABLE `bot_credentials` (
  `id` int NOT NULL,
  `whatsapp_phone_number_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `whatsapp_access_token` text COLLATE utf8mb4_unicode_ci,
  `whatsapp_app_secret` text COLLATE utf8mb4_unicode_ci,
  `whatsapp_verify_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `openai_api_key` text COLLATE utf8mb4_unicode_ci,
  `openai_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'gpt-3.5-turbo',
  `openai_embedding_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'text-embedding-ada-002',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bot_credentials`
--

INSERT INTO `bot_credentials` (`id`, `whatsapp_phone_number_id`, `whatsapp_access_token`, `whatsapp_app_secret`, `whatsapp_verify_token`, `openai_api_key`, `openai_model`, `openai_embedding_model`, `created_at`, `updated_at`) VALUES
(1, '945985608606607', 'FSZC8hRyRXMUdHouU9175kKht1ptDiuFSccdjeOhvMaFPMHCxVpyxFDaPtHWGLyH6FqQisAB/rqAB5YBn4TQ+c0EzqkLz+UqMB1WbqF9XcIG3gwqUAyoQH9aWWcZMcAPTsXarGNqYgBkRbamILX1YYekNyOfcIP2rSBM8RVQo+uteUZAe9h2bUqF6QmmNl0BbkJ7b/ehWMuvffUzsuS78Q9cr6tg51JFgH0CVPo0hiBpn/VulmTRNGY7MrScDPHr+Jb9FCyWFYyzFY+TcICkKEAoJrUjHiJjZhYCRFXG9Wg=', 'lMeT9gjFCD/uh6sW8KFBrqS9AY/0oGLy6DbB0dl+llV0tJmbpnisdX4zEQaf0pW8NdwHItGiyoK0VEShf20acg==', 'token', 'cGkmUsNd/rufNvh0aa3iEA+HBC/V7mOp4HMyqPmr6XDcMOZXdaCDwXweFNs1XkWbPEDzTp3NnZ9qz6QaX8+RMIg5rFj/c3X4yKhT1d1JA88BVjTo60agofiFrcePrk+GYOFsUCmOGqsO9zGrfWNXhNjLBl+iJ8C7bGNUFOqE7AEmfbeYWnNHFa1pKr62yGJrvGtJBy69iDrhuX5QkMEpiUtW1vYwMp4ls0F5zqZU59HOZKI9/h3e2UC0WCGD31Lg', 'gpt-3.5-turbo', 'text-embedding-ada-002', '2026-03-03 14:55:53', '2026-03-03 15:02:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendar_flow_state`
--

CREATE TABLE `calendar_flow_state` (
  `id` int NOT NULL,
  `user_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` int NOT NULL,
  `current_step` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extracted_date` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extracted_time` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extracted_service` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_events_json` text COLLATE utf8mb4_unicode_ci,
  `attempts` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendar_settings`
--

CREATE TABLE `calendar_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `calendar_settings`
--

INSERT INTO `calendar_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'timezone', 'America/Bogota', '2026-03-03 14:55:53'),
(2, 'default_duration_minutes', '60', '2026-03-03 14:55:53'),
(3, 'max_events_per_day', '10', '2026-03-03 14:55:53'),
(4, 'min_advance_hours', '1', '2026-03-03 14:55:53'),
(5, 'business_hours_monday', '{\"enabled\":true,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(6, 'business_hours_tuesday', '{\"enabled\":true,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(7, 'business_hours_wednesday', '{\"enabled\":true,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(8, 'business_hours_thursday', '{\"enabled\":true,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(9, 'business_hours_friday', '{\"enabled\":true,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(10, 'business_hours_saturday', '{\"enabled\":true,\"start\":\"10:00\",\"end\":\"14:00\"}', '2026-03-03 14:55:53'),
(11, 'business_hours_sunday', '{\"enabled\":false,\"start\":\"09:00\",\"end\":\"18:00\"}', '2026-03-03 14:55:53'),
(12, 'reminder_email_enabled', 'true', '2026-03-03 14:55:53'),
(13, 'reminder_email_minutes', '1440', '2026-03-03 14:55:53'),
(14, 'reminder_popup_enabled', 'true', '2026-03-03 14:55:53'),
(15, 'reminder_popup_minutes', '30', '2026-03-03 14:55:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `classic_flow_sessions`
--

CREATE TABLE `classic_flow_sessions` (
  `id` int NOT NULL,
  `user_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_node_id` int DEFAULT NULL,
  `attempts` int DEFAULT '0',
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversations`
--

CREATE TABLE `conversations` (
  `id` int NOT NULL,
  `phone_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','closed','pending_human') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `ai_enabled` tinyint(1) DEFAULT '1',
  `last_message_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_bot_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunk_count` int DEFAULT '0',
  `file_size` int NOT NULL,
  `file_hash` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `flow_nodes`
--

CREATE TABLE `flow_nodes` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_keywords` json NOT NULL,
  `message_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_node_id` int DEFAULT NULL,
  `is_root` tinyint(1) DEFAULT '0',
  `requires_calendar` tinyint(1) DEFAULT '0',
  `position_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `flow_options`
--

CREATE TABLE `flow_options` (
  `id` int NOT NULL,
  `node_id` int NOT NULL,
  `option_text` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_keywords` json NOT NULL,
  `next_node_id` int DEFAULT NULL,
  `position_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `google_oauth_credentials`
--

CREATE TABLE `google_oauth_credentials` (
  `id` int NOT NULL,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `client_secret` text COLLATE utf8mb4_unicode_ci,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `calendar_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `google_oauth_credentials`
--

INSERT INTO `google_oauth_credentials` (`id`, `client_id`, `client_secret`, `access_token`, `refresh_token`, `token_expires_at`, `calendar_id`, `created_at`, `updated_at`) VALUES
(1, '', NULL, NULL, NULL, NULL, '', '2026-03-03 14:55:53', '2026-03-03 14:55:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender_type` enum('user','bot','human') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `audio_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` enum('text','audio','image','video','document') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `context_used` text COLLATE utf8mb4_unicode_ci,
  `confidence_score` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `onboarding_progress`
--

CREATE TABLE `onboarding_progress` (
  `id` int NOT NULL,
  `step_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL,
  `is_completed` tinyint(1) DEFAULT '0',
  `is_skipped` tinyint(1) DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `onboarding_progress`
--

INSERT INTO `onboarding_progress` (`id`, `step_name`, `step_order`, `is_completed`, `is_skipped`, `completed_at`, `created_at`) VALUES
(1, 'whatsapp_credentials', 1, 1, 0, '2026-03-03 15:01:30', '2026-03-03 14:55:53'),
(2, 'openai_credentials', 2, 1, 0, '2026-03-03 15:02:02', '2026-03-03 14:55:53'),
(3, 'bot_personality', 3, 1, 0, '2026-03-03 15:02:15', '2026-03-03 14:55:53'),
(4, 'calendar_setup', 4, 0, 1, NULL, '2026-03-03 14:55:53'),
(5, 'flow_builder', 5, 0, 1, NULL, '2026-03-03 14:55:53'),
(6, 'test_connection', 6, 1, 0, '2026-03-03 15:02:31', '2026-03-03 14:55:53'),
(7, 'go_live', 7, 1, 0, '2026-03-03 15:02:36', '2026-03-03 14:55:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `query_embedding_cache`
--

CREATE TABLE `query_embedding_cache` (
  `query_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `embedding` mediumblob NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `hit_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_type` enum('text','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_type`, `setting_value`, `updated_at`) VALUES
(1, 'bot_name', 'text', 'Romeral', '2026-03-03 15:02:14'),
(2, 'bot_greeting', 'text', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?', '2026-03-03 14:55:53'),
(3, 'bot_fallback_message', 'text', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.', '2026-03-03 14:55:53'),
(4, 'human_handoff_enabled', 'boolean', 'true', '2026-03-03 14:55:53'),
(5, 'openai_status', 'text', 'active', '2026-03-03 14:55:53'),
(6, 'openai_last_error', 'text', '', '2026-03-03 14:55:53'),
(7, 'openai_error_timestamp', 'text', '', '2026-03-03 14:55:53'),
(8, 'system_prompt', 'text', 'Eres el asistente virtual de una tienda. Ayuda a los clientes con información sobre productos, precios, disponibilidad, envíos y devoluciones. Sé amable, directo y siempre ofrece alternativas si un producto no está disponible.', '2026-03-03 15:02:13'),
(9, 'bot_mode', 'text', 'ai', '2026-03-03 14:55:53'),
(10, 'context_messages_count', 'text', '5', '2026-03-03 14:55:53'),
(11, 'business_name', 'text', 'Mi Negocio', '2026-03-03 14:55:53'),
(12, 'timezone', 'text', 'America/Bogota', '2026-03-03 14:55:53'),
(13, 'welcome_message', 'text', 'Hola! Soy un asistente virtual. ¿En qué puedo ayudarte?', '2026-03-03 14:55:53'),
(14, 'fallback_message', 'text', 'Lo siento, no encontré información relevante. Un operador humano te atenderá pronto.', '2026-03-03 14:55:53'),
(15, 'calendar_enabled', 'boolean', 'false', '2026-03-03 14:55:53'),
(16, 'confidence_threshold', 'text', '0.7', '2026-03-03 14:55:53'),
(17, 'max_results', 'text', '5', '2026-03-03 14:55:53'),
(18, 'chunk_size', 'text', '1000', '2026-03-03 14:55:53'),
(19, 'auto_reply', 'boolean', 'true', '2026-03-03 14:55:53'),
(20, 'temperature', 'text', '0.7', '2026-03-03 14:55:53'),
(21, 'timeout', 'text', '30', '2026-03-03 14:55:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vectors`
--

CREATE TABLE `vectors` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `chunk_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunk_index` int NOT NULL,
  `embedding` blob NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bot_credentials`
--
ALTER TABLE `bot_credentials`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `calendar_flow_state`
--
ALTER TABLE `calendar_flow_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_phone` (`user_phone`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `conversation_id` (`conversation_id`);

--
-- Indices de la tabla `calendar_settings`
--
ALTER TABLE `calendar_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indices de la tabla `classic_flow_sessions`
--
ALTER TABLE `classic_flow_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_phone` (`user_phone`),
  ADD KEY `current_node_id` (`current_node_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indices de la tabla `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_phone` (`phone_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_message` (`last_message_at`);

--
-- Indices de la tabla `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_file_hash` (`file_hash`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indices de la tabla `flow_nodes`
--
ALTER TABLE `flow_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `next_node_id` (`next_node_id`),
  ADD KEY `idx_is_root` (`is_root`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_position` (`position_order`);

--
-- Indices de la tabla `flow_options`
--
ALTER TABLE `flow_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `next_node_id` (`next_node_id`),
  ADD KEY `idx_node` (`node_id`),
  ADD KEY `idx_position` (`position_order`);

--
-- Indices de la tabla `google_oauth_credentials`
--
ALTER TABLE `google_oauth_credentials`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_sender` (`sender_type`);

--
-- Indices de la tabla `onboarding_progress`
--
ALTER TABLE `onboarding_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `step_name` (`step_name`);

--
-- Indices de la tabla `query_embedding_cache`
--
ALTER TABLE `query_embedding_cache`
  ADD PRIMARY KEY (`query_hash`),
  ADD KEY `idx_last_used` (`last_used_at`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indices de la tabla `vectors`
--
ALTER TABLE `vectors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bot_credentials`
--
ALTER TABLE `bot_credentials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `calendar_flow_state`
--
ALTER TABLE `calendar_flow_state`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendar_settings`
--
ALTER TABLE `calendar_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `classic_flow_sessions`
--
ALTER TABLE `classic_flow_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `flow_nodes`
--
ALTER TABLE `flow_nodes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `flow_options`
--
ALTER TABLE `flow_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `google_oauth_credentials`
--
ALTER TABLE `google_oauth_credentials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `onboarding_progress`
--
ALTER TABLE `onboarding_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `vectors`
--
ALTER TABLE `vectors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `calendar_flow_state`
--
ALTER TABLE `calendar_flow_state`
  ADD CONSTRAINT `calendar_flow_state_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `classic_flow_sessions`
--
ALTER TABLE `classic_flow_sessions`
  ADD CONSTRAINT `classic_flow_sessions_ibfk_1` FOREIGN KEY (`current_node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `flow_nodes`
--
ALTER TABLE `flow_nodes`
  ADD CONSTRAINT `flow_nodes_ibfk_1` FOREIGN KEY (`next_node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `flow_options`
--
ALTER TABLE `flow_options`
  ADD CONSTRAINT `flow_options_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `flow_options_ibfk_2` FOREIGN KEY (`next_node_id`) REFERENCES `flow_nodes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vectors`
--
ALTER TABLE `vectors`
  ADD CONSTRAINT `vectors_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
