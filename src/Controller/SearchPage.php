<?php

namespace Drupal\lynx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\lynx\Helper\QueryHelper;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;
use Drupal\vsite\Plugin\AppManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the Lynx search page.
 */
class SearchPage extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Search Query helper.
   *
   * @var \Drupal\lynx\Helper\QueryHelper
   */
  protected $queryHelper;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManagerInterface
   */
  protected $appManager;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('lynx.query_helper'),
      $container->get('vsite.app.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Construct a new SearchPage object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\lynx\Helper\QueryHelper $query_helper
   *   QueryHelper service.
   * @param \Drupal\vsite\Plugin\AppManagerInterface $app_mananger
   *   App manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(FormBuilderInterface $form_builder, QueryHelper $query_helper, AppManagerInterface $app_mananger, RequestStack $request_stack) {
    $this->formBuilder = $form_builder;
    $this->queryHelper = $query_helper;
    $this->appManager = $app_mananger;
    $this->requestStack = $request_stack;
  }

  /**
   * Render Search page.
   *
   * @param string $keyword
   *   Search keyword.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function render($keyword) {

    $build['search_listing'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'lynx-listing-page',
      ],
    ];

    // Build Search Form.
    $form = $this->formBuilder->getForm('Drupal\lynx\Form\SearchLynxForm');
    unset($form['title_text']);
    $build['search_listing']['search_form'] = $form;
    $build['search_listing']['search_form']['text']['#markup'] = t('<div class="lynx-search-text">Search</div>');
    $build['search_listing']['search_form']['keyword']['#value'] = $keyword;

    // Build Search Result.
    $page = pager_find_page();
    $num_per_page = 9;
    $from = $page * $num_per_page;
    $indices = $this->queryHelper->getAllowedIndices();
    $indices_str = implode(',', array_keys($indices));
    $params = [
      'keyword' => $keyword,
      'terms' => [
        'vsite_visibility' => 'public',
      ],
      'from' => $from,
      'size' => $num_per_page,
    ];

    // Content type filter.
    $current_request = $this->requestStack->getCurrentRequest();
    $types = $current_request->query->get('types');
    $publication_types = $this->entityTypeManager()->getStorage('bibcite_reference_type')->loadMultiple();

    if ($types) {
      $types = explode(',', $types);
      if (in_array('publications', $types)) {
        $types = array_merge($types, array_keys($publication_types));
      }
      $params['terms']['custom_type'] = $types;
    }

    $query = $this->queryHelper->buildQuery($params);
    $response = $this->queryHelper->search($indices_str, $query);
    $result = [];
    $total = $response['hits']['total']['value'];
    $bundles = [];
    if ($total > 0) {
      $apps = $this->appManager->getDefinitions();
      foreach ($apps as $app) {
        if (isset($app['bundle'])) {
          foreach ($app['bundle'] as $bundle) {
            $bundles[$bundle] = $app['title'];
          }
        }
      }
    }
    foreach ($response['hits']['hits'] as $row) {
      $base_url = $indices[$row['_index']]['mappings']['_meta']['base_url'];
      $raw_url = explode(':', $row['_id'])[1];
      $url_params = explode('/', $raw_url);
      $url = Url::fromRoute('entity.' . $url_params[0] . '.canonical', [$url_params[0] => $url_params[1]])->toString();
      $vsite_url = '/group/' . current($row['_source']['custom_search_group']);
      $content_type = current($row['_source']['custom_type']);
      if (array_key_exists($content_type, $bundles)) {
        $name = $bundles[$content_type];
      }
      elseif (array_key_exists($content_type, $publication_types)) {
        $name = 'Publications';
      }

      $result[] = [
        'title' => current($row['_source']['custom_title']),
        'body' => isset($row['_source']['body']) ? current($row['_source']['body']) : '',
        'url' => $base_url . $url,
        'vsite_name' => current($row['_source']['vsite_name']),
        'vsite_logo' => current($row['_source']['vsite_logo']),
        'vsite_url' => $base_url . $vsite_url,
        'vsite_description' => current($row['_source']['vsite_description']),
        'content_type' => $name,
      ];
    }

    pager_default_initialize($total, $num_per_page);
    $build['search_listing']['result'] = $this->createRenderArray($result);
    $build['search_listing']['result']['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Build search result list.
   *
   * @param array $result
   *   Search result.
   *
   * @return array
   *   Render array.
   */
  protected function createRenderArray(array $result) {
    $items = [];
    foreach ($result as $id => $row) {

      $items[$id] = [
        'vsite_name' => [
          '#type' => 'link',
          '#url' => Url::fromUri($row['vsite_url'], ['absolute' => TRUE]),
          '#title' => $row['vsite_name'],
          '#attributes' => [
            'class' => 'lynx-title',
          ],
        ],
        'vsite_description' => [
          '#markup' => '<div class="meta-description">' . $row['vsite_description'] . '</div>',
        ],
        'content_type' => [
          '#markup' => '<div class="content-type">' . $row['content_type'] . '</div>',
        ],
        'title' => [
          '#prefix' => '<h2 class="node--title">',
          '#type' => 'link',
          '#url' => Url::fromUri($row['url'], ['absolute' => TRUE]),
          '#title' => $row['title'],
          '#suffix' => '</h2>',
        ],
        'body' => [
          '#markup' => '<div class="body">' . Unicode::truncate($row['body'], 128, TRUE, TRUE) . '</div>',
        ],
        'see_more' => [
          '#type' => 'link',
          '#url' => Url::fromUri($row['url'], ['absolute' => TRUE]),
          '#title' => $this->t('Read more'),
        ],
      ];
      if ($row['vsite_logo']) {
        $image = [
          '#theme' => 'image',
          '#uri' => $row['vsite_logo'],
          '#alt' => $row['vsite_name'],
          '#weight' => -1,
        ];
        $items[$id]['vsite_logo'] = [
          '#type' => 'link',
          '#url' => Url::fromUri($row['vsite_url'], ['absolute' => TRUE]),
          '#title' => $image,
          '#weight' => -2,
        ];
      }
    }

    $build['search_results'] = [
      '#title' => $this->t('Search'),
      'content' => [
        '#theme' => 'item_list__search_results',
        '#items' => $items,
        '#empty' => [
          '#markup' => '<h3>' . $this->t('Your search yielded no results.') . '</h3>',
        ],
        '#type' => 'remote',
        '#attributes' => [
          'class' => ['lynx-search-listing'],
        ],
      ],
    ];
    return $build;
  }

}
