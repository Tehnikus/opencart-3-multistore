<?php
class ModelBlogArticle extends Model {
  public function getList($filter) : array {
    $result = [];
    $storeId = (int) $this->session->data['store_id'];

    $sql = "
      SELECT
        *
      FROM " . DB_PREFIX . "blog_article_description bad
      JOIN " . DB_PREFIX . "blog_article_to_store ba2s
        ON ba2s.article_id = bad.article_id
        AND ba2s.store_id = {$storeId}
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
      FROM " . DB_PREFIX . "blog_article_description bad
      JOIN " . DB_PREFIX . "blog_article_to_store ba2s
        ON ba2s.article_id = bad.article_id
        AND ba2s.store_id = {$storeId}
      WHERE bad.article_id = {$pageId}
    ";
    
    return $this->db->query($sql)->rows ?? [];
  }

  public function getArticleTotal() : int {
    $storeId = (int) $this->session->data['store_id'];
    $query = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM " . DB_PREFIX . "blog_article_description bad
      JOIN " . DB_PREFIX . "blog_article_to_store ba2s
        ON ba2s.article_id = bad.article_id
        AND ba2s.store_id = {$storeId}
    ");

    return (int) ($query->row['pages_count'] ?? 0);
  }

}