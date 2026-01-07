<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_itmoaccel_install() {
    \local_itmoaccel\service\stage_service::ensure_default_stage_defs();
}
