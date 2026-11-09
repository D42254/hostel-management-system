<?php
// includes/validators.php
// Reusable server-side validators

if (!function_exists('v_required')) {

    function v_required($v) {
        return trim((string)$v) !== '';
    }

    function v_email($v) {
        return filter_var((string)$v, FILTER_VALIDATE_EMAIL) !== false;
    }

    function v_phone_ke($v) {
        $v = preg_replace('/\s+/', '', (string)$v);
        return preg_match('/^(?:\+?254|0)(7\d{8}|1\d{8})$/', $v) === 1;
    }

    function v_id($v) {
        return preg_match('/^\d{6,10}$/', (string)$v) === 1;
    }

    function v_password($v) {
        return preg_match('/^(?=.*[A-Za-z])(?=.*\d).{6,}$/', (string)$v) === 1;
    }

    function v_in($v, array $set) {
        return in_array($v, $set, true);
    }

    function v_upload(
        $file,
        array $allowedMimes = ['image/jpeg','image/png','image/jpg','application/pdf'],
        int $maxBytes = 5_242_880
    ) {
        if (empty($file) || !is_array($file) || empty($file['name'])) {
            return "File required";
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return "Upload error (code " . ($file['error'] ?? '?') . ")";
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return "File must be ≤ 5MB";
        }
        $mime = @mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            return "Only JPG, PNG or PDF allowed";
        }
        return null;
    }
}