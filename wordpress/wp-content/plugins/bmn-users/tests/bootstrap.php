<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Users
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the users plugin autoloader and adds missing WP function stubs.
 */

// Load platform bootstrap (WordPress stubs + platform classes).
$platformBootstrap = dirname(__DIR__, 3) . '/mu-plugins/bmn-platform/tests/bootstrap.php';

if (file_exists($platformBootstrap)) {
    require_once $platformBootstrap;
} else {
    // Fallback: define minimum WP stubs inline.
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('BMN_PLATFORM_VERSION')) {
        define('BMN_PLATFORM_VERSION', '2.0.0-dev');
    }
}

// Users constants.
if (! defined('BMN_USERS_VERSION')) {
    define('BMN_USERS_VERSION', '2.0.0-dev');
}

if (! defined('BMN_USERS_PATH')) {
    define('BMN_USERS_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_USERS_URL')) {
    define('BMN_USERS_URL', 'https://bmnboston.com/wp-content/plugins/bmn-users/');
}

// Users autoloader.
$usersAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($usersAutoloader)) {
    require_once $usersAutoloader;
}

// ---------------------------------------------------------------
// WP_User class stub (must be before function stubs that use it)
// ---------------------------------------------------------------

if (! class_exists('WP_User')) {
    /**
     * Minimal stub of WordPress WP_User for unit testing.
     */
    class WP_User
    {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $user_pass = '';
        public string $display_name = '';
        public string $first_name = '';
        public string $last_name = '';
        public array $roles = [];

        public function __construct(int $id = 0)
        {
            $this->ID = $id;
        }

        public function __get(string $name): mixed
        {
            return $this->$name ?? '';
        }

        public function __isset(string $name): bool
        {
            return isset($this->$name);
        }

        /**
         * Create a WP_User from a stdClass object.
         */
        public static function fromStdClass(object $obj): self
        {
            $user = new self((int) ($obj->ID ?? 0));
            foreach (get_object_vars($obj) as $key => $value) {
                $user->$key = $value;
            }
            return $user;
        }
    }
}

// Override wp_get_current_user to return WP_User.
// The platform bootstrap defines this but returns stdClass.
// We redefine to match the WP_User return type.
$GLOBALS['_bmn_wp_get_current_user_override'] = true;

// ---------------------------------------------------------------
// Additional WordPress function stubs for user operations
// ---------------------------------------------------------------

if (! function_exists('wp_authenticate')) {
    /**
     * Authenticate a user (stub for testing).
     *
     * Returns a user object from globals or WP_Error.
     */
    function wp_authenticate(string $username, string $password): object
    {
        $key = 'wp_authenticate_result';
        if (isset($GLOBALS[$key])) {
            return $GLOBALS[$key];
        }

        // Default: check email-based user stub.
        $user = get_user_by('email', $username);
        if ($user !== false && isset($user->user_pass) && wp_check_password($password, $user->user_pass)) {
            return $user;
        }

        return new \WP_Error('invalid_credentials', 'Invalid email or password.');
    }
}

if (! function_exists('wp_insert_user')) {
    /** @var int Auto-incrementing user ID for testing. */
    $GLOBALS['wp_next_user_id'] = $GLOBALS['wp_next_user_id'] ?? 100;

    /**
     * Insert a new WordPress user (stub for testing).
     *
     * @param array $userdata User data array.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    function wp_insert_user(array $userdata): int|\WP_Error
    {
        if (isset($GLOBALS['wp_insert_user_error']) && $GLOBALS['wp_insert_user_error']) {
            return new \WP_Error('insert_failed', 'Failed to create user.');
        }

        $userId = $GLOBALS['wp_next_user_id']++;

        // Store as a user stub.
        $user = new \stdClass();
        $user->ID = $userId;
        $user->user_login = $userdata['user_login'] ?? '';
        $user->user_email = $userdata['user_email'] ?? '';
        $user->user_pass = wp_hash_password($userdata['user_pass'] ?? '');
        $user->first_name = $userdata['first_name'] ?? '';
        $user->last_name = $userdata['last_name'] ?? '';
        $user->display_name = $userdata['display_name'] ?? '';
        $user->roles = [$userdata['role'] ?? 'subscriber'];

        $GLOBALS["wp_user_id_{$userId}"] = $user;
        $GLOBALS["wp_user_email_{$user->user_email}"] = $user;

        return $userId;
    }
}

if (! function_exists('wp_update_user')) {
    /**
     * Update a WordPress user (stub for testing).
     *
     * @param array $userdata User data array with 'ID' key.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    function wp_update_user(array $userdata): int|\WP_Error
    {
        if (isset($GLOBALS['wp_update_user_error']) && $GLOBALS['wp_update_user_error']) {
            return new \WP_Error('update_failed', 'Failed to update user.');
        }

        $userId = $userdata['ID'];
        $key = "wp_user_id_{$userId}";

        if (isset($GLOBALS[$key])) {
            $user = $GLOBALS[$key];
            foreach ($userdata as $field => $value) {
                if ($field !== 'ID') {
                    $user->$field = $value;
                }
            }
            $GLOBALS[$key] = $user;

            if (isset($user->user_email)) {
                $GLOBALS["wp_user_email_{$user->user_email}"] = $user;
            }
        }

        return $userId;
    }
}

if (! function_exists('wp_delete_user')) {
    /**
     * Delete a WordPress user (stub for testing).
     */
    function wp_delete_user(int $id, ?int $reassign = null): bool
    {
        $key = "wp_user_id_{$id}";
        if (isset($GLOBALS[$key])) {
            $user = $GLOBALS[$key];
            unset($GLOBALS[$key]);
            if (isset($user->user_email)) {
                unset($GLOBALS["wp_user_email_{$user->user_email}"]);
            }
        }
        return true;
    }
}

if (! function_exists('wp_set_password')) {
    /**
     * Set a user's password (stub for testing).
     */
    function wp_set_password(string $password, int $user_id): void
    {
        $key = "wp_user_id_{$user_id}";
        if (isset($GLOBALS[$key])) {
            $GLOBALS[$key]->user_pass = wp_hash_password($password);
        }
    }
}

if (! function_exists('get_avatar_url')) {
    /**
     * Get the avatar URL for a user (stub for testing).
     */
    function get_avatar_url(int|string $id_or_email, ?array $args = null): string|false
    {
        return 'https://gravatar.com/avatar/' . md5((string) $id_or_email);
    }
}

if (! function_exists('update_user_meta')) {
    /**
     * Update user meta (stub for testing).
     */
    function update_user_meta(int $user_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool
    {
        $GLOBALS["wp_usermeta_{$user_id}_{$meta_key}"] = $meta_value;
        return true;
    }
}

if (! function_exists('delete_user_meta')) {
    /**
     * Delete user meta (stub for testing).
     */
    function delete_user_meta(int $user_id, string $meta_key, mixed $meta_value = ''): bool
    {
        unset($GLOBALS["wp_usermeta_{$user_id}_{$meta_key}"]);
        return true;
    }
}

if (! function_exists('wp_timezone')) {
    /**
     * Return the site timezone (stub for testing).
     */
    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('America/New_York');
    }
}

if (! function_exists('headers_sent')) {
    // Already defined in most PHP CLI environments, but just in case.
}
