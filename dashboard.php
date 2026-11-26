<?php

declare(strict_types=1);

function dashboard_view(array $user): void
{
    $modules = modules();
    $today = new DateTimeImmutable('today');
    $start = $today->modify('-6 days');
    [$studentScope, $studentParams] = scope('student', $user);
    [$classScope, $classParams] = scope('class', $user);
    [$enrollmentScope, $enrollmentParams] = scope('enrollment', $user);
    [$noticeScope, $noticeParams] = scope('announcement', $user);

    $students = query("SELECT COUNT(*) AS total, COALESCE(SUM(s.status = 'Active'), 0) AS active
        FROM students s WHERE $studentScope", $studentParams)->fetch();
    $classes = (int) query("SELECT COUNT(*) FROM classes c WHERE $classScope", $classParams)->fetchColumn();
    $set = query("SELECT a.attendance_date,
        SUM(a.status = 'Present') AS present, SUM(a.status = 'Late') AS late, COUNT(*) AS total
        FROM {$modules['attendance']['from']} WHERE $enrollmentScope AND a.attendance_date BETWEEN ? AND ?
        GROUP BY a.attendance_date", [...$enrollmentParams, $start->format('Y-m-d'), $today->format('Y-m-d')])
        ->fetchAll(PDO::FETCH_UNIQUE);
    $daily = $set[$today->format('Y-m-d')] ?? ['present' => 0, 'late' => 0, 'total' => 0];
    $attendance = $daily['total'] ? round(100 * ($daily['present'] + $daily['late']) / $daily['total']) . '%' : '—';

    $schedule = query("SELECT t.*, c.name AS class FROM {$modules['timetable']['from']}
        WHERE $classScope AND t.starts_at >= ? ORDER BY t.starts_at, t.id LIMIT 3",
        [...$classParams, date('Y-m-d H:i:s')])->fetchAll();
    $exams = (int) query("SELECT COUNT(*) FROM {$modules['timetable']['from']}
        WHERE $classScope AND t.kind = 'Exam' AND t.starts_at >= ?", [...$classParams, date('Y-m-d H:i:s')])->fetchColumn();
    $notices = query("SELECT m.* FROM announcements m WHERE ($noticeScope) AND m.published_on <= ?
        ORDER BY m.published_on DESC, m.id DESC LIMIT 3", [...$noticeParams, $today->format('Y-m-d')])->fetchAll();
    $recent = query("SELECT {$modules['students']['select']} FROM students s
        WHERE $studentScope ORDER BY s.id DESC LIMIT 4", $studentParams)->fetchAll();

    $manage = can_manage($user);
    $firstName = preg_split('/\s+/u', $user['name'])[0];
    $heading = match ($user['role']) {
        'admin' => 'School overview', 'teacher' => 'Class overview', 'student' => 'Student overview', 'parent' => 'Parent overview',
    };
    $copy = match ($user['role']) {
        'admin' => 'Review today’s attendance, student records and upcoming campus events.',
        'teacher' => 'Review your class rosters, attendance and upcoming sessions.',
        'student' => 'Check your classes, attendance and upcoming exams.',
        'parent' => 'See academic records and schedules for the students linked to your account.',
    };
    $cards = [
        ['people', 'violet', 'Active students', $students['active'], number_format((int) $students['total']) . ' visible records', 'students', ['status' => 'Active']],
        ['building', 'blue', 'Class sections', $classes, 'Across school years', 'classes', []],
        ['clipboard-check', 'green', 'Attendance today', $attendance,
            $daily['total'] ? number_format((int) $daily['total']) . ' records marked' : 'No records today',
            'attendance', ['attendance_date' => $today->format('Y-m-d')]],
        ['calendar-event', 'orange', 'Upcoming exams', $exams, 'Scheduled exams', 'timetable', ['kind' => 'Exam', 'period' => 'Upcoming']],
    ];
    layout_start('Overview', 'dashboard', $user);
    page_heading($heading, $today->format('l, F j, Y'), $manage
        ? '<a class="btn btn-primary" href="' . e(url('students', ['action' => 'new'])) . '">' . icon('plus-lg') . ' Add student</a>' : '');
    ?>
    <section class="welcome-banner" aria-labelledby="welcome-title">
        <div class="welcome-content">
            <div class="welcome-role"><?= icon('mortarboard') ?> <?= e(role_name($user['role'])) ?> portal</div>
            <h2 id="welcome-title">Welcome back, <?= e($firstName) ?>.</h2>
            <p><?= e($copy) ?></p>
            <a class="btn btn-welcome" href="<?= e(url($manage ? 'register' : 'timetable')) ?>">
                <?= icon($manage ? 'clipboard-check' : 'calendar3') ?> <?= $manage ? 'Take attendance' : 'View timetable' ?>
            </a>
        </div>
        <div class="welcome-art" aria-hidden="true">
            <span class="art-orbit orbit-outer"></span><span class="art-orbit orbit-middle"></span>
            <span class="art-orbit orbit-inner"></span><span class="art-icon"><?= icon('mortarboard-fill') ?></span>
        </div>
    </section>
    <section class="stat-grid" aria-label="Campus metrics">
        <?php foreach ($cards as [$icon, $tone, $label, $value, $note, $page, $filters]): ?>
            <a class="panel stat-card" href="<?= e(url($page, $filters)) ?>">
                <div class="metric-icon metric-<?= e($tone) ?>"><?= icon($icon) ?></div>
                <div><h2><?= e($label) ?></h2><strong><?= e(is_numeric($value) ? number_format((int) $value) : $value) ?></strong>
                    <span><?= e($note) ?></span></div>
            </a>
        <?php endforeach ?>
    </section>
    <div class="dashboard-grid">
        <section class="panel chart-panel" aria-labelledby="chart-title">
            <div class="panel-heading">
                <div><h2 id="chart-title">Attendance trend</h2><p>Last seven days</p></div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('attendance')) ?>">View attendance</a>
            </div>
            <?php attendance_chart($start, $set); ?>
        </section>
        <section class="panel notice-panel" aria-labelledby="notice-title">
            <div class="panel-heading">
                <div><h2 id="notice-title">Announcements</h2><p>Latest campus updates</p></div>
                <a class="text-link" href="<?= e(url('announcements')) ?>">View all</a>
            </div>
            <?php if (!$notices): ?><p class="empty muted">No announcements yet.</p><?php endif ?>
            <ul class="notice-list">
                <?php foreach ($notices as $notice): ?>
                    <li>
                        <div class="notice-icon"><?= icon('megaphone') ?></div>
                        <div>
                            <a href="<?= e(url('announcements', ['q' => $notice['title']])) ?>"><?= e($notice['title']) ?></a>
                            <p><?= e(formatted_date($notice['published_on'], 'M j')) ?> <span aria-hidden="true">·</span> <?= e($notice['audience']) ?></p>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
        <section class="panel recent-panel" aria-labelledby="student-title">
            <div class="panel-heading">
                <div><h2 id="student-title"><?= $manage ? 'Recently added students' : 'Student records' ?></h2><p>Profiles and academic status</p></div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('students')) ?>">View students</a>
            </div>
            <?php if ($recent):
                $studentModule = $modules['students'];
                if ($user['role'] === 'teacher') {
                    unset($studentModule['columns']['class']);
                }
                records_table('students', $studentModule, $recent, false);
            else: ?><p class="empty muted">No student records available.</p><?php endif ?>
        </section>
        <section class="panel" aria-labelledby="schedule-title">
            <div class="panel-heading">
                <div><h2 id="schedule-title">Upcoming schedule</h2><p>Classes and exams</p></div>
                <a class="text-link" href="<?= e(url('timetable')) ?>">View all</a>
            </div>
            <?php if (!$schedule): ?><p class="empty muted">No upcoming events.</p><?php endif ?>
            <ul class="notice-list schedule-list">
                <?php foreach ($schedule as $event): ?>
                    <li>
                        <div class="date-tile">
                            <span><?= e(formatted_date($event['starts_at'], 'M')) ?></span>
                            <strong><?= e(formatted_date($event['starts_at'], 'j')) ?></strong>
                        </div>
                        <div><a href="<?= e(url('timetable', ['date' => substr($event['starts_at'], 0, 10)])) ?>"><?= e($event['title']) ?></a>
                            <p><?= e(formatted_date($event['starts_at'], 'g:i A')) ?> <span aria-hidden="true">·</span> <?= e($event['class']) ?></p></div>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    </div>
    <?php
    layout_end();
}

function attendance_chart(DateTimeImmutable $start, array $set): void
{
    if (!$set) {
        echo '<p class="empty muted">No attendance recorded in the last seven days.</p>';
        return;
    }
    $days = [];
    for ($index = 0; $index < 7; $index++) {
        $date = $start->modify("+$index days");
        $data = $set[$date->format('Y-m-d')] ?? ['present' => 0, 'late' => 0, 'total' => 0];
        $days[] = [$date, $data['present'], $data['late'], $data['total']];
    }
    ?>
    <div class="chart-wrap">
        <div class="chart" aria-hidden="true">
            <div class="chart-axis"><span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0%</span></div>
            <div class="chart-plot">
                <div class="chart-grid"><span></span><span></span><span></span><span></span><span></span></div>
                <div class="chart-bars">
                    <?php foreach ($days as [$date, $present, $late, $total]):
                        $presentShare = $total ? 100 * $present / $total : 0;
                        $lateShare = $total ? 100 * $late / $total : 0;
                        ?>
                        <div class="chart-slot">
                            <div class="chart-track<?= $total ? '' : ' no-data' ?>"
                                title="<?= e($date->format('D, M j') . " · $present present · $late late · $total recorded") ?>">
                                <span class="bar-late" style="height:<?= e(round($lateShare, 3)) ?>%"></span>
                                <span class="bar-present" style="height:<?= e(round($presentShare, 3)) ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
                <div class="chart-labels"><?php foreach ($days as [$date]): ?><span><?= e($date->format('D')) ?></span><?php endforeach ?></div>
            </div>
        </div>
        <div class="chart-caption">
            <span><i class="legend-dot legend-present"></i>Present</span>
            <span><i class="legend-dot legend-late"></i>Late</span>
            <span><i class="legend-dot legend-other"></i>Absent or excused</span>
        </div>
        <details class="chart-data">
            <summary>Attendance numbers</summary>
            <div class="table-responsive">
                <table class="table app-table"><thead><tr><th>Date</th><th>Present</th><th>Late</th><th>Absent or excused</th><th>Recorded</th></tr></thead><tbody>
                    <?php foreach ($days as [$date, $present, $late, $total]): ?>
                        <tr><th scope="row"><?= e($date->format('M j')) ?></th><td><?= e($present) ?></td>
                            <td><?= e($late) ?></td><td><?= e($total - $present - $late) ?></td><td><?= e($total) ?></td></tr>
                    <?php endforeach ?>
                </tbody></table>
            </div>
        </details>
    </div>
    <?php
}
