<?php

declare(strict_types=1);

use Workshop\PdfUpload;

$dir = temp_dir();
$uploads = new PdfUpload($dir, 1048576, false);
$file = static function (string $content, int $error = UPLOAD_ERR_OK) use ($dir): array {
    $tmp = $dir . '/tmp-' . bin2hex(random_bytes(4));
    file_put_contents($tmp, $content);

    return ['name' => 'paper.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => $error, 'size' => strlen($content)];
};

return [
    'accepts a real PDF' => function () use ($uploads, $file) {
        same(null, $uploads->validate($file(minimal_pdf())));
    },
    'rejects a text file named .pdf' => function () use ($uploads, $file) {
        truthy($uploads->validate($file("Hello, I am not a PDF\n")) !== null, 'text accepted');
    },
    'rejects HTML disguised with a PDF header' => function () use ($uploads, $file) {
        truthy($uploads->validate($file("<html><script>alert(1)</script>%PDF-1.4")) !== null, 'html accepted');
    },
    'rejects an empty file' => function () use ($uploads, $file) {
        same('The uploaded file is empty.', $uploads->validate($file('')));
    },
    'rejects a file above the limit' => function () use ($uploads, $file) {
        same($uploads->tooLargeMessage(), $uploads->validate($file(minimal_pdf() . str_repeat('x', 1048576))));
    },
    'maps PHP upload errors to messages' => function () use ($uploads, $file) {
        same($uploads->tooLargeMessage(), $uploads->validate($file('', UPLOAD_ERR_INI_SIZE)));
        same('Please attach your paper as a PDF file.', $uploads->validate($file('', UPLOAD_ERR_NO_FILE)));
    },
    'rejects missing or array-shaped file fields' => function () use ($uploads) {
        truthy($uploads->validate(null) !== null, 'null accepted');
        truthy($uploads->validate(['error' => [0], 'tmp_name' => ['x']]) !== null, 'array upload accepted');
    },
    'stores under a random name and never uses the client name' => function () use ($uploads, $file, $dir) {
        $upload = $file(minimal_pdf());
        $upload['name'] = '../../evil.php';
        $stored = $uploads->store($upload);
        truthy(PdfUpload::isStoredName($stored['stored']), 'unexpected stored name ' . $stored['stored']);
        truthy(is_file($dir . '/' . $stored['stored']), 'stored file missing');
        same(hash('sha256', minimal_pdf()), $stored['sha256']);
    },
    'path() refuses anything but a stored name' => function () use ($uploads) {
        foreach (['../config/env.php', 'abc.pdf', str_repeat('a', 32) . '.php'] as $bad) {
            try {
                $uploads->path($bad);
            } catch (InvalidArgumentException) {
                continue;
            }
            throw new RuntimeException("path() accepted '$bad'");
        }
    },
];
