<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

use PhpOffice\PhpSpreadsheet\IOFactory;

class import_service {

    /**
     * @return array{ok:int, errors:array<int,string>}
     */
    public static function import_assignments(string $filepath): array {
        global $DB;

        $rows = self::read_rows($filepath);
        $ok = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header is 1
            $studentemail = trim((string)($row['student_email'] ?? ''));
            $supervisoremail = trim((string)($row['supervisor_email'] ?? ''));
            $school = trim((string)($row['school'] ?? ''));
            $class = trim((string)($row['class'] ?? ''));

            if ($studentemail === '' || $supervisoremail === '') {
                $errors[] = "Line {$line}: missing emails";
                continue;
            }

            $student = $DB->get_record('user', ['email' => $studentemail], '*', IGNORE_MISSING);
            if (!$student) {
                $errors[] = "Line {$line}: " . get_string('err_user_not_found', 'local_itmoaccel', $studentemail);
                continue;
            }

            $supervisor = $DB->get_record('user', ['email' => $supervisoremail], '*', IGNORE_MISSING);
            if (!$supervisor) {
                $errors[] = "Line {$line}: " . get_string('err_user_not_found', 'local_itmoaccel', $supervisoremail);
                continue;
            }

            // Ensure active project exists.
            $project = project_service::get_active_project((int)$student->id);
            project_service::assign_supervisor((int)$project->id, (int)$supervisor->id);

            // Upsert profile.
            self::upsert_profile((int)$student->id, $school, $class);

            $ok++;
        }

        return ['ok' => $ok, 'errors' => $errors];
    }

    private static function upsert_profile(int $userid, string $school, string $class): void {
        global $DB;
        $now = time();
        $rec = $DB->get_record('local_itmoaccel_profiles', ['userid' => $userid], '*', IGNORE_MISSING);
        if (!$rec) {
            $DB->insert_record('local_itmoaccel_profiles', (object)[
                'userid' => $userid,
                'school' => $school,
                'class' => $class,
                'timemodified' => $now,
            ]);
            return;
        }
        $rec->school = $school;
        $rec->class = $class;
        $rec->timemodified = $now;
        $DB->update_record('local_itmoaccel_profiles', $rec);
    }

    /**
     * Reads rows as associative arrays with keys:
     * student_email, supervisor_email, school, class
     *
     * Supports CSV and XLSX. Moodle bundles PhpSpreadsheet. :contentReference[oaicite:3]{index=3}
     */
    private static function read_rows(string $filepath): array {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return self::read_csv($filepath);
        }

        if ($ext === 'xlsx') {
            return self::read_xlsx($filepath);
        }

        // fallback: try CSV
        return self::read_csv($filepath);
    }

    private static function read_csv(string $filepath): array {
        $rows = [];
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return $rows;
        }
        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $data);
                continue;
            }
            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = $data[$idx] ?? '';
            }
            $rows[] = $assoc;
        }
        fclose($handle);
        return $rows;
    }

    private static function read_xlsx(string $filepath): array {
        $rows = [];
        $spreadsheet = IOFactory::load($filepath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $header = null;
        for ($r = 1; $r <= $highestRow; $r++) {
            $line = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, false)[0] ?? [];
            $line = array_map(static fn($v) => is_string($v) ? trim($v) : (string)$v, $line);

            if ($header === null) {
                $header = $line;
                continue;
            }

            $assoc = [];
            foreach ($header as $idx => $key) {
                if ($key === '') continue;
                $assoc[$key] = $line[$idx] ?? '';
            }
            $rows[] = $assoc;
        }
        return $rows;
    }
}
