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
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ContentChangeset extends DataObject
{
	public static $db = array(
		'Title' => 'Varchar(64)',
		'Status' => "Enum('Active,Review,Published','Active')",
		'PublishedDate' => 'SS_Datetime',
	);

	public static $has_one = array(
		'Owner' => 'Member',
	);

    public static $many_many = array(
		'Items' => 'SiteTree',
	);

	/**
	 * Gets all the changes in this changeset
	 *
	 * @return ComponentSet
	 */
	public function Changes() {
		$old = Versioned::current_stage();

		// need to do both live and staging...
		Versioned::reading_stage('Stage');
		$staged = $this->Items();
		Versioned::reading_stage('Live');
		$live = $this->Items();

		// This could probably be a lot faster, but it's late
		$staged->merge($live);
		$staged->removeDuplicates();

		Versioned::reading_stage($old);

		return $staged;
	}


	/**
	 * Removes an item from a changeset. This typically occurs when a piece of content has been
	 * forcibly published by an admin user. This is NOT the same as reverting the content - though the consequences
	 * may be similar (ie the changeset is set to 'inactive'
	 *
	 * @param SiteTree $item
	 */
	public function remove($object) {
		$this->Changes()->remove($object);
	}

	/**
	 * Add an object to the changeset
	 *
	 * @param SiteTree $object
	 */
	public function addItem($object) {
		$this->Changes()->add($object);
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
		$items = $this->Changes();
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
		$items = $this->Changes();
		foreach ($items as $object) {

			$this->revert($object);
		}
	}
}
?>