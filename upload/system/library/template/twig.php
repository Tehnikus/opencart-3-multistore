<?php

namespace Template;

use Twig\Extra\Cache\CacheExtension;
use Twig\Extra\Cache\CacheRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

final class Twig {

  private $data = [];

  public function set($key, $value) {
    $this->data[$key] = $value;
  }

  public function render($filename, $code = '') {

    // Get template file
    $file = DIR_TEMPLATE . $filename . '.twig';

    if (!is_file($file)) {
      throw new \Exception('Error: Could not load template ' . $file . '!');
    }

    // FilesystemLoader instead of ArrayLoader
    $paths = array_unique(array_filter([
      rtrim(DIR_TEMPLATE, '/'), // Current template
      rtrim(DIR_SYSTEM . '../catalog/view/theme/default/template', '/'), // fallback default
    ]));

    $loader = new \Twig\Loader\FilesystemLoader($paths);

    //
    // Twig Environment
    //
    $twig = new \Twig\Environment($loader, [
      'autoescape'  => false,
      'debug'       => false,
      'auto_reload' => true,
      'cache'       => DIR_CACHE . 'template/',
    ]);

    
    // Add {% cache %} tag support
		// Static cache folder
    $fragmentCacheDir = DIR_CACHE . 'html/';

    $twig->addExtension(new CacheExtension());

    $twig->addRuntimeLoader(
      new class ($fragmentCacheDir) implements RuntimeLoaderInterface {
        public function __construct(private string $cacheDir) {}

        public function load(string $class): ?object {
          if ($class === CacheRuntime::class) {
            return new CacheRuntime(
							// Tag support
              new TagAwareAdapter(
                new FilesystemAdapter(
                  namespace: 'twig',
                  defaultLifetime: 0,
                  directory: $this->cacheDir
                )
              )
            );
          }
          return null;
        }
      }
    );

    // Render
    try {
      $result = $twig->render($filename . '.twig', $this->data);
      return $result;
    } catch (\Exception $e) {
      trigger_error('Error: Could not load template ' . $filename . '! ' . $e->getMessage());
      exit();
    }
  }
}
