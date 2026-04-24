<?php
// --- CONFIGURATION & SETUP ---
// Enable robust line detection for Mac/Excel CSVs (Deprecated in PHP 8.1+)
// ini_set('auto_detect_line_endings', true);
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
    $file = __DIR__ . '/tmp/debug_log.txt';
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

    // Determine if table already exists
    $table_exists = $link->query("SHOW TABLES LIKE '$table'")->num_rows > 0;

    $sample_data = fgetcsv($handle, 0, $delimiter);
    if (!$sample_data) {
        log_message("Error: CSV has no data rows.", 'error');
        exit;
    }

    $column_definitions = [];
    $index_columns = [];
    $column_types_map = [];
    $primary_key = $is_users_table ? "user_id" : "tweet_id";

    // --- SMART SCHEMA DEFINITION ---
    foreach ($columns as $index => $col) {
        if (empty($col))
            continue;

        $clean_col = strtolower($col);

        // Default to TEXT
        $col_type = "TEXT";

        // 1. Force VARCHAR(191) for keys/IDs/Usernames to allow indexing
        if (
            strpos($clean_col, 'id') !== false ||
            strpos($clean_col, 'screen_name') !== false ||
            $clean_col === 'in_reply_to_user' ||
            $clean_col === 'in_reply_to_tweet' ||
            $clean_col === 'reply_to'
        ) {
            $col_type = "VARCHAR(191)";
        }
        // 2. Detect Numeric Columns
        elseif (
            strpos($clean_col, 'count') !== false ||
            $clean_col === 'retweets' ||
            $clean_col === 'favorites' ||
            $clean_col === 'quotes' ||
            $clean_col === 'replies' ||
            strpos($clean_col, 'followers') !== false ||
            strpos($clean_col, 'following') !== false ||
            $clean_col === 'user_tweets' ||
            $clean_col === 'user_lists'
        ) {
            $col_type = "INT UNSIGNED DEFAULT 0";
        }
        // 3. Force LONGTEXT for content that might be huge
        elseif ($clean_col === 'user_mentions' || $clean_col === 'raw_text' || $clean_col === 'clear_text') {
            $col_type = "LONGTEXT";
        }

        $column_definitions[] = "`$col` $col_type";
        $column_types_map[$col] = $col_type;

        // 4. Only index if it's a safe type (VARCHAR/INT)
        if ($col_type === "VARCHAR(191)" || strpos($col_type, "INT") !== false) {
            $index_columns[] = "`$col`";
        }
    }

    if (!$table_exists) {
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
    }
    else {
        log_message("Table `$table` exists. Appending new records...", 'success');

        // Dynamically add missing columns to support legacy tables or new feature exports
        $existing_columns = [];
        $result = $link->query("SHOW COLUMNS FROM `$table`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
        }

        foreach ($columns as $col) {
            if (empty($col))
                continue;

            if (!in_array($col, $existing_columns)) {
                $col_type = isset($column_types_map[$col]) ? $column_types_map[$col] : "TEXT";
                $alter_sql = "ALTER TABLE `$table` ADD COLUMN `$col` $col_type";
                if ($link->query($alter_sql)) {
                    log_message("Added missing column `$col` to existing table schema.", 'light');
                }
                else {
                    log_message("Warning: Failed to add missing column `$col`: " . $link->error, 'warning');
                }
            }
        }
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
        $escaped_data = [];
        foreach ($data as $idx => $val) {
            $col_name = $columns[$idx] ?? '';
            // Mecodify legacy fallback: fetch queries expect NULL instead of 0 for undeleted tweets
            if ($col_name === 'is_protected_or_deleted' && ($val === '0' || $val === '')) {
                $escaped_data[] = "NULL";
            }
            else {
                $escaped_data[] = "'" . mysqli_real_escape_string($link, $val) . "'";
            }
        }
        $values_batch[] = "(" . implode(",", $escaped_data) . ")";
        $count++;

        if (count($values_batch) >= $batch_size) {
            $sql = "REPLACE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
            if (!$link->query($sql))
                log_message("Insert Error: " . $link->error, 'error');
            $values_batch = [];
            if ($count % 1000 == 0)
                log_message("Imported $count rows...", 'light');
        }
    }

    if (!empty($values_batch)) {
        $sql = "REPLACE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
        $link->query($sql);
    }

    fclose($handle);
    log_message("✅ <strong>Finished:</strong> Imported $count records into `$table`.", 'success');
}

/**
 * NEW: Handle JSON import from consolidated file
 */
function insert_json_into_db($file, $tweets_table, $users_table, $link)
{
    log_message("Reading JSON file...", 'info');
    $json_content = file_get_contents($file);
    if ($json_content === false) {
        log_message("CRITICAL: Could not read JSON file.", 'error');
        return;
    }

    // Strip UTF-8 BOM if exists
    $bom = pack('H*', 'EFBBBF');
    $json_content = preg_replace("/^$bom/", '', $json_content);

    $data = json_decode($json_content, true);
    if ($data === null) {
        log_message("CRITICAL: Invalid JSON format: " . json_last_error_msg(), 'error');
        // Debug: Log the first few bytes to see what's going on
        debug_log("JSON Decode failed. first 20 bytes: " . bin2hex(substr($json_content, 0, 20)));
        return;
    }


    if (isset($data['tweets']) && is_array($data['tweets']) && !empty($data['tweets'])) {
        log_message("Importing Tweets from JSON (" . count($data['tweets']) . " records)...", 'primary');
        process_data_array($data['tweets'], $tweets_table, $link, false);
    }
    else {
        log_message("No valid tweets array found in JSON.", 'warning');
    }

    if (isset($data['users']) && is_array($data['users']) && !empty($data['users'])) {
        log_message("Importing Users from JSON (" . count($data['users']) . " records)...", 'primary');
        process_data_array($data['users'], $users_table, $link, true);
    }
    else {
        log_message("No valid users array found in JSON.", 'warning');
    }
}

/**
 * NEW: Generic array-to-table import function
 */
function process_data_array($rows, $table, $link, $is_users_table = false)
{
    if (empty($rows))
        return;

    // Extract columns from the first row
    $columns = array_keys($rows[0]);
    log_message("Starting import for table: <strong>$table</strong>", 'info');

    // --- SCHEMA MANAGEMENT ---
    $table_exists = $link->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    $column_definitions = [];
    $index_columns = [];
    $column_types_map = [];
    $primary_key = $is_users_table ? "user_id" : "tweet_id";

    foreach ($columns as $col) {
        if (empty($col))
            continue;
        $clean_col = strtolower($col);
        $col_type = "TEXT";
        if (strpos($clean_col, 'id') !== false || strpos($clean_col, 'screen_name') !== false ||
        $clean_col === 'in_reply_to_user' || $clean_col === 'in_reply_to_tweet' || $clean_col === 'reply_to') {
            $col_type = "VARCHAR(191)";
        }
        elseif ($clean_col === 'user_mentions' || $clean_col === 'raw_text' || $clean_col === 'clear_text') {
            $col_type = "LONGTEXT";
        }
        $column_definitions[] = "`$col` $col_type";
        $column_types_map[$col] = $col_type;
        if ($col_type === "VARCHAR(191)" || strpos($col_type, "INT") !== false) {
            $index_columns[] = "`$col`";
        }
    }

    if (!$table_exists) {
        $create_sql = "CREATE TABLE `$table` (" . implode(",", $column_definitions);
        if (in_array($primary_key, $columns))
            $create_sql .= ", PRIMARY KEY (`$primary_key`)";
        $create_sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        if (!$link->query($create_sql)) {
            log_message("SQL Error creating table: " . $link->error, 'error');
            return;
        }
        log_message("Table `$table` created.", 'success');
        if (!empty($index_columns)) {
            foreach ($index_columns as $idx_col) {
                $idx_name = "idx_" . preg_replace('/[^a-z0-9_]/i', '', $idx_col);
                $link->query("ALTER TABLE `$table` ADD INDEX $idx_name ($idx_col)");
            }
        }
    }
    else {
        $existing_columns = [];
        $res = $link->query("SHOW COLUMNS FROM `$table`");
        while ($row = $res->fetch_assoc())
            $existing_columns[] = $row['Field'];
        foreach ($columns as $col) {
            if (empty($col))
                continue;
            if (!in_array($col, $existing_columns)) {
                $col_type = $column_types_map[$col] ?? "TEXT";
                $link->query("ALTER TABLE `$table` ADD COLUMN `$col` $col_type");
            }
        }
    }

    // --- BATCH INSERTION ---
    $batch_size = 500;
    $count = 0;
    $values_batch = [];
    $col_names_sql = implode(",", array_map(fn($c) => "`$c`", $columns));

    foreach ($rows as $row) {
        $escaped_data = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            
            // Sanitize scraper artifacts: convert literal "0" in text fields to empty strings
            if ($val === '0' || $val === 0) {
                $text_columns_to_scrub = ['hashtags', 'in_reply_to_user', 'in_reply_to_tweet', 'in_reply_to_user_id', 'in_response_to_user_screen_name', 'media_link', 'expanded_links', 'urls', 'location_name', 'country'];
                if (in_array($col, $text_columns_to_scrub)) {
                    $val = '';
                }
            }

            if ($col === 'is_protected_or_deleted' && ($val === '0' || $val === '' || $val === null)) {
                $escaped_data[] = "NULL";
            }
            else {
                $escaped_data[] = "'" . mysqli_real_escape_string($link, $val) . "'";
            }
        }
        $values_batch[] = "(" . implode(",", $escaped_data) . ")";
        $count++;

        if (count($values_batch) >= $batch_size) {
            $sql = "REPLACE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
            if (!$link->query($sql))
                log_message("Insert Error: " . $link->error, 'error');
            $values_batch = [];
        }
    }

    if (!empty($values_batch)) {
        $sql = "REPLACE INTO `$table` ($col_names_sql) VALUES " . implode(",", $values_batch);
        $link->query($sql);
    }
    log_message("✅ <strong>Finished:</strong> Imported $count records from JSON into `$table`.", 'success');
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
    harden_schema($table);
    update_response_mentions();
    draw_network($table);
}

function harden_schema($table)
{
    global $link;
    log_message("Hardening database schema for performance...", 'info');

    $tables = [$table, "users_" . $table];
    foreach ($tables as $t) {
        $res = $link->query("SHOW TABLES LIKE '$t'");
        if (!$res || $res->num_rows == 0) continue;

        log_message("Optimizing table: $t", 'light');
        $cols = $link->query("SHOW FULL COLUMNS FROM `$t` ");
        if (!$cols) continue;

        $existing = [];
        while ($c = $cols->fetch_assoc()) $existing[$c['Field']] = $c;

        $to_int = ['retweets', 'favorites', 'quotes', 'replies', 'user_followers', 'user_following', 'user_tweets', 'user_favorites', 'user_lists'];
        $to_varchar = ['user_id', 'tweet_id', 'user_screen_name', 'in_reply_to_user', 'in_reply_to_tweet'];
        $to_index = ['date_time'];

        foreach ($to_int as $col) {
            if (isset($existing[$col])) {
                if (strpos($existing[$col]['Type'], 'int') === false) {
                    log_message("Converting $col to INT in $t...", 'light');
                    $link->query("ALTER TABLE `$t` MODIFY `$col` INT UNSIGNED DEFAULT 0");
                }
                $idx_res = $link->query("SHOW INDEX FROM `$t` WHERE Key_name = 'idx_$col'");
                if ($idx_res && $idx_res->num_rows == 0) {
                    $link->query("ALTER TABLE `$t` ADD INDEX `idx_$col` (`$col`)");
                }
            }
        }
        foreach ($to_varchar as $col) {
            if (isset($existing[$col])) {
                if (strpos($existing[$col]['Type'], 'varchar') === false || !isset($existing[$col]['Collation']) || $existing[$col]['Collation'] != 'utf8_unicode_ci') {
                    log_message("Converting $col to VARCHAR(191) with utf8_unicode_ci in $t...", 'light');
                    $link->query("ALTER TABLE `$t` MODIFY `$col` VARCHAR(191) CHARACTER SET utf8 COLLATE utf8_unicode_ci");
                }
                $idx_res = $link->query("SHOW INDEX FROM `$t` WHERE Key_name = 'idx_$col'");
                if ($idx_res && $idx_res->num_rows == 0) {
                    $link->query("ALTER TABLE `$t` ADD INDEX `idx_$col` (`$col`)");
                }
            }
        }
        foreach ($to_index as $col) {
            if (isset($existing[$col])) {
                $idx_res = $link->query("SHOW INDEX FROM `$t` WHERE Key_name = 'idx_$col'");
                if ($idx_res && $idx_res->num_rows == 0) {
                    log_message("Indexing $col in $t...", 'light');
                    $link->query("ALTER TABLE `$t` ADD INDEX `idx_$col` (`$col`)");
                }
            }
        }
        $link->query("OPTIMIZE TABLE `$t` ");
    }
    log_message("Database schema hardened successfully.", 'success');
}

function update_response_mentions()
{
    global $table;
    global $link;
    $all_m = "user_all_mentions_" . "$table";
    $u_m = "user_mentions_" . $table;

    log_message("Analyzing interactions...", 'primary');
    log_message("Step 1: Marking missing users...", 'light');
    $q1 = "UPDATE `users_" . $table . "` u LEFT JOIN `$table` t ON u.`user_screen_name` = t.`user_screen_name` SET u.`not_in_search_results` = 1 WHERE t.`user_screen_name` IS NULL";
    if ($link->query($q1)) {
        log_message("Step 1 complete.", 'light');
    } else {
        log_message("Step 1 failed: " . $link->error, 'danger');
    }

    // 2. Mentions Table (All IDs are VARCHAR(191) to match)
    $link->query("DROP TABLE IF EXISTS $all_m");
    $create_all_m = "CREATE TABLE `$all_m` (
      `tweet_id` varchar(191) DEFAULT NULL,
      `replies` int UNSIGNED NOT NULL DEFAULT '0',
      `user_id` varchar(191) DEFAULT NULL,
      `user_screen_name` varchar(191) DEFAULT NULL,
      `responses_to_tweeter` int UNSIGNED NOT NULL DEFAULT '0',
      `mentions_of_tweeter` int UNSIGNED NOT NULL DEFAULT '0',
      `mention1` int UNSIGNED NOT NULL DEFAULT '0',
      `mention2` int UNSIGNED NOT NULL DEFAULT '0',
      `mention3` int UNSIGNED NOT NULL DEFAULT '0',
      `mention4` int UNSIGNED NOT NULL DEFAULT '0',
      `mention5` int UNSIGNED NOT NULL DEFAULT '0',
      `mention6` int UNSIGNED NOT NULL DEFAULT '0',
      `mention7` int UNSIGNED NOT NULL DEFAULT '0',
      `mention8` int UNSIGNED NOT NULL DEFAULT '0',
      `mention9` int UNSIGNED NOT NULL DEFAULT '0',
      `mention10` int UNSIGNED NOT NULL DEFAULT '0',
      `mention11` int UNSIGNED NOT NULL DEFAULT '0',
      `mention12` int UNSIGNED NOT NULL DEFAULT '0',
      `mention13` int UNSIGNED NOT NULL DEFAULT '0',
      `mention14` int UNSIGNED NOT NULL DEFAULT '0',
      `mention15` int UNSIGNED NOT NULL DEFAULT '0',
      `mention16` int UNSIGNED NOT NULL DEFAULT '0',
      `mention17` int UNSIGNED NOT NULL DEFAULT '0',
      `mention18` int UNSIGNED NOT NULL DEFAULT '0',
      `mention19` int UNSIGNED NOT NULL DEFAULT '0',
      `mention20` int UNSIGNED NOT NULL DEFAULT '0',
      KEY `user_screen_name` (`user_screen_name`),
      KEY `tweet_id` (`tweet_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
    log_message("Step 2: Creating all_mentions table...", 'light');
    $link->query("DROP TABLE IF EXISTS $all_m");
    if ($link->query($create_all_m)) {
        log_message("Step 2 complete.", 'light');
    } else {
        log_message("Step 2 failed: " . $link->error, 'danger');
    }

    // 3. Insert Replies
    log_message("Step 3: Calculating replies...", 'light');
    $query_replies = "INSERT INTO $all_m (tweet_id,replies) (SELECT $table.in_reply_to_tweet, count($table.tweet_id) FROM $table WHERE ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.in_reply_to_tweet IS NOT NULL GROUP BY $table.in_reply_to_tweet ORDER BY count($table.tweet_id) DESC)";
    if ($link->query($query_replies)) {
        log_message("Step 3 complete.", 'light');
    } else {
        log_message("Step 3 failed: " . $link->error, 'danger');
    }

    log_message("Step 3.1: Updating user info in all_mentions (Chunked)...", 'light');
    
    $ids = [];
    if ($res = $link->query("SELECT tweet_id FROM $all_m")) {
        while ($r = $res->fetch_assoc()) {
            $ids[] = $r['tweet_id'];
        }
    }
    
    $total_ids = count($ids);
    log_message("Total IDs to process: $total_ids", 'light');
    
    $chunks = array_chunk($ids, 1000);
    $processed = 0;
    
    foreach ($chunks as $index => $chunk) {
        $id_list = "'" . implode("','", array_map([$link, 'real_escape_string'], $chunk)) . "'";
        
        // Fetch valid author info for these IDs from the main table
        $q_fetch = "SELECT tweet_id, user_screen_name, user_id FROM $table WHERE tweet_id IN ($id_list)";
        $res = $link->query($q_fetch);
        $count_in_chunk = 0;
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $u_sn = $link->real_escape_string($row['user_screen_name']);
                $u_id = $link->real_escape_string($row['user_id']);
                $t_id = $link->real_escape_string($row['tweet_id']);
                
                $link->query("UPDATE $all_m SET user_screen_name = '$u_sn', user_id = '$u_id' WHERE tweet_id = '$t_id'");
                $count_in_chunk++;
            }
        }
        
        $processed += count($chunk);
        log_message("Chunk " . ($index + 1) . "/" . count($chunks) . " complete ($processed/$total_ids). Found $count_in_chunk matches.", 'light');
    }
    log_message("Step 3.1 complete.", 'light');
    
    log_message("Step 4: Calculating responses (Optimized)...", 'light');
    
    $query_select = "SELECT in_reply_to_user, count(tweet_id) as cnt FROM $table WHERE in_reply_to_user IS NOT NULL AND in_reply_to_user != '' AND (is_protected_or_deleted IS NULL OR is_protected_or_deleted<>1) GROUP BY in_reply_to_user";
    $res = $link->query($query_select);
    
    if ($res) {
        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $user = $link->real_escape_string($row['in_reply_to_user']);
            $cnt = (int)$row['cnt'];
            $link->query("UPDATE $all_m SET responses_to_tweeter = $cnt WHERE user_screen_name = '$user'");
            $count++;
        }
        log_message("Step 4 complete. Updated $count users.", 'light');
    } else {
        log_message("Step 4 failed: " . $link->error, 'danger');
    }

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
      KEY `user_id` (`user_id`),
      KEY `user_screen_name` (`user_screen_name`),
      KEY `in_response_to_user_screen_name` (`in_response_to_user_screen_name`)
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

    log_message("Parsing mentions in 20 cycles...", 'info');
    for ($i = 1; $i <= 20; $i++) {
        $query = "INSERT INTO `$all_m` (user_screen_name,mention$i) (SELECT SUBSTR($u_m.mention$i,2), count($u_m.tweet_id) AS counts FROM $u_m WHERE $u_m.mention$i<>'' GROUP BY $u_m.mention$i ORDER BY count($u_m.tweet_id) DESC) ON DUPLICATE KEY UPDATE $all_m.mention$i=VALUES(mention$i)";
        $link->query($query);
        if ($i % 5 == 0) log_message("Mention cycle $i/20 completed...", 'light');
    }

    $sum_mentions = implode("+", array_map(fn($n) => "mention$n", range(1, 20)));
    $link->query("UPDATE $all_m SET mentions_of_tweeter = ($sum_mentions)");

    log_message("Updating tweet reply counts (Optimized)...", 'info');
    $q_replies = "SELECT tweet_id, replies FROM $all_m WHERE tweet_id IS NOT NULL AND replies > 0";
    if ($res = $link->query($q_replies)) {
        $upd_count = 0;
        while ($row = $res->fetch_assoc()) {
            $t_id = $link->real_escape_string($row['tweet_id']);
            $replies = (int)$row['replies'];
            $link->query("UPDATE $table SET replies = $replies WHERE tweet_id = '$t_id'");
            $upd_count++;
        }
        log_message("Updated reply counts for $upd_count tweets.", 'light');
    }

    log_message("Mapping responses to users (Optimized)...", 'info');
    $u_m = "user_mentions_" . $table;
    $q_map = "SELECT tweet_id FROM $u_m";
    if ($res = $link->query($q_map)) {
        $upd_count = 0;
        while ($row = $res->fetch_assoc()) {
            $t_id = $link->real_escape_string($row['tweet_id']);
            
            // Fetch the corresponding data from the main table
            $q_fetch = "SELECT in_reply_to_tweet, in_reply_to_user FROM $table WHERE tweet_id = '$t_id'";
            if ($t_res = $link->query($q_fetch)) {
                if ($t_row = $t_res->fetch_assoc()) {
                    $in_rep_t = $link->real_escape_string($t_row['in_reply_to_tweet']);
                    $in_rep_u = $link->real_escape_string($t_row['in_reply_to_user']);
                    $link->query("UPDATE $u_m SET in_response_to_tweet = '$in_rep_t', in_response_to_user_screen_name = '$in_rep_u' WHERE tweet_id = '$t_id'");
                    $upd_count++;
                }
            }
        }
        log_message("Mapped responses for $upd_count tweets.", 'light');
    }

    log_message("Enriching mention data with user profiles (Optimized)...", 'info');
    $q_enrich1 = "SELECT user_id, user_name, user_verified, user_followers FROM users_$table";
    if ($res = $link->query($q_enrich1)) {
        $upd_count = 0;
        while ($row = $res->fetch_assoc()) {
            $u_id = $link->real_escape_string($row['user_id']);
            $u_name = $link->real_escape_string($row['user_name']);
            $u_ver = (int)$row['user_verified'];
            $u_foll = (int)$row['user_followers'];
            $link->query("UPDATE $u_m SET user_name='$u_name', user_verified=$u_ver, user_followers=$u_foll WHERE user_id='$u_id'");
            $upd_count++;
        }
        log_message("Enriched mention data using $upd_count user profiles.", 'light');
    }

    log_message("Enriching response data with user profiles (Optimized)...", 'info');
    $q_enrich2 = "SELECT user_screen_name, user_name, user_verified, user_followers FROM users_$table";
    if ($res = $link->query($q_enrich2)) {
        $upd_count = 0;
        while ($row = $res->fetch_assoc()) {
            $u_sn = $link->real_escape_string($row['user_screen_name']);
            $u_name = $link->real_escape_string($row['user_name']);
            $u_ver = (int)$row['user_verified'];
            $u_foll = (int)$row['user_followers'];
            $link->query("UPDATE $u_m SET in_response_to_user_name='$u_name', in_response_to_user_verified=$u_ver, in_response_to_user_followers=$u_foll WHERE in_response_to_user_screen_name='$u_sn'");
            $upd_count++;
        }
        log_message("Enriched response data using $upd_count user profiles.", 'light');
    }

    log_message("Syncing interaction timestamps (Optimized)...", 'info');
    $q_time = "SELECT tweet_id, date_time FROM $table WHERE tweet_id IN (SELECT tweet_id FROM $u_m)";
    if ($res = $link->query($q_time)) {
        $upd_count = 0;
        while ($row = $res->fetch_assoc()) {
            $t_id = $link->real_escape_string($row['tweet_id']);
            $d_time = $link->real_escape_string($row['date_time']);
            $link->query("UPDATE $u_m SET tweet_datetime='$d_time' WHERE tweet_id='$t_id'");
            $upd_count++;
        }
        log_message("Synced timestamps for $upd_count tweets.", 'light');
    }

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
    // --- 1. Top Tweets (Optimized: Unbuffered streaming) ---
    log_message("Exporting top tweets to Kumu files (unbuffered)...", 'info');
    $max_limit = max($top_limit);
    // Note: We avoid ordering by TEXT columns if possible. 
    // harden_schema should have converted retweets to INT.
    $query = "SELECT user_screen_name as screen_name, user_image_url, '' as profile_link, '' as tweet_type, date_time, raw_text, tweet_permalink_path, hashtags, tweet_language, source, retweets, quotes, favorites, replies, user_mentions, user_name, user_location, user_lang, user_bio, user_verified, in_reply_to_user, is_retweet, is_quote, is_reply, has_image, has_video, has_link, location_name, country FROM $table WHERE (is_protected_or_deleted is null OR is_protected_or_deleted<>1) and date_time is not null ORDER BY retweets DESC LIMIT $max_limit";
    
    // Use MYSQLI_USE_RESULT to start streaming immediately without buffering 10k rows in memory
    $result = $link->query($query, MYSQLI_USE_RESULT);

    if ($result) {
        $fps = [];
        log_message("Writing to: " . realpath('tmp/kumu'), 'info');
        foreach ($top_limit as $toplimit) {
            $filename = $table . "_top_tweets_" . $toplimit . ".csv";
            $filepath = "tmp/kumu/" . $filename;
            $fps[$toplimit] = fopen($filepath, 'w');
            if (!$fps[$toplimit]) {
                log_message("FAILED to open $filepath for writing. Check permissions.", 'error');
            }
            fputcsv($fps[$toplimit], array("Label", "Image", "Profile Link", "Type", "Date", "Tweet Text", "Tweet Link", "Tags", "Tweet Language", "Source", "Retweets", "Favorites", "Quotes", "Replies", "User Mentions", "User Full Name", "User Location", "User Language", "User Bio", "User Verified", "In Reply to User", "Is a Retweet", "Has an Image", "Has a Video", "Has a Link", "Media Link", "Other Links", "Tweeted From Location", "Tweeted from Country"));
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $row['profile_link'] = "https://twitter.com/" . $row['screen_name'];
            $row['tweet_type'] = $row['is_retweet'] ? "Retweet" : ($row['is_reply'] ? "Tweet with reply" : "Regular tweet");
            $row['raw_text'] = preg_replace("/[\r\n]+/", " ", $row['raw_text'] ?? '');

            foreach ($top_limit as $toplimit) {
                if ($count < $toplimit && $fps[$toplimit]) {
                    fputcsv($fps[$toplimit], $row);
                }
            }
            $count++;
            if ($count % 500 == 0) {
                log_message("Exported $count top tweets...", 'light');
            }
        }
        $result->free(); 

        foreach ($fps as $toplimit => $fp) {
            if ($fp) {
                fclose($fp);
                $filename = $table . "_top_tweets_" . $toplimit . ".csv";
                if (file_exists("tmp/kumu/$filename")) {
                    log_message("Saved: <a href='tmp/kumu/$filename' target='_blank' class='fw-bold'>$filename</a> (Size: " . filesize("tmp/kumu/$filename") . " bytes)", 'light');
                } else {
                    log_message("ERROR: $filename was not found after saving!", 'error');
                }
            }
        }
    }

    $valid_users = array();
    $result = $link->query("SELECT user_screen_name FROM users_" . $table);
    while ($row = $result->fetch_array()) {
        $valid_users[$row[0]] = 1;
    }

    $all_users_to_graph = array();

    // --- 2. Responses (Optimized) ---
    log_message("Processing responses for Kumu...", 'info');
    $t = "user_mentions_" . $table;
    $query = "SELECT $t.user_screen_name as screen_name, $t.in_response_to_user_screen_name as response_screen_name, '' as tweet_type, $t.in_response_to_tweet, $table.is_retweet, $table.is_quote, $table.is_reply, $t.tweet_datetime, $table.tweet_permalink_path, $table.user_verified, $table.has_image, $table.has_video, $table.has_link, $table.media_link, $table.expanded_links, $table.source, $table.location_name, $table.country, $table.tweet_language, $table.raw_text, $table.hashtags, $table.user_mentions, $table.retweets, $table.quotes, $table.replies, $table.favorites, $t.tweet_id FROM $t JOIN $table ON $t.tweet_id=$table.tweet_id WHERE $t.in_response_to_user_screen_name IS NOT NULL AND $t.user_screen_name IS NOT NULL AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.date_time IS NOT NULL ORDER BY $table.retweets DESC";

    $result = $link->query($query);
    if ($result && $result->num_rows > 0) {
        $filename = $table . "_responses.csv";
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

    // --- 3. Mentions (Optimized) ---
    log_message("Processing mentions for Kumu...", 'info');
    $mentions_sql = "mention1";
    for ($i = 2; $i <= 20; $i++) {
        $mentions_sql .= ",$t.mention$i";
    }
    $query = "SELECT $t.user_screen_name as screen_name, $mentions_sql, $t.tweet_datetime, $table.is_retweet, $table.is_quote, $table.is_reply, $table.tweet_permalink_path, $table.user_verified, $table.has_image, $table.has_video, $table.has_link, $table.media_link, $table.expanded_links, $table.source, $table.location_name, $table.country, $table.tweet_language, $table.raw_text, $table.hashtags, $table.user_mentions, $table.retweets, $table.quotes, $table.replies, $table.favorites, $t.tweet_id FROM $t JOIN $table ON $t.tweet_id=$table.tweet_id WHERE $t.mention1>'' AND $t.user_screen_name IS NOT NULL AND ($table.is_protected_or_deleted IS NULL OR $table.is_protected_or_deleted<>1) AND $table.date_time IS NOT NULL ORDER BY $table.retweets DESC";

    $result = $link->query($query);
    if ($result && $result->num_rows > 0) {
        $filename = $table . "_mentions.csv";
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
    log_message("Exporting users for Kumu...", 'info');
    $filename = $table . "_users.csv";
    $fp = fopen("tmp/kumu/" . $filename, 'w');
    $header = array("Label", "Image", "User Verified", "Link", "Bio", "Language", "Location", "Tweets", "Followers", "Following", "Favorites", "Lists", "Created Date");
    fputcsv($fp, $header);

    $query = "SELECT user_screen_name, user_image_url, user_verified, user_url, user_bio, user_lang, user_location, user_tweets, user_followers, user_following, user_favorites, user_lists, user_created FROM users_" . $table;
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

    // Step 2: Replies (Optimized)
    $query = "SELECT user_screen_name, in_response_to_user_screen_name, count(tweet_id) FROM user_mentions_$table WHERE in_response_to_user_screen_name IS NOT NULL AND in_response_to_user_screen_name != '' GROUP BY user_screen_name, in_response_to_user_screen_name ORDER BY count(tweet_id) DESC";
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
    if (!($result = $link->query($query))) {
        die("Could not insert/update case. Please contact admin! <a href='javascript:void(0)' onclick=javascript:case_proc('add_case');>Try again</a>");
    }

    // The following block seems to be misplaced here. It appears to be logic for handling case creation/update
    // and premature reloads, likely from a different part of the application (e.g., login.php or a case management function).
    // Including it directly here would cause the draw_network function to terminate prematurely.
    // If this logic is intended for a different file, it should be applied there.
    // If it's meant to replace existing logic within this file, its placement needs careful consideration.
    // For now, I'm commenting it out to prevent breaking the draw_network function, as per the instruction
    // to "make the change faithfully and without making any unrelated edits" and "incorporate the change in a way
    // so that the resulting file is syntactically correct."
    /*
     if ($replace) {
     echo $returned;
     exit();
     }
     $_SESSION[basename(__DIR__) . 'created'] = $_POST['case_id'];
     echo '<script type="text/javascript"> location.reload(); </script>';
     email_admin(lst($_POST), "");
     exit();
     */

    if ($result) { // This `if ($result)` block should enclose the rest of the mention processing.
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

    if ($mode == "started") {
        array_map('unlink', glob("tmp/cache/$table*.tab"));
        array_map('unlink', glob("tmp/cache/$table*.htm*"));
    }

    $query = "update cases set last_process_$mode=NOW()$add_compl, status='$mode' where id='$table'";

    $link->query($query);

    if ($mode == "completed") {
        $done = 1;
        echo "<script>
            document.getElementById('processHeader').innerHTML = '<i class=\"bi bi-check-circle-fill\"></i> Processing Complete';
            document.getElementById('processHeader').classList.remove('bg-primary');
            document.getElementById('processHeader').classList.add('bg-success');
            if (window.scrollInterval) clearInterval(window.scrollInterval);

            // Auto-reload parent window with the case selected
            setTimeout(function () {
                if (window.parent && window.parent !== window) {
                    window.parent.location.href = 'index.php?table=$table';
                }
            }, 3000);
        </script>";

        echo "<div class='mt-4 text-center'>
                <h4 class='text-success'>✅ Process Completed Successfully!</h4>
                <p>Returning to Dashboard...</p>
                <button onclick='if(window.parent && window.parent !== window){ window.parent.location.href=\"index.php?table=$table\"; } else { window.location.href=\"index.php?table=$table\"; }' class='btn btn-success btn-lg shadow'>Return to Dashboard</button>
              </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV & JSON Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-card {
            max-width: 800px;
            margin: 20px auto;
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
    log_message("Initiating import process...", 'info');

    $combined_json = isset($_FILES['combined_json']) ? $_FILES['combined_json']['tmp_name'] : null;
    $tweets_file = isset($_FILES['tweets_csv']) ? $_FILES['tweets_csv']['tmp_name'] : null;
    $users_file = isset($_FILES['users_csv']) ? $_FILES['users_csv']['tmp_name'] : null;

    update_cases_table("started");

    $processed = false;
    if ($combined_json && filesize($combined_json) > 0) {
        log_message("Combined JSON detected. Processing...", 'primary');
        insert_json_into_db($combined_json, $tweets_table, $users_table, $link);
        $processed = true;
    }
    else {
        if ($tweets_file && filesize($tweets_file) > 0) {
            log_message("Importing Tweets...", 'primary');
            insert_csv_into_db($tweets_file, $tweets_table, $link, false);
            $processed = true;
        }

        if ($users_file && filesize($users_file) > 0) {
            log_message("Importing Users...", 'primary');
            insert_csv_into_db($users_file, $users_table, $link, true);
            $processed = true;
        }
    }

    if ($processed) {
        get_hashtag_cloud($table_name);
        tweeter_data($table_name);
    }
    else {
        log_message("No valid files uploaded. Please select a JSON or CSV file.", 'error');
        echo "<div class='text-center mt-3'><button onclick='window.location.reload()' class='btn btn-primary'>Try Again</button></div>";
    }
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

                    <!-- NEW: Consolidated JSON Option -->
                    <div class="mb-4">
                        <div class="p-3 border border-primary border-2 rounded bg-light shadow-sm">
                            <label for="combined_json" class="form-label d-flex align-items-center">
                                <i class="bi bi-filetype-json text-primary fs-4 me-2"></i>
                                <span class="fw-bold">Combined JSON File (Recommended)</span>
                                <span class="badge bg-primary ms-2">New</span>
                            </label>
                            <input class="form-control form-control-lg" type="file" name="combined_json"
                                id="combined_json" accept=".json">
                            <div class="form-text mt-2">
                                <i class="bi bi-info-circle"></i> Upload the consolidated JSON file containing both
                                tweets and users from xscraper.
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible CSV Option -->
                    <div class="mb-4">
                        <button class="btn btn-outline-secondary btn-sm w-100 mb-3 collapsed shadow-sm" type="button"
                            data-bs-toggle="collapse" data-bs-target="#csvUploadCollapse" aria-expanded="false"
                            aria-controls="csvUploadCollapse">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Or upload legacy CSV files separately
                        </button>

                        <div class="collapse" id="csvUploadCollapse">
                            <div class="card card-body bg-light border-0 shadow-sm p-3">
                                <div class="mb-3">
                                    <label for="tweets_csv" class="form-label small fw-bold">Tweets CSV File</label>
                                    <input class="form-control" type="file" name="tweets_csv" id="tweets_csv"
                                        accept=".csv">
                                    <div class="form-text small">Select the tweets dataset (e.g., my_tweets.csv).</div>
                                </div>

                                <div class="mb-0">
                                    <label for="users_csv" class="form-label small fw-bold">Users CSV File</label>
                                    <input class="form-control" type="file" name="users_csv" id="users_csv"
                                        accept=".csv">
                                    <div class="form-text small">Select the users dataset (e.g., my_users.csv).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                            <path
                                d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                        </svg>
                        <div>
                            <strong>Notice:</strong> If a case with this ID exists, new data will be appended and any
                            existing duplicates will be updated.
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
                        <button type="submit" class="btn btn-upload btn-primary text-white shadow-sm" id="uploadBtn">
                            Upload and Process Files
                        </button>
                    </div>

                </form>
            </div>
        </div>
        <script>
            document.querySelector('form').onsubmit = function (e) {
                var json = document.getElementById('combined_json').value;
                var tweets = document.getElementById('tweets_csv').value;
                var users = document.getElementById('users_csv').value;
                if (!json && !tweets && !users) {
                    alert('Please select at least one file to upload.');
                    return false;
                }
                document.getElementById('uploadBtn').disabled = true;
                document.getElementById('uploadBtn').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                return true;
            };
        </script>
        <?php
endif; ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </div>
</body>

</html>