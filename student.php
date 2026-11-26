<?php

declare(strict_types=1);

function student_view(array $user, int $id, bool $report): void
{
    $student = record('students', $id, $user);
    if (!$student) {
        display_error(404, 'This student profile could not be found.', $user);
        return;
    }

    $module = modules()['enrollments'];
    [$where, $params] = scope('enrollment', $user);
    $enrollments = query("SELECT {$module['select']} FROM {$module['from']}
        WHERE $where AND n.student_id = ? ORDER BY c.school_year DESC, n.enrolled_on DESC, n.id DESC",
        [...$params, $id])->fetchAll();

    if ($report) {
        report_view($student, $enrollments, $user);
        return;
    }

    $title = $student['student'];
    $actions = '<a class="btn btn-outline-secondary" href="' . e(url('report', ['id' => $id])) . '">' . icon('journal-text') . ' Report card</a>';
    if (can_manage($user)) {
        $actions .= '<a class="btn btn-primary" href="' . e(url('students', ['action' => 'edit', 'id' => $id])) . '">' . icon('pencil') . ' Edit profile</a>';
    }
    $details = [
        'Student ID' => $student['student_no'], 'Academic status' => badge($student['status']),
        'Date of birth' => formatted_date($student['birth_date']),
        'Email address' => $student['email'], 'Phone number' => $student['phone'],
    ];
    layout_start($title, 'students', $user);
    page_heading($title, 'Student profile', $actions);
    ?>
    <div class="profile-grid">
        <section class="panel">
            <div class="panel-heading"><div><h2>Personal details</h2></div></div>
            <dl class="profile-details">
                <?php foreach ($details as $label => $value): ?>
                    <div><dt><?= e($label) ?></dt><dd><?= $label === 'Academic status' ? $value : e($value ?: '—') ?></dd></div>
                <?php endforeach ?>
            </dl>
        </section>
        <section class="panel">
            <div class="panel-heading"><div><h2>Enrollment history</h2></div></div>
            <?php if ($enrollments):
                unset($module['columns']['student']);
                records_table('enrollments', $module, $enrollments, can_manage($user));
            else: ?><p class="empty muted">No enrollment history available.</p><?php endif ?>
        </section>
    </div>
    <div class="panel profile-links">
        <a class="btn btn-outline-secondary" href="<?= e(url('attendance', ['q' => $student['student_no']])) ?>"><?= icon('clipboard-check') ?> Attendance</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('grades', ['q' => $student['student_no']])) ?>"><?= icon('journal-text') ?> Gradebook</a>
        <?php if (allowed_module('fees', $user)): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('fees', ['q' => $student['student_no']])) ?>"><?= icon('wallet2') ?> Fees &amp; dues</a>
        <?php endif ?>
    </div>
    <?php
    layout_end();
}

function report_view(array $student, array $enrollments, array $user): void
{
    $id = (int) $student['id'];
    $byId = array_column($enrollments, null, 'id');
    $selected = input($_GET, 'enrollment') ?: (string) ($enrollments[0]['id'] ?? '');
    if ($selected !== '' && !isset($byId[$selected])) {
        display_error(404, 'This enrollment could not be found.', $user);
        return;
    }

    $enrollment = $byId[$selected] ?? null;
    $grades = $enrollment ? query('SELECT subject, term, score FROM grades
        WHERE enrollment_id = ? ORDER BY subject, term', [$enrollment['id']])->fetchAll() : [];
    $terms = array_keys(modules()['grades']['fields']['term']['values']);
    $subjects = [];
    $scoresByTerm = [];
    foreach ($grades as $grade) {
        $subjects[$grade['subject']][$grade['term']] = $grade['score'];
        $scoresByTerm[$grade['term']][] = (float) $grade['score'];
    }

    layout_start('Report card', 'grades', $user);
    page_heading('Report card', $student['student'] . ' · ' . $student['student_no'],
        '<a class="btn btn-outline-secondary" href="' . e(url('profile', ['id' => $id])) . '">Student profile</a>'
        . '<button class="btn btn-primary" type="button" data-print>' . icon('printer') . ' Print report</button>');
    ?>
    <section class="panel report">
        <?php if ($enrollments): ?>
            <form class="filter-bar report-filter" method="get">
                <input type="hidden" name="page" value="report"><input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="filter-field">
                    <label for="report-enrollment" class="form-label">Enrollment</label>
                    <select id="report-enrollment" class="form-select" name="enrollment">
                        <?php foreach ($byId as $key => $row): ?>
                            <option value="<?= e($key) ?>" <?= (string) $key === $selected ? 'selected' : '' ?>>
                                <?= e($row['class'] . ' · ' . $row['school_year']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <button class="btn btn-primary">View report</button>
            </form>
            <div class="panel-heading">
                <div><h2><?= e($enrollment['class']) ?></h2><p>School year <?= e($enrollment['school_year']) ?></p></div>
                <?= badge($enrollment['status']) ?>
            </div>
        <?php endif ?>
        <?php if (!$subjects): ?><p class="empty muted">No grades recorded for this enrollment.</p><?php else: ?>
            <div class="table-responsive" role="region" aria-label="Report card grades" tabindex="0">
                <table class="table app-table report-table">
                    <thead><tr><th scope="col">Subject</th>
                        <?php foreach ($terms as $term): ?><th scope="col" class="text-end"><?= e($term) ?></th><?php endforeach ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $subject => $scores): ?>
                        <tr><th scope="row"><?= e($subject) ?></th>
                            <?php foreach ($terms as $term): ?>
                                <td class="text-end"><?= isset($scores[$term]) ? e(number_format((float) $scores[$term], 2) . '%') : '—' ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                    <tfoot><tr><th scope="row">Average of recorded grades</th>
                        <?php foreach ($terms as $term):
                            $scores = $scoresByTerm[$term] ?? []; ?>
                            <td class="text-end"><?= $scores ? e(number_format(array_sum($scores) / count($scores), 2) . '%') : '—' ?></td>
                        <?php endforeach ?>
                    </tr></tfoot>
                </table>
            </div>
        <?php endif ?>
    </section>
    <?php
    layout_end();
}
