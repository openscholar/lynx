<?php

namespace Drupal\lynx\Helper;

use Drupal\elasticsearch_connector\ClusterManager;
use Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface;

/**
 * QueryHelper class.
 */
class QueryHelper implements QueryHelperInterface {

  /**
   * The client manager service.
   *
   * @var \Drupal\elasticsearch_connector\ElasticSearch\ClientManagerInterface
   */
  private $clientManager;

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
   * {@inheritdoc}
   */
  public function __construct(ClusterManager $cluster_manager, ClientManagerInterface $client_manager) {
    $this->clientManager = $client_manager;
    $this->clusterManager = $cluster_manager;
    $clusters = $cluster_manager->loadAllClusters(FALSE);
    $this->client = $this->clientManager->getClientForCluster(current($clusters));
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedIndices() {
    $indices = [];
    $all_indices = $this->client->indices()->getMapping();
    foreach ($all_indices as $id => $index) {
      if (isset($index['mappings']['_meta']['base_url']) && $index['mappings']['_meta']['index_type'] != 'private') {
        $indices[$id] = $index;
      }
    }
    return $indices;
  }

  /**
   * {@inheritdoc}
   */
  public function buildQuery($params) {
    $query = [];
    $keyword_values = [];
    if ($params['keyword']) {
      $keyword_values['query'] = $params['keyword'] . '~';
      $keyword_values['fields'] = ['body', 'custom_title', 'reference_title'];
      $query['query']['bool']['must']['query_string'] = $keyword_values;
    }
    else {
      return [];
    }

    if ($params['terms']) {
      foreach ($params['terms'] as $field => $value) {
        if (is_array($value)) {
          $filter_values[]['terms'][$field] = $value;
        }
        else {
          $filter_values[]['term'][$field] = $value;
        }
      }
      $query['query']['bool']['filter'] = $filter_values;
    }
    $query['aggs']['group_by_type']['terms'] = ['size' => 100, 'field' => 'custom_type'];
    $query['_source'] = [
      'custom_title',
      'body',
      'vsite_logo',
      'vsite_name',
      'vsite_description',
      'custom_search_group',
      'custom_type',
    ];
    $query['from'] = $params['from'];
    $query['size'] = $params['size'];
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function search($indices, $query) {
    try {
      $response = $this->client->search([
        'index' => $indices,
        'body' => $query,
      ])->getRawResponse();
    }
    catch (\Exception $e) {
      watchdog_exception('Elasticsearch API', $e);
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function putMapping($params) {
    try {
      $response = $this->client->indices()->putMapping($params);
    }
    catch (\Exception $e) {
      watchdog_exception('Elasticsearch API', $e);
    }
    return $response;
  }

}
