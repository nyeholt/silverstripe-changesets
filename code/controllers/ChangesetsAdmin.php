<?php

/**
 * Admin controller for changesets
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ChangesetsAdmin extends ModelAdmin
{
    public static $url_segment = 'changesets';
    public static $menu_title = 'Changes';
    
    private static $managed_models = array('ContentChangeset');
    
    private static $allowed_actions = array(
        'showchangeset',
        'submitall',
        'revertall',
        'EditForm',
    );
    
    private static $dependencies = array(
        'changesetService' => '%$ChangesetService'
    );

    /**
     *
     * @var ChangesetService
     */
    public $changesetService;

    /**
     * Include required JS stuff
     */
    public function init()
    {
        Versioned::reading_stage('Stage');
        parent::init();
    }

    /**
     * Get the list of changesets available to this user
     */
    public function Changesets()
    {
        $changesets = $this->changesetService->getAvailableChangesets();
        return $changesets;
    }

    public function EditForm($request = null, $vars = null)
    {
        $form = parent::EditForm();
        $grid = $form->Fields()->dataFieldByName('ContentChangeset');
        $config = $grid->getConfig();
        
        $config->removeComponentsByType('GridFieldDeleteAction');
        $config->removeComponentsByType('GridFieldFilterHeader');
        $config->removeComponentsByType('GridFieldAddNewButton');
        $config->removeComponentsByType('GridFieldExportButton');
        $config->removeComponentsByType('GridFieldPrintButton');
        
        
        // $grid->setList($this->changesetService->getAvailableChangesets());

        $config->getComponentByType('GridFieldDetailForm')->setItemEditFormCallback(function ($form, $itemRequest) {
            $actions = new FieldList();
            
            $record = $form->getRecord();
            if ($record->Status == 'Active') {
                $actions->push(FormAction::create('submitall', 'Submit all')
                        ->setUseButtonTag(true)
                        ->addExtraClass('ss-ui-action-constructive'));

                $actions->push(FormAction::create('revertall', 'Revert all')
                        ->setUseButtonTag(true)
                        ->addExtraClass('ss-ui-action-destructive'));

                $curmbs = $itemRequest->Breadcrumbs();
                if ($curmbs && $curmbs->count()>=2) {
                    $one_level_up = $curmbs->offsetGet($curmbs->count()-2);
                    $text = sprintf(
                        "<a class=\"%s\" href=\"%s\">%s</a>",
                        "crumb ss-ui-button ss-ui-action-destructive cms-panel-link ui-corner-all", // CSS classes
                        $one_level_up->Link, // url
                        _t('GridFieldDetailForm.CancelBtn', 'Cancel') // label
                    );
                    $actions->push(new LiteralField('cancelbutton', $text));
                }
                
                $record->updateCMSActions($actions);
            } elseif ($record->Status == 'Published') {
                $nodes = DataList::create('RemoteSyncroNode')->map();
                
                if ($nodes->count()) {
                    $form->Fields()->insertBefore(new DropdownField('TargetNode', 'Deploy to', $nodes), 'ChangesetItems');
                    $actions->push(FormAction::create('push', 'Deploy changes')
                        ->setUseButtonTag(true)
                        ->addExtraClass('ss-ui-action-constructive'));
                } else {
                    $form->Fields()->insertBefore(LiteralField::create('DeployNotice', '<strong>Create a Syncro node to deploy this changeset</strong>'), 'ChangesetItems');
                }
            }
            
            $form->setActions($actions);
            
        });
        
        $config->getComponentByType('GridFieldDetailForm')->setItemRequestClass('ChangesetDetail_ItemRequest');
        
        return $form;
    }
}

class ChangesetDetail_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $dependencies = array('syncrotronService' => '%$SyncrotronService');
    
    /**
     * @var SyncrotronService
     */
    public $syncrotronService;
    
    public function submitall($data, Form $form)
    {
        $changeset = $this->record;
        $changeset->submit();
        $controller = $form->getController()->getTopLevelController();
        $noActionURL = $controller->removeAction($data['url']);
        $controller->getRequest()->addHeader('X-Pjax', 'Content');
        
        return $controller->redirect($noActionURL, 302);
    }
    
    public function revertall($data, Form $form)
    {
        $changeset = $this->record;
        $changeset->revertAll();
        $controller = $form->getController()->getTopLevelController();
        $noActionURL = $controller->removeAction($data['url']);
        $controller->getRequest()->addHeader('X-Pjax', 'Content');
        return $controller->redirect($noActionURL, 302);
    }
    
    public function push($data, Form $form)
    {
        $toNode = $data['TargetNode'];
        if ($toNode) {
            $node = DataList::create('RemoteSyncroNode')->byID($toNode);
            if ($node) {
                $status = $this->syncrotronService->pushChangeset($this->record, $node);
                if (is_array($status)) {
                    $form->sessionMessage($status[1], ($status[0] ? 'good' : 'bad'));
                }
            }
        }
        
        $controller = $form->getController()->getTopLevelController();
        $controller->getRequest()->addHeader('X-Pjax', 'Content');
        return $controller->redirect($form->getController()->Link(), 302);
    }
}
