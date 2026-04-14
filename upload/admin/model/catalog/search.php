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

}