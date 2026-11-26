<?php

declare(strict_types=1);

function field_options(array $field): array
{
    return $field['values'] ?? query($field['sql'])->fetchAll(PDO::FETCH_KEY_PAIR);
}

function validate_record(string $page, array $data, ?array $existing): array
{
    $values = $errors = [];
    foreach (modules()[$page]['fields'] as $name => $field) {
        $raw = input($data, $name);
        $type = $field['type'];
        $required = $field['required'];
        if ($name === 'role' && $existing) {
            continue;
        }
        if ($type === 'password') {
            $raw = is_string($data[$name] ?? null) ? $data[$name] : '';
            $required = !$existing;
        }
        if ($raw === '') {
            if ($required) {
                $errors[$name] = 'Enter or select a value.';
            }
            if ($type !== 'password') {
                $values[$name] = null;
            }
            continue;
        }

        $values[$name] = $raw;
        if ($field['max'] && mb_strlen($raw) > $field['max']) {
            $errors[$name] = 'Use at most ' . $field['max'] . ' characters.';
            continue;
        }

        switch ($type) {
            case 'choice':
            case 'relation':
                if (!array_key_exists($raw, field_options($field))) {
                    $errors[$name] = 'Select an available option.';
                }
                break;
            case 'email':
                if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Enter a valid email address.';
                }
                if ($page === 'users') {
                    $values[$name] = strtolower($raw);
                }
                break;
            case 'date':
                if (!valid_date($raw)) {
                    $errors[$name] = 'Enter a valid date.';
                }
                break;
            case 'datetime-local':
                if (!valid_date($raw, 'Y-m-d\TH:i')) {
                    $errors[$name] = 'Enter a valid date and time.';
                } else {
                    $values[$name] = str_replace('T', ' ', $raw) . ':00';
                }
                break;
            case 'year_range':
                if (!preg_match('/^(\d{4})-(\d{4})$/D', $raw, $matches)
                    || (int) $matches[1] < 1000 || (int) $matches[2] !== (int) $matches[1] + 1) {
                    $errors[$name] = 'Enter two consecutive years, such as 2026-2027.';
                }
                break;
            case 'number':
                if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $raw)
                    || (float) $raw < $field['min'] || (float) $raw > $field['limit']) {
                    $errors[$name] = 'Enter a number from ' . $field['min'] . ' to ' . $field['limit'] . ' with up to two decimal places.';
                }
                break;
            case 'password':
                unset($values[$name]);
                if (strlen($raw) < 12 || strlen($raw) > 72 || str_contains($raw, "\0")) {
                    $errors[$name] = 'Use a password of 12 to 72 bytes.';
                } else {
                    $values['password_hash'] = password_hash($raw, PASSWORD_DEFAULT);
                }
                break;
        }
    }

    if (!$errors && $page === 'fees' && (float) $values['paid'] > (float) $values['amount']) {
        $errors['paid'] = 'Payments cannot exceed the amount due.';
    }
    if (!$errors && $page === 'timetable' && $values['ends_at'] <= $values['starts_at']) {
        $errors['ends_at'] = 'The end time must be after the start time.';
    }
    if (!$errors && $page === 'students' && $values['birth_date'] && $values['birth_date'] > date('Y-m-d')) {
        $errors['birth_date'] = 'Date of birth cannot be in the future.';
    }
    return [$values, $errors];
}

function save_record(string $page, array $values, ?array $existing): int
{
    $columns = array_keys($values);
    if ($existing) {
        $pairs = implode(', ', array_map(static fn($col) => "$col = ?", $columns));
        query("UPDATE $page SET $pairs WHERE id = ?", [...array_values($values), $existing['id']]);
        return (int) $existing['id'];
    }

    $names = implode(', ', $columns);
    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    query("INSERT INTO $page ($names) VALUES ($placeholders)", array_values($values));
    return (int) db()->lastInsertId();
}

function delete_record(string $page, array $record, array $user): void
{
    if ($page !== 'users') {
        query("DELETE FROM $page WHERE id = ?", [$record['id']]);
        return;
    }
    if ((int) $record['id'] === (int) $user['id']) {
        throw new DomainException('You cannot delete the account you are signed in with.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admins = query("SELECT id FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array((int) $user['id'], array_map('intval', $admins), true)) {
            throw new DomainException('The signed-in account is no longer an administrator.');
        }
        query('DELETE FROM users WHERE id = ?', [$record['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function record_error(PDOException $error): string
{
    return match ($error->errorInfo[1] ?? null) {
        1062 => 'A record already exists with these unique details. Check the ID, email or record fields.',
        1451 => 'Other records use this item. Update or remove those records before deleting it.',
        1452 => 'A selected record is no longer available. Refresh the page and try again.',
        3819 => 'One or more values are outside the allowed range.',
        default => 'The database could not save this change. Try again.',
    };
}
