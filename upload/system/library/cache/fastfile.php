<?php
namespace Cache;

/**
 * Fast file cache
 *
 * Main ideas:
 * - TTL is stored in file's mtime (touch(file, time()+$expire))
 * - Files are put in separate folders, no pile of thousands files are stored in one folder, so no possible filesystem limitations
 * - File path is determined by key name: parts before last dot => subfolders, last part => file name
 * - Data can be stored in JSON (array serialize) or TXT (raw text). Storage type is defined by data type: is_array($data) => json, else => txt
 * - set() also removes all files of the same key to avoid duplicates 
 * - Atomic write: tmp + rename
 * - Minimal expensive operations in get(): is_file, filemtime, file_get_contents
 */
class FastFile
{
  private $expire;
  private $defaultFormat;
  private $baseDir;
  private $formats; // Allowed file formats (basically only json is treated separately)

  // Directory tree cache
  private static $pathCache = [];
  private static $createdDirs = [];

  /**
   * Initializes the file cache system.
   *
   * @param int|null $expire Default cache lifetime in seconds (3600 if not specified or invalid)
   *
   * The constructor sets up:
   * - The base cache directory (using DIR_CACHE if defined, otherwise system temp).
   * - The default expiration time.
   * - Supported storage formats (JSON for arrays, TXT for raw string data).
   */
  public function __construct($expire = null)
  {
    $this->expire = ($expire > 0 ? $expire : 3600);

    $base = defined('DIR_CACHE') ? rtrim(DIR_CACHE, "/\\") : sys_get_temp_dir();
    $this->baseDir = $base . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;

    // Set default format
    $this->defaultFormat = 'json';
    $this->formats = [$this->defaultFormat];
    // Add second format for raw data
    if ($this->defaultFormat !== 'txt') {
      $this->formats[] = 'txt';
    }
  }

  /**
   * Read cache file
   * Return false if no valid file found 
   * Delete file if it is expired
   *
   * @param string $key encoded file path and name
   * @param string|null $format if not set defaultFormat if used
   * @return mixed|false (array for json, string for html/txt/raw, or false)
   */
  public function get(string $key, ?string $format = null) : bool|array|string {
    $format = $format ?? $this->defaultFormat;

    $path = $this->getCachedPath($key, $format);
    if (is_file($path)) {
      $mtime = @filemtime($path);
      if ($mtime === false || $mtime < time()) {
        @unlink($path);
        return false;
      }
      $data = @file_get_contents($path);
      if ($data === false) {
        @unlink($path);
        return false;
      }
      if ($format === 'json') {
        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          @unlink($path);
          return false;
        }
        return $decoded;
      }
      return $data; // txt/html raw
    }

    return false;
  }

  /**
   * Write cache file
   * @param string $key filename and path
   * @param array|string $data data to be stored in cache file
   * @param int $expire cache TTL in seconds
   * if is_array($data) then $data is written in JSON, else TXT.
   * Delete previous file if present
   */
  public function set(string $key, $data, int $expire = 0) : bool {
    if (is_array($data) && empty($data)) {
      return false;
    }
    $expire = $expire === 0 ? $this->expire : (int) $expire;
    $format = is_array($data) ? 'json' : 'txt';

    // Delete previous files
    $this->delete($key);

    // Write file and prevent read/write races (atomic tmp -> rename)
    $path = $this->getCachedPath($key, $format);
    $dir = dirname($path);

    // Save some time for checking if directory exists
    if (!isset(self::$createdDirs[$dir])) {
      if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
      }
      // Mark $dir as existing only if it exists
      if (is_dir($dir)) {
        self::$createdDirs[$dir] = true;
      }
    }

    // If is_array($data) and $format === 'json'
    if ($format === 'json') {
      $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($payload === false) {
        return false;
      }
      // Othewise
    } else {
      $payload = (string) $data;
    }

    // Temp file
    $tmp = $dir . DIRECTORY_SEPARATOR . uniqid('tmp_', true) . '.tmp';
    $written = @file_put_contents($tmp, $payload, LOCK_EX);
    if ($written === false) {
      @unlink($tmp);
      return false;
    }

    // Write temp to $key cache path and file 
    @touch($tmp, time() + $expire); // First create file to avoid collision when file is already created, but mtime is not set yet, so the file will be treated as expired and deleted again
    if (!@rename($tmp, $path)) {
      @unlink($path);
      if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
      }
    }

    return true;
  }

  /**
   * Delete cache file by key (both json and txt) 
   */
  public function delete(string $key) : void {
    foreach ($this->formats as $format) {
      $path = $this->getCachedPath($key, $format);
      if (is_file($path)) {
        @unlink($path);
      }
    }
  }

  /**
   * Delete all expired files
   * Expensive operation, run by cron or admin.
   */
  public function flush() : void {
    foreach ($this->collectCacheFiles() as $file) {
      $mtime = @filemtime($file);
      if ($mtime === false || $mtime < time()) {
        @unlink($file);
      }
    }
    $this->cleanupEmptyDirs($this->baseDir);
  }

  /**
   * Delete all cache.
   * Expensive operation, run by cron or admin.
   */
  public function clear() : void {
    foreach ($this->collectCacheFiles() as $file) {
      @unlink($file);
    }
    $this->cleanupEmptyDirs($this->baseDir);
    self::$createdDirs = [];
  }

  // Helper methods //

  /** 
   * Get all cache files
   * @return array
   */
  private function collectCacheFiles() : array {
    $files = [];
    $baseDir = $this->baseDir;
    if (!is_dir($baseDir))
      return $files;

    $it = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $fi) {
      if ($fi->isFile()) {
        $name = $fi->getFilename();
        if (strpos($name, 'cache.') === 0) {
          $files[] = $fi->getPathname();
        }
      }
    }
    return $files;
  }

  /** 
   * Return cached file path 
   * Put cached file path to self::$pathCache for faster file read
   * @param string $key	cache name
   * @param string $format file format
   * @return string 
   */
  private function getCachedPath(string $key, string $format) : string {
    $cacheKey = $key . '|' . $format;
    if (isset(self::$pathCache[$cacheKey])) {
      return self::$pathCache[$cacheKey];
    }
    $path = $this->buildFilePath($key, $format);
    self::$pathCache[$cacheKey] = $path;
    return $path;
  }

  /** Make file path bu cache $key: all parts before last dot become subfolders, part after last dot becomes basename */
  private function buildFilePath(string $key, string $format) : string {
    $san = preg_replace('/[^A-Z0-9\._-]/i', '', (string) $key);
    if ($san === '') {
      $san = 'key';
    }

    // Prevent double dots .. thus double slashes //
    $parts = array_filter(explode('.', $san), function ($p) {
      return $p !== '' && $p !== '.' && $p !== '..';
    });
    $basename = array_pop($parts) ?: 'k';

    $dir = rtrim($this->baseDir, DIRECTORY_SEPARATOR);
    if (!empty($parts)) {
      $dir .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }
    $dir .= DIRECTORY_SEPARATOR;

    $filename = 'cache.' . $basename . '.' . $format;
    return $dir . $filename;
  }

  /** 
   * Delete empty directories from dir to baseDir 
   */
  private function cleanupEmptyDirs(string $dir) : void {
    $baseDir = rtrim($this->baseDir, "/\\");
    $dir = rtrim($dir, "/\\");

    if (!is_dir($dir)) {
      return;
    }

    $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $fileinfo) {
      if ($fileinfo->isDir()) {
        $this->cleanupEmptyDirs($fileinfo->getPathname());
      }
    }

    // After recursion — check if directory became empty
    if ($dir !== $baseDir && !(new \FilesystemIterator($dir))->valid()) {
      @rmdir($dir);
    }
  }

  /**
 * Delete all cache files matching key prefix
 * Works by deleting the directory corresponding to the prefix
 * Key parts must be separated by dots: "facets.0.1.1.5"
 */
  public function deleteByPrefix(string $prefix) : void {
    $san  = preg_replace('/[^A-Z0-9\._-]/i', '', $prefix);
    $parts = array_filter(explode('.', $san), fn($p) => $p !== '' && $p !== '.' && $p !== '..');

    $dir = rtrim($this->baseDir, DIRECTORY_SEPARATOR);
    foreach ($parts as $part) {
      $dir .= DIRECTORY_SEPARATOR . $part;
    }

    if (is_dir($dir)) {
      $this->removeDir($dir);
      // Remove all keys with this prefix from pathCache 
      foreach (array_keys(self::$pathCache) as $k) {
        if (str_starts_with($k, $prefix)) {
          unset(self::$pathCache[$k]);
        }
      }
    }
  }
  
  private function removeDir(string $dir) : void {
    $it = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $fi) {
      $fi->isDir() ? @rmdir($fi->getPathname()) : @unlink($fi->getPathname());
    }
    @rmdir($dir);

    // Remove from $createdDirs
    foreach (array_keys(self::$createdDirs) as $k) {
      if (str_starts_with($k, $dir)) {
        unset(self::$createdDirs[$k]);
      }
    }
  }
}
