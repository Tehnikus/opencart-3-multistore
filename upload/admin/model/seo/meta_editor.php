<?php
class ModelSeoMetaEditor extends Model
{
  private $types = [
    'category'      => ['main_table' => 'category_to_store',        'description_table' => 'category_description',        'column_id' => 'category_id'],
    'product'       => ['main_table' => 'product_to_store',         'description_table' => 'product_description',         'column_id' => 'product_id'],
    'filter_page'   => ['main_table' => 'seo_filter_page_to_store', 'description_table' => 'seo_filter_page_description', 'column_id' => 'filter_page_id'],
    'manufacturer'  => ['main_table' => 'manufacturer_to_store',    'description_table' => 'manufacturer_description',    'column_id' => 'manufacturer_id'],
    'article'       => ['main_table' => 'article_to_store',         'description_table' => 'article_description',         'column_id' => 'article_id'],
    'tag'           => ['main_table' => 'blog_tag_to_store',        'description_table' => 'blog_tag_description',        'column_id' => 'blog_tag_id'],
  ];

  public function getTypes() : array {
    return $this->types;
  }

  public function getPages($filter) : array {
    $result = [];
    $preresult = [];
    if (!isset($this->types[$filter['type']])) {
      return $result;
    }

    $type = $this->types[$filter['type']];
    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 100));
    $start  = max(0, (int) ($filter['start'] ?? 0));

    $query = $this->db->query("
      SELECT
        m.`" . $type['column_id'] . "` as column_id,
        d.*
      FROM (
        SELECT `" . $type['column_id'] . "`
        FROM `" . DB_PREFIX . $type['main_table'] . "`
        WHERE `store_id` = " . (int) $this->session->data['store_id'] . "
        LIMIT {$start}, {$limit}
      ) m
      LEFT JOIN `" . DB_PREFIX . $type['description_table'] . "` d 
        ON d.`" . $type['column_id'] . "` = m.`" . $type['column_id'] . "`
        AND d.`store_id` = " . (int) $this->session->data['store_id'] . "
    ");

    foreach ($query->rows as $row) {
      $result[] = $row;
    }

    return $result;
  }

}
