<?php

namespace Drupal\lynx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vsite\Plugin\AppManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;

/**
 * Implements filter content type form.
 */
class FilterContentTypeForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManagerInterface
   */
  protected $appManager;

  /**
   * Entity type manager service.
   *
   * @var object
   */
  protected $entityTypeManager;

  /**
   * Create an instance of LynxIndicesForm.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\vsite\Plugin\AppManagerInterface $app_mananger
   *   App manager.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, AppManagerInterface $app_mananger) {
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->appManager = $app_mananger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('vsite.app.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'filter_content_type_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $keyword = NULL, $types = NULL) {
    $form = [];
    $options = [];
    $apps = $this->appManager->getDefinitions();
    $publication_types = $this->entityTypeManager->getStorage('bibcite_reference_type')->loadMultiple();
    $publication_types = array_keys($publication_types);
    // kint($publication_types);
    $bundles = [];
    foreach ($apps as $app) {
      if (isset($app['bundle'])) {
        foreach ($app['bundle'] as $bundle) {
          $bundles[$bundle] = $app['title'];
        }
      }
    }

    $pub_count = 0;
    foreach ($types as $type) {
      if (in_array($type['key'], $publication_types)) {
        $pub_count += $type['doc_count'];
      }
      else {
        $options[$type['key']] = $bundles[$type['key']] . ' (' . $type['doc_count'] . ')';
      }
    }
    if ($pub_count > 0) {
      $options['publications'] = 'Publications (' . $pub_count . ')';
    }

    $current_request = $this->requestStack->getCurrentRequest();
    $types = $current_request->query->get('types');
    $types = explode(',', $types);

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $types,
      '#title' => 'Content Type',
      '#attributes'    => [
        'onChange' => 'this.form.submit();',
      ],
    ];

    $form['clear_all_filters'] = [
      '#type' => 'link',
      '#title' => $this->t('Clear all Filters'),
      '#url' => Url::fromRoute('lynx.search_page', ['keyword' => $keyword]),
    ];


    $form['keyword'] = [
      '#type' => 'hidden',
      '#value' => $keyword,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Apply',
      '#attributes' => [
        'style' => ['display: none;'],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keyword = $form_state->getValue('keyword');
    $types = $form_state->getValue('content_types');
    $types = array_filter($types);
    if ($keyword) {
      $options = [
        'query' => ['types' => implode(',', $types)],
      ];
      $form_state->setRedirect('lynx.search_page', ['keyword' => $keyword], $options);
    }
  }

}
