<?php /**
 * @file
 * Contains \Drupal\hax\Controller\HaxModeController.
 */

namespace Drupal\hax\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;
//use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to render a single node in HAX Mode.
 */
class HaxModeController extends NodeViewController {

  /**
   * {@inheritdoc}
   */
  public function hax_node_form(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    // Based on NodeViewController's view() method.

    $build = parent::view($node, $view_mode, $langcode);



    if ($view_mode == 'full' && \Drupal::config('hax.settings')->get('hax_autoload_element_node_view')) {

dpm('in HaxModeController::hax_node_form()');

      // generate autoload list
      $elementstring = \Drupal::config('hax.settings')->get('hax_autoload_element_list');
      // blow up based on space
      $elements = explode(' ', $elementstring);
      $components = '';
      foreach ($elements as $element) {
        $component = new \stdClass();
        $component->machine_name = $element;
        // pull in from webcomponents location
        $component->file = 'libraries/webcomponents/polymer/bower_components/' . $element . '/' . $element . '.html';
        $element = [
          '#tag' => 'link', // The #tag is the html tag
          '#attributes' => [ // Set up an array of attributes inside the tag
            'href' => base_path() . $component->file,
            'rel' => 'import',
          ],
        ];
        $build['#attached']['html_head'][] = [$element, 'webcomponent-' . $component->machine_name];
      }

      $appStoreConnection = array(
        'url' => base_path() . 'hax-app-store/' . \Drupal::csrfToken()->get(),
      );

      // TODO Tag renders, but you must place
      // <cms-hax open-default end-point body-offset-left app-store-connection>
      // into the input format first. Perhaps what we need to do is create our
      // own input format. Or in the least, this step needs to be in the README.
      // To be explored...

//      $loaded_node = Node::load($node->id());
//      dpm($loaded_node->getFieldDefinitions());

// $build['#cache']; // tags, context, keys...

      dpm($build);
// todo do get set here

      /*
      $node->get('body')->set(0, '
            <cms-hax open-default end-point="' . base_path() . 'hax-node-save/' . $node->id() . '/' . \Drupal::csrfToken()->get() . '" body-offset-left="' . \Drupal::config('hax.settings')->get('hax_offset_left') . '" app-store-connection=' . "'" . json_encode($appStoreConnection) . "'" . '>'
        . $components
        . check_markup($node->body[0]->value, $node->body[0]->format)
        . '</cms-hax>');
      */

      $build['body'][0]['#text'] =  '
          <cms-hax open-default end-point="' . base_path() . 'hax-node-save/' . $node->id() . '/' . \Drupal::csrfToken()->get() . '" body-offset-left="' . \Drupal::config('hax.settings')->get('hax_offset_left') . '" app-store-connection=' . "'" . json_encode($appStoreConnection) . "'" . '>'
        . $components .
        check_markup($node->body[0]->value, $node->body[0]->format)
        .'</cms-hax>';

    }











return $build;

    // TODO NOTES
    // This method only seems useful for adding attachments, but not for
    // altering. Much of the contents of $build['#node'] are protected
    // Is hax_node_view() a better place for altering the node field output?
    // Or are there other hooks we're missing?


    // Add HAX components to node.

    // Only apply on full view mode.
    if ($build['#view_mode'] == 'full') {

      //dpm($build);
      //dpm($build['#node']);
      //dpm($build['#node']->id());

      // fake a component to get it into the head of the document, heavy weighting
      $component = new \stdClass();
      $component->machine_name = 'cms-hax';
      $component->file = 'libraries/webcomponents/polymer/bower_components/cms-hax/cms-hax.html';
      $element = [
        '#tag' => 'link', // The #tag is the html tag
        '#attributes' => [ // Set up an array of attributes inside the tag
          'href' => base_path() . $component->file,
          'rel' => 'import',
        ],
      ];
      $build['#attached']['html_head'][] = [
        $element,
        'webcomponent-' . $component->machine_name
      ];


      /* This stuff is being added in hax_node_view().

          // generate autoload list
          $elementstring = \Drupal::config('hax.settings')->get('hax_autoload_element_list');
          // blow up based on space
          $elements = explode(' ', $elementstring);
          $components = '';
          foreach ($elements as $element) {
            // sanity check
            if (!empty($element)) {
              $components .= '<' . $element . ' slot="autoloader">' . '</' . $element . '>';
            }
          }
          $appStoreConnection = array(
            'url' => base_path() . 'hax-app-store/' . \Drupal::csrfToken()->get(),
          );
          // write content to screen, wrapped in tag to do all the work
          $content = '
          <cms-hax open-default end-point="' . base_path() . 'hax-node-save/' . $node->id() . '/' . \Drupal::csrfToken()->get() . '" body-offset-left="' . \Drupal::config('hax.settings')->get('hax_offset_left') . '" app-store-connection=' . "'" . json_encode($appStoreConnection) . "'" . '>'
          . $components .
            check_markup($node->body[0]->value, $node->body[0]->format)
          .'</cms-hax>';

          $response = new \Symfony\Component\HttpFoundation\Response();
          $response->setContent($content);
          $response->setMaxAge(1);
          error_log('in hax\DefaultController\_hax_node_form');
          return $response;
      */

    }

    return $build;
  }

  /**
   * The _title_callback for the page that renders a single node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(EntityInterface $node) {
    // TODO - doesn't appear to be working, but shows up in router. What gives?
    // TODO - make translatable
    return "HAX edit ". $this->entityManager->getTranslationFromContext($node)->label();
  }

}