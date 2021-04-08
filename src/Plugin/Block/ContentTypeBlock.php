<?php

namespace Drupal\lynx\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\lynx\Helper\QueryHelper;
use Drupal\vsite\Plugin\AppManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Provides a block for Lynx content type filter.
 *
 * @Block(
 *   id = "contenttypefilter",
 *   admin_label = @Translation("Lynx Content type filter")
 * )
 */
class ContentTypeBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Entity type manager service.
   *
   * @var object
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lynx.query_helper'),
      $container->get('vsite.app.manager'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Construct a new ContentTypeBlock object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\lynx\Helper\QueryHelper $query_helper
   *   QueryHelper service.
   * @param \Drupal\vsite\Plugin\AppManagerInterface $app_mananger
   *   App manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueryHelper $query_helper, AppManagerInterface $app_mananger, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queryHelper = $query_helper;
    $this->appManager = $app_mananger;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_request = $this->requestStack->getCurrentRequest();
    $build = [];
    $keyword = $current_request->attributes->get('keyword');
    if ($keyword) {
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
      $types = $current_request->query->get('types');
      $publication_types = $this->entityTypeManager->getStorage('bibcite_reference_type')->loadMultiple();

      if ($types) {
        $types = explode(',', $types);
        if (in_array('publications', $types)) {
          $types = array_merge($types, array_keys($publication_types));
        }
        $params['terms']['custom_type'] = $types;
      }

      $query = $this->queryHelper->buildQuery($params);
      $response = $this->queryHelper->search($indices_str, $query);
      if ($response) {
        $build['filter_block_title'] = [
          '#markup' => '<h3>Filter Search Results</h3>',
        ];
        $types = $response['aggregations']['group_by_type']['buckets'];
        $build['filter_form'] = $this->formBuilder->getForm('Drupal\lynx\Form\FilterContentTypeForm', $keyword, $types);
      }
    }
    return $build;
  }

}
