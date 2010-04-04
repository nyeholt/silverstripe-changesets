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
	public function extraStatics() {
		return array(
			'belongs_many_many' => array(
				'Changesets' => 'ContentChangeset'
			),
		);
	}

	/**
	 *
	 * @var A temporary flag that says whether we can publish or not
	 */
	private $publishingViaChangeset = false;

	/**
	 * An instance method that sets temporarily that this object can be published.
	 *
	 * This is called by the changeset service just before it calls 'doPublish'. If this flag isn't set
	 * then the 'canPublish' check below will return false for this changeset. 
	 */
	public function setPublishingViaChangeset() {
		$this->publishingViaChangeset = true;
	}

	/**
	 * Get the current changeset that this is associated with
	 *
	 * @return ContentChangeset
	 */
	public function getCurrentChangeset() {
		$service = singleton('ChangesetService');
		return $service->getChangesetForContent($this->owner);
	}

	/**
	 * Get all the changesets that this page is in currently and historically
	 *
	 * @return DataObjectSet
	 */
	public function getChangesets() {

	}

	/**
	 * Before writing content, add it into the user's current changeset if it isn't already
	 */
    public function onBeforeWrite() {

		// We only add content into changesets that exists already - this is because until it exists, it doesn't
		// have an ID, meaning the relationship can't be created. From a usage standpoint this is okay... not ideal,
		// but users will always change something about a default created page before wanting it published (it also
		// means that non-modified default content doesn't get accidentally published...)
		if (!$this->owner->ID) {
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
				$changeset = $service->createChangeset(sprintf(_t('Changesets.DEFAULT_TITLE', 'Changeset Started at %s'), date('Y-m-d H:i:s')));
			}

			if ($changeset) {
				$service->addContentToChangeset($this->owner, $changeset);
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
		$val = $this->publishingViaChangeset ? 1 : 0;
		return $val;
	}

	/**
	 * Can only edit content that's NOT in another person's content changeset
	 */
	public function canEdit() {
		$changeset = $this->getCurrentChangeset();
		if (!$changeset) {
			return 1;
		}

		// check the owner of the changeset
		if ($changeset->OwnerID != Member::currentUserID()) {
			return 0;
		}
	}

	/**
	 * After an item is published, lets check to see whether the publication was part of the changeset publication
	 * process (canPublish flag will be true). If it is NOT true, it means that the publication happened
	 * because of something else in the system that we couldn't control (eg an admin could publish). This is okay,
	 * but we need to make sure that any active changeset for the object is cleaned up. 
	 */
	public function onAfterPublish() {
		// calling the local canpublish, not the object one which checks if the user is admin... 
		if (!$this->canPublish()) {
			$changeset = $this->owner->getCurrentChangeset();
			if ($changeset) {
				// remove this object, which will close the changeset if need be.
				$changeset->removeFromChangeset($this->owner);
			}
		}
	}
}
?>