<?php

namespace Drupal\lynx\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a route subscriber to register a url for serving search pages.
 */
class SearchLynxRoutes implements ContainerInjectionInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates a new SearchLynxRoutes object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function routes() {
    $routes = [];
    $settings = $this->configFactory->get('lynx.settings');
    $path = $settings->get('path');
    $title = $settings->get('title');
    $permission = $settings->get('permission');
    $defaults = [
      '_form' => '\Drupal\lynx\Form\SearchLynxForm',
      '_title' => $title,
    ];
    $requirements = [
      '_permission' => $permission,
    ];
    $routes['lynx.search_form'] = new Route($path, $defaults, $requirements);

    $path .= '/{keyword}';
    $defaults = [
      '_controller' => '\Drupal\lynx\Controller\SearchPage::render',
      '_title' => $title,
    ];
    $requirements = [
      '_permission' => $permission,
    ];
    $routes['lynx.search_page'] = new Route($path, $defaults, $requirements);
    return $routes;
  }

}
