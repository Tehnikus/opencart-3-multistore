<?php
class ControllerProductBestseller extends Controller {
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('catalog/common');

		$data = $this->getDescriptions();
		$allowedRequestParams = array_column($this->model_catalog_product->getFacetTypes(), 'facetType');
		
		// Get products with total, price_min, price_max, rating, reviews
		$data['products'] = [];
		$request  = $this->model_catalog_product->prepareGetProductsRequest($this->request->get);
		$products = $this->model_catalog_product->getProducts($request, true);
		$data = array_merge($data, $products);
		// Get common data - SEO tags, JSON-LD, robots, columns, pagination
		$commonData = $this->model_catalog_common->prepageCommonData($data, $allowedRequestParams, 'product');
		$data = array_merge($data, $commonData);

		$this->response->setOutput($this->load->view('product/category', $data));
	}

	/**
	 * Get filter page description in case when a combination of current page and filter parameters exists 
	 * E.g. "bestseller" + "brand" or "latest" + "option value"
	 * @return array
	 */
	private function getDescriptions() : array|bool {
		$data = [];
		// Get SEO filter page. If exists, use description data from there, othewise use category description
		$getRequest 	= $this->request->get;

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