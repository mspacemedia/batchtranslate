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
  function getActionTitle()
  {
    return _t('CMSBatchActions.TRANSLATE_PAGES_TO_DRAFT', 'Translate to draft');
  }

  function getDoingText()
  {
    return _t('CMSBatchActions.TRANSLATING_PAGES_TO_DRAFT', 'Translating pages');
  }

  function run(SS_List $objs)
  {
    return $this->batchaction(
        $objs,
        null,
        _t('CMSBatchActions.TRANSLATED_PAGES_TO_DRAFT', 'Processed %d pages and saved %d translations (draft)')
    );
  }

  public function batchaction(SS_List $objs, $helperMethod, $successMessage, $arguments = Array())
  {
    $status = array('modified' => array(), 'error' => array());
    $allowedLang = Translatable::get_existing_content_languages('SiteConfig');
    if ($allowedLang == null)
    {
      return $this->response($successMessage, $status);
    }
    else
    {
      $translated = 0;

      foreach($objs as $page)
      {
        foreach ($allowedLang as $key => $lang)
        {
          if ($page->Locale == $key) continue;
          if (! $page->hasTranslation($key))
          {
            try
            {
              $translation = $page->createTranslation($key);
              if ($helperMethod) $translation->$helperMethod();
              $translation->destroy();
              unset($translation);
              $translated++;
              $status['modified'][$publishedRecord->ID] = array(
                  'TreeTitle' => $publishedRecord->TreeTitle,
              );
            }
            catch (Exception $e)
            {
              // no permission - fail gracefully
            }
          }
        }

        $page->destroy();
        unset($page);
      }

      $message = sprintf($successMessage, $objs->Count(), $translated);
      return $this->response($message, $status);
    }

    return $this->response($successMessage, $status);
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
?>