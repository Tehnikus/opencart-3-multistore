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
          INSERT INTO " . DB_PREFIX . "seo_filter_page_description 
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
  public function editFilterPage($filter_page_id, $data) {
    
    $this->db->query("START TRANSACTION");

    try {

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "seo_filter_page_to_store 
        SET
          `date_modified`  = NOW()
        WHERE filter_page_id = " . (int) $filter_page_id . "
      ");

      // Update descriptions
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_filter_page_description
        WHERE filter_page_id = " . (int) $filter_page_id . "
      ");

      foreach ($data['filter_page_description'] as $language_id => $value) {
        $this->db->query("
          INSERT INTO " . DB_PREFIX . "seo_filter_page_description 
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

      // Update facet list
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_filter_page_facet_index
        WHERE filter_page_id = " . (int) $filter_page_id . "
      ");

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
    $languageId = (int) $this->config->get('config_language_id');
    $sql = "
      SELECT
        pd.filter_page_id,
        pd.`name`,
        (
          SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
              'facet_type',     fi.facet_type,
              'facet_group_id', fi.facet_group_id,
              'facet_value_id', fi.facet_value_id,
              'name',           fn.name,
              'group_name',     fn.group_name
            )
          )
          FROM " . DB_PREFIX . "seo_filter_page_facet_index fi
          LEFT JOIN " . DB_PREFIX . "facet_name fn
            ON  fn.facet_type     = fi.facet_type
            AND fn.facet_group_id = fi.facet_group_id
            AND fn.facet_value_id = fi.facet_value_id
          WHERE fi.filter_page_id = pd.filter_page_id
        ) AS facets,
        (
          SELECT COUNT(*)
          FROM (
            SELECT p.product_id
            FROM " . DB_PREFIX . "facet_index p
            JOIN " . DB_PREFIX . "seo_filter_page_facet_index fi
              ON fi.filter_page_id = pd.filter_page_id
              AND fi.facet_type = p.facet_type
              AND fi.facet_group_id = p.facet_group_id
              AND fi.facet_value_id = p.facet_value_id
            WHERE p.store_id = {$storeId}
            GROUP BY p.product_id
            HAVING COUNT(*) = (
              SELECT COUNT(*)
              FROM " . DB_PREFIX . "seo_filter_page_facet_index fi2
              WHERE fi2.filter_page_id = pd.filter_page_id
            )
          ) t
        ) AS product_count,
        (SELECT fi.facet_value_id FROM " . DB_PREFIX . "seo_filter_page_facet_index fi WHERE fi.filter_page_id = pd.filter_page_id AND fi.facet_type = 1) AS category_id
      FROM " . DB_PREFIX . "seo_filter_page_description pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
        AND p2s.store_id = {$storeId}
      WHERE pd.language_id = {$languageId}
    ";

    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['facets'] = json_decode($row['facets'], true);
      // Sort facets, so parent category (facet_type == 1) is always first
      usort($row['facets'], fn($a, $b) => $a['facet_type'] <=> $b['facet_type']);
      $result[] = $row;
    }

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
      FROM " . DB_PREFIX . "seo_filter_page_description pd
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

  /**
   * Check if filter page with selected facets already exists
   * @param array $filters selected facets
   * @return array of filter page ids
   */
  public function getExistingPage($filters) : array {
    
    $flat  = [];
    $where = [];

    // Build flat list fo facets
    foreach ($filters as $facetType => $groups) {
      foreach ($groups as $groupId => $values) {
        foreach ($values as $valueId) {
          $flat[] = [
            'facet_type'     => (int)$facetType,
            'facet_group_id' => (int)$groupId,
            'facet_value_id' => (int)$valueId,
          ];
        }
      }
    }

    // Build WHERE conditions
    foreach ($flat as $f) {
      $where[] = "(
        facet_type = {$f['facet_type']} AND
        facet_group_id = {$f['facet_group_id']} AND
        facet_value_id = {$f['facet_value_id']}
      )";
    }

    $sql = "
      SELECT 
        filter_page_id
      FROM " . DB_PREFIX . "seo_filter_page_facet_index
      WHERE " . implode(" OR ", $where) . "
      GROUP BY filter_page_id

      HAVING 
        COUNT(*) = " . count($flat) . " -- Requested facet_value_id count
        AND COUNT(*) = (
          SELECT COUNT(*) -- Actual page facet_value_id count
          FROM " . DB_PREFIX . "seo_filter_page_facet_index fi2
          WHERE fi2.filter_page_id = " . DB_PREFIX . "seo_filter_page_facet_index.filter_page_id
        )
    ";

    $result = $this->db->query($sql);

    return $result->rows;
  }

  public function getFilterPageTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "seo_filter_page_description pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
        AND p2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}