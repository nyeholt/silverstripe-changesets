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
		
		$grid->setList($this->changesetService->getAvailableChangesets());
		
		$config->getComponentByType('GridFieldDetailForm')->setItemEditFormCallback(function ($form, $itemRequest) {
			$actions = new FieldList();
			$actions->push(FormAction::create('submitall', 'Submit all'));
			$actions->push(FormAction::create('revertall', 'Revert all'));
			$form->setActions($actions);
		});
		
		$config->getComponentByType('GridFieldDetailForm')->setItemRequestClass('ChangesetDetail_ItemRequest');
		
		return $form;
	}

	/**
	 * Gets the changes for a particular user
	 */
	public function showchangeset() {
		return $this->renderWith('ChangesetsAdmin_right');
	}

	/**
	 * Submits all the items in the currently selected changeset
	 */
	public function submitall($params = null, $form = null) {
		$cid = isset($params['ID']) ? $params['ID'] : null;
		$changeset = null;
		if (!$cid) {
			throw new Exception("Invalid Changeset");
		}

		$changeset = singleton('ChangesetService')->getChangeset($cid);
		if ($changeset) {
			$changeset->submit();
			FormResponse::status_message(sprintf(_t('Changesets.SUBMITTED_CHANGESET', 'Submitted content in changeset %s'), $changeset->Title), 'good');
		} else {
			FormResponse::status_message(sprintf(_t('Changesets.CHANGESET_NOT_FOUND', 'Could not find changeset')), 'bad');
		}

		return FormResponse::respond();
	}

	/**
	 * Revert all edits for a particular changeset
	 */
	public function revertall($params = null, $form = null) {
		$cid = isset($params['ID']) ? $params['ID'] : null;
		$changeset = null;
		if (!$cid) {
			throw new Exception("Invalid Changeset");
		}

		$changeset = singleton('ChangesetService')->getChangeset($cid);
		if ($changeset) {
			$changeset->revertAll();
			FormResponse::status_message(sprintf(_t('Changesets.REVERTED_ALL', 'Reverted content in changeset %s'), $changeset->Title), 'good');
		} else {
			FormResponse::status_message(sprintf(_t('Changesets.CHANGESET_NOT_FOUND', 'Could not find changeset')), 'bad');
		}

		return FormResponse::respond();
	}

}

class ChangesetDetail_ItemRequest extends GridFieldDetailForm_ItemRequest {
	public function submitall($data, $form) {
		$changeset = $this->record;
		$changeset->submit();
		
		
	}
}