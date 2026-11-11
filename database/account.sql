-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2024 at 01:49 PM
-- Server version: 10.3.16-MariaDB
-- PHP Version: 7.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `main`
--

-- --------------------------------------------------------

--
-- Table structure for table `banip`
--

CREATE TABLE `banIP` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `time` int(11) UNSIGNED NOT NULL,
  `blockTill` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
--
-- Table structure for table `clubMedals`
--

CREATE TABLE `clubMedals` (
  `id` int(11) NOT NULL,
  `worldId` varchar(255) NOT NULL,
  `nickname` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tribe` tinyint(1) UNSIGNED NOT NULL,
  `type` int(10) NOT NULL,
  `params` varchar(500) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL,
  `hidden` tinyint(1) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `email_blacklist`
--

CREATE TABLE `email_blacklist` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `gameServers`
--

CREATE TABLE `gameServers` (
  `id` int(11) NOT NULL,
  `worldId` varchar(100) NOT NULL,
  `speed` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `version` int(1) NOT NULL,
  `gameWorldUrl` varchar(500) NOT NULL,
  `startTime` int(10) UNSIGNED NOT NULL,
  `roundLength` int(10) UNSIGNED NOT NULL,
  `finished` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `registerClosed` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `activation` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `preregistration_key_only` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `hidden` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `promoted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `configFileLocation` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `goldProducts`
--

CREATE TABLE `goldProducts` (
  `goldProductId` int(11) NOT NULL,
  `goldProductName` varchar(100) NOT NULL,
  `goldProductGold` int(10) UNSIGNED NOT NULL,
  `goldProductPrice` decimal(10,2) UNSIGNED NOT NULL,
  `goldProductImageName` varchar(100) NOT NULL,
  `goldProductHasOffer` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `isBestSeller` tinyint(4) NOT NULL DEFAULT 0,
  `isBestValue` tinyint(4) NOT NULL DEFAULT 0,
  `isActive` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `goldProducts`
-- One inactive tier set priced in the operator's single configured currency
-- (see payments.local.php); the operator edits and activates it in the admin.
--

INSERT INTO `goldProducts` (`goldProductId`, `goldProductName`, `goldProductGold`, `goldProductPrice`, `goldProductImageName`, `goldProductHasOffer`, `isBestSeller`, `isBestValue`, `isActive`) VALUES
(1, 'Package A', 36, 1.99, 'package1.png', 0, 0, 0, 0),
(2, 'Package B', 114, 4.99, 'package2.png', 0, 0, 0, 0),
(3, 'Package C', 280, 9.99, 'package3.png', 0, 0, 0, 0),
(4, 'Package D', 680, 19.99, 'package4.png', 0, 1, 0, 0),
(5, 'Package E', 1815, 49.99, 'package5.png', 0, 0, 0, 0),
(6, 'Package F', 3750, 99.99, 'package6.png', 0, 0, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `infobox`
--

CREATE TABLE `infobox` (
  `id` int(11) UNSIGNED NOT NULL,
  `autoType` tinyint(1) NOT NULL DEFAULT 0,
  `params` text NOT NULL,
  `showFrom` int(10) UNSIGNED NOT NULL,
  `showTo` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mailServer`
--

CREATE TABLE `mailServer` (
  `id` int(11) NOT NULL,
  `toEmail` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `html` longtext NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `replyTo` varchar(254) NOT NULL DEFAULT '',
  `unsubscribeUrl` varchar(512) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `availableAt` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lockedAt` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `leaseToken` char(32) NOT NULL DEFAULT '',
  `lastError` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `pin` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `package_codes`
--

CREATE TABLE `package_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `isGift` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `used` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `passwordRecovery`
--

CREATE TABLE `passwordRecovery` (
  `id` int(10) UNSIGNED NOT NULL,
  `wid` int(10) UNSIGNED NOT NULL,
  `recoveryCode` varchar(255) NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `created` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `paymentConfig`
--

CREATE TABLE `paymentConfig` (
  `id` int(10) UNSIGNED NOT NULL,
  `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `offerFrom` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `offer` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `paymentOrder`
-- One row per checkout. `token` is the secret order reference sent to the
-- provider; `paymentRef` is the provider's payment id (Stripe payment intent,
-- PayPal capture) learned at payment time and used to match refunds/disputes.
--

CREATE TABLE `paymentOrder` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `worldUniqueId` int(11) NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `productId` int(11) NOT NULL,
  `gold` int(10) UNSIGNED NOT NULL,
  `amountMinor` int(10) UNSIGNED NOT NULL,
  `currency` char(3) NOT NULL,
  `provider` varchar(10) NOT NULL,
  `providerRef` varchar(100) DEFAULT NULL,
  `paymentRef` varchar(100) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `created` int(10) UNSIGNED NOT NULL,
  `updated` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `paymentEvent`
-- Every provider event ever received. UNIQUE(provider, eventId) is what makes
-- webhook redelivery a no-op. Raw payloads are never stored.
--

CREATE TABLE `paymentEvent` (
  `id` int(10) UNSIGNED NOT NULL,
  `provider` varchar(10) NOT NULL,
  `eventId` varchar(100) NOT NULL,
  `orderId` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(10) NOT NULL,
  `amountMinor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` char(3) NOT NULL DEFAULT '',
  `outcome` varchar(10) NOT NULL DEFAULT 'received',
  `detail` varchar(200) NOT NULL DEFAULT '',
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `paymentFlag`
-- Accounts whose purchase was refunded or disputed, for operator review.
--

CREATE TABLE `paymentFlag` (
  `id` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `worldUniqueId` int(11) NOT NULL,
  `orderId` int(10) UNSIGNED NOT NULL,
  `reason` varchar(10) NOT NULL,
  `goldDeducted` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `clearedAt` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `paymentVoucher`
--

CREATE TABLE `paymentVoucher` (
  `id` int(11) NOT NULL,
  `gold` int(10) UNSIGNED NOT NULL,
  `email` varchar(100) NOT NULL,
  `worldId` varchar(100) DEFAULT NULL,
  `player` varchar(100) DEFAULT NULL,
  `reason` varchar(50) DEFAULT NULL,
  `voucherCode` varchar(100) NOT NULL,
  `time` int(11) UNSIGNED NOT NULL,
  `used` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `usedTime` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `usedWorldId` varchar(350) DEFAULT NULL,
  `usedPlayer` varchar(100) DEFAULT NULL,
  `usedEmail` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `preregistration_keys`
--

CREATE TABLE `preregistration_keys` (
  `id` int(10) UNSIGNED NOT NULL,
  `worldId` varchar(20) NOT NULL,
  `pre_key` varchar(32) NOT NULL,
  `used` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banip`
--
ALTER TABLE `banIP`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`,`blockTill`);

--
--
-- Indexes for table `clubMedals`
--
ALTER TABLE `clubMedals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hidden` (`hidden`);

--
-- Indexes for table `email_blacklist`
--
ALTER TABLE `email_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `gameServers`
--
ALTER TABLE `gameServers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `search` (`finished`,`registerClosed`,`hidden`);

--
-- Indexes for table `goldProducts`
--
ALTER TABLE `goldProducts`
  ADD PRIMARY KEY (`goldProductId`);

--
-- Indexes for table `infobox`
--
ALTER TABLE `infobox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `search` (`showTo`);

--
-- Indexes for table `mailServer`
--
ALTER TABLE `mailServer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `priority` (`priority`),
  ADD KEY `mailQueueClaim` (`status`,`availableAt`,`lockedAt`,`priority`,`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `search` (`time`);

--
-- Indexes for table `package_codes`
--
ALTER TABLE `package_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `used_package` (`used`,`package_id`,`id`);

--
-- Indexes for table `passwordRecovery`
--
ALTER TABLE `passwordRecovery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recoveryCode` (`recoveryCode`),
  ADD KEY `uid_wid` (`uid`,`wid`);

--
-- Indexes for table `paymentConfig`
--
ALTER TABLE `paymentConfig`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paymentOrder`
--
ALTER TABLE `paymentOrder`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `provider_paymentRef` (`provider`,`paymentRef`),
  ADD KEY `uid` (`uid`),
  ADD KEY `status_created` (`status`,`created`);

--
-- Indexes for table `paymentEvent`
--
ALTER TABLE `paymentEvent`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_eventId` (`provider`,`eventId`),
  ADD KEY `orderId` (`orderId`);

--
-- Indexes for table `paymentFlag`
--
ALTER TABLE `paymentFlag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orderId` (`orderId`),
  ADD KEY `uid` (`uid`);

--
-- Indexes for table `paymentVoucher`
--
ALTER TABLE `paymentVoucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucherCode` (`voucherCode`),
  ADD KEY `email` (`email`),
  ADD KEY `used_time` (`used`,`time`);

--
-- Indexes for table `preregistration_keys`
--
ALTER TABLE `preregistration_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `worldId` (`worldId`,`pre_key`,`used`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banip`
--
ALTER TABLE `banIP`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
--
-- AUTO_INCREMENT for table `clubMedals`
--
ALTER TABLE `clubMedals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_blacklist`
--
ALTER TABLE `email_blacklist`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gameServers`
--
ALTER TABLE `gameServers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goldProducts`
--
ALTER TABLE `goldProducts`
  MODIFY `goldProductId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `infobox`
--
ALTER TABLE `infobox`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mailServer`
--
ALTER TABLE `mailServer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_codes`
--
ALTER TABLE `package_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `passwordRecovery`
--
ALTER TABLE `passwordRecovery`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentConfig`
--
ALTER TABLE `paymentConfig`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentOrder`
--
ALTER TABLE `paymentOrder`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentEvent`
--
ALTER TABLE `paymentEvent`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentFlag`
--
ALTER TABLE `paymentFlag`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymentVoucher`
--
ALTER TABLE `paymentVoucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `preregistration_keys`
--
ALTER TABLE `preregistration_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
