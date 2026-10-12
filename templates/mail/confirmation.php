<?php
/**
 * Plain-text e-mail – no HTML escaping here. Built line by line so PHP's
 * closing tags cannot swallow line breaks. The recipient address is not
 * verified, so user text is shortened and stripped of links.
 *
 * @var Workshop\App $app
 * @var array $event
 * @var array $submission
 */

use Workshop\Dates;
use Workshop\Text;

$received = (new DateTimeImmutable('@' . $submission['created_at']))->setTimezone($app->tz);
$lines = [
    'Dear author,',
    '',
    sprintf('thank you for your submission to the %s (%s, St.Gallen).', $event['event']['title'], Dates::day(Dates::parse($event['event']['date'], $app->tz))),
    '',
    'Submission ID: ' . $submission['public_id'],
    'Title:         ' . Text::defang($submission['title']),
    'File:          ' . Text::defang($submission['file_original'], 80) . ' (' . Text::fileSize((int) $submission['file_size']) . ')',
    'Received:      ' . Dates::withZone($received),
    '',
];
if ((int) $submission['version'] > 1) {
    $lines[] = "This is version {$submission['version']} of your submission; it replaces the earlier one.";
    $lines[] = 'If you did not submit this version yourself, please reply to this e-mail.';
    $lines[] = '';
}
array_push(
    $lines,
    'To update your paper, submit it again with this e-mail address before the deadline',
    '(' . Dates::withZone($app->gate()->deadline()) . '). The latest version counts.',
    '',
    'We will let you know by ' . Dates::day(Dates::parse($event['submission']['notification_date'], $app->tz)) . '.',
    '',
    'Questions? Simply reply to this e-mail.',
    '',
    'Best regards',
    $event['contact']['people'],
    $app->absoluteUrl('/'),
);

echo implode("\n", $lines), "\n";
