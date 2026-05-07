<?php
class ControllerProductBestseller extends Controller {

	public function index() {
		$this->load->language('product/bestseller');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));
		$data = $this->prepageCommonData();
		$this->preparePagination('product/bestseller', $data);
		$data['breadcrumbs'] = $this->prepareBreadcrumbs();
		$data['products'] = [];
		$request = $this->model_catalog_product->prepareGetProductsRequest($this->request->get);
		$results = $this->model_catalog_product->getProducts($request, true);
		echo '<pre>' . htmlspecialchars(print_r($request, true)) . '</pre>';
		$data['total'] = $results['total'];
		
		foreach ($results['products'] as $row) {
			$this->load->model('catalog/product');
			$data['products'][] = $this->model_catalog_product->prepageProductMiniature($row);
		}

		$this->response->setOutput($this->load->view('product/bestseller', $data));
	}

  private function prepareBreadcrumbs() : array {
    $breadcrumbs = [];
    
    $breadcrumbs[] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
    ];

		$breadcrumbs[] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/bestseller')
    ];

    return $breadcrumbs;
  }

  private function preparePagination($route, &$data) {
    $pagination         = new Pagination();
		$pagination->total  = $data['total'];
		$pagination->page   = $data['page'];
		$pagination->limit  = $data['limit'];
		$pagination->url    = $this->url->link($route, '&page={page}');

		$pagination = $pagination->render();

		$results = sprintf(
      $this->language->get('text_pagination'), 
      ($data['total']) 
        ? (($data['page'] - 1) * $data['limit']) + 1 
        : 0, 
      ((($data['page'] - 1) * $data['limit']) > ($data['total'] - $data['limit'])) 
        ? $data['total'] 
        : ((($data['page'] - 1) * $data['limit']) + $data['limit']), 
      $data['total'], 
      ceil($data['total'] / $data['limit'])
    );

		if ($data['page'] == 1) {
      $this->document->addLink($this->url->link($route, '', true), 'canonical');
		} else {
      $this->document->addLink($this->url->link($route, 'page='. $data['page'] , true), 'canonical');
		}		
		
		if ($data['page'] > 1) {
			$this->document->addLink($this->url->link($route, (($data['page'] - 2) ? '&page='. ($data['page'] - 1) : ''), true), 'prev');
		}

		if ($data['limit'] && ceil($data['total'] / $data['limit']) > $data['page']) {
      $this->document->addLink($this->url->link($route, 'page='. ($data['page'] + 1), true), 'next');
		}

    $data['pagination'] = $pagination;
		$data['results'] = $results;
  }

  private function prepageCommonData() : array {
    $data = [];
    $data['page']             = (int) ($this->request->get['page'] ?? 1);
    $data['sort']             = $this->request->get['sort'] ?? 'sales';
    $data['limit']            = (int) ($this->request->get['limit'] ?? $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit') ?? 20);
    $data['sort']             = $this->request->get['sort'] ?? null;
		$data['order']            = $this->request->get['order'] ?? null;
    $data['column_left']      = $this->load->controller('common/column_left');
		$data['column_right']     = $this->load->controller('common/column_right');
		$data['content_top']      = $this->load->controller('common/content_top');
		$data['content_bottom']   = $this->load->controller('common/content_bottom');
		$data['footer']           = $this->load->controller('common/footer');
		$data['header']           = $this->load->controller('common/header');

    return $data;
  }
}