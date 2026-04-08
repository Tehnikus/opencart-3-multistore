<?php
class ModelBlogArticle extends Model {
  public function getList($filter) : array {
    $result = [];
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
    ";

    $this->db->query($sql);

    return $result;
  }

  public function getArticleDescriptions($pageId) : array {
    $pageId = (int) $pageId;
    $storeId = (int) $this->session->data['store_id'];
    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
      WHERE ad.article_id = {$pageId}
    ";
    
    return $this->db->query($sql)->rows ?? [];
  }

  public function getArticleTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "article_description ad
      JOIN " . DB_PREFIX . "article_to_store a2s
        ON a2s.article_id = ad.article_id
        AND a2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}