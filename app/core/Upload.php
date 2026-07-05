<?php
namespace App\Core;

/** Validated image upload handling. Stores files under public/uploads/<subdir>. */
class Upload
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED   = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * Validate and store an uploaded image.
     *
     * @param array  $file   an entry from $_FILES
     * @param string $subdir subdirectory under public/uploads (e.g. 'students')
     * @return array{ok:bool, path?:string, error?:string} path is relative to uploads/
     */
    public static function image(array $file, string $subdir): array
    {
        $check = self::validate($file['tmp_name'] ?? '', $file['name'] ?? '', (int) ($file['size'] ?? 0), (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
        if (!$check['ok']) {
            return $check;
        }

        $dir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $check['ext'];
        $dest = $dir . '/' . $name;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $dest)
            : @rename($file['tmp_name'], $dest); // test/CLI fallback

        if (!$moved) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }
        return ['ok' => true, 'path' => $subdir . '/' . $name];
    }

    /**
     * Validate an upload without moving it (separately testable).
     *
     * @return array{ok:bool, ext?:string, error?:string}
     */
    public static function validate(string $tmpPath, string $origName, int $size, int $error): array
    {
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was uploaded.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed (error code ' . $error . ').'];
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and 2 MB.'];
        }
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
        }
        // Confirm the bytes really are an image of the claimed type.
        $info = @getimagesize($tmpPath);
        if ($info === false || !in_array($info['mime'], self::ALLOWED, true)) {
            return ['ok' => false, 'error' => 'The file is not a valid image.'];
        }
        return ['ok' => true, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
    }

    /** Delete a previously stored upload (path relative to uploads/). */
    public static function remove(?string $path): void
    {
        if (!$path) {
            return;
        }
        $full = UPLOAD_PATH . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
