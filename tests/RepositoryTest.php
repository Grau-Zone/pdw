<?php

declare(strict_types=1);

use Workshop\Database;
use Workshop\RateLimiter;
use Workshop\SubmissionRepository;

$pdo = Database::open(temp_dir() . '/test.sqlite');
$repo = new SubmissionRepository($pdo);
$values = static fn (string $email, string $title = 'T'): array => [
    'name' => 'Anna', 'email' => $email, 'affiliation' => 'HSG', 'title' => $title, 'coauthors' => '', 'keywords' => '',
    'abstract' => '', 'feedback' => '', 'consent_privacy' => true, 'consent_share' => true, 'consent_programme' => false,
];
$file = static fn (string $sha): array => ['stored' => str_repeat('a', 32) . '.pdf', 'size' => 10, 'sha256' => $sha, 'original' => 'p.pdf'];

return [
    'creates submissions with readable IDs' => function () use ($repo, $values, $file) {
        $s = $repo->create($values('anna@example.org'), $file('h1'), 'v1', 1000);
        truthy(SubmissionRepository::isPublicId($s['public_id']), 'bad id ' . $s['public_id']);
        same(1, (int) $s['version']);
    },
    'resubmission with the same address is a new version, case-insensitive' => function () use ($repo, $values, $file) {
        $s = $repo->create($values('Anna@Example.org', 'T2'), $file('h2'), 'v1', 2000);
        same(2, (int) $s['version']);
        $latest = $repo->latest();
        same(1, count($latest));
        same('T2', $latest[0]['title']);
        same(2, count($repo->all()));
    },
    'identical resend (reload) is recognised as duplicate' => function () use ($repo, $values) {
        truthy($repo->findRecentDuplicate($values('Anna@Example.org', 'T2'), 'h2', 2300) !== null, 'duplicate not found');
        same(null, $repo->findRecentDuplicate($values('Anna@Example.org', 'T2'), 'h2', 2000 + 601), 'outside the window');
        same(null, $repo->findRecentDuplicate($values('other@example.org', 'T2'), 'h2', 2300), 'other address');
    },
    'a correction with the same file is a new version, not a duplicate' => function () use ($repo, $values) {
        same(null, $repo->findRecentDuplicate($values('Anna@Example.org', 'T2 corrected'), 'h2', 2300), 'changed title');
        $withdrawn = ['consent_programme' => true] + $values('Anna@Example.org', 'T2');
        same(null, $repo->findRecentDuplicate($withdrawn, 'h2', 2300), 'changed consent');
    },
    'resending an older version is not mistaken for a duplicate' => function () use ($repo, $values) {
        same(null, $repo->findRecentDuplicate($values('anna@example.org'), 'h1', 1100), 'v1 is no longer the latest');
    },
    'IPv6 clients are grouped by /64, IPv4-mapped addresses by IPv4' => function () {
        same(RateLimiter::network('2001:db8:1:2:aaaa::1'), RateLimiter::network('2001:db8:1:2:ffff::9'));
        truthy(RateLimiter::network('2001:db8:1:2::1') !== RateLimiter::network('2001:db8:1:3::1'), 'different /64 merged');
        same('192.0.2.7', RateLimiter::network('::ffff:192.0.2.7'));
        same('192.0.2.7', RateLimiter::network('192.0.2.7'));
    },
    'rate limiter blocks after the limit and forgets old hits' => function () use ($pdo) {
        $limiter = new RateLimiter($pdo, str_repeat('s', 32));
        for ($i = 0; $i < 3; $i++) {
            truthy($limiter->allow('t', '1.2.3.4', 3, 60, 5000 + $i), "hit $i blocked");
        }
        truthy(!$limiter->allow('t', '1.2.3.4', 3, 60, 5010), 'fourth hit allowed');
        truthy($limiter->allow('t', '5.6.7.8', 3, 60, 5010), 'other IP blocked');
        truthy($limiter->allow('t', '1.2.3.4', 3, 60, 5100), 'not released after window');
        same(0, (int) $pdo->query("SELECT COUNT(*) FROM hits WHERE bucket LIKE '%1.2.3.4%'")->fetchColumn(), 'raw IP stored');
    },
];
