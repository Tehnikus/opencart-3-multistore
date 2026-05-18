<?php
class ModelCatalogCommon extends Model {

  /**
   * Render common data
   * @param mixed $total
   * @return array
   */
  public function prepageCommonData($total = null) : array {
    $data = [];
    $this->addDocumentLinks($total);
    // Pagination
    $pagination               = $this->preparePagination($total);
    $data['pagination']       = $pagination['pagiantion'] ?? [];
    $data['results']          = $pagination['results']    ?? '';

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
  public function preparePagination($allowedRequestParams = [], $total = null) : array {
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
    $description = explode('.', strip_tags($data['description']));
    $sentenceCount = 0;
    while (mb_strlen($shortDesciption) < 255) {
      $shortDesciption .= $description[$sentenceCount] . ". ";
      $sentenceCount ++;
    }
    $this->document->setTitle($data['meta_title'] ?? $data['h1'] ?? $data['name']);
    $this->document->setDescription($data['meta_description'] ?? $data['meta_title'] ?? $shortDesciption);
    $this->document->setKeywords($data['meta_keyword']);
  }

  /**
   * Add JSON-LD Microdata
   * @param mixed $data
   * @return void
   */
  public function addDocumentJsonLd($data = []) : void {
    $this->document->setJson('faq',             $data['faq']);
    $this->document->setJson('howTo',           $data['how_to']);
    $this->document->setJson('image',           $data['image']);
    $this->document->setJson('images',          $data['images']);
    $this->document->setJson('product',         $data['product']);
    $this->document->setJson('products',        $data['products']);
    $this->document->setJson('review',          $data['review']);
    $this->document->setJson('aggregateRating', $data['aggregateRating']);
    $this->document->setJson('productGroup',    $data['aggregateRating']);
    $this->document->setJson('Organization',    $data['Organization']);
    $this->document->setJson('LocalBusiness',   $data['LocalBusiness']);
    $this->document->setJson('Article',         $data['Article']);
    $this->document->setJson('BreadcrumbList',  $data['BreadcrumbList']);
    $this->document->setJson('ItemList',        $data['ItemList']);
  }

}