<?php
class ModelSeoMetaEditor extends Model
{
  private $types = [
    'category'      => ['table' => 'category_description',        'column_id' => 'category_id'],
    'product'       => ['table' => 'product_description',         'column_id' => 'product_id'],
    'filter_page'   => ['table' => 'seo_filter_page_description', 'column_id' => 'seo_filter_page_id'],
    'manufacturer'  => ['table' => 'manufacturer_description',    'column_id' => 'manufacturer_id'],
    'article'       => ['table' => 'article_description',         'column_id' => 'article_id'],
    'tag'           => ['table' => 'blog_tag_description',        'column_id' => 'blog_tag_id'],
  ];

  public function getTypes() : array {
    return $this->types;
  }

  public function getPages($filter) : array {
    $result = [];
    if (!isset($this->types[$filter['type']])) {
      return $result;
    }

    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 100));
    $start  = max(0, (int) ($filter['start'] ?? 0));
    $limits = " LIMIT {$start}, {$limit}";

    $query = $this->db->query("
      SELECT
        *
      FROM " . DB_PREFIX . $this->types[$filter['type']]['table'] . "
      WHERE store_id = " . (int) $this->session->data['store_id'] . "
      $limits
    ");

    foreach ($query->rows ?? [] as $row) {
      $result[$row[$this->types[$filter['type']]['column_id']]][$row['language_id']] = $row;
    }

    return $result;
  }

}
