<?php
class ControllerSeoRobotsEditor extends Controller {
  
  private $error = [];
  public function index() {
    $this->load->language('seo/robots_editor');
    $this->document->setTitle($this->language->get('heading_title'));
    
    $this->getForm();
  }
  
  public function getForm() : void {

    $this->load->language('seo/robots_editor');
    $user_token = (string) $this->session->data['user_token'];    
    
    $data = [
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'action'        => $this->url->link('seo/robots_editor/edit', 'user_token=' . $user_token, true),
      'cancel'        => $this->url->link('seo/robots_editor', 'user_token=' . $user_token, true),
      'user_token'    => $user_token,
    ];
    
    $data = [...$data, ...$this->getFormData()];
    
    $this->response->setOutput($this->load->view('seo/robots_editor_form', $data));
  }
  
  public function getFormData() : array {
    $formData = [];
    $path = dirname(DIR_CATALOG) . '/robots.txt';

    $formData['robots'] = file_exists($path) ? file_get_contents($path) : '';

    return $formData;
  }

  public function saveRobots(string $content): bool {
    $path = dirname(DIR_CATALOG) . '/robots.txt';

    if (!is_writable(dirname($path))) {
        return false;
    }

    return file_put_contents($path, $content, LOCK_EX) !== false;
  }
  
  public function fetchSave() {
    $this->load->language('seo/robots_editor');
    $json['success'] = $this->saveRobots($this->request->post['robots']);
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }
}