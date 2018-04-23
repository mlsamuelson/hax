<?php

/**
 * @file
 * Contains \Drupal\hax\Form\HaxSettings.
 */

namespace Drupal\hax\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Link;
use Drupal\Core\Url;

class HaxSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hax_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hax.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hax.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $config = $this->config('hax.settings');

    $form['hax_offset_left'] = [
      '#type' => 'textfield',
      '#title' => t('Offset'),
      '#default_value' => $config->get('hax_offset_left'),
      '#description' => t("Helps with theme compatibility when positioning the context menu. Adjust this if HAX context menu doesn't correctly align with the side of your content when editing. Value is in pixels but should not include px. Some themes that mess with box-model may or may not have this issue."),
    ];
    // collapse default state
    $form['hax_autoload_element_list'] = [
      '#type' => 'textfield',
      '#title' => t('Elements to autoload'),
      '#default_value' => $config->get('hax_autoload_element_list'),
      '#maxlength' => 1000,
      '#description' => t("This allows for auto-loading elements known to play nice with HAX. If you've written any webcomponents that won't automatically be loaded into the page via that module this allows you to attempt to auto-load them when HAX loads. For example, if you have a video-player element in your bower_components directory and want it to load on this interface, this would be a simple way to do that. Spaces only between elements, no comma"),
    ];


    $hax = new \Drupal\hax\HAXService;
    $baseApps = $hax->baseSupportedApps();
    foreach ($baseApps as $key => $app) {
      $form['hax_' . $key . '_key'] = [
        '#type' => 'textfield',
        '#title' => t('@name API key', [
          '@name' => $app['name']
          ]),
        '#default_value' => $config->get('hax_' . $key . '_key'),
        '#description' => t('See') . ' ' . Link::fromTextAndUrl(t('@name developer docs', [
          '@name' => $app['name']
          ]), Url::fromUri($app['docs']))->toString() . ' ' . t('for details'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

}
?>
