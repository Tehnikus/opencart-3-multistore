<?php
class ModelCatalogSearch extends Model {

  /**
   * Full search index rebuild
   */
  public function rebuildIndex() : array {
    $stats = [
      'deleted' => 0,
      'indexed' => 0,
      'errors'  => [],
    ];

    $this->db->query("START TRANSACTION");

    try {
      // Clear index
      // Truncate is faster than delte by row
      $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "product_search_index`");
      $stats['deleted'] = $this->db->countAffected();

      // Build the whole index
      $this->db->query($this->buildInsertQuery());
      $stats['indexed'] = $this->db->countAffected();
      $this->db->query("COMMIT");

    } catch (Exception $e) {
      $stats['errors'][] = $e->getMessage();
      $this->db->query("ROLLBACK");
    }

    return $stats;
  }

  /**
   * @param mixed $product_id If not isset the index will be created for all products in store with $store_id = n
   * @param mixed $store_id If not isset the index for $product_id = n will be created for all stores
   * If both params are not set the index will be created for all products in all stores
   */
  public function buildSearchIndex($product_id = null, $store_id = null) : void {
    $where = [];
    if ($product_id !== null) {
      $where[] = "product_id = " . (int) $product_id;
    }
    if ($store_id !== null) {
      $where[] = "store_id = " . (int) $store_id;
    }

    // DB Transaction
    $this->db->query("START TRANSACTION");
    try {
      if ($product_id !== null) {

        // First remove old data
        $this->db->query("
          DELETE FROM `" . DB_PREFIX . "product_search_index`
          WHERE " . implode(' AND ', $where) . "
        ");
  
        // Then check product status
        $result = $this->db->query("
          SELECT 
            `status` 
          FROM `" . DB_PREFIX . "product_to_store`
          WHERE " . implode(' AND ', $where) . "
        ");

        // If product is disabled just exit
        if (!$result->num_rows || !$result->row['status']) {
          return;
        }
      }

      // If product is available or product_id is null build search index
      $this->db->query($this->buildInsertQuery($product_id, $store_id));
      // Commit DB changes
      $this->db->query("COMMIT");
    } catch (Exception $e) {
      // Rollback DB changes
      $this->db->query("ROLLBACK");
      throw $e;
    }
  }

  /**
   * Delete product from index
   * @param mixed $product_id If not isset the index will be deleted for all products in the store with $store_id = n
   * @param mixed $store_id If not isset the index for $product_id = n will be deleted in all stores
   */
  public function deleteSearchIndex($product_id = null, $store_id = null) : void {
    $where = [];
    if ($product_id !== null) {
      $where[] = "product_id = " . (int) $product_id;
    }
    if ($store_id !== null) {
      $where[] = "store_id = " . (int) $store_id;
    }
    $this->db->query("
      DELETE FROM `" . DB_PREFIX . "product_search_index`
      WHERE " . implode(' AND ', $where) . "
    ");
  }

  /**
   * Main query
   * Gets strings and writes them into fulltext indexed columns
   * @param mixed $product_id If not isset the index will be created for all products in the store with $store_id = n
   * @param mixed $store_id If not isset the index for $product_id = n will be created for all stores
   * If both params are not set the index will be created for all products in all stores
   */
  
  private function buildInsertQuery($product_id = null, $store_id = null): string {
    $prefix = DB_PREFIX;

    $where = [];
    $where[] = "p2s.`status` = 1";
    if ($product_id !== null) {
      $where[] = "pd.product_id = " . (int) $product_id;
    }
    if ($store_id !== null) {
      $where[] = "pd.store_id = " . (int) $store_id;
    }

    return "
      REPLACE INTO `{$prefix}product_search_index`
        (`product_id`, `language_id`, `store_id`, `name`, `manufacturer`, `category`, `extra`)

      SELECT
        p.`product_id`,
        pd.`language_id`,
        p2s.`store_id`,

        /* Product name, model, SKU */
        CONCAT_WS(' ',
          pd.`name`,
          NULLIF(p.`model`, ''),
          NULLIF(p.`sku`, '')
        ) AS name,

        /* Manufacturer */
        COALESCE(
          (
            SELECT
              md.`name`
            FROM {$prefix}manufacturer_description md
            WHERE md.`manufacturer_id` = p.`manufacturer_id`
              AND md.`store_id` = p2s.`store_id`
              AND md.`language_id` = pd.`language_id`
          ), ''
        ) AS manufacturer,

        /* Categories */
        COALESCE(
          (
            SELECT 
              GROUP_CONCAT(DISTINCT cd.name SEPARATOR ' ')
            FROM {$prefix}product_to_category p2c
            JOIN {$prefix}category_description cd
              ON  cd.`category_id`  = p2c.`category_id`
              AND cd.`language_id`  = pd.`language_id`
              AND cd.`store_id`     = p2s.`store_id`
            WHERE p2c.`product_id`  = p2s.`product_id`
              AND p2c.`store_id`    = p2s.`store_id`
          ), ''
        ) AS category,

        /* Extra */
        CONCAT_WS(' ',
          /* Option values */
          NULLIF(
            (
              SELECT 
                GROUP_CONCAT(DISTINCT ovd.name SEPARATOR ' ')
              FROM {$prefix}product_option po
              JOIN {$prefix}product_option_value pov
                ON  pov.`product_option_id` = po.`product_option_id`
                AND pov.`store_id`          = p2s.`store_id`
              JOIN {$prefix}option_value_description ovd
                ON  ovd.`option_value_id` = pov.`option_value_id`
                AND ovd.`language_id`     = pd.`language_id`
                AND ovd.`store_id`        = p2s.`store_id`
              WHERE po.`product_id` = p2s.`product_id`
                AND po.`store_id`   = p2s.`store_id`
            ), ''),

          /* Attributes */
          NULLIF(
            (
              SELECT 
                GROUP_CONCAT(DISTINCT ad.name SEPARATOR ' ')
              FROM {$prefix}attribute_description ad
              JOIN {$prefix}product_attribute pa
                ON  pa.`product_id`  = p.`product_id`
                AND pa.`store_id`    = p2s.`store_id`
              WHERE ad.`attribute_id` = pa.`attribute_id`
                AND ad.`language_id`  = pd.`language_id`
                AND ad.`store_id`     = p2s.`store_id`
            ), ''),

          /* Filters */
          NULLIF(
            (
              SELECT 
                GROUP_CONCAT(DISTINCT fd.name SEPARATOR ' ')
              FROM {$prefix}product_filter pf
              JOIN {$prefix}filter_description fd
                ON  fd.filter_id   = pf.filter_id
                AND fd.language_id = pd.language_id
                AND fd.store_id    = p2s.store_id
              WHERE pf.product_id = p.product_id
                AND pf.store_id   = p2s.store_id
            ), ''),

          NULLIF(
            (
              SELECT GROUP_CONCAT(DISTINCT td.name SEPARATOR ' ')
                FROM {$prefix}product_seo_tag pt
              JOIN {$prefix}seo_tag_description td
                ON td.seo_tag_id = pt.seo_tag_id
                AND td.language_id = pd.language_id
                AND td.store_id = p2s.store_id
              WHERE pt.product_id = p.product_id
                AND pt.store_id   = p2s.store_id
            ), '')
        ) AS extra

      FROM `{$prefix}product` p
      JOIN `{$prefix}product_description` pd
        ON pd.product_id = p.product_id
      JOIN `{$prefix}product_to_store` p2s
        ON p2s.product_id = p.product_id

      WHERE " . implode(' AND ', $where) . "
    ";
  }
}