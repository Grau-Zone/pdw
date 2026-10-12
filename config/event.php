<?php

/*
 * All content, dates and rules of the site in one place.
 *
 * Anything marked TBA still has to be confirmed by the organisers – see
 * "Offene Punkte" in README.md. Dates are local time in 'timezone'
 * (format YYYY-MM-DD or YYYY-MM-DD HH:MM).
 */

// One paper slot in the programme. Leave presenter/title empty until papers are selected.
$slot = static fn (string $from, string $to, string $presenter = '', string $affiliation = '', string $title = ''): array =>
    ['time' => $from, 'end' => $to, 'presenter' => $presenter, 'affiliation' => $affiliation, 'title' => $title];

return [
    // Shows a "draft" banner and keeps search engines away. Switch off for the public launch.
    'draft' => true,

    'timezone' => 'Europe/Zurich',

    'event' => [
        'title' => 'Doctoral Seminar on Data Studies',          // TBA – working title ("PDW" vs. "doctoral seminar")
        'title_highlight' => 'Data Studies',                    // part of the title shown in the accent colour ('' for none)
        'short_title' => 'HSG Doctoral Seminar 2026',           // used in e-mail subjects
        'tagline' => 'A one-day paper development workshop for doctoral students', // TBA
        'date' => '2026-11-13',                                 // TBA – final decision on Mon 12 Oct
        'time' => '09:00–17:00',                                // TBA
        'format' => 'In person',                                // TBA – hybrid?
        'fee' => 'Free of charge',                              // TBA
        'language' => 'English',
        'host' => 'Chair of Prof. Dr. Jan Marco Leimeister, University of St.Gallen (HSG)', // TBA – exact unit name
    ],

    // Short paragraphs for the "About" section. DRAFT wording – replace with the organisers' call for papers.
    'about' => [
        'This one-day seminar gives doctoral students the opportunity to develop a working paper together with experienced faculty and peers. Each accepted paper receives focused, constructive feedback in a small-group setting.',
        'We welcome papers at different stages – from an early extended abstract to a full draft that is about to be submitted. [TBA: thematic scope, e.g. data studies in information systems research.]',
    ],

    'submission' => [
        // Master switch. On the live site the form additionally needs privacy.approved = true.
        'enabled' => true,
        'opens_at' => null,                                     // optional, e.g. '2026-10-14 12:00'
        'deadline' => '2026-10-30 23:59',                       // TBA – shown as "23:59 Zurich time (CET, UTC+01:00)"
        // Uploads within this many minutes after the deadline are still accepted (not shown on the site).
        'grace_minutes' => 5,
        'notification_date' => '2026-11-03',                    // TBA
        'max_file_mb' => 10,
        'what_to_submit' => [                                   // TBA – format, page limit, template, anonymised?
            'A full paper or an extended abstract (max. [TBA] pages, any citation style).',
            'One PDF file per submission, max. 10 MB.',
            'Non-archival: there are no proceedings. Papers under review elsewhere are welcome.',
        ],
        'abstract_required' => true,
        'abstract_max_chars' => 2000,
        // Safety net against abuse: no new uploads beyond this many (incl. earlier versions).
        'max_uploads_total' => 400,
    ],

    /*
     * Programme in the format of the CHAIS PDW Bern (see material/): joint
     * items, two parallel paper tracks, keynote and group work. All paper
     * sessions are before lunch so that faculty available only in the morning can chair a track.
     * kind: 'plenary' (default), 'break' or 'papers' (slots per track id).
     * DRAFT – times, rooms, chairs and keynote are TBA.
     */
    'programme_note' => 'Paper sessions run in two parallel tracks. Each paper has 30 minutes: [TBA] minutes presentation and [TBA] minutes of feedback from faculty and peers.',
    'tracks' => [
        't1' => ['name' => 'Track 1', 'room' => 'Room TBA', 'chair' => 'Chair TBA'],
        't2' => ['name' => 'Track 2', 'room' => 'Room TBA', 'chair' => 'Chair TBA'],
    ],
    'programme' => [
        ['time' => '08:30', 'end' => '09:00', 'title' => 'Arrival', 'kind' => 'break'],
        ['time' => '09:00', 'end' => '09:20', 'title' => 'Opening remarks and introduction', 'detail' => 'Faculty TBA'],
        ['time' => '09:30', 'end' => '11:00', 'title' => 'Paper sessions I', 'kind' => 'papers', 'slots' => [
            't1' => [$slot('09:30', '10:00'), $slot('10:00', '10:30'), $slot('10:30', '11:00')],
            't2' => [$slot('09:30', '10:00'), $slot('10:00', '10:30'), $slot('10:30', '11:00')],
        ]],
        ['time' => '11:00', 'end' => '11:30', 'title' => 'Coffee break', 'kind' => 'break'],
        ['time' => '11:30', 'end' => '12:30', 'title' => 'Paper sessions II', 'kind' => 'papers', 'slots' => [
            't1' => [$slot('11:30', '12:00'), $slot('12:00', '12:30')],
            't2' => [$slot('11:30', '12:00'), $slot('12:00', '12:30')],
        ]],
        ['time' => '12:45', 'end' => '14:15', 'title' => 'Lunch break', 'kind' => 'break', 'detail' => 'Location TBA'],
        ['time' => '14:15', 'end' => '14:45', 'title' => 'Keynote', 'detail' => 'Speaker and topic TBA'],
        ['time' => '14:45', 'end' => '16:45', 'title' => 'Group work and 1:1 meetings with senior scholars', 'detail' => 'Room TBA'],
        ['time' => '16:45', 'end' => '17:00', 'title' => 'Final remarks'],
        ['time' => '17:00', 'end' => '', 'title' => 'Apéro and farewell', 'kind' => 'break'],
    ],

    // Senior faculty who attend, alphabetical by surname.
    // 'photo' is optional: a file in public/assets/img/ (only with the person's permission).
    'faculty' => [
        // Affiliation from his Stevens profile (Oct 2026) – please confirm with him.
        ['name' => 'Prof. Dr. Aleksi Aaltonen', 'affiliation' => 'Stevens Institute of Technology', 'role' => 'Senior faculty', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Andreas Hein', 'affiliation' => 'University of St.Gallen', 'role' => 'Senior faculty', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Christine Legner', 'affiliation' => 'HEC Lausanne, University of Lausanne', 'role' => 'Senior faculty', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Jan Marco Leimeister', 'affiliation' => 'University of St.Gallen', 'role' => 'Host', 'bio' => '', 'photo' => ''],
    ],

    'venue' => [
        'name' => 'University of St.Gallen (HSG)',
        'room' => 'Room TBA',                                   // after the HSG event request is approved
        'address' => 'TBA, 9000 St.Gallen, Switzerland',
        'map_url' => '',                                        // e.g. an OpenStreetMap link
        'travel' => 'St.Gallen is about one hour by train from Zurich Airport. [TBA: directions to the building.]',
    ],

    'contact' => [
        // A function mailbox, not a personal address.
        'email' => 'workshop@example.org',                      // TBA
        'people' => 'Leon Müller (organiser)',                  // TBA
    ],

    'privacy' => [
        // Set to true only after the HSG data protection office approved the texts.
        // While false, the live site (draft = false) does not accept submissions.
        'approved' => false,
        'version' => '2026-10-draft',                           // stored with every submission's consents
        'controller' => 'University of St.Gallen (HSG), [TBA: unit], Dufourstrasse 50, 9000 St.Gallen, Switzerland',
        'dpo_email' => 'datenschutz@unisg.ch',
        'hosting' => '[TBA: hosting provider], data centres in Switzerland',
        'recipients' => 'The organisers and the workshop faculty (TBA: names and institutions). Accepted papers are shared confidentially with the other workshop participants.',
        'delete_after' => '2027-02-13',                         // TBA – e.g. three months after the event
    ],

    'imprint' => [
        'publisher' => 'University of St.Gallen (HSG), [TBA: unit]',
        'address' => 'Dufourstrasse 50, 9000 St.Gallen, Switzerland',
        'responsible' => 'Prof. Dr. Jan Marco Leimeister',      // TBA
        'email' => 'workshop@example.org',                      // TBA
    ],
];
