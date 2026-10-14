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
        'title' => 'Paper Development Workshop on Data Studies', // marketed as a one-day PDW (feedback 13 Oct); final wording TBA
        'short_title' => 'HSG Paper Development Workshop 2026',  // used in e-mail subjects and the page header
        'tagline' => 'A one-day workshop for doctoral students in two tracks: Data Platforms and Ecosystems, and Data Governance.',
        // Second half of the hero statement, shown in grey after the tagline ('' for none).
        'tagline_more' => 'Each accepted paper receives focused feedback from senior faculty in a small group.',
        'date' => '2026-12-03',                                 // confirmed (feedback 13 Oct)
        'time' => 'Agenda to be announced',                     // exact agenda and times to be announced
        'format' => 'In person',                                // TBA – hybrid?
        'fee' => 'Free of charge',                              // TBA
        'language' => 'English',
        'host' => 'Chair of Prof. Dr. Jan Marco Leimeister, University of St.Gallen (HSG)', // TBA – exact unit name
    ],

    // Short paragraphs for the "About" section. DRAFT wording – replace with the organisers' call for papers.
    'about' => [
        'This one-day paper development workshop (PDW) gives doctoral students the opportunity to develop a working paper together with experienced faculty and peers. Each accepted paper receives focused, constructive feedback in a small-group setting.',
        'Submissions are extended abstracts and are assigned to one of two tracks: Data Platforms and Ecosystems, or Data Governance. Each track is chaired by two senior faculty members. [TBA: call for papers text and the thematic scope of each track.]',
    ],

    'submission' => [
        // How extended abstracts are submitted (14 Oct): 'email' = authors send the PDF to
        // 'email' below and the page shows instructions instead of the upload form;
        // 'form' = the upload form with server-side checks, admin area and confirmation mails.
        'method' => 'email',
        'email' => 'adrian.bohrer@unisg.ch',
        'email_subject' => 'PDW 2026 - extended abstract',     // ASCII only: some mail clients garble non-ASCII mailto subjects
        // Master switch. In form mode the live site additionally needs privacy.approved = true.
        'enabled' => true,
        'opens_at' => null,                                     // optional, e.g. '2026-10-14 12:00'
        'deadline' => '2026-11-05 23:59',                       // date confirmed (feedback 13 Oct); 23:59 is our default – shown as "23:59 Zurich time (CET, UTC+01:00)"
        // false shows "Provisional" next to the deadline. Set to true once the organisers confirmed it.
        'deadline_confirmed' => true,
        // Uploads within this many minutes after the deadline are still accepted (not shown on the site).
        'grace_minutes' => 5,
        'notification_date' => '2026-11-12',                    // confirmed (feedback 13 Oct)
        'max_file_mb' => 10,
        'what_to_submit' => [                                   // length 2 pages (Leon, 14 Oct); TBA – anonymised? The delivery line (e-mail or upload) is added by templates/home.php.
            'An extended abstract of max. 2 pages, structured with the Emerald template (headings below).',
            'Non-archival: there are no proceedings. Papers under review elsewhere are welcome.',
        ],
        // Required structure of the extended abstract: the Emerald structured abstract, as at WISIC
        // (Leon, 14 Oct). Heading => short guiding question (our own wording).
        'abstract_template' => [
            'name' => 'Emerald template',
            'sections' => [
                'Purpose' => 'Why this research? Its aims and core research questions.',
                'Design/methodology/approach' => 'How the aims are achieved: the main methods, the approach and the theoretical scope.',
                'Findings/results so far' => 'What you have found so far, from analysis, discussion or results.',
                'Research limitations/implications so far' => 'Known limitations and suggestions for future research.',
                'Practical implications' => 'What the research means for practice and what should change.',
                'Originality' => 'What is new: the contribution of the paper and for whom it matters.',
            ],
        ],
        'abstract_required' => true,
        'abstract_max_chars' => 2000,
        // Safety net against abuse: no new uploads beyond this many (incl. earlier versions).
        'max_uploads_total' => 400,
    ],

    /*
     * Programme in the format of the CHAIS PDW Bern (see material/): joint
     * items, two parallel paper tracks and a panel with the senior faculty; no
     * keynote and no 1:1 coaching sessions (feedback 13 Oct). The former keynote
     * time is used for a third paper session in the afternoon (feedback 14 Oct).
     * kind: 'plenary' (default), 'break' or 'papers' (slots per track id).
     * DRAFT – the exact agenda, times and rooms are to be announced.
     */
    'programme_note' => 'Provisional outline – the exact agenda and the rooms will be announced. Paper sessions run in two parallel tracks; each paper receives feedback from the track chairs and peers.',
    'tracks' => [
        't1' => ['name' => 'Track 1: Data Platforms and Ecosystems', 'room' => 'Room TBA', 'chair' => 'Jan Marco Leimeister, Andreas Hein'], // replaces "Data & Digital Sovereignty" (14 Oct)
        't2' => ['name' => 'Track 2: Data Governance', 'room' => 'Room TBA', 'chair' => 'Christine Legner, Aleksi Aaltonen'],
    ],
    'programme' => [
        ['time' => '08:30', 'end' => '09:00', 'title' => 'Arrival', 'kind' => 'break'],
        ['time' => '09:00', 'end' => '09:15', 'title' => 'Opening remarks'],
        ['time' => '09:15', 'end' => '10:45', 'title' => 'Paper sessions I', 'kind' => 'papers', 'slots' => [
            't1' => [$slot('09:15', '09:45'), $slot('09:45', '10:15'), $slot('10:15', '10:45')],
            't2' => [$slot('09:15', '09:45'), $slot('09:45', '10:15'), $slot('10:15', '10:45')],
        ]],
        ['time' => '10:45', 'end' => '11:15', 'title' => 'Coffee break', 'kind' => 'break'],
        ['time' => '11:15', 'end' => '12:45', 'title' => 'Paper sessions II', 'kind' => 'papers', 'slots' => [
            't1' => [$slot('11:15', '11:45'), $slot('11:45', '12:15'), $slot('12:15', '12:45')],
            't2' => [$slot('11:15', '11:45'), $slot('11:45', '12:15'), $slot('12:15', '12:45')],
        ]],
        ['time' => '12:45', 'end' => '13:45', 'title' => 'Lunch break', 'kind' => 'break', 'detail' => 'Location TBA'],
        ['time' => '13:45', 'end' => '15:15', 'title' => 'Paper sessions III', 'kind' => 'papers', 'slots' => [
            't1' => [$slot('13:45', '14:15'), $slot('14:15', '14:45'), $slot('14:45', '15:15')],
            't2' => [$slot('13:45', '14:15'), $slot('14:15', '14:45'), $slot('14:45', '15:15')],
        ]],
        ['time' => '15:15', 'end' => '15:30', 'title' => 'Coffee break', 'kind' => 'break'],
        ['time' => '15:30', 'end' => '16:15', 'title' => 'Panel with the senior faculty', 'detail' => 'Aleksi Aaltonen, Andreas Hein, Christine Legner and Jan Marco Leimeister · moderated by Andreas Hein'],
        ['time' => '16:15', 'end' => '16:30', 'title' => 'Final remarks'],
        ['time' => '16:30', 'end' => '', 'title' => 'Apéro and farewell', 'kind' => 'break'],
    ],

    // Senior faculty who attend, alphabetical by surname. 'role' is shown next to the
    // name unless it is the plain 'Senior faculty'.
    // 'photo' is optional: a file in public/assets/img/ (only with the person's permission).
    'faculty' => [
        // Affiliation from his Stevens profile (Oct 2026) – please confirm with him.
        ['name' => 'Prof. Dr. Aleksi Aaltonen', 'affiliation' => 'Stevens Institute of Technology', 'role' => 'Track 2 chair', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Andreas Hein', 'affiliation' => 'University of St.Gallen', 'role' => 'Track 1 chair · Panel moderator', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Christine Legner', 'affiliation' => 'HEC Lausanne, University of Lausanne', 'role' => 'Track 2 chair', 'bio' => '', 'photo' => ''],
        ['name' => 'Prof. Dr. Jan Marco Leimeister', 'affiliation' => 'University of St.Gallen', 'role' => 'Host · Track 1 chair', 'bio' => '', 'photo' => ''],
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
        'recipients' => 'The organisers and the four senior faculty: Aleksi Aaltonen (Stevens Institute of Technology, USA), Andreas Hein and Jan Marco Leimeister (University of St.Gallen), Christine Legner (HEC Lausanne). Accepted papers are shared confidentially with the other workshop participants. [TBA: disclosure abroad (USA) and its legal basis, to be reviewed by the data protection office.]',
        'delete_after' => '2027-03-03',                         // TBA – e.g. three months after the event
    ],

    'imprint' => [
        'publisher' => 'University of St.Gallen (HSG), [TBA: unit]',
        'address' => 'Dufourstrasse 50, 9000 St.Gallen, Switzerland',
        'responsible' => 'Prof. Dr. Jan Marco Leimeister',      // TBA
        'email' => 'workshop@example.org',                      // TBA
    ],
];
