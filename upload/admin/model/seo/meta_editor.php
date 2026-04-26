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
    $type = $filter['type'];

    switch ($type) {
      case 'product':
       $sql = $this->productRequest($filter);
        break;
      
      default:
        $sql = $this->categoryRequest($filter);
        break;
    }

    $this->log->write($sql);

    $rows = $this->db->query($sql)->rows;

    foreach ($rows as $row) {
      $row['vars']      = json_decode($row['vars'], true) ?? [];
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


  private function categoryRequest($filter) : string {

    $type         = $this->types[$filter['type']];
    $limit        = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 100));
    $start        = max(0, (int) ($filter['start'] ?? 0));
    $currentLang  = (int) $this->config->get('config_language_id'); // Current admin language id
    $currentStore = (int) $this->session->data['store_id']; // Current store id

    $sql = "
      SELECT
        (
          SELECT JSON_OBJECT(
            'price',      AVG(fs.current_price),
            'minPrice',   MIN(fs.current_price),
            'maxPrice',   MAX(fs.current_price),
            'discount',   GREATEST(fs.current_price - pd.price, fs.current_price - ps.price),
            'rating',     AVG(fs.rating_avg),
            'reviews',    SUM(fs.review_count),
            'offers',     COUNT(fs.product_id)
          )
          FROM " . DB_PREFIX . "product_to_category p2c
          JOIN " . DB_PREFIX . "facet_sort fs 
            ON fs.product_id = p2c.product_id
            AND fs.store_id = p2c.store_id
          LEFT JOIN " . DB_PREFIX . "product_discount pd 
            ON  pd.product_id = p2c.product_id
            AND pd.store_id   = p2c.store_id
          LEFT JOIN " . DB_PREFIX . "product_special ps 
            ON  ps.product_id = p2c.product_id
            AND ps.store_id   = p2c.store_id
          WHERE p2c.category_id = m.`" . $type['column_id'] . "`
            AND p2c.store_id    = m.store_id 
            AND fs.current_price > 0
        ) AS vars,

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
    ";

    return $sql;
  }

  private function productRequest($filter) : string {
    $type         = $this->types[$filter['type']];
    $limit        = max(1, (int) ($filter['limit'] ?? $this->config->get('config_limit_admin') ?? 100));
    $start        = max(0, (int) ($filter['start'] ?? 0));
    $currentLang  = (int) $this->config->get('config_language_id'); // Current admin language id
    $currentStore = (int) $this->session->data['store_id']; // Current store id

    $sql = "

      -- Pagination
      WITH paged_ids AS (
        SELECT `" . $type['column_id'] . "` AS product_id
        FROM `" . DB_PREFIX . $type['main_table'] . "`
        WHERE store_id = {$currentStore}
        LIMIT {$start}, {$limit}
      ),

      option_impacts AS (
        SELECT
          pov.product_id,
          pov.store_id,
          pov.option_id,
          o.type,
          po.required,
          CASE WHEN pov.price_prefix = '-' THEN -pov.price ELSE pov.price END AS price_impact
        FROM `" . DB_PREFIX . "product_option_value` pov
        JOIN `" . DB_PREFIX . "option` o          ON o.option_id          = pov.option_id
        JOIN `" . DB_PREFIX . "product_option` po  ON po.product_option_id = pov.product_option_id
        -- Join paginated product ids
        JOIN paged_ids ON paged_ids.product_id = pov.product_id
        WHERE pov.store_id = {$currentStore}
      ),

      option_minmax AS (
        SELECT
          option_id,
          product_id,
          store_id,
          CASE
            WHEN type IN ('radio', 'select') THEN
              CASE
                WHEN MAX(required) = 1 THEN MIN(price_impact)
                ELSE LEAST(0, MIN(price_impact))
              END
            WHEN type = 'checkbox' THEN
              COALESCE(SUM(CASE WHEN price_impact < 0 THEN price_impact ELSE 0 END), 0)
            ELSE 0
          END AS min_impact,
          CASE
            WHEN type IN ('radio', 'select') THEN MAX(price_impact)
            WHEN type = 'checkbox'           THEN COALESCE(SUM(CASE WHEN price_impact > 0 THEN price_impact ELSE 0 END), 0)
            ELSE 0
          END AS max_impact
        FROM option_impacts
        GROUP BY product_id, store_id, option_id, type
      ),

      product_price_ranges AS (
        SELECT
          product_id,
          store_id,
          COALESCE(SUM(min_impact), 0) AS total_min_impact,
          COALESCE(SUM(max_impact), 0) AS total_max_impact
        FROM option_minmax
        GROUP BY product_id, store_id
      )

      SELECT
        m.`" . $type['column_id'] . "` AS column_id,
        COALESCE(
          MAX(CASE WHEN d.language_id = {$currentLang} AND d.store_id = {$currentStore} THEN d.name END),
          MAX(CASE WHEN d.language_id = {$currentLang} THEN d.name END),
          MAX(d.name)
        ) AS default_name,
        JSON_OBJECT(
          'price',    fs.current_price,
          'minPrice', fs.current_price + COALESCE(ppr.total_min_impact, 0),
          'maxPrice', fs.current_price + COALESCE(ppr.total_max_impact, 0),
          'discount', GREATEST(COALESCE(fs.current_price - pd.price, 0), COALESCE(fs.current_price - ps.price, 0)),
          'rating',   fs.rating_avg,
          'reviews',  fs.review_count,
          'offers',   1
        ) AS vars,
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
      -- Join paginated product ids
      JOIN paged_ids ON paged_ids.product_id = m.`" . $type['column_id'] . "`

      LEFT JOIN `" . DB_PREFIX . $type['description_table'] . "` d
        ON  d.`" . $type['column_id'] . "` = m.`" . $type['column_id'] . "`
        AND d.`store_id` = m.`store_id`

      LEFT JOIN `" . DB_PREFIX . "facet_sort` fs
        ON  fs.product_id = m.`" . $type['column_id'] . "`
        AND fs.store_id   = m.`store_id`

      LEFT JOIN `" . DB_PREFIX . "product_discount` pd
        ON  pd.product_id = m.`" . $type['column_id'] . "`
        AND pd.store_id   = m.`store_id`

      LEFT JOIN `" . DB_PREFIX . "product_special` ps
        ON  ps.product_id = m.`" . $type['column_id'] . "`
        AND ps.store_id   = m.`store_id`

      LEFT JOIN product_price_ranges ppr
        ON  ppr.product_id = m.`" . $type['column_id'] . "`
        AND ppr.store_id   = m.`store_id`

      WHERE m.`store_id` = {$currentStore}
      GROUP BY m.`" . $type['column_id'] . "`, m.`store_id`, fs.current_price, fs.rating_avg, fs.review_count
    ";

    return $sql;
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

    $this->db->query($sql);

    return (int)$this->db->countAffected();
  }

}
