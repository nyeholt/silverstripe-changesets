<?php

/**
 * A dataobject that represents a changeset of pages in the system (ideally we'll support other content types, but
 * for now only sitetree objects support publishing....)
 *
 * A changeset is created anytime someone starts editing content. When that content is saved, it is added to the
 * user's current changeset (a new one is created if they don't have one). This allows items to be submitted all at once
 *
 * When an item is added to a changeset, a representative object (ContentChangesetItem) is added so that we can store
 * objects of any type in the changeset. 
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ContentChangeset extends DataObject {

	public static $db = array(
		'Title' => 'Varchar(64)',
		'Status' => "Enum('Active,Review,Published','Active')",
		'PublishedDate' => 'SS_Datetime',
		'LockType' => "Enum('Exclusive,Shared','Exclusive')" // whether a changeset is locked to a single user or not
	);
	public static $has_one = array(
		'Owner' => 'Member',
	);
	public static $has_many = array(
		'ChangesetItems' => 'ContentChangesetItem',
	);
	
	public static $dependencies = array(
		'ChangesetService' => '%$ChangesetService',
	);
	
	/**
	 * @var ChangesetService
	 */
	public $changesetService;

	/**
	 * We want to first get all our changesetitems and retrieve the objects for those
	 */
	public function getItems() {
		$old = Versioned::current_stage();

		$cs = $this->ChangesetItems();
		$items = new ArrayList();
		foreach ($cs as $record) {
			// we need to do this query on both Live and Stage because an object may existing
			// on either area (eg unpublish changes etc)
			Versioned::reading_stage('Stage');
			$item = $record->getRealItem();
			if ($item && $item->ID) {
				$items->push($item);
				// have the object, don't need the additional check
				continue;
			}

			Versioned::reading_stage('Live');
			$item = $record->getRealItem();
			if ($item && $item->ID) {
				$items->push($item);
			}
		}

		Versioned::reading_stage($old);

		return $items;
	}

	/**
	 * Get the content changeset item for a particular object for THIS changeset
	 *
	 * @param ContentChangesetItem $object
	 */
	public function changesetItemFor($object) {
		$filter = singleton('ChangesetUtils')->quote(array(
			'OtherID =' => $object->ID,
			'OtherClass =' => $object->class,
			'ChangesetID =' => $this->ID,
				));

		$filter = array(
			'OtherID'		=> $object->ID,
			'OtherClass'	=> $object->class,
			'ChangesetID'	=> $this->ID
		);
		$item = DataList::create('ContentChangesetItem')->filter($filter)->first();
		return $item;
	}

	/**
	 * Removes an item from a changeset. This typically occurs when a piece of content has been
	 * forcibly published by an admin user. This is NOT the same as reverting the content - though the consequences
	 * may be similar (ie the changeset is set to 'inactive'
	 * 
	 * @param SiteTree $item
	 */
	public function remove($object) {
		// find the ChangesetItem
		if ($item = $this->changesetItemFor($object)) {
			// $this->ChangesetItems()->remove($item);
			$item->delete();
		}
	}

	/**
	 * Add an object to the changeset
	 *
	 * @param SiteTree $object
	 */
	public function addItem($object) {
		// item is already in the changeset
		if ($item = $this->changesetItemFor($object)) {
			return;
		}
		if (!$this->ID) {
			throw new Exception("Changeset doesn't have an ID! $this->Title");
		}
		$change = ContentChangesetItem::create();
		$change->OtherID = $object->ID;
		$change->OtherClass = $object->class;
		$change->ChangesetID = $this->ID;
		$change->write();
	}

	/**
	 * Remove an object from a changeset
	 *
	 * @param SiteTree $object
	 * 			The object to remove
	 */
	public function revert(SiteTree $object) {
		switch ($object->getChangeType()) {
			case "Draft Deleted": {
					$object->doRestoreToStage();
					break;
				}
			case "Unpublished": {
					// republish... ? should never actually get here heh...
					throw new Exception("HOW TO HERE?");
					break;
				}
			case "Deleted": {
					break;
				}
			case "New": {
					$object->delete();
					break;
				}
			case "Edited":
			default: {
					$object->doRevertToLive();
				}
		}

		$this->remove($object);
	}

	/**
	 * Submit changeset to the published site
	 */
	public function submit() {
		$items = $this->getItems();
		foreach ($items as $item) {
			$item->setPublishingViaChangeset();
			switch ($item->getChangeType()) {
				case "Draft Deleted": {
						$item->doUnpublish();
						break;
					}
				case "Deleted": {
						break;
					}
				case "New":
				case "Edited":
				default: {
						$item->doPublish();
					}
			}
		}

		$this->Status = 'Published';
		$this->PublishedDate = date('Y-m-d H:i:s');
		$this->write();
	}

	/**
	 * Reverts an entire changeset
	 *
	 * @param ContentChangeset $changeset
	 */
	public function revertAll() {
		$items = $this->getItems();
		foreach ($items as $object) {
			$this->revert($object);
		}
	}

	public function unlock() {
		$this->LockType = 'Shared';
	}

	public function lock() {
		$this->LockType = 'Exclusive';
	}
}