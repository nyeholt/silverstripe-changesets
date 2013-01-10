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
		
		return $form;
		
		$forUser = Member::currentUser();
		$cid = $this->request->param('ID');
		$changeset = null;
		if ($cid) {
			$changeset = singleton('ChangesetService')->getChangeset($cid);
		} else {
			$changeset = singleton('ChangesetService')->getChangesetForUser();
			if (!$changeset) {
				// just get any one
				$possibles = singleton('ChangesetService')->getAvailableChangesets();
				if ($possibles) {
					$changeset = $possibles->First();
				}
			}
		}

		$form = null;

		if ($changeset) {
			$tableFields = array(
				"Title" => _t('Changesets.PAGE_TITLE', 'Title'),
				'ClassName' => _t('Changesets.CONTENT_TYPE', 'Type'),
				"LastEdited" => _t('Changesets.LAST_EDITED', 'Last Edited'),
				'ChangeType' => _t('Changesets.CHANGE_TYPE', 'Type of Change')
			);

			$popupFields = new FieldSet(
							new TextField('Name', _t('CommentAdmin.NAME', 'Name')),
							new TextField('CommenterURL', _t('CommentAdmin.COMMENTERURL', 'URL'))
			);

			$idField = new HiddenField('ID', '', $changeset->ID);

			$table = new ComplexTableField($this, "Changes", "SiteTree", $tableFields);
			$table->setParentClass(false);
			$table->setFieldCasting(array(
				'LastEdited' => 'SSDatetime->Nice',
			));

			$items = $changeset->getItems();
			$table->setCustomSourceItems($items);
			$table->pageSize = $items->Count();
			$fields = new FieldSet(
							new TabSet('Root',
									new Tab(_t('Changesets.CHANGESETS', 'Changesets'),
											new LiteralField("Title", $changeset->Title . ' (' . $changeset->Owner()->Email . ')'),
											$idField,
											$table
									)
							)
			);

			$actions = new FieldSet();

			$actions->push(new FormAction('submitall', _t('Changesets.SUBMIT_ALL', 'Submit All Changes')));
			$actions->push(new FormAction('revertall', _t('Changesets.REVERT_ALL', 'Revert All Changes')));

			$form = new Form($this, "EditForm", $fields, $actions);
		}

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
