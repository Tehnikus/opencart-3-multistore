<?php
class ModelSeoMetaEditor extends Model
{
  private $types = [
    'category'      => ['main_table' => 'category_to_store',        'description_table' => 'category_description',        'path' => 'catalog/category',     'column_id' => 'category_id'],
    'product'       => ['main_table' => 'product_to_store',         'description_table' => 'product_description',         'path' => 'catalog/product',      'column_id' => 'product_id'],
    'filter_page'   => ['main_table' => 'seo_filter_page_to_store', 'description_table' => 'seo_filter_page_description', 'path' => 'seo/filter_page',      'column_id' => 'filter_page_id'],
    'manufacturer'  => ['main_table' => 'manufacturer_to_store',    'description_table' => 'manufacturer_description',    'path' => 'catalog/manufacturer', 'column_id' => 'manufacturer_id'],
    'article'       => ['main_table' => 'article_to_store',         'description_table' => 'article_description',         'path' => 'blog/article',         'column_id' => 'article_id'],
    'tag'           => ['main_table' => 'blog_tag_to_store',        'description_table' => 'blog_tag_description',        'path' => 'blog/tag',             'column_id' => 'blog_tag_id'],
  ];

  public function getTypes() : array {
    return $this->types;
  }

  /**
   * Get pages SEO data
   * @param array $filter
   * @return array pages data with nested langId => data_array
   */
  public function getPages($filter) : array {
    $result = [];
    if (!isset($this->types[$filter['type']])) {
      return $result;
    }

    $type = $this->types[$filter['type']];
    // Limits
    $limit  = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 100));
    $start  = max(0, (int) ($filter['start'] ?? 0));
    $currentLang = (int) $this->config->get('config_language_id'); // Current admin language id
    $currentStore = (int) $this->session->data['store_id']; // Current store id
    $rows = $this->db->query("
      SELECT
        m.`" . $type['column_id'] . "` as column_id,
        COALESCE(
          MAX(CASE 
            WHEN d.language_id = {$currentLang}
            AND d.store_id = {$currentStore}
            THEN d.name
          END),
          MAX(CASE 
            WHEN d.language_id = {$currentLang}
            THEN d.name
          END),
          MAX(d.name)
        ) AS default_name,
        JSON_ARRAYAGG(
          JSON_OBJECT(
            '" . $type['column_id'] . "', d.`" . $type['column_id'] . "`,
            'name',               d.`name`,
            'h1',                 d.`h1`,
            'meta_title',         d.`meta_title`,
            'meta_description',   d.`meta_description`,
            'meta_keyword',       d.`meta_keyword`,
            'description',        d.`description`,
            'seo_keywords',       d.`seo_keywords`,
            'seo_description',    d.`seo_description`,
            'faq',                d.`faq`,
            'how_to',             d.`how_to`,
            'footer',             d.`footer`,
            'date_modified',      d.`date_modified`,
            'language_id',        d.`language_id`,
            'store_id',           d.`store_id`
          )
        ) AS lang_data
        FROM `" . DB_PREFIX . $type['main_table'] . "` m
        LEFT JOIN `" . DB_PREFIX . $type['description_table'] . "` d 
          ON  d.`" . $type['column_id'] . "` = m.`" . $type['column_id'] . "`
          AND d.`store_id` = m.`store_id`
        WHERE m.`store_id` = " . (int) $this->session->data['store_id'] . "
        GROUP BY m.`" . $type['column_id'] . "`, m.`store_id`
        LIMIT {$start}, {$limit}
    ")->rows;

    foreach ($rows as &$row) {
      $row['lang_data'] = json_decode($row['lang_data'], true) ?? [];

      $lang_data = $row['lang_data'];
      unset($row['lang_data']);

      foreach ($lang_data as $lang) {

        $lang['description']     = mb_strlen(strip_tags(html_entity_decode($lang['description'] ?? '')));
        $lang['seo_description'] = mb_strlen(strip_tags(html_entity_decode($lang['seo_description'] ?? '')));
        $lang['faq']             = !empty($lang['faq']);
        $lang['how_to']          = !empty($lang['how_to']);
        $lang['footer']          = !empty($lang['footer']);
        $lang['seo_keywords']    = is_array($lang['seo_keywords']) ? count($lang['seo_keywords']) : (empty($lang['seo_keywords']) ? 0 : 1);
        
        $row['lang_data'][$lang['language_id']] = $lang;
      }

      $result[] = $row;
    }

    return $result;
  }

  /**
   * Get total pages count to for async progress bar
   * @param string $page_type the type of pages to be returned
   * @return int pages coun
   */
  public function getTotalPages($page_type) : int {

    if (!isset($this->types[$page_type])) {
      return 0;
    }

    $type = $this->types[$page_type];
    $result = $this->db->query("
      SELECT
        COUNT(*) AS pages_count
      FROM `" . DB_PREFIX . $type['main_table'] . "`
      WHERE `store_id` = " . (int) $this->session->data['store_id'] . "
    ")->row;

    return (int) $result['pages_count'];
  }

  /**
   * Upsert data to description table
   * @param string $type string type to be upserted
   * @param array $data array of nested language data arrays [$langId => ['h1' => 'Some H1', 'meta_title' => 'Some meta title']]
   * @return int Affecteed rows
   */
  public function savePages($data, $type) : int {
    $allowedColumns = [
      'h1',
      'meta_title',
      'meta_description',
      'meta_keyword',
      'description',
      'seo_keywords',
      'seo_description',
      'faq',
      'how_to',
      'footer',
    ];

    $types = $this->getTypes();

    if (!isset($types[$type])) {
      return 0;
    }

    $table      = DB_PREFIX . $types[$type]['description_table'];
    $entityKey  = $types[$type]['column_id'];
    $storeId    = $this->session->data['store_id'];

    // Primary key columns list
    $columns = array_merge([$entityKey, 'store_id', 'language_id']);

    $values = [];

    foreach ($data as $row) {

      // Add columns from POST to INSERT columns list
      foreach (array_keys($row) as $columnName) {
        if (in_array($columnName, $allowedColumns) && !in_array($columnName, $columns)) {
          $columns[] = $columnName;
        }
      }

      // Primary key values list 
      $rowValues = [
        $entityKey    => (int) $row[$entityKey],
        'store_id'    => (int) $storeId,
        'language_id' => (int) $row['language_id']
      ];

      // Add row values excluding primary key row values
      foreach ($columns as $column) {
        if (array_key_exists($column, $row) && !in_array($column, array_keys($rowValues))) {
          $value = $row[$column];
          // Escape values
          $rowValues[$column] = "'" . $this->db->escape($value) . "'";
        }
      }

      $values[] = "(" . implode(', ', $rowValues) . ")";
    }

    if (!$values) {
      return 0;
    }

    // UPDATE only if column value is not NULL
    $update = [];

    foreach ($columns as $column) {
      $update[] = "`{$column}` = new_data.`{$column}`";
    }

    $sql = "
      INSERT INTO `$table`
      (`" . implode('`, `', $columns) . "`)
      VALUES " . implode(', ', $values) . "
      AS new_data
      ON DUPLICATE KEY UPDATE " . implode(', ', $update) . "
    ";

    $this->log->write($sql);
    $this->db->query($sql);


    return (int)$this->db->countAffected();
  }

}
