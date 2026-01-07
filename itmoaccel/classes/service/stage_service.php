<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

class stage_service {
    public const STATUS_DRAFT = 0;
    public const STATUS_PENDING = 10;
    public const STATUS_APPROVED = 20;
    public const STATUS_REJECTED = 30;

    public static function ensure_default_stage_defs(): void {
        global $DB;
        if ($DB->count_records('local_itmoaccel_stage_defs') > 0) {
            return;
        }
        $now = time();
        $defs = [
            ['ai_topics', 'Генератор тем', 'ai_topics'],
            ['topic', 'Тема', 'text'],
            ['goals', 'Цели/задачи', 'text'],
            ['plan', 'План', 'text'],
            ['files', 'Файлы', 'files'],
        ];
        $i = 10;
        foreach ($defs as [$shortname, $title, $type]) {
            $DB->insert_record('local_itmoaccel_stage_defs', (object)[
                'shortname' => $shortname,
                'title' => $title,
                'handlertype' => $type,
                'sortorder' => $i,
                'enabled' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $i += 10;
        }
    }

    /** @return \stdClass[] */
    public static function get_stage_defs(): array {
        global $DB;
        return $DB->get_records('local_itmoaccel_stage_defs', ['enabled' => 1], 'sortorder ASC');
    }

    public static function get_stage_def_by_shortname(string $shortname): ?\stdClass {
        global $DB;
        return $DB->get_record('local_itmoaccel_stage_defs', ['shortname' => $shortname], '*', IGNORE_MISSING);
    }

    public static function get_latest_submission(int $projectid, int $stagedefid): ?\stdClass {
        global $DB;
        return $DB->get_record_sql(
            "SELECT *
               FROM {local_itmoaccel_submissions}
              WHERE projectid = :pid AND stagedefid = :sid
              ORDER BY version DESC",
            ['pid' => $projectid, 'sid' => $stagedefid],
            IGNORE_MULTIPLE
        );
    }

    public static function upsert_text_submission(int $projectid, int $stagedefid, int $userid, string $text): \stdClass {
        global $DB;
        $now = time();
        $existing = self::get_latest_submission($projectid, $stagedefid);
        $datajson = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);

        if (!$existing) {
            $id = $DB->insert_record('local_itmoaccel_submissions', (object)[
                'projectid' => $projectid,
                'stagedefid' => $stagedefid,
                'version' => 1,
                'status' => self::STATUS_DRAFT,
                'datajson' => $datajson,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return $DB->get_record('local_itmoaccel_submissions', ['id' => $id], '*', MUST_EXIST);
        }

        // Если было approved, начинаем новую версию.
        if ((int)$existing->status === self::STATUS_APPROVED) {
            $id = $DB->insert_record('local_itmoaccel_submissions', (object)[
                'projectid' => $projectid,
                'stagedefid' => $stagedefid,
                'version' => ((int)$existing->version) + 1,
                'status' => self::STATUS_DRAFT,
                'datajson' => $datajson,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return $DB->get_record('local_itmoaccel_submissions', ['id' => $id], '*', MUST_EXIST);
        }

        $existing->datajson = $datajson;
        $existing->timemodified = $now;
        if ((int)$existing->status === self::STATUS_REJECTED) {
            $existing->status = self::STATUS_DRAFT;
        }
        $DB->update_record('local_itmoaccel_submissions', $existing);
        return $existing;
    }

    public static function mark_pending(int $submissionid): void {
        global $DB;
        $sub = $DB->get_record('local_itmoaccel_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $sub->status = self::STATUS_PENDING;
        $sub->timesubmitted = time();
        $sub->timemodified = time();
        $DB->update_record('local_itmoaccel_submissions', $sub);
    }

    public static function decide(int $submissionid, int $deciderid, bool $approved, string $comment): void {
        global $DB;
        $sub = $DB->get_record('local_itmoaccel_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $sub->status = $approved ? self::STATUS_APPROVED : self::STATUS_REJECTED;
        $sub->timedecided = time();
        $sub->deciderid = $deciderid;
        $sub->decisioncomment = $comment;
        $sub->timemodified = time();
        $DB->update_record('local_itmoaccel_submissions', $sub);
    }

    public static function ensure_files_submission(int $projectid, int $stagedefid): \stdClass {
        global $DB;
        $now = time();
        $existing = self::get_latest_submission($projectid, $stagedefid);

        if (!$existing) {
            $id = $DB->insert_record('local_itmoaccel_submissions', (object)[
                'projectid' => $projectid,
                'stagedefid' => $stagedefid,
                'version' => 1,
                'status' => self::STATUS_DRAFT,
                'datajson' => json_encode(['type' => 'files'], JSON_UNESCAPED_UNICODE),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return $DB->get_record('local_itmoaccel_submissions', ['id' => $id], '*', MUST_EXIST);
        }

        // Если уже согласовано — создаём новую версию, чтобы не править “подписанную”.
        if ((int)$existing->status === self::STATUS_APPROVED) {
            $id = $DB->insert_record('local_itmoaccel_submissions', (object)[
                'projectid' => $projectid,
                'stagedefid' => $stagedefid,
                'version' => ((int)$existing->version) + 1,
                'status' => self::STATUS_DRAFT,
                'datajson' => json_encode(['type' => 'files'], JSON_UNESCAPED_UNICODE),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            return $DB->get_record('local_itmoaccel_submissions', ['id' => $id], '*', MUST_EXIST);
        }

        return $existing;
    }


    public static function status_label(int $status): string {
        switch ($status) {
            case self::STATUS_PENDING: return get_string('status_pending', 'local_itmoaccel');
            case self::STATUS_APPROVED: return get_string('status_approved', 'local_itmoaccel');
            case self::STATUS_REJECTED: return get_string('status_rejected', 'local_itmoaccel');
            default: return get_string('status_draft', 'local_itmoaccel');
        }
    }
}