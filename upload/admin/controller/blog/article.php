<?php
class ControllerBlogArticle extends Controller {
  
  private $error = [];
  public function index() {
    $this->load->language('blog/article');
    $this->load->model('blog/article');
    $this->document->setTitle($this->language->get('heading_title'));
    
    $this->getList();
  }
  
  public function getList() {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/article');
    $user_token = $this->session->data['user_token'];
    $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['page', 'order'])));
    
    // Get items list
    $filter_data = array(
      'sort'  => $this->request->get['sort'] ?? 'date_added',
      'order' => $this->request->get['order'] ?? 'DESC',
      'start' => (($this->request->get['page'] ?? 1) - 1) * $this->config->get('config_limit_admin'),
      'limit' => $this->config->get('config_limit_admin')
    );
    $items = $this->model_blog_article->getList($filter_data);
    // Set each item edit link
    foreach ($items as $key => $item) {
      $items[$key]['edit'] = $this->url->link('blog/article/edit', 'article_id=' . $item['article_id'] . '&user_token=' . $user_token, true);
    }
    $data = [
      'items'         => $items,
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'breadcrumbs'   => $this->displayBreadcrumbs(),
      'pagination'    => $this->getPagination()['pagination'],
      'results'       => $this->getPagination()['results'],
      'user_token'    => $user_token,
      'add'           => $this->url->link('blog/article/add', 'user_token=' . $user_token, true),
      'delete'        => $this->url->link('blog/article/delete', 'user_token=' . $user_token, true),
      'sort'          => $this->request->get['sort'] ?? 'date_added',
      'sort_name'     => $this->url->link('blog/article', 'user_token=' . $user_token . $this->getSortOrder('name') . $url, true),
    ];
    
    $this->response->setOutput($this->load->view('blog/article_list', $data));
  }
  
  public function getForm() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/article');
    $this->load->language('blog/article');
    
    $data       = [];
    $id         = $this->request->get['article_id'] ?? null;
    $user_token = $this->session->data['user_token'];
    $url        = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'languages'     => $this->model_localisation_language->getLanguages(),
      'stores'        => $this->model_setting_store->getMultistores(),
      'action'        => $id ? $this->url->link('blog/article/edit', 'user_token=' . $user_token . '&article_id=' . $id . $url, true) : $this->url->link('blog/article/add', 'user_token=' . $user_token . $url, true),
      'cancel'        => $this->url->link('blog/article', 'user_token=' . $user_token . $url, true),
    ];
    
    // Merge with errors array to hihlight faulty inputs. Merge saved data, POST data, errors and interface
    $data = [...$data, ...$this->error, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('blog/article_form', $data));
  }
  
  public function getFormData() : array {
    $formData       = [];
    $article_id = $this->request->get['article_id'] ?? null;
    $this->load->model('blog/article');
    $formData['article_description'] = $this->model_blog_article->getArticleDescription($article_id);
    // Replace actual data with POST data
    if ($this->request->server['REQUEST_METHOD'] == 'POST') {
      $formData = $this->request->post;
    }
    return $formData;
  }
  
  public function add() {
    $this->load->language('blog/article');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/article');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_blog_article->addArticle($this->request->post);
      $this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function edit() {
    $this->load->language('blog/article');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/article');
    
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
      $this->model_blog_article->editArticle($this->request->get['article_id'], $this->request->post);
      $this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getForm();
  }
  
  public function delete() {
    $this->load->language('blog/article');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('blog/article');
    
    if (isset($this->request->post['selected']) && $this->validateDelete()) {
      foreach ($this->request->post['selected'] as $article_id) {
        $this->model_blog_article->deleteArticle($article_id);
      }
      $this->session->data['success'] = $this->language->get('text_success_deleted');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));
      $this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
    }
    
    $this->getList();
  }
  
  public function displayBreadcrumbs() {
    $this->load->language('blog/article');
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'], true)
    ];
    
    return $breadcrumbs;
  }
  
  public function getPagination() {
    $this->load->model('blog/article');
    $total = $this->model_blog_article->getArticleTotal();
    $page = (int) ($this->request->get['page'] ?? 1);
    $params = ['sort', 'order'];
    $query = [];
    
    foreach ($params as $param) {
      if (isset($this->request->get[$param])) {
        $query[$param] = $this->request->get[$param];
      }
    }
    
    $url = $query ? '&' . http_build_query($query) : '';
    $pagination = new Pagination();
    $pagination->total = $total;
    $pagination->page  = $page;
    $pagination->limit = $this->config->get('config_limit_admin');
    $pagination->url = $this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
    $data['pagination'] = $pagination->render();
    $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total, ceil($total / $this->config->get('config_limit_admin')));
    
    return $data;
  }
  
  private function getSortOrder(string $column): string {
    $currentSort  = $this->request->get['sort'] ?? '';
    $currentOrder = $this->request->get['order'] ?? 'ASC';
    
    if ($currentSort === $column) {
      $order = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
    } else {
      $order = 'ASC';
    }
    
    return '&sort=' . $column . '&order=' . $order;
  }
  protected function validateForm() {
    if (!$this->user->hasPermission('modify', 'blog/article')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }
    
    foreach ($this->request->post['article_description'] as $language_id => $value) {
      if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
        $this->error['name'][$language_id] = $this->language->get('error_name');
      }
    }
    if ($this->error && !isset($this->error['warning'])) {
      $this->error['warning'] = $this->language->get('error_warning');
    }
    
    return !$this->error;
  }
  
  protected function validateDelete() {
    if (!$this->user->hasPermission('modify', 'blog/article')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }
    return !$this->error;
  }
}