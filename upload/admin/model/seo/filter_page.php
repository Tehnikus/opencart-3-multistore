<?php
class ModelSeoFilterPage extends Model {

  public function addFilterPage($data) {

    $this->db->query("START TRANSACTION");

    try {

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "seo_filter_page_to_store 
        SET
          `store_id` 			 = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', 
          `date_modified`  = NOW()
        ");

      $filter_page_id = $this->db->getLastId();

      // Save descriptions
      foreach ($data['filter_page_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "seo_filter_page_desciption 
          SET 
            `filter_page_id` 		= '" . (int) $filter_page_id . "', 
            `language_id` 			= '" . (int) $language_id . "', 
            `store_id` 					= '" . (int) $this->session->data['store_id'] . "',
            `name` 							= '" . $this->db->escape($value['name']) . "', 
            `h1`                = '" . $this->db->escape($value['h1']) . "', 
            `description` 			= '" . $this->db->escape($value['description']) . "', 
            `meta_title` 				= '" . $this->db->escape($value['meta_title']) . "', 
            `meta_description` 	= '" . $this->db->escape($value['meta_description']) . "', 
            `meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "'
        ");
      }

      foreach ($data['filter_page_facet'] ?? [] as $facet_type => $facet_group) {
        foreach ($facet_group as $facet_group_id => $facet_value) {
          foreach ($facet_value as $facet_value_id) {
            $this->db->query("
              INSERT INTO " . DB_PREFIX . "seo_filter_page_facet_index
              SET
                `filter_page_id`    = " . (int) $filter_page_id . ",
                `facet_type`        = " . (int) $facet_type . ",
                `facet_value_id`    = " . (int) $facet_value_id . ",
                `facet_group_id`    = " . (int) $facet_group_id . ",
                `store_id`          = " . (int) $this->session->data['store_id'] . "
            ");
          }
        }
      }

      // Save URL
      // foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
      //   if (!empty($keyword)) {
      //     $this->db->query("
      //       INSERT INTO " . DB_PREFIX . "seo_url 
      //       SET 
      //         store_id    = '" . (int) $this->session->data['store_id'] . "',
      //         language_id = '" . (int) $language_id . "', 
      //         query       = '', 
      //         keyword     = '" . $this->db->escape($keyword) . "'
      //     ");
      //   }
      // }
      
      $this->db->query("COMMIT");
      return $filter_page_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function getList($filter) : array {
    $result = [];
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "seo_filter_page_descciption pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
        AND p2s.store_id = {$storeId}
    ";

    $this->db->query($sql);

    return $result;
  }

  public function getFilterPageDescription($pageId) : array {
    $result = [];
    if ($pageId === null) {
      return $result;
    }
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "seo_filter_page_desciption pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
        AND p2s.store_id = {$storeId}
      WHERE pd.filter_page_id = {$pageId}
    ";

    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $result[$row['language_id']] = $row;
    }
    
    return $result ?? [];
  }
  
  public function getFilterPageFacets($pageId) : array {
    $result = [];
    if ($pageId === null) {
      return $result;
    }
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "seo_filter_page_facet_index
      WHERE filter_page_id = {$pageId}
        AND store_id = {$storeId}
    ";

    foreach ($this->db->query($sql)->rows ?? [] as $row) {
      $result[$row['facet_type']][$row['facet_group_id']][$row['facet_value_id']] = $row['facet_value_id'];
    }
    
    return $result;
  }

  public function getFacetName($facetTypeId, $facetGroupId, $facetValueId) : array {
    $storeId = (int) $this->session->data['store_id'];
    $languageId = (int) $this->config->get('config_language_id');
    
    $result = $this->db->query("
      SELECT
        `name`,
        `group_name`
      FROM " . DB_PREFIX . "facet_name
      WHERE `facet_type`     = " . (int) $facetTypeId . "
        AND `facet_value_id` = " . (int) $facetValueId . "
        AND `facet_group_id` = " . (int) $facetGroupId . "
        AND `language_id`    = " . (int) $languageId . "
        AND `store_id`       = " . (int) $storeId . "
    ")->row;

    return $result ?? [];
  }

  public function getFilterPageTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "seo_filter_page_desciption pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
        AND p2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}