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
      if ($index['mappings']['_meta']['index_type'] != 'private') {
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
        $filter_values[]['term'][$field] = $value;
      }
      $query['query']['bool']['filter'] = $filter_values;
    }
    $query['_source'] = ['custom_title', 'body', 'vsite_logo', 'vsite_name', 'custom_search_group'];
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

}
