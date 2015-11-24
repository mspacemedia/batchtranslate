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


        $languages = Translatable::get_allowed_locales();

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
        $allFields->push(LanguageDropdownField::create("NewTransLang", _t('Translatable.NEWLANGUAGE', 'New language'), $languages ));

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

        $language = $data['NewTransLang'];
        $pages = explode(',', $data['PageIDs']);

        foreach ($pages as $page) {
            $p = SiteTree::get()->byID($page);
            $p->createTranslation($language, true);

        }

    }
}