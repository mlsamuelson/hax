<?php /**
 * @file
 * Contains \Drupal\hax\Controller\DefaultController.
 */

namespace Drupal\hax\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the hax module.
 */
class DefaultController extends ControllerBase {

  public function _hax_node_access($op, \Drupal\node\NodeInterface $node, Drupal\Core\Session\AccountInterface $account) {
    if (user_access('use hax') && node_access($op, $node)) {
      return TRUE;
    }
    return FALSE;
  }

  public function _hax_node_save(\Drupal\node\NodeInterface $node, $token) {
    // ensure we had data PUT here and it is valid
    if ($_SERVER['REQUEST_METHOD'] == 'PUT' && drupal_valid_token($token, 'hax')) {
      // load the data from input stream
      $body = file_get_contents("php://input");
      $node->body[0]->value = $body;
      if (!isset($node->body[0]->format)) {
        $node->body[0]->format = filter_default_format();
      }
      node_save($node);
      // send back happy headers
      drupal_add_http_header('Content-Type', 'application/json');
      // define status
      drupal_add_http_header('Status', 200);
      $return = [
        'status' => 200,
        'message' => t('Save successful!'),
        'data' => $node,
      ];
      // output the response as json
      print drupal_json_output($return);
      exit;
    }
  }

  public function _hax_file_access($op, Drupal\Core\Session\AccountInterface $account) {
    if (user_access('use hax') && entity_access('create', 'file', $_FILES['file-upload']['type'])) {
      return TRUE;
    }
    return FALSE;
  }

  public function _hax_file_save($token) {
    $status = 403;
    // check for the uploaded file from our 1-page-uploader app
    // and ensure there are entity permissions to create a file of this type
    if (drupal_valid_token($token, 'hax') && isset($_FILES['file-upload']) && entity_access('create', 'file', $_FILES['file-upload']['type'])) {
      $upload = $_FILES['file-upload'];
      // check for a file upload
      if (isset($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
        // get contents of the file if it was uploaded into a variable
        $data = file_get_contents($upload['tmp_name']);
        $params = filter_var_array($_GET, FILTER_SANITIZE_STRING);
        // see if we had a file_wrapper defined, otherwise this is public
        if (isset($params['file_wrapper'])) {
          $file_wrapper = $params['file_wrapper'];
        }
        else {
          $file_wrapper = 'public';
        }
        // see if Drupal can load from this data source
        if ($file = file_save_data($data, $file_wrapper . '://' . $upload['name'])) {
          file_save($file);
          $file->url = file_create_url($file->uri);
          $return = ['file' => $file];
          $status = 200;
        }
      }
    }
    // send back happy headers
    drupal_add_http_header('Content-Type', 'application/json');
    // define status
    drupal_add_http_header('Status', 200);
    $return = [
      'status' => $status,
      'data' => $return,
    ];
    // output the response as json
    print drupal_json_output($return);
    exit;
  }

  public function _hax_load_app_store($token) {
    // ensure we had data PUT here and it is valid
    if (drupal_valid_token($token, 'hax')) {
      $appStore = module_invoke_all('hax_app_store');
      \Drupal::moduleHandler()->alter('hax_app_store', $appStore);
      $staxList = module_invoke_all('hax_stax');
      \Drupal::moduleHandler()->alter('hax_stax', $staxList);
      // send back happy headers
      drupal_add_http_header('Content-Type', 'application/json');
      // define status
      drupal_add_http_header('Status', 200);
      $return = [
        'status' => 200,
        'apps' => $appStore,
        'stax' => $staxList,
      ];
      // output the response as json
      print drupal_json_output($return);
      exit;
    }
  }

  public function _hax_node_form(\Drupal\node\NodeInterface $node) {
    // set page title
    drupal_set_title(t('HAX edit @title', [
      '@title' => $node->getTitle()
      ]), PASS_THROUGH);
    // fake a component to get it into the head of the document, heavy weighting
    $component = new stdClass();
    $component->machine_name = 'cms-hax';
    // pull in from webcomponents location
    $component->file = libraries_get_path('webcomponents') . '/polymer/bower_components/cms-hax/cms-hax.html';
    _webcomponents_add_to_head($component, 10000);
    // generate autoload list
    $elementstring = variable_get('hax_autoload_element_list', HAX_DEFAULT_ELEMENTS);
    // blow up based on space
    $elements = explode(' ', $elementstring);
    $components = '';
    foreach ($elements as $element) {
      // sanity check
      if (!empty($element)) {
        $components .= '<' . $element . ' slot="autoloader">' . '</' . $element . '>';
      }
    }
    $appStoreConnection = [
      'url' => base_path() . 'hax-app-store/' . drupal_get_token('hax')
      ];
    // write content to screen, wrapped in tag to do all the work
    $content = '
  <cms-hax open-default end-point="' . base_path() . 'hax-node-save/' . $node->id() . '/' . drupal_get_token('hax') . '" body-offset-left="' . variable_get('hax_offset_left', 0) . '" app-store-connection=' . "'" . json_encode($appStoreConnection) . "'" . '>' . $components . check_markup($node->body[0]->value, $node->body[0]->format) . '</cms-hax>';
    return $content;
  }

}
