<?php
/**
 * Olama Central System Logger
 *
 * Unified logger for Olama School System, Olama Exam Engine,
 * and Olama Registration. Stores entries in wp_olama_system_logs
 * (errors/warnings) and optionally to a private log file.
 *
 * Log Levels:
 *  - ERROR   : Failed AJAX, DB write failure, grader crash, exceptions
 *  - WARNING : Setting misconfigured, fallback triggered, permission mismatch
 *  - INFO    : Exam submitted, registration created, backup completed
 *  - DEBUG   : Verbose trace data — only written when WP_DEBUG === true
 *
 * Sources (plugin slugs):
 *  - 'school'        : Olama School System
 *  - 'exam-engine'   : Olama Exam Engine
 *  - 'registration'  : Olama Registration
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_System_Logger {

    // ── Constants ────────────────────────────────────────────────────────────

    const LEVEL_ERROR   = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO    = 'info';
    const LEVEL_DEBUG   = 'debug';

    /** Maximum rows to keep in the DB table before pruning. */
    const MAX_DB_ROWS = 5000;

    /** Days to retain INFO/DEBUG rows automatically. */
    const RETENTION_DAYS = 30;

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Log an ERROR — always written to DB and file.
     *
     * @param string $message Human-readable description.
     * @param string $source  Plugin slug ('school', 'exam-engine', 'registration').
     * @param array  $context Optional key-value pairs (e.g. ['attempt_id' => 5]).
     */
    public static function error( $message, $source = 'school', $context = [] ) {
        self::write( self::LEVEL_ERROR, $message, $source, $context );
    }

    /**
     * Log a WARNING — always written to DB and file.
     */
    public static function warning( $message, $source = 'school', $context = [] ) {
        self::write( self::LEVEL_WARNING, $message, $source, $context );
    }

    /**
     * Log an INFO event — written to DB and file.
     */
    public static function info( $message, $source = 'school', $context = [] ) {
        self::write( self::LEVEL_INFO, $message, $source, $context );
    }

    /**
     * Log a DEBUG message — ONLY written when WP_DEBUG === true.
     */
    public static function debug( $message, $source = 'school', $context = [] ) {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return;
        }
        self::write( self::LEVEL_DEBUG, $message, $source, $context );
    }

    // ── Core Write ───────────────────────────────────────────────────────────

    /**
     * Internal write method. Sanitises context and persists.
     */
    private static function write( $level, $message, $source, $context ) {
        // Sanitize PII before storage
        $context = self::sanitize_context( $context );

        $entry = [
            'level'      => $level,
            'source'     => sanitize_key( $source ),
            'message'    => wp_strip_all_tags( (string) $message ),
            'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
            'user_id'    => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ];

        self::write_to_db( $entry );
        self::write_to_file( $level, $source, $entry['message'], $context );
    }

    // ── DB Storage ───────────────────────────────────────────────────────────

    /**
     * Insert a log entry into wp_olama_system_logs.
     * Silently aborts if the table does not exist yet (e.g. during activation).
     */
    private static function write_to_db( $entry ) {
        global $wpdb;

        $table = $wpdb->prefix . 'olama_system_logs';

        // Emergency brake — stop writing if table is too large.
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
        if ( $count >= self::MAX_DB_ROWS ) {
            // Prune oldest 500 rows to make room.
            $wpdb->query( "DELETE FROM {$table} ORDER BY id ASC LIMIT 500" ); // phpcs:ignore
        }

        $wpdb->insert( $table, $entry ); // phpcs:ignore
    }

    // ── File Storage ─────────────────────────────────────────────────────────

    /**
     * Append a log line to the private log file.
     */
    private static function write_to_file( $level, $source, $message, $context ) {
        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/olama-logs';

        // Bootstrap directory on first write.
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $log_file  = $log_dir . '/olama-system.log';
        $timestamp = gmdate( '[Y-m-d H:i:s]' );
        $ctx_str   = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
        $line      = "{$timestamp} [{$level}] [{$source}] {$message}{$ctx_str}" . PHP_EOL;

        error_log( $line, 3, $log_file ); // phpcs:ignore
    }

    // ── PII Sanitizer ────────────────────────────────────────────────────────

    /**
     * Strip common PII keys from context arrays before logging.
     */
    private static function sanitize_context( $context ) {
        if ( ! is_array( $context ) ) {
            return [];
        }

        $pii_keys = [ 'password', 'pass', 'token', 'secret', 'national_id', 'dob', 'phone' ];

        foreach ( $context as $key => $value ) {
            if ( in_array( strtolower( (string) $key ), $pii_keys, true ) ) {
                $context[ $key ] = '***REDACTED***';
            }
        }

        return $context;
    }

    // ── Admin Utilities ──────────────────────────────────────────────────────

    /**
     * Fetch log entries for the admin UI.
     *
     * @param array $args {
     *     Optional query arguments.
     *     @type string $source  Filter by plugin source slug.
     *     @type string $level   Filter by log level.
     *     @type int    $limit   Number of rows (default 100).
     *     @type int    $offset  Pagination offset (default 0).
     * }
     * @return array Array of log row objects.
     */
    public static function get_logs( $args = [] ) {
        global $wpdb;

        $defaults = [
            'source' => '',
            'level'  => '',
            'limit'  => 100,
            'offset' => 0,
        ];
        $args = wp_parse_args( $args, $defaults );

        $table  = $wpdb->prefix . 'olama_system_logs';
        $where  = [];
        $values = [];

        if ( ! empty( $args['source'] ) ) {
            $where[]  = 'source = %s';
            $values[] = sanitize_key( $args['source'] );
        }

        if ( ! empty( $args['level'] ) ) {
            $where[]  = 'level = %s';
            $values[] = sanitize_key( $args['level'] );
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $limit_sql = $wpdb->prepare( 'LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );

        // Build final query.
        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC {$limit_sql}",
                ...$values
            );
        } else {
            $query = "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC {$limit_sql}"; // phpcs:ignore
        }

        return $wpdb->get_results( $query ); // phpcs:ignore
    }

    /**
     * Count total log entries (respects source/level filters).
     */
    public static function count_logs( $args = [] ) {
        global $wpdb;

        $defaults = [ 'source' => '', 'level' => '' ];
        $args     = wp_parse_args( $args, $defaults );
        $table    = $wpdb->prefix . 'olama_system_logs';
        $where    = [];
        $values   = [];

        if ( ! empty( $args['source'] ) ) {
            $where[]  = 'source = %s';
            $values[] = sanitize_key( $args['source'] );
        }

        if ( ! empty( $args['level'] ) ) {
            $where[]  = 'level = %s';
            $values[] = sanitize_key( $args['level'] );
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", ...$values ) ); // phpcs:ignore
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_sql}" ); // phpcs:ignore
    }

    /**
     * Delete log entries older than a given number of days.
     *
     * @param int $days Number of days to retain.
     * @return int Number of rows deleted.
     */
    public static function prune_logs( $days = 30 ) {
        global $wpdb;

        $table  = $wpdb->prefix . 'olama_system_logs';
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return (int) $wpdb->query( // phpcs:ignore
            $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff )
        );
    }

    /**
     * Truncate ALL log entries (admin only — used by the "Clear All" button).
     */
    public static function clear_all_logs() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}olama_system_logs" ); // phpcs:ignore
    }

    /**
     * Get the absolute path to the log file.
     */
    public static function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/olama-logs/olama-system.log';
    }
}
