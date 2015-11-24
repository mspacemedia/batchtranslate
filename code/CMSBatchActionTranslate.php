<?php
/**
 * Translate items to all available locales and store new pages as draft - CMS batch action.
 * Requires {@link Translatable::enabled} in your _config.php.
 *
 * Add batch actions by adding this to your _config.php:
 * CMSBatchActionHandler::register('translate', 'CMSBatchActionTranslate');
 *
 * @author Dirk Adler / KLITSCHE.DE
 * @upgraded by Ivan Krkotic / fake-media.com
 */
class CMSBatchActionTranslate extends CMSBatchAction
{
  function getActionTitle() {
    return _t('CMSBatchActions.TRANSLATE_PAGES_TO_DRAFT', 'Translate to draft');
  }
  function run(SS_List $pages) {
    $status = array('translated' => array(), 'error' => array());

    foreach ($pages as $page) {
      $id = $page->ID;
      foreach (Translatable::get_allowed_locales() as $locale) {
        if ($page->Locale == $locale)
          continue;
        if (!$page->hasTranslation($locale)) {
          try {
            $translation = $page->createTranslation($locale);
            $successMessage = $this->duplicateRelations($page, $translation);
            $status['translated'][$translation->ID] = array(
                'TreeTitle' => $translation->TreeTitle,
            );
            $translation->destroy();
            unset($translation);
          } catch (Exception $e) {
            // no permission - fail gracefully
            $status['error'][$page->ID] = true;
          }
        }
      }
      $page -> destroy();
      unset($page);
    }
    return $this->response(_t('CMSBatchActions.TRANSLATED_PAGES', 'Translated %d pages to draft site, %d failures'), $status);
  }
  function applicablePages($ids) {
    return $this -> applicablePagesHelper($ids, 'canPublish', true, false);
  }
  public function duplicateRelations($obj, $new) {
    if ($has_manys = $obj->has_many()) {
      foreach ($has_manys as $name => $class) {
        if ($related_objects = $obj->$name()) {
          // Debug::dump($related_objects);
          foreach ($related_objects as $related_obj) {
            $o = $related_obj->duplicate(true);
            $new->$name()->add($o);
          }
        }
      }
    }
    if ($many_manys = $obj->many_many()) {
      foreach ($many_manys as $name => $class) {
        if ($obj->$name()) {
          $new->$name() -> setByIdList($obj->$name()->column());
        }
      }
      $new->write();
    }
  }
}

/**
 * Translate and publish items to all other available locales - batch action.
 * Requires {@link Translatable::enabled} in your _config.php.
 *
 * Add batch actions by adding this to your _config.php:
 * CMSBatchActionHandler::register('translate-and-publish', 'CMSBatchActionTranslateAndPublish');
 *
 * @author Dirk Adler / KLITSCHE.DE
 */
class CMSBatchActionTranslateAndPublish extends CMSBatchActionTranslate
{
  function getActionTitle()
  {
    return _t('CMSBatchActions.TRANSLATE_PAGES_TO_LIVE', 'Translate and publish');
  }

  function getDoingText()
  {
    return _t('CMSBatchActions.TRANSLATING_PAGES_TO_LIVE', 'Translating and publishing pages');
  }

  function run(SS_List $objs)
  {
    return $this->batchaction(
        $objs,
        'doPublish',
        _t('CMSBatchActions.TRANSLATED_PAGES_TO_LIVE', 'Processed %s pages and saved %s translations (live)')
    );
  }
}

class CMSBatchActionTranslateSelected extends CMSBatchAction
{
  function getActionTitle()
  {
    return _t('CMSBatchActions.TRANSLATE_SELECTED_PAGES_TO', 'Translate to selected language');
  }

  function run(SS_List $objs)
  {
    // NULL: Run method is handled by Controller.
  }
}
?>