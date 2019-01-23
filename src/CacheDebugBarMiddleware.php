<?php

namespace Drupal\cache_debug_bar;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * CacheDebugBarMiddleware middleware.
 */
class CacheDebugBarMiddleware implements HttpKernelInterface {

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs the CacheDebugBarMiddleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $response = $this->httpKernel->handle($request, $type, $catch);

    if ($response->isRedirection()) {
      return $response;
    }

    if ($request->isXmlHttpRequest()) {
      return $response;
    }

    $content = $response->getContent();

    if (stripos($content, '</body>') === FALSE) {
      return $response;
    }

    $start = $_SERVER['REQUEST_TIME_FLOAT'];
    $finish = microtime(TRUE);
    $time = number_format(1000 * ($finish - $start), 1, '.', '') . ' ms';

    $anonymous_cache = $response->headers->get('X-Drupal-Cache') ?: 'NONE';
    $anonymous_cache_class = 'cache-debug-' . strtolower($anonymous_cache);

    $dynamic_cache = $response->headers->get('X-Drupal-Dynamic-Cache') ?: 'NONE';
    $dynamic_cache_class = 'cache-debug-' . strtolower($dynamic_cache);

    // There is no way to render the bar using Renderer::renderPlain() because
    // Request stack is already empty at this moment.
    $bar = <<<EOF
<style>
  .cache-debug-bar {
    font-family: Arial, Helvetica, sans-serif;
    z-index: 10000;
    border: double 3px #777;
    border-bottom-width: 0;
    border-right-width: 0;
    border-top-left-radius: 7px;
    position: fixed;
    bottom: 0;
    right: 0;
    background-color: #f0f5ff;
    padding: 3px;
  }
  .cache-debug-bar div {
    float: left;
    padding: 1px 7px;
  }
  .cache-debug-bar-cache-item,
  .cache-debug-bar-dynamic-cache-item {
    border-left: solid #bbb 1px;
    color: red;
  }
  .cache-debug-miss {
    color: orange;
  }
  .cache-debug-hit {
    color: green;
  }
</style>
<div class="cache-debug-bar">
  <div class="cache-debug-bar-time-item" title="Execution time" class="page-cache-time">$time</div>
  <div class="cache-debug-bar-cache-item $anonymous_cache_class" title="Page cache" class="">$anonymous_cache</div>
  <div class="cache-debug-bar-dynamic-cache-item $dynamic_cache_class" title="Dynamic page cache">$dynamic_cache</div>
</div>
EOF;

    $response->setContent(str_replace('</body>', $bar . '</body>', $content));
    return $response;
  }

}
