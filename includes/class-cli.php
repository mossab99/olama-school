<?php
/**
 * WP-CLI Commands for Olama School System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_CLI
{
    /**
     * Generate a database backup
     *
     * ## OPTIONS
     *
     * [--file=<filename>]
     * : The filename to save the backup to.
     *
     * ## EXAMPLES
     *
     *     wp olama backup --file=my-backup.json
     *
     * @when after_wp_load
     */
    public function backup($args, $assoc_args)
    {
        WP_CLI::line('Generating Olama School backup...');

        $backup_data = Olama_School_Backup::generate_backup();
        $json_data = json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = $assoc_args['file'] ?? 'olama-backup-' . current_time('Y-m-d-His') . '.json';

        if (file_put_contents($filename, $json_data)) {
            WP_CLI::success("Backup saved to: $filename");
        } else {
            WP_CLI::error("Failed to save backup to: $filename");
        }
    }

    /**
     * Restore a database backup from a JSON file
     *
     * ## EXAMPLES
     *
     *     wp olama restore my-backup.json
     *
     * @when after_wp_load
     */
    public function restore($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Please specify the backup file to restore.');
        }

        $file = $args[0];

        if (!file_exists($file)) {
            WP_CLI::error("Backup file not found: $file");
        }

        WP_CLI::confirm("Are you sure you want to restore from $file? This will overwrite all CURRENT school data.");

        WP_CLI::line("Reading backup file...");
        $json_data = file_get_contents($file);

        WP_CLI::line("Processing restoration (this may take a while)...");
        $result = Olama_School_Backup::restore_backup($json_data);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        } else {
            WP_CLI::success('Database restoration completed successfully!');
        }
    }
}
