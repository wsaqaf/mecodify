<?php
// --- CONFIGURATION & SETUP ---
// Enable robust line detection for Mac/Excel CSVs
ini_set('auto_detect_line_endings', true);
ini_set('max_execution_time', 0); // Infinite execution time
ini_set('memory_limit', '2048M'); // Increase memory limit
date_default_timezone_set('UTC');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<div class="alert alert-danger m-4">Error: Missing or invalid table identifier.</div>');
}

require_once("configurations.php");
require_once("cloud.php");

$table_name = mysqli_real_escape_string($link, $_GET['id']);
$tweets_table = $table_name;
$table = $table_name;
$users_table = "users_" . $table_name;
$done = 0;

// --- LOGGING & UI HELPERS ---
function log_message($message, $type = 'info')
{
    $colors = [
        'info' => 'alert-info', 'success' => 'alert-success',
        'warning' => 'alert-warning', 'error' => 'alert-danger',
        'primary' => 'alert-primary', 'light' => 'alert-secondary'
    ];
    $class = $colors[$type] ?? 'alert-secondary';

    echo "<div class='alert $class py-2 mb-1 small shadow-sm'>$message</div>";
    debug_log(strip_tags($message));

    if (ob_get_level() > 0)
        ob_flush();
    flush();
}

function debug_log($message)
{
    $file = 'tmp/debug_log.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

function not_blank($var)
{
    return isset($var) && ($var || $var === 0);
}

// --- ROBUST CSV PARSER ---
function get_csv_delimiter($file)
{
    $handle = fopen($file, "r");
    $line = fgets($handle);
    fclose($handle);
    if (substr_count($line, ';') > substr_count($line, ','))
        return ';';
    return ',';
}

function clean_header($header)
{
    $bom = pack('H*', 'EFBBBF');
    $header = preg_replace("/^$bom/", '', $header);
    return trim($header, "\"\t\n\r\0\x0B ");
}

function insert_csv_into_db($file, $table, $link, $is_users_table = false)
{
    log_message("Starting import for table: <strong>$table</strong>", 'info');

    $handle = fopen($file, "r");
    if ($handle === false) {
        log_message("CRITICAL: Could not open file $file", 'error');
        exit;
    }

    $delimiter = get_csv_delimiter($file);
    $raw_columns = fgetcsv($handle, 0, $delimiter);
    if (!$raw_columns) {
        log_message("CRITICAL: CSV file appears empty or unreadable.", 'error');
        exit;
    }

    $columns = array_map(function ($col) use ($link) {
        $col = clean_header($col);
        return mysqli_real_escape_string($link, $col);
    }, $raw_columns);

    // Drop Old Table
    $link->query("DROP TABLE IF EXISTS `$table`");

    $sample_data = fgetcsv($handle, 0, $delimiter);
    if (!$sample_data) {
        log_message("Error: CSV has no data rows.", 'error');
        exit;
    }

    $column_definitions = [];
    $index_columns = [];
    $primary_key = $is_users_table ? "user_id" : "tweet_id";

    // --- SMART SCHEMA DEFINITION ---
    foreach ($columns as $index => $col) {
        if (empty($col))
            continue;

        $clean_col = strtolower($col);

        // Default to TEXT
        $col_type = "TEXT";

        // 1. Force VARCHAR(191) for keys/IDs/Usernames to allow indexing
        // 191 is the safe max length for utf8mb4 indexes
        if (
        strpos($clean_col, 'id') !== false ||
        strpos($clean_col, 'screen_name') !== false ||
        $clean_col === 'in_reply_to_user' ||
        $clean_col === 'in_reply_to_tweet' ||
        $clean_col === 'reply_to'
        ) {
            $col_type = "VARCHAR(191)";
        }
        // 2. Force LONGTEXT for content that might be huge
        elseif ($clean_col === 'user_mentions' || $clean_col === 'raw_text' || $clean_col === 'clear_text') {
            $col_type = "LONGTEXT";
        }

        $column_definitions[] = "`$col` $col_type";

        // 3. Only index if it's a safe type (VARCHAR/INT)
        // This PREVENTS the "BLOB/TEXT key without length" crash
        if ($col_type === "VARCHAR(191)" || strpos($col_type, "INT") !== false) {
            $index_columns[] = "`$col`";
        }
    }

    $create_sql = "CREATE TABLE `$table` (" . implode(",", $column_definitions);

    // Add Primary Key if exists
    if (in_array($primary_key, $columns)) {
        $create_sql .= ", PRIMARY KEY (`$primary_key`)";
    }

    // Force Collation
    $create_sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if (!$link->query($create_sql)) {
        log_message("SQL Error creating table: " . $link->error, 'error');
        exit;
    }
    log_message("Table `$table` created.", 'success');

    // Add individual indexes separately to avoid exceeding 3072-byte key length limit
    if (!empty($index_columns)) {
        foreach ($index_columns as $idx_col) {
            $idx_name = "idx_" . preg_replace('/[^a-z0-9_]/i', '', $idx_col);
            $idx_sql = "ALTER TABLE `$table` ADD INDEX $idx_name ($idx_col)";
            if (!$link->query($idx_sql)) {
                log_message("Warning: Could not create index on $idx_col: " . $link->error, 'warning');
            }
        }
        log_message("Individual indexes created.", 'success');
    }

    // Reset and Import Data
    fseek($handle, 0);
    fgetcsv($handle, 0, $delimiter); // Skip header

    $batch_size = 500;
    $count = 0;
    $values_batch = [];
    $col_names_sql = implode(",", array_map(fn($c) => "`$c`", $columns));

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($data) < count($columns))
            continue;

        $escaped_data = array_map(fn($val) => "'" . mysqli_real_escape_string($link, $val) . "'", $data);
        $values_batch[] = "(" . implode(",", $escaped_data) . ")";
        $count++;

        if (count($values_batch) >= $batch_size) {
            $sql = "INSERT IGNORE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
            if (!$link->query($sql))
                log_message("Insert Error: " . $link->error, 'error');
            $values_batch = [];
            if ($count % 1000 == 0)
                log_message("Imported $count rows...", 'light');
        }
    }

    if (!empty($values_batch)) {
        $sql = "INSERT IGNORE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
        $link->query($sql);
    }

    fclose($handle);
    log_message("✅ <strong>Finished:</strong> Imported $count records into `$table`.", 'success');
}

function get_hashtag_cloud($table)
{
    global $link;
    $query = "SELECT hashtags FROM $table where hashtags is not null";
    $result = $link->query($query);
    if (!$result || !$result->num_rows)
        return;

    $hash_cloud = "";
    while ($row = $result->fetch_assoc())
        $hash_cloud .= " " . $row['hashtags'];

    file_put_contents("tmp/cache/$table-hashcloud.html", "<html><meta http-equiv='content-type' content='text/html; charset=utf-8' />\n$hash_cloud</html>\n");

    $cloud = new PTagCloud(100);
    $cloud->addTagsFromText(trim($hash_cloud));
    $cloud->setWidth("900px");
    $temp = $link->real_escape_string($cloud->emitCloud());
    $link->query("UPDATE cases SET hashtag_cloud='$temp' where id='$table'");
}

// --- PROCESSING LOGIC ---

function tweeter_data($table)
{
    update_response_mentions();
    draw_network($table);
}

function update_response_mentions()
{
    global $table;
    global $link;
    $all_m = "user_all_mentions_" . "$table";
    $u_m = "user_mentions_" . $table;

    log_message("Analyzing interactions...", 'primary');

    // 1. Mark missing users
    $link->query("UPDATE `users_" . $table . "` SET `users_" . $table . "`.`not_in_search_results`=1 WHERE NOT EXISTS (SELECT 1 FROM `" . $table . "` WHERE `" . $table . "`.`user_screen_name`=`users_" . $table . "`.`user_screen_name`)");

    // 2. Mentions Table (All IDs are VARCHAR(191) to match)
    $link->query("DROP TABLE IF EXISTS $all_m");
    $create_all_m = "CREATE TABLE `$all_m` (
      `tweet_id` varchar(191) DEFAULT NULL,
      `replies` int UNSIGNED NOT NULL DEFAULT '0',
      `user_id` varchar(191) DEFAULT NULL,
      `user_screen_name` varchar(191) DEFAULT NULL,
      `responses_to_tweeter` int UNSIGNED NOT NULL DEFAULT '0',
      `mentions_of_tweeter` int UNSIGNED NOT NULL DEFAULT '0',
      `mention1` VARCHAR(191) DEFAULT NULL,
      `mention2` VARCHAR(191) DEFAULT NULL,
      `mention3` VARCHAR(191) DEFAULT NULL,
      `mention4` VARCHAR(191) DEFAULT NULL,
      `mention5` VARCHAR(191) DEFAULT NULL,
      `mention6` VARCHAR(191) DEFAULT NULL,
      `mention7` VARCHAR(191) DEFAULT NULL,
      `mention8` VARCHAR(191) DEFAULT NULL,
      `mention9` VARCHAR(191) DEFAULT NULL,
      `mention10` VARCHAR(191) DEFAULT NULL,
      `mention11` VARCHAR(191) DEFAULT NULL,
      `mention12` VARCHAR(191) DEFAULT NULL,
      `mention13` VARCHAR(191) DEFAULT NULL,
      `mention14` VARCHAR(191) DEFAULT NULL,
      `mention15` VARCHAR(191) DEFAULT NULL,
      `mention16` VARCHAR(191) DEFAULT NULL,
      `mention17` VARCHAR(191) DEFAULT NULL,
      `mention18` VARCHAR(191) DEFAULT NULL,
      `mention19` VARCHAR(191) DEFAULT NULL,
      `mention20` VARCHAR(191) DEFAULT NULL,
      UNIQUE KEY `user_screen_name` (`user_screen_name`),
      KEY `tweet_id` (`tweet_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $link->query($create_all_m);

    // 3. Insert Replies
    $query = "INSERT INTO $all_m (tweet_id,replies) (SELECT $table.in_reply_to_tweet, count($table.tweet_id) FROM $table WHERE ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.in_reply_to_tweet IS NOT NULL GROUP BY $table.in_reply_to_tweet ORDER BY count($table.tweet_id) DESC)";
    $link->query($query);

    $link->query("UPDATE IGNORE $all_m,$table SET $all_m.user_screen_name = LOWER($table.user_screen_name), $all_m.user_id = $table.user_id WHERE $all_m.tweet_id = $table.tweet_id");
    $link->query("UPDATE $all_m, $table SET $all_m.responses_to_tweeter=(SELECT count($table.tweet_id) FROM $table WHERE $table.in_reply_to_user IS NOT NULL AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $all_m.user_screen_name=LOWER($table.in_reply_to_user) GROUP BY $table.in_reply_to_user) WHERE $all_m.user_screen_name=LOWER($table.in_reply_to_user)");

    // 5. Parse Mentions
    $link->query("DROP TABLE IF EXISTS $u_m");
    $create_u_m = "CREATE TABLE `$u_m` (
      `tweet_id` varchar(191) NOT NULL,
      `tweet_datetime` datetime DEFAULT NULL,
      `user_id` varchar(191) DEFAULT NULL,
      `user_screen_name` varchar(191) NOT NULL,
      `user_name` tinytext,
      `user_verified` tinyint(1) DEFAULT NULL,
      `in_response_to_tweet` varchar(191) DEFAULT NULL,
      `in_response_to_user_id` varchar(191) DEFAULT NULL,
      `in_response_to_user_screen_name` varchar(191) DEFAULT NULL,
      `in_response_to_user_name` tinytext,
      `in_response_to_user_verified` tinyint(1) DEFAULT NULL,
      `in_response_to_user_followers` int UNSIGNED DEFAULT NULL,
      `user_followers` int UNSIGNED DEFAULT NULL,
      `mention1` varchar(191) DEFAULT NULL,
      `mention2` varchar(191) DEFAULT NULL,
      `mention3` varchar(191) DEFAULT NULL,
      `mention4` varchar(191) DEFAULT NULL,
      `mention5` varchar(191) DEFAULT NULL,
      `mention6` varchar(191) DEFAULT NULL,
      `mention7` varchar(191) DEFAULT NULL,
      `mention8` varchar(191) DEFAULT NULL,
      `mention9` varchar(191) DEFAULT NULL,
      `mention10` varchar(191) DEFAULT NULL,
      `mention11` varchar(191) DEFAULT NULL,
      `mention12` varchar(191) DEFAULT NULL,
      `mention13` varchar(191) DEFAULT NULL,
      `mention14` varchar(191) DEFAULT NULL,
      `mention15` varchar(191) DEFAULT NULL,
      `mention16` varchar(191) DEFAULT NULL,
      `mention17` varchar(191) DEFAULT NULL,
      `mention18` varchar(191) DEFAULT NULL,
      `mention19` varchar(191) DEFAULT NULL,
      `mention20` varchar(191) DEFAULT NULL,
      PRIMARY KEY (`tweet_id`),
      KEY `user_screen_name` (`user_screen_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $link->query($create_u_m);

    $link->query("DROP FUNCTION IF EXISTS SPLIT_STRING");
    $link->query("CREATE FUNCTION SPLIT_STRING(str VARCHAR(255), delim VARCHAR(12), pos INT) RETURNS VARCHAR(255) DETERMINISTIC RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos), LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1), delim, '')");

    // Check if user_mentions column is populated
    $check_mentions = $link->query("SELECT COUNT(*) as cnt FROM $table WHERE user_mentions IS NOT NULL AND user_mentions != '' AND user_mentions != '0'");
    $has_mentions_data = $check_mentions->fetch_assoc()['cnt'] > 0;

    if ($has_mentions_data) {
        // Original logic: Split user_mentions column
        $mentions_cols = "mention1";
        $mentions_split = "SPLIT_STRING($table.user_mentions, ' ', 1)";
        for ($i = 2; $i <= 20; $i++) {
            $mentions_cols .= ", mention$i";
            $mentions_split .= ",SPLIT_STRING($table.user_mentions, ' ', $i)";
        }

        $query = "INSERT INTO $u_m(tweet_id,user_id,user_screen_name, $mentions_cols) SELECT $table.tweet_id, $table.user_id, LOWER($table.user_screen_name), $mentions_split FROM $table WHERE $table.user_mentions IS NOT NULL OR ($table.in_reply_to_tweet IS NOT NULL AND $table.in_reply_to_user IS NOT NULL AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1))";
        $link->query($query);
    }
    else {
        // Fallback: Extract mentions from raw_text using PHP
        log_message("user_mentions column is empty. Extracting mentions from raw_text...", 'warning');

        $result = $link->query("SELECT tweet_id, user_id, user_screen_name, raw_text FROM $table WHERE raw_text LIKE '%@%' AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1)");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $raw_text = $row['raw_text'];
                // Extract @mentions using regex
                preg_match_all('/@(\w+)/', $raw_text, $matches);

                if (!empty($matches[1])) {
                    $mentions = array_unique($matches[1]); // Remove duplicates
                    $mentions = array_slice($mentions, 0, 20); // Limit to 20

                    $mention_values = array_fill(0, 20, 'NULL');
                    foreach ($mentions as $idx => $mention) {
                        $mention_values[$idx] = "'" . $link->real_escape_string('@' . $mention) . "'";
                    }

                    $insert = "INSERT INTO $u_m (tweet_id, user_id, user_screen_name, mention1, mention2, mention3, mention4, mention5, mention6, mention7, mention8, mention9, mention10, mention11, mention12, mention13, mention14, mention15, mention16, mention17, mention18, mention19, mention20) VALUES (
                        '" . $link->real_escape_string($row['tweet_id']) . "',
                        '" . $link->real_escape_string($row['user_id']) . "',
                        '" . $link->real_escape_string(strtolower($row['user_screen_name'])) . "',
                        " . implode(',', $mention_values) . "
                    )";
                    $link->query($insert);
                }
            }
        }

        log_message("Extracted mentions from raw_text for " . ($result ? $result->num_rows : 0) . " tweets.", 'success');
    }

    for ($i = 1; $i <= 20; $i++) {
        $query = "INSERT INTO `$all_m` (user_screen_name,mention$i) (SELECT SUBSTR($u_m.mention$i,2), count($u_m.tweet_id) AS counts FROM $u_m WHERE $u_m.mention$i<>'' GROUP BY $u_m.mention$i ORDER BY count($u_m.tweet_id) DESC) ON DUPLICATE KEY UPDATE $all_m.mention$i=VALUES(mention$i)";
        $link->query($query);
    }

    $sum_mentions = implode("+", array_map(fn($n) => "sum(mention$n)", range(1, 20)));
    $link->query("UPDATE $all_m r JOIN (SELECT user_screen_name, $sum_mentions as mt FROM $all_m GROUP BY user_screen_name) u ON r.user_screen_name=u.user_screen_name SET r.mentions_of_tweeter=u.mt");

    $link->query("UPDATE $table, $all_m SET $table.replies=$all_m.replies WHERE $table.tweet_id=$all_m.tweet_id AND $all_m.tweet_id IS NOT NULL");

    $link->query("UPDATE user_mentions_" . $table . ", $table SET user_mentions_" . $table . ".in_response_to_tweet=$table.in_reply_to_tweet, user_mentions_" . $table . ".in_response_to_user_screen_name=LOWER($table.in_reply_to_user) WHERE user_mentions_" . $table . ".tweet_id=$table.tweet_id");

    $link->query("UPDATE user_mentions_" . $table . ", users_" . $table . " SET user_mentions_" . $table . ".user_name=users_" . $table . ".user_name, user_mentions_" . $table . ".user_verified=users_" . $table . ".user_verified, user_mentions_" . $table . ".user_followers=users_" . $table . ".user_followers WHERE user_mentions_" . $table . ".user_id=users_" . $table . ".user_id");

    $link->query("UPDATE user_mentions_" . $table . ", users_" . $table . " SET user_mentions_" . $table . ".in_response_to_user_name=users_" . $table . ".user_name, user_mentions_" . $table . ".in_response_to_user_verified=users_" . $table . ".user_verified, user_mentions_" . $table . ".in_response_to_user_followers=users_" . $table . ".user_followers WHERE user_mentions_" . $table . ".in_response_to_user_screen_name=LOWER(users_" . $table . ".user_screen_name)");

    $link->query("UPDATE `user_mentions_" . $table . "`,`" . $table . "` SET `user_mentions_" . $table . "`.`tweet_datetime`=`" . $table . "`.`date_time` WHERE `user_mentions_" . $table . "`.`tweet_id`=`" . $table . "`.`tweet_id`");

    log_message("User mentions and replies updated.", 'success');
}

function update_kumu_files($table)
{
    global $link;
    $top_limit = array("1000", "5000", "10000");
    $max_kumu_size = 2000;

    // Ensure directories exist
    if (!is_dir('tmp'))
        mkdir('tmp', 0755, true);
    if (!is_dir('tmp/kumu'))
        mkdir('tmp/kumu', 0755, true);
    if (!is_dir('tmp/network'))
        mkdir('tmp/network', 0755, true);

    log_message("Generating Kumu Files...", 'primary');

    // --- 1. Top Tweets ---
    foreach ($top_limit as $toplimit) {
        $query = "SELECT LOWER($table.user_screen_name) as screen_name, $table.user_image_url, '' as profile_link, '' as tweet_type, $table.date_time, $table.raw_text, $table.tweet_permalink_path, $table.hashtags, $table.tweet_language, $table.source, $table.retweets, $table.quotes, $table.favorites, $table.replies, LOWER($table.user_mentions) as user_mentions, $table.user_name, $table.user_location, $table.user_lang, $table.user_bio, $table.user_verified, $table.in_reply_to_user, $table.is_retweet, $table.is_quote, $table.is_reply, $table.has_image, $table.has_video, $table.has_link, $table.location_name, $table.country FROM $table WHERE ($table.is_protected_or_deleted is null OR $table.is_protected_or_deleted<>1) and $table.date_time is not null ORDER BY retweets DESC";
        $result = $link->query($query);

        if ($result && $result->num_rows > 0) {
            $filename = "kumu_" . $table . "_top_tweets_" . $toplimit . ".csv";
            $fp = fopen("tmp/kumu/" . $filename, 'w');
            fputcsv($fp, array("Label", "Image", "Profile Link", "Type", "Date", "Tweet Text", "Tweet Link", "Tags", "Tweet Language", "Source", "Retweets", "Favorites", "Quotes", "Replies", "User Mentions", "User Full Name", "User Location", "User Language", "User Bio", "User Verified", "In Reply to User", "Is a Retweet", "Has an Image", "Has a Video", "Has a Link", "Media Link", "Other Links", "Tweeted From Location", "Tweeted from Country"));

            $ind = 0;
            while ($row = $result->fetch_assoc()) {
                if ($ind == $toplimit)
                    break;
                $row['profile_link'] = "https://twitter.com/" . $row['screen_name'];
                $row['tweet_type'] = $row['is_retweet'] ? "Retweet" : ($row['is_reply'] ? "Tweet with reply" : "Regular tweet");
                $row['raw_text'] = preg_replace("/[\r\n]+/", " ", $row['raw_text']);
                fputcsv($fp, $row);
                $ind++;
            }
            fclose($fp);
            log_message("Saved: <a href='tmp/kumu/$filename' target='_blank' class='fw-bold'>$filename</a>", 'light');
        }
    }

    $valid_users = array();
    $result = $link->query("SELECT LOWER(user_screen_name) FROM users_" . $table);
    while ($row = $result->fetch_array()) {
        $valid_users[$row[0]] = 1;
    }

    $all_users_to_graph = array();

    // --- 2. Responses ---
    $t = "user_mentions_" . $table;
    $query = "SELECT LOWER($t.user_screen_name) as screen_name, LOWER($t.in_response_to_user_screen_name) as response_screen_name, '' as tweet_type, $t.in_response_to_tweet, $table.is_retweet, $table.is_quote, $table.is_reply, $t.tweet_datetime, $table.tweet_permalink_path, $table.user_verified, $table.has_image, $table.has_video, $table.has_link, $table.media_link, $table.expanded_links, $table.source, $table.location_name, $table.country, $table.tweet_language, $table.raw_text, $table.hashtags, $table.user_mentions, $table.retweets, $table.quotes, $table.replies, $table.favorites, $t.tweet_id FROM $t, $table WHERE $t.in_response_to_user_screen_name IS NOT NULL AND $t.user_screen_name IS NOT NULL AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.date_time IS NOT NULL AND $t.tweet_id=$table.tweet_id ORDER BY $table.retweets DESC";

    $result = $link->query($query);
    if ($result && $result->num_rows > 0) {
        $filename = "kumu_" . $table . "_responses.csv";
        $fp = fopen("tmp/kumu/" . $filename, 'w');
        $header = array("From", "To", "Type", "Date", "Link", "From_Verified_User", "Is_Image", "Is_Video", "Is_Link", "Media_Link", "Other_Links", "Source", "Location", "Language", "Content", "Tags", "Mentions", "Retweets", "Quotes", "Replies", "Favorites", "Tweet_ID");
        fputcsv($fp, $header);

        $indx = 0;
        while ($row = $result->fetch_assoc()) {
            if (!isset($valid_users[$row['screen_name']]))
                continue;

            // Fix: Strict check for To field
            if (!trim($row['response_screen_name']))
                continue;

            $all_users_to_graph[$row['screen_name']] = 1;
            $all_users_to_graph[$row['response_screen_name']] = 1;

            $csv_row = array(
                ltrim($row['screen_name'], '@'),
                ltrim($row['response_screen_name'], '@'),
                $row['is_retweet'] ? "Retweet" : ($row['is_reply'] ? "Reply to tweet" : "Regular tweet"),
                $row['tweet_datetime'],
                $row['tweet_permalink_path'],
                $row['user_verified'],
                $row['has_image'],
                $row['has_video'],
                $row['has_link'],
                $row['media_link'],
                $row['expanded_links'],
                $row['source'],
                trim($row['location_name'] . " " . $row['country']),
                $row['tweet_language'],
                str_replace("\"", "'", preg_replace("/[\r\n]+/", " ", $row['raw_text'])),
                $row['hashtags'],
                $row['user_mentions'],
                $row['retweets'],
                $row['quotes'],
                $row['replies'],
                $row['favorites'],
                $row['tweet_id']
            );
            fputcsv($fp, $csv_row);
            $indx++;
            if ($indx >= $max_kumu_size)
                break;
        }
        fclose($fp);
        log_message("Saved: <a href='tmp/kumu/$filename' target='_blank' class='fw-bold'>$filename</a>", 'light');
    }

    // --- 3. Mentions ---
    $mentions_sql = "mention1";
    for ($i = 2; $i <= 20; $i++) {
        $mentions_sql .= ",$t.mention$i";
    }
    $query = "SELECT LOWER($t.user_screen_name) as screen_name, $mentions_sql, $t.tweet_datetime, $table.is_retweet, $table.is_quote, $table.is_reply, $table.tweet_permalink_path, $table.user_verified, $table.has_image, $table.has_video, $table.has_link, $table.media_link, $table.expanded_links, $table.source, $table.location_name, $table.country, $table.tweet_language, $table.raw_text, $table.hashtags, $table.user_mentions, $table.retweets, $table.quotes, $table.replies, $table.favorites, $t.tweet_id FROM $t,$table WHERE $t.mention1>'' AND $t.user_screen_name IS NOT NULL AND $t.tweet_id=$table.tweet_id AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.date_time IS NOT NULL ORDER BY $table.retweets DESC";

    $result = $link->query($query);
    if ($result && $result->num_rows > 0) {
        $filename = "kumu_" . $table . "_mentions.csv";
        $fp = fopen("tmp/kumu/" . $filename, 'w');
        $header = array("From", "To", "Type", "Date", "Position", "Link", "From_Verified_User", "Is_Image", "Is_Video", "Is_Link", "Media_Link", "Other_Links", "Source", "Location", "Language", "Content", "Tags", "Mentions", "Retweets", "Quotes", "Replies", "Favorites", "Tweet_ID");
        fputcsv($fp, $header);

        $indx = 0;
        while ($row = $result->fetch_assoc()) {
            if (!isset($valid_users[$row['screen_name']]))
                continue;
            $all_users_to_graph[$row['screen_name']] = 1;

            $base_data = array(
                'From' => ltrim($row['screen_name'], '@'),
                'Date' => $row['tweet_datetime'],
                'Link' => $row['tweet_permalink_path'],
                'From_Verified_User' => $row['user_verified'],
                'Is_Image' => $row['has_image'],
                'Is_Video' => $row['has_video'],
                'Is_Link' => $row['has_link'],
                'Media_Link' => $row['media_link'],
                'Other_Links' => $row['expanded_links'],
                'Source' => $row['source'],
                'Location' => trim($row['location_name'] . " " . $row['country']),
                'Language' => $row['tweet_language'],
                'Content' => str_replace("\"", "'", preg_replace("/[\r\n]+/", " ", $row['raw_text'])),
                'Tags' => $row['hashtags'],
                'Mentions' => $row['user_mentions'],
                'Retweets' => $row['retweets'],
                'Quotes' => $row['quotes'],
                'Replies' => $row['replies'],
                'Favorites' => $row['favorites'],
                'Tweet_ID' => $row['tweet_id']
            );

            for ($i = 1; $i <= 20; $i++) {
                $mention_col = "mention$i";
                if (empty($row[$mention_col]))
                    break;

                $mentioned_user = ltrim($row[$mention_col], '@');

                // Only include if the mentioned user exists in our dataset
                if (!isset($valid_users[$mentioned_user]))
                    continue;

                $all_users_to_graph[$mentioned_user] = 1;

                $csv_row = array(
                    $base_data['From'], $mentioned_user, "mention", $base_data['Date'], $i,
                    $base_data['Link'], $base_data['From_Verified_User'], $base_data['Is_Image'],
                    $base_data['Is_Video'], $base_data['Is_Link'], $base_data['Media_Link'],
                    $base_data['Other_Links'], $base_data['Source'], $base_data['Location'],
                    $base_data['Language'], $base_data['Content'], $base_data['Tags'],
                    $base_data['Mentions'], $base_data['Retweets'], $base_data['Quotes'],
                    $base_data['Replies'], $base_data['Favorites'], $base_data['Tweet_ID']
                );
                fputcsv($fp, $csv_row);
            }
            $indx++;
            if ($indx >= $max_kumu_size)
                break;
        }
        fclose($fp);
        log_message("Saved: <a href='tmp/kumu/$filename' target='_blank' class='fw-bold'>$filename</a>", 'light');
    }

    // --- 4. Users ---
    $filename = "kumu_" . $table . "_users.csv";
    $fp = fopen("tmp/kumu/" . $filename, 'w');
    $header = array("Label", "Image", "User Verified", "Link", "Bio", "Language", "Location", "Tweets", "Followers", "Following", "Favorites", "Lists", "Created Date");
    fputcsv($fp, $header);

    $query = "SELECT LOWER(user_screen_name), user_image_url, user_verified, user_url, user_bio, user_lang, user_location, user_tweets, user_followers, user_following, user_favorites, user_lists, user_created FROM users_" . $table;
    $result = $link->query($query);

    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        if (!isset($all_users_to_graph[$row[0]]))
            continue;

        $screen_name = $row[0];
        $profile_link = "https://twitter.com/" . $screen_name;

        $csv_row = array(
            $screen_name, $row[1], $row[2], $profile_link,
            str_replace("\"", "'", preg_replace("/[\r\n]+/", " ", $row[4])),
            $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]
        );
        fputcsv($fp, $csv_row);
        unset($all_users_to_graph[$screen_name]);
    }

    // Add ghosts
    foreach ($all_users_to_graph as $user => $val) {
        if (empty($user))
            continue;
        $profile_link = "https://twitter.com/" . $user;
        $csv_row = array($user, "", "", $profile_link, "", "", "", "", "", "", "", "", "");
        fputcsv($fp, $csv_row);
    }

    fclose($fp);
    log_message("Saved: <a href='tmp/kumu/$filename' target='_blank' class='fw-bold'>$filename</a>", 'light');
}

// --- NETWORK DRAWING LOGIC ---
function draw_network($table)
{
    global $link;
    update_kumu_files($table);
    log_message("Drawing Network Graphs...", 'primary');

    $valid_screen_names = [];

    // Step 1: Users (Load valid users to array)
    $query = "SELECT user_screen_name FROM users_$table WHERE user_screen_name IS NOT NULL";
    $result = $link->query($query);
    if ($result) {
        while ($row = $result->fetch_array()) {
            $valid_screen_names[strtolower($row[0])] = 1;
        }
    }

    $edges = array_fill(0, 6, "source,target,value\n");
    $maximum_strength = 5;

    // Step 2: Replies
    $query = "SELECT LOWER(user_screen_name), LOWER(in_response_to_user_screen_name), count(tweet_id) FROM user_mentions_$table WHERE in_response_to_user_screen_name IS NOT NULL AND in_response_to_user_screen_name != '' GROUP BY concat(user_screen_name, ' ', in_response_to_user_screen_name) ORDER BY count(tweet_id) DESC";
    $result = $link->query($query);
    if ($result) {
        while ($row = $result->fetch_array()) {
            $source = $row[0];
            $target = $row[1];
            $count = $row[2];

            // Only skip if source is unknown, but include edge even if target is external
            if (!isset($valid_screen_names[$source]))
                continue;

            for ($i = $maximum_strength; $i > 0; $i--) {
                if ($count >= $i)
                    $edges[$i] .= "$source,$target,$count\n";
            }
        }
        for ($i = $maximum_strength; $i > 0; $i--) {
            file_put_contents("tmp/network/" . $table . "_" . $i . ".csv", $edges[$i]);
        }
        log_message("Step 2 (Replies): Generated network files.", 'info');
    }

    // Step 3: Mentions
    $edges = array_fill(0, 6, "source,target,value\n");
    $mentions_cols = "mention1";
    for ($k = 2; $k <= 20; $k++)
        $mentions_cols .= ",mention$k";

    $query = "SELECT LOWER(user_screen_name), $mentions_cols FROM user_mentions_$table WHERE mention1 IS NOT NULL AND mention1 != ''";
    $result = $link->query($query);

    if ($result) {
        $edge_counts = [];
        while ($row = $result->fetch_array()) {
            $source = $row[0];
            // Removed strict validation check here to fix blank files
            if (!isset($valid_screen_names[$source]))
                continue;

            for ($k = 1; $k <= 10; $k++) {
                // Row is indexed numerically: row[0]=user_screen_name, row[1]=mention1, row[2]=mention2, etc.
                if (empty($row[$k]))
                    break;

                $target = strtolower(ltrim($row[$k], '@'));
                // Skip empty targets after trimming
                if (empty($target))
                    continue;

                $key = "$source,$target";
                if (!isset($edge_counts[$key]))
                    $edge_counts[$key] = 0;
                $edge_counts[$key]++;
            }
        }
        foreach ($edge_counts as $key => $count) {
            for ($i = $maximum_strength; $i > 0; $i--) {
                if ($count >= $i)
                    $edges[$i] .= "$key,$count\n";
            }
        }
        for ($i = $maximum_strength; $i > 0; $i--) {
            file_put_contents("tmp/network/" . $table . "_mentions_" . $i . ".csv", $edges[$i]);
        }
        log_message("Step 3 (Mentions): Generated network files.", 'info');
    }

    update_cases_table("completed");
}

function update_cases_table($mode)
{
    global $table;
    global $link;
    global $done;

    $add_compl = ($mode == "started") ? ",last_process_completed=NULL" : "";
    $query = "update cases set last_process_$mode=NOW()$add_compl, status='$mode' where id='$table'";

    $link->query($query);

    if ($mode == "completed") {
        $done = 1;
        echo "<script>
            document.getElementById('processHeader').innerHTML = '<i class=\"bi bi-check-circle-fill\"></i> Processing Complete';
            document.getElementById('processHeader').classList.remove('bg-primary');
            document.getElementById('processHeader').classList.add('bg-success');
            if (window.scrollInterval) clearInterval(window.scrollInterval);
        </script>";

        echo "<div class='mt-4 text-center'>
                <h4 class='text-success'>✅ Process Completed Successfully!</h4>
                <p>You can now close this window and return to the main dashboard.</p>
                <button onclick='window.close()' class='btn btn-success btn-lg shadow'>Close Window</button>
              </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Upload & Processing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-card {
            max-width: 800px;
            margin: 50px auto;
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            padding: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #555;
        }

        /* Auto-expanding log */
        .log-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            min-height: 200px;
        }

        .btn-upload {
            background-color: #4f46e5;
            border-color: #4f46e5;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-upload:hover {
            background-color: #4338ca;
            border-color: #4338ca;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <div class="container">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['tweets_csv'])): ?>
        <div class="card main-card">
            <div id="processHeader" class="card-header bg-primary text-white">
                <i class="bi bi-gear-fill"></i> Processing Data...
            </div>
            <div class="card-body">
                <div class="log-container">
                    <script>
                        // Auto-scroll to bottom while processing
                        window.scrollInterval = setInterval(function () {
                            window.scrollTo(0, document.body.scrollHeight);
                        }, 200);
                    </script>
                    <?php
    // Start Processing Log
    log_message("Initiating upload process...", 'info');

    $tweets_file = $_FILES['tweets_csv']['tmp_name'];
    $users_file = $_FILES['users_csv']['tmp_name'];

    update_cases_table("started");

    log_message("Importing Tweets...", 'primary');
    insert_csv_into_db($tweets_file, $tweets_table, $link, false);

    log_message("Importing Users...", 'primary');
    insert_csv_into_db($users_file, $users_table, $link, true);

    get_hashtag_cloud($table_name);
    tweeter_data($table_name);
?>
                </div>
            </div>
        </div>
        <?php
else: ?>
        <div class="card main-card">
            <div class="card-header bg-white text-center">
                <h3 class="mb-0 text-primary">Upload Case Files</h3>
                <p class="text-muted small mt-2">Case ID: <strong>
                        <?php echo htmlspecialchars($table_name); ?>
                    </strong></p>
            </div>
            <div class="card-body p-4">
                <form action="csv_upload.php?id=<?php echo htmlspecialchars($table_name); ?>" method="post"
                    enctype="multipart/form-data">

                    <div class="mb-4">
                        <label for="tweets_csv" class="form-label">Tweets CSV File</label>
                        <input class="form-control form-control-lg" type="file" name="tweets_csv" id="tweets_csv"
                            accept=".csv" required>
                        <div class="form-text">Select the tweets dataset (test2_tweets.csv).</div>
                    </div>

                    <div class="mb-4">
                        <label for="users_csv" class="form-label">Users CSV File</label>
                        <input class="form-control form-control-lg" type="file" name="users_csv" id="users_csv"
                            accept=".csv" required>
                        <div class="form-text">Select the users dataset (test2_users.csv).</div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                            <path
                                d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                        </svg>
                        <div>
                            <strong>Warning:</strong> If a case with this ID exists, data will be overwritten.
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center mt-3" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-info-circle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                            <path
                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                        </svg>
                        <div>
                            <strong>Don't have the CSV files yet?</strong><br>
                            You can extract them instantly using the <a href="https://github.com/wsaqaf/xscraper"
                                target="_blank" class="alert-link">xscraper Chrome Extension</a>. Just install the
                            extension, navigate to the targeted X/Twitter page, scroll down to load tweets, and click
                            the scraper icon to download your datasets.
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-upload btn-primary text-white shadow-sm">
                            Upload and Process Files
                        </button>
                    </div>

                </form>
            </div>
        </div>
        <?php
endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>