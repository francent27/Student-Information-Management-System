<?php

declare(strict_types=1);

function icon(string $name): string
{
    return '<i class="bi bi-' . e($name) . '" aria-hidden="true"></i>';
}

function brand(): string
{
    return '<span class="brand-mark">' . icon('mortarboard-fill') . '</span>'
        . '<span class="brand-wordmark">Campus<span>SIMS</span></span>';
}

function role_name(string $role): string
{
    return $role === 'admin' ? 'Administrator' : ucfirst($role);
}

function badge(string $label): string
{
    $tone = match ($label) {
        'Active', 'Enrolled', 'Present', 'Paid' => 'green',
        'Late', 'Partial', 'Unpaid' => 'amber',
        'Absent', 'Overdue', 'Withdrawn' => 'red',
        'Completed', 'Graduated', 'Class', 'All' => 'blue',
        'Exam', 'Teachers', 'Students', 'Parents', 'Administrator', 'Teacher', 'Student', 'Parent' => 'purple',
        default => 'neutral',
    };
    return '<span class="status status-' . $tone . '">' . e($label) . '</span>';
}

function document_start(string $title, string $class = ''): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Campus SIMS</title>
        <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
        <link rel="stylesheet" href="assets/app.css">
        <script src="assets/vendor/bootstrap.bundle.min.js" defer></script>
        <script src="assets/app.js" defer></script>
    </head>
    <body class="<?= e($class) ?>">
    <a class="skip-link" href="#main">Skip to content</a>
    <?php
}

function layout_start(string $title, string $active, array $user): void
{
    document_start($title);
    $modules = modules();
    $groups = [
        '' => ['dashboard'],
        'Academic records' => ['students', 'classes', 'enrollments', 'attendance', 'grades'],
        'Campus' => ['timetable', 'fees', 'announcements'],
        'Administration' => ['users'],
    ];
    ?>
    <div class="app-shell">
        <aside class="offcanvas-lg offcanvas-start sidebar" id="main-navigation" tabindex="-1" aria-label="Main navigation">
            <div class="sidebar-header">
                <a href="<?= e(url()) ?>" class="brand" aria-label="Campus SIMS dashboard"><?= brand() ?></a>
                <button class="sidebar-close d-lg-none" type="button" data-bs-dismiss="offcanvas"
                    data-bs-target="#main-navigation" aria-label="Close navigation"><?= icon('x-lg') ?></button>
            </div>
            <nav class="sidebar-nav" aria-label="Main navigation">
                <?php foreach ($groups as $label => $links):
                    $links = array_filter($links, static fn($link) => allowed_module($link, $user));
                    if (!$links) {
                        continue;
                    } ?>
                    <?php if ($label): ?><p class="nav-group"><?= e($label) ?></p><?php endif ?>
                    <?php foreach ($links as $link):
                        $item = $modules[$link] ?? ['title' => 'Overview', 'icon' => 'grid-1x2'];
                        ?>
                        <a class="nav-item<?= $link === $active ? ' current' : '' ?>" href="<?= e(url($link)) ?>"
                            <?= $link === $active ? 'aria-current="page"' : '' ?>>
                            <?= icon($item['icon']) ?><span><?= e($item['title']) ?></span>
                        </a>
                    <?php endforeach ?>
                <?php endforeach ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="sidebar-account">
                    <span class="avatar avatar-sidebar" aria-hidden="true"><?= e(initials($user['name'])) ?></span>
                    <div><strong><?= e($user['name']) ?></strong><span><?= e(role_name($user['role'])) ?></span></div>
                </div>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="nav-item sign-out" type="submit"><?= icon('box-arrow-right') ?> Sign out</button>
                </form>
            </div>
        </aside>
        <div class="workspace">
            <header class="topbar">
                <button class="menu-button d-lg-none" type="button" aria-label="Open navigation"
                    data-bs-toggle="offcanvas" data-bs-target="#main-navigation" aria-controls="main-navigation">
                    <?= icon('list') ?>
                </button>
                <form class="global-search" method="get" role="search" aria-label="Quick student search">
                    <input type="hidden" name="page" value="students">
                    <input id="global-q" name="q" type="search" class="form-control" placeholder="Search students by name or ID"
                        maxlength="190" aria-label="Search students by name or ID">
                    <button type="submit" aria-label="Search"><?= icon('search') ?></button>
                </form>
                <div class="topbar-aside">
                    <span class="topbar-date"><?= icon('calendar3') ?> <?= e(date('D, M j, Y')) ?></span>
                    <a class="topbar-notices" href="<?= e(url('announcements')) ?>" aria-label="Announcements"><?= icon('bell') ?></a>
                    <span class="avatar" title="<?= e($user['name']) ?>" aria-hidden="true"><?= e(initials($user['name'])) ?></span>
                </div>
            </header>
            <main id="main" class="content" tabindex="-1">
            <?php if (isset($_SESSION['flash'])):
                [$message, $type] = $_SESSION['flash'];
                unset($_SESSION['flash']); ?>
                <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'danger' ? 'alert' : 'status' ?>"><?= e($message) ?></div>
            <?php endif ?>
    <?php
}

function layout_end(): void
{
    echo '</main></div></div></body></html>';
}

function page_heading(string $title, string $intro, string $actions = ''): void
{
    ?>
    <div class="page-heading">
        <div><h1><?= e($title) ?></h1><p><?= e($intro) ?></p></div>
        <?php if ($actions): ?><div class="page-actions"><?= $actions ?></div><?php endif ?>
    </div>
    <?php
}

function login_view(string $error = '', string $email = ''): void
{
    document_start('Sign in', 'login-page');
    ?>
    <main id="main" class="login-layout" tabindex="-1">
        <section class="login-cover" aria-label="Campus SIMS">
            <div class="brand"><?= brand() ?></div>
            <div class="login-cover-text">
                <div class="login-symbol" aria-hidden="true"><?= icon('mortarboard') ?></div>
                <h1>Student Information<br>Management System</h1>
                <p>Access academic records, attendance and campus updates from your school account.</p>
            </div>
        </section>
        <section class="login-form-wrap" aria-labelledby="login-title">
            <form method="post" action="<?= e(url('login')) ?>" class="login-form">
                <?= csrf_field() ?>
                <div class="login-mobile-brand brand"><?= brand() ?></div>
                <h2 id="login-title">Sign in</h2>
                <p>Use your school account to continue.</p>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>"
                        placeholder="name@school.edu" autocomplete="username" maxlength="190" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                        autocomplete="current-password" maxlength="72" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign in</button>
                <?php if (getenv('SIMS_DEMO') === '1'): ?>
                    <details class="demo-accounts" open>
                        <summary>Demo accounts</summary>
                        <div class="demo-options">
                            <?php foreach (['admin' => 'Administrator', 'teacher' => 'Teacher', 'student' => 'Student', 'parent' => 'Parent'] as $role => $label): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-demo-email="<?= e($role) ?>@campus.test"><?= e($label) ?></button>
                            <?php endforeach ?>
                        </div>
                        <p>Shared password <code id="demo-password">CampusDemo!2026</code></p>
                    </details>
                <?php endif ?>
            </form>
        </section>
    </main>
    </body></html>
    <?php
}

function display_error(int $code, string $message, ?array $user = null): void
{
    http_response_code($code);
    $heading = match ($code) {
        401 => 'Sign-in required',
        403 => 'Access denied',
        404 => 'Page unavailable',
        default => 'Service unavailable',
    };
    if ($user) {
        layout_start($heading, '', $user);
    } else {
        document_start($heading);
        echo '<main id="main" class="content standalone-error" tabindex="-1">';
    }
    page_heading($heading, $message, '<a class="btn btn-primary" href="' . e(url()) . '">'
        . ($user ? 'Back to overview' : 'Back to sign in') . '</a>');
    if ($user) {
        layout_end();
    } else {
        echo '</main></body></html>';
    }
}

function field_control(string $key, array $field, mixed $value, string $error = '', bool $disabled = false): void
{
    $type = $field['type'];
    $invalid = $error !== '' ? ' is-invalid' : '';
    $id = 'field-' . $key;
    $attrs = ' id="' . e($id) . '" name="' . e($key) . '"';
    $attrs .= $disabled ? ' disabled' : ($field['required'] ? ' required' : '');
    if ($error !== '') {
        $attrs .= ' aria-invalid="true" aria-describedby="' . e($id . '-error') . '"';
    }
    ?>
    <div class="<?= $type === 'textarea' ? 'col-12' : 'col-12 col-md-6' ?>">
        <label class="form-label" for="<?= e($id) ?>"><?= e($field['label']) ?>
            <?php if (!$field['required'] && $type !== 'password'): ?><span class="field-optional">Optional</span><?php endif ?>
        </label>
        <?php if ($type === 'choice' || $type === 'relation'): ?>
            <select class="form-select<?= $invalid ?>"<?= $attrs ?>>
                <option value=""><?= $field['required'] ? 'Select an option' : 'None' ?></option>
                <?php foreach (field_options($field) as $option => $label): ?>
                    <option value="<?= e($option) ?>" <?= (string) $option === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea class="form-control<?= $invalid ?>" rows="6" maxlength="<?= e($field['max']) ?>"<?= $attrs ?>><?= e($value) ?></textarea>
        <?php else:
            $value = $type === 'password' ? '' : $value;
            $htmlType = $type === 'year_range' ? 'text' : $type;
            $attrs .= $field['max'] && $type !== 'number' ? ' maxlength="' . e($field['max']) . '"' : '';
            $attrs .= match ($type) {
                'password' => ' autocomplete="new-password" minlength="12"',
                'year_range' => ' placeholder="2026-2027" maxlength="9" pattern="[0-9]{4}-[0-9]{4}"',
                'number' => ' step="0.01" min="' . e($field['min']) . '" max="' . e($field['limit']) . '"',
                default => '',
            };
            ?>
            <input type="<?= e($htmlType) ?>" value="<?= e($value) ?>" class="form-control<?= $invalid ?>"<?= $attrs ?>>
        <?php endif ?>
        <?php if ($error !== ''): ?>
            <div id="<?= e($id . '-error') ?>" class="invalid-feedback"><?= e($error) ?></div>
        <?php endif ?>
    </div>
    <?php
}

function form_view(string $page, array $module, array $user, ?array $existing, array $data = [], array $errors = []): void
{
    $title = ($existing ? 'Edit ' : 'Add ') . $module['item'];
    $params = $existing ? ['action' => 'edit', 'id' => $existing['id']] : ['action' => 'new'];
    $defaults = ['published_on' => date('Y-m-d'), 'enrolled_on' => date('Y-m-d'),
        'attendance_date' => date('Y-m-d'), 'audience' => 'All', 'paid' => '0.00'];
    $defaults['status'] = match ($page) {
        'students' => 'Active', 'enrollments' => 'Enrolled', 'attendance' => 'Present', default => '',
    };
    layout_start($title, $page, $user);
    page_heading($title, $module['intro']);
    if ($errors): ?>
        <div class="alert alert-danger" role="alert"><?= e($errors['general'] ?? 'Review the highlighted fields and try again.') ?></div>
    <?php endif; ?>
    <form class="panel editor" method="post" action="<?= e(url($page, $params)) ?>">
        <?= csrf_field() ?>
        <div class="editor-fields row g-3">
            <?php foreach ($module['fields'] as $key => $field):
                $value = $existing[$key] ?? $defaults[$key] ?? '';
                if ($field['type'] === 'datetime-local' && $value) {
                    $value = formatted_date($value, 'Y-m-d\TH:i');
                }
                $disabled = $key === 'role' && $existing !== null;
                if ($key === 'password' && $existing) {
                    $field['required'] = false;
                    $field['label'] = 'New password (leave blank to keep current)';
                }
                field_control($key, $field, $disabled ? $value : input($data, $key, (string) $value),
                    $errors[$key] ?? '', $disabled);
            endforeach; ?>
        </div>
        <div class="panel-footer">
            <button type="submit" class="btn btn-primary">Save <?= e($module['item']) ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url($page)) ?>">Cancel</a>
        </div>
    </form>
    <?php
    layout_end();
}

function list_view(string $page, array $module, array $user): void
{
    $manage = can_manage($user);
    $listing = listing($page, $user, $_GET, (int) input($_GET, 'p', '1'));
    if ($page === 'students' && $user['role'] === 'teacher') {
        unset($module['columns']['class']);
    }
    $actions = $manage
        ? '<a class="btn btn-primary" href="' . e(url($page, ['action' => 'new'])) . '">' . icon('plus-lg') . ' Add ' . e($module['item']) . '</a>' : '';
    if ($page === 'attendance' && $manage) {
        $actions = '<a class="btn btn-outline-secondary" href="' . e(url('register')) . '">Take attendance</a>' . $actions;
    }
    layout_start($module['title'], $page, $user);
    page_heading($module['title'], $module['intro'], $actions);
    ?>
    <section class="panel" aria-label="<?= e($module['title']) ?> records">
        <?php list_filters($page, $module, $user); ?>
        <?php if (!$listing['rows']): ?>
            <div class="empty">
                <div class="empty-icon"><?= icon($module['icon']) ?></div>
                <h2>No records found</h2>
                <p>Adjust your search or filters to see more results.</p>
            </div>
        <?php elseif ($page === 'announcements'): ?>
            <div class="announcement-grid">
                <?php foreach ($listing['rows'] as $row): ?>
                    <article class="announcement-card">
                        <div class="announcement-meta"><?= badge($row['audience']) ?><span><?= e(formatted_date($row['published_on'])) ?></span></div>
                        <h2><?= e($row['title']) ?></h2>
                        <p><?= nl2br(e($row['body'])) ?></p>
                        <?php if ($manage): ?><?= row_actions($page, $module, (int) $row['id']) ?><?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <?php records_table($page, $module, $listing['rows'], $manage); ?>
        <?php endif ?>
        <?php pagination($page, $module, $listing); ?>
    </section>
    <?php
    layout_end();
}

function list_filters(string $page, array $module, array $user): void
{
    $labels = ['q' => 'Search', 'class_id' => 'Class', 'school_year' => 'School year', 'status' => 'Status',
        'attendance_date' => 'Attendance date', 'term' => 'Term', 'kind' => 'Event type', 'date' => 'Date',
        'audience' => 'Audience', 'role' => 'Role', 'period' => 'Time range'];
    ?>
    <form method="get" class="filter-bar" role="search" aria-label="<?= e($module['title']) ?> filters">
        <input type="hidden" name="page" value="<?= e($page) ?>">
        <div class="filter-field filter-search">
            <label for="filter-q" class="form-label">Search</label>
            <input class="form-control" id="filter-q" type="search" name="q" maxlength="190"
                value="<?= e(input($_GET, 'q')) ?>" placeholder="<?= $page === 'students' ? 'Name or student ID' : 'Search records' ?>">
        </div>
        <?php foreach ($module['filters'] as $key => $expression):
            $value = input($_GET, $key); ?>
            <div class="filter-field">
                <label for="filter-<?= e($key) ?>" class="form-label"><?= e($labels[$key]) ?></label>
                <?php if (in_array($key, ['date', 'attendance_date'], true)): ?>
                    <input type="date" class="form-control" id="filter-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>">
                <?php else: ?>
                    <select id="filter-<?= e($key) ?>" name="<?= e($key) ?>" class="form-select">
                        <option value="">All</option>
                        <?php foreach (filter_values($page, $key, $user) as $option => $label): ?>
                            <option value="<?= e($option) ?>" <?= (string) $option === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <button class="btn btn-primary">Apply filters</button>
        <?php if (array_filter(array_map(static fn($key) => input($_GET, $key), ['q', ...array_keys($module['filters'])]))): ?>
            <a href="<?= e(url($page)) ?>" class="btn btn-clear">Clear</a>
        <?php endif ?>
    </form>
    <?php
}

function records_table(string $page, array $module, array $rows, bool $manage): void
{
    ?>
    <div class="table-responsive" role="region" tabindex="0" aria-label="<?= e($module['title']) ?> table">
        <table class="table app-table">
            <thead><tr>
                <?php foreach ($module['columns'] as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach ?>
                <?php if ($manage || $page === 'grades'): ?><th scope="col" class="text-end">Actions</th><?php endif ?>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?><tr>
                    <?php foreach ($module['columns'] as $key => $label): ?><td><?= cell($page, $key, $row) ?></td><?php endforeach ?>
                    <?php if ($manage || $page === 'grades'): ?>
                        <td class="text-end">
                            <div class="table-actions">
                                <?php if ($page === 'grades'): ?>
                                    <a class="action-link" href="<?= e(url('report', ['id' => $row['student_id'], 'enrollment' => $row['enrollment_id']])) ?>">Report card</a>
                                <?php endif ?>
                                <?php if ($manage): ?><?= row_actions($page, $module, (int) $row['id']) ?><?php endif ?>
                            </div>
                        </td>
                    <?php endif ?>
                </tr><?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php
}

function cell(string $page, string $key, array $row): string
{
    $value = $row[$key] ?? '';
    if ($key === 'student') {
        $id = $page === 'students' ? $row['id'] : $row['student_id'];
        return '<a class="student-link" href="' . e(url('profile', ['id' => $id])) . '">'
            . '<span class="avatar avatar-student" aria-hidden="true">' . e(initials($value)) . '</span>'
            . '<span>' . e($value) . '</span></a>';
    }
    if ($key === 'role') {
        return badge(role_name($value));
    }
    if (in_array($key, ['status', 'kind', 'audience'], true)) {
        return badge($value);
    }
    if (in_array($key, ['starts_at', 'ends_at'], true)) {
        return '<span class="nowrap">' . e(formatted_date($value, 'M j, Y · g:i A')) . '</span>';
    }
    if (in_array($key, ['attendance_date', 'published_on', 'enrolled_on', 'due_date', 'created_at'], true)) {
        return '<span class="nowrap">' . e(formatted_date($value)) . '</span>';
    }
    if (in_array($key, ['amount', 'paid', 'balance'], true)) {
        return '<span class="nowrap">' . e(amount($value)) . '</span>';
    }
    if ($key === 'score') {
        return '<strong>' . e(number_format((float) $value, 2)) . '%</strong>';
    }
    return $value === '' ? '<span class="text-secondary">—</span>' : e($value);
}

function row_actions(string $page, array $module, int $id): string
{
    $item = $module['item'];
    $message = match ($page) {
        'students' => 'Delete this student and all associated enrollments, grades, attendance and fees?',
        'enrollments' => 'Delete this enrollment and all associated grades and attendance records?',
        'classes' => 'Delete this class and all associated timetable entries?',
        default => 'Delete this ' . $item . '?',
    };
    return '<div class="row-actions">'
        . '<a class="action-icon" href="' . e(url($page, ['action' => 'edit', 'id' => $id])) . '" title="Edit ' . e($item)
        . '" aria-label="Edit ' . e($item) . '">' . icon('pencil') . '</a>'
        . '<form method="post" action="' . e(url($page, ['action' => 'delete', 'id' => $id]))
        . '" data-confirm="' . e($message) . '">' . csrf_field() . '<button type="submit" class="action-icon delete-button" aria-label="Delete '
        . e($item) . '" title="Delete ' . e($item) . '">' . icon('trash3') . '</button></form></div>';
}

function pagination(string $page, array $module, array $listing): void
{
    $params = array_filter(array_intersect_key($_GET, array_flip(['q', ...array_keys($module['filters'])])), 'is_string');
    $count = $listing['count'];
    $current = $listing['current'];
    $from = $listing['offset'] + 1;
    $to = $from + count($listing['rows']) - 1;
    ?>
    <div class="panel-footer pagination-footer">
        <span><?= $count ? e("Showing {$from}–{$to} of {$count}") : '0 results' ?></span>
        <?php if ($listing['pages'] > 1): ?>
            <nav class="pagination-links" aria-label="Results pages">
                <?php if ($current > 1): ?><a class="btn btn-outline-secondary btn-sm"
                    href="<?= e(url($page, $params + ['p' => $current - 1])) ?>">Previous</a><?php endif ?>
                <span>Page <?= e($current) ?> of <?= e($listing['pages']) ?></span>
                <?php if ($current < $listing['pages']): ?><a class="btn btn-outline-secondary btn-sm"
                    href="<?= e(url($page, $params + ['p' => $current + 1])) ?>">Next</a><?php endif ?>
            </nav>
        <?php endif ?>
    </div>
    <?php
}
