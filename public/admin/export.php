<?php

declare(strict_types=1);

use Workshop\Csv;
use Workshop\Text;

/** @var Workshop\App $app */
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';

$auth = $app->auth();
$auth->start();
$app->noStore();
if ($auth->user() === null) {
    $app->redirect('/admin/');
}
// Release the session lock so a long download does not block other admin pages.
session_write_close();

$repo = $app->submissions();
$stamp = $app->now()->format('Y-m-d_Hi');

$csv = static function (array $rows) use ($app): string {
    $out = Csv::BOM . Csv::row(['ID', 'Version', 'Latest', 'Received', 'Name', 'E-mail', 'Affiliation', 'Title', 'Co-authors',
        'Keywords', 'Abstract', 'Feedback wanted', 'Public programme', 'Consent version', 'File', 'Size (bytes)', 'SHA-256']);
    foreach ($rows as $r) {
        $out .= Csv::row([
            $r['public_id'], $r['version'], $r['is_latest'] ? 'yes' : 'no',
            (new DateTimeImmutable('@' . $r['created_at']))->setTimezone($app->tz)->format('Y-m-d H:i'),
            $r['name'], $r['email'], $r['affiliation'], $r['title'], $r['coauthors'], $r['keywords'], $r['abstract'],
            $r['feedback'], $r['consent_programme'] ? 'yes' : 'no', $r['consent_version'], $r['file_original'],
            $r['file_size'], $r['file_sha256'],
        ]);
    }

    return $out;
};

if (($_GET['format'] ?? '') === 'csv') {
    $app->sendHeaders(200, true, 'text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="submissions_' . $stamp . '.csv"');
    echo $csv($repo->all());
    exit;
}

if (($_GET['format'] ?? '') === 'zip') {
    $latest = $repo->latest();
    $uploads = $app->uploads();
    $tmpDir = $app->storage . '/tmp';
    // Leftovers from earlier exports (e.g. a crash) are removed after an hour.
    foreach (glob($tmpDir . '/*.zip') ?: [] as $old) {
        if (filemtime($old) < time() - 3600) {
            @unlink($old);
        }
    }
    $zipFile = $tmpDir . '/' . bin2hex(random_bytes(8)) . '.zip';
    // Also runs when the organiser cancels the download half-way.
    register_shutdown_function(static fn () => @unlink($zipFile));

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('Could not create ZIP file');
    }
    $missing = [];
    $zip->addFromString('submissions.csv', $csv($latest));
    foreach ($latest as $r) {
        $path = $uploads->path($r['file_stored']);
        $name = sprintf('%s_%s_%s.pdf', $r['public_id'], Text::slug($r['name'], 30), Text::slug($r['title'], 40));
        if (!is_file($path) || !$zip->addFile($path, $name)) {
            $missing[] = $r['public_id'];
        }
    }
    if ($missing !== [] || !$zip->close()) {
        $app->sendHeaders(500, true, 'text/plain; charset=utf-8');
        exit('The ZIP could not be built' . ($missing !== [] ? ' – files missing for: ' . implode(', ', $missing) : '') . ". Nothing was downloaded.\n");
    }

    $app->sendHeaders(200, true, 'application/zip');
    header('Content-Disposition: attachment; filename="papers_' . $stamp . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    exit;
}

$app->sendHeaders(400, true, 'text/plain; charset=utf-8');
echo "Unknown format.\n";
