-- phpMyAdmin SQL Dump
-- version 4.4.15.5
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Nov 03, 2016 at 07:40 PM
-- Server version: 5.6.30
-- PHP Version: 5.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET GLOBAL log_bin_trust_function_creators = 1;



/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `<DBS>`
--

--
-- Functions
--
DROP FUNCTION IF EXISTS `SPLIT_STRING`;
DROP FUNCTION IF EXISTS `Nr_Twitter_Users`;

CREATE DEFINER=`<USR>`@`<SRVR>` FUNCTION `SPLIT_STRING`(str VARCHAR(255), delim VARCHAR(12), pos INT) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
DETERMINISTIC
RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos), LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1), delim, '');
CREATE DEFINER=`<USR>`@`<SRVR>` FUNCTION `Nr_Twitter_Users`(year_value INT) RETURNS int(11)
READS SQL DATA
DETERMINISTIC
BEGIN
   DECLARE twitter_users INT;
   IF year_value = 2006 THEN SET twitter_users = 0.1;
   ELSEIF year_value = 2007 THEN SET twitter_users = 1;
   ELSEIF year_value = 2008 THEN SET twitter_users = 6;
   ELSEIF year_value = 2009 THEN SET twitter_users = 18;
   ELSEIF year_value = 2010 THEN SET twitter_users = 43;
   ELSEIF year_value = 2011 THEN SET twitter_users = 92;
   ELSEIF year_value = 2012 THEN SET twitter_users = 160;
   ELSEIF year_value = 2013 THEN SET twitter_users = 224;
   ELSEIF year_value = 2014 THEN SET twitter_users = 275;
   ELSEIF year_value = 2015 THEN SET twitter_users = 305;
   ELSEIF year_value = 2016 THEN SET twitter_users = 315;
   ELSEIF year_value = 2017 THEN SET twitter_users = 330;
   ELSE SET twitter_users = 330;
   END IF;
   RETURN twitter_users;
 END;

-- --------------------------------------------------------

--
-- Table structure for table `1_empty_all_mentions`
--

CREATE TABLE IF NOT EXISTS `1_empty_all_mentions` (
  `tweet_id` bigint(20) unsigned DEFAULT NULL,
  `replies` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_screen_name` varchar(20) CHARACTER SET ascii DEFAULT NULL,
  `responses_to_tweeter` int(10) unsigned NOT NULL DEFAULT '0',
  `mentions_of_tweeter` int(10) unsigned NOT NULL DEFAULT '0',
  `mention1` int(10) unsigned NOT NULL DEFAULT '0',
  `mention2` int(10) unsigned NOT NULL DEFAULT '0',
  `mention3` int(10) unsigned NOT NULL DEFAULT '0',
  `mention4` int(10) unsigned NOT NULL DEFAULT '0',
  `mention5` int(10) unsigned NOT NULL DEFAULT '0',
  `mention6` int(10) unsigned NOT NULL DEFAULT '0',
  `mention7` int(10) unsigned NOT NULL DEFAULT '0',
  `mention8` int(10) unsigned NOT NULL DEFAULT '0',
  `mention9` int(10) unsigned NOT NULL DEFAULT '0',
  `mention10` int(10) unsigned NOT NULL DEFAULT '0',
  `mention11` int(10) unsigned NOT NULL DEFAULT '0',
  `mention12` int(10) unsigned NOT NULL DEFAULT '0',
  `mention13` int(10) unsigned NOT NULL DEFAULT '0',
  `mention14` int(10) unsigned NOT NULL DEFAULT '0',
  `mention15` int(10) unsigned NOT NULL DEFAULT '0',
  `mention16` int(10) unsigned NOT NULL DEFAULT '0',
  `mention17` int(10) unsigned NOT NULL DEFAULT '0',
  `mention18` int(10) unsigned NOT NULL DEFAULT '0',
  `mention19` int(10) unsigned NOT NULL DEFAULT '0',
  `mention20` int(10) unsigned NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `1_empty_all_mentions`
  ADD KEY `user_screen_name` (`user_screen_name`),
  ADD KEY `mentions_of_tweeter` (`mentions_of_tweeter`);
COMMIT;

-- --------------------------------------------------------

--
-- Table structure for table `1_empty_case`
--

CREATE TABLE IF NOT EXISTS `1_empty_case` (
  `index_on_page` int(10) unsigned NOT NULL DEFAULT '0',
  `tweet_id` bigint(10) unsigned NOT NULL,
  `tweet_permalink_path` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `in_reply_to_user` bigint(20) unsigned DEFAULT NULL,
  `full_source` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `in_reply_to_tweet` bigint(20) unsigned DEFAULT NULL,
  `quoted_tweet_id` bigint(20) unsigned DEFAULT NULL,
  `user_screen_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(10) unsigned NOT NULL,
  `user_name` tinytext,
  `user_location` tinytext,
  `user_timezone` tinytext,
  `user_lang` varchar(10) DEFAULT NULL,
  `user_bio` text,
  `user_image_url` text,
  `date_time` datetime DEFAULT NULL,
  `tweet_date` date DEFAULT NULL,
  `coordinates_long` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `coordinates_lat` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `country` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_fullname` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_type` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `raw_text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clear_text` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_verified` tinyint(1) DEFAULT NULL,
  `hashtags` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `responses_to_tweeter` int(10) unsigned DEFAULT NULL,
  `urls` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_mentions` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tweet_language` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filter_level` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_retweet` tinyint(1) NOT NULL DEFAULT '0',
  `is_quote` tinyint(1) NOT NULL DEFAULT '0',
  `is_reply` tinyint(1) NOT NULL DEFAULT '0',
  `is_referenced` tinyint(1) NOT NULL DEFAULT '0',
  `retweeted_tweet_id` bigint(20) unsigned DEFAULT NULL,
  `retweeted_user_id` bigint(20) unsigned DEFAULT NULL,
  `retweeter_ids` mediumtext,
  `is_message` tinyint(1) NOT NULL DEFAULT '0',
  `has_image` tinyint(1) NOT NULL DEFAULT '0',
  `media_link` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_video` tinyint(1) NOT NULL DEFAULT '0',
  `has_link` tinyint(1) NOT NULL DEFAULT '0',
  `links` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expanded_links` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retweets` int(10) unsigned NOT NULL DEFAULT '0',
  `quotes` int(10) unsigned NOT NULL DEFAULT '0',
  `favorites` int(10) unsigned DEFAULT NULL DEFAULT '0',
  `replies` int(10) unsigned DEFAULT NULL DEFAULT '0',
  `source` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mentions_of_tweeter` int(10) unsigned DEFAULT NULL,
  `context_annotations` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `possibly_sensitive` tinyint(1) DEFAULT NULL,
  `conversation_id` bigint(20) DEFAULT NULL,
  `withheld_copyright` tinyint(1) DEFAULT NULL,
  `withheld_in_countries` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `withheld_scope` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_protected_or_deleted` tinyint(1) DEFAULT NULL,
  `retweeter_api_cursor` int(11) NOT NULL DEFAULT '-1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE IF NOT EXISTS `cases` (
  `id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `top_only` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `include_retweets` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `query` varchar(2400) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_date` datetime DEFAULT NULL,
  `to_date` datetime DEFAULT NULL,
  `details` varchar(2400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `private` tinyint(1) NOT NULL DEFAULT '0',
  `last_process_started` datetime DEFAULT '0000-00-00 00:00:00',
  `last_process_updated` datetime DEFAULT '0000-00-00 00:00:00',
  `last_process_completed` datetime DEFAULT '0000-00-00 00:00:00',
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashtag_cloud` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flags` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE IF NOT EXISTS `members` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_str` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `members`
  ADD PRIMARY KEY(`email`),
  ADD INDEX(`email`);

-- --------------------------------------------------------

--
-- Table structure for table `1_empty_users`
--

CREATE TABLE IF NOT EXISTS `1_empty_users` (
  `user_id` bigint(10) unsigned NOT NULL DEFAULT '0',
  `user_screen_name` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_name` tinytext COLLATE utf8_unicode_ci,
  `user_lang` text COLLATE utf8_unicode_ci,
  `user_geo_enabled` tinyint(1) DEFAULT NULL,
  `user_location` text COLLATE utf8_unicode_ci,
  `user_timezone` tinytext COLLATE utf8_unicode_ci,
  `user_utc_offset` int(11) DEFAULT NULL,
  `user_tweets` bigint(20) unsigned DEFAULT NULL,
  `user_followers` int(10) unsigned DEFAULT NULL,
  `user_following` bigint(20) unsigned DEFAULT NULL,
  `user_friends` bigint(20) DEFAULT NULL,
  `user_favorites` int(10) unsigned DEFAULT NULL,
  `user_lists` bigint(20) unsigned DEFAULT NULL,
  `user_bio` text COLLATE utf8_unicode_ci,
  `user_verified` tinyint(1) DEFAULT NULL,
  `user_protected` tinyint(1) DEFAULT NULL,
  `user_withheld_in_countries` text COLLATE utf8_unicode_ci,
  `user_withheld_scope` text COLLATE utf8_unicode_ci,
  `user_created` datetime DEFAULT NULL,
  `user_image_url` text COLLATE utf8_unicode_ci,
  `user_url` text COLLATE utf8_unicode_ci,
  `restricted_to_public` tinyint(1) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT NULL,
  `is_suspended` tinyint(1) DEFAULT NULL,
  `item_updated_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `not_in_search_results` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `1_empty_user_mentions`
--

CREATE TABLE IF NOT EXISTS `1_empty_user_mentions` (
  `tweet_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `tweet_datetime` datetime DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_screen_name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_name` tinytext COLLATE utf8mb4_unicode_ci,
  `user_verified` tinyint(1) DEFAULT NULL,
  `in_response_to_tweet` bigint(11) unsigned DEFAULT NULL,
  `user_followers` int(10) unsigned DEFAULT NULL,
  `in_response_to_user_id` bigint(11) unsigned DEFAULT NULL,
  `in_response_to_user_screen_name` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_response_to_user_name` tinytext COLLATE utf8mb4_unicode_ci,
  `in_response_to_user_followers` int(10) unsigned DEFAULT NULL,
  `in_response_to_user_verified` tinyint(1) DEFAULT NULL,
  `responses_to_tweet` int(10) unsigned DEFAULT NULL,
  `responses_to_tweeter` int(10) unsigned DEFAULT NULL,
  `mention1` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention2` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention3` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention4` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention5` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention6` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention7` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention8` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention9` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention10` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention11` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention12` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention13` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention14` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention15` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention16` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention17` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention18` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention19` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mention20` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

--
-- Indexes for table `1_empty_case`
--
ALTER TABLE `1_empty_case`
  ADD UNIQUE KEY `tweet_id` (`tweet_id`),
  ADD KEY `tweet_id_2` (`tweet_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `retweeted_tweet_id` (`retweeted_tweet_id`),
  ADD KEY `retweeted_user_id` (`retweeted_user_id`),
  ADD KEY `quoted_tweet_id` (`quoted_tweet_id`),
  ADD KEY `retweets` (`retweets`),
  ADD KEY `user_screen_name` (`user_screen_name`),
  ADD KEY `responses_to_tweeter` (`responses_to_tweeter`,`replies`,`mentions_of_tweeter`),
  ADD KEY `in_reply_to_user` (`in_reply_to_user`);

--
-- Indexes for table `cases`
--
ALTER TABLE `cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `id_2` (`id`);

--
-- Indexes for table `1_empty_users`
--
ALTER TABLE `1_empty_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `user_screen_name` (`user_screen_name`);

--
-- Indexes for table `1_empty_user_mentions`
--
ALTER TABLE `1_empty_user_mentions`
  ADD PRIMARY KEY (`tweet_id`),
  ADD UNIQUE KEY `tweet_id` (`tweet_id`),
  ADD KEY `tweet_id_2` (`tweet_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `in_response_to_user_id` (`in_response_to_user_id`),
  ADD KEY `in_response_to_tweet` (`in_response_to_tweet`,`in_response_to_user_screen_name`,`responses_to_tweet`,`responses_to_tweeter`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
