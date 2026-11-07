<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function test_session_token(string $session): string {
  $file = app_config()['session']['save_path'].'/sess_'.$session;
  $raw = is_file($file) ? file_get_contents($file) : '';
  return preg_match('/csrf_token\|s:\d+:"([a-f0-9]{64})";/',$raw,$match) ? $match[1] : '';
}