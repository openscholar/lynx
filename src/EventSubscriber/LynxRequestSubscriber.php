<?php

namespace Drupal\lynx\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class LynxRequestSubscriber.
 */
class LynxRequestSubscriber implements EventSubscriberInterface {

  /**
   * Module specific configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * LynxRequestSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   ConfigFactoryInterface instance.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config;
    $this->lynxPath = $this->config->get('lynx.settings')->get('path');
    $this->lynxDomain = $this->config->get('lynx.domain.settings')->get('lynx_domain');
  }

  /**
   * Performs an internal redirect to lynx search page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   */
  public function onKernelRequestLynx(RequestEvent $event) {
    $request = $event->getRequest();
    $host = $request->getHttpHost();
    $uri = $request->getPathInfo();

    // If we are requesting a "files" path don't forward requests to /lynx
    $public_base_url_path = parse_url(Settings::get('file_public_base_url', ''), PHP_URL_PATH);
    if ($public_base_url_path && strpos($uri, $public_base_url_path) === 0) {
      return;
    }

    if ($this->lynxDomain && $host == $this->lynxDomain && strpos($uri, $this->lynxPath) !== 0) {
      $newrequest = $request->duplicate();
      $newrequest->server->set('REQUEST_URI', $this->lynxPath);
      $response = $event->getKernel()->handle($newrequest, HttpKernelInterface::SUB_REQUEST);
      if ($response) {
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // We need highest priority for this.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestLynx', 1000];
    return $events;
  }

}
