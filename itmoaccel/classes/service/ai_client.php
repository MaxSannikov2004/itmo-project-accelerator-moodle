<?php
namespace local_itmoaccel\service;

defined('MOODLE_INTERNAL') || die();

class ai_client {
    /**
     * @return string[] topics
     */
    public static function generate_batch_topics(string $prompt, int $count = 8): array {
        $base = trim((string)get_config('local_itmoaccel', 'ai_base_url'));
        if ($base === '') {
            throw new \moodle_exception('err_ai_not_configured', 'local_itmoaccel');
        }

        $token = (string)get_config('local_itmoaccel', 'ai_token');
        $url = rtrim($base, '/') . '/api/generate-batch-topics';

        $curl = new \curl();
        $headers = ['Content-Type: application/json'];
        if ($token !== '') {
            $headers[] = 'X-ITMOACCEL-TOKEN: ' . $token;
        }
        $curl->setHeader($headers);

        $payload = json_encode(['prompt' => $prompt, 'count' => $count], JSON_UNESCAPED_UNICODE);
        $resp = $curl->post($url, $payload);

        $data = json_decode($resp ?? '', true);
        if (!is_array($data)) {
            return [];
        }

        // Ожидаем либо {topics:[...]} либо просто [...]
        if (isset($data['topics']) && is_array($data['topics'])) {
            return array_values(array_filter(array_map('strval', $data['topics'])));
        }
        if (array_is_list($data)) {
            return array_values(array_filter(array_map('strval', $data)));
        }
        return [];
    }
}
