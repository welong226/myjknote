<?php
/*
  我的票券 API
  GET  ?action=list              -> 票券陣列
  GET  ?action=img&id=xxx        -> 截圖二進位
  POST multipart action=upsert   -> 新增／更新（可附 image）
  POST JSON      action=delete   -> { id }
  POST JSON      action=status   -> { id, status }
*/

header('Cache-Control: no-store');

$dataDir = __DIR__ . '/data';
$imgDir  = $dataDir . '/vouchers';
$metaFile = $dataDir . '/vouchers.json';

function ensure_dirs($dataDir, $imgDir) {
    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true)) return false;
    if (!is_dir($imgDir) && !mkdir($imgDir, 0755, true)) return false;
    return true;
}

function read_vouchers($file) {
    if (!file_exists($file)) return array();
    $txt = file_get_contents($file);
    if ($txt === false || $txt === '') return array();
    $obj = json_decode($txt, true);
    return is_array($obj) ? $obj : array();
}

function write_vouchers($file, $list) {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
    $tmp = $file . '.tmp';
    $fp = fopen($tmp, 'c+');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    ftruncate($fp, 0);
    fseek($fp, 0);
    fwrite($fp, json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return rename($tmp, $file);
}

function find_image_path($imgDir, $id) {
    foreach (array('jpg', 'jpeg', 'png', 'webp', 'gif') as $ext) {
        $p = $imgDir . '/' . $id . '.' . $ext;
        if (file_exists($p)) return $p;
    }
    return null;
}

function json_out($obj, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($obj, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ensure_dirs($dataDir, $imgDir)) {
    json_out(array('error' => 'cannot create data dirs'), 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET') {
    if ($action === 'img') {
        $id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']) : '';
        if ($id === '') { http_response_code(400); exit; }
        $path = find_image_path($imgDir, $id);
        if (!$path) { http_response_code(404); exit; }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = array(
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'
        );
        header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    // default list
    $list = read_vouchers($metaFile);
    usort($list, function ($a, $b) {
        $ae = isset($a['expires']) ? $a['expires'] : '9999-99-99';
        $be = isset($b['expires']) ? $b['expires'] : '9999-99-99';
        if ($ae === $be) {
            $at = isset($a['updatedAt']) ? $a['updatedAt'] : '';
            $bt = isset($b['updatedAt']) ? $b['updatedAt'] : '';
            return strcmp($bt, $at);
        }
        return strcmp($ae, $be);
    });
    json_out($list);
}

if ($method === 'POST') {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    // multipart upsert
    if (stripos($contentType, 'multipart/form-data') !== false || isset($_POST['action'])) {
        $act = isset($_POST['action']) ? $_POST['action'] : 'upsert';
        if ($act !== 'upsert') json_out(array('error' => 'bad action'), 400);

        $id = isset($_POST['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['id']) : '';
        if ($id === '') $id = 'v' . date('YmdHis') . substr(bin2hex(random_bytes(3)), 0, 6);

        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $source = isset($_POST['source']) ? trim($_POST['source']) : '';
        $expires = isset($_POST['expires']) ? trim($_POST['expires']) : '';
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
        if (!in_array($status, array('active', 'used', 'expired'), true)) $status = 'active';
        if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            json_out(array('error' => 'expires must be YYYY-MM-DD'), 400);
        }

        $list = read_vouchers($metaFile);
        $idx = -1;
        foreach ($list as $i => $v) {
            if (isset($v['id']) && $v['id'] === $id) { $idx = $i; break; }
        }

        $hasImage = false;
        if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);
            $map = array(
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            );
            if (!isset($map[$mime])) json_out(array('error' => 'image must be jpg/png/webp/gif'), 400);
            if ($_FILES['image']['size'] > 8 * 1024 * 1024) {
                json_out(array('error' => 'image too large (max 8MB)'), 400);
            }
            // remove old extensions
            foreach (array('jpg', 'jpeg', 'png', 'webp', 'gif') as $ext) {
                $old = $imgDir . '/' . $id . '.' . $ext;
                if (file_exists($old)) @unlink($old);
            }
            $dest = $imgDir . '/' . $id . '.' . $map[$mime];
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                json_out(array('error' => 'failed to save image'), 500);
            }
            $hasImage = true;
        }

        $now = gmdate('c');
        $item = array(
            'id' => $id,
            'title' => $title !== '' ? $title : '未命名票券',
            'source' => $source,
            'expires' => $expires,
            'note' => $note,
            'status' => $status,
            'hasImage' => $hasImage || ($idx >= 0 && !empty($list[$idx]['hasImage'])),
            'updatedAt' => $now
        );
        if ($idx >= 0) {
            if (empty($item['hasImage']) && !empty($list[$idx]['hasImage'])) {
                $item['hasImage'] = true;
            }
            if (empty($list[$idx]['createdAt'])) $item['createdAt'] = $now;
            else $item['createdAt'] = $list[$idx]['createdAt'];
            $list[$idx] = $item;
        } else {
            $item['createdAt'] = $now;
            $list[] = $item;
        }

        if (!write_vouchers($metaFile, $list)) {
            json_out(array('error' => 'write failed'), 500);
        }
        json_out(array('ok' => true, 'item' => $item));
    }

    // JSON body
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) json_out(array('error' => 'invalid json'), 400);
    $act = isset($body['action']) ? $body['action'] : '';
    $id = isset($body['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $body['id']) : '';
    if ($id === '') json_out(array('error' => 'id required'), 400);

    $list = read_vouchers($metaFile);

    if ($act === 'delete') {
        $new = array();
        $found = false;
        foreach ($list as $v) {
            if (isset($v['id']) && $v['id'] === $id) {
                $found = true;
                $path = find_image_path($imgDir, $id);
                if ($path) @unlink($path);
                continue;
            }
            $new[] = $v;
        }
        if (!$found) json_out(array('error' => 'not found'), 404);
        if (!write_vouchers($metaFile, $new)) json_out(array('error' => 'write failed'), 500);
        json_out(array('ok' => true));
    }

    if ($act === 'status') {
        $status = isset($body['status']) ? $body['status'] : '';
        if (!in_array($status, array('active', 'used', 'expired'), true)) {
            json_out(array('error' => 'bad status'), 400);
        }
        $found = false;
        foreach ($list as $i => $v) {
            if (isset($v['id']) && $v['id'] === $id) {
                $list[$i]['status'] = $status;
                $list[$i]['updatedAt'] = gmdate('c');
                $found = true;
                break;
            }
        }
        if (!$found) json_out(array('error' => 'not found'), 404);
        if (!write_vouchers($metaFile, $list)) json_out(array('error' => 'write failed'), 500);
        json_out(array('ok' => true));
    }

    json_out(array('error' => 'unknown action'), 400);
}

http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('error' => 'method not allowed'));
