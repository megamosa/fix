<?php
namespace MagoArab\CdnIntegration\Model;

class ImageProcessor
{
    /**
     * @var \MagoArab\CdnIntegration\Helper\Data
     */
    protected $helper;
    
    /**
     * @param \MagoArab\CdnIntegration\Helper\Data $helper
     */
    public function __construct(
        \MagoArab\CdnIntegration\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }
    
    /**
     * Convert image to WebP format
     *
     * @param string $sourcePath
     * @return string|bool Path to WebP image or false on failure
     */
    public function convertToWebp($sourcePath)
    {
        // Check if source file exists
        if (!file_exists($sourcePath)) {
            $this->helper->log("Source file does not exist: {$sourcePath}", 'error');
            return false;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        
        // Skip if already WebP
        if ($extension === 'webp') {
            return $sourcePath;
        }
        
        // Only convert jpg, jpeg, png
        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            return false;
        }
        
        // Create destination path
        $destinationPath = substr($sourcePath, 0, strrpos($sourcePath, '.')) . '.webp';
        
        // Use GD library to convert image
        try {
            // Check if GD is available
            if (!extension_loaded('gd')) {
                $this->helper->log("GD library is not available, cannot convert to WebP", 'error');
                return false;
            }
            
            // Create image resource based on type
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($sourcePath);
                    // Handle transparency
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    break;
                default:
                    return false;
            }
            
            if (!$image) {
                $this->helper->log("Failed to create image resource from {$sourcePath}", 'error');
                return false;
            }
            
            // Save as WebP (quality: 80)
            $result = imagewebp($image, $destinationPath, 80);
            imagedestroy($image);
            
            if (!$result) {
                $this->helper->log("Failed to save WebP image to {$destinationPath}", 'error');
                return false;
            }
            
            // Log success
            $originalSize = filesize($sourcePath);
            $webpSize = filesize($destinationPath);
            $savings = round(($originalSize - $webpSize) / $originalSize * 100, 2);
            
            $this->helper->log(
                "Converted {$sourcePath} to WebP. Size reduced from " . 
                $this->formatBytes($originalSize) . " to " . 
                $this->formatBytes($webpSize) . " ({$savings}% saved)",
                'info'
            );
            
            return $destinationPath;
        } catch (\Exception $e) {
            $this->helper->log("Failed to convert image to WebP: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Check if file is an image
     *
     * @param string $filePath
     * @return bool
     */
    public function isImageFile($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
    }
    
    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}