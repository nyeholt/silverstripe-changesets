<?php
/*

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.
*/

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
class ContentChangeset extends DataObject
{
	public static $db = array(
		'Title' => 'Varchar(64)',
		'Status' => "Enum('Active,Review,Published','Active')",
		'PublishedDate' => 'SS_Datetime',
		'LockType' => "Enum('Exclusive,Shared','Exclusive')"	// whether a changeset is locked to a single user or not
	);

	public static $has_one = array(
		'Owner' => 'Member',
	);

    public static $has_many = array(
		'ChangesetItems' => 'ContentChangesetItem',
	);

	/**
	 * We want to first get all our changesetitems and retrieve the objects for those
	 */
	public function getItems() {
		$old = Versioned::current_stage();

		$cs = $this->ChangesetItems();
		$items = new DataObjectSet();
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

		$item = DataObject::get_one('ContentChangesetItem', $filter);
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
		$change = new ContentChangesetItem();
		$change->OtherID = $object->ID;
		$change->OtherClass = $object->class;
		$change->ChangesetID = $this->ID;
		$change->write();
	}

	/**
	 * Remove an object from a changeset
	 *
	 * @param SiteTree $object
	 *			The object to remove
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
			case "New":  {
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