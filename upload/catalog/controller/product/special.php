<?php
class ControllerProductSpecial extends Controller {
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('catalog/common');
		// Set document SEO properties
		$this->model_catalog_common->addDocumentSeo([]);
		$data['products'] = [];
		
		// Prepare request: get base facet type from page route and other applied facets
		$request = $this->model_catalog_product->prepareGetProductsRequest($this->request->get);
		
		// Get products and product count
		$results = $this->model_catalog_product->getProducts(data: $request, withTotal: true);
		$data['total'] = $results['total'];
		
		// Common data: all controllers and serve requests - page, limit, sort
		$commonData = $this->model_catalog_common->prepageCommonData($results['total']);
		
		// Allowed request params for correct pagination links
		$allowedRequestParams = array_merge(array_column($this->model_catalog_product->getFacetTypes(), 'facetType'));
		// Pagination
		$pagination = $this->model_catalog_common->addPagination($allowedRequestParams, $results['total']);
		
		// Canonical links
		$this->model_catalog_common->addDocumentLinks($results['total']);

		// Products
		foreach ($results['products'] as $row) {
			$data['products'][] = $this->model_catalog_product->prepareProductMiniature($row);
		}

		// Merge all data
		$data = array_merge($data, $commonData, $pagination);

		// Display
		$this->response->setOutput($this->load->view('product/category', $data));
	}
}
