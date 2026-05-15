<?php
class ControllerExtensionModuleFacetFilter extends Controller {

	public function __construct($registry) {
		parent::__construct($registry);
	}

	public function index() {

		$this->load->model('catalog/product');
		$data['sortOrders'] = array_keys($this->model_catalog_product->getSortOrders());
		$facetTypes 		= $this->model_catalog_product->getFacetTypes();

		$this->load->model('extension/module/facet_filter');
		$this->load->language('extension/module/facet_filter');
		$settings = $this->config->get('module_facet_filter_settings');

		foreach ($data['sortOrders'] as $key => $sortOrder) {
			$data['sortOrders'][$key] = [
				'name'  => $this->language->get('sort_' . $sortOrder),
				'value' => $sortOrder,
			];
		}

		$route 				= (string) $this->request->get['route'];
		$path 				= $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
		$category_id 	= explode('_', (string) $path);
		$category_id 	= end($category_id) ?? null;
		
		// Interface data
		$data['facetSets'] = $this->getFacets();

		// Sort requests
		$data['requestSort'] = $this->request->get['sort'] ?? null;

		// Reset link
		$data['resetLink'] = '';
		$resetLinkRequest = [];
		if ($route === 'product/category') {
			$resetLinkRequest = [
				'path' => $this->request->get['path'] ?? null,
				'category_id' => $this->request->get['category_id'] ?? null,
				'sort' => $this->request->get['sort'] ?? null,
			];
		}
		if ($route === 'product/manufacturer') {
			$resetLinkRequest = [
				'manufacturer_id' => $this->request->get['manufacturer_id'],
				'sort' => $this->request->get['sort'] ?? null,
			];
		}
		if ($route === 'product/special') {
			$resetLinkRequest = [
				'sort' => $this->request->get['sort'] ?? null,
			];
		}
		$resetLinkRequest = array_filter($resetLinkRequest);
		$data['resetLink'] = $this->url->link($route, http_build_query($resetLinkRequest));
		// End reset link

		return $this->load->view('extension/module/facet_filter', $data);
	}

	public function getFacets() : array {
		$this->load->model('catalog/product');
		$facetTypes 		= $this->model_catalog_product->getFacetTypes();
		// Get facets that have own page type: categories, special, bestsellers, popular, latest, top rated, discounts, etc.
		$pageTypes 			= array_column(array_filter($facetTypes, fn($a) => $a['route'] !== false), 'route');
		// Add search type page explicitly because search page has no own facet type in facet dictionary 
		$pageTypes[] 		= 'product/search'; 
		$requestFilters = []; // Data from $this->request->get
		$filterSets 		= []; // Result to be returned
		$route 					= $this->request->get['route'] ?? null; // Route to apply page settings 
		$path 					= $this->request->get['category_id'] ?? $this->request->get['path'] ?? ''; // Path to fallback to current category id
		$parts       		= explode('_', (string) $path); // Current category id
		$category_id 		= (int) end($parts) ?: null;
		$settings 			= $this->config->get('module_facet_filter_settings');
		$settings				= $settings['distinct_categories'][$category_id] ?? $settings[$route] ?? null; // Settings by page type, default settings for categories and individual category settings
		
		// Only show filters on allowed page types according to settings
		if ($settings === null || $route === null || !in_array($route, $pageTypes)) {
			return [];
		}

		$this->load->language('extension/module/facet_filter');

		// Reverse dictionary
		// ['category_id' => 0, 'manufacturer_id' => 1, ...]
		$facetKeyMap = array_flip(array_column($facetTypes, 'facetType'));

		foreach ($this->request->get as $filterKey => $filterData) {
			if (isset($facetKeyMap[$filterKey])) {
				$requestFilters[$filterKey] = $filterData;
			}
		}

		// Add category id to requested filters
		if ($category_id !== null) {
			$requestFilters['category_id'] = $category_id;
		}

		// Add search request to requested filters, so facets also appear on search page
		if (isset($this->request->get['search']) && !empty($this->request->get['search'])) {
			$requestFilters['filter_name'] = $this->request->get['search'];
		}

		// Get facets and product count for requested filters
		$facets = $this->model_catalog_product->getFilters($requestFilters);
		
		foreach ($facets as $row) {
			$typeId 			= $row['facet_type_id'];
			$facetConfig 	= $facetTypes[$typeId];
			$facetKey    	= $facetConfig['facetType']; // 'category_id', 'option', 'is_available'...
			
			if (!isset($facetTypes[$typeId])) continue; // Skip if facet is not in dictionary
			if (!isset($settings[$facetKey])) continue; // Skip if facet type is not in settings

			// Определяем тип и группу для $filterSets
			if ($facetConfig['group'] !== false) {
				// Facets having gorup explicitly set in dictionary
				$type  = $facetConfig['group'];
				$group = $facetConfig['group'];
			} else {
				// Regular facets that have own group are grouped by facet_type + facet_group_id
				$type  = $facetKey;              // Facet type: 'category_id', 'option', 'attribute', etc.
				$group = $row['facet_group_id']; // Subgroup inside each type: subcategories, option values group, attribute group, etc.
			}

			$group_name = $row['facet_group_name'] ?? $this->language->get('group_' . $type); // Group name
			$facet_name = $row['facet_name'] ?? $this->language->get('facet_' . $facetKey); 	// Facet name

			// Create facet group if not exists
			if (!isset($filterSets[$type][$group])) {
				$filterSets[$type][$group] = [
					'group_name'        => $group_name,
					'filter_group_id'   => $group,
					'group_is_selected' => $row['group_is_selected'],
					'group_sort_order'  => $row['group_sort_order'] ?? 0,
					'filters'           => [],
				];
			}

			// Facet
			$facet = [
				'facet_id'           	=> $row['facet_value_id'],
				'facet_type'         	=> $facetKey,
				'facet_group_id'     	=> $row['facet_group_id'],
				'facet_name'         	=> $facet_name,
				'facet_sort_order'   	=> $row['facet_sort_order'] ?? 0,
				'base_count'         	=> $row['base_count'],
				'current_count'      	=> $row['current_count'] ?? 0,
				'facet_is_selected'  	=> $row['facet_is_selected'],
				'facet_is_available' 	=> $row['facet_is_available'],
			];

			$facet['url'] = $this->getFacetUrl($facet);

			$filterSets[$type][$group]['filters'][] = $facet;
		}

		// Sort facets if they have facet_sort_order
		foreach ($filterSets as $type => &$groups) {
			foreach ($groups as &$group) {
				usort(
					$group['filters'],
					fn($a, $b) => $a['facet_sort_order'] <=> $b['facet_sort_order']
				);
			}
			$groups = array_values($groups);
		}
		unset($groups, $group);
		
		foreach ($filterSets as $type => $groups) {
			$filterSets[$type] = array_values($groups);
		}

		return $filterSets;
	}

	// Create SEO URL for each filter
	public function getFacetUrl(array $facet) : string {
		
		$query 	 			= $this->request->get;
		$route 	 			= $query['route'];
		$currentIds 	= [];
		$facetId 			= (int) $facet['facet_id'];
		$facetType 		= $facet['facet_type'];

		unset($query['route']);

		// Filter and unique ids
		if (!empty($query[$facetType])) {
			$currentIds = array_filter(array_map('intval', explode(',', $query[$facetType])));
		}
		
		if (in_array($facetId, $currentIds, true)) {
			// Remove current facet id if already present in request of facets of the same type
			$currentIds = array_diff($currentIds, [$facetId]);
		} else {
			// Add current facet id if already present in request of facets of the same type
			$currentIds[] = $facetId;
		}
		
		$currentIds = array_values(array_unique($currentIds));
		
		// Sort ids ascending
		sort($currentIds);

		if ($currentIds) {
			$query[$facetType] = implode(',', $currentIds);
		} else {
			unset($query[$facetType]);
		}
		
		return $this->url->link($route, http_build_query($query));
	}
}