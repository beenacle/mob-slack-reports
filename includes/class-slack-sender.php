<?php
defined('ABSPATH') || exit;

class MOB_Slack_Sender {

    /**
     * Upload a file to a Slack channel using the new files.uploadV2 flow:
     * 1. files.getUploadURLExternal  ->  upload URL + file_id
     * 2. PUT/POST binary to that URL
     * 3. files.completeUploadExternal  ->  share in channel
     *
     * @param string $file_path  Absolute path to the file to upload.
     * @param string $filename   Display filename in Slack (e.g. Report_2025-01-01.pdf).
     * @param string $title      Title shown in Slack message.
     * @param string $channel_id Slack channel ID.
     * @param string $bot_token  Slack bot token (xoxb-*).
     * @return array{ok: bool, error?: string, details?: array}
     */
    public static function upload_file(
        string $file_path,
        string $filename,
        string $title,
        string $channel_id,
        string $bot_token
    ): array {
        if (!$bot_token) {
            return ['ok' => false, 'error' => 'missing_bot_token'];
        }
        if (!$channel_id) {
            return ['ok' => false, 'error' => 'missing_channel_id'];
        }
        if (!file_exists($file_path)) {
            return ['ok' => false, 'error' => 'file_not_found'];
        }

        $length = filesize($file_path);

        $get_url = self::api_post($bot_token, 'files.getUploadURLExternal', [
            'filename' => $filename,
            'length'   => $length,
        ]);

        if (empty($get_url['ok']) || empty($get_url['upload_url']) || empty($get_url['file_id'])) {
            return [
                'ok'      => false,
                'error'   => 'getUploadURLExternal_failed',
                'details' => $get_url,
            ];
        }

        $upload = wp_remote_request($get_url['upload_url'], [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/octet-stream'],
            'body'    => file_get_contents($file_path),
            'timeout' => 60,
        ]);

        if (is_wp_error($upload)) {
            return ['ok' => false, 'error' => 'upload_failed', 'details' => $upload->get_error_message()];
        }

        $complete = wp_remote_post('https://slack.com/api/files.completeUploadExternal', [
            'headers' => [
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type'  => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode([
                'files' => [[
                    'id'    => $get_url['file_id'],
                    'title' => $title,
                ]],
                'channel_id' => $channel_id,
            ]),
            'timeout' => 30,
        ]);

        $body = json_decode(wp_remote_retrieve_body($complete), true) ?: [];

        if (empty($body['ok'])) {
            return [
                'ok'      => false,
                'error'   => 'completeUploadExternal_failed',
                'details' => $body,
            ];
        }

        return $body;
    }

    private static function api_post(string $token, string $endpoint, array $form): array {
        $res = wp_remote_post('https://slack.com/api/' . $endpoint, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'body'    => $form,
            'timeout' => 30,
        ]);

        if (is_wp_error($res)) {
            return ['ok' => false, 'error' => $res->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($res), true) ?: [];
    }
}
