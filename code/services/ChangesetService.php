<?php

/**
 * A singletone service that manages changesets within the system.
 *
 * Use methods on this object to create and retrieve changesets, add new items to the current user's changeset,
 * move items between changesets, and submit changesets.
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ChangesetService {

	/**
	 * Create a new changeset for the given member (the current user is used as the default)
	 *
	 * @param String $name
	 * 			A name for this changeset, if applicable
	 * @return
	 * 			The new changeset object
	 */
	public function createChangeset($name = '', $member = null) {
		if (!$member) {
			$member = Member::currentUser();
		}

		$changeset = ContentChangeset::create();
		$changeset->Title = $name;
		$changeset->OwnerID = $member->ID;
		$changeset->write();

		return $changeset;
	}

	/**
	 * Gets a changeset from the DB, if the current user has access to it
	 *
	 * @param String $id
	 */
	public function getChangeset($id) {
		$member = Member::currentUser();

		$filter = array(
			'ID' => $id
		);

		if (!$member->HasPerm('ADMIN')) {
			$filter['OwnerID'] = $member->ID;
		}

		return DataList::create('ContentChangeset')->filter($filter)->first();
	}

	/**
	 * Gets the current changeset for this user if it exists
	 *
	 * @param Member $member
	 *
	 * @return ContentChangeset
	 */
	public function getChangesetForUser($member = null) {
		if (!$member) {
			$member = Member::currentUser();
		}

		if ($member == null) {
			throw new Exception("User not logged in");
		}

		$filter = array(
			'OwnerID' => $member->ID,
			'Status' => 'Active',
		);

		// we just want to get the first changeset
		$changeset = DataList::create('ContentChangeset')->filter($filter)->first();
		return $changeset;
	}

	/**
	 * Gets all the changesets that this user has access to.
	 *
	 * Users have access to any changeset they've created, and if they have the "ADMIN" permission, then
	 * they can also access other users' changesets.
	 *
	 * @TODO This should be expanded later to allow users to have permission to some specific changesets (ie when
	 * dealing with workflow)
	 *
	 * @param Member $member
	 */
	public function getAvailableChangesets($member = null, $status = 'Active') {
		if (!$member) {
			$member = Member::currentUser();
		}

		if ($member == null) {
			throw new Exception("User not logged in");
		}

		$filter = null;

		if (Permission::checkMember($member, 'ADMIN')) {
			$filter = array(
				'Status' => $status,
			);
		} else {
			$filter = array(
				'Status' => $status,
				'OwnerID' => $member->ID,
			);
		}

		$changesets = DataList::create('ContentChangeset')->filter($filter)->sort('Created ASC');
		return $changesets;
	}

	/**
	 * Gets the current changeset for a given content item
	 *
	 * @param SiteTree $object
	 */
	public function getChangesetForContent(DataObject $object, $state = null) {
		$filter = array();
		if ($state) {
			$filter['Status'] = $state;
		}

		$filter['ChangesetItems.OtherID:ExactMatch'] = $object->ID;
		$filter['ChangesetItems.OtherClass:ExactMatch'] = $object->class;

		$list = DataList::create('ContentChangeset');
		$list = $list->filter($filter);

		return $list->first();
	}
}
