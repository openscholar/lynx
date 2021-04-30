<?php

namespace Drupal\lynx\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Class PathProcessorLynx.
 */
class PathProcessorLynx implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The lynx path.
   *
   * @var string
   */
  protected $lynxPath;

  /**
   * The lynx domain.
   *
   * @var string
   */
  protected $lynxDomain;

  /**
   * PathProcessorLynx constructor.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config;
    $lynx_settings = $this->config->get('lynx.settings');
    $domain_settings = $this->config->get('lynx.domain.settings');
    $this->lynxPath = $lynx_settings->get('path');
    $this->lynxDomain = $domain_settings->get('lynx_domain');
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if ($this->lynxDomain && $request->getHttpHost() == $this->lynxDomain && strpos($path, $this->lynxPath) !== 0) {
      return $this->lynxPath;
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!$request && !array_key_exists('route', $options)) {
      return $path;
    }
    if ($this->lynxDomain && $request->getHttpHost() == $this->lynxDomain && strcmp($path, $this->lynxPath) == 0) {
      $path = '/';
    }
    return $path;
  }

}
