<?php

namespace ShubhLabh\App;

use finfo;
use PDO;
use RuntimeException;
use ShubhLabh\Config\Config;

/**
 * Secure Media Library Service
 * 
 * Handles multi-layered image upload validation, MIME sniffing,
 * dimension checks, random UUID naming, storage security, and referential integrity.
 */
class MediaService
{
    public const MAX_FILE_SIZE = 5242880; // 5 MB in bytes
    public const MAX_WIDTH     = 4096;
    public const MAX_HEIGHT    = 4096;
    public const MAX_PIXELS    = 16777216; // 16 Megapixels

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /**
     * Process an authenticated file upload
     * 
     * @param array $file $_FILES['image'] item
     * @param string $altText Accessibility alternative text
     * @return array Created media asset record
     */
    public static function upload(array $file, string $altText): array
    {
        // 1. Verify upload status
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Upload error code: ' . ($file['error'] ?? 'unknown')]);
            throw new RuntimeException(self::getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $tmpPath = $file['tmp_name'];
        $origName = basename($file['name']);
        $altText = trim($altText);

        if (empty($altText)) {
            throw new RuntimeException('Alternative text (Alt text) is required for accessibility.');
        }

        // 2. Enforce file size limit (5 MB)
        $fileSize = (int)$file['size'];
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'File size exceeds 5MB limit', 'file_name' => $origName]);
            throw new RuntimeException('File exceeds the maximum permitted size of 5 MB.');
        }

        // 3. Extract and validate file extension
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allAllowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allAllowedExts, true)) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Disallowed extension: ' . $ext, 'file_name' => $origName]);
            throw new RuntimeException("Unsupported file extension '.{$ext}'. Only JPG, PNG, and WebP are allowed.");
        }

        // 4. Server-Side MIME Sniffing via Fileinfo (never trust client-supplied MIME)
        if (!function_exists('finfo_open')) {
            throw new RuntimeException('Server security module (Fileinfo) is required but unavailable.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath);

        if (!array_key_exists($detectedMime, self::ALLOWED_MIME_TYPES)) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Invalid MIME type: ' . $detectedMime, 'file_name' => $origName]);
            throw new RuntimeException("Security Error: File content does not match an approved image format (Detected: {$detectedMime}).");
        }

        // 5. Verify extension matches detected MIME type
        if (!in_array($ext, self::ALLOWED_MIME_TYPES[$detectedMime], true)) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Extension/MIME mismatch', 'ext' => $ext, 'mime' => $detectedMime]);
            throw new RuntimeException('Security Error: File extension does not match true binary content.');
        }

        // 6. Actual Image Decoding and Dimension Verification
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Image decoding failed or corrupt file', 'file_name' => $origName]);
            throw new RuntimeException('Corrupted or malformed image file. Image could not be parsed.');
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];

        // Decompression bomb defense
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT || ($width * $height) > self::MAX_PIXELS) {
            AuditLogger::log('UPLOAD_REJECTED', 'media_assets', null, ['reason' => 'Exceeds maximum dimensions or pixel count', 'width' => $width, 'height' => $height]);
            throw new RuntimeException(sprintf('Image dimensions (%dx%d) exceed the maximum permitted size of 4096x4096 pixels.', $width, $height));
        }

        // 7. Generate Unpredictable Storage Filename (UUID v4 + clean extension)
        $randomHash = bin2hex(random_bytes(16));
        $normalizedExt = ($ext === 'jpeg') ? 'jpg' : $ext;
        $storedName = $randomHash . '.' . $normalizedExt;

        $targetDir = dirname(__DIR__) . '/public/uploads';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $destPath = $targetDir . '/' . $storedName;
        $publicFilePath = '/uploads/' . $storedName;

        // 8. Safe Re-encoding / Metadata Stripping if GD is available
        $saved = false;
        if (function_exists('imagecreatefromjpeg') && in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp'])) {
            $saved = self::stripMetadataAndSave($tmpPath, $destPath, $detectedMime);
        }

        // Fallback to standard move_uploaded_file if GD not available
        if (!$saved) {
            if (!@move_uploaded_file($tmpPath, $destPath)) {
                throw new RuntimeException('Failed to save uploaded file to media storage.');
            }
        }

        // Set safe file permissions (read-only for web server)
        @chmod($destPath, 0644);

        // 9. Register in Database
        $userId = Session::get('admin_user_id');

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                INSERT INTO media_assets (file_name, stored_name, file_path, mime_type, file_size, width, height, alt_text, uploaded_by, created_at)
                VALUES (:orig_name, :stored_name, :path, :mime, :size, :w, :h, :alt, :user_id, NOW())
            ');

            $stmt->execute([
                'orig_name'   => $origName,
                'stored_name' => $storedName,
                'path'        => $publicFilePath,
                'mime'        => $detectedMime,
                'size'        => $fileSize,
                'w'           => $width,
                'h'           => $height,
                'alt'         => $altText,
                'user_id'     => $userId ? (int)$userId : null,
            ]);

            $assetId = (int)$pdo->lastInsertId();

            AuditLogger::log('UPLOAD_SUCCESS', 'media_assets', $assetId, [
                'file_name'   => $origName,
                'stored_name' => $storedName,
                'file_size'   => $fileSize,
                'dimensions'  => "{$width}x{$height}",
            ]);

            return [
                'id'          => $assetId,
                'file_name'   => $origName,
                'stored_name' => $storedName,
                'file_path'   => $publicFilePath,
                'mime_type'   => $detectedMime,
                'file_size'   => $fileSize,
                'width'       => $width,
                'height'      => $height,
                'alt_text'    => $altText,
            ];

        } catch (\Throwable $e) {
            // Roll back physical file if database insert fails
            if (file_exists($destPath)) {
                @unlink($destPath);
            }
            throw new RuntimeException('Database registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Check references across products, articles, and sections
     */
    public static function getReferences(int $mediaId): array
    {
        $references = [];

        try {
            $pdo = Database::getConnection();

            // Products
            $pStmt = $pdo->prepare('SELECT id, name FROM products WHERE main_image_id = :id');
            $pStmt->execute(['id' => $mediaId]);
            $prods = $pStmt->fetchAll();
            foreach ($prods as $p) {
                $references[] = ['type' => 'Product', 'name' => $p['name'], 'url' => "/admin/products.php?action=edit&id={$p['id']}"];
            }

            // Articles
            $aStmt = $pdo->prepare('SELECT id, title FROM knowledge_articles WHERE cover_image_id = :id');
            $aStmt->execute(['id' => $mediaId]);
            $arts = $aStmt->fetchAll();
            foreach ($arts as $a) {
                $references[] = ['type' => 'Knowledge Article', 'name' => $a['title'], 'url' => "/admin/articles.php?action=edit&id={$a['id']}"];
            }

            // Sections
            $sStmt = $pdo->prepare('SELECT id, section_key, title FROM site_sections WHERE media_id = :id');
            $sStmt->execute(['id' => $mediaId]);
            $secs = $sStmt->fetchAll();
            foreach ($secs as $s) {
                $references[] = ['type' => 'Website Section', 'name' => ucfirst($s['section_key']) . ' (' . $s['title'] . ')', 'url' => "/admin/sections.php?edit={$s['section_key']}"];
            }

        } catch (\Throwable) {
            // DB unavailable
        }

        return $references;
    }

    /**
     * Delete an unused media asset
     */
    public static function delete(int $mediaId): bool
    {
        $references = self::getReferences($mediaId);
        if (!empty($references)) {
            $refNames = array_map(fn($r) => "{$r['type']}: {$r['name']}", $references);
            AuditLogger::log('DELETE_BLOCKED_REFERENCED', 'media_assets', $mediaId, ['references' => $refNames]);
            throw new RuntimeException('Cannot delete image: It is currently in use by ' . implode(', ', $refNames) . '. Remove the image from these items first.');
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT stored_name, file_name FROM media_assets WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $mediaId]);
            $asset = $stmt->fetch();

            if (!$asset) {
                throw new RuntimeException('Media asset not found.');
            }

            // Remove physical file
            $filePath = dirname(__DIR__) . '/public/uploads/' . $asset['stored_name'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            // Remove database record
            $delStmt = $pdo->prepare('DELETE FROM media_assets WHERE id = :id');
            $delStmt->execute(['id' => $mediaId]);

            AuditLogger::log('DELETE_SUCCESS', 'media_assets', $mediaId, ['file_name' => $asset['file_name']]);
            return true;

        } catch (\Throwable $e) {
            throw new RuntimeException('Deletion failed: ' . $e->getMessage());
        }
    }

    /**
     * Update alternative text for accessibility
     */
    public static function updateAltText(int $mediaId, string $altText): bool
    {
        $altText = trim($altText);
        if (empty($altText)) {
            throw new RuntimeException('Alternative text cannot be empty.');
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE media_assets SET alt_text = :alt WHERE id = :id');
            $stmt->execute(['alt' => $altText, 'id' => $mediaId]);

            AuditLogger::log('ALT_UPDATE', 'media_assets', $mediaId, ['alt_text' => $altText]);
            return true;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to update Alt text: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve all media assets, optionally filtered by search
     */
    public static function getAll(string $search = ''): array
    {
        try {
            $pdo = Database::getConnection();

            $sql = '
                SELECT m.*, u.username AS uploader_username
                FROM media_assets m
                LEFT JOIN admin_users u ON m.uploaded_by = u.id
            ';

            $params = [];
            if (!empty($search)) {
                $sql .= ' WHERE m.file_name LIKE :search OR m.alt_text LIKE :search';
                $params['search'] = '%' . trim($search) . '%';
            }

            $sql .= ' ORDER BY m.id DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $assets = $stmt->fetchAll();

            foreach ($assets as &$asset) {
                $asset['references'] = self::getReferences((int)$asset['id']);
                $asset['is_used'] = !empty($asset['references']);
            }

            return $assets;

        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Re-encode image to strip EXIF and metadata (GD)
     */
    private static function stripMetadataAndSave(string $sourcePath, string $destPath, string $mime): bool
    {
        try {
            $image = null;
            if ($mime === 'image/jpeg') {
                $image = @imagecreatefromjpeg($sourcePath);
                if ($image) {
                    $result = imagejpeg($image, $destPath, 90);
                    imagedestroy($image);
                    return $result;
                }
            } elseif ($mime === 'image/png') {
                $image = @imagecreatefrompng($sourcePath);
                if ($image) {
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    $result = imagepng($image, $destPath, 6);
                    imagedestroy($image);
                    return $result;
                }
            } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($sourcePath);
                if ($image) {
                    $result = imagewebp($image, $destPath, 85);
                    imagedestroy($image);
                    return $result;
                }
            }
        } catch (\Throwable) {
            return false;
        }
        return false;
    }

    /**
     * Map PHP upload error codes to user-friendly messages
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the server upload size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write upload file to server disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension interrupted the file upload.',
            default               => 'Unknown upload error occurred.',
        };
    }
}
