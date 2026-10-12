<?php

declare(strict_types=1);

use Workshop\Dates;
use Workshop\DeadlineGate;

$tz = new DateTimeZone('Europe/Zurich');
$at = static fn (string $utc): DateTimeImmutable => new DateTimeImmutable($utc, new DateTimeZone('UTC'));
$gate = static fn (array $overrides = [], bool $on = true): DeadlineGate => DeadlineGate::fromConfig(
    $overrides + ['deadline' => '2026-10-30 23:59', 'grace_minutes' => 5, 'opens_at' => null],
    $tz,
    $on
);

return [
    'deadline after the DST change is CET (UTC+1)' => function () use ($gate) {
        same('2026-10-30T22:59:59+00:00', $gate()->deadline()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
    },
    'deadline before the DST change is CEST (UTC+2)' => function () use ($gate) {
        same('2026-10-24T21:59:59+00:00', $gate(['deadline' => '2026-10-24 23:59'])->deadline()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
    },
    'display names the zone and offset' => function () use ($gate) {
        same('Friday, 30 October 2026, 23:59 Zurich time (CET, UTC+01:00)', Dates::withZone($gate()->deadline()));
    },
    'a bare date means end of that day' => function () use ($gate) {
        same('23:59:59', $gate(['deadline' => '2026-10-30'])->deadline()->format('H:i:s'));
    },
    'open and accepted during the last minute' => function () use ($gate, $at) {
        same(DeadlineGate::OPEN, $gate()->state($at('2026-10-30T22:59:30Z')));
        truthy($gate()->accepts($at('2026-10-30T22:59:59Z')), 'should accept at 23:59:59');
    },
    'grace period: shown as closed but still accepted' => function () use ($gate, $at) {
        $g = $gate();
        same(DeadlineGate::CLOSED, $g->state($at('2026-10-30T23:02:00Z')));
        truthy($g->accepts($at('2026-10-30T23:04:59Z')), 'should accept within grace');
        truthy(!$g->accepts($at('2026-10-30T23:05:00Z')), 'should reject after grace');
    },
    'no grace period' => function () use ($gate, $at) {
        truthy(!$gate(['grace_minutes' => 0])->accepts($at('2026-10-30T23:00:00Z')), 'should reject one second late');
    },
    'switched off: never accepts' => function () use ($gate, $at) {
        $g = $gate([], false);
        same(DeadlineGate::DISABLED, $g->state($at('2026-10-20T10:00:00Z')));
        truthy(!$g->accepts($at('2026-10-20T10:00:00Z')), 'should not accept when switched off');
    },
    'opens_at keeps the form closed until then' => function () use ($gate, $at) {
        $g = $gate(['opens_at' => '2026-10-14 12:00']);
        same(DeadlineGate::NOT_OPEN, $g->state($at('2026-10-14T09:59:00Z')));
        truthy(!$g->accepts($at('2026-10-14T09:59:00Z')), 'should not accept before opening');
        same(DeadlineGate::OPEN, $g->state($at('2026-10-14T10:00:00Z')));
    },
    'typos in dates fail loudly' => function () use ($tz) {
        foreach (['2026-02-30 12:00', '30.10.2026', '2026-10-30 24:00', ''] as $bad) {
            try {
                Dates::parse($bad, $tz);
            } catch (InvalidArgumentException) {
                continue;
            }
            throw new RuntimeException("accepted invalid date '$bad'");
        }
    },
];
