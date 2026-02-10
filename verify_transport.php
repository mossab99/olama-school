<?php
require_once('../../../wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'olama_transport_buses';

echo "Checking table: $table\n";
$columns = $wpdb->get_results("DESCRIBE $table");
if ($columns) {
    echo "Table exists. Columns:\n";
    foreach ($columns as $column) {
        echo " - {$column->Field} ({$column->Type})\n";
    }

    // Test Insert
    echo "\nTesting Insert...\n";
    $test_data = array(
        'bus_number' => 'TEST-001',
        'plate_number' => 'ABC-123',
        'passenger_capacity' => 25,
        'status' => 'active'
    );
    $result = $wpdb->insert($table, $test_data);
    if ($result) {
        $id = $wpdb->insert_id;
        echo "Insert successful. ID: $id\n";

        // Test Delete
        echo "Testing Delete...\n";
        $deleted = $wpdb->delete($table, array('id' => $id));
        if ($deleted) {
            echo "Delete successful.\n";
        } else {
            echo "Delete failed: " . $wpdb->last_error . "\n";
        }
    } else {
        echo "Insert failed: " . $wpdb->last_error . "\n";
    }
} else {
    echo "Table not found or error: " . $wpdb->last_error . "\n";

    // Try to trigger table creation if not exists (simulate what happens in init)
    echo "Attempting to create tables via Olama_School_DB...\n";
    require_once('includes/class-db.php');
    $db = new Olama_School_DB();
    $db->create_tables();

    $columns = $wpdb->get_results("DESCRIBE $table");
    if ($columns) {
        echo "Table created successfully.\n";
    } else {
        echo "Table creation failed.\n";
    }
}

// Check if class-bus.php is loadable
echo "\nChecking Olama_School_Bus class...\n";
if (class_exists('Olama_School_Bus')) {
    echo "Olama_School_Bus class exists.\n";
} else {
    echo "Olama_School_Bus class NOT found.\n";
}
