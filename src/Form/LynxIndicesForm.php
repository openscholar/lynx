<?php

namespace Drupal\lynx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Implements search lynx admin form.
 */
class LynxIndicesForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  protected $clientManager;

  /**
   * The cluster manager service.
   *
   * @var \Drupal\elasticsearch_connector\ClusterManager
   */
  protected $clusterManager;

  /**
   * Client object.
   *
   * @var \nodespark\DESConnector\ClientInterface
   */
  protected $client;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Create an instance of LynxIndicesForm.
   *
   * @param \Drupal\elasticsearch_connector\ClusterManager $cluster_manager
   *   The cluster manager service.
   * @param \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface $client_manager
   *   The client manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ClusterManager $cluster_manager, ClientManagerInterface $client_manager, MessengerInterface $messenger) {
    $this->clientManager = $client_manager;
    $this->clusterManager = $cluster_manager;
    $clusters = $cluster_manager->loadAllClusters(FALSE);
    $this->client = $this->clientManager->getClientForCluster(current($clusters));
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('elasticsearch_connector.cluster_manager'),
      $container->get('elasticsearch_connector.client_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_lynx_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['indices_table'] = [
      '#type' => 'table',
      '#title' => '',
      '#header' => ['Site Name', 'Index Name', 'Is Private'],
      '#empty' => $this->t('No Indices Found.'),
    ];

    $all_indices = $this->client->indices()->getMapping();
    foreach ($all_indices as $id => $index) {

      $is_private = FALSE;
      if ($index['mappings']['_meta']['index_type'] == 'private') {
        $is_private = TRUE;
      }

      $form['indices_table'][$id] = [
        'site_name' => [
          '#markup' => $index['mappings']['_meta']['site_name'],
        ],
        'index_name' => [
          '#markup' => $id,
        ],
        'is_private' => [
          '#type' => 'checkbox',
          '#default_value' => $is_private,
        ],
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $all_indices = $this->client->indices()->getMapping();

    $privacy = [
      "0" => 'public',
      "1" => 'private',
    ];
    foreach ($values['indices_table'] as $index_name => $value) {
      $meta = $all_indices[$index_name]['mappings']['_meta'];
      $meta['index_type'] = $privacy[$value['is_private']] ?? 'public';
      try {
        $params = [
          'index' => $index_name,
          'body' => [
            '_meta' => $meta,
          ],
        ];
        $this->client->indices()->putMapping($params);
      }
      catch (\Exception $e) {
        $this->messenger->addError($e->getMessage());
        return FALSE;
      }
    }
    $this->messenger->addStatus($this->t('Changes saved successfully.'));
  }

}
