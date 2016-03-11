<?php

/**
 * Controller to process translation of pages batch action.
 *
 * @package batchactionstranslate
 */
class CMSBatchAction_TranslateController extends LeftAndMain
{

    private static $url_segment = 'batchtranslate';

    private static $menu_title = 'Translate into';

    private static $required_permission_codes = false;

    private static $allowed_actions = array(
        'TranslatePagesForm',
        'index'
    );

    /**
     * Function called by AJAX from LeftAndMain.BatchActionsTranslate.js
     * and from $this->doTranslatePages().
     *
     * @param Request $request , String $pageIDs
     * @return Form rendered within CMSDialog
     */
    public function index($request, $pageIDs = null)
    {
        $form = $this->TranslatePagesForm($pageIDs);
        return $this->customise(array(
            'Content' => ' ',
            'Form' => $form
        ))->renderWith('CMSDialog');
    }

    /**
     * Presents a form to select a language to translate the pages selected with batch actions into.
     *
     * @param string $pageIDs | null
     * @return Form $form
     */
    public function TranslatePagesForm($pageIDs = null)
    {
        Versioned::reading_stage('Stage'); // Needs this for changes to effect draft tree


        $languages = array_combine(Translatable::get_allowed_locales(), Translatable::get_allowed_locales());

        $action = FormAction::create('doTranslatePages', 'Translate')
            ->setUseButtonTag('true')
            ->addExtraClass('ss-ui-button ss-ui-action-constructive batch-form-actions')
            ->setUseButtonTag(true);

        $actions = FieldList::create($action);

        $allFields = new CompositeField();
        $allFields->addExtraClass('batch-form-body');

        if ($pageIDs == null) {
            $pageIDs = $this->getRequest()->getVar('PageIDs');
        } else {
            $allFields->push(new LiteralField("ErrorParent", '<p class="message bad">Invalid parent selected, please choose another</p>'));
        }

        $allFields->push(new HiddenField("PageIDs", "PageIDs", $pageIDs));
        $allFields->push(CheckboxSetField::create("NewTransLang", _t('Translatable.NEWLANGUAGE', 'New language'), $languages ));
		$allFields->push(OptionsetField::create("DuplicateChildren", _t('Translatable.DUPECHILDREN', 'Duplicate Children'), array('true' => 'Yes', 'false' => 'No') ));


        $headings = new CompositeField(
            new LiteralField(
                'Heading',
                sprintf('<h3 class="">%s</h3>', _t('HtmlEditorField.MOVE', 'Choose Language...')))
        );

        $headings->addExtraClass('cms-content-header batch-pages');

        $fields = new FieldList(
            $headings,
            $allFields
        );

        $form = Form::create(
            $this,
            'TranslatePagesForm',
            $fields,
            $actions
        );

        return $form;
    }

    /**
     * Handles the translation of pages and its relations
     *
     * @param array $data , Form $form
     * @return boolean | index function
     **/
    public function doTranslatePages($data, $form)
    {

        $languages = $data['NewTransLang'];
        $pages = explode(',', $data['PageIDs']);

        $status = array('translated' => array(), 'error' => array());

        foreach ($pages as $p) {
            $page = SiteTree::get()->byID($p);

            $id = $page->ID;

            foreach ($languages as $language) {
                if (!$page->hasTranslation($language)) {
                    try {
                        $translation = $page->createTranslation($language);
                        $successMessage = $this->duplicateRelations($page, $translation);
                        $status['translated'][$translation->ID] = array(
                            'TreeTitle' => $translation->TreeTitle,
                        );
                        $translation->destroy();
                        unset($translation);
						if ($data['DuplicateChildren'] == "true" ) {
							$children = $page->AllChildren();
							if ($children) {
								foreach ($children as $child) {
									$translation = $child->createTranslation($language);
									$successMessage = $this->duplicateRelations($child, $translation);
									$status['translated'][$translation->ID] = array(
										'TreeTitle' => $translation->TreeTitle,
									);
									$translation->destroy();
									unset($translation);
									$child->destroy();
									unset($child);
								}
							}
						}
                    } catch (Exception $e) {
                        // no permission - fail gracefully
                        $status['error'][$page->ID] = true;
                    }
                }
            }
            $page -> destroy();
            unset($page);
        }
        return '<input type="hidden" class="close-dialog" />';
    }

    public function applicablePages($ids)
    {
        return $this -> applicablePagesHelper($ids, 'canPublish', true, false);
    }
    public function duplicateRelations($obj, $new)
    {
        if ($has_manys = $obj -> has_many()) {
            foreach ($has_manys as $name => $class) {
                if ($related_objects = $obj -> $name()) {
                    // Debug::dump($related_objects);
                    foreach ($related_objects as $related_obj) {
                        $o = $related_obj -> duplicate(true);
                        $new -> $name() -> add($o);
                    }
                }
            }
        }
        if ($many_manys = $obj -> many_many()) {
            foreach ($many_manys as $name => $class) {
                if ($obj -> $name()) {
                    $new -> $name() -> setByIdList($obj -> $name() -> column());
                }
            }
            $new -> write();
        }
    }
}
