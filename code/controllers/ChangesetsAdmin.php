<?php

/**
 * Admin controller for changesets
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ChangesetsAdmin extends ModelAdmin {
	static $url_segment = 'changesets';
	static $menu_title = 'Changes';
	
	public static $managed_models = array('ContentChangeset');
	
	public static $allowed_actions = array(
		'showchangeset',
		'submitall',
		'revertall'
	);
	
	public static $dependencies = array(
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
	public function init() {
		parent::init();
		Requirements::javascript('changesets/javascript/ChangesetAdmin.js');
	}

	/**
	 * Get the list of changesets available to this user
	 */
	public function Changesets() {
		$changesets = $this->changesetService->getAvailableChangesets();
		return $changesets;
	}

	public function EditForm($request = null, $vars = null) {
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
				if($curmbs && $curmbs->count()>=2){
					$one_level_up = $curmbs->offsetGet($curmbs->count()-2);
					$text = sprintf(
						"<a class=\"%s\" href=\"%s\">%s</a>",
						"crumb ss-ui-button ss-ui-action-destructive cms-panel-link ui-corner-all", // CSS classes
						$one_level_up->Link, // url
						_t('GridFieldDetailForm.CancelBtn', 'Cancel') // label
					);
					$actions->push(new LiteralField('cancelbutton', $text));
				}
			} else if ($record->Status == 'Published') {
				$actions->push(FormAction::create('push', 'Deploy changes')
						->setUseButtonTag(true)
						->addExtraClass('ss-ui-action-constructive'));
			}
			
			$form->setActions($actions);
			
		});
		
		$config->getComponentByType('GridFieldDetailForm')->setItemRequestClass('ChangesetDetail_ItemRequest');
		
		return $form;
	}

}

class ChangesetDetail_ItemRequest extends GridFieldDetailForm_ItemRequest {
	public function submitall($data, Form $form) {
		$changeset = $this->record;
		$changeset->submit();
		$controller = $form->getController()->getTopLevelController();
		$noActionURL = $controller->removeAction($data['url']);
		$controller->getRequest()->addHeader('X-Pjax', 'Content'); 
		
		return $controller->redirect($noActionURL, 302); 
	}
	
	public function revertall($data, Form $form) {
		$changeset = $this->record;
		$changeset->revertAll();
		$controller = $form->getController()->getTopLevelController();
		$noActionURL = $controller->removeAction($data['url']);
		$controller->getRequest()->addHeader('X-Pjax', 'Content'); 
		return $controller->redirect($noActionURL, 302); 
	}
	
	public function push($data, Form $form) {
		$controller = $form->getController()->getTopLevelController();
		$controller->getRequest()->addHeader('X-Pjax', 'Content'); 
		return $controller->redirect($noActionURL, 302); 
	}
}