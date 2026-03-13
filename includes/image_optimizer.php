<?php
/**
 * Image Optimizer Class
 * จัดการการ optimize รูปภาพสำหรับระบบภาคสนาม
 *
 * Features:
 * - Resize รูปต้นฉบับเป็น max 1920px
 * - สร้าง thumbnail 300x300px
 * - จัดโครงสร้าง folder: YYYY/MM/DD/original + thumbs
 * - รองรับ JPEG, PNG, GIF, WebP, AVIF
 * - ใช้ GD Library
 */

class ImageOptimizer
{
    // Configuration
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1920;
    private const THUMB_SIZE = 300;
    private const JPEG_QUALITY = 85;
    private const PNG_COMPRESSION = 6;

    private $baseUploadDir;

    public function __construct($baseUploadDir = null)
    {
        if ($baseUploadDir === null) {
            // Default: uploads/job_photos from includes/ directory
            $this->baseUploadDir = __DIR__ . '/../uploads/job_photos';
        } else {
            $this->baseUploadDir = $baseUploadDir;
        }

        // ตรวจสอบ directory
        if (!is_dir($this->baseUploadDir)) {
            throw new Exception("Upload directory does not exist: {$this->baseUploadDir}");
        }
    }

    /**
     * Optimize และบันทึกรูปภาพ
     *
     * @param string $tmpPath - Temporary file path จาก $_FILES
     * @param string $originalName - ชื่อไฟล์เดิม
     * @param int $jobId - Job ID (ไม่ได้ใช้ แต่เก็บไว้สำหรับ future features)
     * @return string|false - Relative path สำหรับเก็บใน database หรือ false ถ้าล้มเหลว
     */
    public function optimizeAndSave($tmpPath, $originalName, $jobId = null)
    {
        try {
            // ตรวจสอบไฟล์
            if (!file_exists($tmpPath)) {
                error_log("ImageOptimizer: File not found - $tmpPath");
                return false;
            }

            // ตรวจสอบ MIME type
            $mimeType = $this->getMimeType($tmpPath);
            if (!$this->isAllowedMimeType($mimeType)) {
                error_log("ImageOptimizer: Invalid MIME type - $mimeType");
                return false;
            }

            // สร้างชื่อไฟล์ใหม่
            $extension = $this->getExtensionFromMime($mimeType, $originalName);
            $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

            // สร้าง folder structure: YYYY/MM/DD/
            $datePath = date('Y/m/d');
            $originalDir = $this->baseUploadDir . '/' . $datePath . '/original';
            $thumbDir = $this->baseUploadDir . '/' . $datePath . '/thumbs';

            // สร้าง directories
            if (!$this->createDirectories($originalDir, $thumbDir)) {
                error_log("ImageOptimizer: Failed to create directories");
                return false;
            }

            // Full paths
            $originalPath = $originalDir . '/' . $filename;
            $thumbPath = $thumbDir . '/' . $filename;

            // Resize original
            if (!$this->resizeImage($tmpPath, $originalPath, self::MAX_WIDTH, self::MAX_HEIGHT, $mimeType)) {
                error_log("ImageOptimizer: Failed to resize original image");
                return false;
            }

            // Create thumbnail
            if (!$this->createThumbnail($originalPath, $thumbPath, self::THUMB_SIZE, $mimeType)) {
                error_log("ImageOptimizer: Failed to create thumbnail");
                // ไม่ return false เพราะ original สำเร็จแล้ว
            }

            // Set permissions
            @chmod($originalPath, 0644);
            @chmod($thumbPath, 0644);

            // Return relative path สำหรับ database
            $relativePath = $datePath . '/original/' . $filename;
            return $relativePath;

        } catch (Exception $e) {
            error_log("ImageOptimizer: Exception - " . $e->getMessage());
            return false;
        }
    }

    /**
     * แก้ไข EXIF Orientation - หมุนรูปจากมือถือให้ถูกต้อง
     * มือถือถ่ายรูปแนวตั้งจะฝัง EXIF orientation tag ไว้
     * GD library ไม่อ่าน tag นี้ จึงต้องหมุนเอง
     *
     * @param GdImage $image - GD image resource
     * @param string $filePath - Path ของไฟล์ต้นฉบับ (สำหรับอ่าน EXIF)
     * @return GdImage - รูปที่หมุนแล้ว (หรือรูปเดิมถ้าไม่ต้องหมุน)
     */
    private function fixOrientation($image, $filePath)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($filePath);
        if ($exif === false || !isset($exif['Orientation'])) {
            return $image;
        }

        switch ($exif['Orientation']) {
            case 2: // Horizontal flip
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3: // 180°
                $image = imagerotate($image, 180, 0);
                break;
            case 4: // Vertical flip
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5: // 90° CW + horizontal flip
                $image = imagerotate($image, -90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6: // 90° CW (มือถือถ่ายแนวตั้ง - พบบ่อยที่สุด)
                $image = imagerotate($image, -90, 0);
                break;
            case 7: // 90° CCW + horizontal flip
                $image = imagerotate($image, 90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8: // 90° CCW
                $image = imagerotate($image, 90, 0);
                break;
        }

        return $image;
    }

    /**
     * Resize รูปภาพ
     *
     * @param string $source - Source file path
     * @param string $destination - Destination file path
     * @param int $maxWidth - Max width
     * @param int $maxHeight - Max height
     * @param string $mimeType - MIME type
     * @return bool
     */
    private function resizeImage($source, $destination, $maxWidth, $maxHeight, $mimeType)
    {
        // โหลดรูปตาม MIME type
        $sourceImage = $this->createImageFromMime($source, $mimeType);
        if (!$sourceImage) {
            return false;
        }

        // แก้ไข EXIF Orientation (หมุนรูปจากมือถือให้ถูกต้อง)
        $sourceImage = $this->fixOrientation($sourceImage, $source);

        // ขนาดเดิม
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // คำนวณขนาดใหม่ (maintain aspect ratio)
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);

        // ถ้ารูปเล็กกว่า max size ไม่ต้อง resize
        if ($ratio >= 1) {
            $newWidth = $sourceWidth;
            $newHeight = $sourceHeight;
        } else {
            $newWidth = (int)($sourceWidth * $ratio);
            $newHeight = (int)($sourceHeight * $ratio);
        }

        // สร้างรูปใหม่
        $destImage = imagecreatetruecolor($newWidth, $newHeight);

        // รักษา transparency สำหรับ PNG และ GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize (high quality)
        imagecopyresampled(
            $destImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        // บันทึกไฟล์
        $result = $this->saveImageByMime($destImage, $destination, $mimeType);

        // ทำความสะอาด
        imagedestroy($sourceImage);
        imagedestroy($destImage);

        return $result;
    }

    /**
     * สร้าง Thumbnail (square, center crop)
     *
     * @param string $source - Source file path
     * @param string $destination - Destination file path
     * @param int $size - Square size (300x300)
     * @param string $mimeType - MIME type
     * @return bool
     */
    private function createThumbnail($source, $destination, $size, $mimeType)
    {
        // โหลดรูป
        $sourceImage = $this->createImageFromMime($source, $mimeType);
        if (!$sourceImage) {
            return false;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // คำนวณ crop (center)
        $sourceAspect = $sourceWidth / $sourceHeight;

        if ($sourceAspect > 1) {
            // Landscape
            $cropHeight = $sourceHeight;
            $cropWidth = $sourceHeight;
            $cropX = ($sourceWidth - $cropWidth) / 2;
            $cropY = 0;
        } else {
            // Portrait or square
            $cropWidth = $sourceWidth;
            $cropHeight = $sourceWidth;
            $cropX = 0;
            $cropY = ($sourceHeight - $cropHeight) / 2;
        }

        // สร้าง thumbnail
        $thumbImage = imagecreatetruecolor($size, $size);

        // รักษา transparency
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $size, $size, $transparent);
        }

        // Crop และ resize
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, $cropX, $cropY,
            $size, $size,
            $cropWidth, $cropHeight
        );

        // บันทึก
        $result = $this->saveImageByMime($thumbImage, $destination, $mimeType);

        // ทำความสะอาด
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return $result;
    }

    /**
     * แปลง relative path เป็น paths สำหรับ original + thumbnail
     *
     * @param string $relativePath - Path จาก database (e.g., "2025/01/12/original/filename.jpg")
     * @return array - ['original' => '...', 'thumb' => '...']
     */
    public static function getImagePaths($relativePath)
    {
        // ถ้าเป็น path แบบเก่า (ไม่มี folder structure)
        if (strpos($relativePath, '/') === false) {
            // Old format: just filename
            return [
                'original' => $relativePath,
                'thumb' => $relativePath, // ใช้รูปเดิมแทน
                'exists_thumb' => false
            ];
        }

        // New format: YYYY/MM/DD/original/filename.jpg
        $originalPath = $relativePath;

        // แปลงเป็น thumbnail path
        $thumbPath = str_replace('/original/', '/thumbs/', $relativePath);

        return [
            'original' => $originalPath,
            'thumb' => $thumbPath,
            'exists_thumb' => true
        ];
    }

    /**
     * ตรวจสอบและสร้าง directories
     */
    private function createDirectories($originalDir, $thumbDir)
    {
        if (!is_dir($originalDir)) {
            if (!@mkdir($originalDir, 0777, true)) {
                error_log("ImageOptimizer: Cannot create directory $originalDir - Permission denied");
                return false;
            }
            @chmod($originalDir, 0777);
        }

        if (!is_dir($thumbDir)) {
            if (!@mkdir($thumbDir, 0777, true)) {
                error_log("ImageOptimizer: Cannot create directory $thumbDir - Permission denied");
                return false;
            }
            @chmod($thumbDir, 0777);
        }

        return true;
    }

    /**
     * ตรวจสอบ MIME type
     */
    private function getMimeType($filePath)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mimeType;
    }

    /**
     * ตรวจสอบว่า MIME type ถูกต้องหรือไม่
     */
    private function isAllowedMimeType($mimeType)
    {
        $allowed = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif'
        ];

        return in_array($mimeType, $allowed);
    }

    /**
     * ดึง extension จาก MIME type
     */
    private function getExtensionFromMime($mimeType, $originalName)
    {
        // ลองดึงจากชื่อไฟล์เดิมก่อน
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // ตรวจสอบว่า extension ตรงกับ MIME type หรือไม่
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif'
        ];

        if (isset($mimeMap[$mimeType])) {
            return $mimeMap[$mimeType];
        }

        return $ext ?: 'jpg';
    }

    /**
     * สร้าง image resource จาก MIME type
     */
    private function createImageFromMime($filePath, $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($filePath);
            case 'image/png':
                return @imagecreatefrompng($filePath);
            case 'image/gif':
                return @imagecreatefromgif($filePath);
            case 'image/webp':
                return @imagecreatefromwebp($filePath);
            case 'image/avif':
                if (function_exists('imagecreatefromavif')) {
                    return @imagecreatefromavif($filePath);
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * บันทึก image ตาม MIME type
     */
    private function saveImageByMime($image, $filePath, $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagejpeg($image, $filePath, self::JPEG_QUALITY);
            case 'image/png':
                return @imagepng($image, $filePath, self::PNG_COMPRESSION);
            case 'image/gif':
                return @imagegif($image, $filePath);
            case 'image/webp':
                return @imagewebp($image, $filePath, self::JPEG_QUALITY);
            case 'image/avif':
                if (function_exists('imageavif')) {
                    return @imageavif($image, $filePath, self::JPEG_QUALITY);
                }
                // Fallback to JPEG if AVIF not supported
                return @imagejpeg($image, $filePath, self::JPEG_QUALITY);
            default:
                return false;
        }
    }
}
