<?php

namespace Drupal\lynx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements search lynx form.
 */
class SearchLynxForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_lynx_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
      '#attributes' => array(
        'placeholder' => t('Enter your search terms'),
      ),
      '#description' => $this->t('Enter the terms you wish to search for.'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];
    $form['#attached']['library'][] = 'lynx/lynx_search';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keyword = $form_state->getValue('keyword');
    if ($keyword) {
      $form_state->setRedirect('lynx.search_page', ['keyword' => $keyword]);
    }
  }

}
