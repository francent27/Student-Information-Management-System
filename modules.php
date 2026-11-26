<?php

declare(strict_types=1);

function field(string $label, string $type, bool $required = true, int $max = 0): array
{
    return compact('label', 'type', 'required', 'max');
}

function choice(string $label, array $values): array
{
    return field($label, 'choice') + ['values' => array_combine($values, $values)];
}

function relation(string $label, string $sql, bool $required = true): array
{
    return field($label, 'relation', $required) + ['sql' => $sql];
}

function modules(): array
{
    $studentName = "CONCAT(s.first_name, ' ', s.last_name)";
    $className = "CONCAT(c.name, ' · ', c.school_year)";
    $studentOption = "SELECT s.id, CONCAT($studentName, ' · ', s.student_no) AS label FROM students s ORDER BY s.last_name, s.first_name";
    $classOption = "SELECT c.id, $className AS label FROM classes c ORDER BY c.school_year DESC, c.name";
    $enrollmentOption = "SELECT n.id, CONCAT($studentName, ' · ', s.student_no, ' · ', $className) AS label
        FROM enrollments n JOIN students s ON s.id = n.student_id JOIN classes c ON c.id = n.class_id
        ORDER BY c.school_year DESC, s.last_name, s.first_name, c.name";
    $accountOption = static fn(string $role) =>
        "SELECT id, CONCAT(name, ' · ', email) AS label FROM users WHERE role = '$role' ORDER BY name";
    $enrollmentFrom = 'JOIN students s ON s.id = n.student_id JOIN classes c ON c.id = n.class_id';
    $section = ['class_id' => 'c.id', 'school_year' => 'c.school_year'];

    return [
        'students' => [
            'title' => 'Students', 'item' => 'student', 'icon' => 'people',
            'intro' => 'Find student profiles, academic status and enrollment history.',
            'from' => 'students s', 'alias' => 's', 'scope' => 'student',
            'select' => "s.*, $studentName AS student, (SELECT c.name FROM enrollments n
                JOIN classes c ON c.id = n.class_id WHERE n.student_id = s.id
                AND n.status = 'Enrolled' ORDER BY c.school_year DESC, n.enrolled_on DESC, n.id DESC LIMIT 1) AS class",
            'search' => "CONCAT_WS(' ', $studentName, s.student_no, s.email)", 'order' => 's.last_name, s.first_name, s.id',
            'columns' => ['student' => 'Student', 'student_no' => 'Student ID', 'class' => 'Current class', 'status' => 'Status'],
            'filters' => ['status' => 's.status'],
            'fields' => [
                'student_no' => field('Student ID', 'text', true, 32),
                'status' => choice('Academic status', ['Active', 'Inactive', 'Graduated', 'Transferred']),
                'first_name' => field('First name', 'text', true, 60),
                'last_name' => field('Last name', 'text', true, 60),
                'birth_date' => field('Date of birth', 'date', false),
                'email' => field('Email', 'email', false, 190),
                'phone' => field('Phone', 'tel', false, 32),
                'account_id' => relation('Student login', $accountOption('student'), false),
                'parent_id' => relation('Parent or guardian login', $accountOption('parent'), false),
            ],
        ],
        'classes' => [
            'title' => 'Classes', 'item' => 'class', 'icon' => 'building',
            'intro' => 'Browse class sections, school years and assigned teachers.',
            'from' => 'classes c LEFT JOIN users u ON u.id = c.teacher_id', 'alias' => 'c', 'scope' => 'class',
            'select' => 'c.*, u.name AS teacher', 'search' => "CONCAT_WS(' ', c.name, c.school_year, c.room, u.name)",
            'order' => 'c.school_year DESC, c.name, c.id',
            'columns' => ['name' => 'Class', 'school_year' => 'School year', 'room' => 'Room', 'teacher' => 'Teacher'],
            'filters' => ['school_year' => 'c.school_year'],
            'fields' => [
                'name' => field('Class name', 'text', true, 80),
                'school_year' => field('School year', 'year_range'),
                'room' => field('Room', 'text', false, 40),
                'teacher_id' => relation('Assigned teacher', $accountOption('teacher'), false),
            ],
        ],
        'enrollments' => [
            'title' => 'Enrollments', 'item' => 'enrollment', 'icon' => 'mortarboard',
            'intro' => 'Review student placement and class history by school year.',
            'from' => "enrollments n $enrollmentFrom", 'alias' => 'n', 'scope' => 'enrollment',
            'select' => "n.*, $studentName AS student, s.student_no, c.name AS class, c.school_year",
            'search' => "CONCAT_WS(' ', $studentName, s.student_no, c.name)", 'order' => 'n.enrolled_on DESC, n.id DESC',
            'columns' => ['student' => 'Student', 'class' => 'Class', 'school_year' => 'School year',
                'enrolled_on' => 'Enrolled on', 'status' => 'Status'],
            'filters' => $section + ['status' => 'n.status'],
            'fields' => [
                'student_id' => relation('Student', $studentOption),
                'class_id' => relation('Class', $classOption),
                'enrolled_on' => field('Enrollment date', 'date'),
                'status' => choice('Enrollment status', ['Enrolled', 'Completed', 'Withdrawn']),
            ],
        ],
        'attendance' => [
            'title' => 'Attendance', 'item' => 'attendance record', 'icon' => 'clipboard-check',
            'intro' => 'Review daily attendance by date, class and status.',
            'from' => "attendance a JOIN enrollments n ON n.id = a.enrollment_id $enrollmentFrom",
            'alias' => 'a', 'scope' => 'enrollment',
            'select' => "a.*, $studentName AS student, s.id AS student_id, s.student_no, c.name AS class",
            'search' => "CONCAT_WS(' ', $studentName, s.student_no, c.name, a.notes)",
            'order' => 'a.attendance_date DESC, s.last_name, s.first_name, a.id',
            'columns' => ['student' => 'Student', 'class' => 'Class', 'attendance_date' => 'Date',
                'status' => 'Status', 'notes' => 'Notes'],
            'filters' => ['attendance_date' => 'a.attendance_date'] + $section + ['status' => 'a.status'],
            'fields' => [
                'enrollment_id' => relation('Student enrollment', $enrollmentOption),
                'attendance_date' => field('Attendance date', 'date'),
                'status' => choice('Attendance status', ['Present', 'Late', 'Absent', 'Excused']),
                'notes' => field('Notes', 'text', false, 240),
            ],
        ],
        'grades' => [
            'title' => 'Gradebook', 'item' => 'grade', 'icon' => 'journal-text',
            'intro' => 'Find subject grades and open a student’s report card.',
            'from' => "grades g JOIN enrollments n ON n.id = g.enrollment_id $enrollmentFrom",
            'alias' => 'g', 'scope' => 'enrollment',
            'select' => "g.*, $studentName AS student, s.id AS student_id, s.student_no, c.name AS class",
            'search' => "CONCAT_WS(' ', $studentName, s.student_no, c.name, g.subject)",
            'order' => 'c.school_year DESC, g.term DESC, s.last_name, s.first_name, g.subject, g.id',
            'columns' => ['student' => 'Student', 'subject' => 'Subject', 'class' => 'Class', 'term' => 'Term',
                'score' => 'Grade', 'remarks' => 'Remarks'],
            'filters' => ['term' => 'g.term'] + $section,
            'fields' => [
                'enrollment_id' => relation('Student enrollment', $enrollmentOption),
                'subject' => field('Subject', 'text', true, 100),
                'term' => choice('Term', ['Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4']),
                'score' => field('Grade (%)', 'number') + ['min' => 0, 'limit' => 100],
                'remarks' => field('Remarks', 'text', false, 240),
            ],
        ],
        'timetable' => [
            'title' => 'Timetable', 'item' => 'schedule entry', 'icon' => 'calendar3',
            'intro' => 'Look up class sessions and exams by date and section.',
            'from' => 'timetable t JOIN classes c ON c.id = t.class_id', 'alias' => 't', 'scope' => 'class',
            'select' => 't.*, c.name AS class, c.school_year',
            'search' => "CONCAT_WS(' ', t.title, c.name, t.room)", 'order' => 't.starts_at ASC, t.id',
            'columns' => ['title' => 'Event', 'kind' => 'Type', 'class' => 'Class', 'starts_at' => 'Starts',
                'ends_at' => 'Ends', 'room' => 'Room'],
            'filters' => ['kind' => 't.kind', 'date' => 'DATE(t.starts_at)',
                'period' => "CASE WHEN t.starts_at >= NOW() THEN 'Upcoming' ELSE 'Past' END"] + $section,
            'fields' => [
                'title' => field('Event title', 'text', true, 120),
                'kind' => choice('Event type', ['Class', 'Exam']),
                'class_id' => relation('Class', $classOption),
                'room' => field('Room', 'text', true, 40),
                'starts_at' => field('Starts at', 'datetime-local'),
                'ends_at' => field('Ends at', 'datetime-local'),
            ],
        ],
        'fees' => [
            'title' => 'Fees & Dues', 'item' => 'fee', 'icon' => 'wallet2',
            'intro' => 'Review due dates, payments and outstanding balances.',
            'from' => 'fees f JOIN students s ON s.id = f.student_id', 'alias' => 'f', 'scope' => 'student',
            'select' => "f.*, $studentName AS student, s.student_no, (f.amount - f.paid) AS balance,
                CASE WHEN f.paid >= f.amount THEN 'Paid' WHEN f.due_date < CURRENT_DATE THEN 'Overdue'
                    WHEN f.paid > 0 THEN 'Partial' ELSE 'Unpaid' END AS status",
            'search' => "CONCAT_WS(' ', $studentName, s.student_no, f.description)",
            'order' => 'f.due_date DESC, f.id DESC',
            'columns' => ['student' => 'Student', 'description' => 'Fee', 'amount' => 'Amount',
                'paid' => 'Paid', 'balance' => 'Balance', 'due_date' => 'Due date', 'status' => 'Status'],
            'filters' => ['status' => "CASE WHEN f.paid >= f.amount THEN 'Paid' WHEN f.due_date < CURRENT_DATE THEN 'Overdue'
                WHEN f.paid > 0 THEN 'Partial' ELSE 'Unpaid' END"],
            'fields' => [
                'student_id' => relation('Student', $studentOption),
                'description' => field('Description', 'text', true, 120),
                'amount' => field('Amount', 'number') + ['min' => 0.01, 'limit' => 99999999.99],
                'paid' => field('Payments received', 'number') + ['min' => 0, 'limit' => 99999999.99],
                'due_date' => field('Due date', 'date'),
            ],
        ],
        'announcements' => [
            'title' => 'Announcements', 'item' => 'announcement', 'icon' => 'megaphone',
            'intro' => 'Read campus updates and role-specific notices.',
            'from' => 'announcements m', 'alias' => 'm', 'scope' => 'announcement',
            'select' => 'm.*', 'search' => "CONCAT_WS(' ', m.title, m.body)", 'order' => 'm.published_on DESC, m.id DESC',
            'columns' => ['title' => 'Announcement', 'audience' => 'Audience', 'published_on' => 'Publish date'],
            'filters' => ['audience' => 'm.audience'],
            'fields' => [
                'title' => field('Title', 'text', true, 120),
                'audience' => choice('Audience', ['All', 'Teachers', 'Students', 'Parents']),
                'published_on' => field('Publish date', 'date'),
                'body' => field('Message', 'textarea', true, 8000),
            ],
        ],
        'users' => [
            'title' => 'Accounts', 'item' => 'account', 'icon' => 'person-gear',
            'intro' => 'Manage sign-in accounts for administrators, teachers, students and parents.',
            'from' => 'users u', 'alias' => 'u', 'scope' => 'admin',
            'select' => 'u.id, u.name, u.email, u.role, u.created_at',
            'search' => "CONCAT_WS(' ', u.name, u.email)", 'order' => 'u.name, u.id',
            'columns' => ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'created_at' => 'Created'],
            'filters' => ['role' => 'u.role'],
            'fields' => [
                'name' => field('Full name', 'text', true, 100),
                'email' => field('Email', 'email', true, 190),
                'role' => field('Role', 'choice') + ['values' => [
                    'admin' => 'Administrator', 'teacher' => 'Teacher', 'student' => 'Student', 'parent' => 'Parent']],
                'password' => field('Password', 'password', true, 72),
            ],
        ],
    ];
}

function allowed_module(string $page, array $user): bool
{
    return ($page !== 'users' || can_manage($user))
        && ($page !== 'fees' || $user['role'] !== 'teacher');
}

function scope(string $type, array $user): array
{
    if (can_manage($user)) {
        return ['1 = 1', []];
    }

    $role = $user['role'];
    $id = $user['id'];
    if ($type === 'announcement') {
        return ["m.published_on <= ? AND m.audience IN ('All', ?)", [date('Y-m-d'), ucfirst($role) . 's']];
    }
    if ($role === 'teacher') {
        return match ($type) {
            'class', 'enrollment' => ['c.teacher_id = ?', [$id]],
            'student' => ['EXISTS (SELECT 1 FROM enrollments en JOIN classes cl ON cl.id = en.class_id
                WHERE en.student_id = s.id AND cl.teacher_id = ?)', [$id]],
            default => ['1 = 0', []],
        };
    }

    $link = $role === 'student' ? 'account_id' : 'parent_id';
    return match ($type) {
        'student', 'enrollment' => ["s.$link = ?", [$id]],
        'class' => ["EXISTS (SELECT 1 FROM enrollments en JOIN students st ON st.id = en.student_id
            WHERE en.class_id = c.id AND en.status <> 'Withdrawn' AND st.$link = ?)", [$id]],
        default => ['1 = 0', []],
    };
}

function record(string $page, int $id, array $user): ?array
{
    $module = modules()[$page];
    [$where, $params] = scope($module['scope'], $user);
    $sql = "SELECT {$module['select']} FROM {$module['from']} WHERE $where AND {$module['alias']}.id = ?";
    return query($sql, [...$params, $id])->fetch() ?: null;
}

function filter_values(string $page, string $key, array $user): array
{
    $module = modules()[$page];
    if (isset($module['fields'][$key]['values'])) {
        return $module['fields'][$key]['values'];
    }
    if ($page === 'timetable' && $key === 'period') {
        return ['Upcoming' => 'Upcoming', 'Past' => 'Past'];
    }
    if ($page === 'fees' && $key === 'status') {
        return array_combine(['Paid', 'Partial', 'Unpaid', 'Overdue'], ['Paid', 'Partial', 'Unpaid', 'Overdue']);
    }
    [$where, $params] = scope($module['scope'], $user);
    $expr = $module['filters'][$key];
    if ($key === 'class_id') {
        return query("SELECT DISTINCT c.id, CONCAT(c.name, ' · ', c.school_year) AS label
            FROM {$module['from']} WHERE $where ORDER BY label", $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    $years = query("SELECT DISTINCT $expr AS label FROM {$module['from']}
        WHERE $where ORDER BY label DESC", $params)->fetchAll(PDO::FETCH_COLUMN);
    return $years ? array_combine($years, $years) : [];
}

function listing(string $page, array $user, array $filters, int $requestedPage): array
{
    $module = modules()[$page];
    [$where, $params] = scope($module['scope'], $user);
    $criteria = ["($where)"];
    if (($term = input($filters, 'q')) !== '') {
        $criteria[] = "{$module['search']} LIKE ? ESCAPE '!'";
        $params[] = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    foreach ($module['filters'] as $key => $expr) {
        $value = input($filters, $key);
        if ($value === '') {
            continue;
        }
        if (in_array($key, ['date', 'attendance_date'], true) && !valid_date($value)) {
            $criteria[] = '1 = 0';
            continue;
        }
        $criteria[] = "($expr) = ?";
        $params[] = $value;
    }
    $where = implode(' AND ', $criteria);
    $count = (int) query("SELECT COUNT(*) FROM {$module['from']} WHERE $where", $params)->fetchColumn();
    $size = 12;
    $pages = max(1, (int) ceil($count / $size));
    $current = min($pages, max(1, $requestedPage));
    $offset = ($current - 1) * $size;
    $rows = query("SELECT {$module['select']} FROM {$module['from']} WHERE $where
        ORDER BY {$module['order']} LIMIT $size OFFSET $offset", $params)->fetchAll();
    return compact('rows', 'count', 'pages', 'current', 'offset');
}
