<?php
class ModelCatalogCommon extends Model {
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

  public function preparePagination($total = null) : array {
    if ($total === null) return [];
    $data               = [];
    $route              = $this->request->get['route'];
    $page               = (int) ($this->request->get['page'] ?? 1);
    $limit              = (int) ($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);
    $pagination         = new Pagination();
		$pagination->total  = $total;
		$pagination->page   = $page;
		$pagination->limit  = $limit;
		$pagination->url    = $this->url->link($route, '&page={page}');
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

}