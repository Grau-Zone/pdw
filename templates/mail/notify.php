<?php
/**
 * Short note to the organisers – deliberately without the paper or abstract.
 *
 * @var Workshop\App $app
 * @var array $submission
 */

use Workshop\Text;

$lines = [
    'New submission ' . $submission['public_id']
        . ((int) $submission['version'] > 1 ? " (version {$submission['version']}, replaces an earlier upload)" : ''),
    '',
    'Name:        ' . Text::defang($submission['name']),
    'Affiliation: ' . Text::defang($submission['affiliation']),
    'Title:       ' . Text::defang($submission['title']),
    '',
    'All submissions: ' . $app->absoluteUrl('/admin/'),
];

echo implode("\n", $lines), "\n";
