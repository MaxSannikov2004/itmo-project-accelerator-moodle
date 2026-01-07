<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

class project_service {
    private const PREF_ACTIVE_PROJECT = 'local_itmoaccel_active_projectid';

    public static function list_projects(int $userid): array {
        global $DB;
        return $DB->get_records('local_itmoaccel_projects', ['userid' => $userid], 'isarchived ASC, timemodified DESC');
    }

    public static function get_project(int $projectid): \stdClass {
        global $DB;
        return $DB->get_record('local_itmoaccel_projects', ['id' => $projectid], '*', MUST_EXIST);
    }

    public static function get_active_project(int $userid): \stdClass {
        $active = (int)get_user_preferences(self::PREF_ACTIVE_PROJECT, 0, $userid);
        if ($active > 0) {
            $p = self::get_project_safe($active, $userid);
            if ($p && (int)$p->isarchived === 0) {
                return $p;
            }
        }

        $projects = self::list_projects($userid);
        foreach ($projects as $p) {
            if ((int)$p->isarchived === 0) {
                self::set_active_project($userid, (int)$p->id);
                return $p;
            }
        }

        $p = self::create_project($userid, 'Проект ' . userdate(time(), '%d.%m.%Y'));
        self::set_active_project($userid, (int)$p->id);
        return $p;
    }

    private static function get_project_safe(int $projectid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_itmoaccel_projects', ['id' => $projectid, 'userid' => $userid], '*', IGNORE_MISSING);
    }

    public static function set_active_project(int $userid, int $projectid): void {
        set_user_preference(self::PREF_ACTIVE_PROJECT, (string)$projectid, $userid);
    }

    public static function create_project(int $userid, string $name): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('local_itmoaccel_projects', (object)[
            'userid' => $userid,
            'name' => $name,
            'isarchived' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record('local_itmoaccel_projects', ['id' => $id], '*', MUST_EXIST);
    }

    public static function archive_project(int $userid, int $projectid): void {
        global $DB;
        $p = self::get_project_safe($projectid, $userid);
        if (!$p) {
            throw new \required_capability_exception(\context_system::instance(), 'local/itmoaccel:student', 'nopermissions', '');
        }
        $p->isarchived = 1;
        $p->timearchived = time();
        $p->timemodified = time();
        $DB->update_record('local_itmoaccel_projects', $p);

        // Если архивировали активный — переключаемся.
        $active = (int)get_user_preferences(self::PREF_ACTIVE_PROJECT, 0, $userid);
        if ($active === (int)$projectid) {
            set_user_preference(self::PREF_ACTIVE_PROJECT, '0', $userid);
        }
    }

    public static function assign_supervisor(int $projectid, int $supervisorid): void {
        global $DB;
        $now = time();
        $existing = $DB->get_record('local_itmoaccel_supervisors', ['projectid' => $projectid], '*', IGNORE_MISSING);
        if ($existing) {
            $existing->supervisorid = $supervisorid;
            $existing->timeassigned = $now;
            $DB->update_record('local_itmoaccel_supervisors', $existing);
            return;
        }
        $DB->insert_record('local_itmoaccel_supervisors', (object)[
            'projectid' => $projectid,
            'supervisorid' => $supervisorid,
            'timeassigned' => $now,
        ]);
    }

    public static function get_supervisor_user(int $projectid): ?\stdClass {
        global $DB;
        $map = $DB->get_record('local_itmoaccel_supervisors', ['projectid' => $projectid], '*', IGNORE_MISSING);
        if (!$map) {
            return null;
        }
        return $DB->get_record('user', ['id' => (int)$map->supervisorid], '*', IGNORE_MISSING);
    }

    public static function is_supervisor_for_project(int $userid, int $projectid): bool {
        global $DB;
        return $DB->record_exists('local_itmoaccel_supervisors', ['projectid' => $projectid, 'supervisorid' => $userid]);
    }

    /** @return \stdClass[] */
    public static function list_projects_for_supervisor(int $supervisorid): array {
        global $DB;
        $sql = "SELECT p.*
                  FROM {local_itmoaccel_projects} p
                  JOIN {local_itmoaccel_supervisors} s ON s.projectid = p.id
                 WHERE s.supervisorid = :sid
              ORDER BY p.timemodified DESC";
        return $DB->get_records_sql($sql, ['sid' => $supervisorid]);
    }
}
