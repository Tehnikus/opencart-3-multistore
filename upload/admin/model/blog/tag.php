<?php
class ModelBlogCategory extends Model {
  public function getList($filter) : array {
    $result = [];
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "blog_category_description bcd
      JOIN " . DB_PREFIX . "blog_category_to_store bc2s
        ON bc2s.category_id = bcd.category_id
        AND bc2s.store_id = {$storeId}
    ";

    $this->db->query($sql);

    return $result;
  }

  public function getCategoryDescriptions($pageId) : array {
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "blog_category_description bcd
      JOIN " . DB_PREFIX . "blog_category_to_store bc2s
        ON bc2s.category_id = bcd.category_id
        AND bc2s.store_id = {$storeId}
      WHERE bcd.category_id = {$pageId}
    ";
    
    return $this->db->query($sql)->rows ?? [];
  }

  public function getCategoryTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "blog_category_description bcd
      JOIN " . DB_PREFIX . "blog_category_to_store bc2s
        ON bc2s.category_id = bcd.category_id
        AND bc2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}