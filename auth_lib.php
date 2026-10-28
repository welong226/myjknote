<?php
/**
 * myjknote API keys:
 *   shift    → 班表 api.php（讀寫）
 *   personal → 票券 / 銀行 / 卡片
 *
 * Source (first match):
 *   1) data/keys.php  return ['shift'=>'…','personal'=>'…'];
 *   2) env MYJK_SHIFT_KEY / MYJK_PERSONAL_KEY
 *
 * Client sends key via header X-Myjk-Key, or ?key= / POST key=
 */

function myjk_load_keys() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $file = __DIR__ . '/data/keys.php';
    if (is_file($file)) {
        $k = include $file;
        if (is_array($k)) {
            $cache = array(
                'shift' => isset($k['shift']) ? (string)$k['shift'] : '',
                'personal' => isset($k['personal']) ? (string)$k['personal'] : '',
            );
            return $cache;
        }
    }
    $cache = array(
        'shift' => (string)(getenv('MYJK_SHIFT_KEY') ?: ''),
        'personal' => (string)(getenv('MYJK_PERSONAL_KEY') ?: ''),
    );
    return $cache;
}

function myjk_request_key() {
    if (!empty($_SERVER['HTTP_X_MYJK_KEY'])) {
        return (string)$_SERVER['HTTP_X_MYJK_KEY'];
    }
    if (isset($_GET['key']) && is_string($_GET['key'])) {
        return $_GET['key'];
    }
    if (isset($_POST['key']) && is_string($_POST['key'])) {
        return $_POST['key'];
    }
    return '';
}

/** @param 'shift'|'personal' $kind */
function myjk_require_key($kind) {
    $keys = myjk_load_keys();
    $expected = isset($keys[$kind]) ? $keys[$kind] : '';
    if ($expected === '') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(array(
            'error' => 'server keys not configured',
            'hint' => 'Set MYJK_SHIFT_KEY / MYJK_PERSONAL_KEY or data/keys.php',
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $got = myjk_request_key();
    if ($got === '' || !hash_equals($expected, $got)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('WWW-Authenticate: Key realm="myjknote"');
        echo json_encode(array('error' => 'unauthorized'), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
