<?php
class ControllerProductCategory extends Controller {
	public function index() {
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
			$this->response->redirect($this->url->link('common/not_found', '', true));
		}
	}

	/**
	 * Get category data and SEO filter page data
	 * If filter page exists, replace category data with filter page data
	 * @return array
	 */
	private function getDescriptions() : array|bool {
		$this->load->model('catalog/product');
		$sortOrders = $this->model_catalog_product->getSortOrders();
		// Get SEO filter page. If exists, use description data from there, othewise use category description
		$getRequest 	= $this->request->get;
		$path        	= $getRequest['path'] ?? '';
		$parts       	= explode('_', (string) $path);
		$category_id 	= (int) end($parts) ?: null;
		$data 				= $this->model_catalog_category->getCategory($category_id);

		// Remove default sort order from GET request
		if (isset($getRequest['sort']) && $getRequest['sort'] === 'sort_order') {
			unset($getRequest['sort']);
		}
		// Remove furst page param from GET request
		if (isset($getRequest['page']) && $getRequest['page'] === '1') {
			unset($getRequest['page']);
		}

		// Return if category not exists
		if (!$data || empty($data)) {
			return false;
		}

		// Remove description and SEO description on all category pages except canonical: pagination, sort, filter
		if (!empty(array_diff_key($getRequest, ['path' => '', 'route' => '']))) {
			$data['description'] = '';
			$data['seoDescription'] = '';
		}

		// Get filter page
		$filterPage = $this->model_catalog_product->getFilterPage($getRequest);
		if ($filterPage && !empty($filterPage)) {
			$data = array_merge($data, $filterPage);
		}

		// Remove description and SEO description on pagination and sort filter pages except canonical
		if (!empty($filterPage) && (isset($getRequest['page']) || isset($getRequest['sort']))) {
			$data['description'] = '';
			$data['seoDescription'] = '';
		}

		// Set H1 fallback
		$data['h1'] = $data['h1'] ?? $data['name'];

		// Add page number to h1 and title
		if (isset($getRequest['page'])) {
			$data['h1'] .= ', ' . sprintf($this->language->get('page_seo_title'), (int) $getRequest['page']);
			$data['meta_title'] .= ', ' . sprintf($this->language->get('page_seo_title'), (int) $getRequest['page']);
		}

		// Add sort order to h1 and title
		if (isset($getRequest['sort']) && isset($sortOrders[$getRequest['sort']]) && $getRequest['sort'] !== 'sort_order') {
			$data['h1'] .= ', '. sprintf($this->language->get('sort_seo_title'), strtolower($this->language->get("sort_{$getRequest['sort']}")));
			$data['meta_title'] .= ', '. sprintf($this->language->get('sort_seo_title'), strtolower($this->language->get("sort_{$getRequest['sort']}")));
		}

		return $data;
	}
}
