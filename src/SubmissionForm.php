<?php

declare(strict_types=1);

namespace Workshop;

/** Field list and validation for the submission form – the template renders the same list. */
final class SubmissionForm
{
    public const CONSENTS_REQUIRED = ['consent_privacy', 'consent_share'];

    public function __construct(
        private readonly int $abstractMax,
        private readonly bool $abstractRequired,
    ) {
    }

    /**
     * name => [label, max length, required, multi-line, hint]
     *
     * @return array<string, array{0: string, 1: int, 2: bool, 3: bool, 4: string}>
     */
    public function fields(): array
    {
        return [
            'name' => ['Full name', 120, true, false, ''],
            'email' => ['E-mail address', 190, true, false, 'We send your confirmation here. To update your submission later, use the same address.'],
            'affiliation' => ['University / institution', 200, true, false, ''],
            'title' => ['Paper title', 300, true, false, ''],
            'coauthors' => ['Co-authors', 300, false, false, 'Optional.'],
            'keywords' => ['Topic and method keywords', 200, false, false, 'Optional. Helps us assign papers to tracks and sessions.'],
            'abstract' => ['Short abstract', $this->abstractMax, $this->abstractRequired, true, sprintf('Max. %d characters.', $this->abstractMax)],
            'feedback' => ['What feedback would help you most?', 1000, false, true, 'Optional. E.g. framing, method, positioning, target journal.'],
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} cleaned values and errors by field */
    public function validate(array $post): array
    {
        $values = [];
        $errors = [];

        foreach ($this->fields() as $field => [$label, $max, $required, $multiline]) {
            $value = Text::clean($post[$field] ?? '', $multiline);
            $values[$field] = $value;
            if ($value === '') {
                if ($required) {
                    $errors[$field] = "Please fill in: $label.";
                }
                continue;
            }
            if (mb_strlen($value) > $max) {
                $errors[$field] = sprintf('%s is too long (max. %d characters, currently %d).', $label, $max, mb_strlen($value));
            }
        }

        if (!isset($errors['email']) && $values['email'] !== '' && !self::isPlainEmail($values['email'])) {
            $errors['email'] = 'Please enter a valid e-mail address.';
        }

        foreach (self::CONSENTS_REQUIRED as $consent) {
            $values[$consent] = ($post[$consent] ?? null) === '1';
            if (!$values[$consent]) {
                $errors[$consent] = 'This consent is required to take part.';
            }
        }
        $values['consent_programme'] = ($post['consent_programme'] ?? null) === '1';

        return [$values, $errors];
    }

    /**
     * Ordinary addresses only. RFC-valid oddities such as quoted local parts,
     * "?" or "%" would later turn into extra fields in mailto: links.
     */
    public static function isPlainEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[A-Za-z0-9._+\-]+@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+$/D', $email) === 1;
    }
}
