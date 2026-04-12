<?php
Class ModelCatalogFacet extends Model {

  public $facetTypes = [];

  public function __construct($registry) {
    parent::__construct($registry);

    $this->facetTypes = [
			'category_id'   		=> 1,
			'filter'        		=> 2,
			'option'        		=> 3,
			'attribute'     		=> 4,
			'manufacturer_id'	  => 5,
			'tag'               => 6,
			'supplier_id'       => 7,
			'is_available'  		=> 8,
			'has_discount'  		=> 9,
			'is_featured'   		=> 10,
		];
  }

  public function getFacetTypes() : array {
    return $this->facetTypes;
  }

  public function buildFacetIndex($product_id = null, $facet_value_id = null, $facet_group_id = null, $facet_type = null, $store_id = null) : void {
    $where = [];
    $where[] = "1";
    if ($product_id !== null) {
      $where[] = "src.product_id = " . (int) $product_id . "";
    }
    if ($store_id !== null) {
      $where[] = "src.store_id = " . (int) $store_id . "";
    }
    if ($facet_value_id !== null) {
      $where[] = "src.facet_value_id = " . (int) $facet_value_id . "";
    }
    if ($facet_group_id !== null) {
      $where[] = "src.facet_group_id = " . (int) $facet_group_id . "";
    }
    if ($facet_type !== null) {
      $where[] = "src.facet_type = " . (int) $facet_type . "";
    }

    $this->db->query("
      DELETE FROM " . DB_PREFIX . "facet_index src
      WHERE " . implode(" AND ", $where) . "
    ");

    $sql = "

      INSERT INTO " . DB_PREFIX . "facet_index
      (`product_id`, `store_id`, `facet_value_id`, `facet_group_id`, `facet_type`)

      SELECT
        src.`product_id`,
        src.`store_id`,
        src.`facet_value_id`,
        src.`facet_group_id`,
        src.`facet_type`
      FROM (

        /* CATEGORIES */
        SELECT
          p2c.`product_id`            AS `product_id`,
          p2c.`store_id`              AS `store_id`,
          p2c.`category_id`           AS `facet_value_id`,
          COALESCE(c2s.parent_id, 0)  AS `facet_group_id`,
          1                           AS `facet_type`
        FROM " . DB_PREFIX . "product_to_category p2c
        LEFT JOIN " . DB_PREFIX . "category_to_store c2s
          ON c2s.`category_id` = p2c.`category_id`
          AND c2s.`store_id`   = p2c.`store_id`

        UNION ALL

        /* FILTERS */
        SELECT
          pf.`product_id`       AS `product_id`,
          pf.`store_id`         AS `store_id`,
          pf.`filter_id`        AS `facet_value_id`,
          pf.`filter_group_id`  AS `facet_group_id`,
          2                     AS `facet_type`
        FROM " . DB_PREFIX . "product_filter pf

        UNION ALL

        /* OPTIONS */
        SELECT
          pov.`product_id`      AS `product_id`,
          pov.`store_id`        AS `store_id`,
          pov.`option_value_id` AS `facet_value_id`,
          pov.`option_id`       AS `facet_group_id`,
          3                     AS `facet_type`
        FROM " . DB_PREFIX . "product_option_value pov

        UNION ALL

        /* ATTRIBUTES */
        SELECT
          pa.`product_id`         AS `product_id`,
          pa.`store_id`           AS `store_id`,
          pa.`attribute_id`       AS `facet_value_id`,
          pa.`attribute_group_id` AS `facet_group_id`,
          4                       AS `facet_type`
        FROM " . DB_PREFIX . "product_attribute pa

        UNION ALL

        /* MANUFACTURER */
        SELECT
          p.`product_id`          AS `product_id`,
          p2s.`store_id`          AS `store_id`,
          p.`manufacturer_id`     AS `facet_value_id`,
          0                       AS `facet_group_id`,
          5                       AS `facet_type`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`
        WHERE p.`manufacturer_id` <> 0

        UNION ALL

        /* SEO TAGS */
        SELECT
          pst.`product_id`        AS `product_id`,
          pst.`store_id`          AS `store_id`,
          pst.`seo_tag_id`        AS `facet_value_id`,
          0                       AS `facet_group_id`,
          6                       AS `facet_type`
        FROM " . DB_PREFIX . "product_seo_tag pst
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON pst.`product_id` = p2s.`product_id`

        UNION ALL

        /* SUPPLIER */
        SELECT
          p.`product_id`          AS `product_id`,
          p2s.`store_id`          AS `store_id`,
          p.`supplier_id`         AS `facet_value_id`,
          0                       AS `facet_group_id`,
          7                       AS `facet_type`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`
        WHERE p.`supplier_id` <> 0

        UNION ALL

        /* AVAILABILITY */
        SELECT
          p.`product_id`          AS `product_id`,
          p2s.`store_id`          AS `store_id`,
          p2s.`is_available`      AS `facet_value_id`,
          0                       AS `facet_group_id`,
          8                       AS `facet_type`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`

        UNION ALL

        /* DISCOUNT */
        SELECT
          p.`product_id`          AS `product_id`,
          p2s.`store_id`          AS `store_id`,
          1                       AS `facet_value_id`,
          0                       AS `facet_group_id`,
          9                       AS `facet_type`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`
        WHERE (
          EXISTS(
            SELECT 1
            FROM " . DB_PREFIX . "product_special ps
            WHERE ps.`product_id`   = p.`product_id`
              AND ps.`store_id`     = p2s.`store_id`
              AND (ps.`date_start`  = '0000-00-00' OR ps.date_start < NOW())
              AND (ps.`date_end`    = '0000-00-00' OR ps.date_end   > NOW())
          )
          OR
          EXISTS(
            SELECT 1
            FROM " . DB_PREFIX . "product_discount pd
            WHERE pd.`product_id`   = p.`product_id`
              AND pd.`store_id`     = p2s.`store_id`
              AND (pd.`date_start`  ='0000-00-00' OR pd.`date_start` < NOW())
              AND (pd.`date_end`    ='0000-00-00' OR pd.`date_end`   > NOW())
          )
        )

        UNION ALL

        /* FEATURED */
        SELECT
          p.`product_id`      AS `product_id`,
          p2s.`store_id`      AS `store_id`,
          p2s.`is_featured`   AS `facet_value_id`,
          0                   AS `facet_group_id`,
          10                  AS `facet_type`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`
        WHERE p2s.`is_featured` <> 0
      ) src

      JOIN " . DB_PREFIX . "product_to_store p2s
        ON p2s.`product_id` = src.`product_id`
        AND p2s.`store_id` = src.`store_id`
        AND p2s.`status` = 1
      WHERE " . implode(" AND ", $where) . "
    ";

    $this->db->query($sql);

    $this->db->query("ANALYZE TABLE " . DB_PREFIX . "facet_index");
    $this->db->query("FLUSH TABLE " . DB_PREFIX . "facet_index");

  }

  public function buildFacetSorts($product_id = null, $store_id = null) : void {
    $where = [];
    $where[] = "1";
    if ($product_id !== null) {
      $where[] = "src.product_id = " . (int) $product_id . "";
    }
    if ($store_id !== null) {
      $where[] = "src.store_id = " . (int) $store_id . "";
    }

    $this->db->query("
      INSERT INTO " . DB_PREFIX . "facet_sort (
        `product_id`,
        `store_id`,
        `orders`,
        `returns`,
        `sort_order`,
        `review_count`,
        `rating_avg`,
        `date_last_review`,
        `current_price`,
        `is_available`,
        `is_featured`,
        `has_discount`,
        `date_added`,
        `date_last_order`
      )

      SELECT
        src.`product_id`,
        src.`store_id`,
        src.`orders`,
        src.`returns`,
        src.`sort_order`,
        src.`review_count`,
        src.`rating_avg`,
        src.`date_last_review`,
        src.`current_price`,
        src.`is_available`,
        src.`is_featured`,
        src.`has_discount`,
        src.`date_added`,
        src.`date_last_order`
      FROM (
      
        SELECT
          p.`product_id`                  AS `product_id`,
          p2s.`store_id`                  AS `store_id`,
          COALESCE(o.`orders`, 0)         AS `orders`,
          0                               AS `returns`,
          p2s.`sort_order`                AS `sort_order`,
          COALESCE(r.`review_count`, 0)   AS `review_count`,
          COALESCE(r.`rating_avg`, 0)     AS `rating_avg`,
          r.`date_last_review`            AS `date_last_review`,
          p2s.`is_available`              AS `is_available`,
          p2s.`is_featured`               AS `is_featured`,
          p.`date_added`                  AS `date_added`,
          o.`date_last_order`             AS `date_last_order`,
          COALESCE(ps.`price`, pd.`price`, p2s.`price`, p.`price`)    AS `current_price`,
          IF(ps.`price` IS NOT NULL OR pd.`price` IS NOT NULL, 1, 0)  AS `has_discount`
        FROM " . DB_PREFIX . "product p
        JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.`product_id` = p.`product_id`
      
        LEFT JOIN (
          SELECT
            op.`product_id`,
            o.`store_id`,
            SUM(op.`quantity`)  AS `orders`,
            MAX(o.`date_added`) AS `date_last_order`
          FROM " . DB_PREFIX . "order_product op
          JOIN " . DB_PREFIX . "order o
            ON o.`order_id` = op.`order_id`
          GROUP BY
            op.`product_id`,
            o.`store_id`
        ) o
        ON o.`product_id` = p.`product_id`
        AND o.`store_id` = p2s.`store_id`
      
        LEFT JOIN (
          SELECT
            r.`product_id`,
            r.`store_id`,
            COUNT(*)            AS `review_count`,
            AVG(r.`rating`)     AS `rating_avg`,
            MAX(r.`date_added`) AS date_last_review
          FROM " . DB_PREFIX . "review r
          WHERE r.`status` = 1
          GROUP BY
            r.`product_id`,
            r.`store_id`
        ) r
        ON r.`product_id` = p.`product_id`
        AND r.`store_id` = p2s.`store_id`
      
        LEFT JOIN (
          SELECT
            t.product_id,
            t.store_id,
            MIN(t.price) AS price
          FROM " . DB_PREFIX . "product_special t
          JOIN (
            SELECT
              product_id,
              store_id,
              MIN(priority) AS priority
            FROM " . DB_PREFIX . "product_special
            WHERE
              (date_start = '0000-00-00' OR date_start < NOW())
              AND (date_end = '0000-00-00' OR date_end > NOW())
            GROUP BY product_id, store_id
          ) ps_priority
            ON ps_priority.product_id = t.product_id
            AND ps_priority.store_id = t.store_id
            AND ps_priority.priority = t.priority
          WHERE
            (t.date_start = '0000-00-00' OR t.date_start < NOW())
            AND (t.date_end = '0000-00-00' OR t.date_end > NOW())
          GROUP BY t.product_id, t.store_id
        ) ps
        ON ps.product_id = p.product_id
        AND ps.store_id = p2s.store_id
      
        LEFT JOIN (
          SELECT
            t.product_id,
            t.store_id,
            MIN(t.price) AS price
          FROM " . DB_PREFIX . "product_discount t
          JOIN (
            SELECT
              product_id,
              store_id,
              MIN(priority) AS priority
            FROM " . DB_PREFIX . "product_discount
            WHERE
              (date_start = '0000-00-00' OR date_start < NOW())
              AND (date_end = '0000-00-00' OR date_end > NOW())
            GROUP BY product_id, store_id
          ) pd_priority
            ON pd_priority.product_id = t.product_id
            AND pd_priority.store_id  = t.store_id
            AND pd_priority.priority  = t.priority
          WHERE
            (t.date_start = '0000-00-00' OR t.date_start < NOW())
            AND (t.date_end = '0000-00-00' OR t.date_end > NOW())
          GROUP BY t.product_id, t.store_id
        ) pd
        ON pd.product_id = p.product_id
        AND pd.store_id = p2s.store_id
        
        WHERE p2s.`status` = 1
      ) src
      WHERE " . implode(" AND ", $where) . "

      ON DUPLICATE KEY UPDATE
        orders              = src.orders,
        review_count        = src.review_count,
        rating_avg          = src.rating_avg,
        current_price       = src.current_price,
        is_available        = src.is_available,
        is_featured         = src.is_featured,
        has_discount        = src.has_discount,
        sort_order          = src.sort_order,
        date_last_order     = src.date_last_order,
        date_last_review    = src.date_last_review,
        date_added          = src.date_added
    ");

    // Cleanup trash entries
    $this->db->query("
      DELETE fs
      FROM " . DB_PREFIX . "facet_sort fs
      LEFT JOIN " . DB_PREFIX . "product_to_store p2s
        ON p2s.product_id = fs.product_id
        AND p2s.store_id = fs.store_id
      WHERE p2s.product_id IS NULL
        OR p2s.status = 0
    ");

    $this->db->query("ANALYZE TABLE " . DB_PREFIX . "facet_sort");
    $this->db->query("FLUSH TABLE " . DB_PREFIX . "facet_sort");

  }

  public function buildFacetNames($facet_value_id = null, $facet_group_id = null, $facet_type = null, $store_id = null) : void {
    $where = [];

    $where[] = "1";
    if ($facet_value_id !== null) {
      $where[] = "src.facet_value_id = " . (int) $facet_value_id . "";
    }
    if ($facet_group_id !== null) {
      $where[] = "src.facet_group_id = " . (int) $facet_group_id . "";
    }
    if ($facet_type !== null) {
      $where[] = "src.facet_type = " . (int) $facet_type . "";
    }
    if ($store_id !== null) {
      $where[] = "src.store_id = " . (int) $store_id . "";
    }

    $this->db->query("
      DELETE FROM " . DB_PREFIX . "facet_name src
      WHERE " . implode(" AND ", $where) . ""
    );

    $sql = "
      INSERT INTO " . DB_PREFIX . "facet_name
      (`name`, `group_name`, `facet_type`, `facet_value_id`, `facet_group_id`, `language_id`, `store_id`, `sort_order`, `group_sort_order`)

      SELECT
        src.`name`,
        src.`group_name`,
        src.`facet_type`,
        src.`facet_value_id`,
        src.`facet_group_id`,
        src.`language_id`,
        src.`store_id`,
        src.`sort_order`,
        src.`group_sort_order`

      FROM (

        SELECT
          1 AS `facet_type`,
          cd.`name`,
          (SELECT cd2.`name` FROM " . DB_PREFIX . "category_description cd2 WHERE cd2.`category_id` = c.`parent_id` AND cd2.`language_id` = cd.`language_id` AND cd2.`store_id` = c2s.`store_id`) AS `group_name`,
          c.`category_id` AS `facet_value_id`,
          c.`parent_id` AS `facet_group_id`,
          cd.`language_id`,
          c2s.`store_id`,
          c2s.`sort_order`,
          0 AS group_sort_order
        FROM " . DB_PREFIX . "category c
        JOIN " . DB_PREFIX . "category_to_store c2s 
          ON c2s.`category_id` = c.`category_id`
        JOIN " . DB_PREFIX . "category_description cd 
          ON cd.`category_id` = c.`category_id`
          AND cd.`store_id` = c2s.`store_id`

        UNION ALL

        SELECT
          2 AS `facet_type`,
          fd.`name`,
          fgd.`name` AS `group_name`,
          f.filter_id AS `facet_value_id`,
          f.`filter_group_id` AS `facet_group_id`,
          fd.`language_id`,
          f.`store_id`,
          f.`sort_order`,
          f2s.`sort_order` AS `group_sort_order`
        FROM " . DB_PREFIX . "filter f
        JOIN " . DB_PREFIX . "filter_description fd 
          ON  fd.`filter_id` = f.`filter_id`
          AND fd.`store_id`  = f.`store_id`
        JOIN " . DB_PREFIX . "filter_group_to_store f2s 
          ON  f2s.`filter_group_id` = f.`filter_group_id`
          AND f2s.`store_id`        = f.`store_id`
        JOIN " . DB_PREFIX . "filter_group_description fgd 
          ON fgd.filter_group_id  = f.filter_group_id
          AND fgd.`store_id`      = f.`store_id`
          AND fgd.`language_id`   = fd.`language_id`
      
        UNION ALL
      
        SELECT
          3 AS `facet_type`,
          ovd.`name`,
          od.`name` AS `group_name`,
          ov.`option_value_id` AS `facet_value_id`,
          o.option_id AS `facet_group_id`,
          ovd.`language_id`,
          ov.`store_id`,
          ov.`sort_order`,
          o2s.`sort_order` AS `group_sort_order`
        FROM " . DB_PREFIX . "option_value ov
        JOIN " . DB_PREFIX . "option o 
          ON o.`option_id` = ov.`option_id`
        JOIN " . DB_PREFIX . "option_to_store o2s 
          ON  o2s.`option_id`  = o.`option_id`
          AND o2s.store_id     = ov.store_id
        JOIN " . DB_PREFIX . "option_value_description ovd 
          ON  ovd.option_value_id  = ov.option_value_id
          AND ovd.store_id        = ov.store_id
        JOIN " . DB_PREFIX . "option_description od 
          ON  od.`option_id`     = o.`option_id`
          AND od.`store_id`      = ov.`store_id`
          AND od.`language_id`   = ovd.`language_id`
      
        UNION ALL
      
        SELECT
          4 AS `facet_type`,
          ad.`name`,
          agd.`name` AS `group_name`,
          a.`attribute_id` AS `facet_value_id`,
          a.`attribute_group_id` AS `facet_group_id`,
          ad.`language_id`,
          ats.`store_id`,
          ats.`sort_order`,
          agts.`sort_order` AS `group_sort_order`
        FROM " . DB_PREFIX . "attribute a
        JOIN " . DB_PREFIX . "attribute_to_store ats 
          ON ats.`attribute_id` = a.`attribute_id`
        JOIN " . DB_PREFIX . "attribute_description ad 
          ON ad.`attribute_id` = a.`attribute_id`
          AND ad.`store_id` = ats.`store_id`
        JOIN " . DB_PREFIX . "attribute_group_to_store agts 
          ON agts.`attribute_group_id` = a.`attribute_group_id`
          AND agts.store_id = ats.store_id
        JOIN " . DB_PREFIX . "attribute_group_description agd 
          ON agd.`attribute_group_id` = a.`attribute_group_id`
          AND agd.`store_id` = ats.`store_id`
          AND agd.`language_id` = ad.`language_id`
      
        UNION ALL
      
        SELECT 
          5 AS `facet_type`,
          md.`name` AS `name`,
          NULL AS `group_name`,
          m.`manufacturer_id` AS `facet_value_id`,
          0 AS `facet_group_id`,
          md.`language_id` AS `language_id`,
          m2s.`store_id` AS `store_id`,
          m.`sort_order` AS `sort_order`,
          0 AS `group_sort_order`
        FROM " . DB_PREFIX . "manufacturer m
        JOIN " . DB_PREFIX . "manufacturer_to_store m2s
          ON m2s.`manufacturer_id` = m.`manufacturer_id`
        JOIN " . DB_PREFIX . "manufacturer_description md
          ON md.`manufacturer_id` = m.`manufacturer_id`
          AND md.`store_id` = m2s.`store_id`

        UNION ALL

        SELECT 
          6 AS `facet_type`,
          td.`name` AS `name`,
          NULL AS `group_name`,
          td.`seo_tag_id` AS `facet_value_id`,
          0 AS `facet_group_id`,
          td.`language_id` AS `language_id`,
          td.`store_id` AS `store_id`,
          0 AS `sort_order`,
          0 AS `group_sort_order`
        FROM " . DB_PREFIX . "seo_tag_description td
        JOIN " . DB_PREFIX . "seo_tag_to_store t2s
          ON  t2s.`seo_tag_id` = td.`seo_tag_id`
          AND t2s.`store_id`   = td.`store_id`
      
      ) src

      WHERE " . implode(" AND ", $where) . "

      GROUP BY 
        src.`facet_type`,
        src.`facet_group_id`,
        src.`facet_value_id`,
        src.`store_id`,
        src.`language_id`

    ";

    $this->db->query($sql);
    $this->db->query("ANALYZE TABLE " . DB_PREFIX . "facet_name");
    $this->db->query("FLUSH TABLE " . DB_PREFIX . "facet_name");

  }

  public function cleanupFacetIndex(?int $store_id = null): void {

    $storeWhere = $store_id !== null ? "AND fi.store_id = " . (int)$store_id : "";

    // 1. Delete removed products or those that are turned off (not displayed)
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "product_to_store p2s
          ON p2s.product_id = fi.product_id
          AND p2s.store_id = fi.store_id
        WHERE (p2s.product_id IS NULL OR p2s.status = 0)
        $storeWhere
    ");

    // 2. Delete categories
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "category_to_store c2s
          ON c2s.category_id = fi.facet_value_id
          AND c2s.store_id fi.store_id
        WHERE fi.facet_type = 1
        AND (c2s.category_id IS NULL OR c2s.status = 0)
        $storeWhere
    ");

    // 3. Filters
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "filter f
          ON f.filter_id = fi.facet_value_id
          AND f.store_id = fi.store_id
        WHERE fi.facet_type = 2
        AND f.filter_id IS NULL
        $storeWhere
    ");

    // 4. Option values
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "option_value ov
          ON ov.option_value_id = fi.facet_value_id
          AND ov.store_id = fi.store_id
        WHERE fi.facet_type = 3
        AND ov.option_value_id IS NULL
        $storeWhere
    ");

    // 5. Attributes
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "attribute_to_store a
          ON a.attribute_id = fi.facet_value_id
          AND a.store_id = fi.store_id
        WHERE fi.facet_type = 4
        AND a.attribute_id IS NULL
        $storeWhere
    ");

    // 5. Manufacturers
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m
          ON m.manufacturer_id = fi.facet_value_id
          AND m.store_id = fi.store_id
        WHERE fi.facet_type = 5
        AND m.manufacturer_id IS NULL
        $storeWhere
    ");

    // 6. SEO tags
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "seo_tag_to_store st
          ON st.seo_tag_id = fi.facet_value_id
          AND st.store_id = fi.store_id
        WHERE fi.facet_type = 6
        AND st.seo_tag_id IS NULL
        $storeWhere
    ");

    // 7. Suppliers (TODO...)
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        LEFT JOIN " . DB_PREFIX . "supplier_to_store s
          ON s.supplier_id = fi.facet_value_id
          AND s.store_id = fi.store_id
        WHERE fi.facet_type = 7
        AND s.supplier_id IS NULL
        $storeWhere
    ");

    // 8. Discounts (if products doesn't have discount)
    $this->db->query("
        DELETE fi
        FROM " . DB_PREFIX . "facet_index fi
        WHERE fi.facet_type = 9
        AND NOT EXISTS (
          SELECT 1
          FROM " . DB_PREFIX . "product_special ps
          WHERE ps.product_id = fi.product_id
            AND ps.store_id = fi.store_id
            AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW())
            AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())
        )
        AND NOT EXISTS (
          SELECT 1
          FROM " . DB_PREFIX . "product_discount pd
          WHERE pd.product_id = fi.product_id
            AND pd.store_id = fi.store_id
            AND (pd.date_start = '0000-00-00' OR pd.date_start < NOW())
            AND (pd.date_end = '0000-00-00' OR pd.date_end > NOW())
        )
        $storeWhere
    ");

    // 9. Featured
    $this->db->query("
      DELETE fi
      FROM " . DB_PREFIX . "facet_index fi
      LEFT JOIN " . DB_PREFIX . "product_to_store p2s
        ON p2s.product_id = fi.product_id
        AND p2s.store_id = fi.store_id
      WHERE fi.facet_type = 10
      AND (p2s.is_featured = 0 OR p2s.is_featured IS NULL)
      $storeWhere
    ");
  }

  public function getFacets($search = [], $selectedFacets = []) : array {
    $storeId          = (int) $this->session->data['store_id'];
    $languageId       = (int) $this->config->get('config_language_id');
    $searchRequest    = [];
    $prefilterFacets  = [];

    $prefilterFacets[] = "i.`store_id` = {$storeId}";

    $currentFacetType = (int)($search['facet_type'] ?? 0);

    foreach ($selectedFacets ?? [] as $facetType => $groups) {
      if ((int)$facetType === $currentFacetType) continue;

      foreach ($groups as $groupId => $values) {
        $values = array_filter(array_map('intval', $values));
        if (!$values) continue;

        $prefilterFacets[] = "
          EXISTS (
            SELECT 1
            FROM " . DB_PREFIX . "facet_index f
            WHERE f.product_id = i.product_id
              AND f.facet_type = " . (int)$facetType . "
              AND f.facet_group_id = " . (int)$groupId . "
              AND f.facet_value_id IN (" . implode(',', $values) . ")
          )
        ";
      }
    }

    // Dummy fallback for facet search so request doen't fail if no filter provided
    $searchRequest[] = '1'; 
    // Filter facets by name and group
    if (isset($search['facet_type'])) {
      $searchRequest[] = "(
        n.`facet_type` = " . (int) $search['facet_type'] . "
      )";
    }
    if (isset($search['name'])) {
      $searchRequest[] = "(
        n.`name` LIKE '%" . $this->db->escape($search['name']) . "%'
        OR n.`group_name` LIKE '%" . $this->db->escape($search['name']) . "%'
      )";
    }
    // if (isset($search['excludedIds'])) {
    //   $searchRequest[] = "f.`facet_value_id` NOT IN(" . implode(',', array_map('intval', $search['excludedIds'])) . ")";
    // }

		$sql = "
      WITH facet AS (
        -- 2. Get all facets of current category products
        SELECT
          i.`facet_value_id`,
          i.`facet_type`,
          i.`facet_group_id`,
          i.`store_id`,
          COUNT(DISTINCT(i.`product_id`)) AS count_product
        FROM " . DB_PREFIX . "facet_index i
        WHERE " . implode(" AND ", $prefilterFacets) . "
        GROUP BY i.facet_type, i.facet_group_id, i.facet_value_id
      )
      
      SELECT 
        f.`facet_value_id`, 
        f.`facet_type`, 
        f.`facet_group_id`,
        f.`count_product`,
        n.`name`,
        n.`group_name`
      FROM facet f
      LEFT JOIN " . DB_PREFIX . "facet_name n
        ON  n.`facet_value_id` = f.`facet_value_id`
        AND n.`facet_type` = f.`facet_type`
        AND n.`facet_group_id` = f.`facet_group_id`
        AND n.`store_id` = f.`store_id`
        AND n.`language_id` = {$languageId}
      -- 3. Filter facets by type and name  
      WHERE " . implode(" AND ", $searchRequest) . "
      LIMIT " . ((int) $this->config->get('config_limit_admin')) ?? 20 . "
    ";

    return $this->db->query($sql)->rows ?? [];
	}
}