<?php

declare(strict_types=1);

use Workshop\Csv;
use Workshop\FormGuard;
use Workshop\SubmissionForm;
use Workshop\Text;

$guard = new FormGuard(str_repeat('k', 32));
$form = new SubmissionForm(2000, true);
$valid = [
    'name' => '  Anna   Muster ', 'email' => 'anna@example.org', 'affiliation' => 'University of St.Gallen',
    'title' => 'A paper', 'abstract' => "Line one\r\n\r\n\r\n\r\nLine two", 'consent_privacy' => '1', 'consent_share' => '1',
];

return [
    'valid token after a few seconds passes' => function () use ($guard) {
        same(null, $guard->check($guard->issue(1000), '', 1010));
    },
    'too fast, expired, tampered and honeypot are rejected' => function () use ($guard) {
        truthy($guard->check($guard->issue(1000), '', 1001) !== null, 'too fast accepted');
        truthy($guard->check($guard->issue(1000), '', 1000 + 172801) !== null, 'expired accepted');
        truthy($guard->check('2000.' . substr($guard->issue(1000), 5), '', 2010) !== null, 'tampered accepted');
        truthy($guard->check($guard->issue(1000), 'http://spam', 1010) !== null, 'honeypot accepted');
        truthy($guard->check(['x'], '', 1010) !== null, 'array token accepted');
    },
    'valid form is cleaned' => function () use ($form, $valid) {
        [$values, $errors] = $form->validate($valid);
        same([], $errors);
        same('Anna Muster', $values['name']);
        same("Line one\n\nLine two", $values['abstract']);
        same(false, $values['consent_programme']);
    },
    'missing fields and consents are reported' => function () use ($form) {
        [, $errors] = $form->validate([]);
        foreach (['name', 'email', 'affiliation', 'title', 'abstract', 'consent_privacy', 'consent_share'] as $field) {
            truthy(isset($errors[$field]), "no error for $field");
        }
    },
    'invalid e-mail, overlong text and non-string input are rejected' => function () use ($form, $valid) {
        [, $errors] = $form->validate(['email' => "a@b.c\r\nBcc: x@y.z", 'title' => str_repeat('x', 301), 'name' => ['array']] + $valid);
        truthy(isset($errors['email']), 'header injection e-mail accepted');
        truthy(isset($errors['title']), 'overlong title accepted');
        truthy(isset($errors['name']), 'array name accepted');
    },
    'bidi controls and control chars are stripped' => function () {
        same('evil fdp.exe', Text::clean("evil\u{202E} fdp.exe\x00"));
        same('', Text::clean("\xC3\x28"));
    },
    'file names keep only the base name' => function () {
        same('paper.pdf', Text::filename('C:\\Users\\x\\paper.pdf'));
        same('evil.php', Text::filename('../../evil.php'));
    },
    'slug is ASCII' => function () {
        same('muller-zurich', Text::slug('Müller / Zürich'));
    },
    'CSV neutralises formulas' => function () {
        same("\"'=HYPERLINK(\"\"x\"\")\";\"ok\"\r\n", Csv::row(['=HYPERLINK("x")', 'ok']));
        same('"\'-2+3"', Csv::cell('-2+3'));
        same('"\'@SUM(A1)"', Csv::cell('@SUM(A1)'));
        same('"x,\'=1+1,y"', Csv::cell('x,=1+1,y'), 'trigger after a comma');
        same('"a; \'+cmd"', Csv::cell('a; +cmd'), 'trigger after a semicolon');
    },
    'only plain e-mail addresses are accepted' => function () {
        truthy(SubmissionForm::isPlainEmail('anna.muster+ws@student.unisg.ch'), 'normal address rejected');
        foreach (['a%40b.ch?bcc=x@evil.example', '"x;spy@evil.example"@uni.ch', 'x@[10.0.0.1]', 'a&cc=b@evil.example'] as $bad) {
            truthy(!SubmissionForm::isPlainEmail($bad), "accepted $bad");
        }
    },
    'links are removed from text that is mailed out' => function () {
        same('Please verify at [link removed] now', Text::defang('Please verify at https://evil.example/login now'));
        same('see [link removed]', Text::defang('see www.evil.example'));
        same(5, mb_strlen(Text::defang(str_repeat('x', 50), 5)));
    },
];
