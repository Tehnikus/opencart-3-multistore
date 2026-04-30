<?php
// class ModelCatalogSearch extends Model {

//   /**
//    * Full search index rebuild
//    */
//   public function rebuildIndex() : array {
//     $stats = [
//       'deleted' => 0,
//       'indexed' => 0,
//       'errors'  => [],
//     ];

//     $this->db->query("START TRANSACTION");

//     try {
//       // Clear index
//       // Truncate is faster than delte by row
//       $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "product_search_index`");
//       $stats['deleted'] = $this->db->countAffected();

//       // Build the whole index
//       $this->db->query($this->buildInsertQuery());
//       $stats['indexed'] = $this->db->countAffected();
//       $this->db->query("COMMIT");

//     } catch (Exception $e) {
//       $stats['errors'][] = $e->getMessage();
//       $this->db->query("ROLLBACK");
//     }

//     return $stats;
//   }

//   /**
//    * @param mixed $product_id If not isset the index will be created for all products in store with $store_id = n
//    * @param mixed $store_id If not isset the index for $product_id = n will be created for all stores
//    * If both params are not set the index will be created for all products in all stores
//    */
//   public function buildSearchIndex($product_id = null, $store_id = null) : void {
//     $where = [];
//     if ($product_id !== null) {
//       $where[] = "product_id = " . (int) $product_id;
//     }
//     if ($store_id !== null) {
//       $where[] = "store_id = " . (int) $store_id;
//     }

//     // DB Transaction
//     $this->db->query("START TRANSACTION");
//     try {
//       if ($product_id !== null) {

//         // First remove old data
//         $this->db->query("
//           DELETE FROM `" . DB_PREFIX . "product_search_index`
//           WHERE " . implode(' AND ', $where) . "
//         ");
  
//         // Then check product status
//         $result = $this->db->query("
//           SELECT 
//             `status` 
//           FROM `" . DB_PREFIX . "product_to_store`
//           WHERE " . implode(' AND ', $where) . "
//         ");

//         // If product is disabled just exit
//         if (!$result->num_rows || !$result->row['status']) {
//           return;
//         }
//       }

//       // If product is available or product_id is null build search index
//       $this->db->query($this->buildInsertQuery($product_id, $store_id));
//       // Commit DB changes
//       $this->db->query("COMMIT");
//     } catch (Exception $e) {
//       // Rollback DB changes
//       $this->db->query("ROLLBACK");
//       throw $e;
//     }
//   }

//   /**
//    * Delete product from index
//    * @param mixed $product_id If not isset the index will be deleted for all products in the store with $store_id = n
//    * @param mixed $store_id If not isset the index for $product_id = n will be deleted in all stores
//    */
//   public function deleteSearchIndex($product_id = null, $store_id = null) : void {
//     $where = [];
//     if ($product_id !== null) {
//       $where[] = "product_id = " . (int) $product_id;
//     }
//     if ($store_id !== null) {
//       $where[] = "store_id = " . (int) $store_id;
//     }
//     $this->db->query("
//       DELETE FROM `" . DB_PREFIX . "product_search_index`
//       WHERE " . implode(' AND ', $where) . "
//     ");
//   }

//   /**
//    * Main query
//    * Gets strings and writes them into fulltext indexed columns
//    * @param mixed $product_id If not isset the index will be created for all products in the store with $store_id = n
//    * @param mixed $store_id If not isset the index for $product_id = n will be created for all stores
//    * If both params are not set the index will be created for all products in all stores
//    */
  
//   private function buildInsertQuery($product_id = null, $store_id = null): string {
//     $prefix = DB_PREFIX;

//     $where = [];
//     $where[] = "p2s.`status` = 1";
//     if ($product_id !== null) {
//       $where[] = "pd.product_id = " . (int) $product_id;
//     }
//     if ($store_id !== null) {
//       $where[] = "pd.store_id = " . (int) $store_id;
//     }

//     return "
//       REPLACE INTO `{$prefix}product_search_index`
//         (`product_id`, `language_id`, `store_id`, `name`, `manufacturer`, `category`, `extra`)

//       SELECT
//         p.`product_id`,
//         pd.`language_id`,
//         p2s.`store_id`,

//         /* Product name, model, SKU */
//         REGEXP_REPLACE(
//           LOWER(
//             CONCAT_WS(' ',
//               pd.`name`,
//               NULLIF(p.`model`, ''),
//               NULLIF(p.`sku`, '')
//             )
//           ), 
//         '[^a-z0-9 ]', '') AS name,

//         /* Manufacturer */
//         COALESCE(
//           REGEXP_REPLACE(
//             LOWER(
//               (
//                 SELECT
//                   md.`name`
//                 FROM {$prefix}manufacturer_description md
//                 WHERE md.`manufacturer_id` = p.`manufacturer_id`
//                   AND md.`store_id` = p2s.`store_id`
//                   AND md.`language_id` = pd.`language_id`
//               )
//             ), 
//           '[^a-z0-9 ]', ''), ''
//         ) AS manufacturer,

//         /* Categories */
//         COALESCE(
//           REGEXP_REPLACE(
//             LOWER(
//               (
//                 SELECT 
//                   GROUP_CONCAT(DISTINCT cd.name SEPARATOR ' ')
//                 FROM {$prefix}product_to_category p2c
//                 JOIN {$prefix}category_description cd
//                   ON  cd.`category_id`  = p2c.`category_id`
//                   AND cd.`language_id`  = pd.`language_id`
//                   AND cd.`store_id`     = p2s.`store_id`
//                 WHERE p2c.`product_id`  = p2s.`product_id`
//                   AND p2c.`store_id`    = p2s.`store_id`
//               )
//             ), 
//           '[^a-z0-9 ]', ''), ''
//         ) AS category,

//         /* Extra */
//         REGEXP_REPLACE(
//           LOWER(
//             /* concat to srting skipping NULL values */
//             CONCAT_WS(' ',
//               /* Option values */
//               NULLIF(
//                 (
//                   SELECT 
//                     GROUP_CONCAT(DISTINCT ovd.name SEPARATOR ' ')
//                   FROM {$prefix}product_option po
//                   JOIN {$prefix}product_option_value pov
//                     ON  pov.`product_option_id` = po.`product_option_id`
//                     AND pov.`store_id`          = p2s.`store_id`
//                   JOIN {$prefix}option_value_description ovd
//                     ON  ovd.`option_value_id` = pov.`option_value_id`
//                     AND ovd.`language_id`     = pd.`language_id`
//                     AND ovd.`store_id`        = p2s.`store_id`
//                   WHERE po.`product_id` = p2s.`product_id`
//                     AND po.`store_id`   = p2s.`store_id`
//                 ), ''
//               ),

//               /* Attributes */
//               NULLIF(
//                 (
//                   SELECT 
//                     GROUP_CONCAT(DISTINCT ad.name SEPARATOR ' ')
//                   FROM {$prefix}attribute_description ad
//                   JOIN {$prefix}product_attribute pa
//                     ON  pa.`product_id`  = p.`product_id`
//                     AND pa.`store_id`    = p2s.`store_id`
//                   WHERE ad.`attribute_id` = pa.`attribute_id`
//                     AND ad.`language_id`  = pd.`language_id`
//                     AND ad.`store_id`     = p2s.`store_id`
//                 ), ''
//               ),

//               /* Filters */
//               NULLIF(
//                 (
//                   SELECT 
//                     GROUP_CONCAT(DISTINCT fd.name SEPARATOR ' ')
//                   FROM {$prefix}product_filter pf
//                   JOIN {$prefix}filter_description fd
//                     ON  fd.filter_id   = pf.filter_id
//                     AND fd.language_id = pd.language_id
//                     AND fd.store_id    = p2s.store_id
//                   WHERE pf.product_id = p.product_id
//                     AND pf.store_id   = p2s.store_id
//                 ), ''
//               ),

//               NULLIF(
//                 (
//                   SELECT GROUP_CONCAT(DISTINCT td.name SEPARATOR ' ')
//                     FROM {$prefix}product_seo_tag pt
//                   JOIN {$prefix}seo_tag_description td
//                     ON td.seo_tag_id = pt.seo_tag_id
//                     AND td.language_id = pd.language_id
//                     AND td.store_id = p2s.store_id
//                   WHERE pt.product_id = p.product_id
//                     AND pt.store_id   = p2s.store_id
//                 ), ''
//               )
//             )
//           ),
//         '[^a-z0-9 ]', '') AS extra

//       FROM `{$prefix}product` p
//       JOIN `{$prefix}product_description` pd
//         ON pd.product_id = p.product_id
//       JOIN `{$prefix}product_to_store` p2s
//         ON p2s.product_id = p.product_id

//       WHERE " . implode(' AND ', $where) . "
//     ";
//   }
// }


class ModelCatalogSearch extends Model {

  // Batch size for index rebuild so memory won't overflow
  private const BATCH_SIZE = 100;

  /**
   * Full index rebuild
   */
  public function rebuildIndex(): array {
    $stats = ['indexed' => 0, 'errors' => []];

    $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "product_search_index`");

    $offset = 0;
    do {
      $rows = $this->fetchRawData(null, null, self::BATCH_SIZE, $offset);
      if (empty($rows)) break;

      $this->insertNormalized($rows, $stats);
      $offset += self::BATCH_SIZE;

    } while (count($rows) === self::BATCH_SIZE);

    return $stats;
  }

  /**
   * Add product to search index
   */
  public function addProduct(int $product_id, int $store_id): void {
    $this->db->query("
      DELETE FROM `" . DB_PREFIX . "product_search_index`
      WHERE product_id = '" . $product_id . "'
    ");

    $status = $this->db->query("
      SELECT status FROM `" . DB_PREFIX . "product`
      WHERE product_id = '" . $product_id . "'
    ");

    if (!$status->num_rows || !$status->row['status']) {
      return;
    }

    $stats  = ['indexed' => 0, 'errors' => []];
    $rows   = $this->fetchRawData($product_id, $store_id);
    $this->insertNormalized($rows, $stats);
  }

  /**
   * Remove product from index
   */
  public function deleteProduct(int $product_id): void {
    $this->db->query("
      DELETE FROM `" . DB_PREFIX . "product_search_index`
      WHERE product_id = '" . $product_id . "'
    ");
  }

  /**
   * Normalize strings before insert in index
   * @param string $text: The string to be normalized
   * @param bool $deduplicate: The flag to deduplicate string (leave only unique words)
   * Pipeline:
   *  1. lowercase
   *  2. New line and tab symbols replaced by spaces
   *  3. Hyphens, dashes and periods: make duplicate word with and without them
   *  4. Remove junk chars excluding hyphens, dashes and periods
   *  5. Replace sequential spaces with single
   *  6. Deduplicate words (used for extra). Reduces index length and makes search ranking more even
   */
  public function normalize(string $text, bool $deduplicate = false): string {
    // 1. Lowercase support Unicode chars
    $text = mb_strtolower($text, 'UTF-8');

    // 2. New lines and tabs to space
    $text = preg_replace('/[\r\n\t]+/', ' ', $text);

    // 3. Make duplicate with and without hyphens, dashes and periods
    $text = preg_replace_callback(
      '/[a-z0-9]+(?:[-\.][a-z0-9]+)+/',
      function (array $m): string {
        $stripped = preg_replace('/[-\.]/', '', $m[0]);
        return $m[0] !== $stripped
          ? $m[0] . ' ' . $stripped
          : $m[0];
      },
      $text
    );

    // 4. Cleanup junk chars except hyphens, dashes and periods
    // \p{L} Any Unicode letter 
    // \p{N} Any number
    $text = preg_replace('/[^\p{L}\p{N} \-\.]/u', '', $text);

    // 5. Cleanup sequential spaces
    $text = preg_replace('/\s{2,}/', ' ', $text);
    $text = trim($text);

    // 6. Deduplicate words (for extra)
    if ($deduplicate) {
      $words = explode(' ', $text);
      $text  = implode(' ', array_unique($words));
    }

    return $text;
  }

  /**
   * Select raw data from DB
   * @param int|null $product_id  If null, all products data will be selected
   * @param int|null $store_id    If null, all stores data will be selected
   * @param int $limit Limits to select data in batches
   * @param int $offset Limits to select data in batches
   * @return array
   */
  private function fetchRawData(?int $product_id = null, ?int $store_id = null, int  $limit = self::BATCH_SIZE, int  $offset = 0) : array {
    $prefix = DB_PREFIX;

    $where   = [];
    $where[] = "p.`status` = 1";

    if ($product_id !== null) {
      $where[] = "p.`product_id` = " . $product_id;
    }
    if ($store_id !== null) {
      $where[] = "p2s.`store_id` = " . $store_id;
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "
      SELECT
        p.`product_id`,
        pd.`language_id`,
        p2s.`store_id`,

        /* Name, model, sku */
        CONCAT_WS(' ',
          pd.`name`,
          NULLIF(p.`model`, ''),
          NULLIF(p.`sku`, '')
        ) AS name,

        /* Manufacturer */
        COALESCE(
          (SELECT
            md.`name`
          FROM {$prefix}manufacturer_description md
          WHERE md.`manufacturer_id` = p.`manufacturer_id`
            AND md.`store_id` = p2s.`store_id`
            AND md.`language_id` = pd.`language_id`),
        '') AS manufacturer,

        /* Categories */
        COALESCE(
          (SELECT GROUP_CONCAT(DISTINCT cd.`name` SEPARATOR ' ')
            FROM `{$prefix}product_to_category` p2c
            JOIN `{$prefix}category_description` cd
              ON  cd.`category_id` = p2c.`category_id`
              AND cd.`language_id` = pd.`language_id`
              AND cd.`store_id`    = p2s.`store_id`
            WHERE p2c.`product_id` = p.`product_id`
          ), ''
        ) AS category,

        /* Extra: options, attributes, filters, SEO tags */
        CONCAT_WS(' ',
          NULLIF(
            (SELECT GROUP_CONCAT(DISTINCT ovd.`name` SEPARATOR ' ')
              FROM `{$prefix}product_option` po
              JOIN `{$prefix}product_option_value` pov
                ON  pov.`product_option_id` = po.`product_option_id`
                AND pov.`store_id` = p2s.`store_id`
              JOIN `{$prefix}option_value_description` ovd
                ON  ovd.`option_value_id` = pov.`option_value_id`
                AND ovd.`language_id`     = pd.`language_id`
                AND ovd.`store_id`        = p2s.`store_id`
              WHERE po.`product_id` = p.`product_id`
            ), 
          ''),

          NULLIF(
            (SELECT GROUP_CONCAT(DISTINCT ad.name SEPARATOR ' ')
            FROM {$prefix}attribute_description ad
            JOIN {$prefix}product_attribute pa
              ON  pa.`product_id`  = p.`product_id`
              AND pa.`store_id`    = p2s.`store_id`
            WHERE ad.`attribute_id` = pa.`attribute_id`
              AND ad.`language_id`  = pd.`language_id`
              AND ad.`store_id`     = p2s.`store_id`
            ), 
          ''),

          NULLIF(
            (SELECT GROUP_CONCAT(DISTINCT fd.name SEPARATOR ' ')
            FROM {$prefix}product_filter pf
            JOIN {$prefix}filter_description fd
              ON  fd.filter_id   = pf.filter_id
              AND fd.language_id = pd.language_id
              AND fd.store_id    = p2s.store_id
            WHERE pf.product_id = p.product_id
              AND pf.store_id   = p2s.store_id
            ), 
          ''),

          NULLIF(
            (SELECT GROUP_CONCAT(DISTINCT td.name SEPARATOR ' ')
              FROM {$prefix}product_seo_tag pt
            JOIN {$prefix}seo_tag_description td
              ON  td.seo_tag_id = pt.seo_tag_id
              AND td.language_id = pd.language_id
              AND td.store_id = p2s.store_id
            WHERE pt.product_id = p.product_id
              AND pt.store_id   = p2s.store_id
            ), 
          '')

        ) AS extra

      FROM `{$prefix}product` p
      JOIN `{$prefix}product_description` pd
        ON pd.`product_id` = p.`product_id`
      JOIN `{$prefix}product_to_store` p2s
        ON p2s.`product_id` = p.`product_id`

      WHERE {$whereSQL}
      LIMIT {$limit} OFFSET {$offset}
    ";

    return $this->db->query($sql)->rows;
  }

  /**
   * Insert normalized strings
   * @param array $rows Array of rows to be inserted
   * @param array $stats Array to cut normalized data in batches and accumulate errors
   * @return void
   */
  private function insertNormalized(array $rows, array &$stats) : void {
    if (empty($rows)) return;

    $prefix = DB_PREFIX;
    $values = [];

    foreach ($rows as $row) {
      $product_id  = (int)$row['product_id'];
      $language_id = (int)$row['language_id'];
      $store_id    = (int)$row['store_id'];

      // Normalize inserted strings
      $name         = $this->db->escape($this->normalize($row['name']));
      $manufacturer = $this->db->escape($this->normalize($row['manufacturer']));
      $category     = $this->db->escape($this->normalize($row['category']));
      $extra        = $this->db->escape($this->normalize($row['extra'], true)); // extra: deduplicate words = true

      $values[] = "({$product_id}, {$language_id}, {$store_id}, '{$name}', '{$manufacturer}', '{$category}', '{$extra}')";
    }

    // One INSERT on the whole batch
    $this->db->query("
      REPLACE INTO `{$prefix}product_search_index`
        (`product_id`, `language_id`, `store_id`, `name`, `manufacturer`, `category`, `extra`)
      VALUES " . implode(', ', $values)
    );

    $stats['indexed'] += count($rows);
  }
}