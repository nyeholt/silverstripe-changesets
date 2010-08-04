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
 * An extension that makes an object's changes trackable in a changeset along with other content that
 * was changed at the same time. Objects must have the 'versionable' aspect applied. 
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ChangesetTrackable extends DataObjectDecorator
{
	/**
	 *
	 * @var A temporary flag that says whether we can publish or not
	 */
	private $publishingViaChangeset = false;

	/**
	 * Who is this obect locked by?
	 *
	 * Cache variable that is set any time this info is needed
	 */
	protected $lockedBy = false;

	/**
	 * An instance method that sets temporarily that this object can be published.
	 *
	 * This is called by the changeset service just before it calls 'doPublish'. If this flag isn't set
	 * then the 'canPublish' check below will return false for this changeset. 
	 */
	public function setPublishingViaChangeset($v=true) {
		$this->publishingViaChangeset = $v;
	}

	/**
	 * Indicates who this change set is locked by
	 */
	public function lockedBy() {
		if (is_bool($this->lockedBy)) {
			$cs = $this->getCurrentChangeset();
			if ($cs) {
				$this->lockedBy = $cs->Owner();
			} else {
				$this->lockedBy = null;
			}
		}

		return $this->lockedBy;
	}

	/**
	 * Indicates whether this page can be locked
	 *
	 * @return boolean
	 */
	public function canBeLocked() {
		$cs = $this->getCurrentChangeset();
		return $cs ? $cs->LockType == 'Shared' : true;
	}

	/**
	 * What is the lock applied to this page?
	 */
	public function lockType() {
		$cs = $this->getCurrentChangeset();
		return $cs ? $cs->LockType : null;
	}

	/**
	 * Get the current changeset that this is associated with
	 *
	 * @return ContentChangeset
	 */
	public function getCurrentChangeset() {
		$service = singleton('ChangesetService');
		return $service->getChangesetForContent($this->owner, 'Active');
	}

	/**
	 * Get all the changesets that this page is in currently and historically
	 *
	 * @return DataObjectSet
	 */
	public function Changesets() {
		$service = singleton('ChangesetService');
		return $service->getChangesetForContent($this->owner);
	}

	/**
	 * Returns a string indicating the type of change that this was
	 *
	 * @return String
	 */
	public function getChangeType() {
		if ($this->owner->IsDeletedFromStage && $this->owner->ExistsOnLive) {
			return "Draft Deleted";
		}

		if ($this->owner->IsDeletedFromStage && !$this->owner->ExistsOnLive) {
			return "Deleted";
		}

		if ($this->owner->Status == "Unpublished") {
			return "Unpublished";
		}

		if ($this->owner->IsAddedToStage) {
			return "New";
		}

		if ($this->owner->IsModifiedOnStage) {
			return "Edited";
		}
	}

	/**
	 * Before writing content, add it into the user's current changeset if it isn't already
	 */
    public function onAfterWrite() {
		$this->addToChangeset();
	}

	/**
	 * Add this item into a particular changeset
	 */
	public function addToChangeset() {
		// We only add content into changesets that exists already - this is because until it exists, it doesn't
		// have an ID, meaning the relationship can't be created. From a usage standpoint this is okay... not ideal,
		// but users will always change something about a default created page before wanting it published (it also
		// means that non-modified default content doesn't get accidentally published...)
		$oid = $this->owner->ID;
		$mid = Member::currentUserID();
		if (!$this->owner->ID || !Member::currentUserID()) {
			return;
		}

		// first see if it's in an active changeset already
		$changeset = $this->getCurrentChangeset();
		if ($changeset) {
			return;
		}

		// if not, get the current user's changeset
		$service = singleton('ChangesetService');
		try {
			$changeset = $service->getChangesetForUser();

			if (!$changeset) {
				$changeset = $service->createChangeset(sprintf(_t('Changesets.DEFAULT_TITLE', '%s started at %s'), Member::currentUser()->getTitle(), date('Y-m-d H:i:s')));
			}

			if ($changeset) {
				$changeset->addItem($this->owner);
			}
		} catch (Exception $e) {
			SS_Log::log($e, SS_Log::ERR);
		}
	}

	/**
	 * Before content is published, lets see whether or not the item is the only piece of content in the current
	 * changeset - if it is, then we'll let it go, otherwise we'll prevent publication. 
	 */
	public function canPublish() {
		return $this->publishingViaChangeset;
	}

	/**
	 * Cannot directly delete an object from live - it must be deleted from draft, then have the change pushed through
	 * as part of a changeset submission
	 */
	public function canDeleteFromLive() {
		return $this->publishingViaChangeset;
	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 */
	public function canEdit() {
		$stage = Versioned::current_stage();
		
		$changeset = $this->getCurrentChangeset();
		if (!$changeset) {
			return 1;	// needs to be a 1 for the way ss's extensions work
		}

		if ($changeset->LockType == 'Shared') {
			return 1;
		}

		// check the owner of the changeset
		if ($changeset->OwnerID != Member::currentUserID()) {
			return 0;
		}
	}

	/**
	 * After an item is published, lets check to see whether the publication was part of the changeset publication
	 * process (canPublish flag will be true).
	 *
	 * If it is NOT true, it means that the publication happened
	 * because of something else in the system that we couldn't control (eg an admin could publish). This is okay,
	 * but we need to make sure that any active changeset for the object is cleaned up. 
	 */
	public function onAfterPublish() {
		// calling the local canpublish, not the object one which checks if the user is admin... 
		if (!$this->publishingViaChangeset) {
			$changeset = $this->owner->getCurrentChangeset();
			if ($changeset) {
				// remove this object, which will close the changeset if need be.
				$changeset->remove($this->owner);
			}
		}
	}

	/**
	 * If something is deleted, make sure to track that it has been so that we can unpublish it later also
	 *
	 */
	public function onAfterDelete() {
		$this->addToChangeset();
	}
}