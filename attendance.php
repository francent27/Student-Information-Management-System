<?php

declare(strict_types=1);

function register_view(array $user, bool $post): void
{
    $classes = query("SELECT id, CONCAT(name, ' · ', school_year) AS label FROM classes
        ORDER BY school_year DESC, name")->fetchAll(PDO::FETCH_KEY_PAIR);
    $classId = input($_GET, 'class_id');
    $date = input($_GET, 'date', date('Y-m-d'));
    $valid = isset($classes[$classId]) && valid_date($date);
    $statuses = modules()['attendance']['fields']['status']['values'];
    $error = '';
    $roster = [];

    if ($valid) {
        $roster = query("SELECT n.id, s.first_name, s.last_name, s.student_no, a.status
            FROM enrollments n JOIN students s ON s.id = n.student_id
            LEFT JOIN attendance a ON a.enrollment_id = n.id AND a.attendance_date = ?
            WHERE n.class_id = ? AND n.status = 'Enrolled'
            ORDER BY s.last_name, s.first_name", [$date, $classId])->fetchAll();
    }

    if ($post) {
        $submitted = $_POST['status'] ?? null;
        if (!$valid || !is_array($submitted)) {
            $error = 'Choose a class and a valid attendance date.';
        } else {
            $changes = [];
            foreach ($roster as $student) {
                $status = input($submitted, (string) $student['id']);
                if ($status === '') {
                    continue;
                }
                if (!isset($statuses[$status])) {
                    $error = 'Select a valid status for each student.';
                    break;
                }
                $changes[$student['id']] = $status;
            }
            if (!$error && $changes) {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $enrolled = query("SELECT id FROM enrollments WHERE class_id = ? AND status = 'Enrolled'
                        ORDER BY id FOR UPDATE", [$classId])->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($changes as $id => $status) {
                        if (!in_array($id, $enrolled)) {
                            throw new DomainException('The roster changed. Reload it before saving.');
                        }
                        query('INSERT INTO attendance (enrollment_id, attendance_date, status) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = ?', [$id, $date, $status, $status]);
                    }
                    $pdo->commit();
                    flash('Attendance register saved.');
                    redirect('register', ['class_id' => $classId, 'date' => $date]);
                } catch (PDOException | DomainException $failure) {
                    $pdo->rollBack();
                    $error = $failure instanceof PDOException ? record_error($failure) : $failure->getMessage();
                }
            } elseif (!$error) {
                $error = 'Select at least one attendance status.';
            }
        }
        http_response_code(422);
    }

    layout_start('Attendance register', 'attendance', $user);
    page_heading('Attendance register', 'Record the status of each student in a class for one date.',
        '<a class="btn btn-outline-secondary" href="' . e(url('attendance')) . '">Attendance records</a>');
    ?>
    <section class="panel mb-4">
        <form class="filter-bar" method="get">
            <input type="hidden" name="page" value="register">
            <div class="filter-field">
                <label for="register-class" class="form-label">Class</label>
                <select id="register-class" name="class_id" class="form-select" required>
                    <option value="">Select a class</option>
                    <?php foreach ($classes as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= (string) $value === $classId ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="register-date" class="form-label">Date</label>
                <input type="date" id="register-date" name="date" value="<?= e($date) ?>" class="form-control" required>
            </div>
            <button class="btn btn-primary">Load roster</button>
        </form>
    </section>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif ?>
    <?php if ($valid && $roster): ?>
        <form method="post" action="<?= e(url('register', ['class_id' => $classId, 'date' => $date])) ?>" class="panel">
            <?= csrf_field() ?>
            <div class="panel-heading">
                <div><h2><?= e($classes[$classId]) ?></h2><p><?= e(formatted_date($date, 'l, F j, Y')) ?></p></div>
                <button type="button" class="btn btn-outline-secondary" data-mark-present>Mark unrecorded present</button>
            </div>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Class roster">
                <table class="table app-table">
                    <thead><tr><th scope="col">Student</th><th scope="col">Student ID</th><th scope="col">Attendance</th></tr></thead>
                    <tbody>
                    <?php foreach ($roster as $student):
                        $name = $student['first_name'] . ' ' . $student['last_name'];
                        $status = $post && is_array($_POST['status'] ?? null)
                            ? input($_POST['status'], (string) $student['id']) : '';
                        ?>
                        <tr>
                            <th scope="row"><?= e($name) ?></th><td><?= e($student['student_no']) ?></td>
                            <td><select class="form-select form-select-sm attendance-select" name="status[<?= e($student['id']) ?>]"
                                        aria-label="Attendance for <?= e($name) ?>" data-existing="<?= e($student['status']) ?>">
                                <option value=""><?= $student['status'] ? e('Keep ' . $student['status']) : 'Unrecorded' ?></option>
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $value === $status ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach ?>
                            </select></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-footer"><button class="btn btn-primary">Save attendance</button></div>
        </form>
    <?php elseif ($classId !== '' && !$error): ?>
        <div class="panel empty"><?= $valid ? 'This class has no enrolled students.' : 'Select an available class and a valid date.' ?></div>
    <?php endif ?>
    <?php
    layout_end();
}
