<?php
class ModelCatalogCommon extends Model {

  /**
   * Render common data
   * @param mixed $total
   * @return array
   */
  public function prepageCommonData($total = null) : array {
    $data = [];
    // $this->addDocumentLinks($total);
    // Pagination
    // $pagination               = $this->addPagination($total);
    // $data['pagination']       = $pagination['pagiantion'] ?? [];
    // $data['results']          = $pagination['results']    ?? '';

    $data['page']             = (int) ($this->request->get['page'] ?? 1);
    $data['limit']            = (int) ($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);
    $data['sort']             = $this->request->get['sort'] ?? null;
    $data['column_left']      = $this->load->controller('common/column_left');
		$data['column_right']     = $this->load->controller('common/column_right');
		$data['content_top']      = $this->load->controller('common/content_top');
		$data['content_bottom']   = $this->load->controller('common/content_bottom');
		$data['footer']           = $this->load->controller('common/footer');
		$data['header']           = $this->load->controller('common/header');

    return $data;
  }

  /**
   * render pagination
   * @param mixed $total
   * @return array
   */
  public function addPagination($allowedRequestParams = [], $total = null) : array {
    if ($total === null) return [];
    $requestParams      = [];
    $data               = [];
    $route              = $this->request->get['route'];
    $page               = (int) ($this->request->get['page'] ?? 1);
    $limit              = (int) ($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);

    foreach ($allowedRequestParams as $param) {
      if (isset($this->request->get[$param])) {
        $requestParams[$param] = $this->request->get[$param];
      }
    }
    $requestParams['page'] = '{page}';

    $pagination         = new Pagination();
		$pagination->total  = $total;
		$pagination->page   = $page;
		$pagination->limit  = $limit;
		$pagination->url    = $this->url->link($route, urldecode(http_build_query($requestParams)));
		$pagination         = $pagination->render();

		$results = sprintf(
      $this->language->get('text_pagination'), 
      ($total) 
        ? (($page - 1) * $limit) + 1 
        : 0, 
      ((($page - 1) * $limit) > ($total - $limit)) 
        ? $total 
        : ((($page - 1) * $limit) + $limit), 
      $total, 
      ceil($total / $limit)
    );

    $data['pagination'] = $pagination;
		$data['results'] 		= $results;
    return $data;
  }

  /**
   * Add canonical links to document header
   * @return void
   */
  public function addDocumentLinks($total = null) : void {
    $route = $this->request->get['route'] ?? '';
    $page  = (int) ($this->request->get['page'] ?? 1);
    $limit = (int) ($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);

    // Safely return
    if (empty($route)) return;

    if ($page == 1) {
      $this->document->addLink($this->url->link($route, '', true), 'canonical');
		} else {
      $this->document->addLink($this->url->link($route, 'page='. $page , true), 'canonical');
		}		
		
		if ($page > 1) {
			$this->document->addLink($this->url->link($route, (($page - 2) ? '&page='. ($page - 1) : ''), true), 'prev');
		}

		if ($total !== null && $limit && ceil($total / $limit) > $page) {
      $this->document->addLink($this->url->link($route, 'page='. ($page + 1), true), 'next');
		}
  }

  /**
   * Add essential SEO tags
   * @param mixed $data
   * @return void
   */
  public function addDocumentSeo($data = []) {
    $shortDesciption = '';
		$description = strip_tags($data['description'] ?? $data['h1'] ?? $data['name']);
		if (mb_strlen($description) > 255) {
			$description = explode('.', $description);
			$sentenceCount = 0;
			while (mb_strlen($shortDesciption) < 255) {
				$shortDesciption .= $description[$sentenceCount] . ". ";
				$sentenceCount ++;
			}
		} else {
			$shortDesciption = $description;
		}

    $this->document->setTitle($data['meta_title'] ?? $data['h1'] ?? $data['name']);
    $this->document->setDescription($data['meta_description'] ?? $data['meta_title'] ?? $shortDesciption);
    $this->document->setKeywords($data['meta_keyword']);
  }

  /**
   * Add JSON-LD microdata to document
   */
  public function addDocumentJsonLd(array $data) : void {
    if (!empty($data['product'])) {
      $schema = $this->buildProductMicroData($data['product']);
      if ($schema) {
        $this->document->setJsonLd($schema);
      }
    }
    // TODO Later add buildCategoryMicroData, buildArticleMicroData etc
  }

  /**
   * Build Product / ProductGroup schema
   */
  private function buildProductMicroData(array $product) : array {
    $currency = $this->config->get('config_currency') ?: 'UAH';

    $schema = [
      '@context' => 'https://schema.org',
      '@type'    => 'Product',
      'name'     => $product['name'],
    ];

    // Description cleanup tags and entities
    if (!empty($product['description'])) {
      $schema['description'] = mb_substr(strip_tags($product['description']), 0, 5000);
    }

    // GTIN
    foreach (['gtin13', 'gtin8', 'mpn', 'sku'] as $key) {
      if (!empty($product[$key])) {
        $schema[$key] = $product[$key];
      }
    }

    // URL
    if (!empty($product['url'])) {
      $schema['url'] = $product['url'];
    }

    // Images
    $images = $this->buildImageList($product);
    if (!empty($images)) {
      $schema['image'] = count($images) === 1 ? $images[0] : $images;
    }

    // Brand
    if (!empty($product['manufacturer'])) {
      $schema['brand'] = [
        '@type' => 'Brand',
        'name'  => $product['manufacturer'],
      ];
    }

    // Offers
    $schema['offers'] = $this->buildOffers($product, $currency);

    // Aggregate Rating
    if (!empty($product['rating']) && !empty($product['reviews'])) {
      $schema['aggregateRating'] = [
        '@type'         => 'AggregateRating',
        'ratingValue'   => $product['rating'],
        'reviewCount'   => (int) $product['reviews'],
        'worstRating'   => 1,
        'bestRating'    => 5,
      ];
    }

    // Reviews
    if (!empty($product['last_reviews'])) {
      $schema['review'] = $this->buildReviews($product['last_reviews']);
    }

    return $schema;
  }

  /**
   * Offer or AggregateOffer
   */
  private function buildOffers(array $product, string $currency) : array {
    // AggregateOffer
    if (isset($product['price_min'], $product['price_max'])) {
      $offer = [
        '@type'           => 'AggregateOffer',
        'priceCurrency'   => $currency,
        'lowPrice'        => $product['price_min'],
        'highPrice'       => $product['price_max'],
        'availability'    => 'https://schema.org/InStock',
        'itemCondition'   => 'https://schema.org/NewCondition',
      ];
      if (!empty($product['product_count'])) {
        $offer['offerCount'] = (int) $product['product_count'];
      }
      return $offer;
    }

    // Offer
    // Availability might be empty for categories and other product lists
    $availability = (!empty($product['quantity']) && $product['quantity'] > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

    $offer = [
      '@type' => 'Offer',
      'priceCurrency'     => $currency,
      'availability'      => $availability,
      'itemCondition'     => 'https://schema.org/NewCondition',
      'priceValidUntil'   => $product['special_date_end'] ?? date('Y-m-d', strtotime('+1 month')),
    ];

    // Apply canonical URL
    if (!empty($product['url'])) {
      $offer['url'] = $product['url'];
    }

    // Apply special price in priceSpecification if applicable
    if (!empty($product['special'])) {
      $offer['priceSpecification'] = [
        [
          '@type'         => 'UnitPriceSpecification',
          'price'         => $product['special'],      // current price
          'priceCurrency' => $currency,
        ],
        [
          '@type'         => 'UnitPriceSpecification',
          'priceType'     => 'https://schema.org/StrikethroughPrice',
          'price'         => $product['price'],        // Old price strikethrough
          'priceCurrency' => $currency,
        ],
      ];
    } else {
      $offer['price'] = $product['price'];
    }

    return $offer;
  }

  /**
   * List of ImageObject from main image and additional images
   */
  private function buildImageList(array $product, $allowedType = 'covers') : array {
    $images = [];

    if (!empty($product['image']) && !str_contains($product['image'], 'no_image')) {
      $images[] = [
        '@type'       => 'ImageObject',
        'url'         => $product['image'],
        'description' => $product['name'],
      ];
    }

    foreach ($product['images'] ?? [] as $type => $imageSet) {

      if ($type !== $allowedType) continue;
      
      foreach ($imageSet ?? [] as $img) {

        if (str_contains($img['src'], 'no_image')) continue;

        $images[] = [
          '@type'         => 'ImageObject', 
          'url'           => $img['src'],
          'height'        => $img['height'],
          'width'         => $img['width'],
          'description'   => $img['description'] ?? $product['name'],
        ];
      }
    }

    return $images;
  }

  /**
   * Reviews array
   */
  private function buildReviews(array $reviews) : array {
    $result = [];

    foreach ($reviews as $review) {
      $item = [
        '@type' => 'Review',
        'author' => [
          '@type' => 'Person',
          'name' => $review['author'],
        ],
        'reviewRating' => [
          '@type' => 'Rating',
          'ratingValue' => $review['review_rating'],
          'worstRating' => 1,
          'bestRating' => 5,
        ],
      ];

      if (!empty($review['review_date'])) {
        $item['datePublished'] = $review['review_date'];
      }
      if (!empty($review['review_text'])) {
        $item['reviewBody'] = $review['review_text'];
      }

      $result[] = $item;
    }

    return $result;
  }


  private function buildShippingDetails() {
          //   "shippingDetails": {
          // "@type": "OfferShippingDetails",
          // "shippingRate": {
          //   "@type": "MonetaryAmount",
          //   "value": 3.49,
          //   "currency": "USD"
          // },
          // "shippingDestination": {
          //   "@type": "DefinedRegion",
          //   "addressCountry": "US"
          // },
          // "deliveryTime": {
          //   "@type": "ShippingDeliveryTime",
          //   "handlingTime": {
          //     "@type": "QuantitativeValue",
          //     "minValue": 0,
          //     "maxValue": 1,
          //     "unitCode": "DAY"
          //   },
          //   "transitTime": {
          //     "@type": "QuantitativeValue",
          //     "minValue": 1,
          //     "maxValue": 5,
          //     "unitCode": "DAY"
          //   }
          // }
  }
}