-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 05 oct. 2026 à 14:30
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `project_manager`
--

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `name`, `contact_info`) VALUES
(1, 'ABC Company', 'abc@mail.com'),
(2, 'Tech Corp', 'tech@mail.com');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','urgent') DEFAULT 'info',
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `read_status`, `created_at`) VALUES
(1, 2, 'New task assigned: Design Homepage', 'info', 1, '2026-01-19 09:37:41'),
(3, 1, 'Task deadline approaching', 'warning', 1, '2026-01-19 17:47:15'),
(4, 1, 'Project delayed!', 'urgent', 1, '2026-01-19 17:47:15');

-- --------------------------------------------------------

--
-- Structure de la table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `status` enum('Starting','Running','Finishing','Completed') DEFAULT 'Starting',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `client_id`, `status`, `start_date`, `end_date`, `manager_id`) VALUES
(1, 'Website Redesign', 'Corporate website redesign', 1, 'Running', '2025-01-01', '2025-03-01', 1),
(2, 'project1', 'test', NULL, 'Starting', NULL, NULL, 1),
(8, 'khalil\'s project', 'yesss', NULL, 'Completed', NULL, NULL, 1),
(9, 'khalil\'s project', 'yesss', NULL, 'Completed', NULL, NULL, 1),
(10, 'stage de perfectionemment', 'i am 75%  ready', NULL, 'Running', NULL, NULL, 1),
(11, 'stage de perfectionemment', 'i am 75%  ready', NULL, 'Completed', NULL, NULL, 1),
(12, 'project aplha', 'aaaaa', NULL, 'Finishing', NULL, NULL, 1),
(15, 'projet ranim', 'this is ranim\'s networking ^project', NULL, 'Starting', NULL, NULL, 1),
(16, 'fatma\'s project', 'reseau', NULL, 'Running', NULL, NULL, 1),
(17, 'hiba project', 'fbbkjrg', NULL, 'Starting', NULL, NULL, 1),
(18, 'project7', 'test 7', NULL, 'Starting', NULL, NULL, 1),
(19, 's1', 'hfjkshfdskfjq', NULL, 'Starting', NULL, NULL, 1),
(20, 'test22', 'sfekjazbdjk', NULL, 'Running', NULL, NULL, 1),
(21, 'test10', 'uhhuhuh', NULL, 'Starting', NULL, NULL, 1),
(22, 'projet dali', 'jfggrehfgkejfhel', NULL, 'Starting', NULL, NULL, 1),
(23, 'projet wissal', 'fgjierhiogj', NULL, 'Starting', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `project_members`
--

CREATE TABLE `project_members` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `project_members`
--

INSERT INTO `project_members` (`project_id`, `user_id`) VALUES
(1, 1),
(1, 2);

-- --------------------------------------------------------

--
-- Structure de la table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Todo','In Progress','Done') DEFAULT 'Todo',
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `deadline` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `assigned_to`, `title`, `description`, `status`, `priority`, `deadline`, `created_by`) VALUES
(1, 1, 2, 'Design Homepage', 'Create modern homepage UI', 'Done', 'High', '2025-02-10', 1),
(2, 1, 2, 'task1', 'taskk', 'Done', 'Medium', '2026-01-26', 1),
(3, 12, 2, 'doingefhejgfherklf', 'mmmmm', 'Done', 'High', '2026-02-01', 1),
(4, 16, 4, 'lnvlkvflkvfdv', 'vjhbkjbl;jknl', 'Done', 'High', '2026-02-02', 1),
(5, 18, 4, 'test', 'test', 'In Progress', 'High', '2026-02-08', 1),
(6, 1, 5, 'task12', '', 'Todo', 'High', '2026-02-12', 1),
(7, 20, 2, 'task test ', 'this for the presentation', 'Todo', 'High', '2026-02-18', 1),
(8, 12, 2, 'premier', 'kgkj', 'Todo', 'High', '2026-04-08', 1),
(9, 23, 2, 'premiére', 'kekjtnh', 'Todo', 'Medium', '2026-05-06', 1);

-- --------------------------------------------------------

--
-- Structure de la table `task_comments`
--

CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `timesheets`
--

CREATE TABLE `timesheets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `billable` tinyint(1) DEFAULT 1,
  `work_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `timesheets`
--

INSERT INTO `timesheets` (`id`, `user_id`, `task_id`, `hours`, `billable`, `work_date`) VALUES
(1, 2, 1, 4.50, 1, '2025-02-01');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('manager','employee') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Project Manager', 'manager@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '2026-01-19 09:37:40'),
(2, 'Employee One', 'employee@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', '2026-01-19 09:37:40'),
(3, 'Mirna nafeti', 'mirna@email.com', '$2y$10$DO19DgWEG01a9spkfBWo5O9aRhHqw/XCUu7pPihmO9OHLCd7hNpUS', 'employee', '2026-01-21 11:36:24'),
(4, 'fatma', 'fatma@test.com', '$2y$10$73/05k095Fv8OpwAUb/2qOztcASHde29VJkohGnIeaezbgxNq/U56', 'employee', '2026-01-22 12:52:52'),
(5, 'Jhon', 'Jhon@gmail.com', '$2y$10$Ky03wO8vYxtv/84uECOev./ZXXXffKsfMBRhe3MbbFi47PbvpG0Ve', 'employee', '2026-02-10 17:01:11'),
(6, 'test12', 'test12@gmail.com', '$2y$10$35.AGXbmjUxKR2K/uF9PAuiF9noZ24A0rWGJq/X4T77.fw1ruYM/G', 'employee', '2026-02-16 17:49:52'),
(7, 'dali', 'dali@gmail.com', '$2y$10$tcgSf6ndHQ13a6n9AOn3quZgmjl9lP/7qz3oT2w.BLzeK0WKuBsGq', 'employee', '2026-04-07 11:07:25'),
(8, 'Wissal', 'Wissal@gmail.com', '$2y$10$d/uRzuePC9o7QxyKLY1d7OVGtxfkB0MEQWPRa33pGJ9XvWOZYE.5y', 'employee', '2026-05-05 09:58:04');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_read_status` (`read_status`),
  ADD KEY `idx_type` (`type`);

--
-- Index pour la table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_deadline` (`deadline`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Index pour la table `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `timesheets`
--
ALTER TABLE `timesheets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `timesheets`
--
ALTER TABLE `timesheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `task_comments`
--
ALTER TABLE `task_comments`
  ADD CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `timesheets`
--
ALTER TABLE `timesheets`
  ADD CONSTRAINT `timesheets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timesheets_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
