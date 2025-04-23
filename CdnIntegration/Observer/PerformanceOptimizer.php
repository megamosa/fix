<?php
/**
 * MagoArab CdnIntegration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @category   MagoArab
 * @package    MagoArab_CdnIntegration
 * @copyright  Copyright (c) 2025 MagoArab (https://www.mago-ar.com/)
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
namespace MagoArab\CdnIntegration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use MagoArab\CdnIntegration\Helper\Data as Helper;

class PerformanceOptimizer implements ObserverInterface
{
    /**
     * @var Helper
     */
    protected $helper;
    
    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    ) {
        $this->helper = $helper;
    }
    
    /**
     * Add performance optimizations to HTML output
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        // Skip if optimizations are not enabled
        if (!$this->helper->isPerformanceOptimizationEnabled()) {
            return;
        }
        
        $response = $observer->getEvent()->getResponse();
        if (!$response) {
            return;
        }
        
        $html = $response->getBody();
        if (empty($html)) {
            return;
        }
        
        // Apply various optimizations based on settings
        if ($this->helper->isLazyLoadImagesEnabled()) {
            $html = $this->addLazyLoading($html);
        }
        
        if ($this->helper->isDeferJsEnabled()) {
            $html = $this->addJsDefer($html);
        }
        
        if ($this->helper->isHtmlMinifyEnabled()) {
            $html = $this->minifyHtml($html);
        }
        
        // Update response with optimized HTML
        $response->setBody($html);
    }
    
    /**
     * Add lazy loading to image tags
     *
     * @param string $html
     * @return string
     */
    private function addLazyLoading($html)
    {
        // Don't process if already processed or no img tags
        if (strpos($html, '<img') === false) {
            return $html;
        }
        
        // Replace img tags with lazy loading attribute if not already present
        return preg_replace_callback(
            '/<img\s[^>]*(?<!loading=)[^>]*>/i',
            function($matches) {
                $tag = $matches[0];
                
                // Skip if already has loading attribute
                if (preg_match('/\sloading=[\'"](lazy|eager|auto)[\'"]/', $tag)) {
                    return $tag;
                }
                
                // Add loading="lazy" before closing >
                return substr_replace($tag, ' loading="lazy"', strrpos($tag, '>'), 0);
            },
            $html
        );
    }
    
    /**
     * Add defer attribute to non-critical JS files
     *
     * @param string $html
     * @return string
     */
    private function addJsDefer($html)
    {
        // Don't process if already processed or no script tags
        if (strpos($html, '<script') === false) {
            return $html;
        }
        
        // Critical scripts that should not be deferred
        $criticalScripts = [
            'requirejs',
            'require.js',
            'jquery.js',
            'jquery.min.js',
            'knockout.js',
            'mage/requirejs/mixins.js',
            'mage/polyfill.js',
            'mage/bootstrap.js'
        ];
        
        // Add defer to non-critical scripts
        return preg_replace_callback(
            '/<script\s+([^>]*?)src=[\'"](.*?)[\'"]([^>]*?)><\/script>/i',
            function($matches) use ($criticalScripts) {
                $before = $matches[1];
                $src = $matches[2];
                $after = $matches[3];
                
                // Skip if already has async or defer attribute
                if (strpos($before . $after, 'defer') !== false || 
                    strpos($before . $after, 'async') !== false) {
                    return $matches[0];
                }
                
                // Skip critical scripts
                foreach ($criticalScripts as $criticalScript) {
                    if (strpos($src, $criticalScript) !== false) {
                        return $matches[0];
                    }
                }
                
                // Add defer attribute
                return "<script {$before}src=\"{$src}\"{$after} defer></script>";
            },
            $html
        );
    }
    /**
 * Update image tags to use WebP with fallback
 *
 * @param string $html
 * @return string
 */
private function enhanceImagesWithWebp($html)
{
    return preg_replace_callback(
        '/<img([^>]*)src=[\'"]((?:[^\'"]+\.(jpg|jpeg|png))(?:\?[^\'"]*)?)[\'"]([^>]*)>/i',
        function($matches) {
            $beforeSrc = $matches[1];
            $imgSrc = $matches[2];
            $extension = $matches[3];
            $afterSrc = $matches[4];
            
            // Skip SVGs and GIFs
            if (in_array($extension, ['svg', 'gif'])) {
                return $matches[0];
            }
            
            // Create WebP URL
            $webpSrc = substr($imgSrc, 0, strrpos($imgSrc, '.')) . '.webp';
            
            // Check if WebP exists on CDN
            if ($this->webpExistsOnCdn($webpSrc)) {
                // Create picture element with WebP and fallback
                return '<picture>' .
                    '<source srcset="' . $webpSrc . '" type="image/webp">' .
                    '<img' . $beforeSrc . 'src="' . $imgSrc . '"' . $afterSrc . '>' .
                    '</picture>';
            }
            
            // Return original if WebP doesn't exist
            return $matches[0];
        },
        $html
    );
}
    /**
     * Simple HTML minification
     *
     * @param string $html
     * @return string
     */
    private function minifyHtml($html)
    {
        // Skip if empty
        if (empty($html)) {
            return $html;
        }
        
        // Simple minification rules
        $replace = [
            '/\>[^\S ]+/s'     => '>',     // Remove whitespace after tags
            '/[^\S ]+\</s'     => '<',     // Remove whitespace before tags
            '/([\t ])+/s'      => ' ',     // Replace multiple spaces with single space
            '/^([\t ])+/m'     => '',      // Remove line lead whitespace
            '/([\t ])+$/m'     => '',      // Remove line end whitespace
            '~//[a-zA-Z0-9 ]+$~m' => '',   // Remove simple comments
            '/[\r\n]/'         => '',      // Remove newlines & returns
            '/\s+/'            => ' '      // Replace runs of whitespace with single space
        ];
        
        // Apply regular expression replacements
        $minified = preg_replace(array_keys($replace), array_values($replace), $html);
        
        // If something went wrong, return the original
        return ($minified === null) ? $html : $minified;
    }
}