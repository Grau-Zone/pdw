<?php

declare(strict_types=1);

/*
 * Static preview for people without PHP (e.g. to send to Leon for feedback):
 * renders the public pages to plain HTML that opens with a double-click.
 * In form mode the submit button is disabled and a banner says so; in e-mail mode the
 * export is the public site: no draft banner and a working mailto link.
 *   php bin/export-preview.php                                    ->  export/workshop-vorschau.zip
 *   php bin/export-preview.php https://grau-zone.github.io/pdw/   (address where the preview is published)
 */

use Workshop\App;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot();
$root = $app->root;
$publicUrl = $argv[1] ?? null;
$out = $root . '/export/vorschau';
$zipFile = $root . '/export/workshop-vorschau.zip';

// Fresh output directory.
if (is_dir($out)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}
mkdir($out . '/assets/fonts', 0750, true);

$pages = [
    'index.html' => ['home', []],
    'privacy.html' => ['privacy', ['pageTitle' => 'Privacy notice']],
    'imprint.html' => ['imprint', ['pageTitle' => 'Imprint']],
];
$links = ['privacy.php' => 'privacy.html', 'imprint.php' => 'imprint.html', 'submit.php' => '#submit'];

foreach ($pages as $file => [$template, $vars]) {
    ob_start();
    $app->page($template, $vars);
    $html = (string) ob_get_clean();

    // Site-absolute links become relative so the files work from any folder.
    $html = (string) preg_replace_callback('/\b(href|src|action)="\/([^"]*)"/', static function (array $m) use ($links): string {
        [$path, $fragment] = array_pad(explode('#', $m[2], 2), 2, null);
        $path = (string) preg_replace('/\?.*$/', '', (string) $path);
        $target = $path === '' ? 'index.html' : ($links[$path] ?? $path);
        if ($fragment !== null) {
            $target = ($path === '' && $m[1] !== 'href' ? '' : $target) . '#' . $fragment;
        }

        return $m[1] . '="' . $target . '"';
    }, $html);

    // Link previews (og:url) should point to where the preview is published, not to localhost.
    if ($publicUrl !== null) {
        $html = str_replace(e($app->absoluteUrl('/')), e($publicUrl), $html);
    }

    if ($app->submissionsByEmail()) {
        // With e-mail submissions the exported pages are the public site (online since 14 Oct 2026):
        // no draft banner, the mailto link works.
        $html = (string) preg_replace('#<div class="draftbar"[^>]*>.*?</div>\s*#s', '', $html);
    } else {
        // The preview cannot submit anything through a form – say so and disable the button.
        $html = str_replace(
            'Draft · content not final · please do not submit real papers yet',
            'Preview · draft content · the form does not work in this preview',
            $html
        );
    }
    // Robust against extra attributes on the button (e.g. data-magnetic for the effects).
    $html = (string) preg_replace('/type="submit"([^>]*)><span data-label>/', 'type="button" disabled$1><span data-label>', $html);
    file_put_contents("$out/$file", $html);
}

foreach (['fonts.css', 'site.css', 'app.js', 'favicon.svg'] as $asset) {
    copy("$root/public/assets/$asset", "$out/assets/$asset");
}
foreach (['fonts', 'img', 'fx'] as $dir) {
    @mkdir("$out/assets/$dir", 0750, true);
    foreach (glob("$root/public/assets/$dir/*") ?: [] as $file) {
        copy($file, "$out/assets/$dir/" . basename($file));
    }
}

@unlink($zipFile);
$zip = new ZipArchive();
$zip->open($zipFile, ZipArchive::CREATE);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)) as $f) {
    $zip->addFile($f->getPathname(), 'workshop-vorschau/' . str_replace('\\', '/', substr($f->getPathname(), strlen($out) + 1)));
}
$zip->close();

printf("Preview: %s/index.html\nZIP:     %s (%d KB)\n", $out, $zipFile, (int) round(filesize($zipFile) / 1024));
