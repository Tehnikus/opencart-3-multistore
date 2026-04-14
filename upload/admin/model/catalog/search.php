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

}