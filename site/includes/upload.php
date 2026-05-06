<?php
/**
 * Shared file upload validation (extension + MIME).
 */

/**
 * Validate an uploaded file field: extension whitelist and finfo MIME check.
 *
 * @param string $field_name $_FILES key
 * @param array $allowed_extensions Lowercase extensions without dot, e.g. ['png', 'jpg']
 * @param array $allowed_mimes Allowed MIME strings, e.g. ['image/png', 'image/jpeg']
 * @return array{tmp_name: string, name: string, ext: string, size: int, mime: string}|null
 */
function validate_upload(string $field_name, array $allowed_extensions, array $allowed_mimes): ?array {
	if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
		error_log("upload: rejected $field_name, MIME=");
		return null;
	}
	$f = $_FILES[$field_name];
	if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		error_log("upload: rejected $field_name, MIME=");
		return null;
	}
	$tmp = $f['tmp_name'] ?? '';
	if ($tmp === '' || !is_uploaded_file($tmp)) {
		error_log("upload: rejected $field_name, MIME=");
		return null;
	}

	$name = (string)($f['name'] ?? '');
	$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	$allowed_ext = array_map('strtolower', $allowed_extensions);

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = ($finfo && $tmp !== '') ? (string)(finfo_file($finfo, $tmp) ?: '') : '';
	if ($finfo) {
		finfo_close($finfo);
	}

	if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
		error_log("upload: rejected $field_name, MIME=$mime");
		return null;
	}

	if ($mime === '' || !in_array($mime, $allowed_mimes, true)) {
		error_log("upload: rejected $field_name, MIME=$mime");
		return null;
	}

	return [
		'tmp_name' => $tmp,
		'name' => $name,
		'ext' => $ext,
		'size' => (int)($f['size'] ?? 0),
		'mime' => $mime,
	];
}
