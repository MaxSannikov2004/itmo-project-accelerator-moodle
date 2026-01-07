<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

class notifier {
    public static function notify_stage_submitted(int $fromuserid, int $touserid, string $projectname, string $stagetitle): void {
        $message = new \core\message\message();
        $message->component = 'local_itmoaccel';
        $message->name = 'stage_submitted';
        $message->userfrom = \core_user::get_user($fromuserid);
        $message->userto = \core_user::get_user($touserid);
        $message->subject = "На согласование: {$projectname}";
        $message->fullmessage = "Этап \"{$stagetitle}\" отправлен на согласование.";
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = "Этап \"{$stagetitle}\" отправлен на согласование.";
        $message->notification = 1;
        message_send($message);
    }

    public static function notify_stage_decided(int $fromuserid, int $touserid, string $projectname, string $stagetitle, bool $approved): void {
        $message = new \core\message\message();
        $message->component = 'local_itmoaccel';
        $message->name = 'stage_decided';
        $message->userfrom = \core_user::get_user($fromuserid);
        $message->userto = \core_user::get_user($touserid);
        $status = $approved ? 'Согласовано' : 'Отклонено';
        $message->subject = "{$status}: {$projectname}";
        $message->fullmessage = "{$status}. Этап \"{$stagetitle}\".";
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = "{$status}: \"{$stagetitle}\"";
        $message->notification = 1;
        message_send($message);
    }
}
