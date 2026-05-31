<?php
class ControllerProductCategory extends Controller {
	public function index() {
		$this->load->language('product/category');
		// Load required models
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('catalog/common');

		// Get category data or filter page data if available
		$data = $this->getDescriptions();

		if ($data) {
			// Set allowed request params for pagination. Merge 'path' with all facet types, so filter pagination is built correctly
			$allowedRequestParams = array_merge(['path'], array_column($this->model_catalog_product->getFacetTypes(), 'facetType'));
			
			// Get products with total, price_min, price_max, rating, reviews
			$data['products'] = [];
			$request = $this->model_catalog_product->prepareGetProductsRequest($this->request->get);
			$products = $this->model_catalog_product->getProducts($request, true);
			$data = array_merge($data, $products);
			// Get common data - SEO tags, JSON-LD, robots, columns, pagination
			$commonData = $this->model_catalog_common->prepageCommonData($data, $allowedRequestParams, 'product');
			$data = array_merge($data, $commonData);

			$this->response->setOutput($this->load->view('product/category', $data));

		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/category', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	/**
	 * Get category data and SEO filter page data
	 * If filter page exists, replace category data with filter page data
	 * @return array
	 */
	private function getDescriptions() : array|bool {
		// Get SEO filter page. If exists, use description data from there, othewise use category description
		$getRequest 	= $this->request->get;
		$path        	= $getRequest['path'] ?? '';
		$parts       	= explode('_', (string) $path);
		$category_id 	= (int) end($parts) ?: null;
		$data 				= $this->model_catalog_category->getCategory($category_id);

		// Return if category not exists
		if (!$data || empty($data)) {
			return false;
		}

		// Get filter page
		$filterPage = $this->model_catalog_product->getFilterPage($getRequest);
		if ($filterPage && !empty($filterPage)) {
			$data = array_merge($data, $filterPage);
		}

		// Remove SEO description on all pages except canonical - without pages and sort order
		if ((isset($getRequest['page']) && $getRequest['page'] !== 1) || (isset($getRequest['sort']) && $getRequest['sort'] !== 'sort_order')) {
			$data['description'] = '';
			$data['seoDescription'] = '';
		}

		// Set H1 fallback
		$data['h1'] = $data['h1'] ?? $data['name'];

		return $data;
	}
}
