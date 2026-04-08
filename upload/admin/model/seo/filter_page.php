<?php
class ModelSeoFilterPage extends Model {

  private $sortOrders = [
    'name'          => 'pd.`name`',
    'date_modified' => 'p2s.`date_modified`',
    'product_count' => '`product_count`',
    'category'      => '`category`',
  ];

  public function addFilterPage($data) {

    $this->db->query("START TRANSACTION");

    try {
      $this->load->model('design/seo_url');
      $requestString = $this->model_design_seo_url->buildQuery($data['filter_page_facet']);

      // Save main table
      $this->db->query("
        INSERT INTO " . DB_PREFIX . "seo_filter_page_to_store 
        SET
          `store_id` 			 = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', 
          `query`          = '" . $this->db->escape($requestString) . "',
          `date_modified`  = NOW()
        ");

      $filter_page_id = $this->db->getLastId();

      // Delete remaining parts just in case. Should never happen
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_url su
        WHERE su.`query` = (
          SELECT
            fp2s.`query`
          FROM " . DB_PREFIX . "seo_filter_page_to_store fp2s
          WHERE fp2s.filter_page_id = " . (int) $filter_page_id . "
            AND fp2s.store_id = " . (int) $this->session->data['store_id'] . "
        )
        AND su.store_id = " . (int) $this->session->data['store_id'] . "
      ");

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
            `meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? []), JSON_UNESCAPED_UNICODE)) . "'
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

      $this->editImages($filter_page_id, $data['filter_page_images']);

      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE query       = '" . $this->db->escape($requestString) . "'
            AND language_id = '" . (int) $language_id . "'
            AND store_id    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              store_id    = '" . (int) $this->session->data['store_id'] . "',
              language_id = '" . (int) $language_id . "', 
              query       = '" . $this->db->escape($requestString) . "', 
              keyword     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
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

      $this->load->model('design/seo_url');
      $requestString = $this->model_design_seo_url->buildQuery($data['filter_page_facet']);

      // Delete previous URL and request
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_url su
        WHERE su.`query` = (
          SELECT
            fp2s.`query`
          FROM " . DB_PREFIX . "seo_filter_page_to_store fp2s
          WHERE fp2s.filter_page_id = " . (int) $filter_page_id . "
            AND fp2s.store_id       = " . (int) $this->session->data['store_id'] . "
        )
        AND su.store_id = " . (int) $this->session->data['store_id'] . "
      ");

      // Update main table
      $this->db->query("
        UPDATE " . DB_PREFIX . "seo_filter_page_to_store 
        SET
          `query`          = '" . $this->db->escape($requestString) . "',
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
            `meta_keyword` 			= '" . $this->db->escape($value['meta_keyword']) . "',
            `footer`            = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['footer'] ?? []), JSON_UNESCAPED_UNICODE)) . "',
            `faq`               = '" . $this->db->escape(json_encode($this->filterArrayRecursively($value['faq'] ?? []), JSON_UNESCAPED_UNICODE)) . "'
        ");
      }

      // Update facet list
      $this->db->query("
        DELETE FROM " . DB_PREFIX . "seo_filter_page_facet_index
        WHERE filter_page_id = " . (int) $filter_page_id . "
          AND store_id       = " . (int) $this->session->data['store_id'] . "
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

      $this->editImages($filter_page_id, $data['filter_page_images']);


      // Save URL
      foreach ($data['seo_url'] ?? [] as $language_id => $keyword) {
        // Mostly safety delete, should never happen
        $this->db->query("
          DELETE FROM " . DB_PREFIX . "seo_url 
          WHERE query       = '" . $this->db->escape($requestString) . "'
            AND language_id = '" . (int) $language_id . "'
            AND store_id    = '" . (int) $this->session->data['store_id'] . "'
        ");

        if (!empty($keyword)) {
          $this->db->query("
            INSERT INTO " . DB_PREFIX . "seo_url 
            SET 
              store_id    = '" . (int) $this->session->data['store_id'] . "',
              language_id = '" . (int) $language_id . "', 
              query       = '" . $this->db->escape($requestString) . "', 
              keyword     = '" . $this->db->escape($keyword) . "'
          ");
        }
      }
      
      $this->db->query("COMMIT");
      return $filter_page_id;
      
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function editImages($page_id, $image_data = []) : int {

    $page_id = (int) $page_id;
    $store_id = (int) $this->session->data['store_id'];

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "seo_filter_page_image` 
      WHERE `filter_page_id` = '" . (int) $page_id . "'
        AND store_id         = " . (int) $store_id . "
    ");

    $this->db->query("
      DELETE FROM `". DB_PREFIX . "seo_filter_page_image_description` 
      WHERE `filter_page_id` = '" . (int) $page_id . "'
        AND store_id         = " . (int) $store_id . "
    ");


    foreach ($image_data as $image) {

      // Check if image actually exists
      if (!empty($image['image'])) {
        $this->db->query("
          INSERT INTO `". DB_PREFIX . "seo_filter_page_image`
          SET 
            `filter_page_id` 	  = '" . (int) $page_id . "', 
            `image` 				    = '" . $this->db->escape($image['image']) . "', 
            `sort_order` 		    = '" . (int) $image['sort_order'] . "',
            `store_id`          = '" . $store_id . "'
        ");

        // Add multilang multistore image descriptions
        $image_id = $this->db->getLastId();

        foreach ($image['description'] as $language_id => $image_description) {
          if (!$image_description) {
            continue;
          }
          $this->db->query("
            INSERT INTO `". DB_PREFIX . "seo_filter_page_image_description`
            SET
              `image_id` 	      = '" . (int) $image_id . "',
              `filter_page_id` 	= '" . (int) $page_id . "',
              `language_id` 		= '" . (int) $language_id . "',
              `store_id` 				= '" . (int) $store_id . "',
              `description` 		= '" . $this->db->escape($image_description) ."'
          ");
        }
      }
    }

    return $this->db->countAffected();
  }

  public function deleteFilterPage($filter_page_id) : void {
    $this->db->query("START TRANSACTION");

    try {
      $tables = [
        'seo_filter_page_facet_index',
        'seo_filter_page_to_store',
        'seo_filter_page_description',
        'seo_filter_page_image',
        'seo_filter_page_image_description',
      ];

      // Delete URL
      $this->db->query("
        DELETE su
        FROM " . DB_PREFIX . "seo_url su
        JOIN " . DB_PREFIX . "seo_filter_page_to_store fp2s
          ON fp2s.`query` = su.`query`
        WHERE fp2s.filter_page_id = " . (int)$filter_page_id . "
          AND fp2s.store_id = " . (int)$this->session->data['store_id'] . "
          AND su.store_id = " . (int)$this->session->data['store_id'] . "
      ");

      // Delete data
      foreach ($tables as $table) {
        $this->db->query("
          DELETE FROM " . DB_PREFIX . $table . "
          WHERE filter_page_id = " . (int) $filter_page_id . "
            AND store_id       = " . (int) $this->session->data['store_id'] . "
        ");
      }

      $otherStores = $this->db->query("
        SELECT 1
        FROM " . DB_PREFIX . "seo_filter_page_to_store
        WHERE filter_page_id = " . (int)$filter_page_id . "
          AND store_id      <> " . (int) $this->session->data['store_id'] . "
        LIMIT 1
      ")->num_rows;

      if (!$otherStores) {
        foreach ($tables as $table) {
          $this->db->query("
            DELETE FROM " . DB_PREFIX . $table . "
            WHERE filter_page_id = " . (int)$filter_page_id . "
          ");
        }
      }

      $this->db->query("COMMIT");
    } catch (\Throwable $e) {
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  public function getList($filter) : array {
    $result = [];
    $storeId = (int) $this->session->data['store_id'];
    $languageId = (int) $this->config->get('config_language_id');
    
    // Orders
    $ordering = '';
    $ordering = "ORDER BY " . ($this->sortOrders[$filter['sort']] ?? 'p2s.`date_modified`');
    if (!empty($filter['order']) && in_array($filter['order'], ['ASC', 'DESC'])) {
      $ordering .= " " . $filter['order'];
    }
      
    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 20));
    $start  = max(0, (int) ($filter['start'] ?? 0));
    $limits = " LIMIT {$start}, {$limit}";

    $sql = "
      SELECT
        pd.`filter_page_id`,
        pd.`name`,
        p2s.`date_modified`,
        (SELECT fpi.image FROM " . DB_PREFIX . "seo_filter_page_image fpi WHERE fpi.filter_page_id = p2s.filter_page_id AND fpi.store_id = p2s.store_id ORDER BY fpi.sort_order LIMIT 1) AS image,
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
        (
          SELECT JSON_OBJECT(
            'descriptionLength',    COALESCE(CHAR_LENGTH(pd.`description`), 0),
            'seoDescriptionLength', COALESCE(CHAR_LENGTH(pd.`seo_description`), 0),
            'seoKeywords',          pd.seo_keywords,
            'hasFooter',            CHAR_LENGTH(COALESCE(pd.`footer`, '')) > 2,
            'hasFaq',               CHAR_LENGTH(COALESCE(pd.`faq`, '')) > 2,
            'hasHowTo',             CHAR_LENGTH(COALESCE(pd.`how_to`, '')) > 2
          )
        ) AS seo,
        (SELECT fi.facet_value_id FROM " . DB_PREFIX . "seo_filter_page_facet_index fi WHERE fi.filter_page_id = pd.filter_page_id AND fi.facet_type = 1) AS category_id
      FROM " . DB_PREFIX . "seo_filter_page_description pd
      JOIN " . DB_PREFIX . "seo_filter_page_to_store p2s
        ON p2s.filter_page_id = pd.filter_page_id
      WHERE pd.language_id = {$languageId}
        AND p2s.store_id   = {$storeId}
      {$ordering}
      {$limits}
    ";

    foreach($this->db->query($sql)->rows ?? [] as $row) {
      $row['facets'] = json_decode($row['facets'], true);
      $row['seo'] = json_decode($row['seo'], true);
      $row['seo']['seoKeywords'] = count(array_filter(explode(',', $row['seo']['seoKeywords'])));
      $row['image'] = $row['image'] ? (HTTPS_CATALOG . 'image/' . $row['image']) : (HTTPS_CATALOG . 'image/no_image.webp');

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
      $row['footer'] = json_decode($row['footer'] ?? '[]', true);
      $row['faq']    = json_decode($row['faq'] ?? '[]', true);
      $result[$row['language_id']] = $row;
    }
    
    return $result ?? [];
  }

  public function getSeoUrl($filter_page_id = null) : array {
    $result   = [];
    $store_id = (int) $this->session->data['store_id'];

    if ($filter_page_id === null) {
      return $result;
    }

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_url su
      WHERE su.`query` = (
        SELECT 
          fp2s.`query`
        FROM " . DB_PREFIX . "seo_filter_page_to_store fp2s
        WHERE fp2s.filter_page_id = " . (int) $filter_page_id . "
          AND fp2s.store_id = " . $store_id . "
      )
      AND su.`store_id` = " . $store_id . "
    ")->rows;

    foreach ($query as $row) {
      $result[$row['language_id']] = $row['keyword'];
    }

    return $result;
  }

  public function getImages($page_id) : array {
    $result = [];
    $store_id = (int) $this->session->data['store_id'];
    $images = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . "seo_filter_page_image pi
      WHERE `filter_page_id` = " . (int) $page_id . "
        AND store_id         = " . (int) $store_id . "
      ORDER BY `sort_order`
    ")->rows;

    foreach ($images as $row) {
      $descriptions = $this->db->query("
        SELECT
          `language_id`,
          `description`
        FROM " . DB_PREFIX . "seo_filter_page_image_description
        WHERE image_id = " . (int) $row['image_id'] . "
          AND store_id = " . (int) $store_id . "
      ")->rows;
      foreach ($descriptions as $description) {
        $row['description'][$description['language_id']] = $description['description'];
      }
      $result[] = $row;
    }

    return $result;
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
    $store_id = (int) $this->session->data['store_id'];

    // Build flat list fo facets
    foreach ($filters as $facetType => $groups) {
      foreach ($groups as $groupId => $values) {
        foreach ($values as $valueId) {
          $flat[] = [
            'facet_type'     => (int) $facetType,
            'facet_group_id' => (int) $groupId,
            'facet_value_id' => (int) $valueId,
          ];
        }
      }
    }

    // Build WHERE conditions
    foreach ($flat as $f) {
      $where[] = "(
        facet_type         = {$f['facet_type']} 
        AND facet_group_id = {$f['facet_group_id']} 
        AND facet_value_id = {$f['facet_value_id']}
        AND store_id       = {$store_id}
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

    /**
   * Filter array recursively and remove empty key => value pairs
   * @param array $array The array to be affected
   * @param array $deletedKeys The array of keys that will be treated as empty if all other keys are empty on this level 
   * @return array
   */
  public function filterArrayRecursively(array $array = [], array $deletedKeys = []): array {
    $filtered = [];

    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $value = $this->filterArrayRecursively($value, $deletedKeys);
        if (!empty($value)) {
          $filtered[$key] = $value;
        }
      // $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;' is a workaround for empty Summernote editor which always places this string: '<p><br></p>'
      } elseif (trim((string) $value) !== '' && $value !== '&lt;p&gt;&lt;br&gt;&lt;/p&gt;') {
        $filtered[$key] = $value;
      }
    }

    // If result array is not empty, but only includes deletedKeys - then clear them also
    if (!empty($filtered)) {
      $nonDeletedKeys = array_diff(array_keys($filtered), $deletedKeys);

      // Check recursively
      $hasMeaningfulData = !empty($nonDeletedKeys);
      foreach ($filtered as $key => $value) {
        if (is_array($value) && !empty($value)) {
          $hasMeaningfulData = true;
          break;
        }
      }

      if (!$hasMeaningfulData) {
        return [];
      }
    }

    return $filtered;
  }
}