<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

class access_service {
    public const PARTICIPANT_APPROVED = 10;

    public static function is_staff(): bool {
        $ctx = \context_system::instance();
        return has_capability('local/itmoaccel:manage', $ctx) || has_capability('local/itmoaccel:supervisor', $ctx);
    }

    public static function is_approved_participant(int $userid): bool {
        global $DB;
        $rec = $DB->get_record('local_itmoaccel_participants', ['userid' => $userid], '*', IGNORE_MISSING);
        return $rec && (int)$rec->status === self::PARTICIPANT_APPROVED;
    }

    public static function require_participant_or_staff(): void {
        require_login();
        global $USER;

        if (self::is_staff()) {
            return;
        }
        if (self::is_approved_participant((int)$USER->id)) {
            return;
        }

        redirect(new \moodle_url('/local/itmoaccel/apply.php'));
    }
}