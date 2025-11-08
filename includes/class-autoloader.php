<?php
/**
 * Autoloader for WP State Machine Plugin
 *
 * @package     WP_State_Machine
 * @subpackage  Includes
 * @version     1.0.0
 * @author      arisciwek
 *
 * Path: /wp-state-machine/includes/class-autoloader.php
 *
 * Description: Autoloader untuk WP State Machine plugin.
 *              Menangani autoloading dengan:
 *              - Validasi class name
 *              - Namespace mapping
 *              - Cache management
 *              - Error handling dan debug
 *              - File existence checking
 *
 * Dependencies:
 * - WordPress core
 * - WP_DEBUG untuk logging
 *
 * Usage:
 * require_once WP_STATE_MACHINE_PATH . 'includes/class-autoloader.php';
 * $autoloader = new WP_State_Machine_Autoloader();
 * $autoloader->register();
 *
 * Changelog:
 * 1.0.0 - 2025-11-07
 * - Initial creation
 * - Added namespace mapping
 * - Added cache management
 * - Added debug logging
 * - Added class validation
 * - Follow wp-agency pattern
 */

class WP_State_Machine_Autoloader {
    private $prefix;
    private $baseDir;
    private $mappings = [];
    private $loadedClasses = [];
    private $debugMode;

    public function __construct() {
        $this->prefix = 'WPStateMachine\\';
        $this->baseDir = rtrim(WP_STATE_MACHINE_PATH, '/\\') . '/';
        $this->debugMode = defined('WP_DEBUG') && WP_DEBUG;

        // Add default mapping
        $this->addMapping('', 'src/');
    }

    /**
     * Add custom namespace to directory mapping
     *
     * @param string $namespace Namespace prefix
     * @param string $directory Directory path
     * @return void
     */
    public function addMapping($namespace, $directory) {
        $namespace = trim($namespace, '\\');
        $this->mappings[$namespace] = rtrim($directory, '/\\') . '/';
    }

    /**
     * Register the autoloader
     *
     * @return void
     */
    public function register() {
        // Load composer autoload if available
        $composer_autoload = $this->baseDir . 'vendor/autoload.php';
        if (file_exists($composer_autoload)) {
            require_once $composer_autoload;
        }

        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Unregister the autoloader
     *
     * @return void
     */
    public function unregister() {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Main class loading method
     *
     * @param string $class Fully qualified class name
     * @return bool True if class loaded successfully
     */
    public function loadClass($class) {
        try {
            $this->log("=== AUTOLOADER LOADING: $class ===");

            // Check if class already loaded
            if (isset($this->loadedClasses[$class])) {
                return true;
            }

            // Validate class name format
            if (!$this->isValidClassName($class)) {
                $this->log("Invalid class name format: $class");
                return false;
            }

            // Check if class uses our namespace
            if (strpos($class, $this->prefix) !== 0) {
                $this->log("Class doesn't use our prefix: $class (prefix: {$this->prefix})");
                return false;
            }

            // Get the relative class name
            $relativeClass = substr($class, strlen($this->prefix));
            $this->log("Relative class: $relativeClass");

            // Find matching namespace mapping
            $mappedPath = $this->findMappedPath($relativeClass);
            if (!$mappedPath) {
                $this->log("No mapping found for class: $class");
                return false;
            }

            // Build the full file path
            $file = $this->baseDir . $mappedPath;
            $this->log("Base directory: {$this->baseDir}");
            $this->log("Full file path: $file");

            // Check if file exists
            if (!$this->validateFile($file)) {
                $this->log("File not found or not readable: $file");
                return false;
            }

            // Load the file
            require_once $file;

            // Verify class was actually loaded
            if (!$this->verifyClassLoaded($class)) {
                $this->log("Class $class not found in file $file");
                return false;
            }

            // Mark class as loaded
            $this->loadedClasses[$class] = true;
            $this->log("Successfully loaded class: $class");

            return true;

        } catch (\Exception $e) {
            $this->log("Error loading class $class: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate class name format
     *
     * @param string $class Class name to validate
     * @return bool True if valid
     */
    private function isValidClassName($class) {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/', $class);
    }

    /**
     * Find mapped path for class
     *
     * @param string $relativeClass Relative class name
     * @return string|false Mapped path or false
     */
    private function findMappedPath($relativeClass) {
        $this->log("Finding mapped path for: $relativeClass");
        $this->log("Available mappings: " . print_r($this->mappings, true));

        // Try each mapping from most specific to least
        foreach ($this->mappings as $namespace => $directory) {
            if (empty($namespace) || strpos($relativeClass, $namespace) === 0) {
                $classPath = empty($namespace) ? $relativeClass : substr($relativeClass, strlen($namespace));
                $mappedPath = $directory . str_replace('\\', '/', $classPath) . '.php';
                $this->log("Mapped path result: $mappedPath");
                return $mappedPath;
            }
        }
        return false;
    }

    /**
     * Validate file exists and is readable
     *
     * @param string $file File path to validate
     * @return bool True if valid
     */
    private function validateFile($file) {
        // Clear stat cache to avoid stale file existence checks
        clearstatcache(true, $file);

        if (!file_exists($file)) {
            $this->log("File does not exist: $file");
            // Double-check with realpath
            $realFile = realpath($file);
            $this->log("Realpath result: " . ($realFile ? $realFile : 'FALSE'));
            return false;
        }

        if (!is_readable($file)) {
            $this->log("File not readable: $file");
            return false;
        }

        return true;
    }

    /**
     * Verify class was actually loaded
     *
     * @param string $class Class name to verify
     * @return bool True if class exists
     */
    private function verifyClassLoaded($class) {
        return class_exists($class, false) ||
               interface_exists($class, false) ||
               trait_exists($class, false);
    }

    /**
     * Debug logging
     *
     * @param string $message Log message
     * @return void
     */
    private function log($message) {
        // Disabled verbose autoloader logging to prevent AJAX response pollution
        // if ($this->debugMode) {
        //     error_log("[WP_State_Machine_Autoloader] $message");
        // }
    }

    /**
     * Get list of loaded classes
     *
     * @return array Array of loaded class names
     */
    public function getLoadedClasses() {
        return array_keys($this->loadedClasses);
    }

    /**
     * Clear loaded classes cache
     *
     * @return void
     */
    public function clearCache() {
        $this->loadedClasses = [];
    }
}
