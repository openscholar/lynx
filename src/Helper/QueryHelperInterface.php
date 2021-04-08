<?php

namespace Drupal\lynx\Helper;

/**
 * Lynx search query interface.
 */
interface QueryHelperInterface {

  /**
   * Get Allowed Indices for Lynx.
   *
   * @return array
   *   Returns array of allowed indices.
   */
  public function getAllowedIndices();

  /**
   * Build Elastic Search Query.
   *
   * @param string $params
   *   Comma-separated list of indices to be searched.
   *
   * @return array
   *   Returns array of allowed indices.
   */
  public function buildQuery($params);

  /**
   * Perform search operation.
   *
   * @param string $indices
   *   Comma-separated list of indices to be searched.
   * @param array $query
   *   Elasticsearch Query.
   *
   * @return array
   *   Returns search results.
   */
  public function search($indices, array $query);

  /**
   * Alter Index mapping properties.
   *
   * @param string $params
   *   Index properties to be changed.
   *
   * @return array
   *   Returns acknowledgement if its sucessfull.
   */
  public function putMapping($params);

}
