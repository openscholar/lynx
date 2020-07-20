<?php

namespace Drupal\lynx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\lynx\Helper\QueryHelper;
use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('lynx.query_helper')
    );
  }

  /**
   * Construct a new SearchPage object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\lynx\Helper\QueryHelper $query_helper
   *   QueryHelper service.
   */
  public function __construct(FormBuilderInterface $form_builder, QueryHelper $query_helper) {
    $this->formBuilder = $form_builder;
    $this->queryHelper = $query_helper;
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
    // Build Search Form.
    $build['search_form'] = $this->formBuilder->getForm('Drupal\lynx\Form\SearchLynxForm');
    $build['search_form']['keyword']['#value'] = $keyword;

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
    $query = $this->queryHelper->buildQuery($params);
    $response = $this->queryHelper->search($indices_str, $query);

    $result = [];
    $total = $response['hits']['total']['value'];
    foreach ($response['hits']['hits'] as $row) {
      $base_url = $indices[$row['_index']]['mappings']['_meta']['base_url'];
      $raw_url = explode(':', $row['_id'])[1];
      $url_params = explode('/', $raw_url);
      $url = Url::fromRoute('entity.' . $url_params[0] . '.canonical', [$url_params[0] => $url_params[1]])->toString();
      $vsite_url = Url::fromRoute('entity.group.canonical', ['group' => current($row['_source']['custom_search_group'])])->toString();
      $result[] = [
        'title' => current($row['_source']['custom_title']),
        'body' => current($row['_source']['body']),
        'url' => $base_url . $url,
        'vsite_name' => current($row['_source']['vsite_name']),
        'vsite_logo' => current($row['_source']['vsite_logo']),
        'vsite_url' => $base_url . $vsite_url,
      ];
    }

    pager_default_initialize($total, $num_per_page);
    $build['result'] = $this->createRenderArray($result);
    $build['result']['pager'] = ['#type' => 'pager'];

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
          ]
        ],
        'title' => [
          '#prefix' => '<h2 class="node--title">',
          '#type' => 'link',
          '#url' => Url::fromUri($row['url'], ['absolute' => TRUE]),
          '#title' => $row['title'],
          '#suffix' => '</h2>',
        ],
        'body' => [
          '#markup' => '<div>' . Unicode::truncate($row['body'], 128, TRUE, TRUE) . '</div>',
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
          '#weight' => -1
        ];
        $items[$id]['vsite_logo'] = [
          '#type' => 'link',
          '#url' => Url::fromUri($row['vsite_url'], ['absolute' => TRUE]),
          '#title' => $image,
          '#weight' => -2
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
