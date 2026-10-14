<?php

declare(strict_types=1);

/*
 * Deletes all submissions, uploaded papers, mails, sessions and logs.
 *   php bin/purge.php            shows what would be deleted
 *   php bin/purge.php --confirm  deletes it
 * Remember the hosting provider's backups (Infomaniak keeps 7 days) and any
 * ZIP exports organisers downloaded to their own computers.
 */

use Workshop\App;
use Workshop\Dates;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot();
$confirm = in_array('--confirm', $argv, true);

$deleteAfter = Dates::parse($app->event['privacy']['delete_after'], $app->tz);
echo 'Deletion date in the privacy notice: ' . Dates::day($deleteAfter) . "\n";
if ($app->now() < $deleteAfter->modify('-60 days')) {
    echo "Note: that is still more than 60 days away – are you sure the workshop is over?\n";
}

$files = [];
foreach (['uploads', 'db', 'mail-outbox', 'sessions', 'tmp', 'logs'] as $dir) {
    $path = $app->storage . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = $file->getPathname();
    }
}

echo count($files) . " files in storage/.\n";
if (!$confirm) {
    echo "Dry run – nothing deleted. Run with --confirm to delete.\n";
    exit(0);
}

$failed = 0;
foreach ($files as $file) {
    if (!@unlink($file)) {
        $failed++;
        echo "Could not delete $file\n";
    }
}
echo $failed === 0 ? "All data deleted.\n" : "$failed files could not be deleted.\n";
exit($failed === 0 ? 0 : 1);
