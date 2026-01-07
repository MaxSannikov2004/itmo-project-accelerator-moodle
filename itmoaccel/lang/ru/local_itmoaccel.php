<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Проектный акселератор ИТМО';

$string['nav_projects'] = 'Мои проекты';
$string['nav_project'] = 'Проект';
$string['nav_profile'] = 'Профиль';
$string['nav_supervisor'] = 'Дашборд руководителя';
$string['nav_manage'] = 'Управление (назначения/импорт)';
$string['nav_stages'] = 'Этапы (админка)';
$string['nav_materials'] = 'Материалы';

$string['ai_base_url'] = 'URL ИИ-сервиса';
$string['ai_base_url_desc'] = 'Например https://ai.example.ru (без /api). Если пусто — ИИ скрывается.';
$string['ai_token'] = 'Токен ИИ-сервиса';
$string['ai_token_desc'] = 'Если используете shared secret, он уйдёт заголовком X-ITMOACCEL-TOKEN.';

$string['projects'] = 'Проекты';
$string['create_project'] = 'Создать проект';
$string['archive_project'] = 'В архив';
$string['set_active'] = 'Сделать текущим';

$string['profile'] = 'Профиль';
$string['school'] = 'Школа';
$string['class'] = 'Класс';
$string['save'] = 'Сохранить';

$string['stages'] = 'Этапы';
$string['add_stage'] = 'Добавить этап';
$string['shortname'] = 'Код';
$string['title'] = 'Название';
$string['handlertype'] = 'Тип';
$string['enabled'] = 'Включён';
$string['sortorder'] = 'Порядок';

$string['manage'] = 'Управление';
$string['assign_manual'] = 'Назначить вручную (по email)';
$string['student_email'] = 'Email школьника';
$string['supervisor_email'] = 'Email руководителя';
$string['assign'] = 'Назначить';

$string['import'] = 'Импорт (CSV/XLSX)';
$string['import_file'] = 'Файл импорта';
$string['import_run'] = 'Импортировать';

$string['materials'] = 'Материалы';
$string['manage_materials'] = 'Управлять материалами';
$string['add_material'] = 'Добавить материал';
$string['material_title'] = 'Название';
$string['material_file'] = 'PDF/файл';

$string['status_draft'] = 'В работе';
$string['status_pending'] = 'Ожидает согласования';
$string['status_approved'] = 'Согласовано';
$string['status_rejected'] = 'Отклонено';

$string['submit_for_approval'] = 'Отправить на согласование';
$string['approve'] = 'Согласовать';
$string['reject'] = 'Отклонить';
$string['comment'] = 'Комментарий';

$string['ai_topics'] = 'Генератор тем';
$string['ai_prompt'] = 'Запрос/описание (для генератора тем)';
$string['generate'] = 'Сгенерировать';
$string['use_this_topic'] = 'Использовать эту тему';

$string['err_user_not_found'] = 'Пользователь не найден: {$a}';
$string['err_ai_not_configured'] = 'ИИ не настроен (заполните URL в настройках плагина).';
