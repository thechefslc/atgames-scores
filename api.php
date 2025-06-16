<?php

// --- Configuration ---
define('DATABASE_NAME', 'arcadenet_scores.db'); // SQLite database file
define('MAX_SIZE', 5); // Used in fetchRepeating for pagination
define('FETCH_INTERVAL_SECONDS', 24 * 60 * 60); // 24 hours in seconds

// Define the Arcade.Net API endpoints
define('ARCADE_NET_SIGN_IN_URL', 'https://www.atgames.net/arcadenet/backend/api/account/sign_in');
define('ARCADE_NET_SCORES_URL', 'https://www.atgames.net/arcadenet/backend/d2d/arcade/v2/leaderboards/personal?limit=' . MAX_SIZE . '&model=RK9920');

// --- Database Functions ---

function get_db_connection() {
    /**
     * Establishes a connection to the SQLite database.
     * Creates the database file if it doesn't exist.
     */
    try {
        $db_path = __DIR__ . '/' . DATABASE_NAME;
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Fetch rows as associative arrays
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Could not connect to the database.");
    }
}

function init_db() {
    /**
     * Initializes the database schema by creating the 'user_scores', 'users', and 'hidden_games' tables if they don't exist.
     */
    try {
        $conn = get_db_connection();
        // Table for individual game scores
        $conn->exec('
            CREATE TABLE IF NOT EXISTS user_scores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_account_id INTEGER NOT NULL,
                game_id INTEGER NOT NULL,
                game_name TEXT NOT NULL,
                score_value TEXT,
                rank INTEGER,
                signature TEXT,
                user_name TEXT,
                created_at TEXT,
                hardware TEXT,
                series TEXT,
                snapshot TEXT,
                UNIQUE(user_account_id, game_id) ON CONFLICT REPLACE
            )
        ');
        // Table to track last fetch timestamp for each user
        $conn->exec('
            CREATE TABLE IF NOT EXISTS users (
                user_account_id INTEGER PRIMARY KEY,
                last_fetch_timestamp TEXT NOT NULL
            )
        ');
        // NEW: Table to track hidden games for each user
        $conn->exec('
            CREATE TABLE IF NOT EXISTS hidden_games (
                user_account_id INTEGER NOT NULL,
                game_id INTEGER NOT NULL,
                is_hidden INTEGER DEFAULT 1, -- 1 for hidden, 0 for not hidden
                PRIMARY KEY (user_account_id, game_id)
            )
        ');
        error_log("Database '" . DATABASE_NAME . "' initialized.");
    } catch (Exception $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        throw new Exception("Database initialization failed: " . $e->getMessage());
    }
}

function get_last_fetch_timestamp($account_id) {
    /**
     * Retrieves the last fetch timestamp for a given user from the 'users' table.
     * Returns null if no record exists.
     */
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT last_fetch_timestamp FROM users WHERE user_account_id = ?');
        $stmt->execute([$account_id]);
        $result = $stmt->fetch();
        return $result ? $result['last_fetch_timestamp'] : null;
    } catch (Exception $e) {
        error_log("Failed to get last fetch timestamp: " . $e->getMessage());
        return null; // Return null on error so it triggers a fresh fetch
    }
}

function update_last_fetch_timestamp($account_id) {
    /**
     * Updates the last fetch timestamp for a given user in the 'users' table.
     * Inserts a new record if one doesn't exist. Stores in UTC.
     */
    try {
        $conn = get_db_connection();
        $current_time = gmdate('Y-m-d H:i:s'); // Store in UTC
        $stmt = $conn->prepare('INSERT OR REPLACE INTO users (user_account_id, last_fetch_timestamp) VALUES (?, ?)');
        $stmt->execute([$account_id, $current_time]);
        error_log("Updated last fetch timestamp for account ID " . $account_id . " to " . $current_time . " (UTC).");
    } catch (Exception $e) {
        error_log("Failed to update last fetch timestamp: " . $e->getMessage());
    }
}

function save_personal_scores_to_db($scores_data, $account_id) {
    /**
     * Saves a list of score dictionaries to the database for a specific user.
     * Uses ON CONFLICT REPLACE to update existing scores for a game by the same user.
     */
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare('
            INSERT INTO user_scores (
                user_account_id, game_id, game_name, score_value, rank, 
                signature, user_name, created_at, hardware, series, snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($scores_data as $score_entry) {
            $stmt->execute([
                $account_id,
                $score_entry['game_id'] ?? null,
                $score_entry['name'] ?? null, // Game name from 'name' key in score entry
                $score_entry['score'] ?? null,
                $score_entry['rank'] ?? null,
                $score_entry['signature'] ?? null,
                $score_entry['user_name'] ?? null,
                $score_entry['created_at'] ?? null,
                $score_entry['hardware'] ?? null,
                $score_entry['series'] ?? null,
                $score_entry['snapshot'] ?? null
            ]);
        }
        error_log("Saved " . count($scores_data) . " scores for account ID " . $account_id . " to database.");
    } catch (Exception $e) {
        error_log("Failed to save scores to DB: " . $e->getMessage());
        throw new Exception("Failed to save scores to database.");
    }
}

function get_personal_scores_from_db($account_id) {
    /**
     * Retrieves all scores for a given user from the database, including their hidden status.
     */
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare('
            SELECT us.game_name, us.score_value, us.rank, us.signature, us.created_at, us.game_id,
                   CASE WHEN hg.is_hidden IS NULL THEN 0 ELSE hg.is_hidden END AS is_hidden
            FROM user_scores us
            LEFT JOIN hidden_games hg ON us.user_account_id = hg.user_account_id AND us.game_id = hg.game_id
            WHERE us.user_account_id = ? 
            ORDER BY us.rank ASC
        ');
        $stmt->execute([$account_id]);
        return $stmt->fetchAll(); // Returns associative array
    } catch (Exception $e) {
        error_log("Failed to retrieve scores from DB: " . $e->getMessage());
        throw new Exception("Failed to retrieve scores from database.");
    }
}

function toggle_game_hidden_status($user_account_id, $game_id, $is_hidden) {
    /**
     * Toggles the hidden status of a specific game for a given user.
     * Inserts or updates the hidden_games table.
     */
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare('
            INSERT OR REPLACE INTO hidden_games (user_account_id, game_id, is_hidden) 
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$user_account_id, $game_id, (int)$is_hidden]);
        error_log("Toggled game " . $game_id . " for user " . $user_account_id . " to hidden=" . (int)$is_hidden);
        return true;
    } catch (Exception $e) {
        error_log("Failed to toggle hidden status: " . $e->getMessage());
        throw new Exception("Failed to update game visibility.");
    }
}

// --- Helper Functions (HTTP/API interaction) ---

function make_curl_request($url, $method = 'GET', $headers = [], $payload = null) {
    /**
     * Generic cURL function to make HTTP requests.
     */
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

    // IMPORTANT: CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST are set to false
    // to match your original Python script's behavior (verify=False).
    // In a production environment, bypassing SSL verification is a security risk
    // and should generally be avoided unless you fully understand and accept the implications.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $headers[] = 'Content-Type: application/json'; // Ensure content type for JSON payload
        }
    }

    // Set headers
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("cURL Error: " . $error);
    }

    if ($http_code >= 400) {
        throw new Exception("API Error: HTTP " . $http_code . " - " . $response, $http_code);
    }

    return json_decode($response, true); // Decode JSON response into associative array
}

function login_to_arcadenet($email, $password) {
    /**
     * Performs a login request to Arcade.Net to obtain a bearer token and account ID.
     */
    $payload = ['email' => $email, 'password' => $password];
    $headers = [
        "accept: application/json, text/plain, */*",
        "accept-language: en-US,en;q=0.9",
        "content-type: application/json",
        "fp: ZDRlMGZkNmVhYmE2NTczNTNhMzQ3ZmFiOTc2NWNiMWU=",
        "sec-ch-ua: \" Not A;Brand\";v=\"99\", \"Chromium\";v=\"90\", \"Google Chrome\";v=\"90\"",
        "sec-ch-ua-mobile: ?0",
        "sec-fetch-dest: empty",
        "sec-fetch-mode: cors",
        "sec-fetch-site: same-origin"
    ];

    $response_json = make_curl_request(ARCADE_NET_SIGN_IN_URL, 'POST', $headers, $payload);
    
    $token = $response_json['account']['token'] ?? null;
    $account_id = $response_json['account']['id'] ?? null;

    if (!$token || !$account_id) {
        throw new Exception("Login response missing token or account ID.");
    }
    
    return ['token' => 'Bearer ' . $token, 'account_id' => $account_id];
}

function fetch_repeating($base_url, $token, $limit = MAX_SIZE) {
    /**
     * Fetches data from an API endpoint with pagination.
     * This version is tailored for personal leaderboards, which use 'game_id' for pagination.
     */
    $all_items = [];
    $current_url = $base_url;
    $headers = ['Authorization: ' . $token];

    $new_items = make_curl_request($current_url, 'GET', $headers);
    $all_items = array_merge($all_items, $new_items);

    while (count($new_items) >= $limit) {
        // For personal leaderboards, pagination uses 'game_id' of the last entry
        $last_game_id = $new_items[count($new_items) - 1]['game_id'] ?? null;
        if ($last_game_id === null) { 
            break;
        }
        
        $separator = strpos($base_url, '?') !== false ? '&' : '?';
        $new_url = $base_url . $separator . 'after=' . $last_game_id;
        
        error_log('Fetching next page: ' . $new_url);
        $new_items = make_curl_request($new_url, 'GET', $headers);
        $all_items = array_merge($all_items, $new_items);
        error_log('Fetched ' . count($new_items) . ' new items for a total of ' . count($all_items) . '.');
    }
    return $all_items;
}

// --- Main API Endpoint Logic ---

header('Content-Type: application/json'); // Set content type for JSON response

// Initialize the database if it doesn't exist
try {
    init_db();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server setup error: ' . $e->getMessage()]);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;
$force_update = filter_var($input['force_update'] ?? false, FILTER_VALIDATE_BOOLEAN);

// NEW: Parameters for toggling hidden status
$toggle_game_id = $input['toggle_game_id'] ?? null;
$toggle_is_hidden = $input['toggle_is_hidden'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$email || !$password) {
        echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
        exit();
    }

    try {
        // Always try to log in first to authenticate and get account_id
        $login_result = login_to_arcadenet($email, $password);
        $bearer_token = $login_result['token'];
        $account_id = $login_result['account_id'];
        error_log("Authenticated as account ID: " . $account_id);

        if ($toggle_game_id !== null && $toggle_is_hidden !== null) {
            // This is a request to toggle a game's hidden status
            toggle_game_hidden_status($account_id, $toggle_game_id, $toggle_is_hidden);
            echo json_encode(['success' => true, 'message' => 'Game visibility updated.']);
        } else {
            // This is a request to fetch and display scores (original flow)
            $last_fetch_timestamp_str = get_last_fetch_timestamp($account_id);
            $should_fetch_from_api = false;

            if ($force_update) {
                $should_fetch_from_api = true;
                error_log("Force update enabled. Fetching from API.");
            } elseif ($last_fetch_timestamp_str === null) {
                $should_fetch_from_api = true;
                error_log("No previous fetch timestamp found. Fetching from API.");
            } else {
                // Compare timestamps in UTC for consistency
                $last_fetch_time_utc = strtotime($last_fetch_timestamp_str . ' UTC'); // Assume stored as UTC
                $current_time_utc = time(); // PHP's time() is Unix timestamp, timezone independent
                
                if (($current_time_utc - $last_fetch_time_utc) > FETCH_INTERVAL_SECONDS) {
                    $should_fetch_from_api = true;
                    error_log("Last fetch was more than " . (FETCH_INTERVAL_SECONDS / 3600) . " hours ago. Fetching from API.");
                } else {
                    error_log("Scores are up-to-date (last fetched " . $last_fetch_timestamp_str . " UTC). Fetching from database.");
                }
            }

            if ($should_fetch_from_api) {
                // Fetch personal scores from Arcade.Net API
                $all_scores_from_api = fetch_repeating(ARCADE_NET_SCORES_URL, $bearer_token);
                error_log("Fetched " . count($all_scores_from_api) . " scores from Arcade.Net API.");
                
                // Save fetched scores to database
                save_personal_scores_to_db($all_scores_from_api, $account_id);
                // Update the last fetch timestamp (will now be stored in UTC)
                update_last_fetch_timestamp($account_id);
                // Re-fetch the timestamp to ensure the latest UTC is sent in the response
                $last_fetch_timestamp_str = get_last_fetch_timestamp($account_id); 
            }

            // Always retrieve scores from database for display (whether newly fetched or not)
            $scores_for_display = get_personal_scores_from_db($account_id);
            
            $scores_html_table = '';
            if (!empty($scores_for_display)) {
                // Manually build HTML table from fetched data
                $scores_html_table .= '<table class="table table-striped table-bordered">';
                $scores_html_table .= '<thead><tr><th>Game Name</th><th>Score</th><th>Rank</th><th>Signature</th><th>Recorded At</th><th>Actions</th></tr></thead>';
                $scores_html_table .= '<tbody>';
                foreach ($scores_for_display as $row) {
                    // Add 'hidden' class to row if is_hidden is true
                    $row_class = ($row['is_hidden'] == 1) ? 'class="score-row hidden"' : 'class="score-row"';
                    $button_text = ($row['is_hidden'] == 1) ? 'Show' : 'Hide';
                    $button_variant = ($row['is_hidden'] == 1) ? 'btn-outline-success' : 'btn-outline-secondary';
                    $is_hidden_value = ($row['is_hidden'] == 1) ? '1' : '0';

                    // Format score with commas
                    $formatted_score = number_format((float)str_replace(',', '', $row['score_value']), 0, '.', ',');
                    $scores_html_table .= '<tr ' . $row_class . ' data-game-id="' . htmlspecialchars($row['game_id']) . '" data-is-hidden="' . $is_hidden_value . '">';
                    // NEW: Added class and data-game-id to the Game Name cell
                    $scores_html_table .= '<td class="game-name-cell" data-game-id="' . htmlspecialchars($row['game_id']) . '">' . htmlspecialchars($row['game_name']) . '</td>';
                    $scores_html_table .= '<td>' . htmlspecialchars($formatted_score) . '</td>';
                    $scores_html_table .= '<td>' . htmlspecialchars($row['rank']) . '</td>';
                    $scores_html_table .= '<td>' . htmlspecialchars($row['signature']) . '</td>';
                    // Pass the original 'created_at' string from DB/API to data-original-date
                    $scores_html_table .= '<td data-original-date="' . htmlspecialchars($row['created_at']) . '">' . htmlspecialchars($row['created_at']) . '</td>';
                    $scores_html_table .= '<td><button class="toggle-hide-btn btn btn-sm ' . $button_variant . '">' . $button_text . '</button></td>';
                    $scores_html_table .= '</tr>';
                }
                $scores_html_table .= '</tbody>';
                $scores_html_table .= '</table>';
            } else {
                $scores_html_table = '<p>No scores found for this account in the database.</p>';
            }

            echo json_encode([
                'success' => true, 
                'scores_html' => $scores_html_table,
                'last_fetched_time' => $last_fetch_timestamp_str // This will now be the UTC timestamp
            ]);
        }

    } catch (Exception $e) {
        $http_code = $e->getCode() ?: 500;
        http_response_code($http_code); // Set HTTP status code
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} else {
    // Handle non-POST requests (e.g., direct access to api.php)
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}

?>
