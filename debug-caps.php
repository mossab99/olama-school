<?php
require_once('../../../wp-load.php');
if (current_user_can('manage_options')) {
    header('Content-Type: text/plain');
    $author = get_role('author');
    echo "Author Role Object: " . ($author ? "Found" : "Not Found") . "\n";
    if ($author) {
        print_r($author->capabilities);
    }

    $teacher = get_role('teacher');
    echo "\nTeacher Role Object: " . ($teacher ? "Found" : "Not Found") . "\n";
    if ($teacher) {
        print_r($teacher->capabilities);
    }
} else {
    echo "Unauthorized";
}
